<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\Input;
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\CryptFields;

/**
 * Allows FTP to be used as a backend storage
 * 
 * The FTP extension's methods are mostly used rather than the fwrapper
 * functions, since the fwrapper functions create a new connection for
 * every call.  fwrapper functions are still used as fallbacks where needed.
 * Uses the credcrypt trait for optionally encrypting server credentials.
 */
class FTP extends FWrapper
{
    use BasePath, UserPass, TableTypes\TableNoChildren;

    /** Hostname of the server */
    protected FieldTypes\StringType $hostname;
    /** The port to connect to the server */
    protected FieldTypes\NullIntType $port;
    /** Whether or not to use implicit TLS (else explicit TLS or none) */
    protected FieldTypes\BoolType $implssl;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->hostname = new FieldTypes\StringType('hostname');
        $this->port = new FieldTypes\NullIntType('port');
        $this->implssl = new FieldTypes\BoolType('implssl');

        $this->RegisterFields($fields, self::class);
        $this->BasePathCreateFields();
        $this->UserPassCreateFields();
        parent::CreateFields();
    }

    /** @return list<CryptFields\CryptField> */
    protected function GetCryptFields() : array { 
        return $this->GetUserPassCryptFields(); }

    /**
     * Returns a printable client object of this FTP storage
     * @return array{id:string} `{hostname:string, port:?int, implssl:bool}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array // TODO RAY implement me
    {
        return parent::GetClientObject($activate)/* + array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'implssl' => $this->GetScalar('implssl'),
        )*/;
    }
    
    public static function GetCreateUsage() : string { 
        return static::GetBasePathCreateUsage()." ".static::GetUserPassCreateUsage().
        " --hostname alphanum [--port ?uint16] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : self
    {
        $params = $input->GetParams();
        $obj = parent::Create($database, $input, $owner);
        
        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->port->SetValue($params->GetOptParam('port',null)->GetNullUint16());
        $obj->implssl->SetValue($params->GetOptParam('implssl',false)->GetBool());
            
        $obj->BasePathCreate($params);
        $obj->UserPassCreate($params);
        return $obj;
    }
    
    public static function GetEditUsage() : string {
        return static::GetBasePathCreateUsage()." ".static::GetUserPassCreateUsage().
        " [--hostname alphanum] [--port ?uint16] [--implssl bool]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('hostname'))
            $this->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        if ($params->HasParam('port'))
            $this->port->SetValue($params->GetParam('port')->GetNullUint16());
        if ($params->HasParam('implssl'))
            $this->implssl->SetValue($params->GetParam('implssl')->GetBool());
        
        $this->BasePathEdit($params);
        $this->UserPassEdit($params);
        return parent::Edit($input);
    }
    
    /** Check for the FTP extension */
    public function PostConstruct(bool $created) : void
    {
        if (!function_exists('ftp_connect'))
            throw new Exceptions\FTPExtensionException();
    }
    
    private ?\FTP\Connection $ftp = null;

    public function Activate() : self { $this->GetConnection(); return $this; }

    protected function GetConnection() : \FTP\Connection
    {
        if ($this->ftp !== null) return $this->ftp;
        
        $host = $this->hostname->GetValue(); 
        $port = $this->port->TryGetValue() ?? 21;
        $user = $this->username->TryGetValue() ?? 'anonymous';
        $pass = $this->password->TryGetValue() ?? "";
        
        if ($this->implssl->GetValue()) 
            $ftp = ftp_ssl_connect($host, $port);
        else $ftp = ftp_connect($host, $port);
        
        if ($ftp === false)
            throw new Exceptions\FTPConnectionFailure();
        
        if (!ftp_login($ftp, $user, $pass))
            throw new Exceptions\FTPAuthenticationFailure();
        
        ftp_pasv($ftp, true);
        
        return $this->ftp = $ftp;
    }

    protected function GetFullURL(string $path = "") : string
    {
        $port = $this->port->TryGetValue();
        $username = rawurlencode($this->username->TryGetValue() ?? "");
        $password = rawurlencode($this->password->TryGetValue() ?? "");
        
        $proto = $this->implssl->GetValue() ? "ftps" : "ftp";
        $usrstr = ($username !== "") ? "$username:$password@" : "";
        $hostname = $this->hostname->GetValue();
        $portstr = ($port !== null) ? ":$port" : "";
        $connectstr = "$proto://$usrstr$hostname$portstr";
        
        return $connectstr.'/'.$this->GetPath($path);
    }
    
    // even though FTP can uses PHP's fwrapper, we'll override most of the
    // required methods anyway because it's faster to reuse one connection

    public function ItemStat(string $path) : ItemStat
    {
        $size = max(ftp_size($this->GetConnection(), $this->GetPath($path)),0);
        $mtime = max(ftp_mdtm($this->GetConnection(), $this->GetPath($path)),0);
        // FTP does not support atime or ctime!
        return new ItemStat(0, 0, $mtime, $size);
    }
    
    public function isFolder(string $path) : bool
    {
        try { return ftp_chdir($this->GetConnection(), $this->GetPath($path)); }
        catch (\Throwable $e) { return false; }
    }
    
    public function isFile(string $path) : bool
    {
        return ftp_size($this->GetConnection(), $this->GetPath($path)) >= 0;
    }
    
    // WORKAROUND - is_writeable does not work on directories
    protected function assertWriteable() : void { $this->TestWriteable(); }
    
    protected function SubReadFolder(string $path) : array
    {        
        // TODO PHP Note that this parameter isn't escaped so there may be some issues with filenames containing spaces and other characters. 
        if (($list = ftp_nlist($this->GetConnection(), $this->GetPath($path))) === false)
            throw new Exceptions\FolderReadFailedException();
        return array_map(function($item){ return basename($item); }, $list); // TODO why is basename needed? check on . and .. behavior
    }    
    
    protected function SubCreateFolder(string $path) : self
    {
        if (ftp_mkdir($this->GetConnection(), $this->GetPath($path)) === false) 
            throw new Exceptions\FolderCreateFailedException();
        else return $this;
    }
    
    protected function SubCreateFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if ($this->isFile($path))
            $this->SubDeleteFile($path);
        
        if (($handle = fopen($this->GetFullURL($path),'w')) === false) 
            throw new Exceptions\FileCreateFailedException();
        
        if (!fclose($handle)) throw new Exceptions\FileCreateFailedException(); 
        
        return $this;
    }
    
    protected function SubImportFile(string $src, string $dest, bool $istemp): self
    {
        $this->ClosePath($dest);
        
        if (!ftp_put($this->GetConnection(), $this->GetPath($dest), $src, FTP_BINARY))
            throw new Exceptions\FileCreateFailedException();
        
        return $this;
    }
    
    /** @throws Exceptions\FTPAppendOnlyException */
    protected function SubTruncate(string $path, int $length) : self
    {
        if ($length === 0) // doable
            $this->DeleteFile($path)->CreateFile($path);
        else if (ftp_size($this->GetConnection(), $this->GetPath($path)) !== $length)
            throw new Exceptions\FTPAppendOnlyException();
        return $this;
    }    
    
    protected function SubDeleteFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if (!ftp_delete($this->GetConnection(), $this->GetPath($path)))
            throw new Exceptions\FileDeleteFailedException();
            else return $this;
    }
    
    protected function SubDeleteFolder(string $path) : self
    {
        if (!ftp_rmdir($this->GetConnection(), $this->GetPath($path))) 
            throw new Exceptions\FolderDeleteFailedException();
        else return $this;
    }

    protected function SubRenameFile(string $old, string $new) : self
    {
        $this->ClosePath($old);
        $this->ClosePath($new);
        
        if (!ftp_rename($this->GetConnection(), $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FileRenameFailedException();
        return $this;
    }
    
    protected function SubRenameFolder(string $old, string $new) : self
    {
        if (!ftp_rename($this->GetConnection(), $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FolderRenameFailedException();
        return $this;
    }
        
    protected function SubMoveFile(string $old, string $new) : self
    {
        $this->ClosePath($old);
        $this->ClosePath($new);
        
        if (!ftp_rename($this->GetConnection(), $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FileMoveFailedException();
        return $this;
    }
    
    protected function SubMoveFolder(string $old, string $new) : self
    {
        if (!ftp_rename($this->GetConnection(), $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FolderMoveFailedException();
        return $this;
    }
    
    protected function SubCopyFile(string $old, string $new) : self
    {
        throw new Exceptions\FTPCopyFileException();
    }

    protected static function supportsReadWrite() : bool { return false; }
    protected static function supportsSeekReuse() : bool { return false; }
    
    protected function OpenReadHandle(string $path){ throw new Exceptions\FileOpenFailedException(); }
    protected function OpenWriteHandle(string $path){  throw new Exceptions\FileOpenFailedException(); }
    
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        if ($isWrite)
        {
            if ($offset !== ftp_size($this->GetConnection(), $this->GetPath($path)))
                throw new Exceptions\FTPAppendOnlyException();
            $handle = fopen($this->GetFullURL($path), 'a');
        }
        else
        {
            $stropt = stream_context_create(array('ftp'=>array('resume_pos'=>$offset)));
            $handle = fopen($this->GetFullURL($path), 'rb', false, $stropt);
        }
        
        if ($handle === false)
            throw new Exceptions\FileOpenFailedException();
        return new FileContext($handle, $offset, $isWrite);
    }
    
}
