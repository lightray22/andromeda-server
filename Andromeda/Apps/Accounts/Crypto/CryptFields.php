<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto\CryptFields; if (!defined('Andromeda')) die();

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\FieldTypes\{BaseField, NullStringType, ObjectRefT};
use Andromeda\Core\Database\Exceptions\FieldDataNullException;

use Andromeda\Apps\Accounts\{Account, Authenticator};

// TODO FILES possibly will need to bring back OptFieldCrypt, and the object-central parts of FieldCrypt (SetEncrypted?)

/** 
 * A field that is encrypted with an account's crypto
 * 
 * The encryption uses the owner account's secret-key crypto (only accessible by them)
 */
abstract class CryptField extends BaseField
{
    /** 
     * field holding the account field to encrypt this field for
     * @var ObjectRefT<Account> 
     */
    protected ObjectRefT $account;

    /** nonce field to use for encryption */
    protected NullStringType $nonce;

    /** 
     * @param ObjectRefT<Account> $account to encrypt for
     * @param NullStringType $nonce nonce field to encrypt with
     */
    public function __construct(string $name, ObjectRefT $account, NullStringType $nonce)
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

    /** Returns true if the decrypted value is available */
    abstract public function isValueReady() : bool;

    /** Sets the encryption state of the field */
    abstract public function SetEncrypted(bool $crypt) : bool;
    
    /** Unlocks account crypto for usage and returns it */
    protected function RequireCrypto() : Account
    {
        $account = $this->account->GetObject();
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
        return isset($this->plainvalue) || !$this->isEncrypted() || $this->account->GetObject()->isCryptoAvailable();
    }

    public function SetEncrypted(bool $crypt) : bool
    {
        return $this->SetValue($this->TryGetValue(), $crypt);
    }
    
    /** Returns the field's value, decrypting if needed (maybe null) */
    public function TryGetValue() : ?string
    {
        if (!isset($this->plainvalue))
        {
            $nonce = $this->nonce->TryGetValue();
            if ($this->dbvalue !== null && $nonce !== null)
            {
                $account = $this->RequireCrypto();
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

                $account = $this->RequireCrypto();
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
        return isset($this->plainvalue) || !$this->isEncrypted() || $this->account->GetObject()->isCryptoAvailable();
    }

    /** Returns true if this field's value is initialized */
    public function isInitialized() : bool { return isset($this->dbvalue); }
    
    public function SetEncrypted(bool $crypt) : bool
    {
        return $this->SetValue($this->GetValue(), $crypt);
    }
    
    /** Returns the field's value, decrypting if needed */
    public function GetValue() : string
    {
        if (!isset($this->plainvalue))
        {
            $nonce = $this->nonce->TryGetValue();
            if ($nonce !== null)
            {
                $account = $this->RequireCrypto();
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
     */
    public function SetValue(string $value, ?bool $docrypt = null) : bool
    {
        $this->plainvalue = $value;

        $docrypt ??= $this->isEncrypted();
        if ($docrypt)
        {
            $account = $this->RequireCrypto();
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