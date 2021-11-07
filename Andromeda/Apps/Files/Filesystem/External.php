<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\StaticWrapper;

require_once(ROOT."/Apps/Files/Filesystem/Native.php");

require_once(ROOT."/Apps/Files/Item.php"); use Andromeda\Apps\Files\Item;
require_once(ROOT."/Apps/Files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/Apps/Files/Folder.php"); use Andromeda\Apps\Files\Folder;
require_once(ROOT."/Apps/Files/SubFolder.php"); use Andromeda\Apps\Files\SubFolder;
require_once(ROOT."/Apps/Files/RootFolder.php"); use Andromeda\Apps\Files\RootFolder;

/**
 * An External Andromeda filesystem is accessible outside Andromeda
 * 
 * The filesystem is used "normally" so that the contents can be
 * viewed outside Andromeda.  This also means that the filesystem
 * contents are shared between all users, if it is globally accessible.
 * Useful for making existing content accessible through andromeda.
 * 
 * The files and folder on-disk are considered the authoritative
 * record of what exists, and the database is merely a metadata cache.
 */
class External extends BaseFileFS
{
    /**
     * Returns the root-relative path of the given item
     * @param Item $item item to get (or null)
     * @param string $child if not null, add the given path to the end of the result
     * @return string path of the given item + $child
     */
    protected function GetItemPath(Item $item, ?string $child = null) : string
    {
        $parent = $item->GetParent();
        
        $path = ($parent === null) ? "" :
            $this->GetItemPath($parent, $item->GetName());
        
        return $path.($child !== null ? '/'.$child :"");
    }
    
    /** Get the root-relative path of the given file */
    protected function GetFilePath(File $file) : string { return $this->GetItemPath($file); }

    /**
     * Updates the given DB file from disk
     * 
     * Checks that it exists, then updates stat metadata
     */
    public function RefreshFile(File $file) : self
    {
        $storage = $this->GetStorage();        
        $path = $this->GetItemPath($file);
        
        if (!$storage->isFile($path)) { $file->NotifyFSDeleted(); return $this; }

        $stat = $storage->ItemStat($path); 
        $file->SetSize($stat->size,true);
        if ($stat->atime) $file->SetAccessed($stat->atime);
        if ($stat->ctime) $file->SetCreated($stat->ctime);
        if ($stat->mtime) $file->SetModified($stat->mtime); 
        
        return $this;
    }
    
    /**
     * Updates the given DB folder (and contents) from disk
     *
     * Checks that it exists, then updates stat metadata.
     * Also scans for new items and creates objects for them.
     * @param bool $doContents if true, recurse, else just this folder
     * @param ?StaticWrapper $fileSw MUST BE NULL (unit testing only)
     * @param ?StaticWrapper $folderSw MUST BE NULL (unit testing only)
     */
    public function RefreshFolder(Folder $folder, bool $doContents = true, 
        ?StaticWrapper $fileSw = null, ?StaticWrapper $folderSw = null) : self
    {
        $storage = $this->GetStorage();
        $path = $this->GetItemPath($folder);

        if (!$storage->isFolder($path))
        {
            // missing root is usually the result of a config error
            if (!($folder instanceof RootFolder))
                $folder->NotifyFSDeleted();
            return $this;
        }
                
        $stat = $storage->ItemStat($path);
        if ($stat->atime) $folder->SetAccessed($stat->atime);
        if ($stat->ctime) $folder->SetCreated($stat->ctime);
        if ($stat->mtime) $folder->SetModified($stat->mtime);

        if ($doContents) 
        {
            $dbitems = array();
            
            foreach ($folder->GetFiles() as $file) $dbitems[$file->GetName()] = $file;
            foreach ($folder->GetFolders() as $folder) $dbitems[$folder->GetName()] = $folder;
            
            foreach ($storage->ReadFolder($path) as $fsname)
            {
                $fspath = $path.'/'.$fsname;
                $isfile = $storage->isFile($fspath);
                if (!$isfile && !$storage->isFolder($fspath)) continue;
                
                if (array_key_exists($fsname, $dbitems))
                    unset($dbitems[$fsname]);
                else
                {
                    $sw = $isfile ? $fileSw : $folderSw;
                    $class = $isfile ? File::class : SubFolder::class;
                    
                    $database = $this->GetDatabase();
                    $owner = $this->GetFSManager()->GetOwner();
                    
                    if ($sw !== null) 
                            $dbitem = $sw->NotifyCreate($database, $folder, $owner, $fsname);
                    else $dbitem = $class::NotifyCreate($database, $folder, $owner, $fsname);
                    
                    $dbitem->Refresh()->Save(); // update metadata, and insert to the DB immediately
                }
            }
            
            foreach ($dbitems as $dbitem) 
                $dbitem->NotifyFSDeleted(); // prune extras
        }
        
        return $this;        
    }
    
    public function CreateFolder(Folder $folder) : self
    {
        $path = $this->GetItemPath($folder);
        $this->GetStorage()->CreateFolder($path);
        return $this;
    }

    public function DeleteFolder(Folder $folder) : self
    {
        $path = $this->GetItemPath($folder);
        $this->GetStorage()->DeleteFolder($path);
        return $this;
    }    
    
    public function RenameFile(File $file, string $name) : self
    { 
        $oldpath = $this->GetItemPath($file);
        $newpath = $this->GetItemPath($file->GetParent(),$name);
        $this->GetStorage()->RenameFile($oldpath, $newpath);
        return $this;
    }
    
    public function RenameFolder(Folder $folder, string $name) : self
    { 
        $oldpath = $this->GetItemPath($folder);
        $newpath = $this->GetItemPath($folder->GetParent(),$name);
        $this->GetStorage()->RenameFolder($oldpath, $newpath);
        return $this;
    }
    
    public function MoveFile(File $file, Folder $parent) : self
    { 
        $path = $this->GetItemPath($file);
        $dest = $this->GetItemPath($parent,$file->GetName());
        $this->GetStorage()->MoveFile($path, $dest);
        return $this;
    }
    
    public function MoveFolder(Folder $folder, Folder $parent) : self
    { 
        $path = $this->GetItemPath($folder);
        $dest = $this->GetItemPath($parent,$folder->GetName());
        $this->GetStorage()->MoveFolder($path, $dest);
        return $this;
    }

    public function CopyFolder(Folder $folder, Folder $dest) : self
    {
        $storage = $this->GetStorage();
        if ($storage->canCopyFolders())
        {
            $path = $this->GetItemPath($folder);
            $dest = $this->GetItemPath($dest);
            $storage->CopyFolder($path, $dest); 
        }
        else $this->ManualCopyFolder($folder, $dest);

        return $this;
    }
}