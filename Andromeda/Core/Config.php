<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\{IOInterface, SafeParams};

/** 
 * The global framework config stored in the database
 * @phpstan-type ConfigJ array{apiver:string, apps:array<string,?string>, read_only:bool, enabled:bool}|array{
 *    apiver:string, apps:array<string,?string>, read_only:bool, enabled:bool, date_created:float, datadir:?string, actionlog_file:bool, actionlog_db:bool, actionlog_details:string, 
 *       metrics:string, metrics_dblog:bool, metrics_filelog:bool, email:bool, debug:string, debug_http:bool, debug_dblog:bool, debug_filelog:bool }
 */
class Config extends BaseConfig
{
    public static function getAppname() : string { return 'core'; }
    
    public static function getVersion() : string {
        return VersionInfo::toCompatVer(andromeda_version); }

    use TableTypes\TableNoChildren;

    /** Directory for basic server data (logs) */
    private FieldTypes\NullStringType $datadir;
    /** True if the server is read-only */
    private FieldTypes\BoolType $read_only;
    /** True if the server is enabled for HTTP */
    private FieldTypes\BoolType $enabled;
    /** True if outgoing email is allowed */
    private FieldTypes\BoolType $email;
    /** 
     * List of installed+enabled apps 
     * @var FieldTypes\JsonArray<list<string>>
     */
    private FieldTypes\JsonArray $apps;
    /** True if requests should be logged to DB */
    private FieldTypes\BoolType $actionlog_db;
    /** True if requests should be logged to a file */
    private FieldTypes\BoolType $actionlog_file;
    /** The details level enum for request logging */
    private FieldTypes\IntType $actionlog_details;
    /** The debug logging level enum */
    private FieldTypes\IntType $debug;
    /** True if debug/metrics can be sent over HTTP */
    private FieldTypes\BoolType $debug_http;
    /** True if server errors should be logged to the DB */
    private FieldTypes\BoolType $debug_dblog;
    /** True if server errors should be logged to a file */
    private FieldTypes\BoolType $debug_filelog;
    /** The performance metrics logging level enum */
    private FieldTypes\IntType $metrics;
    /** True if metrics should be logged to the DB */
    private FieldTypes\BoolType $metrics_dblog;
    /** True if metrics should be logged to a file */
    private FieldTypes\BoolType $metrics_filelog;
    
