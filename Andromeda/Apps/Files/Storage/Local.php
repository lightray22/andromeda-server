<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/Apps/Files/Storage/Exceptions.php");
require_once(ROOT."/Apps/Files/Storage/FWrapper.php");

abstract class LocalBase extends FWrapper { use BasePath; }

/** 
 * A storage on local-disk on the server
 * 
 * Only admin can add storages of this type!
 */
class Local extends LocalBase
{
    public function Activate() : self { return $this; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        $account = $filesystem->GetOwner();
        if ($account && !$account->isAdmin()) 
            throw new LocalNonAdminException();
        
        else return parent::Create($database, $input, $filesystem);
    }
    
    public function canGetFreeSpace() : bool { return true; }
    
    public function usesBandwidth() : bool { return false; }
    
    public function GetFreeSpace() : int
    {
        $space = disk_free_space($this->GetPath());
        if ($space === false) throw new FreeSpaceFailedException();
        else return (int)$space;
    }

    protected function GetFullURL(string $path = "") : string
    {
        return $this->GetPath($path); // TODO use file:// ?
    }
    
    /**
     * Import the file quickly by just renaming it if allowed
     * @see FWrapper::ImportFile()
     */
    protected function SubImportFile(string $src, string $dest, bool $istemp) : self
    {
        if (!$istemp) return parent::SubImportFile($src, $dest, $istemp);
        
        if (!rename($src, $this->GetFullURL($dest)))
            throw new FileCreateFailedException();
        return $this;
    }
}
