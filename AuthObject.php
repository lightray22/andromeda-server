<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class RawKeyNotAvailableException extends Exceptions\ServerException { public $message = "AUTHOBJECT_KEY_NOT_AVAILABLE"; }

abstract class AuthObject extends StandardObject
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'authkey' => null
        ));
    }
    
    const KEY_LENGTH = 32;
    
    const SETTINGS = array('time_cost' => 1, 'memory_cost' => 1024);

    public static function BaseCreate(ObjectDatabase $database) : self
    {
        $obj = parent::BaseCreate($database);
        $key = Utilities::Random(self::KEY_LENGTH);
        return $obj->SetAuthKey($key, true);
    }
    
    public function CheckKeyMatch(string $key) : bool
    {
        $hash = $this->GetAuthKey(true);
        $correct = password_verify($key, $hash);
        if ($correct) $this->SetAuthKey($key);
        return $correct;
    }
    
    protected function GetAuthKey(bool $asHash = false) : string
    {
        if (!$asHash && !$this->haveKey) 
            throw new RawKeyNotAvailableException();
        return $this->GetScalar('authkey', !$asHash);
    }
    
    private $haveKey = false;
    
    protected function SetAuthKey(string $key, bool $forceHash = false) : self 
    {
        $this->haveKey = true; $algo = Utilities::GetHashAlgo(); 

        if ($forceHash || password_needs_rehash($this->GetAuthKey(true), $algo, self::SETTINGS)) 
        {
            $this->SetScalar('authkey', password_hash($key, $algo, self::SETTINGS));
        }
        
        return $this->SetScalar('authkey', $key, true);
    }
    
    const OBJECT_METADATA = 0; const OBJECT_WITHSECRET = 1;
    
    public function GetClientObject(int $level = 0) : array
    {
        return ($level === self::OBJECT_WITHSECRET) ? array('authkey'=>$this->GetAuthKey()) : array();
    }
}
