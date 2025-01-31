<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\FieldTypes;
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Apps\Accounts\Crypto\CryptFields;

/** Trait for storage classes that store a possibly-encrypted username and password */
trait UserPass
{
    use CryptFields\CryptObject;

    /** The optional username to use with the storage */
    protected CryptFields\NullCryptStringType $username;
    /** The nonce to use if the username is encrypted */
    protected FieldTypes\NullStringType $username_nonce;
    /** The optional password to use with the storage */
    protected CryptFields\NullCryptStringType $password;
    /** The nonce to use if the password is encrypted */
    protected FieldTypes\NullStringType $password_nonce;

    protected function UserPassCreateFields() : void
    {
        $fields = array();
        $this->username_nonce = $fields[] = new FieldTypes\NullStringType('username_nonce');
        $this->username = $fields[] = new CryptFields\NullCryptStringType('username',$this->owner,$this->username_nonce);
        $this->password_nonce = $fields[] = new FieldTypes\NullStringType('password_nonce');
        $this->password = $fields[] = new CryptFields\NullCryptStringType('password',$this->owner,$this->password_nonce);
        $this->RegisterChildFields($fields);
    }

    /** @return list<CryptFields\CryptField> */
    protected function GetUserPassCryptFields() : array { return array($this->username, $this->password); }

    /** Returns the command usage for Create() */
    public static function GetUserPassCreateUsage() : string { return "[--username ?alphanum] [--password ?raw] [--fieldcrypt bool]"; }
    
    /** Performs cred-crypt level initialization on a new storage */
    public function UserPassCreate(SafeParams $params) : self
    {
        $this->SetEncrypted($params->GetOptParam('fieldcrypt',false)->GetBool());
        $this->username->SetValue($params->GetOptParam('username',null)->CheckLength(255)->GetNullAlphanum());
        $this->password->SetValue($params->GetOptParam('password',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        return $this;
    }
    
    /** Returns the command usage for Edit() */
    public static function GetUserPassEditUsage() : string { return "[--username ?alphanum] [--password ?raw] [--fieldcrypt bool]"; }
    
    /** Performs cred-crypt level edit on an existing storage */
    public function UserPassEdit(SafeParams $params) : self
    {
        if ($params->HasParam('fieldcrypt'))
            $this->SetEncrypted($params->GetParam('fieldcrypt')->GetBool());
        if ($params->HasParam('username')) 
            $this->username->SetValue($params->GetParam('username')->CheckLength(255)->GetNullAlphanum());
        if ($params->HasParam('password')) 
            $this->password->SetValue($params->GetParam('password',SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        return $this;
    }
    
    /**
     * Returns the printable client object of this trait
     * @return array{}
     */
    public function GetUserPassClientObject() : array
    {
        /*$retval = array();
        
        foreach (static::getEncryptedFields() as $field)
        {
            $retval[$field."_iscrypt"] = $this->isFieldEncrypted($field);
        }
        
        return $retval;*/
        /*return parent::GetClientObject($activate) + $this->GetUserPassClientObject() + array(
            'username' => $this->TryGetUsername(),
            'password' => (bool)($this->TryGetPassword()),
        );*/
        // TODO RAY !! implement me - also should have username and password bool?
        return array();
    }
}
