<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, TableTypes, QueryBuilder};
use Andromeda\Core\Errors\ErrorManager;
use Andromeda\Core\IOFormat\{Input, SafeParams};
use Andromeda\Core\{Crypto, Utilities};

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Config;
use Andromeda\Apps\Files\Filesystem\{Filesystem, Native, NativeCrypt, External};

/** Class representing a file stat result */
class ItemStat
{
    public function __construct(
        public float $atime = 0, 
        public float $ctime = 0, 
        public float $mtime = 0, 
        public int $size = 0) {}
}

/** 
 * A Storage implements the on-disk functions that actually store data.
 * Storages have some metadata, like name, owner, a read-only flag.
 * 
 * Storages have associated filesystems that determine the structure and usage
 * of the storage (how database objects map to disk files).  File->Filesystem->Storage
 * 
 * Storages do not implement transactions and cannot rollback actions.
 * Any "expected" exceptions should always be checked before storage actions.
 */
abstract class Storage extends BaseObject // TODO was StandardObject
{
    use TableTypes\TableLinkedChildren;
    
    /** 
     * A map of all storage classes as $name=>$class 
     * @var array<string, class-string<self>>
     */
    public const TYPES = array(
        'local' => Local::class, 
        /*'smb' => SMB::class, 
        'sftp' => SFTP::class, 
        's3' => S3::class,
        'ftp' => FTP::class*/
    );
    
    /** @return array<string, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { return self::TYPES; }
    
    public const FSTYPE_NATIVE = 0; 
    public const FSTYPE_NATIVE_CRYPT = 1; 
    public const FSTYPE_EXTERNAL = 2;
    
    public const FSTYPES = array(
        'native' => self::FSTYPE_NATIVE,
        'native_crypt' => self::FSTYPE_NATIVE_CRYPT,
        'external' => self::FSTYPE_EXTERNAL ); 

    /** Timestamp that the object was created */
    protected FieldTypes\Timestamp $date_created;
    /** Enum value for what filesystem type to use */
    protected FieldTypes\IntType $fstype;
    /** Boolean value indicating if the storage is read-only */
    protected FieldTypes\BoolType $readonly;
    /**
     * The account that this storage belongs to (or null if public)
     * @var FieldTypes\NullObjectRefT<Account>
     */
    protected FieldTypes\NullObjectRefT $owner;
    /** The human name of the storage */
    protected FieldTypes\StringType $name;
    /** The crypto masterkey if using a crypto filesystem */
    protected FieldTypes\NullStringType $crypto_masterkey;
    /** The crypto chunksize if using a crypto filesystem */
    protected FieldTypes\NullIntType $crypto_chunksize;
    
    protected function CreateFields() : void
    {
        $fields = array();
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->fstype = $fields[] = new FieldTypes\IntType('fstype');
        $this->readonly = $fields[] = new FieldTypes\BoolType('readonly');
        $this->owner = $fields[] = new FieldTypes\NullObjectRefT(Account::class, 'owner');
        $this->name = $fields[] = new FieldTypes\StringType('name', default:self::DEFAULT_NAME);
        $this->crypto_masterkey = $fields[] = new FieldTypes\NullStringType('crypto_masterkey');
        $this->crypto_chunksize = $fields[] = new FieldTypes\NullIntType('crypto_chunksize');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /** 
     * Loads all storages owned by the given account
     * @param bool $public if true, include public storages
     * @return array<string, static>
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $owner, bool $public) : array
    {
        $retval = $database->LoadObjectsByKey(static::class, 'owner', $owner->ID());
        if ($public) $retval += $database->LoadObjectsByKey(static::class, 'owner', null); // public storages
        return $retval;
    }

    /** 
     * Attempts to load a storage with the given owner and ID
     * @param string $id the storage ID to load
     * @param bool $public if true, allow owner being null (public storage)
     * @return ?static 
     */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $owner, string $id, bool $public) : ?self
    {
        $q = new QueryBuilder(); 
        $ownerq = $q->Equals('owner',$owner->ID());
        if ($public) $ownerq = $q->Or($ownerq, $q->IsNull('owner'));
        
        $q->Where($q->And($ownerq,$q->Equals('id',$id)));
        return $database->TryLoadUniqueByQuery(static::class, $q);
    }
    
