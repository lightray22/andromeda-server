<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\Input;

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ 
    if (!$init) FTP::DecryptAccount($database, $account); }); // TODO RAY !! move all these to FilesApp main

abstract class FTPBase1 extends FWrapper { use BasePath; }
abstract class FTPBase2 extends FTPBase1 { use UserPass; }

/**
 * Allows FTP to be used as a backend storage
 * 
 * The FTP extension's methods are mostly used rather than the fwrapper
 * functions, since the fwrapper functions create a new connection for
 * every call.  fwrapper functions are still used as fallbacks where needed.
 * Uses the fieldcrypt trait for optionally encrypting server credentials.
 */
class FTP extends FTPBase2
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => new FieldTypes\StringType(),
            'port' => new FieldTypes\IntType(),
            'implssl' => new FieldTypes\BoolType(), // if true, use implicit SSL, else explicit/none
        ));
    }
    
    /**
     * Returns a printable client object of this FTP storage
     * @return array<mixed> `{hostname:string, port:?int, implssl:bool}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        return parent::GetClientObject($activate) + array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'implssl' => $this->GetScalar('implssl'),
        );
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--port ?uint16] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        $params = $input->GetParams();
        
        return parent::Create($database, $params, $filesystem)
            ->SetScalar('hostname', $params->GetParam('hostname')->GetHostname())
            ->SetScalar('port', $params->GetOptParam('port',null)->GetNullUint16())
            ->SetScalar('implssl', $params->GetOptParam('implssl',false)->GetBool());
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--port ?uint16] [--implssl bool]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('hostname')) $this->SetScalar('hostname',$params->GetParam('hostname')->GetHostname());
        if ($params->HasParam('port')) $this->SetScalar('port',$params->GetParam('port')->GetNullUint16());
        if ($params->HasParam('implssl')) $this->SetScalar('implssl',$params->GetParam('implssl')->GetBool());
        
        return parent::Edit($params);
    }
    
    /** Check for the FTP extension */
    public function PostConstruct() : void
    {
        if (!function_exists('ftp_connect')) throw new Exceptions\FTPExtensionException();
    }
    
    /** The FTP connection resource */ private $ftp;
    
    public function Activate() : self
    {
        if (isset($this->ftp)) return $this;
        
        $host = $this->GetScalar('hostname'); 
        $port = $this->TryGetScalar('port') ?? 21;
        $user = $this->TryGetUsername() ?? 'anonymous';
        $pass = $this->TryGetPassword() ?? "";
        
        if ($this->GetScalar('implssl')) 
            $ftp = ftp_ssl_connect($host, $port);
        else $ftp = ftp_connect($host, $port);
        
        if (!$ftp) throw new Exceptions\FTPConnectionFailure();
        
        if (!ftp_login($ftp, $user, $pass)) throw new Exceptions\FTPAuthenticationFailure();
        
        ftp_pasv($ftp, true);
        
        $this->ftp = $ftp; return $this;
    }

    protected function GetFullURL(string $path = "") : string
    {
        $port = $this->TryGetScalar('port') ?? "";
        $username = rawurlencode($this->TryGetUsername() ?? "");
        $password = rawurlencode($this->TryGetPassword() ?? "");
        
        $proto = $this->GetScalar('implssl') ? "ftps" : "ftp";
        $usrstr = $username ? "$username:$password@" : "";
        $hostname = $this->GetScalar('hostname');
        $portstr = $port ? ":$port" : "";
        $connectstr = "$proto://$usrstr$hostname$portstr";
        
        return $connectstr.'/'.$this->GetPath($path);
    }
    
    // even though FTP can uses PHP's fwrapper, we'll override most of the
    // required methods anyway because it's faster to reuse one connection

    public function ItemStat(string $path) : ItemStat
    {
        $size = max(ftp_size($this->ftp, $this->GetPath($path)),0);
        $mtime = max(ftp_mdtm($this->ftp, $this->GetPath($path)),0);
        // FTP does not support atime or ctime!
        return new ItemStat(0, 0, $mtime, $size);
    }
    
    public function isFolder(string $path) : bool
    {
        try { return ftp_chdir($this->ftp, $this->GetPath($path)); }
        catch (\Throwable $e) { return false; }
    }
    
    public function isFile(string $path) : bool
    {
        return ftp_size($this->ftp, $this->GetPath($path)) >= 0;
    }
    
    // WORKAROUND - is_writeable does not work on directories
    protected function assertWriteable() : void { $this->TestWriteable(); }
    
    protected function SubReadFolder(string $path) : array
    {        
        $list = ftp_nlist($this->ftp, $this->GetPath($path));
        // TODO PHP Note that this parameter isn't escaped so there may be some issues with filenames containing spaces and other characters. 
        if ($list === false) throw new Exceptions\FolderReadFailedException();
        return array_map(function($item){ return basename($item); }, $list); // TODO why is basename needed? check on . and .. behavior
    }    
    
    protected function SubCreateFolder(string $path) : self
    {
        if (!ftp_mkdir($this->ftp, $this->GetPath($path))) 
            throw new Exceptions\FolderCreateFailedException();
        else return $this;
    }
    
    protected function SubCreateFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if ($this->isFile($path)) $this->SubDeleteFile($path);
        
        if (!($handle = fopen($this->GetFullURL($path),'w'))) 
            throw new Exceptions\FileCreateFailedException();
        
        if (!fclose($handle)) throw new Exceptions\FileCreateFailedException(); 
        
        return $this;
    }
    
    protected function SubImportFile(string $src, string $dest, bool $istemp): self
    {
        $this->ClosePath($dest);
        
        if (!ftp_put($this->ftp, $this->GetPath($dest), $src, FTP_BINARY))
            throw new Exceptions\FileCreateFailedException();
        
        return $this;
    }
    
    protected static function supportsReadWrite() : bool { return false; }
    protected static function supportsSeekReuse() : bool { return false; }
    
    protected function OpenReadHandle(string $path){ throw new Exceptions\FileOpenFailedException(); }
    protected function OpenWriteHandle(string $path){  throw new Exceptions\FileOpenFailedException(); }
    
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        if ($isWrite)
        {
            $fsize = ftp_size($this->ftp, $this->GetPath($path));
            
            if ($offset !== $fsize) throw new Exceptions\FTPAppendOnlyException();
            
            $handle = fopen($this->GetFullURL($path), 'a');
        }
        else
        {
            $stropt = stream_context_create(array('ftp'=>array('resume_pos'=>$offset)));
            
            $handle = fopen($this->GetFullURL($path), 'rb', false, $stropt);
        }
        
        if (!$handle) throw new Exceptions\FileOpenFailedException();
        
        return new FileContext($handle, $offset, $isWrite);
    }
    
    /** @throws FTPAppendOnlyException */
    protected function SubTruncate(string $path, int $length) : self
    {
        if (!$length) $this->DeleteFile($path)->CreateFile($path);
        else if (ftp_size($this->ftp, $this->GetPath($path)) !== $length)
            throw new Exceptions\FTPAppendOnlyException();
        return $this;
    }    
    
    protected function SubDeleteFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if (!ftp_delete($this->ftp, $this->GetPath($path)))
            throw new Exceptions\FileDeleteFailedException();
            else return $this;
    }
    
    protected function SubDeleteFolder(string $path) : self
    {
        if (!ftp_rmdir($this->ftp, $this->GetPath($path))) 
            throw new Exceptions\FolderDeleteFailedException();
        else return $this;
    }

    protected function SubRenameFile(string $old, string $new) : self
    {
        $this->ClosePath($old); $this->ClosePath($new);
        
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FileRenameFailedException();
        return $this;
    }
    
    protected function SubRenameFolder(string $old, string $new) : self
    {
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FolderRenameFailedException();
        return $this;
    }
        
    protected function SubMoveFile(string $old, string $new) : self
    {
        $this->ClosePath($old); $this->ClosePath($new);
        
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FileMoveFailedException();
        return $this;
    }
    
    protected function SubMoveFolder(string $old, string $new) : self
    {
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new Exceptions\FolderMoveFailedException();
        return $this;
    }
    
    protected function SubCopyFile(string $old, string $new) : self
    {
        throw new Exceptions\FTPCopyFileException();
    }
}
