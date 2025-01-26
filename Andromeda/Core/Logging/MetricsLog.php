<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\{Config, /*phpstan*/RunContext, Utilities};
use Andromeda\Core\Database\{BaseObject, DBStats, FieldTypes, ObjectDatabase, TableTypes};

/** 
 * Log entry representing a performance metrics for a request
 * @phpstan-import-type ScalarArray from Utilities
 */
class MetricsLog extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    protected const IDLength = 20;
    
    /** 
     * Link to the main log for this request
     * @var FieldTypes\NullObjectRefT<ActionLog>
     */
    private FieldTypes\NullObjectRefT $actionlog;
    /** Timestamp of the request */
    private FieldTypes\Timestamp $date_created;
    /** Peak memory usage reported by PHP */
    private FieldTypes\IntType $peak_memory;
    /** The number of included PHP files */
    private FieldTypes\IntType $nincludes;
    /** The number of objects loaded in database memory */
    private FieldTypes\IntType $nobjects;
    
    private FieldTypes\IntType $init_db_reads;
    private FieldTypes\FloatType $init_db_read_time;
    private FieldTypes\IntType $init_db_writes;
    private FieldTypes\FloatType $init_db_write_time;
    private FieldTypes\FloatType $init_code_time;
    private FieldTypes\FloatType $init_autoloader_time;
    private FieldTypes\FloatType $init_total_time;

    /** The command action app name */
    private FieldTypes\StringType $app;
    /** The command action name */
    private FieldTypes\StringType $action;

    private FieldTypes\IntType $action_db_reads;
    private FieldTypes\FloatType $action_db_read_time;
    private FieldTypes\IntType $action_db_writes;
    private FieldTypes\FloatType $action_db_write_time;
    private FieldTypes\FloatType $action_code_time;
    private FieldTypes\FloatType $action_autoloader_time;
    private FieldTypes\FloatType $action_total_time;

    private FieldTypes\NullIntType $commit_db_reads;
    private FieldTypes\NullFloatType $commit_db_read_time;
    private FieldTypes\NullIntType $commit_db_writes;
    private FieldTypes\NullFloatType $commit_db_write_time;
    private FieldTypes\NullFloatType $commit_code_time;
    private FieldTypes\NullFloatType $commit_autoloader_time;
    private FieldTypes\NullFloatType $commit_total_time;

    /** The total number of read queries */
    private FieldTypes\IntType $db_reads;
    /** Time total spent on read queries */
    private FieldTypes\FloatType $db_read_time;
    /** The total number of write queries */
    private FieldTypes\IntType $db_writes;
    /** Time total spent on write queries */
    private FieldTypes\FloatType $db_write_time;
    /** Time total spent on PHP code (non-query) */
    private FieldTypes\FloatType $code_time;
    /** Time total spent on PHP code (non-query) */
    private FieldTypes\FloatType $autoloader_time;
    /** Total time measured by the DBStats */
    private FieldTypes\FloatType $total_time;

    /** 
     * Garbage collection stats reported by PHP 
     * @var FieldTypes\NullJsonArray<array<string,scalar>>
     */
    private FieldTypes\NullJsonArray $gcstats;
    /**
     * Resource usage reported by PHP 
     * @var FieldTypes\NullJsonArray<array<string,scalar>>
     */
    private FieldTypes\NullJsonArray $rusage;
    /** 
     * List of files included by PHP 
     * @var FieldTypes\NullJsonArray<list<string>>
     */
    private FieldTypes\NullJsonArray $includes;
    /** 
     * List of objects in database memory 
     * @var FieldTypes\NullJsonArray<array<string, array<string, string>>>
     */
    private FieldTypes\NullJsonArray $objects;
    /** 
     * List of database queries 
     * @var FieldTypes\NullJsonArray<array<array{query:string,time:float}>>
     */
    private FieldTypes\NullJsonArray $queries;
    /** 
     * The main debug log supplement 
     * @var FieldTypes\NullJsonArray<ScalarArray>
     */
    private FieldTypes\NullJsonArray $debughints;
    
    private bool $writtenToFile = false;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $fields[] = $this->actionlog =   new FieldTypes\NullObjectRefT(ActionLog::class,'actionlog');
        $fields[] = $this->date_created = new FieldTypes\Timestamp('date_created');
        $fields[] = $this->peak_memory =  new FieldTypes\IntType('peak_memory');
        $fields[] = $this->nincludes =    new FieldTypes\IntType('nincludes');
        $fields[] = $this->nobjects =     new FieldTypes\IntType('nobjects');
        
        $fields[] = $this->init_db_reads =      new FieldTypes\IntType('init_db_reads');
        $fields[] = $this->init_db_read_time =  new FieldTypes\FloatType('init_db_read_time');
        $fields[] = $this->init_db_writes =     new FieldTypes\IntType('init_db_writes');
        $fields[] = $this->init_db_write_time = new FieldTypes\FloatType('init_db_write_time');
        $fields[] = $this->init_code_time =     new FieldTypes\FloatType('init_code_time');
        $fields[] = $this->init_autoloader_time = new FieldTypes\FloatType('init_autoloader_time');
        $fields[] = $this->init_total_time =    new FieldTypes\FloatType('init_total_time');

        $fields[] = $this->app =     new FieldTypes\StringType('app');
        $fields[] = $this->action =  new FieldTypes\StringType('action');

        $fields[] = $this->action_db_reads =      new FieldTypes\IntType('action_db_reads');
        $fields[] = $this->action_db_read_time =  new FieldTypes\FloatType('action_db_read_time');
        $fields[] = $this->action_db_writes =     new FieldTypes\IntType('action_db_writes');
        $fields[] = $this->action_db_write_time = new FieldTypes\FloatType('action_db_write_time');
        $fields[] = $this->action_code_time =     new FieldTypes\FloatType('action_code_time');
        $fields[] = $this->action_autoloader_time = new FieldTypes\FloatType('action_autoloader_time');
        $fields[] = $this->action_total_time =    new FieldTypes\FloatType('action_total_time');

        $fields[] = $this->commit_db_reads =      new FieldTypes\NullIntType('commit_db_reads');
        $fields[] = $this->commit_db_read_time =  new FieldTypes\NullFloatType('commit_db_read_time');
        $fields[] = $this->commit_db_writes =     new FieldTypes\NullIntType('commit_db_writes');
        $fields[] = $this->commit_db_write_time = new FieldTypes\NullFloatType('commit_db_write_time');
        $fields[] = $this->commit_code_time =     new FieldTypes\NullFloatType('commit_code_time');
        $fields[] = $this->commit_autoloader_time = new FieldTypes\NullFloatType('commit_autoloader_time');
        $fields[] = $this->commit_total_time =    new FieldTypes\NullFloatType('commit_total_time');

        $fields[] = $this->db_reads =      new FieldTypes\IntType('db_reads');
        $fields[] = $this->db_read_time =  new FieldTypes\FloatType('db_read_time');
        $fields[] = $this->db_writes =     new FieldTypes\IntType('db_writes');
        $fields[] = $this->db_write_time = new FieldTypes\FloatType('db_write_time');
        $fields[] = $this->code_time =     new FieldTypes\FloatType('code_time');
        $fields[] = $this->autoloader_time = new FieldTypes\FloatType('autoloader_time');
        $fields[] = $this->total_time =    new FieldTypes\FloatType('total_time');

        $fields[] = $this->gcstats =  new FieldTypes\NullJsonArray('gcstats');
        $fields[] = $this->rusage =   new FieldTypes\NullJsonArray('rusage');
        $fields[] = $this->includes = new FieldTypes\NullJsonArray('includes');
        $fields[] = $this->objects =  new FieldTypes\NullJsonArray('objects');
        $fields[] = $this->queries =  new FieldTypes\NullJsonArray('queries');
        $fields[] = $this->debughints = new FieldTypes\NullJsonArray('debughints');
        
        $this->RegisterFields($fields, self::class);
         
        parent::CreateFields();
    }
    
    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'actionlog';
        return $ret;
    }
    
    /**
     * Logs metrics and returns a metrics object
     * @param int $level logging level
     * @param ObjectDatabase $database database reference
     * @param DBStats $init construct stats
     * @param RunContext $context run context with metrics
     * @param DBStats $total total request stats
     */
    public static function Create(int $level, ObjectDatabase $database, DBStats $init, 
                                RunContext $context, DBStats $total) : static
    {
        // TODO FUTURE it would be nice if this worked with a read-only database
        $obj = $database->CreateObject(static::class);

        $obj->date_created->SetTimeNow();
        $obj->actionlog->SetObject($context->TryGetActionLog());
        
        $obj->peak_memory->SetValue(memory_get_peak_usage());
        $obj->nincludes->SetValue(count(get_included_files()));
        $obj->nobjects->SetValue($database->getLoadedCount());
        
        $obj->init_db_reads->SetValue($init->GetReads());
        $obj->init_db_read_time->SetValue($init->GetReadTime());
        $obj->init_db_writes->SetValue($init->GetWrites());
        $obj->init_db_write_time->SetValue($init->GetWriteTime());
        $obj->init_code_time->SetValue($init->GetCodeTime());
        $obj->init_autoloader_time->SetValue($init->GetAutoloaderTime());
        $obj->init_total_time->SetValue($init->GetTotalTime());

        $obj->app->SetValue($context->GetInput()->GetApp());
        $obj->action->SetValue($context->GetInput()->GetAction());

        $actionstat = $context->GetActionMetrics();
        $obj->action_db_reads->SetValue($actionstat->GetReads());
        $obj->action_db_read_time->SetValue($actionstat->GetReadTime());
        $obj->action_db_writes->SetValue($actionstat->GetWrites());
        $obj->action_db_write_time->SetValue($actionstat->GetWriteTime());
        $obj->action_code_time->SetValue($actionstat->GetCodeTime());
        $obj->action_autoloader_time->SetValue($actionstat->GetAutoloaderTime());
        $obj->action_total_time->SetValue($actionstat->GetTotalTime());

        if ($context->HasCommitMetrics())
        {
            $commitstat = $context->GetCommitMetrics();
            $obj->commit_db_reads->SetValue($commitstat->GetReads());
            $obj->commit_db_read_time->SetValue($commitstat->GetReadTime());
            $obj->commit_db_writes->SetValue($commitstat->GetWrites());
            $obj->commit_db_write_time->SetValue($commitstat->GetWriteTime());
            $obj->commit_code_time->SetValue($commitstat->GetCodeTime());
            $obj->commit_autoloader_time->SetValue($commitstat->GetAutoloaderTime());
            $obj->commit_total_time->SetValue($commitstat->GetTotalTime());
        }

        $obj->db_reads->SetValue($total->GetReads());
        $obj->db_read_time->SetValue($total->GetReadTime());
        $obj->db_writes->SetValue($total->GetWrites());
        $obj->db_write_time->SetValue($total->GetWriteTime());
        $obj->code_time->SetValue($total->GetCodeTime());
        $obj->autoloader_time->SetValue($total->GetAutoloaderTime());
        $obj->total_time->SetValue($total->GetTotalTime());

        if ($level >= Config::METRICS_EXTENDED)
        {
            $obj->gcstats->SetArray(gc_status());
            
            $rusage = getrusage();
            if ($rusage === false) $rusage = null;
            $obj->rusage->SetArray($rusage); // @phpstan-ignore-line type missing
            
            $obj->includes->SetArray(get_included_files());
            $obj->objects->SetArray($database->getLoadedObjectIDs());
            $obj->queries->SetArray($total->getQueries());
            
            $errman = $database->GetApiPackage()->GetErrorManager();
            $obj->debughints->SetArray($errman->GetDebugHints());
        }
        
        return $obj;
    }

    /** 
     * @return $this
     * @throws Exceptions\MultiFileWriteException if called > once
     */
    public function Save(bool $onlyAlways = false) : self
    {
        $config = $this->GetApiPackage()->GetConfig();
        
        if ($config->GetMetricsLog2DB()) parent::Save(); // ignore $onlyAlways (save on error)
        
        if ($config->GetMetricsLog2File() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            if ($this->writtenToFile) 
                throw new Exceptions\MultiFileWriteException();
            $this->writtenToFile = true;
            
            $data = Utilities::JSONEncode($this->GetClientObject());
            file_put_contents("$logdir/metrics.log", $data.PHP_EOL, FILE_APPEND | LOCK_EX); 
        }

        return $this;
    }

    /**
     * Returns the printable client object of this metrics
     * @param bool $isError if true, omit duplicated debugging information
     * @return array{date_created:float, peak_memory:int, nincludes:int, nobjects:int, 
     *   init_stats:array{reads:int,read_time:float,writes:int,write_time:float,code_time:float,autoloader_time:float,total_time:float}, 
     *   action_stats:array{reads:int,read_time:float,writes:int,write_time:float,code_time:float,autoloader_time:float,total_time:float,app:string,action:string}, 
     *   commit_stats:array{reads:?int,read_time:?float,writes:?int,write_time:?float,code_time:?float,autoloader_time:?float,total_time:?float}, 
     *   total_stats:array{reads:int,read_time:float,writes:int,write_time:float,code_time:float,autoloader_time:float,total_time:float},
     *   gcstats?:?ScalarArray, rusage?:?ScalarArray, includes?:?ScalarArray, objects?:?ScalarArray, queries?:?ScalarArray, debughints?:?ScalarArray}
     * ... gcstats,rusage,includes,objects,queries,debughints are set if Config::METRICS_EXTENDED, objects,queries,debughints are only if !wasError
     */
    public function GetClientObject(bool $isError = false) : array
    {
        $retval = array(
            'date_created' => $this->date_created->GetValue(),
            'peak_memory' =>  $this->peak_memory->GetValue(),
            'nincludes' =>    $this->nincludes->GetValue(),
            'nobjects' =>     $this->nobjects->GetValue(),
            'init_stats' => array
            (
                'reads' =>      $this->init_db_reads->GetValue(),
                'read_time' =>  $this->init_db_read_time->GetValue(),
                'writes' =>     $this->init_db_writes->GetValue(),
                'write_time' => $this->init_db_write_time->GetValue(),
                'code_time' =>  $this->init_code_time->GetValue(),
                'autoloader_time' =>  $this->init_autoloader_time->GetValue(),
                'total_time' => $this->init_total_time->GetValue()
            ),
            'action_stats' => array(
                'app' =>        $this->app->GetValue(),
                'action' =>     $this->action->GetValue(),
                'reads' =>      $this->action_db_reads->GetValue(),
                'read_time' =>  $this->action_db_read_time->GetValue(),
                'writes' =>     $this->action_db_writes->GetValue(),
                'write_time' => $this->action_db_write_time->GetValue(),
                'code_time' =>  $this->action_code_time->GetValue(),
                'autoloader_time' =>  $this->action_autoloader_time->GetValue(),
                'total_time' => $this->action_total_time->GetValue()
            ),
            'commit_stats' => array(
                'reads' =>      $this->commit_db_reads->TryGetValue(),
                'read_time' =>  $this->commit_db_read_time->TryGetValue(),
                'writes' =>     $this->commit_db_writes->TryGetValue(),
                'write_time' => $this->commit_db_write_time->TryGetValue(),
                'code_time' =>  $this->commit_code_time->TryGetValue(),
                'autoloader_time' =>  $this->commit_autoloader_time->TryGetValue(),
                'total_time' => $this->commit_total_time->TryGetValue()
            ),
            'total_stats' => array(
                'reads' =>      $this->db_reads->GetValue(),
                'read_time' =>  $this->db_read_time->GetValue(),
                'writes' =>     $this->db_writes->GetValue(),
                'write_time' => $this->db_write_time->GetValue(),
                'code_time' =>  $this->code_time->GetValue(),
                'autoloader_time' =>  $this->autoloader_time->GetValue(),
                'total_time' => $this->total_time->GetValue()
            )
        );

        if ($this->gcstats->TryGetArray() !== null)
            $retval['gcstats'] = $this->gcstats->TryGetArray();
    
        if ($this->rusage->TryGetArray() !== null)
            $retval['rusage'] = $this->rusage->TryGetArray();

        if ($this->includes->TryGetArray() !== null)
            $retval['includes'] = $this->includes->TryGetArray();

        if (!$isError && $this->objects->TryGetArray() !== null) // duplicated in error log
            $retval['objects'] = $this->objects->TryGetArray();

        if (!$isError && $this->queries->TryGetArray() !== null) // duplicated in error log
            $retval['queries'] = $this->queries->TryGetArray();

        if (!$isError && $this->debughints->TryGetArray() !== null) // duplicated in error log
            $retval['debughints'] = $this->debughints->TryGetArray();

        return $retval;
    }
}
