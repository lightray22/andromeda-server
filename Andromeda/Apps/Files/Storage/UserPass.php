<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\{Input, SafeParams};
use Andromeda\Apps\Accounts\Account;

/** Trait for storage classes that store a possibly-encrypted username and password */
trait UserPass // TODO RAY !! change function names to be trait-specific
{
    //use OptFieldCrypt; // TODO RAY !! deleted from accounts, needs refactor
    
    protected static function getEncryptedFields() : array { return array('username','password'); }
    
    /** Gets the extra DB fields required for this trait */
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), self::GetFieldCryptFieldTemplate(), array(
            'username' => new FieldTypes\StringType(), 
            'password' => new FieldTypes\StringType(),
        ));
    }
    
    /**
     * Returns the printable client object of this trait
     * @return array<mixed> `{username:?string, password:bool}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        return parent::GetClientObject($activate) + $this->GetFieldCryptClientObject() + array(
            'username' => $this->TryGetUsername(),
            'password' => (bool)($this->TryGetPassword()),
        );
    }
    
    /** Returns the command usage for Create() */
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." ".self::GetFieldCryptCreateUsage()." [--username ?alphanum] [--password ?raw]"; }
    
    /** Performs cred-crypt level initialization on a new storage */
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : Storage
    {
        $params = $input->GetParams();
        
        return parent::Create($database, $input, $filesystem)->FieldCryptCreate($params)
            ->SetPassword($params->GetOptParam('password',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString())
            ->SetUsername($params->GetOptParam('username',null)->CheckLength(255)->GetNullAlphanum());
    }
    
    /** Returns the command usage for Edit() */
    public static function GetEditUsage() : string { return parent::GetEditUsage()." ".self::GetFieldCryptEditUsage()." [--username ?alphanum] [--password ?raw]"; }
    
    /** Performs cred-crypt level edit on an existing storage */
    public function Edit(Input $input) : Storage
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('password')) $this->SetPassword($params->GetParam('password',SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        if ($params->HasParam('username')) $this->SetUsername($params->GetParam('username')->CheckLength(255)->GetNullAlphanum());

        $this->FieldCryptEdit($params);
        return parent::Edit($input);
    }
    
    /** Returns the decrypted username */
    protected function TryGetUsername() : ?string { return $this->TryGetEncryptedScalar('username'); }
    
    /** Returns the decrypted password */
    protected function TryGetPassword() : ?string { return $this->TryGetEncryptedScalar('password'); }
    
    /**
     * Sets the stored username
     * @param ?string $username username
     * @return $this
     */
    protected function SetUsername(?string $username) : self { 
        return $this->SetEncryptedScalar('username',$username); }
    
    /**
     * Sets the stored password
     * @param ?string $password password
     * @return $this
     */
    protected function SetPassword(?string $password) : self { 
        return $this->SetEncryptedScalar('password',$password); }
}
