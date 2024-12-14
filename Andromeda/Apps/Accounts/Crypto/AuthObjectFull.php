<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, QueryBuilder};
use Andromeda\Apps\Accounts\Account;

/** A trait for getting a serialized user key with both the ID and auth key */
trait AuthObjectFull
{
    use AuthObject;
    
    protected abstract static function GetFullKeyPrefix() : string;

    /**
     * Returns the object ID from a full serialized ID+key
     * @param string $code the full user serialized code
     * @return ?string the object ID the code is for or null if invalid
     */
    public static function TryGetIDFromFullKey(string $code) : ?string
    {
        $code = explode(":", $code, 3);
        
        if (count($code) !== 3 || $code[0] !== static::GetFullKeyPrefix()) return null;
        
        return $code[1];
    }
        
    /**
     * Tries to load an AuthObject by the full serialized key - DOES NOT CheckFullKey()!
     * @param ObjectDatabase $database database reference
     * @param string $code the full user/serialized code
     * @param Account $account the owner of the authObject or null for any
     * @return ?static loaded object or null if not found
     */
    public static function TryLoadByFullKey(ObjectDatabase $database, string $code, ?Account $account = null) : ?self
    {
        $code = explode(":", $code, 3);
        
        if (count($code) !== 3 || $code[0] !== static::GetFullKeyPrefix()) return null;
        
        $q = new QueryBuilder(); $w = $q->Equals('id',$code[1]); 
        
        if ($account !== null) $w = $q->And($w,$q->Equals('account',$account->ID()));

        return $database->TryLoadUniqueByQuery(static::class, $q->Where($w));
    }
    
    /** Checks the given full/serialized key for validity, returns result */
    public function CheckFullKey(string $code) : bool
    {
        $code = explode(":", $code, 3);
        
        if (count($code) !== 3 || $code[0] !== static::GetFullKeyPrefix()) return false;
        
        return $this->CheckKeyMatch($code[2]);
    }
    
    /**
     * Gets the full serialized key value for the user or null if not set
     *
     * The serialized string contains both the key ID and key value
     * @return ?string full key or null if the auth key is not in memory
     * @throws Exceptions\RawKeyNotAvailableException if the real key is not in memory
     */
    public function TryGetFullKey() : ?string
    {
        return ($this->TryGetAuthKey() === null) ? null : $this->GetFullKey();
    }

    /**
     * Gets the full serialized key value for the user
     *
     * The serialized string contains both the key ID and key value
     * @return string full key if the auth key is not in memory
     * @throws Exceptions\RawKeyNotAvailableException if the real key is not in memory or is null
     */
    public function GetFullKey() : ?string
    {
        return implode(":",array(static::GetFullKeyPrefix(), $this->ID(), $this->GetAuthKey()));
    }
}