    /** 
     * Creates a new config singleton with default values 
     * @return static
     */
    public static function Create(ObjectDatabase $database) : self
    {
        $obj = $database->CreateObject(static::class);
        $obj->apps->SetArray(array());
        return $obj;
    }
    
    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return 
        "[--read_only bool] [--enabled bool] [--email bool] [--datadir ?fspath] ".
        "[--actionlog_db bool] [--actionlog_file bool] [--actionlog_details ".implode('|',array_keys(self::ACTLOG_DETAILS_TYPES))."] ".
        "[--debug ".implode('|',array_keys(self::DEBUG_TYPES))."] [--debug_http bool] [--debug_dblog bool] [--debug_filelog bool] ".
        "[--metrics ".implode('|',array_keys(self::METRICS_TYPES))."] [--metrics_dblog bool] [--metrics_filelog bool]"; }
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $fields[] = $this->datadir =            new FieldTypes\NullStringType('datadir');
        $fields[] = $this->read_only =          new FieldTypes\BoolType('read_only',false, false);
        $fields[] = $this->enabled =            new FieldTypes\BoolType('enabled',false, true);
        $fields[] = $this->email =              new FieldTypes\BoolType('email',false, true);
        $fields[] = $this->apps =               new FieldTypes\JsonArray('apps',false);
        
        $fields[] = $this->actionlog_db =      new FieldTypes\BoolType('actionlog_db',false, false);
        $fields[] = $this->actionlog_file  =   new FieldTypes\BoolType('actionlog_file',false, false);
        $fields[] = $this->actionlog_details = new FieldTypes\IntType ('actionlog_details',false, self::ACTLOG_DETAILS_BASIC);
        $fields[] = $this->debug =              new FieldTypes\IntType ('debug',false, self::ERRLOG_ERRORS);
        $fields[] = $this->debug_http =         new FieldTypes\BoolType('debug_http',false, false);
        $fields[] = $this->debug_dblog =        new FieldTypes\BoolType('debug_dblog',false, true);
        $fields[] = $this->debug_filelog =      new FieldTypes\BoolType('debug_filelog',false, false);
        $fields[] = $this->metrics =            new FieldTypes\IntType ('metrics',false, 0);
        $fields[] = $this->metrics_dblog =      new FieldTypes\BoolType('metrics_dblog',false, false);
        $fields[] = $this->metrics_filelog =    new FieldTypes\BoolType('metrics_filelog',false, false);
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /**
     * Updates config with the parameters in the given input (see CLI usage)
     * @throws Exceptions\UnwriteableDatadirException if given a new datadir that is invalid
     * @return $this
     */
    public function SetConfig(SafeParams $params) : self
    {
        if ($params->HasParam('read_only')) // do this first in case disabling
        {
            $ro = $params->GetParam('read_only')->GetBool();
            
            if (!$ro) $this->database->GetInternal()->SetReadOnly(false); // make DB writable
            
            $this->read_only->SetValue($ro);
            
            if ($ro) $this->read_only->SetValue(false,true); // not really RO yet 
        }
        
        if ($params->HasParam('datadir')) 
        {
            $datadir = $params->GetParam('datadir')->GetNullFSPath();
            if ($datadir !== null)
            {
                if (($realdir = realpath($datadir)) === false)
                    throw new Exceptions\UnwriteableDatadirException($datadir);
                if (!is_dir($realdir) || !is_readable($realdir) || !is_writeable($realdir))
                    throw new Exceptions\UnwriteableDatadirException($realdir);
                $datadir = $realdir;
            }
            
            $this->datadir->SetValue($datadir);
        }
        
        if ($params->HasParam('actionlog_db')) $this->actionlog_db->SetValue($params->GetParam('actionlog_db')->GetBool());
        if ($params->HasParam('actionlog_file')) $this->actionlog_file->SetValue($params->GetParam('actionlog_file')->GetBool());

        if ($params->HasParam('actionlog_details'))
        {
            $param = $params->GetParam('actionlog_details')->FromAllowlist(array_keys(self::ACTLOG_DETAILS_TYPES));
            $this->actionlog_details->SetValue(self::ACTLOG_DETAILS_TYPES[$param]);
        }
        
        if ($params->HasParam('debug'))
        {
            $param = $params->GetParam('debug')->FromAllowlist(array_keys(self::DEBUG_TYPES));
            $this->debug->SetValue(self::DEBUG_TYPES[$param]);
        }
        
        if ($params->HasParam('debug_http')) $this->debug_http->SetValue($params->GetParam('debug_http')->GetBool());
        if ($params->HasParam('debug_dblog')) $this->debug_dblog->SetValue($params->GetParam('debug_dblog')->GetBool());
        if ($params->HasParam('debug_filelog')) $this->debug_filelog->SetValue($params->GetParam('debug_filelog')->GetBool());

        if ($params->HasParam('metrics'))
        {
            $param = $params->GetParam('metrics')->FromAllowlist(array_keys(self::METRICS_TYPES));
            $this->metrics->SetValue(self::METRICS_TYPES[$param]);
        }
        
        if ($params->HasParam('metrics_dblog')) $this->metrics_dblog->SetValue($params->GetParam('metrics_dblog')->GetBool());
        if ($params->HasParam('metrics_filelog')) $this->metrics_filelog->SetValue($params->GetParam('metrics_filelog')->GetBool());
        
        if ($params->HasParam('enabled')) $this->enabled->SetValue($params->GetParam('enabled')->GetBool());
        if ($params->HasParam('email')) $this->email->SetValue($params->GetParam('email')->GetBool());
       
        return $this;
    }
    
    /**
     * returns the array of registered apps
     * @return list<string>
     */
    public function GetApps() : array { 
        return $this->apps->GetArray(); } // @phpstan-ignore-line assume array shape here, slow to check...
    
    /** 
     * List all app folders that exist in the filesystem
     * @return list<string>
     */
    public static function ScanApps() : array
    {
        $valid = function(string $app)
        {
            if ($app === "Files") return false; // TODO TEMP allow files app after fixing
            
            if (in_array($app,array('.','..'),true)) return false;
            return is_file(ROOT."/Apps/$app/$app"."App.php");
        };
        
        if (($dir = scandir(ROOT."/Apps")) === false)
            throw new Exceptions\FailedScanAppsException();
        $apps = array_values(array_filter($dir, $valid));

        return array_map(function(string $s){ return strtolower($s); }, $apps);
    }
    
    /** 
     * Registers the specified app name in config
     * @param bool $test if true, test load the app
     */
    public function EnableApp(string $app, bool $test = true) : self
    {
        $app = strtolower($app);
        
        if ($test)
        {
            $apprunner = $this->GetApiPackage()->GetAppRunner();
            $apprunner->LoadApp($app); // test loading
        }

        $capps = $this->GetApps();        
        if (!in_array($app, $capps, true)) $capps[] = $app;
        $this->apps->SetArray($capps); return $this;
    }
    
    /** Unregisters the specified app name */
    public function DisableApp(string $app) : self
    {
        $app = strtolower($app);
    
        $apprunner = $this->GetApiPackage()->GetAppRunner();
        $apprunner->UnloadApp($app);
        
        $capps = $this->GetApps();
        if (($key = array_search($app, $capps, true)) !== false) unset($capps[$key]);
        $this->apps->SetArray(array_values($capps)); return $this;
    }
    
    /** Returns whether the server is allowed to respond to requests */
    public function isEnabled() : bool { return $this->enabled->GetValue(); }
    
    /** Set whether the server is allowed to respond to requests */
    public function SetEnabled(bool $enable) : self { $this->enabled->SetValue($enable); return $this; }

    /** Returns true if the server is set to read-only (not dry run) */
    public function isReadOnly() : bool { return $this->read_only->GetValue(); }
    
    /** Returns the configured global data directory path */
    public function GetDataDir() : ?string 
    { 
        $dir = $this->datadir->TryGetValue(); 
        return ($dir !== null) ? "$dir/" : $dir; 
    }
    
    /** Returns true if request logging to DB is enabled */
    public function GetEnableActionLogDB() : bool { return $this->actionlog_db->GetValue(); }
    
    /** Returns true if request logging to data dir file is enabled */
    public function GetEnableActionLogFile() : bool { return $this->actionlog_file->GetValue(); }
    
    /** Returns true if request logging is enabled */
    public function GetEnableActionLog() : bool { return $this->GetEnableActionLogDB() || $this->GetEnableActionLogFile(); }
    
    /** log basic details params and object IDs */
    public const ACTLOG_DETAILS_BASIC = 1;
    
    /** log more detailed info, and full objects when deleted */
    public const ACTLOG_DETAILS_FULL = 2;
    
    public const ACTLOG_DETAILS_TYPES = array(
        'none'=>0, 
        'basic'=>self::ACTLOG_DETAILS_BASIC, 
        'full'=>self::ACTLOG_DETAILS_FULL);
    
    /** Returns the configured request log details detail level */
    public function GetActionLogDetails() : int { return $this->actionlog_details->GetValue(); }
    
    /** show a basic back trace */ 
    public const ERRLOG_ERRORS = 1; 
    
    /** show a full back trace, loaded objects, SQL queries */
    public const ERRLOG_DETAILS = 2;
    
    /** also show input params, function arguments, SQL values */ 
    public const ERRLOG_SENSITIVE = 3;
    
    public const DEBUG_TYPES = array(
        'none'=>0, 
        'errors'=>self::ERRLOG_ERRORS, 
        'details'=>self::ERRLOG_DETAILS, 
        'sensitive'=>self::ERRLOG_SENSITIVE);
    
    /**
     * Returns the current debug level
     * @param ?IOInterface $interface interface to check privilege level
     */
    public function GetDebugLevel(?IOInterface $interface = null) : int 
    {
        $debug = $this->debug->GetValue();
        
        if ($interface !== null && !$interface->isPrivileged()
            && !$this->debug_http->GetValue()) $debug = 0;
        
        return $debug;
    }
    
    /**
     * Sets the current debug level
     * @param bool $temp if true, only for this request
     */
    public function SetDebugLevel(int $data, bool $temp = true) : self { $this->debug->SetValue($data, $temp); return $this; }
    
    /** Gets whether the server should log errors to the database */
    public function GetDebugLog2DB()   : bool { return $this->debug_dblog->GetValue(); }
    
    /** Gets whether the server should log errors to a log file in the datadir */
    public function GetDebugLog2File() : bool { return $this->debug_filelog->GetValue(); } 

    /** Show basic performance metrics */
    public const METRICS_BASIC = 1;
    
    /** Show extended performance metrics */
    public const METRICS_EXTENDED = 2;
    
    public const METRICS_TYPES = array(
        'none'=>0, 
        'basic'=>self::METRICS_BASIC, 
        'extended'=>self::METRICS_EXTENDED);
    
    /** 
     * Returns the current metrics log level 
     * @param ?IOInterface $interface interface to check privilege level
     */
    public function GetMetricsLevel(?IOInterface $interface = null) : int 
    { 
        $metrics = $this->metrics->GetValue(); 
       
        if ($interface !== null && !$interface->isPrivileged()
            && !$this->debug_http->GetValue()) $metrics = 0;
           
        return $metrics;
    }
    
    /**
     * Sets the current metrics log level
     * @param bool $temp if true, only for this request
     */
    public function SetMetricsLevel(int $data, bool $temp = true) : self { $this->metrics->SetValue($data, $temp); return $this; }
    
    /** Gets whether the server should log metrics to the database */
    public function GetMetricsLog2DB()   : bool { return $this->metrics_dblog->GetValue(); }
    
    /** Gets whether the server should log errors to a log file in the datadir */
    public function GetMetricsLog2File() : bool { return $this->metrics_filelog->GetValue(); } 
    
    /** Gets whether using configured emailers is currently allowed */
    public function GetEnableEmail() : bool { return $this->email->GetValue(); }

    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return ConfigJ
     */
    public function GetClientObject(bool $admin = false) : array
    { 
        $data = array(
            'apiver' => (new VersionInfo(andromeda_version))->getCompatVer(),
            'enabled' => $this->enabled->GetValue(),
            'read_only' => $this->read_only->GetValue(false)
        );

        $apprunner = $this->GetApiPackage()->GetAppRunner();
        
        $data['apps'] = array(); 
        foreach ($apprunner->GetApps() as $name=>$app)
        {
            $data['apps'][$name] = $admin ? $app->getVersion() : 
                VersionInfo::toCompatVer($app->getVersion());
        }

        if ($admin)
        {
            $data['date_created'] =       $this->date_created->GetValue();
            $data['datadir'] =            $this->datadir->TryGetValue();
            $data['email'] =              $this->email->GetValue();
            $data['actionlog_file'] =     $this->actionlog_file->GetValue();
            $data['actionlog_db'] =       $this->actionlog_db->GetValue();
            $data['actionlog_details'] =  array_flip(self::ACTLOG_DETAILS_TYPES)[$this->actionlog_details->GetValue()];
            $data['metrics'] =            array_flip(self::METRICS_TYPES)[$this->metrics->GetValue(false)]; // no temp
            $data['metrics_dblog'] =      $this->metrics_dblog->GetValue();
            $data['metrics_filelog'] =    $this->metrics_filelog->GetValue();
            $data['debug'] =              array_flip(self::DEBUG_TYPES)[$this->debug->GetValue(false)]; // no temp
            $data['debug_http'] =         $this->debug_http->GetValue();
            $data['debug_dblog'] =        $this->debug_dblog->GetValue();
            $data['debug_filelog'] =      $this->debug_filelog->GetValue();

            foreach ($this->GetApps() as $app) 
                if (!array_key_exists($app, $data['apps']))
                    $data['apps'][$app] = null; // failed
        }

        return $data;
    }
}
