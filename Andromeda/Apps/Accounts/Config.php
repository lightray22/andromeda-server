<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseConfig, VersionInfo};
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;
 
use Andromeda\Apps\Accounts\Group;
  
/** 
 * App config stored in the database
 * @phpstan-type ConfigJ array{create_account:key-of<self::CREATE_TYPES>, username_iscontact:bool, require_contact:key-of<self::CONTACT_TYPES>, default_auth:?string}
 * @phpstan-type AdminConfigJ array{date_created:float, default_group:?string}
 */
class Config extends BaseConfig
{
    public static function getAppname() : string { return 'accounts'; }
    
    public static function getVersion() : string { 
        return VersionInfo::toCompatVer(andromeda_version); }
    
    use TableTypes\TableNoChildren;

    /** The setting for public account creation */
    private FieldTypes\IntType $create_account;
    /** The setting for requiring account contact info */
    private FieldTypes\IntType $require_contact;
    /** True if usernames are contact info (e.g. emails) */
    private FieldTypes\BoolType $username_isContact;
    /** 
     * default group for new accounts 
     * @var FieldTypes\NullObjectRefT<Group>
     */
    private FieldTypes\NullObjectRefT $default_group;
    /** 
     * default auth source to use 
     * @var FieldTypes\NullObjectRefT<AuthSource\External>
     */
    private FieldTypes\NullObjectRefT $default_auth;

    protected function CreateFields() : void
    {
        $fields = array();

        $this->create_account = $fields[] =     new FieldTypes\IntType('createaccount', default:0);
        $this->require_contact = $fields[] =    new FieldTypes\IntType('requirecontact', default:0);
        $this->username_isContact = $fields[] = new FieldTypes\BoolType('usernameiscontact', default:false);
        $this->default_group = $fields[]      = new FieldTypes\NullObjectRefT(Group::class, 'default_group');
        $this->default_auth = $fields[]       = new FieldTypes\NullObjectRefT(AuthSource\External::class, 'default_auth');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Creates a new Config singleton */
    public static function Create(ObjectDatabase $database) : static
    {
        return $database->CreateObject(static::class);
    }
    
    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return 
        "[--createaccount ".implode('|',array_keys(self::CREATE_TYPES))."] ".
        "[--requirecontact ".implode('|',array_keys(self::CONTACT_TYPES))."] ".
        "[--usernameiscontact bool] [--createdefgroup bool] [--default_auth ?id]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(SafeParams $params) : self
    {
        if ($params->HasParam('createaccount')) 
        {
            $param = $params->GetParam('createaccount')->FromAllowlist(array_keys(self::CREATE_TYPES));
            $this->create_account->SetValue(self::CREATE_TYPES[$param]);
        }
        
        if ($params->HasParam('requirecontact')) 
        {
            $param = $params->GetParam('requirecontact')->FromAllowlist(array_keys(self::CONTACT_TYPES));
            $this->require_contact->SetValue(self::CONTACT_TYPES[$param]);
        }
        
        if ($params->HasParam('usernameiscontact')) 
        {
            $param = $params->GetParam('usernameiscontact')->GetBool();
            $this->username_isContact->SetValue($param);
        }
        
        if ($params->GetOptParam('createdefgroup',false)->GetBool()) 
            $this->CreateDefaultGroup();
        
        if ($params->HasParam('default_auth'))
        {
            if (($id = $params->GetParam('default_auth')->GetNullRandstr()) !== null)
            {
                $manager = AuthSource\External::TryLoadByID($this->database, $id);
                if ($manager === null) throw new Exceptions\UnknownAuthSourceException();
            }
            else $manager = null;
            
            $this->SetDefaultAuth($manager);
        }
        
        return $this;
    }
    
    /** Returns the default group that all users are implicitly part of */
    public function GetDefaultGroup() : ?Group { return $this->default_group->TryGetObject(); }

    /** Creates a new default group whose implicit members are all accounts */
    private function CreateDefaultGroup() : self
    {
        if ($this->default_group->TryGetObjectID() !== null) return $this;
        
        $group = Group::Create($this->database, "Global Group");
        
        $this->default_group->SetObject($group);
        $group->PostDefaultCreateInitialize(); // init AFTER set default
        return $this;
    }
    
    /** Returns the auth manager that will be used by default */
    public function GetDefaultAuth() : ?AuthSource\External { return $this->default_auth->TryGetObject(); }
    
    /** Returns the ID of the auth manager that will be used by default */
    public function GetDefaultAuthID() : ?string { return $this->default_auth->TryGetObjectID(); }

    /** Sets the default auth manager to the given value */
    public function SetDefaultAuth(?AuthSource\External $manager) : self { $this->default_auth->SetObject($manager); return $this; }
    
    /** Only allow creating accounts with allowlisted usernames */
    public const CREATE_ALLOWLIST = 1; 
    /** Allow anyone to create a new account (public) */
    public const CREATE_PUBLIC = 2;
    
    /** @var array<string,int> */
    private const CREATE_TYPES = array(
        'disable'=>0, 
        'allowlist'=>self::CREATE_ALLOWLIST, 
        'public'=>self::CREATE_PUBLIC);
    
    /** Returns whether the API for creating new accounts is enabled */
    public function GetAllowCreateAccount() : int { return $this->create_account->GetValue(); }
    
    /** Returns whether emails should be used as usernames */
    public function GetUsernameIsContact() : bool { return $this->username_isContact->GetValue(); }
    
    /** Sets whether the API for creating new accounts is enabled */
    public function SetAllowCreateAccount(int $value) : self { $this->create_account->SetValue($value); return $this; }
    
    /** Sets whether emails should be used as usernames */
    public function SetUsernameIsContact(bool $value) : self { $this->username_isContact->SetValue($value); return $this; }
    
    /** Require that accounts have contact info */
    public const CONTACT_EXIST = 1; 
    /** Require that accounts have validated contact info */
    public const CONTACT_VALID = 2;
    
    /** @var array<string,int> */
    private const CONTACT_TYPES = array(
        'none'=>0, 
        'exist'=>self::CONTACT_EXIST, 
        'valid'=>self::CONTACT_VALID);
    
    /** Returns whether a contact for accounts is required or validated */
    public function GetRequireContact() : int { return $this->require_contact->GetValue(); }
    
    /* Sets whether a contact for accounts is required or validated */
    public function SetRequireContact(int $value) : self { $this->require_contact->SetValue($value); return $this; }
     
    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return ($admin is true ? \Union<AdminConfigJ, ConfigJ> : ConfigJ)
     */
    public function GetClientObject(bool $admin = false) : array
    {
        $data = array(
            'username_iscontact' => $this->username_isContact->GetValue(),
            'create_account' => array_flip(self::CREATE_TYPES)[$this->create_account->GetValue()],
            'require_contact' => array_flip(self::CONTACT_TYPES)[$this->require_contact->GetValue()],
            'default_auth' => $this->default_auth->TryGetObjectID()
        );
        
        if ($admin) 
        {
            $data['date_created'] = $this->date_created->GetValue();
            $data['default_group'] = $this->default_group->TryGetObjectID();
        }
        
        return $data;
    }
}
