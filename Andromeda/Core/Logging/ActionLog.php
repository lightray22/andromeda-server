<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseApp, Config, Utilities};
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\{Input, SafeParams, IOInterface};

/** 
 * Log entry representing an app action in a request 
 * 
 * @phpstan-import-type ScalarArray from Utilities
 * @phpstan-import-type ScalarOrArray from Utilities
 * @phpstan-type ActionLogJ array{time:float, addr:string, agent:string, app:string, action:string, 
 *    authuser?:string, params?:ScalarArray, files?:ScalarArray, details?:ScalarArray}
 */
class ActionLog extends BaseLog
{
    use TableTypes\HasTable;

    /** @return array<string, class-string<self>> */
    private static function GetLogApps(ObjectDatabase $database) : array
    {
        return array_filter(array_map(function(BaseApp $app){ return $app->getLogClass(); },
            $database->GetApiPackage()->GetAppRunner()->GetApps()));
    }

    /** @return array<string, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array 
    {
        $map = self::GetLogApps($database);
        // have to add this so DB Load() knows to load by the base class also
        $map[""] = self::class;
        return $map;
    }

    public static function HasTypedRows() : bool { return true; }
    
    public static function GetWhereChild(ObjectDatabase $database, QueryBuilder $q, string $class) : string
    {
        $logapps = self::GetLogApps($database); // appname->class
        $classmap = array_flip($logapps); // class->appname
        $table = $database->GetClassTableName(self::class);
        
        if (array_key_exists($class, $classmap))
            return $q->Equals("$table.app", $classmap[$class],false);
        else 
            return $q->Not($q->ManyEqualsOr("$table.app", array_keys($logapps),false));
    }
    
    /** @return class-string<self> child class of row */
    public static function GetRowClass(ObjectDatabase $database, array $row) : string
    {
        $app = (string)$row['app'];
        $map = self::GetChildMap($database);
        
        // apps previously logged might be uninstalled now
        return array_key_exists($app, $map) ? $map[$app] : self::class;
    }
    
    /** Timestamp of the request */
    private FieldTypes\Timestamp $time;
    /** Interface address used for the request */
    private FieldTypes\StringType $addr;
    /** Interface user-agent used for the request */
    private FieldTypes\StringType $agent;
    /** Error code if response was an error (or null) */
    private FieldTypes\NullIntType $errcode;
    /** Error message if response was an error (or null) */
    private FieldTypes\NullStringType $errtext;
    
    /** Action app name */
    private FieldTypes\StringType $app;
    /** Action action name */
    private FieldTypes\StringType $action;
    /** Basic auth username if present */
    private FieldTypes\NullStringType $authuser;
    /** 
     * Optional input parameter logging 
     * @var FieldTypes\NullJsonArray<array<string, ScalarOrArray>>
     */
    private FieldTypes\NullJsonArray $params;
    /**
     * Optional input files logging
     * @var FieldTypes\NullJsonArray<array<string, array{name:string, path:string, size:int}>>
     */
    private FieldTypes\NullJsonArray $files;
    /** 
     * Optional app-specific details if no subtable 
     * @var FieldTypes\NullJsonArray<array<string, ScalarArray>>
     */
    private FieldTypes\NullJsonArray $details;
    
    /** 
     * Temporary array of logged input params to be saved 
     * @var array<string, ScalarOrArray>
     */
    private array $params_tmp;
    /**
     * Temporary array of logged input files to be saved
     * @var array<string, array{name:string, path:string, size:int}>
     */
    private array $files_tmp;
    /** 
     * Temporary array of logged details to be saved 
     * @var array<string, ScalarOrArray>
     */
    private array $details_tmp;
    
    private bool $writtenToFile = false;

