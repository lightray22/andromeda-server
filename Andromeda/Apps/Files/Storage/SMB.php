<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\Input;

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ 
    if (!$init) SMB::DecryptAccount($database, $account); });

abstract class SMBBase1 extends FWrapper { use BasePath; }
abstract class SMBBase2 extends SMBBase1 { use UserPass; }

/**
 * Allows using an SMB/CIFS server for backend storage
 * 
 * Uses PHP libsmbclient.  Mostly uses the PHP fwrapper
 * functions but some manual workarounds are needed.
 * Uses fieldcrypt to allow encrypting the username/password.
 */
class SMB extends SMBBase2
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'workgroup' => new FieldTypes\StringType(),
            'hostname' => new FieldTypes\StringType()
        ));
    }
    
    /**
     * Returns a printable client object of this SMB storage
     * @return array<mixed> `{workgroup:?string, hostname:string}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        return parent::GetClientObject($activate) + array(
            'workgroup' => $this->TryGetScalar('workgroup'),
            'hostname' => $this->GetScalar('hostname')
        );
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--workgroup ?alphanum]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        $params = $input->GetParams();
        
        return parent::Create($database, $input, $filesystem)
            ->SetScalar('workgroup', $params->GetOptParam('workgroup',null)->CheckLength(255)->GetNullAlphanum())
            ->SetScalar('hostname', $params->GetParam('hostname')->GetHostname());
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--workgroup ?alphanum]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
    
        if ($params->HasParam('workgroup')) $this->SetScalar('workgroup', $params->GetParam('workgroup')->CheckLength(255)->GetNullAlphanum());
        
        if ($params->HasParam('hostname')) $this->SetScalar('hostname', $params->GetParam('hostname')->GetHostname());
        
        return parent::Edit($input);
    }
    
    /** Returns the SMB workgroup to use (or null) */
    public function TryGetWorkgroup() : ?string { return $this->TryGetScalar('workgroup'); }

    /** Checks for the SMB client extension */
    public function PostConstruct() : void
    {
        if (!function_exists('smbclient_version')) throw new Exceptions\SMBExtensionException();
    }
    
    private $state;
    
    public function Activate() : self
    {
        if (isset($this->state)) return $this;
        
        $state = smbclient_state_new();
        
        if (!is_resource($state) || smbclient_state_init($state) !== true)
            throw new Exceptions\SMBStateInitException();
        
        if (!smbclient_option_set($state, 
            SMBCLIENT_OPT_ENCRYPT_LEVEL, 
            SMBCLIENT_ENCRYPTLEVEL_REQUEST))
            throw new Exceptions\SMBStateInitException();
        
        if (!is_readable($this->GetFullURL()))
            throw new Exceptions\SMBConnectException();
        
        $this->state = $state;
        
        return $this; 
    }

    protected function GetFullURL(string $path = "") : string
    {
        $username = rawurlencode($this->TryGetUsername() ?? "");
        $password = rawurlencode($this->TryGetPassword() ?? "");
        $workgroup = rawurlencode($this->TryGetWorkgroup() ?? "");
                
        $connstr = "";
        if ($workgroup) $connstr .= "$workgroup;";
        if ($username) $connstr .= $username;
        if ($password) $connstr .= ":$password";
        if ($connstr) $connstr .= "@";
        
        $connstr = "smb://".$connstr.$this->GetScalar('hostname');
                
        return $connstr.'/'.$this->GetPath($path);
    }
    
    // WORKAROUND: php-smbclient does not support b fopen flag
    protected function OpenReadHandle(string $path){ return fopen($this->GetFullURL($path),'r'); }
    protected function OpenWriteHandle(string $path){ return fopen($this->GetFullURL($path),'r+'); }
    
    // WORKAROUND: php-smbclient <= 3.0.5 does not implement stream ftruncate
    protected function SubTruncate(string $path, int $length) : self
    {
        $this->ClosePath($path); // close existing handles
        
        $handle = smbclient_open($this->state, $this->GetFullURL($path), 'r+');
        if (!$handle) throw new Exceptions\FileWriteFailedException();
            
        if (!smbclient_ftruncate($this->state, $handle, $length))
            throw new Exceptions\FileWriteFailedException();
        
        if (!smbclient_close($this->state, $handle))
            throw new Exceptions\FileWriteFailedException();

        return $this;
    }
    
    public function canGetFreeSpace() : bool { return true; }
    
    public function GetFreeSpace() : int
    {        
        $data = smbclient_statvfs($this->state, $this->GetFullURL());
        
        if ($data === false) throw new Exceptions\FreeSpaceFailedException();
        
        return $data['frsize'] * $data['bsize'] * $data['bavail'];
    }
}
