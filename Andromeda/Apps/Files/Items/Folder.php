<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

 use Andromeda\Core\Utilities;
 use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};

/** 
 * Defines a user-stored folder which groups other items 
 *
 * Folders keep recursive running counts of most statistics like number 
 * of subfiles, total folder size, etc., so retrieving these values is fast.
 * 
 * Every folder must have a name and exactly one parent, other than roots which have neither.
 */
abstract class Folder extends Item
{
    public static function GetDBClass() : string { return self::class; }

    /** @return class-string<self> */
    public static function GetObjClass(array $row) : string 
    {
        return $row['obj_parent'] === null ? RootFolder::class : SubFolder::class;
    }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'count_pubvisits' => new FieldTypes\Counter(), // number of public visits to this folder
            'count_size' => new FieldTypes\Counter(),      // total size of the folder and all contents
            'obj_parent'    => new FieldTypes\ObjectRef(Folder::class, 'folders'),
            'objs_files'     => new FieldTypes\ObjectRefs(File::class, 'parent'),
            'objs_folders'   => new FieldTypes\ObjectRefs(Folder::class, 'parent'),
            'count_subfiles' => new FieldTypes\Counter(),   // total number of subfiles (recursive)
            'count_subfolders' => new FieldTypes\Counter(), // total number of subfolders (recursive)
            'count_subshares' => new FieldTypes\Counter()   // total number of shares (recursive)
        ));
    }

    /** Returns the total size of the folder and its content in bytes */
    public function GetSize() : int { return $this->GetCounter('size'); }
    
    /**
     * Returns an array of the files in this folder (not recursive)
     * @param int $limit the max number of files to load`
     * @param int $offset the offset to start loading from
     * @return array<string, File> files indexed by ID
     */
    public function GetFiles(?int $limit = null, ?int $offset = null) : array { 
        $this->Refresh(true); return $this->GetObjectRefs('files',$limit,$offset); }
        
    /**
     * Returns an array of the folders in this folder (not recursive)
     * @param int $limit the max number of folders to load
     * @param int $offset the offset to start loading from
     * @return array<string, Folder> folders indexed by ID
     */
    public function GetFolders(?int $limit = null, ?int $offset = null) : array { 
        $this->Refresh(true); return $this->GetObjectRefs('folders',$limit,$offset); }
    
    /** Returns the number of files in this folder (not recursive) (fast) */
    public function GetNumFiles() : int { return $this->GetCounter('subfiles'); }
    
    /** Returns the number of folders in this folder (not recursive) (fast) */
    public function GetNumFolders() : int { return $this->GetCounter('subfolders'); }
    
    /** Returns the number of items in this folder (not recursive) (fast) */
    public function GetNumItems() : int { return $this->GetNumFiles() + $this->GetNumFolders(); }
    
    /** Returns the total number of shares on this folder or its contents (recursive) */
    public function GetTotalShares() : int { return $this->GetNumShares() + $this->GetCounter('subshares'); }
    
    /** Increments the folder's visit counter */
    public function CountPublicVisit() : self 
    {
        if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        return $this->DeltaCounter('pubvisits'); 
    }
    
    /** Increments the size of this folder and parents by the given #bytes */
    public function DeltaSize(int $size) : self 
    { 
        if (($parent = $this->GetParent()) !== null)
            $parent->DeltaSize($size);
        return $this->DeltaCounter('size',$size); 
    }
    
    /** Sets this folder and its contents' owners to the given account */
    public function SetOwnerRecursive(Account $account) : self
    {
        $this->SetOwner($account);
        
        //foreach (array_merge($this->GetFiles(), $this->GetFolders()) as $item) $item->SetOwner($account); // TODO no array merge
       
        return $this;
    }
    
    /** Asserts that this folder is not the given folder, or any of its parents */
    protected function CheckIsNotChildOrSelf(Folder $folder) : void
    {
        do { if ($folder === $this)
                throw new Exceptions\InvalidDestinationException(); }
        while (($folder = $folder->GetParent()) !== null);
    }
    
    /**
     * Adds the statistics from the given item to this folder
     * @param Item $item the item to add stats from
     * @param bool $add if true add, else subtract
     */
    private function AddItemCounts(Item $item, bool $add = true) : void
    {
        $this->SetModified(); $val = $add ? 1 : -1;
        $this->DeltaCounter('size', $item->GetSize() * $val);
        $this->DeltaCounter('bandwidth', $item->GetBandwidth() * $val);
        $this->DeltaCounter('pubdownloads', $item->GetPublicDownloads() * $val);        
        
        if ($item instanceof File) 
        {
            $this->DeltaCounter('subfiles', $val);
            
            $this->DeltaCounter('subshares', $item->GetNumShares() * $val);
        }
        
        if ($item instanceof Folder) 
        {
            $this->DeltaCounter('subfolders', $val);
            
            $this->DeltaCounter('subfiles', $item->GetNumFiles() * $val);
            $this->DeltaCounter('subfolders', $item->GetNumFolders() * $val);
            $this->DeltaCounter('subshares', $item->GetTotalShares() * $val);
        }
        
        $parent = $this->GetParent(); if ($parent !== null) $parent->AddItemCounts($item, $add);
    }
    
    /** Counts a share on a subitem of this folder */
    protected function CountSubShare(bool $count = true) : self
    {
        return $this->DeltaCounter('subshares', $count ? 1 : -1);
    }

    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : bool
    {
        $modified = parent::AddObjectRef($field, $object, $notification);
        
        if ($modified && ($field === 'files' || $field === 'folders')) $this->AddItemCounts($object, true);
        
        return $modified;
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : bool
    {
        $modified = parent::RemoveObjectRef($field, $object, $notification);
        
        if ($modified && ($field === 'files' || $field === 'folders')) $this->AddItemCounts($object, false);
        
        return $modified;
    }
    
    protected function AddStatsToLimit(Limits\Base $limit, bool $add = true) : void { $limit->AddFolderCounts($this, $add); }
    
    protected bool $refreshed = false;
    protected bool $subrefreshed = false;
    
    /**
     * Refreshes the folder's metadata from disk
     * @param bool $doContents if true, refresh all contents of the folder
     * @return $this
     */
    public function Refresh(bool $doContents = false) : self
    {
        if ($this->isCreated() || $this->isDeleted()) return $this;
        
        if (!$this->refreshed || (!$this->subrefreshed && $doContents)) 
        {
            $this->refreshed = true; $this->subrefreshed = $doContents;
            $this->GetFSImpl(false)->RefreshFolder($this, $doContents);
        }
        
        return $this;
    }
    
    protected bool $fsDeleted = false; public function isFSDeleted() : bool { return $this->fsDeleted; }
    
    /** Deletes all subfiles and subfolders, refresh if not isNotify */
    public function DeleteChildren(bool $isNotify = false) : void
    {
        $this->fsDeleted = $isNotify;
        if (!$isNotify) $this->Refresh(true);

        $this->DeleteObjects('files');
        $this->DeleteObjects('folders');
    }
    
    /** Deletes this folder and its contents from the DB only */
    public function NotifyFSDeleted() : void
    {
        $this->DeleteChildren(true);
        
        parent::Delete();
    } 
    
    /**
     * Returns all items with a parent that is not owned by the item owner
     *
     * Does not return items that are world accessible
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns the items
     * @return array<string, static> items indexed by ID
     */
    public static function LoadAdoptedByOwner(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder();
        
        $q->SelfJoinWhere($database, Folder::class, 'obj_parent', 'id');
        
        $w = $q->And($q->GetWhere(), $q->NotEquals('_parent.obj_owner', $account->ID()),
            $q->Equals($database->GetClassTableName(Folder::class).'.obj_owner', $account->ID()));

        return array_filter(static::LoadByQuery($database, $q->Where($w)), 
            function(Folder $folder){ return !$folder->isWorldAccess(); });
    }
    
    /**
     * Recursively lists subitems in this folder
     * @param bool $files if true, load files
     * @param bool $folders if true, load folders
     * @param int $limit max number of items to load
     * @param int $offset offset of items to load
     * @return array<string, Item> items indexed by ID
     */
    private function RecursiveItems(?bool $files = true, ?bool $folders = true, ?int $limit = null, ?int $offset = null) : array
    {
        $items = array();
        
        if ($limit && $offset) $limit += $offset;
        
        foreach ($this->GetFolders($limit) as $subfolder)
        {
            $newitems = $subfolder->RecursiveItems($files,$folders,$limit);
            
            $items = array_merge($items, $newitems);            
            if ($limit !== null) $limit -= count($newitems);
        }
        
        $items = array_merge($items, $this->GetFiles($limit));
        
        if ($offset) $items = array_slice($items, $offset);
        
        return $items;
    }

    /** 
     * @see Folder::TryGetClientObjects()
     * @throws DeletedByStorageException if the item is deleted 
     */
    public function GetClientObject(bool $owner = false, bool $details = false,
        bool $files = false, bool $folders = false, bool $recursive = false,
        ?int $limit = null, ?int $offset = null) : array
    {
        $retval = $this->TryGetClientObject($owner,$details,$files,$folders,$recursive,$limit,$offset);
        if ($retval === null) throw new Exceptions\DeletedByStorageException(); else return $retval;
    }
    
    /**
     * Returns a printable client object of this folder
     * @param bool $files if true, show subfiles
     * @param bool $folders if true, show subfolders
     * @param bool $recursive if true, show recursive contents
     * @param int $limit max number of items to show
     * @param int $offset offset of items to show
     * @return ?array null if deleted, else `{files:[id:File], folders:[id:Folder], \
         counters:{size:int, pubvisits:int, subfiles:int, subfolders:int}}` \
         if $owner, add: `{counters:{subshares:int}}`
     * @see Item::SubGetClientObject()
     */
    public function TryGetClientObject(bool $owner = false, bool $details = false,
        bool $files = false, bool $folders = false, bool $recursive = false, 
        ?int $limit = null, ?int $offset = null) : ?array
    {
        $this->Refresh($files || $folders); 
        
        if ($this->isDeleted()) return null;
        
        $this->SetAccessed();
        
        if ($recursive && ($files || $folders))
        {
            $items = $this->RecursiveItems($files,$folders,$limit,$offset);
            
            if ($folders) $subfolders = array_filter($items, function($item){ return ($item instanceof Folder); });            
            if ($files) $subfiles = array_filter($items, function($item){ return ($item instanceof File); });
        }
        else
        {
            if ($folders)
            {
                $subfolders = array_filter($this->GetFolders($limit,$offset), 
                    function(Folder $folder){ return !$folder->Refresh()->isDeleted(); });
                    // check isDeleted early so we have an accurate count for files
            
                $numitems = count($subfolders);
                if ($offset !== null) $offset = max(0, $offset-$numitems);
                if ($limit !== null) $limit = max(0, $limit-$numitems);
            }
            
            if ($files) $subfiles = $this->GetFiles($limit,$offset);
        }
        
        $data = parent::SubGetClientObject($owner,$details);
        
        if ($folders) $data['folders'] = array_filter(array_map(function(Folder $folder)use($owner){ 
            return $folder->TryGetClientObject($owner); }, $subfolders));
        
        if ($files) $data['files'] = array_filter(array_map(function(File $file)use($owner){ 
            return $file->TryGetClientObject($owner); }, $subfiles));
        
        $data['counters'] = Utilities::array_map_keys(function($p){ return $this->GetCounter($p); },
            array('size','pubvisits','subfiles','subfolders'));
        
        if ($owner) $data['counters']['subshares'] = $this->GetCounter('subshares');
        
        return $data;
    }
}
