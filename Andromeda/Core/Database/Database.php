<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) 
    die("PHP PDO Extension Required".PHP_EOL); 

if (!function_exists('mb_internal_encoding'))
    die("PHP mbstring Extension Required".PHP_EOL);

mb_internal_encoding("UTF-8");

use \PDO; use \PDOStatement; use \PDOException;

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Base class for database initialization exceptions */
abstract class DatabaseConfigException extends Exceptions\ServiceUnavailableException { }

/** Exception indicating that the database configuration is not found */
class DatabaseMissingException extends DatabaseConfigException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_CONFIG_MISSING", $details);
    }
}

/** Exception indicating that the database connection failed to initialize */
class DatabaseConnectException extends DatabaseConfigException
{
    public function __construct(?PDOException $e = null) {
        parent::__construct("DATABASE_CONNECT_FAILED"); 
        if ($e) $this->FromException($e, true);
    }
}

/** Exception indicating that the database was requested to use an unknkown driver */
class InvalidDriverException extends DatabaseConfigException
{
    public function __construct(?string $details = null) {
        parent::__construct("PDO_UNKNOWN_DRIVER", $details);
    }
}

/** Exception indicating that the a write was requested to a read-only database */
class DatabaseReadOnlyException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("READ_ONLY_DATABASE", $details);
    }
}

/** Exception indicating that database install config failed */
class DatabaseInstallException extends Exceptions\ClientErrorException
{
    public function __construct(DatabaseConfigException $e) {
        parent::__construct(""); $this->FromException($e);
    }
}

/** Exception indicating the file could not be imported because it's missing */
class ImportFileMissingException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("IMPORT_FILE_MISSING", $details);
    }
}

/** Base class representing a run-time database error */
abstract class DatabaseException extends Exceptions\ServerException
{
    public function __construct(string $message = "DATABASE_ERROR", ?string $details = null) {
        parent::__construct($message, $details);
    }
}

/** Exception indicating that PDO failed to execute the given query */
class DatabaseQueryException extends DatabaseException
{
    public function __construct(PDOException $e) {
        parent::__construct("DATABASE_QUERY_ERROR");
        $this->FromException($e, true);
    }
}

/** Exception indicating that fetching results from the query failed */
class DatabaseFetchException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_FETCH_FAILED", $details);
    }
}

/** Exception indicating the database had an integrity violation */
class DatabaseIntegrityException extends DatabaseException
{
    public function __construct(PDOException $e) {
        parent::__construct("DATABASE_INTEGRITY_VIOLATION");
        $this->FromException($e, true);
    }
}

require_once(ROOT."/Core/Database/DBStats.php");
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\{Main, Config};
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\{Utilities, JSONException};
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

/**
 * This class implements the PDO database abstraction.
 * 
 * Manages connecting to the database, installing config, abstracting some driver 
 * differences, and logging queries and performance statistics.  Queries are always
 * made as part of a transaction, and always used as prepared statements. Performance 
 * statistics and queries are tracked as a stack, but transactions cannot be nested.
 * Queries made must always be compatible with all supported drivers.
 */
class Database
{
    /** the PDO database connection */
    private PDO $connection; 
    
    /** a unique instance ID */
    private string $instanceId;
    
    /** @var array<string, mixed> $config associative array of config for connecting PDO */
    private array $config;
    
    /** The enum value of the driver being used */
    private int $driver;
    
    /** if true, don't allow writes */
    private bool $read_only = false;
    
    /** @var DBStats[] the stack of DB statistics contexts, for nested API->Run() calls */
    private array $stats_stack = array();
    
    /** global history of SQL queries sent to the DB (not a stack) */
    private array $queries = array();
    
    /** the default path for storing the config file */
    private const CONFIG_PATHS = array(
        ROOT."/DBConfig.php",
        null, // ~/.config/andromeda/DBConfig.php
        '/usr/local/etc/andromeda/DBConfig.php',
        '/etc/andromeda/DBConfig.php'
    );    
    
    public const DRIVER_MYSQL = 1; 
    public const DRIVER_SQLITE = 2; 
    public const DRIVER_POSTGRESQL = 3;
    