    /** 
     * Attempts to load a storage with the given owner and name
     * @param ?Account $owner owner of the storage to match (null for public storage)
     * @param ?string $name name of the storage to match (default if null)
     */
    public static function TryLoadByAccountAndName(ObjectDatabase $database, ?Account $owner, ?string $name) : ?self
    {
        $name ??= self::DEFAULT_NAME;
        
        $q = new QueryBuilder(); 
        $ownerq = $q->Equals('owner',($owner !== null) ? $owner->ID() : null);

        $q->Where($q->And($ownerq, $q->Equals('name',$name)));
        return $database->TryLoadUniqueByQuery(static::class, $q);
    }
    
    /**
     * Attempts to load the default storage (no name)
     * @param ObjectDatabase $database database reference
     * @param Account $owner account to get the default for
     * @return ?static loaded storage or null if not available
     */
    public static function LoadDefaultByAccount(ObjectDatabase $database, Account $owner) : ?self
    {
        $q1 = new QueryBuilder();
        $q1->Where($q1->And($q1->Equals('name',self::DEFAULT_NAME), $q1->Equals('owner',$owner->ID())));
        $found = $database->TryLoadUniqueByQuery(static::class, $q1);
        
        if ($found === null) // load the public default
        {
            $q2 = new QueryBuilder(); 
            $q2->Where($q2->And($q2->Equals('name',self::DEFAULT_NAME), $q2->IsNull('owner')));
            $found = $database->TryLoadUniqueByQuery(static::class, $q2);
        }
        
        return $found;
    }
    
    /** Deletes all storages owned by the given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $owner) : void
    {
        $database->DeleteObjectsByKey(static::class, 'owner', $owner->ID());
    }

    /** Deletes this storage and all folder roots on it */
    public function NotifyPreDeleted() : void
    {
        // - if $unlink, from DB only
        $unlink = false; // TODO RAY !! how to do $unlink? probably need to override Delete still, but should we assume true when not running Delete()?e.g. with DeleteByAccount

        //RootFolder::DeleteRootsByStorage($this->database, $this, $unlink); // TODO RAY !!
        
        //Limits\FilesystemTotal::DeleteByClient($this->database, $this); // LIMITS
        //Limits\FilesystemTimed::DeleteByClient($this->database, $this); // LIMITS
    }

    /** Returns true if this storage has an owner (not global) */
    public function isUserOwned() : bool { return $this->owner->TryGetObjectID() !== null; }
    
    /** Returns the account that owns this storage (or null) */
    public function GetOwner() : ?Account { return $this->owner->TryGetObject(); }
    
    /** Returns the owner ID of this storage (or null) */
    public function GetOwnerID() : ?string { return $this->owner->TryGetObjectID(); }
    
    /** Returns true if the data in this storage is external, false if Andromeda owns it */
    public function isExternal() : bool { return $this->fstype->GetValue() === self::FSTYPE_EXTERNAL; }
    
    /** Returns true if the data is encrypted before sending to the storage */
    public function isEncrypted() : bool { return $this->fstype->GetValue() === self::FSTYPE_NATIVE_CRYPT; }
    
    /** Returns true if the storage is read-only */
    public function isReadOnly() : bool { return $this->readonly->GetValue(); }
    
    public const DEFAULT_NAME = "Default";

    /** Returns the name of this storage (or the default if null) */
    public function GetName() : string { return $this->name->GetValue(); }
    
