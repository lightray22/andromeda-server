<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\SafeParams;

/**
 * Stores limits whose statistics are specific to a given time period.
 * 
 * A timed limit is thus composed of both the limited object and the time period.
 *
 * This allows limiting things that cannot decrease unless reset, e.g. bandwidth.
 * Limited objects can have multiple limits here for different time periods.
 * For example a filesystem could have both an hourly and monthly bandwidth limit.
 * 
 * Keeps a history of statistics rather than just resetting them at the end
 * of the applicable time period - each entry is a TimedStats.
 */
abstract class Timed extends Base
{
    /** array<limited object ID, array<self>> */
    protected static $cache = array();
    
    public static function GetDBClass() : string { return self::class; }

    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'objs_stats' => (new FieldTypes\ObjectRefs(TimedStats::class, 'limitobj', true))->autoDelete(),
            'timeperiod' => new FieldTypes\IntType(), // in seconds
            'max_stats_age' => new FieldTypes\IntType(),
            'limit_pubdownloads' => new FieldTypes\Limit(),
            'limit_bandwidth' => new FieldTypes\Limit()
        ));
    }
    
    /** Returns the time period for this timed limit */
    public function GetTimePeriod() : int { return $this->GetScalar('timeperiod'); }
    
    public const MAX_AGE_FOREVER = -1;
    
    /** Returns the maximum stats history age (-1 for forever - default) */
    public function GetMaxStatsAge() : int { return $this->TryGetScalar('max_stats_age') ?? self::MAX_AGE_FOREVER; }
    
    protected function Initialize() : self { return $this; }
    
    /**
     * Returns all Timed Limits with the given time period
     * @param ObjectDatabase $database database reference
     * @param int $period time period
     * @param int $count max objects to load
     * @param int $offset offset for loading
     * @return array<string, Timed> limits indexed by ID
     */
    public static function LoadAllForPeriod(ObjectDatabase $database, int $period, ?int $count = null, ?int $offset = null) : array
    {
        $q = new QueryBuilder(); $w = $q->Equals('timeperiod',$period);
        
        return static::LoadByQuery($database, $q->Where($w)->Limit($count)->Offset($offset));
    }
    
    /**
     * Returns all timed limits for the given limited object (all time periods)
     * @param ObjectDatabase $database database reference
     * @param StandardObject $obj the limited object
     * @return array<string, static> limits indexed by ID
     */
    public static function LoadAllForClient(ObjectDatabase $database, BaseObject $obj) : array
    {
        if (!array_key_exists($obj->ID(), static::$cache))
        {
            static::$cache[$obj->ID()] = static::LoadByObject($database, 'object', $obj, true);
        }
        else
        {
            static::$cache[$obj->ID()] = array_filter(static::$cache[$obj->ID()],
                function(Timed $lim){ return !$lim->isDeleted(); });
        }
        
        return static::$cache[$obj->ID()];
    }
    
    /**
     * Loads the limit object for the given object and time period
     * @param ObjectDatabase $database database reference
     * @param StandardObject $obj the limited object
     * @param int $period the time period
     * @return ?static limit object or null if none
     */
    public static function LoadByClientAndPeriod(ObjectDatabase $database, BaseObject $obj, int $period) : ?self
    {
        foreach (static::LoadAllForClient($database, $obj) as $lim)
        {
            if ($lim->GetTimePeriod() === $period) return $lim;
        }
        return null;
    }
    
    /** Deletes all limit objects corresponding to the given limited object */
    public static function DeleteByClient(ObjectDatabase $database, BaseObject $obj) : void
    {
        if (array_key_exists($obj->ID(), static::$cache)) static::$cache[$obj->ID()] = array();
        
        static::DeleteByObject($database, 'object', $obj, true);
    }
    
    /** Deletes all limit objects corresponding to the given limited object and time period */
    public static function DeleteByClientAndPeriod(ObjectDatabase $database, BaseObject $obj, int $period) : void
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('obj_object',FieldTypes\ObjectPoly::GetObjectDBValue($obj)),$q->Equals('timeperiod',$period));
        
        static::DeleteByQuery($database, $q->Where($w));
    }
    
    /**
     * Creates and caches a new timed limit for the given object
     * @param ObjectDatabase $database database reference
     * @param StandardObject $obj object to limit
     * @param int $timeperiod time period for limit
     * @return static new limit object
     */
    protected static function CreateTimed(ObjectDatabase $database, BaseObject $obj, int $timeperiod) : self
    {
        $newobj = static::BaseCreate($database)->SetObject('object',$obj)->SetScalar('timeperiod',$timeperiod);
        
        static::$cache[$obj->ID()] ??= array();
        static::$cache[$obj->ID()][] = $newobj;
        
        return $newobj;
    }

    /** Loads and returns the current stats for this limit */
    protected function GetCurrentStats() : TimedStats { return TimedStats::LoadCurrentByLimit($this->database, $this); }
    
    // pull counters from the current stats object
    protected function GetCounter(string $name) : int { return $this->GetCurrentStats()->GetCounter($name); }
    
    protected function DeltaCounter(string $name, int $delta = 1, bool $ignoreLimit = false) : self
    {
        $this->GetCurrentStats()->DeltaCounter($name, $delta, $ignoreLimit); return $this;        
    }
    
    /** Returns the command usage for SetTimedLimits() */
    public abstract static function GetTimedUsage() : string;
    
    /** Sets config for a timed limit */
    protected abstract function SetTimedLimits(SafeParams $params) : void;
    
    public static function GetConfigUsage() : string { return static::GetBaseUsage()." ".static::GetTimedUsage(); }
    
    public static function BaseConfigUsage() : string { return "--timeperiod uint32 [--max_pubdownloads ?uint32] [--max_bandwidth ?uint]"; }
    
    /** @return static */
    protected static function BaseConfigLimits(ObjectDatabase $database, BaseObject $obj, SafeParams $params) : self
    {
        $period = $params->GetParam('timeperiod')->GetUint32();
        
        $lim = static::LoadByClientAndPeriod($database, $obj, $period) 
            ?? static::CreateTimed($database, $obj, $period);
        
        $lim->SetBaseLimits($params); 
        $lim->SetTimedLimits($params);

        if ($params->HasParam('max_pubdownloads')) 
            $lim->SetCounterLimit('pubdownloads', $params->GetParam('max_pubdownloads')->GetNullUint32());
        
        if ($params->HasParam('max_bandwidth')) 
            $lim->SetCounterLimit('bandwidth', $params->GetParam('max_bandwidth')->GetNullUint());
        
        if ($lim->isCreated()) $lim->Initialize();
        else TimedStats::PruneStatsByLimit($database, $lim);
        
        return $lim;
    }
    
    /**
     * Returns a printable client object of this timed limit
     * @param bool $full if false, don't show anything
     * @return array<mixed> `{timeperiod:int, max_stats_age:?int, dates:{created:float}, 
            limits: {pubdownloads:?int, bandwidth:?int}`
     * @see Base::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $retval = parent::GetClientObject($full);
        
        if (!$full) return $retval;
        
        $retval += array(
            'timeperiod' => $this->GetTimePeriod(),
            'max_stats_age' => $this->GetMaxStatsAge(),
            'dates' => array(
                'created' => $this->GetDateCreated()
            ),
            'config' => array(), // need track_items/track_dlstats
            'limits' => Utilities::array_map_keys(function($p){ return $this->TryGetCounterLimit($p); },
                array('pubdownloads','bandwidth')
            )
        );
        
        return $retval;
    }
}