    private const DRIVERS = array(
        'mysql'=>self::DRIVER_MYSQL,
        'sqlite'=>self::DRIVER_SQLITE,
        'pgsql'=>self::DRIVER_POSTGRESQL);
    
    /**
     * Loads a config file path into an array of config generated by Install()
     * @param ?string $path the path to the config file to use, null for defaults
     * @throws DatabaseMissingException if the given path does not exist
     */
    public static function LoadConfig(?string $path = null) : array
    {
        $paths = defined('DBCONF') ? array(DBCONF) : self::CONFIG_PATHS;
        
        if ($path !== null)
        {
            if (!file_exists($path)) $path = null;
        }
        else foreach ($paths as $ipath)
        {
            if ($ipath === null)
            {
                $home = $_ENV["HOME"] ?? $_ENV["HOMEPATH"] ?? null;
                if ($home) $ipath = "$home/andromeda/DBConfig.php";
            }
            
            if (file_exists($ipath)) { $path = $ipath; break; }
        }
        
        if ($path !== null) return require($path);
        else throw new DatabaseMissingException();
    }

    /** Returns a string with the primary CLI usage for Install() */
    public static function GetInstallUsage() : string { return "--driver mysql|pgsql|sqlite [--outfile [fspath]]"; }
    
    /** 
     * Returns the CLI usages specific to each driver 
     * @return array<string>
     */
    public static function GetInstallUsages() : array
    {
        return array(
            "--driver mysql --dbname alphanum (--unix_socket fspath | (--host hostname [--port uint16])) [--dbuser name] [--dbpass raw] [--persistent bool]",
            "--driver pgsql --dbname alphanum --host hostname [--port ?uint16] [--dbuser ?name] [--dbpass ?raw] [--persistent ?bool]",
            "--driver sqlite --dbpath fspath"
        );
    }
    
