<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Accounts/AuthActionLog.php"); use Andromeda\Apps\Accounts\AuthActionLog;

require_once(ROOT."/Apps/Files/Exceptions.php");
require_once(ROOT."/Apps/Files/Item.php");
require_once(ROOT."/Apps/Files/File.php");
require_once(ROOT."/Apps/Files/Folder.php");
require_once(ROOT."/Apps/Files/Share.php");

/** Access log for the files app */
class ActionLog extends AuthActionLog
{
    use TableTypes\TableNoChildren;
    
    // TODO comments with @var
    private FieldTypes\NullObjectRefT $file; // TODO combine file/folder to SubItem?
    private FieldTypes\NullObjectRefT $folder;
    private FieldTypes\NullObjectRefT $parent;
    private FieldTypes\NullObjectRefT $file_share;
    private FieldTypes\NullObjectRefT $folder_share; // TODO combine file/folder to item_share?
    private FieldTypes\NullObjectRefT $parent_share;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->file = $fields[] = new FieldTypes\NullObjectRefT(File::class, 'file');
        $this->folder = $fields[] = new FieldTypes\NullObjectRefT(Folder::class, 'folder'); // TODO SubFolder
        $this->parent = $fields[] = new FieldTypes\NullObjectRefT(Folder::class, 'folder'); // TODO RootFolder
        $this->file_share = $fields[] = new FieldTypes\NullObjectRefT(Share::class, 'file_share');
        $this->folder_share = $fields[] = new FieldTypes\NullObjectRefT(Share::class, 'folder_share');
        $this->parent_share = $fields[] = new FieldTypes\NullObjectRefT(Share::class, 'parent_share');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** 
     * Links this log to the given item accessor
     * @param bool $isParent if true, log as a parent folder access
     * @return $this
     */
    public function LogAccess(Item $item, ?Share $share, bool $isParent = false) : self // TODO replace with SetFileAccess, SetFolderAccess and SetParentAccess
    {        
        if ($isParent) 
        {
            $this->parent->SetObject($item);
            $this->parent_share->SetObject($share);
        }
        else if ($item instanceof File) 
        {
            if ($this->file->TryGetObjectID() !== null) 
                throw new ItemLogFullException();
            
            $this->file->SetObject($item);
            $this->file_share->SetObject($share);
        }
        else if ($item instanceof Folder) 
        {
            if ($this->folder->TryGetObjectID() !== null) 
                throw new ItemLogFullException();
            
            $this->folder->SetObject($item);
            $this->folder_share->SetObject($share);
        }
        else throw new BadItemTypeException();
        
        return $this;
    }

    public static function GetAppPropUsage() : string { return "[--file id] [--folder id] [--file_share id] [--folder_share id]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params) : array
    {
        $criteria = array();
        
        if ($params->HasParam('file')) 
            $criteria[] = $q->Equals("file", $params->GetParam('file')->GetRandstr());
        
        if ($params->HasParam('file_share')) 
            $criteria[] = $q->Equals("file_share", $params->GetParam('file_share')->GetRandstr());
        
        if ($params->HasParam('folder')) 
        {
            $folder = $params->GetParam("folder")->GetRandstr();
            $criteria[] = $q->Or($q->Equals("folder",$folder), 
                                 $q->Equals("parent",$folder));
        }
        
        if ($params->HasParam('folder_share'))
        {
            $folder = $params->GetParam("folder_share")->GetRandstr();
            $criteria[] = $q->Or($q->Equals("folder_share",$folder),
                                 $q->Equals("parent_share",$folder));
        }
        
        return array_merge($criteria, parent::GetPropCriteria($database, $q, $params));
    }
    
    /**
     * @return array add `{?file:id, ?folder:id, ?parent:id,
        ?file_share:id, ?folder_share:id, ?parent_share:id}`
       @see AuthActionLog::GetClientObject()
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = parent::GetClientObject($expand);
        
        if ($expand)
        {
            // TODO fix me
        }
        else
        {
            
        }
        
        return $retval; 
        
        /*foreach (array('file','folder','parent','file_share','folder_share','parent_share') as $prop)
            if (($id = $this->TryGetObjectID($prop)) !== null) $retval[$prop] = $id;
        
        return array_merge(parent::GetClientObject($expand), $retval);*/
    }
}
