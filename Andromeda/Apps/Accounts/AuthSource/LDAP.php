<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\Errors\{BaseExceptions, ErrorManager};
use Andromeda\Core\IOFormat\SafeParams;

/** 
 * Uses an LDAP server for authentication
 * @phpstan-import-type ExternalJ from External
 * @phpstan-import-type AdminExternalJ from External
 * @phpstan-type LDAPJ array{hostname:string, secure:bool, userprefix:?string}
 */
class LDAP extends External
{
    use TableTypes\TableNoChildren;
    
    /** Hostname of the LDAP server to connect to */
    private FieldTypes\StringType $hostname;
    /** If true, use LDAP over SSL */
    private FieldTypes\BoolType $secure;
    /** LDAP username lookup prefix */
    private FieldTypes\NullStringType $userprefix;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->hostname =   $fields[] = new FieldTypes\StringType('hostname');
        $this->secure =     $fields[] = new FieldTypes\BoolType('secure');
        $this->userprefix = $fields[] = new FieldTypes\NullStringType('userprefix');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    public static function GetPropUsage() : string { return "--hostname hostname [--secure bool] [--userprefix ?utf8]"; }
    
    public static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        $obj = parent::Create($database, $params);
        
        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->secure->SetValue($params->GetOptParam('secure',false)->GetBool());
        $obj->userprefix->SetValue($params->GetOptParam('userprefix',null)->GetNullUTF8String());
        
        return $obj;
    }
    
    public function Edit(SafeParams $params) : self
    {
        if ($params->HasParam('hostname')) 
            $this->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        
        if ($params->HasParam('secure')) 
            $this->secure->SetValue($params->GetParam('secure')->GetBool());
        
        if ($params->HasParam('userprefix')) 
            $this->userprefix->SetValue($params->GetParam('userprefix')->GetNullUTF8String());
        
        return $this;
    }
    
    /**
     * Returns a printable client object for this LDAP
     * @return ($admin is true ? \Union<AdminExternalJ, LDAPJ> : ExternalJ)
     */
    public function GetClientObject(bool $admin) : array
    {
        return parent::GetClientObject($admin) + (!$admin ? [] : array(
            'hostname' => $this->hostname->GetValue(),
            'secure' => $this->secure->GetValue(),
            'userprefix' => $this->userprefix->TryGetValue()
        ));
    }
    
    /** @var ?resource */
    private $ldapConn = null;

    /** Checks for the existence of the LDAP extension */
    public function PostConstruct(bool $created) : void
    {        
        if (!function_exists('ldap_bind')) 
            throw new Exceptions\LDAPExtensionException();
    }
    
    /** Initiates a connection to the LDAP server */
    public function Activate() : self
    {
        if ($this->ldapConn !== null) return $this;
        
        $protocol = $this->secure->GetValue() ? "ldaps" : "ldap";
        
        $ldapConn = ldap_connect("$protocol://".$this->hostname->GetValue());
        if ($ldapConn === false) throw new Exceptions\LDAPConnectionFailure();
        $this->ldapConn = $ldapConn; // @phpstan-ignore-line PHP 7/8 ldapConn types differ
        
        ldap_set_option($this->ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldapConn, LDAP_OPT_REFERRALS, 0);
        
        return $this;
    }
    
    public function VerifyUsernamePassword(string $username, string $password) : bool
    {
        $this->Activate();
        assert($this->ldapConn !== null); // from Activate
        
        $prefix = $this->userprefix->TryGetValue(); 
        if ($prefix !== null) $username = "$prefix\\$username";
        
        try 
        {
            $success = ldap_bind($this->ldapConn, $username, $password);  // @phpstan-ignore-line PHP 7/8 ldapConn types differ
            
            ldap_close($this->ldapConn); // @phpstan-ignore-line PHP 7/8 ldapConn types differ
            unset($this->ldapConn);
            return $success;
        }
        catch (BaseExceptions\PHPError $e) 
        {
            $errman = $this->GetApiPackage()->GetErrorManager();
            $errman->LogException($e);
            
            if (($lerr = ldap_error($this->ldapConn)) !== "") // @phpstan-ignore-line PHP 7/8 ldapConn types differ
            {
                ldap_get_option($this->ldapConn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $lerr2); // @phpstan-ignore-line PHP 7/8 ldapConn types differ
                assert(is_string($lerr2)); // this ldap option returns a string

                $errman->LogException(new Exceptions\LDAPErrorException("$lerr: $lerr2"));
            }
            
            return false; 
        } 
    }
}
