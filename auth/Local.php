<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\{BaseObject, SingletonObject};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;

require_once(ROOT."/apps/accounts/auth/LDAP.php");
require_once(ROOT."/apps/accounts/auth/IMAP.php");
require_once(ROOT."/apps/accounts/auth/FTP.php");

interface ISource
{
    public function ID() : string;
    public function GetAccountGroup() : ?Group;
    
    public function VerifyPassword(string $username, string $password) : bool;    
}

abstract class External extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array(
            'default_group' => new FieldTypes\ObjectRef(Group::class)
        );
    }
}

class Local extends SingletonObject implements ISource
{
    public function VerifyPassword(string $username, string $password) : bool
    {
        $account = Account::TryLoadByUsername($this->database, $username);
        if ($account === null) return false;
        
        $hash = $account->GetScalar('password');
        
        $correct = password_verify($password, $hash);
        
        if ($correct && password_needs_rehash($hash, Utilities::GetHashAlgo()))
            $account->SetScalar('password', self::HashPassword($password));
            
        return $correct;
    }
    
    public static function HashPassword(string $password) : string
    {
        return password_hash($password, Utilities::GetHashAlgo());
    }
    
    public function GetAccountGroup() : ?Group { return null; }
}

class Pointer extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'description' => null,
            'authsource' => new FieldTypes\ObjectPoly(ISource::class)
        ));
    }
    
    public static function LoadBySource(ObjectDatabase $database, ISource $source) : ?ISource
    {
        $criteria = array('authsource' => FieldTypes\ObjectPoly::GetValueFromObject($source));
        return self::LoadManyMatchingAll($database, $criteria)[0];
    }
    
    public static function TryLoadSourceByPointer(ObjectDatabase $database, string $pointer) : ?ISource
    {
        $authsource = self::TryLoadByID($database, $pointer);
        if ($authsource === null) return null; else return $authsource->GetSource();
    }
    
    public function GetSource() : ISource { return $this->GetObject('authsource'); }
    
    public function GetDescription()
    {
        return $this->TryGetScalar("description") ?? get_class($this->GetSource());
    }
    
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'description' => $this->GetDescription(),
        );
    }
}

