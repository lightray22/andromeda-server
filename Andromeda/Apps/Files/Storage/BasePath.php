<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\FieldTypes;
use Andromeda\Core\IOFormat\SafeParams;

/** A storage that has a base path */
trait BasePath
{
    /** The base path of the storage */
    private FieldTypes\StringType $path;

    protected function BasePathCreateFields() : void
    {
        $fields = array();
        $this->path = $fields[] = new FieldTypes\StringType('path');
        $this->RegisterChildFields($fields);
    }

    /** Returns the Create usage for the BasePath trait */
    public static function GetBasePathCreateUsage() : string { return "--path fspath"; }
    
    /** 
     * Sets fields for the BasePath trait
     * @return $this
     */
    protected function BasePathCreate(SafeParams $params) : self
    {
        $path = $params->GetParam('path')->GetFSPath();
        $this->path->SetValue($path);
        return $this;
    }
    
    /** Returns the Edit usage for the BasePath trait */
    public static function GetBasePathEditUsage() : string { return "[--path fspath]"; }
    
    /** 
     * Sets fields for the BasePath trait
     * @return $this
     */
    protected function BasePathEdit(SafeParams $params) : self
    {
        if ($params->HasParam('path')) 
            $this->path->SetValue($params->GetParam('path')->GetFSPath());
        return $this;
    }
    
    /** Returns the given path with no leading, trailing or duplicate / */
    protected static function cleanPath(string $path) : string
    { 
        return implode('/',array_filter(explode('/',$path)));
    }
    
    /** 
     * Returns the full storage level path for the given root-relative path
     * @param string $path path to add to the result
     */
    protected function GetPath(string $path = "") : string
    {
        return $this->path->GetValue().'/'.$path;
    }
}
