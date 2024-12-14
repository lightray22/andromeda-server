<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, SingletonObject};
use Andromeda\Core\Database\Exceptions\DatabaseException;

/** A singleton object that stores a version field */
abstract class BaseConfig extends SingletonObject
{    
    /** @return string the lowercase name of the app */
    public abstract static function getAppname() : string;
    
    /** @return string the config database schema version */
    public abstract static function getVersion() : string;
    
    /** Date the config object was created */
    protected FieldTypes\Timestamp $date_created;
    /** Version of the app that owns this config */
    protected FieldTypes\StringType $version;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->version = $fields[] = new FieldTypes\StringType('version');
        
        $this->RegisterChildFields($fields);
        
        parent::CreateFields();
    }
    
    /** 
     * Create a new config singleton in the given database 
     * @return static
     */
    public abstract static function Create(ObjectDatabase $database);

    /** 
     * @throws Exceptions\InstallRequiredException if config is not installed
     * @throws Exceptions\UpgradeRequiredException if the version in the given data does not match
     * @return static 
     */
    public static function GetInstance(ObjectDatabase $database) : self
    {
        try { return parent::GetInstance($database); }
        catch (DatabaseException $e) 
        {
            if ($database->HasApiPackage()) // log just in case
                $database->GetApiPackage()->GetErrorManager()->LogException($e);
            throw new Exceptions\InstallRequiredException(static::getAppname()); 
        }
    }
    
    /** true if we should skip the version check when loading */
    protected static bool $skipVersionCheck = false;
    
    /** 
     * Load the object and force updating its version instead of checking 
     * @return static
     */
    public static function ForceUpdate(ObjectDatabase $database) : self
    {
        static::$skipVersionCheck = true;
        $obj = static::GetInstance($database);
        static::$skipVersionCheck = false;

        $obj->version->SetValue(static::getVersion());
        return $obj;
    }
    
    /** @throws Exceptions\UpgradeRequiredException if the version in the given data does not match */
    public function __construct(ObjectDatabase $database, array $data, bool $created)
    {
        $version = $data['version'] ?? null;
        if ($version !== null && $version !== static::getVersion() && !static::$skipVersionCheck)
            throw new Exceptions\UpgradeRequiredException(static::getAppname(), (string)$version);
        
        parent::__construct($database, $data, $created);
    }

    public function PostConstruct(bool $created) : void
    {
        if (!$created) return; // early return
        $this->date_created->SetTimeNow();
        $this->version->SetValue(static::getVersion());
    }
}
