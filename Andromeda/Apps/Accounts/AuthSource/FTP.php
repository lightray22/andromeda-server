<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\Errors\{BaseExceptions, ErrorManager};
use Andromeda\Core\IOFormat\SafeParams;

/** 
 * Uses an FTP server for authentication
 * @phpstan-import-type ExternalJ from External
 * @phpstan-import-type AdminExternalJ from External
 * @phpstan-type FTPJ array{hostname:string, port:?int, implssl:bool} 
 */
class FTP extends External
{
    use TableTypes\TableNoChildren;
    
    /** Hostname of the FTP server */
    private FieldTypes\StringType $hostname;
    /** Port number to use (null for default) */
    private FieldTypes\NullIntType $port;
    /** True if implicit SSL should be used (not STARTTLS) */
    private FieldTypes\BoolType $implssl;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->hostname = $fields[] = new FieldTypes\StringType('hostname');
        $this->port =     $fields[] = new FieldTypes\NullIntType('port');
        $this->implssl =  $fields[] = new FieldTypes\BoolType('implssl');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public static function GetPropUsage() : string { return "--hostname hostname [--port ?uint16] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        $obj = parent::Create($database, $params);

        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->port->SetValue($params->GetOptParam('port',null)->GetNullUint16());
        $obj->implssl->SetValue($params->GetOptParam('implssl',false)->GetBool());
        
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

        return $this;
    }
    
    /**
     * Returns a printable client object for this FTP
     * @return ($admin is true ? \Union<AdminExternalJ, FTPJ> : ExternalJ)
     */
    public function GetClientObject(bool $admin) : array
    {
        return parent::GetClientObject($admin) + (!$admin ? [] : array(
            'hostname' => $this->hostname->GetValue(),
            'port' => $this->port->TryGetValue(),
            'implssl' => $this->implssl->GetValue()
        ));
    }
    
    /** Asserts that the FTP extension exists */
    public function PostConstruct(bool $created) : void
    {
        if (!function_exists('ftp_connect')) 
            throw new Exceptions\FTPExtensionException();
    }
    
    public function VerifyUsernamePassword(string $username, string $password) : bool
    {
        $host = $this->hostname->GetValue(); 
        $port = $this->port->TryGetValue() ?? 0; // 0 to use default
        
        $ftpConn = $this->implssl->GetValue() ? 
            ftp_ssl_connect($host,$port) : ftp_connect($host,$port);

        if ($ftpConn === false)
            throw new Exceptions\FTPConnectionFailure();
        
        try 
        { 
            $success = ftp_login($ftpConn, $username, $password);

            ftp_close($ftpConn);
            return $success;
        }
        catch (BaseExceptions\PHPError $e) 
        { 
            $errman = $this->GetApiPackage()->GetErrorManager();
            $errman->LogException($e);
            return false; 
        }
    }
}
