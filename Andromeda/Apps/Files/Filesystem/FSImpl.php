<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\IOFormat\InputPath;

/**
 * Abstract class for a filesystem implementation, with the actual disk functions.
 * 
 * This is the interface that on-disk objects (files) will call into. Filesystem 
 * implementations define how DB objects map to disk files, and then call the underlying 
 * storage. The FSImpl class's info is managed by the FSManager and is not a DB object.
 * 
 * @see FSManager
 */
abstract class FSImpl
{
    protected FSManager $fsmanager;
    
    public function __construct(FSManager $fsmanager)
    {
        $this->fsmanager = $fsmanager;
    }
    
    /**
     * Returns the preferred byte alignment of the filesystem.
     * 
     * Reads and writes should align to these boundaries for performance
     * @return ?int FS chunk size
     */
    public function GetChunkSize() : ?int { return null; }
    
    /** Returns a reference to the parent FS manager */
    protected function GetFSManager() : FSManager { return $this->fsmanager; }

    /** Returns the underlying storage */
    protected function GetStorage() : Storage { return $this->fsmanager->GetStorage(); }
    
    /** Returns a database reference */
    protected function GetDatabase() : ObjectDatabase { return $this->fsmanager->GetDatabase(); }
    
    /** Synchronizes the given file's metadata with storage */
    public abstract function RefreshFile(File $file) : self;
    
    /** Synchronizes the given folder's metadata with storage */
    public abstract function RefreshFolder(Folder $folder, bool $doContents = true) : self;
    
    /** Creates the given folder on disk */
    public abstract function CreateFolder(Folder $folder) : self;
    
    /** Deletes the given folder from disk */
    public abstract function DeleteFolder(Folder $folder) : self;

    /**
     * Creates a new file and imports its content
     * @param File $file the database object
     * @param InputPath $infile the file to import
     * @return $this
     */
    public abstract function ImportFile(File $file, InputPath $infile) : self;
    
    /** Creates an empty file on storage */
    public abstract function CreateFile(File $file) : self;
    
    /** Deletes the given file from storage */
    public abstract function DeleteFile(File $file) : self;
    
    /**
     * Reads the exact number of desired bytes from the given file
     * 
     * Throws an error if the read goes beyond the end of the file
     * @param File $file file to read
     * @param int $start byte offset
     * @param int $length number of bytes
     * @return string file data
     */
    public abstract function ReadBytes(File $file, int $start, int $length) : string;
    
    /**
     * Writes to the given file, possibly appending it
     * @param File $file file to write
     * @param int $start byte offset
     * @param string $data data to write
     * @return $this
     */
    public abstract function WriteBytes(File $file, int $start, string $data) : self;
    
    /**
     * Truncates (changes size of) a file
     * @param File $file file to truncate
     * @param int $length desired size in bytes
     * @return $this
     */
    public abstract function Truncate(File $file, int $length) : self;
    
    /** Renames a file to the given name */
    public abstract function RenameFile(File $file, string $name) : self;
    
    /** Renames a folder to the given name */
    public abstract function RenameFolder(Folder $folder, string $name) : self;
    
    /**
     * Moves a file
     * @param File $file file to move
     * @param Folder $parent new parent folder
     * @return $this
     */
    public abstract function MoveFile(File $file, Folder $parent) : self;
    
    /**
     * Moves a folder
     * @param Folder $folder folder to move
     * @param Folder $parent new parent folder
     * @return $this
     */
    public abstract function MoveFolder(Folder $folder, Folder $parent) : self;
    
    /**
     * Copies a file
     * @param File $file file to copy
     * @param File $dest new object for destination
     * @return $this
     */
    public abstract function CopyFile(File $file, File $dest) : self;
}
