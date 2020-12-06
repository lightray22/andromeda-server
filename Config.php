<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Group.php");

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'features__createaccount' => null,
            'features__emailasusername' => null,
            'features__requirecontact' => null,
            'features__allowcrypto' => null,
            'default_group' => new FieldTypes\ObjectRef(Group::class)
        ));
    }
    
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database); }
    
    public function GetDefaultGroup() : ?Group      { return $this->TryGetObject('default_group'); }
    public function GetAllowCreateAccount() : bool  { return $this->TryGetFeature('createaccount') ?? false; }
    public function GetUseEmailAsUsername() : bool  { return $this->TryGetFeature('emailasusername') ?? false; }
    public function GetAllowCrypto() : bool         { return $this->TryGetFeature('allowcrypto') ?? true; }
    
    public function SetDefaultGroup(?Group $group) : self     { return $this->SetObject('default_group', $group); }
    public function SetAllowCreateAccount(bool $allow) : self { return $this->SetFeature('createaccount', $allow); }
    public function SetUseEmailAsUsername(bool $useem) : self { return $this->SetFeature('emailasusername', $useem); }
    public function SetAllowCrypto(bool $allow) : self        { return $this->SetFeature('allowcrypto', $allow); }
    
    const CONTACT_NONE = 0; const CONTACT_EXIST = 1; const CONTACT_VALID = 2;
    
    public function GetRequireContact() : int          { return $this->TryGetFeature('requirecontact') ?? self::CONTACT_NONE; }
    public function SetRequireContact(int $req) : self { return $this->SetFeature('requirecontact', $req); }
    
    const OBJECT_SIMPLE = 0; const OBJECT_ADMIN = 1;
    
    public function GetClientObject(int $level = 0) : array
    {
        $data = array(
            'features' => $this->GetAllFeatures()
        );
        
        if ($level == self::OBJECT_ADMIN) $data['default_group'] = $this->GetDefaultGroup()->ID();
        
        return $data;
    }
}