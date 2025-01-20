<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/Apps/Files/Exceptions.php");
require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/Apps/Files/Filesystem/FSImpl.php"); use Andromeda\Apps\Files\Filesystem\FSImpl;
require_once(ROOT."/Apps/Files/Limits/Filesystem.php");
require_once(ROOT."/Apps/Files/Limits/Account.php");

/**
 * An abstract class defining a user-created item in a filesystem.
 * 
 * Like other objects, items are generally referred to by ID, not name path.
 * It is therefore somewhat like an object storage, except that every item
 * must have exactly one parent (other than the root folder).
 */
abstract class Item extends BaseObject // TODO was StandardObject
{
    protected const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => new FieldTypes\StringType(),
            'description' => new FieldTypes\StringType(),
            'date_modified' => new FieldTypes\Timestamp(null),
            'date_accessed' => new FieldTypes\Timestamp(null, true),         
            'count_bandwidth' => new FieldTypes\Counter(true),  // total bandwidth used (recursive for folders)
            'count_pubdownloads' => new FieldTypes\Counter(),   // total public download count (recursive for folders)
            'obj_owner' => new FieldTypes\ObjectRef(Account::class),
            'obj_filesystem' => new FieldTypes\ObjectRef(FSManager::class),
            'objs_likes' => (new FieldTypes\ObjectRefs(Like::class, 'item', true))->autoDelete(), // links to like objects
            'count_likes' => new FieldTypes\Counter(),      // recursive total # of likes
            'count_dislikes' => new FieldTypes\Counter(),   // recursive total # of dislikes
            'objs_tags' => (new FieldTypes\ObjectRefs(Tag::class, 'item', true))->autoDelete(),
            'objs_comments' => (new FieldTypes\ObjectRefs(Comment::class, 'item', true))->autoDelete(),
            'objs_shares' => (new FieldTypes\ObjectRefs(Share::class, 'item', true))->autoDelete()
        ));
    }
    
    /** Updates the metadata of this item by scanning the object in the filesystem */
    public abstract function Refresh() : self;
    
    /** Returns the owner of this item, or null if it's on an external FS */
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    
    /** Returns the ID of the owner of this item (or null) */
    public function GetOwnerID() : ?string { return $this->TryGetObjectID('owner'); }
    
    /** Returns the name of this item */
    public abstract function GetName() : ?string;
    
    /** Returns the size of this item in bytes */
    public abstract function GetSize() : int;
    
    /** Returns the parent folder of this item */
    public abstract function GetParent() : ?Folder;
    
    /** Returns the parent ID of this item */
    public abstract function GetParentID() : ?string;

    /** Renames the item. If $overwrite, deletes an object if the target already exists. */
    public abstract function SetName(string $name, bool $overwrite = false) : self;
    
    /** Moves the item to a new parent. If $overwrite, deletes an object if the target already exists. */
    public abstract function SetParent(Folder $parent, bool $overwrite = false) : self;
    
    /** Returns this item's description */
    public function GetDescription() : ?string { return $this->TryGetScalar('description'); }
    
    /** Sets this item's description to the given value */
    public function SetDescription(?string $val) : self { return $this->SetScalar('description',$val); }
    
    /**
     * Copies the item to a new name.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param string $name the name of the new item
     * @param bool $overwrite if true, reuse the duplicate object
     * @return static the newly created object
     */
    public abstract function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self;
    
    /**
     * Copies the item to a new parent.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param Folder $parent the parent folder of the new item
     * @param bool $overwrite if true, reuse the duplicate object
     * @return static the newly created object
     */
    public abstract function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : self;
    
    /**
     * Asserts that this item can be moved to the given name
     * @param string $name the item name to check for
     * @param bool $overwrite if true, delete the duplicate item
     * @param bool $reuse if true, return the duplicate item for reuse instead of deleting
     * @throws DuplicateItemException if a duplicate item exists and not $overwrite
     * @return ?static existing item to be re-used
     */
    protected function CheckName(string $name, bool $overwrite, bool $reuse) : ?self
    {
        $item = static::TryLoadByParentAndName($this->database, 
            $this->GetParent()->Refresh(true), $name);
        
        if ($item !== null)
        {
            if ($overwrite && $item !== $this) 
            {
                if ($reuse) return $item->SetCreated(); else $item->Delete();
            }
            else throw new DuplicateItemException();
        }
        return null;
    }
    
    /**
     * Asserts that this item can be moved to the given parent
     * @param Folder $parent parent folder to check
     * @param bool $overwrite if true, delete the duplicate item
     * @param bool $reuse if true, return the duplicate item for reuse instead of deleting
     * @throws CrossFilesystemException if the parent is on a different filesystem
     * @throws DuplicateItemException if a duplicate item exists and not $overwrite
     * @return ?static existing item to be re-used
     */
    protected function CheckParent(Folder $parent, bool $overwrite, bool $reuse) : ?self
    {
        if ($parent->GetFilesystemID() !== $this->GetFilesystemID())
            throw new CrossFilesystemException();
            
        $item = static::TryLoadByParentAndName($this->database, 
            $parent->Refresh(true), $this->GetName());
        
        if ($item !== null)
        {
            if ($overwrite && $item !== $this)
            {
                if ($reuse) return $item->SetCreated(); else $item->Delete();
            }
            else throw new DuplicateItemException();
        }
        return null;
    }    
    
    /** Sets this item's owner to the given account */
    public function SetOwner(Account $account) : self
    {
        $this->AddStatsToOwner(false); // subtract stats from old owner
        
        $this->SetObject('owner', $account);
        
        $this->AddStatsToOwner(); // add stats to new owner
        
        return $this;
    }
    
    /**
     * Creates a new object in the database only (no filesystem call)
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the item's parent folder
     * @param Account $account the account owning this item
     * @param string $name the name of the item
     * @return static newly created object
     */
    public abstract static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self;
    
    /** Returns the filesystem manager that stores this object */
    protected function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }
    
    /** Returns the ID of the object's filesystem manager */
    protected function GetFilesystemID() : string { return $this->GetObjectID('filesystem'); }
    
    /** 
     * Returns the filesystem manager's implementor that stores this object 
     * @param bool $requireExist if true the item must not be deleted
     * 
     * Refreshes the item from storage first to make sure it's ready to use.
     * @throws DeletedByStorageException if requreExist and the item has been deleted
     */
    protected function GetFSImpl(bool $requireExist = true) : FSImpl 
    {
        if ($requireExist)
        {
            $this->Refresh(); if ($this->isDeleted())
                throw new DeletedByStorageException();
        }
        
        return $this->GetFilesystem()->GetFSImpl(); 
    }
    
    /** Returns true if this file should be accessible by all accounts */
    public function isWorldAccess() : bool 
    { 
        $fs = $this->GetFilesystem();
        
        return $fs->isExternal() && !$fs->isUserOwned();
    }
    
    /** Sets the item's access time to the given value or now if null */
    public function SetAccessed(?float $time = null) : self 
    { 
        if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;

        return $this->SetDate('accessed', max($this->TryGetDate('accessed'), $time)); 
    }
    
    /** 
     * Sets the item's created time to the given value or now if null 
     * @return $this
     */
    public function SetCreated(?float $time = null) : self  { return $this->SetDate('created',$time); }
    
    /** 
     * Sets the item's modified time to the given value or now if null 
     * @return $this
     */
    public function SetModified(?float $time = null) : self { return $this->SetDate('modified',$time); }
    
    /** Returns the bandwidth used by the item in bytes */
    public function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    
    /** Returns the public download count of the item */
    public function GetPublicDownloads() : int { return $this->GetCounter('pubdownloads'); }
    
    /**
     * Returns the like objects for this item
     * @param ?int $limit max # to load
     * @param ?int $offset index to load from
     * @return array<string, Like> likes indexed by ID
     */
    public function GetLikes(?int $limit = null, ?int $offset = null) : array { return $this->GetObjectRefs('likes',$limit,$offset); }
    
    /**
     * Returns the comment objects for this item
     * @param ?int $limit max # to load
     * @param ?int $offset index to load from
     * @return array<string, Comment> comments indexed by ID
     */
    public function GetComments(?int $limit = null, ?int $offset = null) : array { return $this->GetObjectRefs('comments',$limit,$offset); }
    
    /**
     * Returns the tag objects for this item
     * @return array<string, Tag> tags indexed by ID
     */
    public function GetTags() : array { return $this->GetObjectRefs('tags'); }
    
    /**
     * Returns the share objects for this item
     * @return array<string, Share> shares indexed by ID
     */
    public function GetShares() : array { return $this->GetObjectRefs('shares'); }
    
    /** Returns the number of shares on this object */
    public function GetNumShares() : int { return $this->CountObjectRefs('shares'); }
    
    /** 
     * Registers a new item with all limit objects 
     * @return $this
     */
    protected function CountCreate() : self 
    {
        return $this->MapToLimits(function(Limits\Base $lim){ $lim->CountItem(); })
                    ->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetUploadDate(); }); 
    }
    
    /** 
     * Counts a public download on the item and its parents 
     * @return $this
     */
    protected function CountPublicDownload() : self            
    {        
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountPublicDownload();
        return $this->DeltaCounter('pubdownloads'); 
    }
    
    /** 
     * Counts the given bandwidth on the item and its parents 
     * @return $this
     */
    public function CountBandwidth(int $bytes) : self 
    {        
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountBandwidth($bytes);
        return $this->DeltaCounter('bandwidth', $bytes); 
    }
    
    /**
     * Increments the item's like counter
     * @param bool $value if true, count a like, else count a dislike
     * @param bool $count if false, decrement, if true increment
     * @return $this
     */
    public function CountLike(bool $value, bool $count = true) : self
    { 
        if ($value) $this->DeltaCounter('likes', $count ? 1 : -1);
        else $this->DeltaCounter('dislikes', $count ? 1 : -1);
        return $this;
    }    
    
    /**
     * Increments the item's share counter
     * @param bool $count if true, increment, if false, decrement
     * @return $this
     */
    public function CountShare(bool $count = true) : self
    {
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountSubShare($count);
        
        return $this->MapToLimits(function(Limits\Base $lim)use($count){ $lim->CountShare($count); });
    }

    /** 
     * Maps the given function to all applicable limit objects 
     * @return $this
     */
    protected function MapToLimits(callable $func) : self
    {        
        return $this->MapToTotalLimits($func)->MapToTimedLimits($func);
    }
    
    /** 
     * Maps the given function to all applicable total limit objects 
     * @return $this
     */
    protected function MapToTotalLimits(callable $func) : self
    {        
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem()); if ($fslim !== null) $func($fslim);
        
        if ($this->GetOwnerID()) foreach (Limits\AccountTotal::LoadByAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);
        
        return $this;
    }
    
    /** 
     * Maps the given function to all applicable timed limit objects 
     * @return $this
     */
    protected function MapToTimedLimits(callable $func) : self
    {        
        if (!Config::GetInstance($this->database)->GetAllowTimedStats()) return $this;
        
        foreach (Limits\FilesystemTimed::LoadAllForFilesystem($this->database, $this->GetFilesystem()) as $lim) $func($lim);    
        
        if ($this->GetOwnerID()) foreach (Limits\AccountTimed::LoadAllForAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);   
        
        return $this;
    }
    
    /**
     * Adds this item's stats to all owner limits
     * @param bool $add if true add, else subtract
     * @return $this
     */
    protected function AddStatsToOwner(bool $add = true) : self
    {
        if (!$this->GetOwnerID()) return $this;
        
        foreach (Limits\AccountTotal::LoadByAccountAll($this->database, $this->GetOwner()) as $limit)
            $this->AddStatsToLimit($limit, $add);
 
        if (!Config::GetInstance($this->database)->GetAllowTimedStats()) return $this;
            
        foreach (Limits\AccountTimed::LoadAllForAccountAll($this->database, $this->GetOwner()) as $limit)
            $this->AddStatsToLimit($limit, $add);
        
        return $this;
    }    

    /**
     * Adds this item's stats to all filesystem limits
     * @param bool $add if true add, else subtract
     * @return $this
     */
    protected function AddStatsToFilesystem(bool $add = true) : self
    {        
        $total = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem());
        
        if ($total !== null) $this->AddStatsToLimit($total, $add);
        
        if (!Config::GetInstance($this->database)->GetAllowTimedStats()) return $this;
        
        foreach (Limits\FilesystemTimed::LoadAllForFilesystem($this->database, $this->GetFilesystem()) as $limit)
            $this->AddStatsToLimit($limit, $add);
        
        return $this;
    }
    
    /** Adds this item's stats to the given limit, substracting if not $add */
    protected abstract function AddStatsToLimit(Limits\Base $limit, bool $add = true) : void;
    
    /**
     * Returns a config bool for the item by checking applicable limits
     * @param callable $func the function returning the desired bool
     * @param Account $account the account to check the permission for, or null for defaults
     * @return bool true if (the FS value is null or true) and the account value is true
     */
    protected function GetLimitsBool(callable $func, ?Account $account) : bool
    {
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem());
        $aclim = Limits\AccountTotal::LoadByAccount($this->database, $account, true);
        return ($fslim === null || $func($fslim) !== false) && $func($aclim);
    }
    
    /** Returns true if the item should allow public modifications */
    public function GetAllowPublicModify() : bool {
       return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicModify(); }, null); }
    
    /** Returns true if the item should allow public uploading */
    public function GetAllowPublicUpload() : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicUpload(); }, null); }    
    
    /** Returns true if the item should allow random/partial writes */
    public function GetAllowRandomWrite(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowRandomWrite(); }, $account); }
    
    /** Returns true if the item should allow sharing */
    public function GetAllowItemSharing(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowItemSharing(); }, $account); }

    /** Returns true if the item should allow group shares (shares with a group) */
    public function GetAllowShareToGroups(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowShareToGroups(); }, $account); }
        
    /** Returns true if the item should allow public shares (shares with all users) */
    public function GetAllowShareToEveryone(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowShareToEveryone(); }, $account); }
    
    /** Deletes the item from the DB only */
    public abstract function NotifyFSDeleted() : void;
    
    /** Deleting an item also deletes all of its component objects (likes, tags, comments, shares) */
    public function Delete() : void
    {
        if (!$this->isDeleted())
            $this->MapToLimits(function(Limits\Base $lim){ $lim->CountItem(false); });
        
        parent::Delete();
    }    

    /**
     * Attemps to load this item by the given info 
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of the item
     * @param string $name the name of the item to load
     * @return ?static loaded item or null if not found
     */
    public static function TryLoadByParentAndName(ObjectDatabase $database, Folder $parent, string $name) : ?self
    {
        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('obj_parent',$parent->ID()), $q->Equals('name',$name));        
        return static::TryLoadUniqueByQuery($database, $q->Where($where));
    }
    
    /**
     * Returns an array of all items belonging to the given owner
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner to load objects for
     * @return array<string, static> items indexed by ID
     */
    public static function LoadByOwner(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $where = $q->Equals('obj_owner',$account->ID());
        return static::LoadByQuery($database, $q->Where($where));
    }
    
    /**
     * Returns all items with a parent that is not owned by the item owner
     * 
     * Does not return items that are world accessible
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns the items
     * @return array<string, static> items indexed by ID
     */
    public abstract static function LoadAdoptedByOwner(ObjectDatabase $database, Account $account) : array;

    /**
     * Returns a printable client object of this item
     * @param bool $owner if true, show owner-level details
     * @param bool $details if true, show tag and share objects
     * @return ?array `{id:id, name:?string, owner:?string, parent:?string, filesystem:string, \
         dates:{created:float, modified:?float}, counters:{pubdownloads:int, bandwidth:int, likes:int, dislikes:int, tags:int, comments:int}}` \
         if $owner, add: `{counters:{shares:int}, dates:{accessed:?float}}`, \
         if $details, add: `{tags:[id:Tag]}`, if $details && $owner, add `{shares:[id:Share]}`
     * @see Tag::GetClientObject()
     * @see Share::GetClientObject()
     */
    public function SubGetClientObject(bool $owner = false, bool $details = false) : ?array
    {
        $data = array(
            'id' => $this->ID(),
            'name' => $this->GetName(),
            'owner' => $this->GetOwnerID(),
            'parent' => $this->GetParentID(),
            'filesystem' => $this->GetFilesystemID(),
            'description' => $this->GetDescription(),            
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'modified' => $this->TryGetDate('modified')
            ),
            'counters' => array(
                'pubdownloads' => $this->GetPublicDownloads(),
                'bandwidth' => $this->GetBandwidth(),
                'likes' => $this->GetCounter('likes'),
                'dislikes' => $this->GetCounter('dislikes'),
                'tags' => $this->CountObjectRefs('tags'),
                'comments' => $this->CountObjectRefs('comments')
            )
        );
        
        if ($owner) 
        {
            $data['dates']['accessed'] = $this->TryGetDate('accessed');
            $data['counters']['shares'] = $this->GetNumShares();
        }
        
        if ($details) $data['tags'] = array_map(function(Tag $e) {
            return $e->GetClientObject(); }, $this->GetTags());
        
        if ($owner && $details) $data['shares'] = array_map(function(Share $e) {
            return $e->GetClientObject(); }, $this->GetShares());
        
        return $data;
    }
    
    public abstract function GetClientObject(bool $owner = false, bool $full = false) : array;
    public abstract function TryGetClientObject(bool $owner = false, bool $full = false) : ?array;
}
