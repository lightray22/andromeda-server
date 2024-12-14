<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\Database\Exceptions as DatabaseExceptions;
use Andromeda\Core\Errors\ErrorManager;
use Andromeda\Core\IOFormat\{IOInterface, Input, Output};

/** 
 * A special runner class for loading and running app installers
 * 
 * Creates and holds its own resources instead of a separate ApiPackage
 */
class InstallRunner extends BaseRunner
{
    private IOInterface $interface;
    private ErrorManager $errorman;
    
    private ?ObjectDatabase $database = null;
    /** The exception thrown when db loading failed */
    private ?DatabaseExceptions\DatabaseConnectException $dbexc = null;
    
    /** @var array<string, InstallerApp> */
    private array $installers = array();
    
    /** Returns the interface used for the current request */
    public function GetInterface() : IOInterface { return $this->interface; }
    
    /** Returns a reference to the global error manager */
    public function GetErrorManager() : ErrorManager { return $this->errorman; }
    
    /** Returns true if the database connection is available */
    public function HasDatabase() : bool { return $this->database !== null; }
    
    /** Returns true if the database did not give a DatabaseMissingException */
    public function HasDatabaseConfig() : bool{ 
        return !($this->dbexc instanceof DatabaseExceptions\DatabaseMissingException); }
    
    /** 
     * Returns the ObjectDatabase instance
     * @throws DatabaseExceptions\DatabaseConnectException if not available
     */
    public function RequireDatabase() : ObjectDatabase
    {
        if ($this->database === null) 
        {
            // ASSERT: dbexc XOR database are null
            assert($this->dbexc !== null); throw $this->dbexc; 
        }
        return $this->database;
    }

    /**
     * Returns the array of installer apps
     * @return array<string,InstallerApp>
     */
    public function GetInstallers() : array { return $this->installers; }
    
    /** 
     * Creates the AppRunner service, loading/constructing all installers 
     *
     * @param IOInterface $interface the interface that began the request
     * @param ErrorManager $errman error manager reference
     */
    public function __construct(IOInterface $interface, ErrorManager $errman)
    {
        parent::__construct();
        $this->interface = $interface;
        $this->errorman = $errman;
        
        try { $this->database = ApiPackage::InitDatabase($interface); }
        catch (DatabaseExceptions\DatabaseConnectException $e) { $this->dbexc = $e; }
        
        if ($this->database !== null) try
        {
            $this->errorman->SetDatabase($this->database);
            
            $config = Config::GetInstance($this->database);

            $interface->AdjustConfig($config);
            $this->errorman->SetConfig($config);
        }
        catch (Exceptions\InstallRequiredException | 
               Exceptions\UpgradeRequiredException $e) { /* leave un-init */ }
        
        foreach (Config::ScanApps() as $app)
        {
            $uapp = Utilities::FirstUpper($app);
            $path = ROOT."/Apps/$uapp/$uapp"."InstallApp.php";
            $class = "Andromeda\\Apps\\$uapp\\$uapp".'InstallApp';
            
            if (is_file($path)) require_once($path); else continue;
            if (class_exists($class) && is_a($class, InstallerApp::class, true))
            {
                $this->installers[$app] = new $class($this);
            }
        }
        
        $this->errorman->SetAppRunner($this);
    }
    
    /**
     * Calls into an installer to Run() the given Input command, saves all objects,
     * commits, finally and writes output to the interface
     * 
     * NOTE this function is NOT re-entrant - do NOT call it from apps!
     * @param Input $input the user input command to run
     * @throws Exceptions\UnknownAppException if the requested app is invalid
     */
    public function Run(Input $input) : void
    {
        $this->context = new RunContext($input);

        $app = $input->GetApp();
        if (!array_key_exists($app, $this->installers))
            throw new Exceptions\UnknownAppException();

        $installer = $this->installers[$app];
        $retval = $installer->Run($input);

        if ($this->database !== null)
        {
            $this->database->SaveObjects();
            $this->commit();
        }
        
        $output = Output::Success($retval);
        
        if ($this->interface->UserOutput($output))
            $this->commit();
            
        $this->interface->FinalOutput($output);
        $this->context = null;
    }
    
    /** Rolls back the current database transaction */
    public function rollback(?\Throwable $e = null) : void
    {
        Utilities::RunNoTimeout(function()
        {
            if ($this->database !== null)
                $this->database->rollback();
        });
    }
    
    /** Commits the current database transaction */
    protected function commit() : void
    {
        Utilities::RunNoTimeout(function()
        {
            if ($this->database !== null)
            {
                if ($this->interface->isDryRun())
                    $this->database->rollback();
                else $this->database->commit();
            }
        });
    }
}
