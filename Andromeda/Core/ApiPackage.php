<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{DBStats, PDODatabase, ObjectDatabase};
use Andromeda\Core\Database\Exceptions\DatabaseConnectException;
use Andromeda\Core\Errors\ErrorManager;
use Andromeda\Core\IOFormat\IOInterface;
use Andromeda\Core\Logging\MetricsHandler;

/** The main container class creating and managing API resources */
class ApiPackage
{
    private ObjectDatabase $database;
    private Config $config;
    
    private AppRunner $apprunner;
    private ErrorManager $errorman;
    private IOInterface $interface;
    private MetricsHandler $metrics;

    /** 
     * Instantiates and returns a new ObjectDatabase connection 
     * @param IOInterface $interface interface to get config from
     * @param ?DBStats $init_stats stats to pass to DB for init
     * @throws DatabaseConnectException if the connection fails
     */
    public static function InitDatabase(IOInterface $interface, ?DBStats $init_stats = null) : ObjectDatabase
    {
        $dbconf = PDODatabase::LoadConfig($interface->GetDBConfigFile());
        
        return new ObjectDatabase(new PDODatabase($dbconf, $interface->isPrivileged(), $init_stats));
    }
    
    /** Returns the global ObjectDatabase instance */
    public function GetDatabase() : ObjectDatabase { return $this->database; }

    /** Returns the global config object */
    public function GetConfig() : Config { return $this->config; }

    /** Returns the apprunner interface - WARNING not set until apprunner is created! */
    public function GetAppRunner() : AppRunner { return $this->apprunner; }

    /** Sets the apprunner interface to use */
    public function SetAppRunner(AppRunner $ar) : void 
    { 
        $this->apprunner = $ar; 
        $this->errorman->SetAppRunner($ar);
    }

    /** Returns the interface used for the current request */
    public function GetInterface() : IOInterface { return $this->interface; }
    
    /** Returns a reference to the global error manager */
    public function GetErrorManager() : ErrorManager { return $this->errorman; }
    
    /** Returns the global performance metrics handler */
    public function GetMetricsHandler() : MetricsHandler { return $this->metrics; }

    /**
     * Creates a new API package with the given resources
     *
     * @param IOInterface $interface the interface that began the request
     * @param ErrorManager $errman error manager reference
     * @throws DatabaseConnectException if the connection fails
     * @throws Exceptions\InstallRequiredException if the Config is not available
     * @throws Exceptions\UpgradeRequiredException if the Config version is wrong
     * @throws Exceptions\MaintenanceException if the server is not enabled
     */
    public function __construct(IOInterface $interface, ErrorManager $errman)
    {
        $this->interface = $interface;
        $this->errorman = $errman;
        
        $this->metrics = new MetricsHandler();
        $init_stats = $this->metrics->GetInitStats();

        $this->database = static::InitDatabase($interface, $init_stats);
        $this->database->SetApiPackage($this);
        $this->errorman->SetDatabase($this->database);

        $this->config = Config::GetInstance($this->database);
            
        $interface->AdjustConfig($this->config);
        $this->errorman->SetConfig($this->config);

        $enabled = $this->config->isEnabled() || $interface->isPrivileged();
        if (!$enabled) throw new Exceptions\MaintenanceException();
        
        $pdoDatabase = $this->database->GetInternal();
        
        if ($this->config->isReadOnly())
            $pdoDatabase->SetReadOnly();
            
        $pdoDatabase->SetLogValues(
            $this->GetDebugLevel() >= Config::ERRLOG_SENSITIVE);
        
        $pdoDatabase->stopStatsContext(); // end init_stats
    }

    /**
     * Returns the configured debug level, accounting for the interface
     * @param bool $output if true, adjust level to interface privilege
     */
    public function GetDebugLevel(bool $output = false) : int
    {
        return $this->config->GetDebugLevel($output ? $this->interface : null);
    }
    
    /**
     * Returns the configured performance metrics level
     * @param bool $output if true, adjust level to interface privilege
     */
    public function GetMetricsLevel(bool $output = false) : int
    {
        return $this->config->GetMetricsLevel($output ? $this->interface : null);
    }
    
    /** Return true if we will rollback on commit (dryrun or read-only) */
    public function isCommitRollback() : bool
    {
        return $this->database->isReadOnly() || $this->interface->isDryRun();
    }

    /** Returns true if an action log should be created */
    public function isActionLogEnabled() : bool
    {
        return !$this->config->isReadOnly() && $this->config->GetEnableActionLog();
    }
}
