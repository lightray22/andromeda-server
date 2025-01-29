<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto\CryptFields; if (!defined('Andromeda')) die();

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\Database\FieldTypes\{BaseField, NullStringType, ObjectRefT, NullObjectRefT};
use Andromeda\Core\Database\Exceptions\FieldDataNullException;

use Andromeda\Apps\Accounts\{Account, Authenticator};
use Andromeda\Apps\Accounts\Crypto\Exceptions\CryptoUnlockRequiredException;

/** Helper functions for an object with CryptFields */
trait CryptObject // TODO RAY !! unit test
{
    /**
     * Returns a list of all CryptFields in this object
     * @return list<CryptField>
     */
    abstract protected function GetCryptFields() : array;

    /** Sets the encryption state of all crypt fields */
    public function SetEncrypted(bool $crypt) : void
    {
        foreach ($this->GetCryptFields() as $field)
            $field->SetEncrypted($crypt);
    }
     
    /** 
     * Loads all objects owned by the given account
     * @return array<string, static>
     */
    abstract public static function LoadByAccount(ObjectDatabase $database, Account $owner) : array;

    /**
     * Loads any objects for the given account and decrypts their fields
     * @param ObjectDatabase $database database reference
     * @param Account $account account to load by
     * @param bool $init if true, set encrypted, else decrypted
     */
    public static function SetEncryptedByAccount(ObjectDatabase $database, Account $account, bool $init) : void 
    { 
        foreach (static::LoadByAccount($database, $account) as $obj) 
            $obj->SetEncrypted($init);
    }
}

/** 
 * A field that is encrypted with an account's crypto
 * 
 * The encryption uses the owner account's secret-key crypto (only accessible by them)
 */
abstract class CryptField extends BaseField
{
    /** 
     * field holding the account field to encrypt this field for
     * @var ObjectRefT<Account>|NullObjectRefT<Account> 
     */
    protected ObjectRefT|NullObjectRefT $account;

    /** nonce field to use for encryption */
    protected NullStringType $nonce;

    /** 
     * @param ObjectRefT<Account>|NullObjectRefT<Account> $account to encrypt for
     * @param NullStringType $nonce nonce field to encrypt with
     */
    public function __construct(string $name, ObjectRefT|NullObjectRefT $account, NullStringType $nonce)
    {
        parent::__construct($name);
        $this->account = $account;
        $this->nonce = $nonce;
    }

    /** Returns true if the field is stored encrypted in the DB */
    public function isEncrypted() : bool
    {
        return $this->nonce->TryGetValue() !== null;
    }

    /** Returns the account used with this object */
    public function TryGetAccount() : ?Account
    {
        return $this->account instanceof NullObjectRefT 
            ? $this->account->TryGetObject() 
            : $this->account->GetObject();
    }

    /** Returns true if the decrypted value is available */
    abstract public function isValueReady() : bool;

    /** 
     * Sets the encryption state of the field
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     */
    abstract public function SetEncrypted(bool $crypt) : bool;
    
    /** Unlocks account crypto for usage and returns it */
    protected function TryRequireCrypto() : ?Account
    {
        $account = $this->TryGetAccount();
        if ($account === null) return null;

        Authenticator::RequireCryptoFor($account);
        return $account;
    }
}

/** A possibly-null account-encrypted string */
class NullCryptStringType extends CryptField
{
    /** The possibly-encrypted value stored in the DB */
    protected ?string $dbvalue = null;
    /** The decrypted value (set when available) */
    protected ?string $plainvalue;

