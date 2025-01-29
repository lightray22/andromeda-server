<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\Input;
use Andromeda\Apps\Accounts\Account;

/**
 * Allows using an SMB/CIFS server for backend storage
 * 
 * Uses PHP libsmbclient.  Mostly uses the PHP fwrapper
 * functions but some manual workarounds are needed.
 * Uses fieldcrypt to allow encrypting the username/password.
 */
class SMB extends FWrapper
{
    use BasePath, UserPass, TableTypes\TableNoChildren;

    /** Hostname of the SMB server */
    protected FieldTypes\StringType $hostname;
    /** Optional SMB workgroup */
    protected FieldTypes\NullStringType $workgroup;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->hostname = new FieldTypes\StringType('hostname');
        $this->workgroup = new FieldTypes\NullStringType('workgroup');

        $this->RegisterFields($fields, self::class);
        $this->BasePathCreateFields();
        $this->UserPassCreateFields();
        parent::CreateFields();
    }

    /**
     * Returns a printable client object of this SMB storage
     * @return array{id:string} // TODO RAY !! types
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        return parent::GetClientObject($activate) + array(
            'hostname' => $this->hostname->GetValue(), // TODO RAY !! priv only?
            'workgroup' => $this->workgroup->TryGetValue()
        );
    }
    
    public static function GetCreateUsage() : string { 
        return static::GetBasePathCreateUsage()." ".static::GetUserPassCreateUsage()." ".
        "--hostname alphanum [--workgroup ?alphanum]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : self
    {
        $params = $input->GetParams();
        $obj = parent::Create($database, $input, $owner);

        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->workgroup->SetValue($params->GetOptParam('workgroup',null)->CheckLength(255)->GetNullAlphanum());

        $obj->BasePathCreate($params);
        $obj->UserPassCreate($params);
        return $obj;
    }
    
    public static function GetEditUsage() : string { 
        return static::GetBasePathEditUsage()." ".static::GetUserPassEditUsage()." ".
        "[--hostname alphanum] [--workgroup ?alphanum]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
    
        if ($params->HasParam('hostname')) 
            $this->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        
        if ($params->HasParam('workgroup')) 
            $this->workgroup->SetValue($params->GetParam('workgroup')->CheckLength(255)->GetNullAlphanum());
        
        return parent::Edit($input);
    }
    
    /** Checks for the SMB client extension */
    public function PostConstruct(bool $created) : void // TODO RAY !! where do the auth sources chec kthis?
    {
        if (!function_exists('smbclient_version')) 
            throw new Exceptions\SMBExtensionException();
    }
    
    /** @var resource */
    private $state;
    
    public function Activate() : self
    {
        if (is_resource($this->state)) return $this;
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

    public function canGetFreeSpace() : bool { return true; }
    
    public function GetFreeSpace() : int
    {
        if (($data = smbclient_statvfs($this->state, $this->GetFullURL())) === false)
            throw new Exceptions\FreeSpaceFailedException();

        return $data['frsize'] * $data['bsize'] * $data['bavail']; // @phpstan-ignore-line
    }

    protected function GetFullURL(string $path = "") : string
    {
        $username = rawurlencode($this->username->TryGetValue() ?? "");
        $password = rawurlencode($this->password->TryGetValue() ?? "");
        $workgroup = rawurlencode($this->workgroup->TryGetValue() ?? "");
                
        $connstr = "";
        if ($workgroup !== "") $connstr .= "$workgroup;";
        if ($username !== "")  $connstr .= $username;
        if ($password !== "")  $connstr .= ":$password";
        if ($connstr !== "")   $connstr .= "@";
        
        $connstr = "smb://".$connstr.$this->hostname->GetValue();
                
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
}
