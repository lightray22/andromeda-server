<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) die();

use Andromeda\Core\IOFormat\InputPath;

use Andromeda\Apps\Files\Items\{Item, File, Folder, SubFolder, RootFolder};

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
class External extends Filesystem
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
            // TODO should do validation here in case the DB gets an invalid value (no . .. /)
        
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
        
        if (!$storage->isFile($path)) { 
            $file->NotifyFSDeleted(); return $this; }

        $stat = $storage->ItemStat($path); 
        $file->SetSize($stat->size,true);
        if ($stat->atime !== 0.0) $file->SetAccessed($stat->atime);
        if ($stat->ctime !== 0.0) $file->SetCreated($stat->ctime);
        if ($stat->mtime !== 0.0) $file->SetModified($stat->mtime); 
        
        return $this;
    }
    
    /**
     * Updates the given DB folder (and contents) from disk
     *
     * Checks that it exists, then updates stat metadata.
     * Also scans for new items and creates objects for them.
     * @param bool $doContents if true, recurse, else just this folder
     */
    public function RefreshFolder(Folder $folder, bool $doContents = true) : self
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
        if ($stat->atime !== 0.0) $folder->SetAccessed($stat->atime);
        if ($stat->ctime !== 0.0) $folder->SetCreated($stat->ctime);
        if ($stat->mtime !== 0.0) $folder->SetModified($stat->mtime);

        if ($doContents) 
        {
            $dbitems = array();
            
            foreach ($folder->GetFiles() as $subfile)
                $dbitems[$subfile->GetName()] = $subfile;
            foreach ($folder->GetFolders() as $subfolder)
                $dbitems[$subfolder->GetName()] = $subfolder;
            
            foreach ($storage->ReadFolder($path) as $fsname)
            {
                $fspath = $path.'/'.$fsname;
                $isfile = $storage->isFile($fspath);
                if (!$isfile && !$storage->isFolder($fspath)) continue;
                
                if (array_key_exists($fsname, $dbitems))
                    unset($dbitems[$fsname]);
                else
                {
                    $database = $this->GetStorage()->GetDatabase();
                    $owner = $this->GetStorage()->GetOwner();
                    
                    $class = $isfile ? File::class : Folder::class;
                    $dbitem = $class::NotifyCreate($database, $folder, $owner, $fsname);
                    
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
    
    public function ImportFile(File $file, InputPath $infile) : self
    {
        $this->GetStorage()->ImportFile($infile->GetPath(), $this->GetFilePath($file), $infile->isTemp()); return $this;
    }
    
    public function CreateFile(File $file) : self
    {
        $this->GetStorage()->CreateFile($this->GetFilePath($file)); return $this;
    }
    
    public function DeleteFile(File $file) : self
    {
        $this->GetStorage()->DeleteFile($this->GetFilePath($file)); return $this;
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
        $newpath = $this->GetItemPath($folder->GetParent(),$name); // @phpstan-ignore-line TODO parent can be null, only allow this with subfolders
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
}
