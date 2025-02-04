<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\Input;
use Andromeda\Apps\Accounts\Account;

/** 
 * A storage on local-disk on the server
 * 
 * Only admin can add storages of this type!
 */
class Local extends FWrapper
{
    use BasePath, TableTypes\TableNoChildren;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->RegisterFields($fields, self::class);
        $this->BasePathCreateFields();
        parent::CreateFields();
    }

    public static function GetCreateUsage() : string { return self::GetBasePathCreateUsage(); }
    
    public static function GetEditUsage() : string { return self::GetBasePathEditUsage(); }

    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : self
    {
        if ($owner !== null && !$owner->isAdmin()) 
            throw new Exceptions\LocalNonAdminException();
        
        $obj = parent::Create($database, $input, $owner);
        $obj->BasePathCreate($input->GetParams());
        return $obj;
    }

    public function Edit(Input $input) : self
    {
        $this->BasePathEdit($input->GetParams());
        return parent::Edit($input);
    }

    public function Activate() : self { return $this; }
    
    public function canGetFreeSpace() : bool { return true; }
    
    public function usesBandwidth() : bool { return false; }
    
    public function GetFreeSpace() : int
    {
        if (($space = disk_free_space($this->GetPath())) === false)
            throw new Exceptions\FreeSpaceFailedException();
        else return (int)$space; // disk_free_space returns float
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
            throw new Exceptions\FileCreateFailedException();
        return $this;
    }
}
