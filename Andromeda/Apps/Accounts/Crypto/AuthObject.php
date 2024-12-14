<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\FieldTypes;

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Exceptions\PasswordHashFailedException;

/** 
 * Represents an object that holds an authentication code that can be checked 
 * 
 * The key is stored as a hash and cannot be retrieved unless provided
 */
trait AuthObject
{
    /** 
     * Return the string length of the auth key
     * @return positive-int
     */
    protected static function GetKeyLength() : int { return 32; }
    
    /** 
     * Return the time cost for the hashing algorithm
     * @return positive-int
     */
    protected static function GetTimeCost() : int { return 1; }
    
    /** 
     * Return the memory cost in KiB for the hashing algorithm
     * @return positive-int
     */
    protected static function GetMemoryCost() : int { return 1024; }
    
    /** The hashed auth key stored in DB */
    private FieldTypes\NullStringType $authkey;
    
    /** The actual auth key if in memory */
    private string $authkey_raw;
    
    protected function AuthObjectCreateFields() : void
    {
        $fields = array();
        
        $this->authkey = $fields[] = new FieldTypes\NullStringType('authkey');

        $this->RegisterChildFields($fields);
    }

    /** Returns true if the given key is valid, and stores it in memory for TryGetAuthKey() */
    public function CheckKeyMatch(string $key) : bool
    {
        $hash = $this->authkey->TryGetValue();
        
        if ($hash === null || !password_verify($key, $hash)) return false;

        $this->authkey_raw = $key;
        
        $settings = array(
            'time_cost'=>static::GetTimeCost(), 
            'memory_cost'=>static::GetMemoryCost());
        
        if (password_needs_rehash($hash, $algo = PASSWORD_ARGON2ID, $settings))
        {
            $hash = password_hash($key, $algo, $settings);
            if (!is_string($hash)) // @phpstan-ignore-line PHP7.4 only can return false
                throw new PasswordHashFailedException();
            $this->authkey->SetValue($hash);
        }
        
        return true;
    }
    
    /**
     * Returns the raw auth key if available or null if none
     * @throws Exceptions\RawKeyNotAvailableException if the real key is not in memory
     */
    protected function TryGetAuthKey() : ?string
    {
        if ($this->authkey->TryGetValue() === null)
            return null; // no key/hash
        return $this->GetAuthKey();
    }

    /**
     * Returns the raw auth key
     * @throws Exceptions\RawKeyNotAvailableException if the real key is not in memory or is nullnull
     */
    protected function GetAuthKey() : string
    {
        if (!isset($this->authkey_raw))
            throw new Exceptions\RawKeyNotAvailableException();
        return $this->authkey_raw;
    }

    /** 
     * Sets the auth key to a new random value 
     * @return string the new auth key
     */
    protected function InitAuthKey() : string
    {
        $key = Utilities::Random(static::GetKeyLength());
        $this->SetAuthKey($key);
        return $key;
    }
    
    /**
     * Sets the auth key to the given value and hashes it
     * @param string $key new auth key
     * @return $this
     */
    protected function SetAuthKey(?string $key) : self 
    {
        if ($key === null)
        {
            unset($this->authkey_raw);
            $hash = null;
        }
        else
        {   
            $settings = array(
                'time_cost'=>static::GetTimeCost(),
                'memory_cost'=>static::GetMemoryCost());
            
            $this->authkey_raw = $key;
            $hash = password_hash($key, PASSWORD_ARGON2ID, $settings);
            if (!is_string($hash)) // @phpstan-ignore-line PHP7.4 only can return false
                throw new PasswordHashFailedException();
        }
        
        $this->authkey->SetValue($hash);
        return $this;
    }
}