    protected function CreateFields() : void
    {
        $fields = array();

        $fields[] = $this->time =    new FieldTypes\Timestamp('time');
        $fields[] = $this->addr =    new FieldTypes\StringType('addr');
        $fields[] = $this->agent =   new FieldTypes\StringType('agent');
        $fields[] = $this->errcode = new FieldTypes\NullIntType('errcode');
        $fields[] = $this->errtext = new FieldTypes\NullStringType('errtext');
        $fields[] = $this->app =     new FieldTypes\StringType('app');
        $fields[] = $this->action =  new FieldTypes\StringType('action');
        $fields[] = $this->authuser = new FieldTypes\NullStringType('authuser');
        $fields[] = $this->params =  new FieldTypes\NullJsonArray('params');
        $fields[] = $this->files =   new FieldTypes\NullJsonArray('files');
        $fields[] = $this->details = new FieldTypes\NullJsonArray('details');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** Creates a new access log entry from the given resources */
    public static function Create(ObjectDatabase $database, IOInterface $interface, Input $input) : static
    {
        $obj = $database->CreateObject(static::class);

        $obj->time->SetTimeNow();
        $obj->addr->SetValue($interface->getAddress());
        $obj->agent->SetValue($interface->getUserAgent());
        $obj->app->SetValue($input->GetApp());
        $obj->action->SetValue($input->GetAction());
        
        $details = $database->GetApiPackage()->GetConfig()->GetActionLogDetails();
        if ($details !== 0) $input->SetLogger($obj);
        return $obj;
    }

    /** Returns the time this request log was created */
    public function GetTime() : float { return $this->time->GetValue(); }
    
    /** Sets the given exception as the request result */
    public function SetError(\Throwable $e) : self
    {
        $this->errcode->SetValue((int)$e->getCode());
        $this->errtext->SetValue($e->getMessage());
        return $this;
    }
    
    /**
     * Returns the configured details log detail level
     *
     * If 0, details logs will be discarded, else see Config enum
     * @see Config::GetActionLogDetails()
     */
    public function GetDetailsLevel() : int { return $this->GetApiPackage()->GetConfig()->GetActionLogDetails(); }
    
    /** 
     * Log to the app-specific "details" field if not NONE details
     * 
     * This should be used for data that doesn't make sense to have its own DB column.
     * As this field is stored as JSON, its subfields cannot be selected by in the DB.
     * 
     * @param string $key array key in log
     * @param ScalarOrArray $value the data value
     * @param bool $onlyFull only log if details level is FULL
     * @return $this
     */
    public function LogDetails(string $key, $value, bool $onlyFull = false) : self
    {
        $minlog = $onlyFull ? Config::ACTLOG_DETAILS_FULL : Config::ACTLOG_DETAILS_BASIC;
        if ($this->GetDetailsLevel() >= $minlog)
        {
            $this->details_tmp ??= array();
            $this->details_tmp[$key] = $value;
        }
        return $this;
    }

    /** 
     * Returns a direct reference to the input params log array 
     * @return array<string, ScalarOrArray>
     */
    public function &GetParamsLogRef() : array
    {
        $this->params_tmp ??= array();
        return $this->params_tmp;
    }
    
    /**
     * Returns a direct reference to the input files log array
     * @return array<string, array{name:string, path:string, size:int}>
     */
    public function &GetFilesLogRef() : array
    {
        $this->files_tmp ??= array();
        return $this->files_tmp;
    }
    
    /** @return $this */
    public function SetAuthUser(string $username) : self
    {
        $this->authuser->SetValue($username); return $this;
    }

    /** Writes the temporary input logging arrays to their DB values to be saved */
    private function CollectParams() : void
    {
        if (isset($this->params_tmp) && count($this->params_tmp) !== 0)
            $this->params->SetArray($this->params_tmp);
        
        if (isset($this->files_tmp) && count($this->files_tmp) !== 0)
            $this->files->SetArray($this->files_tmp);
            
        if (isset($this->details_tmp) && count($this->details_tmp) !== 0)
            $this->details->SetArray($this->details_tmp);
    }
    
    /** 
     * Forces saves to DB regardless of config (testing ONLY)
     * @return $this
     */
    public function ForceDBSave() : self
    {
        $this->CollectParams();
        return parent::Save();
    }
    
    /** @return $this */
    public function Save(bool $onlyAlways = false) : self
    {
        $this->CollectParams();
            
        $config = $this->GetApiPackage()->GetConfig();
        if ($config->GetEnableActionLogDB()) parent::Save();
        
        return $this;
    }

    /** 
     * Writes the log to the log file - done separately from Save()
     * so that this is done AFTER all other objects have successfully saved
     * @throws Exceptions\MultiFileWriteException if called > once
     */
    public function WriteFile() : self
    {
        $config = $this->GetApiPackage()->GetConfig();

        if ($config->GetEnableActionLogFile() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            if ($this->writtenToFile)
                throw new Exceptions\MultiFileWriteException();
            $this->writtenToFile = true;
        
            $data = Utilities::JSONEncode($this->GetClientObject());
            file_put_contents("$logdir/actions.log", $data.PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        
        return $this;
    }
   
    public static function GetPropUsage(ObjectDatabase $database) : string 
    {
        $appstr = implode("|",array_filter(array_keys(self::GetChildMap($database))));
        return "[--mintime float] [--maxtime float] [--addr utf8] [--agent utf8] ".
            "[--errcode ?int32] [--errtext ?utf8] [--asc bool] [--app $appstr] [--action alphanum]"; 
    }
    
    /** Returns the app-specific usage for classes that extend this one */
    protected static function GetAppPropUsage() : string { return ""; }
    
    /** 
     * Returns the array of app-specific propUsage strings
      * @return list<string> 
      */
    public static function GetAppPropUsages(ObjectDatabase $database) : array
    {
        $retval = array();
        foreach (self::GetChildMap($database) as $appname=>$logclass)
        {
            if ($appname !== "") 
                $retval[] = "--app $appname ".$logclass::GetAppPropUsage();
        }
        return $retval;
    }

    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $isCount = false) : array
    {
        $criteria = array();
        
        if ($params->HasParam('maxtime')) $criteria[] = $q->LessThan("time", $params->GetParam('maxtime')->GetFloat());
        if ($params->HasParam('mintime')) $criteria[] = $q->GreaterThan("time", $params->GetParam('mintime')->GetFloat());
        
        if ($params->HasParam('addr')) $criteria[] = $q->Equals("addr", $params->GetParam('addr')->GetUTF8String());
        if ($params->HasParam('agent')) $criteria[] = $q->Like("agent", $params->GetParam('agent')->GetUTF8String());
        
        if ($params->HasParam('errcode')) $criteria[] = $q->Equals("errcode", $params->GetParam('errcode')->GetNullInt32());
        if ($params->HasParam('errtext')) $criteria[] = $q->Equals("errtext", $params->GetParam('errtext')->GetNullUTF8String());
        
        if ($params->HasParam('app')) $criteria[] = $q->Equals("app", $params->GetParam('app')->GetAlphanum());
        if ($params->HasParam('action')) $criteria[] = $q->Equals("action", $params->GetParam('action')->GetAlphanum());
        
        if (!$isCount) $q->OrderBy("time", !$params->GetOptParam('asc',false)->GetBool()); // always sort by time, default desc

        return $criteria;
    }
    
    /** @return class-string<self> */
    protected static function GetPropClass(ObjectDatabase $database, SafeParams $params) : string
    {
        if ($params->HasParam('app'))
        {
            $map = self::GetChildMap($database);
            $app = $params->GetParam('app')->GetAlphanum();
            if (array_key_exists($app, $map)) return $map[$app];
        }
        
        return self::class;
    }

    public static function LoadByParams(ObjectDatabase $database, SafeParams $params) : array
    {
        $objs = parent::LoadByParams($database, $params);
        
        // now we need to re-sort by time as loading via child classes may not sort the result
        $desc = !$params->GetOptParam('asc',false)->GetBool(); // default desc

        uasort($objs, function(self $a, self $b)use($desc)
        {
            $v1 = $a->GetTime(); $v2 = $b->GetTime();
            return $desc ? ($v2 <=> $v1) : ($v1 <=> $v2);
        });
 
        return $objs;
    }
    
    /**
     * Returns the printable client object of this action log
     * @param bool $expand if true, expand linked objects
     * @return ActionLogJ
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = array(            
            'time' => $this->time->GetValue(),
            'addr' => $this->addr->GetValue(),
            'agent' => $this->agent->GetValue(),
            'app' => $this->app->GetValue(),
            'action' => $this->action->GetValue()
        );
        
        if (($errcode = $this->errcode->TryGetValue()) !== null)
            $retval['errcode'] = $errcode;
        
        if (($errtext = $this->errtext->TryGetValue()) !== null)
            $retval['errtext'] = $errtext;

        if (($authuser = $this->authuser->TryGetValue()) !== null)
            $retval['authuser'] = $authuser;
        
        if (($params = $this->params->TryGetArray()) !== null)
            $retval['params'] = $params;
        
        if (($files = $this->files->TryGetArray()) !== null)
            $retval['files'] = $files;
            
        if (($details = $this->details->TryGetArray()) !== null)
            $retval['details'] = $details;
        
        return $retval;
    }
}