    /** @return $this */
    public function InitDBValue($value) : self
    {
        if ($value !== null)
            $value = (string)$value;

        $this->dbvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : ?string { return $this->dbvalue; }

    public function isValueReady() : bool
    { 
        return isset($this->plainvalue) || !$this->isEncrypted() || 
            ($this->TryGetAccount()?->isCryptoAvailable() ?? false);
    }

    public function SetEncrypted(bool $crypt) : bool
    {
        return $this->SetValue($this->TryGetValue(), $crypt);
    }
    
    /** 
     * Returns the field's value, decrypting if needed (maybe null) 
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     */
    public function TryGetValue() : ?string
    {
        if (!isset($this->plainvalue))
        {
            $nonce = $this->nonce->TryGetValue();
            if ($this->dbvalue !== null && $nonce !== null)
            {
                if (($account = $this->TryRequireCrypto()) === null)
                    throw new CryptoUnlockRequiredException();
                $this->plainvalue = $account->DecryptSecret($this->dbvalue, $nonce);
            }
            else $this->plainvalue = $this->dbvalue;
        }

        return $this->plainvalue;
    }

    /**
     * Sets the field's value
     * @param ?string $value string value (maybe null)
     * @param bool $docrypt if true, store encrypted - default is current state
     * @return bool true if the field's DB value was modified
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     */
    public function SetValue(?string $value, ?bool $docrypt = null) : bool
    {
        $this->plainvalue = $value;

        $docrypt ??= $this->isEncrypted();
        if ($docrypt)
        {
            // keep a non-null nonce to indicate encryption state
            $this->nonce->SetValue("");

            if ($value !== null)
            {
                $nonce = Crypto::GenerateSecretNonce();
                $this->nonce->SetValue($nonce);

                if (($account = $this->TryRequireCrypto()) === null)
                    throw new CryptoUnlockRequiredException();
                $value = $account->EncryptSecret($value, $nonce);
            }
        }
        else $this->nonce->SetValue(null);

        if ($value !== $this->dbvalue)
        {
            $this->NotifyModified();

            $this->dbvalue = $value;
            $this->delta++;
            return true;
        }

        return false;
    }
    
}

/** A non-null account-encrypted string */
class CryptStringType extends CryptField
{
    /** The possibly-encrypted value stored in the DB */
    protected string $dbvalue;
    /** The decrypted value (set when available) */
    protected string $plainvalue;

    /** @return $this */
    public function InitDBValue($value) : self
    {
        if ($value === null) 
            throw new FieldDataNullException($this->name);
        else $value = (string)$value;
        
        $this->dbvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : string { return $this->dbvalue; }

    public function isValueReady() : bool
    { 
        return isset($this->plainvalue) || !$this->isEncrypted() || 
            ($this->TryGetAccount()?->isCryptoAvailable() ?? false);
    }

    /** Returns true if this field's value is initialized */
    public function isInitialized() : bool { return isset($this->dbvalue); }
    
    public function SetEncrypted(bool $crypt) : bool
    {
        return $this->SetValue($this->GetValue(), $crypt);
    }
    
    /** 
     * Returns the field's value, decrypting if needed
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     */
    public function GetValue() : string
    {
        if (!isset($this->plainvalue))
        {
            $nonce = $this->nonce->TryGetValue();
            if ($nonce !== null)
            {
                if (($account = $this->TryRequireCrypto()) === null)
                    throw new CryptoUnlockRequiredException();
                $this->plainvalue = $account->DecryptSecret($this->dbvalue, $nonce);
            }
            else $this->plainvalue = $this->dbvalue;
        }

        return $this->plainvalue;
    }
    
    /**
     * Sets the field's value
     * @param string $value string value
     * @param bool $docrypt if true, store encrypted - default is current state
     * @return bool true if the field's DB value was modified
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     */
    public function SetValue(string $value, ?bool $docrypt = null) : bool
    {
        $this->plainvalue = $value;

        $docrypt ??= $this->isEncrypted();
        if ($docrypt)
        {
            if (($account = $this->TryRequireCrypto()) === null)
                throw new CryptoUnlockRequiredException();
            $nonce = Crypto::GenerateSecretNonce();
            $this->nonce->SetValue($nonce);

            $value = $account->EncryptSecret($value, $nonce);
        }
        else $this->nonce->SetValue(null);

        if (!isset($this->dbvalue) || $value !== $this->dbvalue)
        {
            $this->NotifyModified();

            $this->dbvalue = $value;
            $this->delta++;
            return true;
        }

        return false;
    }
}