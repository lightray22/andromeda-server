<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};
use Andromeda\Core\Errors\ErrorManager;
use Andromeda\Core\IOFormat\Input;
use Andromeda\Core\Utilities;

/** Class representing a stat result */
class ItemStat
{
    public float $atime; public float $ctime; public float $mtime; public int $size;
    public function __construct(int $atime = 0, int $ctime = 0, int $mtime = 0, int $size = 0){ 
        $this->atime = $atime; $this->ctime = $ctime; $this->mtime = $mtime; $this->size = $size; }
}

/** Class representing a path and rollback action */
class PathRollback
{
    public string $path; public $func;
    public function __construct(string $path, callable $func){
        $this->path = $path; $this->func = $func; }
}

/** 
 * A Storage implements the on-disk functions that actually store data.
 * 
 * Storages implement transactions, but only on a best-effort basis,
 * since the underlying filesystems are obviously not transactional.
 * Certain actions like deleting or writing to files cannot be undone.
 * Any "expected" exceptions should always be checked before storage actions.
 * @see FSManager 
 */
abstract class Storage extends BaseObject // TODO was StandardObject
{
    /** Returns the account that owns this storage (or null) */
    public function GetAccount() : ?Account { return $this->GetFilesystem()->GetOwner(); }
    
    /** Returns the FSManager that manages this storage */
    public function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }
    
    /** Loads all storages for the given account (join with FSManager) */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder();
        
        $q->Join($database, FSManager::class, 'id', static::class, 'obj_filesystem');
        $w = $q->Equals($database->GetClassTableName(FSManager::class).'.obj_owner', $account->ID());
        
        return static::LoadByQuery($database, $q->Where($w));
    }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_filesystem' => new FieldTypes\ObjectRef(FSManager::class)
        ));
    }
    
    /**
     * Returns a printable client object of this storage
     * @param bool $activate if true, show details that require activation
     * @return array<mixed> `{id:id, filesystem:id}` \
        if $activate and supported, add `{freespace:int}`
     */
    public function GetClientObject(bool $activate = false) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'filesystem' => $this->GetObjectID('filesystem')
        );
        
        if ($activate && $this->canGetFreeSpace())
            $retval['freespace'] = $this->Activate()->GetFreeSpace();
        
        return $retval;
    }

    /** Returns the command usage for Create() */
    public static function GetCreateUsage() : string { return ""; }
    
    /** 
     * Creates a new storage with the given input and the given FS manager 
     * @return static
     */
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        return static::BaseCreate($database)->SetObject('filesystem',$filesystem);
    }
    
    /** Returns the command usage for Edit() */
    public static function GetEditUsage() : string { return ""; }
    
    /** Edits an existing storage with the given input */
    public function Edit(Input $input) : self { return $this; }
    
    /** Asserts that the underlying storage can be connected and read from/written to */
    public function Test() : self
    {
        $this->Activate();
        
        $ro = $this->GetFilesystem()->isReadOnly();
        
        $this->assertReadable();
        
        if (!$ro) $this->assertWriteable();
        
        return $this;
    }
    
    /** By default, most storages support using folders */
    public function supportsFolders() : bool { return true; }
    
    /** By default, most storages use network bandwidth */
    public function usesBandwidth() : bool { return true; }
    
    /** Returns whether or not the storage supports getting free space */
    public function canGetFreeSpace() : bool { return false; }
    
    /** Returns the available space in bytes on the storage */
    public function GetFreeSpace() : int { throw new Exceptions\FreeSpaceFailedException(); }

    /** Activates the storage by making any required connections */
    public abstract function Activate() : self;

    /** Asserts that the filesystem root can be read */
    protected abstract function assertReadable() : void;
    
    /** Asserts that the filesystem root can be written to */
    protected abstract function assertWriteable() : void;
    
    /** 
     * Manually tests if the root is writeable by uploading a test file 
     * 
     * Can be used to implement assertWriteable() if no function exists
     */
    protected function TestWriteable() : void
    {
        try
        {
            $name = Utilities::Random(16).".tmp";
            $this->CreateFile($name)->DeleteFile($name);
        }
        catch (StorageException $e) { throw TestWriteFailedException::Copy($e); }
    }    
    
    /**
     * Asserts that the storage is not read only
     * @throws ReadOnlyException if the filesystem or server are read only
     */
    protected function AssertNotReadOnly() : self
    {
        if ($this->GetFilesystem()->isReadOnly())
            throw new Exceptions\ReadOnlyException();
        
        if ($this->GetApiPackage()->GetConfig()->isReadOnly())
            throw new Exceptions\ReadOnlyException();
        
        return $this;
    }
    
    /** Returns true if the server is set to dry run mode */
    protected function isDryRun() : bool
    {
        return $this->GetApiPackage()->GetConfig()->isDryRun();
    }
    
    /** Disallows running a batch transaction */
    protected function disallowBatch() : self
    {
        $this->GetApiPackage()->GetInterface()->DisallowBatch(); return $this;
    }
    
    /** Returns an ItemStat object on the given path */
    public abstract function ItemStat(string $path) : ItemStat;
    
    /** Returns the size of the file with the given path */
    public function getSize(string $path) : int { return $this->ItemStat($path)->size; }
    
    /** Returns true if the given path is a folder */
    public abstract function isFolder(string $path) : bool;
    
    /** Returns true if the given path is a file */
    public abstract function isFile(string $path) : bool;
    
    /**
     * Lists the contents of a folder
     * @param string $path folder path
     * @return list<string> array of names
     */
    public function ReadFolder(string $path) : array
    {
        return $this->SubReadFolder($path);
    }
    
    /**
     * The storage-specific ReadFolder
     * @see Storage::ReadFolder()
     */
    protected abstract function SubReadFolder(string $path) : array;
    
    /** Asserts that the folder with the given path exists */
    public function CreateFolder(string $path) : self
    {
        $this->AssertNotReadOnly();
        
        if ($this->isFolder($path)) return $this;
        
        $this->SubCreateFolder($path);
        
        $this->createdItems[] = $path;
        
        $this->onRollback[] = new PathRollback($path, function()use($path){ 
            $this->SubDeleteFolder($path); });
        
        return $this;
    }
    
    /**
     * The storage-specific CreateFolder
     * @see Storage::CreateFolder()
     */
    protected abstract function SubCreateFolder(string $path) : self;    
    
    /** 
     * Creates a new empty file at the given path
     * 
     * If it already exists, overwrites + NO ROLLBACK
     */
    public function CreateFile(string $path) : self
    {
        $this->AssertNotReadOnly();
        
        if ($overwrite = $this->isFile($path))
        {
            $this->disallowBatch();
            if ($this->isDryRun()) return $this;
        }
        
        $this->SubCreateFile($path);
        
        if ($overwrite) $this->deleteRollbacks($path);
        
        $this->createdItems[] = $path;
        
        $this->onRollback[] = new PathRollback($path, function()use($path){
            $this->SubDeleteFile($path); });
    
        return $this;
    }
    
    /**
     * The storage-specific CreateFile
     * @see Storage::CreateFile()
     */
    protected abstract function SubCreateFile(string $path) : self;
    
    /**
     * Imports an existing file into the storage
     * 
     * If it already exists, overwrites + NO ROLLBACK
     * @param string $src file to import
     * @param string $dest path of new file
     * @param bool $istemp true if we can move the src
     * @return $this
     */
    public function ImportFile(string $src, string $dest, bool $istemp) : self
    {
        $this->AssertNotReadOnly();
        
        if ($overwrite = $this->isFile($dest))
        {
            $this->disallowBatch();
            if ($this->isDryRun()) return $this;
        }
        
        $this->SubImportFile($src, $dest, $istemp);
        
        if ($overwrite) $this->deleteRollbacks($dest);
        
        $this->createdItems[] = $dest;        

        $this->onRollback[] = new PathRollback($dest, function()use($dest){
            $this->SubDeleteFile($dest); });
        
        return $this;
    }    
    
    /**
     * The storage-specific ImportFile
     * @see Storage::ImportFile()
     */
    protected abstract function SubImportFile(string $src, string $dest, bool $istemp) : self;

    /**
     * Reads data from a file
     * @param string $path file to read
     * @param int $start byte offset to read
     * @param int $length exact number of bytes to read
     * @return string file data
     */
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        return $this->SubReadBytes($path, $start, $length);
    }
    
    /**
     * The storage-specific ReadBytes
     * @see Storage::ReadBytes()
     */
    protected abstract function SubReadBytes(string $path, int $start, int $length) : string;

    /**
     * Writes data to a file
     * 
     * NO ROLLBACK if within existing bounds
     * @param string $path file to write
     * @param int $start byte offset to write
     * @param string $data data to write
     * @return $this
     */
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->AssertNotReadOnly();
        
        $created = in_array($path, $this->createdItems, true);
        
        if (!$created)
        {
            $oldsize = $this->getSize($path);
            
            if ($start < $oldsize) 
            {
                $this->disallowBatch();
                if ($this->isDryRun()) return $this;
            }
        }
        
        $this->SubWriteBytes($path, $start, $data);
        
        if (!$created)
        {
            if ($start + strlen($data) > $oldsize)
            {
                $this->onRollback[] = new PathRollback($path, function()use($path,$oldsize){
                    $this->SubTruncate($path, $oldsize); });
            }
        }
        
        return $this;
    }
    
    /**
     * The storage-specific WriteBytes
     * @see Storage::WriteBytes()
     */
    protected abstract function SubWriteBytes(string $path, int $start, string $data) : self;
    
    /**
     * Truncates the file (changes size)
     * 
     * NO ROLLBACK if shrinking the file
     * @param string $path file to resize
     * @param int $length new length of file
     * @return $this
     */
    public function Truncate(string $path, int $length) : self
    {
        $this->AssertNotReadOnly()->disallowBatch();
        
        if ($this->isDryRun()) return $this;

        $this->SubTruncate($path, $length);
        
        if (!in_array($path, $this->createdItems, true))
        {
            $oldsize = $this->getSize($path);
            
            $this->onRollback[] = new PathRollback($path, function()use($path,$oldsize){
                $this->SubTruncate($path, $oldsize); });
        }
            
        return $this;
    }    
    
    /**
     * The storage-specific Truncate
     * @see Storage::Truncate()
     */
    protected abstract function SubTruncate(string $path, int $length) : self;
    
    /** Deletes the file with the given path - NO ROLLBACK */
    public function DeleteFile(string $path) : self
    {
        $this->AssertNotReadOnly()->disallowBatch();
        
        if ($this->isDryRun()) return $this;
        
        if (!$this->isFile($path)) return $this;
        
        return $this->SubDeleteFile($path)->deleteRollbacks($path);
    }
    
    /**
     * The storage-specific DeleteFile
     * @see Storage::DeleteFile()
     */
    protected abstract function SubDeleteFile(string $path) : self;
    
    /** Deletes the **empty** folder with the given path - NO ROLLBACK */
    public function DeleteFolder(string $path) : self
    {
        $this->AssertNotReadOnly()->disallowBatch();
        
        if ($this->isDryRun()) return $this;
        
        if (!$this->isFolder($path)) return $this;
        
        return $this->SubDeleteFolder($path)->deleteRollbacks($path);
    }
    
    /**
     * The storage-specific DeleteFolder
     * @see Storage::DeleteFolder()
     */
    protected abstract function SubDeleteFolder(string $path) : self;

    /** 
     * Renames a file from $old to $new - path shall not change 
     * 
     * NO ROLLBACK if overwriting an existing file
     */
    public function RenameFile(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        
        if (($overwrite = $this->isFile($new)))
        {
            $this->disallowBatch();            
            if ($this->isDryRun()) return $this;
        }
        
        $this->SubRenameFile($old, $new);
        
        if ($overwrite) $this->deleteRollbacks($new);
        
        if (!in_array($old, $this->createdItems, true))
        {
            $this->onRollback[] = new PathRollback($new, function()use($old,$new){
                $this->SubRenameFile($new, $old); });
        }
        
        return $this->renameRollbacks($old,$new);
    }    
    
    /**
     * The storage-specific RenameFile
     * @see Storage::RenameFile()
     */
    protected abstract function SubRenameFile(string $old, string $new) : self;
    
    /** 
     * Renames a folder from $old to $new - path shall not change 
     * 
     * The destination folder must not already exist
     */
    public function RenameFolder(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();                
        
        if (($overwrite = $this->isFolder($new)))
        {
            $this->disallowBatch();
            if ($this->isDryRun()) return $this;
        }
        
        $this->SubRenameFolder($old, $new);
        
        if ($overwrite) $this->deleteRollbacks($new);
        
        if (!in_array($old, $this->createdItems, true))
        {   
            $this->onRollback[] = new PathRollback($new, function()use($old,$new){
                $this->SubRenameFolder($new, $old); });
        }
        
        return $this->renameRollbacks($old,$new);
    }
    
    /**
     * The storage-specific RenameFolder
     * @see Storage::RenameFolder()
     */
    protected abstract function SubRenameFolder(string $old, string $new) : self;
    
    /**
     * Moves a file from $old to $new - name shall not change
     * 
     * NO ROLLBACK if overwriting an existing file
     */
    public function MoveFile(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        
        if ($overwrite = $this->isFile($new))
        {
            $this->disallowBatch();
            if ($this->isDryRun()) return $this;
        }
        
        $this->SubMoveFile($old, $new);
        
        if ($overwrite) $this->deleteRollbacks($new);
        
        if (!in_array($old, $this->createdItems, true))
        {   
            $this->onRollback[] = new PathRollback($new, function()use($old,$new){
                $this->SubMoveFile($new, $old); });
        }
        
        return $this->renameRollbacks($old,$new);
    }
    
    /**
     * The storage-specific MoveFile
     * @see Storage::MoveFile()
     */
    protected abstract function SubMoveFile(string $old, string $new) : self;
    
    /** 
     * Moves a folder from $old to $new - name shall not change 
     * 
     * The destination folder must not already exist
     */
    public function MoveFolder(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        
        if ($overwrite = $this->isFolder($new))
        {
            $this->disallowBatch();
            if ($this->isDryRun()) return $this;
        }
        
        $this->SubMoveFolder($old, $new);
        
        if ($overwrite) $this->deleteRollbacks($new);
        
        if (!in_array($old, $this->createdItems, true))
        {
            $this->onRollback[] = new PathRollback($new, function()use($old,$new){
                $this->SubMoveFolder($new, $old); });
        }
        
        return $this->renameRollbacks($old,$new);
    }
    
    /**
     * The storage-specific MoveFolder
     * @see Storage::MoveFolder()
     */
    protected abstract function SubMoveFolder(string $old, string $new) : self;
    
    /** 
     * Copies a file from $old to $new (path and name can change) 
     * 
     * NO ROLLBACK if overwriting an existing file
     */
    public function CopyFile(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        
        if ($overwrite = $this->isFile($new))
        {
            $this->disallowBatch();
            if ($this->isDryRun()) return $this;
        }
        
        $this->SubCopyFile($old, $new);
        
        if ($overwrite) $this->deleteRollbacks($new);
        
        $this->createdItems[] = $new;
        
        $this->onRollback[] = new PathRollback($new, function()use($new){
            $this->SubDeleteFile($new); });
        
        return $this;
    }
    
    /**
     * The storage-specific CopyFile
     * @see Storage::CopyFile()
     */
    protected abstract function SubCopyFile(string $old, string $new) : self;

    /** array of all instantiated storages */
    private static $instances = array();    
    
    public function PostConstruct() : void { array_push(self::$instances, $this); }
    
    /** array of paths that were newly created */
    protected array $createdItems = array();

    /**
     * Moves all pending rollback actions from $old to $new for delete tracking
     * @param string $old old path of item
     * @param string $new new path of item
     * @return $this
     */
    protected function renameRollbacks(string $old, string $new) : self
    {
        if (in_array($old, $this->createdItems, true))
        {
            unset(array_keys($this->createdItems, $old, true));
            
            $this->createdItems[] = $new;
        }
        
        foreach ($this->onRollback as $obj)
        {
            if ($obj->path === $old) $obj->path = $new;
        }
        
        return $this;
    }
    
    /** Deletes all pending rollback actions for the given path */
    protected function deleteRollbacks(string $path) : self
    {
        unset(array_keys($this->createdItems, $path, true));
        
        $this->onRollback = array_filter($this->onRollback, 
            function(PathRollback $obj)use($path){ return $obj->path !== $path; } );
        
        return $this;
    }
    
    public function commit() 
    {         
        $this->createdItems = array();        
        $this->onRollback = array();
    }

    /** array of functions to run for rollback */
    protected array $onRollback = array();
    
    public function rollback() 
    {
        foreach (array_reverse($this->onRollback) as $obj) 
            ErrorManager::GetInstance()->LoggedTry($obj->func);
        
        $this->createdItems = array();        
        $this->onRollback = array();
    }

    /** Commits all instantiated filesystems */
    public static function commitAll() { 
        foreach (self::$instances as $fs) $fs->commit(); }
    
    /** Rolls back all instantiated filesystems */
    public static function rollbackAll()
    {
        foreach (self::$instances as $fs)
        {
            ErrorManager::GetInstance()->LoggedTry(
                function()use($fs){ $fs->rollback(); });
        }
    }    
}
