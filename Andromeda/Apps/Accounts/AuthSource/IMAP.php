<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\Errors\{BaseExceptions, ErrorManager};
use Andromeda\Core\IOFormat\SafeParams;

/** 
 * Uses an IMAP server for authentication
 * @phpstan-import-type ExternalJ from External
 * @phpstan-import-type AdminExternalJ from External
 * @phpstan-type IMAPJ array{protocol:key-of<self::PROTOCOLS>, hostname:string, port:?int, implssl:bool, secauth:bool}
 */
class IMAP extends External
{
    use TableTypes\TableNoChildren;
    
    /** Enum for which mail protocol to use */
    private FieldTypes\IntType $protocol;
    /** Hostname of the IMAP server */
    private FieldTypes\StringType $hostname;
    /** Port number to use (null for default) */
    private FieldTypes\NullIntType $port;
    /** True if implicit SSL should be used (not STARTTLS) */
    private FieldTypes\BoolType $implssl;
    /** True to use IMAP secure authentication */
    private FieldTypes\BoolType $secauth;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->protocol = $fields[] = new FieldTypes\IntType('protocol');
        $this->hostname = $fields[] = new FieldTypes\StringType('hostname');
        $this->port =     $fields[] = new FieldTypes\NullIntType('port');
        $this->implssl =  $fields[] = new FieldTypes\BoolType('implssl');
        $this->secauth =  $fields[] = new FieldTypes\BoolType('secauth');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    private const PROTOCOL_IMAP = 1; 
    private const PROTOCOL_POP3 = 2; 
    private const PROTOCOL_NNTP = 3;
    
    private const PROTOCOLS = array(
        'imap'=>self::PROTOCOL_IMAP,
        'pop3'=>self::PROTOCOL_POP3,
        'nntp'=>self::PROTOCOL_NNTP);
    
    public static function GetPropUsage() : string { return "--protocol imap|pop3|nntp --hostname hostname [--port ?uint16] [--implssl bool] [--secauth bool]"; }
    
    public static function Create(ObjectDatabase $database, SafeParams $params) : static
    {
        $obj = parent::Create($database, $params);
        
        $protocol = self::PROTOCOLS[$params->GetParam('protocol')
            ->FromAllowlist(array_keys(self::PROTOCOLS))];
        $obj->protocol->SetValue($protocol);
        
        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->port->SetValue($params->GetOptParam('port',null)->GetNullUint16());
        $obj->implssl->SetValue($params->GetOptParam('implssl',false)->GetBool());
        $obj->secauth->SetValue($params->GetOptParam('secauth',false)->GetBool());
        
        return $obj;
    }
    
    public function Edit(SafeParams $params) : self
    {
        if ($params->HasParam('hostname')) 
            $this->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        
        if ($params->HasParam('port')) 
            $this->port->SetValue($params->GetParam('port')->GetNullUint16());
        
        if ($params->HasParam('implssl')) 
            $this->implssl->SetValue($params->GetParam('implssl')->GetBool());
        
        if ($params->HasParam('secauth')) 
            $this->secauth->SetValue($params->GetParam('secauth')->GetBool());
        
        return $this;
    }
    
    /** @return key-of<self::PROTOCOLS> */
    private function GetProtocol() : string { return array_flip(self::PROTOCOLS)[$this->protocol->GetValue()]; }
    
    /**
     * Returns a printable client object for this IMAP
     * @return ($admin is true ? \Union<AdminExternalJ, IMAPJ> : ExternalJ)
     */
    public function GetClientObject(bool $admin) : array
    {
        return parent::GetClientObject($admin) + (!$admin ? [] : array(
            'protocol' => $this->GetProtocol(),
            'hostname' => $this->hostname->GetValue(),
            'port' => $this->port->TryGetValue(),
            'implssl' => $this->implssl->GetValue(),
            'secauth' => $this->secauth->GetValue()
        ));
    }
    
    /** Checks for the existence of the IMAP extension */
    public function PostConstruct(bool $created) : void
    {
        if (!function_exists('imap_open')) 
            throw new Exceptions\IMAPExtensionException();
    }
    
    public function VerifyUsernamePassword(string $username, string $password) : bool
    {
        $hostname = $this->hostname->GetValue(); 
        
        $port = $this->port->TryGetValue();
        if ($port !== null) $hostname .= ":$port";
        
        $implssl = $this->implssl->GetValue() ? 'ssl' : null;
        $secauth = $this->secauth->GetValue() ? 'secure' : null;
        
        $connectstr = implode("/",array_filter(array($hostname, $this->GetProtocol(), $implssl, $secauth)));

        try 
        { 
            $imap = imap_open("{{$connectstr}}", $username, $password, OP_HALFOPEN); 
            
            $success = ($imap !== false);
            if ($success) imap_close($imap);
            return $success;
        }
        catch (BaseExceptions\PHPError $e) 
        {
            $errman = $this->GetApiPackage()->GetErrorManager();
            $errman->LogException($e);

            if (($errs = imap_errors()) !== false) 
                foreach ($errs as $err)
            {
                assert(is_string($err)); // imap_errors returns strings
                $errman->LogException(new Exceptions\IMAPErrorException($err)); 
            }

            return false; 
        }
    }
}
