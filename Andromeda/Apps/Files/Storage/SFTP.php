<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\{Input, SafeParams};
use Andromeda\Apps\Accounts\Account;

abstract class SFTPBase1 extends FWrapper { use BasePath; }
abstract class SFTPBase2 extends SFTPBase1 { use UserPass; }

/**
 * Allows using an SFTP server for backend storage using phpseclib
 * 
 * Uses fieldcrypt to allow encrypting the username/password.
 */
class SFTP extends SFTPBase2
{
    protected static function getEncryptedFields() : array { return array_merge(parent::getEncryptedFields(), array('privkey','keypass')); }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => new FieldTypes\StringType(),
            'port' => new FieldTypes\IntType(),
            'hostkey' => new FieldTypes\StringType(),
            'privkey' => new FieldTypes\StringType(), // private key material for auth
            'keypass' => new FieldTypes\StringType(), // key for unlocking the private key
        ));
    }
    
    /**
     * Returns a printable client object of this SFTP storage
     * @return array<mixed> `{hostname:string, port:?int, pubkey:bool, keypass:bool}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        return parent::GetClientObject($activate) + array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'hostkey' => $this->TryGetHostKey(),
            'privkey' => (bool)($this->TryGetScalar('privkey')),
            'keypass' => (bool)($this->TryGetScalar('keypass')),
        );
    }

    /** Returns the configured username (mandatory) */
    protected function GetUsername() : string { return $this->GetEncryptedScalar('username'); }
    
    /** Returns the private key for authentication */
    protected function TryGetPrivkey() : ?string { return $this->TryGetEncryptedScalar('privkey'); }
    
    /** Returns the password for the private key */
    protected function TryGetKeypass() : ?string { return $this->TryGetEncryptedScalar('keypass'); }
    
    /** Sets the private key used for authentication */
    protected function SetPrivkey(?string $privkey) : self 
    {
        return $this->SetEncryptedScalar('privkey',$privkey); 
    }
    
    /** Sets the password for the private key, encrypted if $fieldcrypt */
    protected function SetKeypass(?string $keypass) : self 
    {
        return $this->SetEncryptedScalar('keypass',$keypass); 
    }

    /** Returns the cached public key for the server host */
    protected function TryGetHostKey() : ? string { return $this->TryGetScalar('hostkey'); }
    
    /** Sets the cached host public key to the given value */
    protected function SetHostKey(?string $val) : self { return $this->SetScalar('hostkey',$val); }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--port ?uint16] [--privkey% path | --privkey-] [--keypass ?raw]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : self
    { 
        $params = $input->GetParams();
        
        $obj = parent::Create($database, $input, $filesystem)
            ->SetScalar('hostname', $params->GetParam('hostname')->GetHostname())
            ->SetScalar('port', $params->GetOptParam('port',null)->GetNullUint16())
            ->SetKeypass($params->GetOptParam('keypass',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        
        if ($input->HasFile('privkey')) $obj->SetPrivkey($input->GetFile('privkey')->GetData());
        
        return $obj;
    }

    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--port ?uint16] [--privkey% path | --privkey-] [--keypass ?raw] [--resethost bool]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('hostname')) $this->SetScalar('hostname',$params->GetParam('hostname')->GetHostname());
        if ($params->HasParam('port')) $this->SetScalar('port',$params->GetParam('port')->GetNullUint16());
        
        if ($params->HasParam('keypass')) $this->SetKeypass($params->GetParam('keypass',SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        if ($params->HasFile('privkey')) $this->SetPrivkey($input->GetFile('privkey')->GetData());
        
        if ($params->GetOptParam('resethost',false)->GetBool()) $this->SetHostKey(null);
        
        return parent::Edit($input);
    }

    /** sftp connection resource */ private $sftp;

    public function Activate() : self
    {
        if (isset($this->sftp)) return $this;
        
        \phpseclib3\Net\SFTP\Stream::register();

        $host = $this->GetScalar('hostname'); 
        $port = $this->TryGetScalar('port') ?? 22;
        
        try
        {
            $sftp = new \phpseclib3\Net\SFTP($host, $port);
            
            $hostkey = $sftp->getServerPublicHostKey();
            
            $cached = $this->TryGetHostKey();
            if ($cached === null) $this->SetHostKey($hostkey);
            else if ($cached !== $hostkey) throw new Exceptions\HostKeyMismatchException();            
        }
        catch (BaseExceptions\PHPError $e) { throw SSHConnectionFailure::Append($e); }

        try
        {
            $username = $this->GetUsername();
            $sftp->login($username);
    
            if (($password = $this->TryGetPassword()) !== null) 
                $sftp->login($username, $password);
    
            if (($privkey = $this->TryGetPrivkey()) !== null)
            {
                $keypass = $this->TryGetKeypass();
                $privkey = \phpseclib3\Crypt\PublicKeyLoader::load($privkey, $keypass);            
                $sftp->login($username, $privkey);
            }
    
            if (!$sftp->isAuthenticated()) throw new Exceptions\SSHAuthenticationFailure();
        }
        catch (\RuntimeException $e) { throw SSHAuthenticationFailure::Copy($e); }
        
        $this->sftp = $sftp; return $this;
    }    
    
    // WORKAROUND - is_writeable does not work on directories
    protected function assertWriteable() : void { $this->TestWriteable(); }

    protected function GetFullURL(string $path = "") : string
    {
        return "sftp://".$this->sftp."/".$this->GetPath($path);
    }
    
    protected function SubImportFile(string $src, string $dest, bool $istemp) : self
    {
        $mode = \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE;
        
        if (!$this->sftp->put($this->GetPath($dest), $src, $mode))   
            throw new Exceptions\FileCreateFailedException();
            
        return $this;
    }
}