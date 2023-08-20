<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/Apps/Accounts/Crypto/Exceptions.php");

/** 
 * Represents an object that holds an authentication code that can be checked 
 * 
 * The key is stored as a hash and cannot be retrieved unless provided
 */
trait AuthObject
{
    /** Return the string length of the auth key */
    protected function GetKeyLength() : int { return 32; }
    
    /** Return the time cost for the hashing algorithm */
    protected function GetTimeCost() : int { return 1; }
    
    /** Return the memory cost in KiB for the hashing algorithm */
    protected function GetMemoryCost() : int { return 1024; }
    
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
        
        if (password_needs_rehash($hash, $algo = Utilities::GetHashAlgo(), $settings))
            $this->authkey->SetValue(password_hash($key, $algo, $settings));
        
        return true;
    }
    
    /**
     * Returns the auth key if available or null if none
     * @throws RawKeyNotAvailableException if the real key is not in memory
     */
    protected function TryGetAuthKey() : ?string
    {
        if ($this->authkey->TryGetValue() === null)
            return null; // no key/hash
        
        if (!isset($this->authkey_raw))
            throw new RawKeyNotAvailableException();
        return $this->authkey_raw;
    }

    /** 
     * Sets the auth key to a new random value 
     * @return string the new auth key
     */
    protected function InitAuthKey() : string
    {
        $key = Utilities::Random(static::GetKeyLength());
        $this->SetAuthKey($key); return $key;
    }
    
    // TODO took away BaseCreate here... was InitAuthkey() if second param true (default)
    
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
            $algo = Utilities::GetHashAlgo();
            $hash = password_hash($key, $algo, $settings);
        }
        
        $this->authkey->SetValue($hash);
        return $this;
    }
}