    /**
     * Creates and tests a new database config from the given user input
     * @param SafeParams $params input parameters
     * @see Database::GetInstallUsage()
     * @throws \Throwable if the database config is not valid and PDO fails
     * @return string the database config file contents
     */
    public static function Install(SafeParams $params) : ?string
    {
        $driver = $params->GetParam('driver')->FromWhitelist(array_keys(self::DRIVERS));
        
        $config = array('DRIVER'=>$driver);
        
        if ($driver === 'mysql' || $driver === 'pgsql')
        {
            $connect = "dbname=".$params->GetParam('dbname')->GetAlphanum();
            
            if ($driver === 'mysql' && $params->HasParam('unix_socket'))
            {
                $connect .= ";unix_socket=".$params->GetParam('unix_socket')->GetFSPath();
            }
            else 
            {
                $connect .= ";host=".$params->GetParam('host')->GetHostname();
                
                if ($port = ($params->GetOptParam('port',null)->GetNullUint16()) !== null)
                {
                    $connect .= ";port=$port";
                }
            }
            
            if ($driver === 'mysql') $connect .= ";charset=utf8mb4";
            
            $config['CONNECT'] = $connect;
            
            $config['PERSISTENT'] = $params->GetOptParam('persistent',null)->GetNullBool();
            
            $config['USERNAME'] = $params->GetOptParam('dbuser',null)->GetNullName();
            $config['PASSWORD'] = $params->GetOptParam('dbpass',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString();
        }
        else if ($driver === 'sqlite')
        {
            $config['CONNECT'] = $params->GetParam('dbpath')->GetFSPath();    
        }
        
        $config = var_export($config,true);
        
        $output = "<?php if (!defined('Andromeda')) die(); return $config;";
        
        if ($params->HasParam('outfile'))
        {
            $outnam = $params->GetParam('outfile')->GetNullFSPath() ?? self::CONFIG_PATHS[0];

            $tmpnam = "$outnam.tmp.php";
            file_put_contents($tmpnam, $output);
            
            try { new self(self::LoadConfig($tmpnam)); }
            catch (DatabaseConfigException $e) 
            {
                unlink($tmpnam); 
                throw new DatabaseInstallException($e); 
            }
            
            rename($tmpnam, $outnam); return null;
        }
        else return $output;
    }
    
    /**
     * Constructs the database and initializes the PDO connection, and adds a stats context
     * @param array $config the associative array of config generated by Install()
     * @throws InvalidDriverException if the driver in the config is invalid
     */
    public function __construct(array $config)
    {
        $this->pushStatsContext();
        
        $this->config = $config;
        
        if (!array_key_exists($config['DRIVER'], self::DRIVERS))
            throw new InvalidDriverException();
        
        $this->driver = self::DRIVERS[$config['DRIVER']];
        
        $connect = $config['DRIVER'].':'.$config['CONNECT'];
        
        try
        {
            $options = array(
                PDO::ATTR_PERSISTENT => $config['PERSISTENT'] ?? false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false
            );
            
            // match rowCount behavior of postgres and sqlite
            if ($this->driver === self::DRIVER_MYSQL)
                $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
                
            $username = $config['USERNAME'] ?? null;
            $password = $config['PASSWORD'] ?? null;
            
            $this->connection = new PDO($connect, $username, $password, $options);
        }
        catch (PDOException $e)
        {
            if (Main::GetInstance()->GetInterface()->isPrivileged())
                throw new DatabaseConnectException($e);
            else throw new DatabaseConnectException();
        }
        
        if ($this->connection->inTransaction())
            $this->connection->rollback();
            
        if ($this->driver === self::DRIVER_SQLITE)
            $this->connection->query("PRAGMA foreign_keys = ON");
        
        $this->instanceId = "Database_".Utilities::Random(4);
    }
    
    /** @see Database::$driver */
    public function getDriver() : int { return $this->driver; }
    
    /** Returns the DB's unique instance ID */
    public function getInstanceID() : string { return $this->instanceId; }
    
    /** 
     * returns the array of config that was loaded from the config file 
     * @return array<string, mixed> `{driver:string, connect:string, ?username:string, ?password:true, ?persistent:bool}`
     */
    public function GetConfig() : array
    {
        $config = $this->config;
        
        if ($config['PASSWORD'] ?? null) 
            $config['PASSWORD'] = true;
        
        return $config;
    }
    
    /**
     * returns an array with some PDO attributes for debugging 
     * @return array `{driver:string, cversion:string, sversion:string, info:string}`
     */
    public function getInfo() : array
    {
        return array(
            'driver' => $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME),
            'cversion' => $this->connection->getAttribute(PDO::ATTR_CLIENT_VERSION),
            'sversion' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'info' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO)
        );
    }
    
    /**
     * Sets the database as writeable or readonly
     * @param bool $ro if true, set as readonly
     * @return $this
     */
    public function setReadOnly(bool $ro = true) : self { $this->read_only = $ro; return $this; }
    
    /** Returns true if the database is read-only */
    public function isReadOnly() : bool { return $this->read_only; }
    
    /**
     * Imports the appropriate SQL template file for an app
     * @param string $path the base path containing the templates
     */
    public function importTemplate(string $path) : self { return $this->importFile("$path/andromeda.".$this->config['DRIVER'].".sql"); }
    
    /**
     * Parses and imports an SQL file into the database
     * @param string $path the path of the SQL file
     */
    public function importFile(string $path) : self
    {
        if (!($data = file($path))) throw new ImportFileMissingException($path);
        
        $lines = array_filter($data,function(string $line){ return mb_substr($line,0,2) != "--"; });
        
        $queries = array_filter(explode(";", preg_replace("/\r|\n/", "", implode($lines))));
        
        foreach ($queries as $query) $this->query(trim($query)); return $this;
    }
    
    /** Whether or not the DB supports the RETURNING keyword */
    public function SupportsRETURNING() : bool { return $this->getDriver() !== self::DRIVER_SQLITE; }
    
    /** Whether or not the DB aborts transactions after an error and requires use of SAVEPOINTs */
    private function RequiresSAVEPOINT() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB fetches binary/blob fields as streams rather than scalars */
    private function BinaryAsStreams() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB requires binary input to be escaped */
    private function BinaryEscapeInput() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB expects using public. as a prefix for table names */
    public function UsePublicSchema() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the returned data rows are always string values (false if the are proper types) */
    public function DataAlwaysStrings() : bool { return $this->getDriver() !== self::DRIVER_MYSQL; }
    
    /** Returns the given arguments concatenated in SQL */
    public function SQLConcat(string ...$args) : string
    {
        if ($this->getDriver() === self::DRIVER_MYSQL)
        {
            return "CONCAT(".implode(',',$args).")";
        }
        else return implode(' || ',$args);
    }

    /** Returns true if the DB is currently in a transaction */
    public function inTransaction() : bool { return $this->connection->inTransaction(); }

    /**
     * Sends an SQL read query down to the database
     * @param string $sql the SQL query string, with placeholder data values
     * @param ?array<string, ?scalar> $data associative array of data replacements for the prepared statement
     * @return array an associative array of the query results
     * @throws DatabaseFetchException if the row fetch fails
     * @see Database::query()
     */
    public function read(string $sql, ?array $data = null) : array
    {
        $this->startTimingQuery();
        
        $query = $this->query($sql, $data);

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        if ($result === false) throw new DatabaseFetchException();

        if ($this->BinaryAsStreams()) $this->fetchStreams($result);
        
        $this->stopTimingQuery($sql, DBStats::QUERY_READ);
        
        return $result;
    }
    
    /**
     * Sends an SQL write query down to the database
     * @param string $sql the SQL query string, with placeholder data values
     * @param ?array<string, ?scalar> $data associative array of data replacements for the prepared statement
     * @return int count of matched objects (not count of modified!)
     * @throws DatabaseReadOnlyException if the DB is read-only
     * @see Database::query()
     */
    public function write(string $sql, ?array $data = null) : int
    {        
        if ($this->read_only) throw new DatabaseReadOnlyException();
        
        $this->startTimingQuery();
        
        $query = $this->query($sql, $data);
        
        $result = $query->rowCount();
        
        $this->stopTimingQuery($sql, DBStats::QUERY_WRITE);
        
        return $result;
    }
    
    /**
     * Sends an SQL read/write query down to the database
     * @param string $sql the SQL query string, with placeholder data values
     * @param ?array<string, ?scalar> $data associative array of data replacements for the prepared statement
     * @return array an associative array of the query results
     * @throws DatabaseReadOnlyException if the DB is read-only
     * @throws DatabaseFetchException if the row fetch fails
     * @see Database::query()
     */
    public function readwrite(string $sql, ?array $data = null) : array
    {
        if ($this->read_only) throw new DatabaseReadOnlyException();
        
        $this->startTimingQuery();
        
        $query = $this->query($sql, $data);
        
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        if ($result === false) throw new DatabaseFetchException();
        
        if ($this->BinaryAsStreams()) $this->fetchStreams($result);
        
        $this->stopTimingQuery($sql, DBStats::QUERY_READ | DBStats::QUERY_WRITE);
        
        return $result;
    }

    /**
     * Sends an SQL query down to the database, possibly beginning a transaction
     * @param string $sql the SQL query string, with placeholder data values
     * @param array<string, string> $data associative array of data replacements for the prepared statement
     * @throws DatabaseQueryException if the database query throws a PDOException
     * @return PDOStatement the finished PDO statement object
     */
    protected function query(string $sql, ?array $data = null) : PDOStatement
    {
        if (!$this->connection->inTransaction())
            $this->beginTransaction();
            
        $this->logQuery($sql, $data);
        
        $doSavepoint = $this->RequiresSAVEPOINT();

        if ($this->BinaryEscapeInput() && $data !== null)
        {
            foreach ($data as &$value)
            {
                if (is_string($value) && !Utilities::isUTF8($value))
                    $value = pg_escape_bytea($value);
            }
        }
        
        try
        {
            if ($doSavepoint)
                $this->connection->query("SAVEPOINT a2save");
            
            $query = $this->connection->prepare($sql);
            $query->execute($data ?? array());

            if ($doSavepoint)
                $this->connection->query("RELEASE SAVEPOINT a2save");
            
            return $query;
        }
        catch (PDOException $e)
        {
            if ($doSavepoint)
                $this->connection->query("ROLLBACK TO SAVEPOINT a2save");
                
            $idx = count($this->queries)-1;
            $this->queries[$idx] = array($this->queries[$idx], $e->getMessage());

            $eclass = substr($e->getCode(),0,2);
            
            if ($eclass === '23') 
                throw new DatabaseIntegrityException($e);
            else throw new DatabaseQueryException($e); 
        }
    }

    /** Logs a query to the internal query history, logging the actual data values if debug allows */
    private function logQuery(string $sql, ?array $data) : string
    {
        if ($data !== null && Main::GetInstance()->GetDebugLevel() >= Config::ERRLOG_SENSITIVE)
        {            
            foreach ($data as $key=>$val)
            {
                try { Utilities::JSONEncode(array($val)); }
                catch (JSONException $e) { 
                    $val = 'b64:'.base64_encode($val); }
                
                if ($val !== null && is_string($val)) 
                    $val = str_replace('\\','\\\\',"'$val'");
                
                $sql = Utilities::replace_first(":$key", ($val===null)?'NULL':(string)$val, $sql);
            }
        }
        
        return $this->queries[] = $sql;
    }
    
    /**
     * Loops through an array of row results and replaces streams with their values
     * @param array $rows reference to an array of rows from the DB
     */
    private function fetchStreams(array &$rows) : void
    {
        foreach ($rows as &$row)
        {
            foreach ($row as &$value)
            {
                if (is_resource($value))
                    $value = stream_get_contents($value);
            }
        }
    }
    
    /** Begins a new database transaction */
    public function beginTransaction() : void
    {
        if (!$this->connection->inTransaction())
        {
            $sql = "PDO->beginTransaction()";
            $this->queries[] = $sql;
            $this->startTimingQuery();
            
            if ($this->driver === self::DRIVER_MYSQL)
                $this->configTransaction();

            $this->connection->beginTransaction();
            
            if ($this->driver === self::DRIVER_POSTGRESQL)
                $this->configTransaction();
                
            $this->stopTimingQuery($sql, DBStats::QUERY_READ, false);
        }
    }
    
    /** Sends a query to configure the isolation level and access mode */
    private function configTransaction() : void
    {
        $qstr = "SET TRANSACTION ISOLATION LEVEL READ COMMITTED";
        
        if ($this->read_only) $qstr .= " READ ONLY";
        
        $this->connection->query($qstr);
    }

    /** Rolls back the current database transaction */
    public function rollback() : void
    { 
        if ($this->connection->inTransaction())
        {
            $sql = "PDO->rollback()";
            $this->queries[] = $sql;
            $this->startTimingQuery();            
            $this->connection->rollback();
            $this->stopTimingQuery($sql, DBStats::QUERY_WRITE, false);
        }
    }
    
    /** Commits the current database transaction */
    public function commit() : void
    {
        if ($this->connection->inTransaction()) 
        {
            $sql = "PDO->commit()";
            $this->queries[] = $sql;
            $this->startTimingQuery();
            $this->connection->commit();             
            $this->stopTimingQuery($sql, DBStats::QUERY_WRITE, false);
        }
    }
    
    /** Begins timing a query (performance metrics) */
    private function startTimingQuery() : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->startQuery();
    }
    
    /** Ends timing a query (performance metrics) */
    private function stopTimingQuery(string $sql, int $type, bool $count = true) : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->endQuery($sql, $type, $count);
    }
    
    /** Add a new performance metrics context on to the stack */
    public function pushStatsContext() : self
    {
        $this->stats_stack[] = new DBStats(); return $this;
    }

    /** Pop the current performance metrics context off of the stack */
    public function popStatsContext() : ?DBStats
    {
        $obj = array_pop($this->stats_stack);
        if ($obj !== null) $obj->stopTiming();
        return $obj;
    }
    
    /** 
     * Returns the array of query history 
     * @return string[] string array
     */
    public function getAllQueries() : array
    {
        return $this->queries;
    }
}

