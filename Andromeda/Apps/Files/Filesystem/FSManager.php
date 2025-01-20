<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) die();

use Andromeda\Core\{Crypto, Utilities};
use Andromeda\Core\IOFormat\Input;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};

/**
 * An object that manages and points to a filesystem manager
 *
 * Filesystems are composed of a manager, which has an implementation, which has a storage.
 * Filesystems are generally referred to by the manager (the storage is internal only).
 * 
 * The manager stores metadata and allows filesystems of different types to be looked up from 
 * a single table.  It also handles creating/loading/deleting/etc. whole filesystems.  
 * 
 * The implementation (loaded by the manager) implements the actual disk functions, 
 * defining the structure and usage of the filesystem (how DB objects map to disk files).
 * 
 * The implementation calls down into a storage, which defines at a lower
 * level how the functions are actually mapped into the underlying storage.
 */
class FSManager extends BaseObject // TODO was StandardObject
{
    private const TYPE_NATIVE = 0; 
    private const TYPE_NATIVE_CRYPT = 1; 
    private const TYPE_EXTERNAL = 2;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => new FieldTypes\StringType(), // name of the FS, null if it's the default
            'type' => new FieldTypes\IntType(), // enum of the type of FS impl
            'readonly' => new FieldTypes\BoolType(),
            'obj_storage' => new FieldTypes\ObjectPoly(Storage::class),
            'obj_owner' => new FieldTypes\ObjectRef(Account::class),
            'crypto_masterkey' => new FieldTypes\StringType(),
            'crypto_chunksize' => new FieldTypes\StringType()
        ));
    }
    
    public const DEFAULT_NAME = "Default";

    /** Returns true if the data in this filesystem is external, false if Andromeda owns it */
    public function isExternal() : bool { return $this->GetType() === self::TYPE_EXTERNAL; }
    
    /** Returns true if the data is encrypted before sending to the filesystem */
    public function isEncrypted() : bool { return $this->GetType() === self::TYPE_NATIVE_CRYPT; }
    
    /** Returns true if the filesystem is read-only */
    public function isReadOnly() : bool { return $this->TryGetScalar('readonly') ?? false; }
    
    /** Sets whether this filesystem is read-only */
    public function SetReadOnly(bool $ro) : self { return $this->SetScalar('readonly', $ro); }
    
    /** Returns the name (or null) of this filesystem */
    public function GetName() : string { return $this->TryGetScalar('name') ?? self::DEFAULT_NAME; }
    
    /** Sets the name of this filesystem, checks uniqueness */
    public function SetName(?string $name) : self 
    {
        if ($name === self::DEFAULT_NAME) $name = null;

        if (static::TryLoadByAccountAndName($this->database, $this->GetOwner(), $name) !== null)
            throw new Exceptions\InvalidNameException();
        
        return $this->SetScalar('name',$name); 
    }
    
    /** Returns true if this filesystem has an owner (not global) */
    public function isUserOwned() : bool { return $this->HasObject('owner'); }
    
    /** Returns the owner of this filesystem (or null) */
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    
    /** Returns the owner ID of this filesystem (or null) */
    public function GetOwnerID() : ?string { return $this->TryGetObjectID('owner'); }
    
    /** Sets the owner of this filesystem to the given account */
    private function SetOwner(?Account $owner) : self { return $this->SetObject('owner',$owner); }
        
    /** Returns the filesystem impl enum type */
    private function GetType() : int { return $this->GetScalar('type'); }
    
    /** Changes the filesystem impl enum type to the given value */
    private function SetType(int $type) { unset($this->interface); return $this->SetScalar('type',$type); }
    
    /** 
     * Returns the underlying storage 
     * @param bool $activate if true, activate
     */
    public function GetStorage(bool $activate = true) : Storage 
    { 
        $ret = $this->GetObject('storage');
        assert($ret instanceof Storage);
        return $activate ? $ret->Activate() : $ret;
    }  
    
    /** Returns the type of the underlying storage */
    public function GetStorageType() : string { return $this->GetObjectType('storage'); }
    
    /** Edits properites of the underlying storage and runs test */
    public function EditStorage(Input $input) : Storage { return $this->GetStorage(false)->Edit($input)->Test(); }
    
    /** Returns a reference to the global database */
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    
    private FSImpl $interface;
    
    /**
     * Rreturns the filesystem impl interface (items use this)
     * @throws InvalidFSTypeException if the stored FS is invalid
     */
    public function GetFSImpl() : FSImpl 
    {
        if (!isset($this->interface))
        {
            if ($this->GetType() === self::TYPE_NATIVE)
            {
                $this->interface = new Native($this);
            }
            else if ($this->GetType() === self::TYPE_NATIVE_CRYPT)
            {
                $masterkey = $this->GetScalar('crypto_masterkey');
                $chunksize = $this->GetScalar('crypto_chunksize');
                $this->interface = new NativeCrypt($this, $masterkey, $chunksize);
            }
            else if ($this->GetType() === self::TYPE_EXTERNAL)
            {
                $this->interface = new External($this);
            }
            else throw new Exceptions\InvalidFSTypeException();
        }
        
        return $this->interface; 
    }
    
    /** Returns a map of all storage classes as $name=>$class */
    private static function getStorageClasses() : array
    {
        $classes = Utilities::getClassesMatching(Storage::class); // TODO this is dumb, just have array here...
        
        $retval = array(); foreach ($classes as $class)
            $retval[strtolower(Utilities::ShortClassName($class))] = $class;
            
        return $retval;
    }
    
    /** Returns the common command usage of Create() */
    public static function GetCreateUsage() : string { return "--sttype ".implode('|',array_keys(self::getStorageClasses())).
        " [--fstype native|crypt|external] [--name ?name] [--global bool] [--readonly bool] [--chunksize uint]"; }
    
    /** Returns the command usage of Create() specific to each storage type */
    public static function GetCreateUsages() : array 
    { 
        $retval = array();
        foreach (self::getStorageClasses() as $name=>$class)
            $retval[] = "--sttype $name".$class::GetCreateUsage();
        return $retval;
    }
    
    /**
     * Creates and tests a new filesystem
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner of the filesystem
     * @throws InvalidStorageException if the storage fails
     * @return static
     */
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : self
    {
        $params = $input->GetParams();
        
        $name = $params->GetOptParam('name',null)->CheckLength(127)->GetNullName();
        
        $readonly = $params->GetOptParam('readonly',false)->GetBool();
        
        $classes = self::getStorageClasses();
        
        $sttype = $params->GetParam('sttype')->FromWhitelist(array_keys($classes));

        switch ($params->GetOptParam('fstype','native')
            ->FromWhitelist(array('native','crypt','external')))
        {
            case 'native': $fstype = self::TYPE_NATIVE; break;
            case 'crypt':  $fstype = self::TYPE_NATIVE_CRYPT; break;
            case 'external': $fstype = self::TYPE_EXTERNAL; break;
        }
        
        $filesystem = static::BaseCreate($database)
            ->SetOwner($owner)->SetName($name)
            ->SetType($fstype)->SetReadOnly($readonly);
        
        if ($filesystem->isEncrypted())
        {
            $chunksize = null; if ($params->HasParam('chunksize'))
            {
                if (!Limits\AccountTotal::LoadByAccount($database, $owner, true)->GetAllowRandomWrite())
                    throw new Exceptions\RandomWriteDisabledException();
                
                $checkSize = function(string $v){ $v = (int)$v; 
                    return $v >= 4*1024 && $v <= 1*1024*1024; }; // check in range [4K,1M]
                $chunksize = $params->GetParam('chunksize')->CheckFunction($checkSize)->GetUint();
                    
            }
            
            $chunksize ??= Config::GetInstance($database)->GetCryptoChunkSize();
            
            $filesystem->SetScalar('crypto_chunksize', $chunksize);
            $filesystem->SetScalar('crypto_masterkey', Crypto::GenerateSecretKey());
        }

        try
        {            
            $filesystem->SetObject('storage',$classes[$sttype]::Create($database, $input, $filesystem));
        
            $filesystem->GetStorage()->Test(); 
        }
        catch (ActivateException $e){ throw InvalidStorageException::Copy($e); }
        
        return $filesystem;
    }
    
    /** Returns the command usage of Edit() */
    public static function GetEditUsage() : string { return "[--name ?name] [--readonly bool]"; }
    
    public static function GetEditUsages() : array
    {
        $retval = array();
        foreach (self::getStorageClasses() as $name=>$class)
            $retval[] = "($name)".$class::GetEditUsage();
        return $retval;
    }
    
    /** Edits an existing filesystem with the given values, and tests it */
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('name')) $this->SetName($params->GetParam('name')->GetNullName());
        if ($params->HasParam('readonly')) $this->SetReadOnly($params->GetParam('readonly')->GetBool());
        
        $this->EditStorage($input)->Test(); return $this;
    }

    /**
     * Attempts to load the default filesystem (no name)
     * @param ObjectDatabase $database database reference
     * @param Account $account account to get the default for
     * @return ?static loaded FS or null if not available
     */
    public static function LoadDefaultByAccount(ObjectDatabase $database, Account $account) : ?self
    {
        $q1 = new QueryBuilder(); $q1->Where($q1->And($q1->IsNull('name'), $q1->Equals('obj_owner',$account->ID())));
        $found = static::TryLoadUniqueByQuery($database, $q1);
        
        if ($found === null)
        {
            $q2 = new QueryBuilder(); $q2->Where($q2->And($q2->IsNull('name'), $q2->IsNull('obj_owner')));
            $found = static::TryLoadUniqueByQuery($database, $q2);
        }
        
        return $found;
    }
    
    /** Attempts to load a filesystem with the given owner and ID - if $null, the owner can be null */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id, bool $null = false) : ?self
    {
        $q = new QueryBuilder(); $ownerq = $q->Equals('obj_owner',$account->ID());
        
        if ($null) $ownerq = $q->Or($ownerq, $q->IsNull('obj_owner'));
        
        $w = $q->And($ownerq,$q->Equals('id',$id));
        
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    /** Attempts to load a filesystem with the given owner (or null) and name */
    public static function TryLoadByAccountAndName(ObjectDatabase $database, ?Account $account, ?string $name) : ?self
    {
        if ($name === self::DEFAULT_NAME) $name = null;
        
        $q = new QueryBuilder(); 
        
        $w1 = $q->Or($q->IsNull('obj_owner'), $q->Equals('obj_owner',$account ? $account->ID() : null));

        return self::TryLoadUniqueByQuery($database, $q->Where($q->And($w1, $q->Equals('name',$name))));
    }
    
    /**
     * Loads an array of all filesystems owned by the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account owner of filesystems
     * @return array<string, FSManager> managers indexed by ID
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $w = $q->Or($q->Equals('obj_owner',$account->ID()),$q->IsNull('obj_owner'));
        
        return self::LoadByQuery($database, $q->Where($w));
    }
    
    /** Deletes all filesystems owned by the given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        static::DeleteByObject($database, 'owner', $account);
    }

    /** Deletes this filesystem and all folder roots on it - if $unlink, from DB only */
    public function Delete(bool $unlink = false) : void
    {
        RootFolder::DeleteRootsByFSManager($this->database, $this, $unlink);
        
        Limits\FilesystemTotal::DeleteByClient($this->database, $this);
        Limits\FilesystemTimed::DeleteByClient($this->database, $this);
        
        $this->DeleteObject('storage'); parent::Delete();
    }
    
    /**
     * Gets a printable client object of this filesystem
     * @param bool $priv if true, show details for the owner
     * @param bool $activ if true, show details that require activation
     * @return array<mixed> `{id:id, name:?string, owner:?id, external:bool, encrypted:bool, readonly:bool, sttype:enum}` \  
        if priv, add `{storage:Storage}` - if isEncrypted, add `{chunksize:int}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $priv = false, bool $activ = false) : array
    {
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
        }
        
        return $data;
    }
}

// when an account is deleted, need to delete files-related stuff also
Account::RegisterDeleteHandler(function(ObjectDatabase $database, Account $account)
{
    FSManager::DeleteByAccount($database, $account);
    RootFolder::DeleteRootsByAccount($database, $account);
});
