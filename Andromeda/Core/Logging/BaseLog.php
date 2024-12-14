<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\SafeParams;

/** Base class for access logs, providing some common DB functions for user viewing */
abstract class BaseLog extends BaseObject
{
    protected const IDLength = 20;
    
    /** Returns the CLI usage string for loading objects by properties */
    public static abstract function GetPropUsage(ObjectDatabase $database) : string;
    
    /**
     * Adds query filter parameters using the given input, default sort by time DESC
     * 
     * MUST prefix column names with the appropriate table name
     * @param ObjectDatabase $database database reference
     * @param QueryBuilder $q query to create params with
     * @param SafeParams $params params with user supplied criteria
     * @param bool $isCount if true, this is a COUNT query
     * @return list<string> array of WHERE strings
     */
    public static abstract function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $isCount = false) : array;
    
    /**
     * Returns the class we should load logs as
     * @param SafeParams $params input to determine class
     * @return class-string<static>
     */
    protected static function GetPropClass(ObjectDatabase $database, SafeParams $params) : string { return static::class; }
    
    /** Returns the common CLI usage for loading log entries */
    public static function GetLoadUsage() : string { return "[--logic and|or] [--limit uint] [--offset uint]"; }
    
    /** Returns the common CLI usage for counting log entries */
    public static function GetCountUsage() : string { return "[--logic and|or]"; }
    
    /**
     * Returns a compiled query selecting and sorting rows from the given input
     * @param ObjectDatabase $database database reference
     * @param SafeParams $params params with user filter params
     * @param bool $isCount if true, this is a COUNT query
     * @return QueryBuilder built query with WHERE set
     */
    protected static function GetWhereQuery(ObjectDatabase $database, SafeParams $params, bool $isCount = false) : QueryBuilder
    {
        $q = new QueryBuilder(); $criteria = static::GetPropCriteria($database, $q, $params, $isCount);
        
        $or = ($params->HasParam('logic') ? $params->GetParam('logic', SafeParams::PARAMLOG_ONLYFULL)
            ->FromAllowlist(array('and','or')) : null) === 'or'; // default AND
        
        if (count($criteria) === 0)
        {
            if ($or) $q->Where("FALSE"); return $q; // match nothing
        }
        else return $q->Where($or ? $q->Or(...$criteria) : $q->And(...$criteria));
    }
    
    /**
     * Loads log entries the given input, default 100 max and sort by time DESC
     * @param ObjectDatabase $database database reference
     * @param SafeParams $params user input with selectors
     * @return array<string, static> loaded log entries indexed by ID
     */
    public static function LoadByParams(ObjectDatabase $database, SafeParams $params) : array
    {
        $class = static::GetPropClass($database, $params);
        
        $q = $class::GetWhereQuery($database, $params);
        
        $q->Limit($params->GetOptParam('limit',100)->GetUint());
        
        if ($params->HasParam('offset')) 
            $q->Offset($params->GetParam('offset')->GetUint());
        
        return $database->LoadObjectsByQuery($class, $q);
    }
    
    /**
     * Counts log entries by the given input
     * @param ObjectDatabase $database database reference
     * @param SafeParams $params user input with selectors
     * @return int number of log entries that match
     */
    public static function CountByParams(ObjectDatabase $database, SafeParams $params) : int
    {
        $class = static::GetPropClass($database, $params);
        
        $q = static::GetWhereQuery($database, $params, true);
        
        return $database->CountObjectsByQuery($class, $q);
    }

    /** Deletes all log entries, returning the count */
    public static function DeleteAll(ObjectDatabase $database) : int
    {
        return $database->DeleteObjectsByQuery(static::class, new QueryBuilder());
    }
}