    /** 
     * Sets the name of this storage, checking uniqueness
     * @return $this
     */
    public function SetName(?string $name) : self 
    {
        $name ??= self::DEFAULT_NAME;

        if (static::TryLoadByAccountAndName($this->database, $this->GetOwner(), $name) !== null)
            throw new Exceptions\InvalidNameException();
        
        $this->name->SetValue($name); return $this;
    }
    
    /** Returns the common command usage of Create() */
    public static function GetCreateUsage() : string { return "--sttype ".implode('|',array_keys(self::TYPES)).
        " [--fstype ".implode('|',array_keys(self::FSTYPES))."]".
        " [--name ?name] [--global bool] [--readonly bool] [--chunksize uint]"; } // TODO let admin give owner ID, not just global
    
    /** 
     * Gets command usage specific to external authentication backends
     * @return list<string>
     */
    final public static function GetCreateUsages() : array
    {
        $retval = array();
        foreach (self::TYPES as $name=>$class)
            $retval[] = "--sttype $name ".$class::GetCreateUsage(); // TODO RAY !! after fixes
        return $retval;
    }
    
    /** Creates and tests a new external auth backend based on the user input */
    public static function TypedCreate(ObjectDatabase $database, SafeParams $params) : self
    {
        $type = $params->GetParam('sttype')->FromAllowlist(array_keys(self::TYPES));
        
        return self::TYPES[$type]::Create($database, $params);
    }

    /**
     * Creates a new storage based on user input 
     * @param ObjectDatabase $database database reference
     * @param ?Account $owner owner of the storage (public if null)
     * @return static
     */
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : self 
    {
        // TODO RAY !! we used to Test() here too, but now do not. caller should do it (see auth source)
        $params = $input->GetParams();
        
        $name = $params->GetOptParam('name',null)->CheckLength(127)->GetNullName();
        $readonly = $params->GetOptParam('readonly',false)->GetBool();

        $fstype = $params->GetOptParam('fstype',default:'native')->FromAllowlist(array_keys(self::FSTYPES));
        
        $storage = $database->CreateObject(static::class);
        $storage->date_created->SetTimeNow();

        $storage->SetName($name);
        $storage->owner->SetObject($owner);
        $storage->readonly->SetValue($readonly);
        $storage->fstype->SetValue(self::FSTYPES[$fstype]);

        if ($storage->isEncrypted())
        {
            $chunksize = null; 
            if ($params->HasParam('chunksize'))
            {
                //if (!Limits\AccountTotal::LoadByAccount($database, $owner, true)->GetAllowRandomWrite())
                //    throw new Exceptions\RandomWriteDisabledException(); // LIMITS
                
                $checkSize = function(string $v){ $v = (int)$v; 
                    return $v >= 4*1024 && $v <= 1*1024*1024; }; // check in range [4K,1M]
                $chunksize = $params->GetParam('chunksize')->CheckFunction($checkSize)->GetUint();
            }
            
            $chunksize ??= Config::GetInstance($database)->GetCryptoChunkSize();
            
            $storage->crypto_chunksize->SetValue($chunksize);
            $storage->crypto_masterkey->SetValue(Crypto::GenerateSecretKey());
        }

        return $storage;
    }
    
    /** Returns the command usage for Edit() */
    public static function GetEditUsage() : string { return "[--name ?name] [--readonly bool]"; }
    
    /** 
     * Gets command usage specific to external authentication backends
     * @return list<string>
     */
    final public static function GetEditUsages() : array
    {
        $retval = array();
        foreach (self::TYPES as $name=>$class)
            $retval[] = "--sttype $name ".$class::GetEditUsage();
        return $retval;
    }
    
