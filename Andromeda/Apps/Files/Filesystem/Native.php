<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) die();

use Andromeda\Core\IOFormat\InputPath;

require_once(ROOT."/Apps/Files/Filesystem/FSImpl.php");

require_once(ROOT."/Apps/Files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/Apps/Files/Folder.php"); use Andromeda\Apps\Files\Folder;

/** 
 * A basic filesystem type that stores files as files (revolutionary) 
 * 
 * Each file call is translated into a root-relative path and passed to storage.
 */
abstract class BaseFileFS extends FSImpl
{
    protected abstract function GetFilePath(File $file) : string;
    
    public function CreateFile(File $file) : self
    {
        $this->GetStorage()->CreateFile($this->GetFilePath($file)); return $this;
    }
    
    public function ImportFile(File $file, InputPath $infile) : self
    {
        $this->GetStorage()->ImportFile($infile->GetPath(), $this->GetFilePath($file), $infile->isTemp()); return $this;
    }
    
    public function ReadBytes(File $file, int $start, int $length) : string
    {
        return $this->GetStorage()->ReadBytes($this->GetFilePath($file), $start, $length);
    }
    
    public function WriteBytes(File $file, int $start, string $data) : self
    {
        $this->GetStorage()->WriteBytes($this->GetFilePath($file), $start, $data); return $this;
    }
    
    public function Truncate(File $file, int $length) : self
    {
        $this->GetStorage()->Truncate($this->GetFilePath($file), $length); return $this;
    }
    
    public function CopyFile(File $file, File $dest) : self 
    {
        $this->GetStorage()->CopyFile($this->GetFilePath($file), $this->GetFilePath($dest)); return $this; 
    }
    
    public function DeleteFile(File $file) : self
    {
        $this->GetStorage()->DeleteFile($this->GetFilePath($file)); return $this;
    }
}

/**
 * An Andromeda native filesystem stores only file content.
 * 
 * All folders and file/folder metadata is stored only in the database.
 * The database is the authoritative record of what exists.
 */
class Native extends BaseFileFS
{
    /** no-op */ public function RefreshFile(File $file) : self                     { return $this; }
    /** no-op */ public function RefreshFolder(Folder $folder, bool $doContents = true) : self { return $this; }
    /** no-op */ public function CreateFolder(Folder $folder) : self                { return $this; }
    /** no-op */ public function DeleteFolder(Folder $folder) : self                { return $this; }
    /** no-op */ public function RenameFile(File $file, string $name) : self        { return $this; }
    /** no-op */ public function RenameFolder(Folder $folder, string $name) : self  { return $this; }
    /** no-op */ public function MoveFile(File $file, Folder $parent) : self        { return $this; }
    /** no-op */ public function MoveFolder(Folder $folder, Folder $parent) : self  { return $this; }
    
    /** The path to a file is simply its ID, broken into a prefix */
    protected function GetFilePath(File $file) : string 
    {
        $id = $file->ID();
        
        $storage = $this->GetStorage();
        
        if (!$storage->supportsFolders()) return $id;

        $len = 2; $path = substr($id, 0, $len);
        
        if (!$storage->isFolder($path))
            $storage->CreateFolder($path);
        
        return $path.'/'.substr($id, $len); 
    }
}
