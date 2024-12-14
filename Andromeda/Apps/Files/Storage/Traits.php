<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\{Input, SafeParams};

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

require_once(ROOT."/Apps/Accounts/Crypto/FieldCrypt.php"); use Andromeda\Apps\Accounts\Crypto\OptFieldCrypt;

/** Trait for storage classes that store a possibly-encrypted username and password */
trait UserPass
{
    use OptFieldCrypt;
    
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
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : Storage
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

/** A storage that does not support subfolders */
trait NoFolders
{
    public function supportsFolders() : bool { return false; }   
    
    public function isFolder(string $path) : bool
    {
        return !count(array_filter(explode('/',$path)));
    }
    
    protected function SubCreateFolder(string $path) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubDeleteFolder(string $path) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubRenameFolder(string $old, string $new) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubMoveFile(string $old, string $new) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubMoveFolder(string $old, string $new) : Storage { throw new FoldersUnsupportedException(); }
}

/** A storage that has a base path */
trait BasePath
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'path' => new FieldTypes\StringType()
        ));
    }
    
    public function GetClientObject(bool $activate = false) : array
    {
        return parent::GetClientObject($activate) + array(
            'path' => $this->GetPath()
        );
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --path fspath"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : Storage
    {
        $path = $input->GetParams()->GetParam('path')->GetFSPath();
        
        return parent::Create($database, $input, $filesystem)->SetPath($path);
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--path fspath]"; }
    
    public function Edit(Input $input) : Storage
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('path')) 
            $this->SetPath($params->GetParam('path')->GetFSPath());
        
        return parent::Edit($input);
    }
    
    /** Returns the full storage level path for the given root-relative path */
    protected function GetPath(string $path = "") : string
    {
        return $this->GetScalar('path').'/'.$path;
    }
    
    /** Sets the path of the storage's root */
    private function SetPath(string $path) : Storage { return $this->SetScalar('path',$path); }
}