    /** 
     * Edits an existing storage with the given values 
     * @return $this
     */ // TODO RAY !! not testing anymore, caller needs to (see auth source)
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('name'))
            $this->SetName($params->GetParam('name')->CheckLength(127)->GetNullName());

        if ($params->HasParam('readonly'))
            $this->readonly->SetValue($params->GetParam('readonly')->GetBool());
        
        return $this;
    }

    /**
     * Returns a printable client object of this storage
     * @param bool $activate if true, show details that require activation
     * @return array{id:string}
     */
    public function GetClientObject(/*bool $priv = false,*/ bool $activate = false) : array
    {
        $retval = array(
            'id' => $this->ID()
            // TODO RAY !! implement me fully
        );
        
        /*if ($activate && $this->canGetFreeSpace())
            $retval['freespace'] = $this->Activate()->GetFreeSpace(); // TODO RAY !! should probably be admin only?
    
        $data = array(
            'id' => $this->ID(),
            'name' => $this->GetName(),
            'owner' => $this->GetOwnerID(),
            'external' => $this->isExternal(),
            'encrypted' => $this->isEncrypted(),
            'readonly' => $this->isReadOnly(),
            'sttype' => Utilities::ShortClassName($this->GetStorageType())
        );
        
        if ($this->isEncrypted()) $data['chunksize'] = (int)$this->GetScalar('crypto_chunksize');
        
        if ($priv) 
        {
            $data['dates'] = array(
                'created' => $this->GetDateCreated()
            );
            
            $data['storage'] = $this->GetStorage(false)->GetClientObject($activ);
        }*/
        
        return $retval;
    }

    /** The filesystem used for this storage */
    private Filesystem $filesystem;
    
    /** 
     * Gets the filesystem object to use with this storage 
     * @throws Exceptions\InvalidFSTypeException if not valid
     */
    public function GetFilesystem() : Filesystem
    {
        if (!isset($this->filesystem))
        {
            // TODO RAY !!  public function GetStorage(bool $activate = true) : Storage  - activate was an option before
            $this->Activate(); // activate underlying storage

            $this->filesystem = match($this->fstype->GetValue())
            {
                self::FSTYPE_NATIVE => new Native($this),
                self::FSTYPE_EXTERNAL => new External($this),
                self::FSTYPE_NATIVE_CRYPT => (function()
                {
                    $key = $this->crypto_masterkey->TryGetValue();
                    $csize = $this->crypto_chunksize->TryGetValue();

                    if ($key === null || $csize === null || $csize < 0)
                        throw new Exceptions\InvalidFSTypeException("invalid key/csize");
                    
                    return new NativeCrypt($this, $key, $csize);
                })(),
                default => throw new Exceptions\InvalidFSTypeException((string)$this->fstype->GetValue())
            };
        }

        return $this->filesystem;
    }
    
    /** By default, most storages support using folders */
    public function supportsFolders() : bool { return true; }
    
    /** Returns whether or not the storage supports getting free space */
    public function canGetFreeSpace() : bool { return false; } // TODO replace this with a general stat call, add total size to it
    
    /** Returns the available space in bytes on the storage */
    public function GetFreeSpace() : int { throw new Exceptions\FreeSpaceFailedException(); }

    /** 
     * Activates the storage by making any required connections, required before any filesystem actions!
     * @return $this
     */
    public abstract function Activate() : self;

    /** 
     * Asserts that the underlying storage can be connected and read from/written to 
     * @param bool $ro if true, don't test writing (only reading)
     */
    public function Test(bool $ro) : self
    {
        $this->Activate();

        $this->assertReadable();
        if (!$ro) $this->assertWriteable();

        return $this;
    }
    
    /** Asserts that the storage root can be read */
    protected abstract function assertReadable() : void;
    
    /** Asserts that the storage root can be written to */
    protected abstract function assertWriteable() : void;
    
    /** 
     * Manually tests if the root is writeable by uploading a test file 
     * Can be used to implement assertWriteable() if no function exists
     */
    protected function TestWriteable() : void
    {
        try
        {
            $name = Utilities::Random(12).".tmp";
            $this->CreateFile($name)->DeleteFile($name);
        }
        catch (Exceptions\StorageException $e) { 
            throw new Exceptions\TestWriteFailedException($e); }
    }

    /** Set to true when any un-committed writes have occurred */
    private bool $written = false;
    
    /**
     * Asserts that the storage is not read only
     * @throws Exceptions\ReadOnlyException if the storage or server are read only
     * @return $this
     */
    protected function AssertNotReadOnly() : self
    {
        if ($this->readonly->GetValue() ||
            $this->GetApiPackage()->GetConfig()->isReadOnly())
            throw new Exceptions\ReadOnlyException();
        
        $this->written = true;
        return $this;
    }
    
    /** Returns true if the server is set to dry run mode */
    protected function isDryRun() : bool
    {
        return $this->GetApiPackage()->GetInterface()->isDryRun();
    }
    
    /** Returns an ItemStat object on the given path */
    public abstract function ItemStat(string $path) : ItemStat;
    
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
     * @return list<string>
     * @see Storage::ReadFolder()
     */
    protected abstract function SubReadFolder(string $path) : array;
    
    /** 
     * Asserts that the folder with the given path exists
     * @return $this
     */
    public function CreateFolder(string $path) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        if ($this->isFolder($path)) return $this;
        
        $this->SubCreateFolder($path);
        return $this;
    }
    
    /**
     * The storage-specific CreateFolder
     * @see Storage::CreateFolder()
     * @return $this
     */
    protected abstract function SubCreateFolder(string $path) : self;    
    
    /** 
     * Creates a new empty file at the given path - overwrites if already exists
     * @return $this
     */
    public function CreateFile(string $path) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubCreateFile($path);
        return $this;
    }
    
    /**
     * The storage-specific CreateFile
     * @see Storage::CreateFile()
     * @return $this
     */
    protected abstract function SubCreateFile(string $path) : self;
    
    /**
     * Imports an existing file into the storage - overwrites if already exists
     * @param string $src file to import
     * @param string $dest path of new file
     * @param bool $istemp true if we can move the src
     * @return $this
     */
    public function ImportFile(string $src, string $dest, bool $istemp) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubImportFile($src, $dest, $istemp);
        return $this;
    }    
    
    /**
     * The storage-specific ImportFile
     * @see Storage::ImportFile()
     * @return $this
     */
    protected abstract function SubImportFile(string $src, string $dest, bool $istemp) : self;

    /**
     * Reads data from a file
     * @param string $path file to read
     * @param non-negative-int $start byte offset to read
     * @param non-negative-int $length exact number of bytes to read
     * @return string file data
     */
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        return $this->SubReadBytes($path, $start, $length);
    }
    
    /**
     * The storage-specific ReadBytes
     * @param non-negative-int $start
     * @param non-negative-int $length
     * @see Storage::ReadBytes()
     */
    protected abstract function SubReadBytes(string $path, int $start, int $length) : string;

    /**
     * Writes data to a file
     * @param string $path file to write
     * @param non-negative-int $start byte offset to write
     * @param string $data data to write
     * @return $this
     */
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubWriteBytes($path, $start, $data);
        return $this;
    }
    
    /**
     * The storage-specific WriteBytes
     * @param non-negative-int $start
     * @see Storage::WriteBytes()
     * @return $this
     */
    protected abstract function SubWriteBytes(string $path, int $start, string $data) : self;
    
    /**
     * Truncates the file (changes size)
     * @param string $path file to resize
     * @param non-negative-int $length new length of file
     * @return $this
     */
    public function Truncate(string $path, int $length) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubTruncate($path, $length);   
        return $this;
    }    
    
    /**
     * The storage-specific Truncate
     * @param non-negative-int $length
     * @see Storage::Truncate()
     * @return $this
     */
    protected abstract function SubTruncate(string $path, int $length) : self;
    
    /** Deletes the file with the given path */
    public function DeleteFile(string $path) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        if (!$this->isFile($path)) return $this;
        
        return $this->SubDeleteFile($path);
    }
    
    /**
     * The storage-specific DeleteFile
     * @see Storage::DeleteFile()
     * @return $this
     */
    protected abstract function SubDeleteFile(string $path) : self;
    
    /** 
     * Deletes the **empty** folder with the given path
     * @return $this
     */
    public function DeleteFolder(string $path) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        if (!$this->isFolder($path)) return $this;
        return $this->SubDeleteFolder($path);
    }
    
    /**
     * The storage-specific DeleteFolder
     * @see Storage::DeleteFolder()
     * @return $this
     */
    protected abstract function SubDeleteFolder(string $path) : self;

    // TODO RAY !! check all storage types for correct folder overwrite behavior in rename/move
    // actually maybe the default should be to NOT overwrite, since the higher level code always checks/deletes first, see CheckParent

    /** 
     * Renames a file from $old to $new - path shall not change - overwrites if already exists
     * @return $this
     */
    public function RenameFile(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubRenameFile($old, $new);
        return $this;
    }    
    
    /**
     * The storage-specific RenameFile
     * @see Storage::RenameFile()
     * @return $this
     */
    protected abstract function SubRenameFile(string $old, string $new) : self;
    
    /** 
     * Renames a folder from $old to $new - path shall not change - overwrites if already exists
     * @return $this
     */
    public function RenameFolder(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubRenameFolder($old, $new);
        return $this;
    }
    
    /**
     * The storage-specific RenameFolder
     * @see Storage::RenameFolder()
     * @return $this
     */
    protected abstract function SubRenameFolder(string $old, string $new) : self;
    
    /** 
     * Moves a file from $old to $new - name shall not change - overwrites if already exists
     * @return $this
     */
    public function MoveFile(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubMoveFile($old, $new);
        return $this;
    }
    
    /**
     * The storage-specific MoveFile
     * @see Storage::MoveFile()
     * @return $this
     */
    protected abstract function SubMoveFile(string $old, string $new) : self;
    
    /** 
     * Moves a folder from $old to $new - name shall not change - overwrites if already exists
     * @return $this
     */
    public function MoveFolder(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubMoveFolder($old, $new);
        return $this;
    }
    
    /**
     * The storage-specific MoveFolder
     * @see Storage::MoveFolder()
     * @return $this
     */
    protected abstract function SubMoveFolder(string $old, string $new) : self;
    
    /** 
     * Copies a file from $old to $new (path and name can change) - overwrites if already exists
     * @return $this
     */
    public function CopyFile(string $old, string $new) : self
    {
        $this->AssertNotReadOnly();
        if ($this->isDryRun()) return $this;
        
        $this->SubCopyFile($old, $new);
        return $this;
    }
    
    /**
     * The storage-specific CopyFile
     * @see Storage::CopyFile()
     * @return $this
     */
    protected abstract function SubCopyFile(string $old, string $new) : self;

    /** Marks the storage as not dirty (for invalid rollback checking) */
    public function commit() : void { $this->written = false; }

    /** Storages can't be rolled back - logs an exception if dirty (written to) */
    public function rollback() : void
    {
        if ($this->written)
        {
            // this is bad! did a write that can't be rolled back
            $e = new Exceptions\InvalidRollbackException();
            $this->GetApiPackage()->GetErrorManager()->LogException($e);
        }
    }

    /** Commits all instantiated storages */
    public static function commitAll(ObjectDatabase $database) : void
    { 
        foreach ($database->getLoadedObjects(self::class) as $fs)
        {
            if (!$fs->isDeleted())
                $fs->commit(); 
        }
    }
    
    /** Rolls back all instantiated storages */
    public static function rollbackAll(ObjectDatabase $database) : void
    {
        foreach ($database->getLoadedObjects(self::class) as $fs)
        {
            if ($fs->isDeleted()) continue;
            $database->GetApiPackage()->GetErrorManager()->LoggedTry(
                function()use($fs){ $fs->rollback(); });
        }
    }    
}
