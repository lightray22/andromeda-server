<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required".PHP_EOL); use \PDO;

require_once(ROOT."/core/Config.php"); use Andromeda\Core\{Main, Config};
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, Transactions, JSONEncodingException};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

/** Base class representing a database exception */
abstract class DatabaseException extends Exceptions\ServerException { }

/** Exception indicating that the database connection is not configured */
class DatabaseConfigException extends DatabaseException { public $message = "DATABASE_CONFIG_MISSING"; }

/** Exception indicating that the database was requested to use an unknkown driver */
class InvalidDriverException extends DatabaseException { public $message = "PDO_UNKNOWN_DRIVER"; }

/** Exception indicating that PDO failed to execute the given query */
class DatabaseErrorException extends DatabaseException { public $message = "DATABASE_ERROR"; }

/** Exception indicating that the a write was requested to a read-only database */
class DatabaseReadOnlyException extends Exceptions\ClientErrorException { public $message = "READ_ONLY_DATABASE"; }

/** Exception indicating that database config failed */
class DatabaseConfigFailException extends Exceptions\ClientErrorException { }

/**
 * This class implements the PDO database abstraction.
 * 
 * Manages connecting to the database, installing config, abstracting some driver 
 * differences, and logging queries and performance statistics.  Queries are always
 * made as part of a transaction, and always used as prepared statements. Performance 
 * statistics and queries are tracked as a stack, but transactions cannot be nested.
 * Queries made must always be compatible with all supported drivers.
 */
class Database implements Transactions 
{
    /** the PDO database connection */
    private PDO $connection; 
    
    /** @var array<string, mixed> $config associative array of config for connecting PDO */
    protected array $config;
    
    /** The enum value of the driver being used */
    protected int $driver;
    
    /** if true, don't allow writes */
    private bool $read_only = false;
    
    /** @var DBStats[] the stack of DB statistics contexts, for nested API->Run() calls */
    private array $stats_stack = array();
    
    /** @var string[] global history of SQL queries sent to the DB (not a stack) */
    private array $queries = array();
    
    /** the default path for storing the config file */
    private const CONFIG_PATHS = array(
        ROOT."/Config.php",
        '/usr/local/etc/andromeda/Config.php',
        '/etc/andromeda/Config.php'
    );    
    
    public const DRIVER_MYSQL = 1; 
    public const DRIVER_SQLITE = 2; 
    public const DRIVER_POSTGRESQL = 3;
    
    private const DRIVERS = array(
        'mysql'=>self::DRIVER_MYSQL,
        'sqlite'=>self::DRIVER_SQLITE,
        'pgsql'=>self::DRIVER_POSTGRESQL);
    
    /** @see Database::$driver */
    public function getDriver() : int { return $this->driver; }
    
    /**
     * Constructs the database and initializes the PDO connection
     * @param string $config the path to the config file to use, generated by Install()
     * @throws DatabaseConfigException if the given path does not exist
     * @throws InvalidDriverException if the driver in the config is invalid
     */
    public function __construct(?string $config = null)
    {
        if ($config !== null)
        {
            if (!file_exists($config)) $config = null;
        }
        else foreach (self::CONFIG_PATHS as $path)
        {
            if (file_exists($path)) { $config = $path; break; }
        }
        
        if ($config !== null) $config = require($config);
        else throw new DatabaseConfigException();
        
        $this->config = $config;
        
        if (!array_key_exists($config['DRIVER'], self::DRIVERS))
            throw new InvalidDriverException();
        
        $this->driver = self::DRIVERS[$config['DRIVER']];
        
        $connect = $config['DRIVER'].':'.$config['CONNECT'];
        
        $this->connection = new PDO($connect, 
            $config['USERNAME'] ?? null, 
            $config['PASSWORD'] ?? null, 
            array(
                PDO::ATTR_PERSISTENT => $config['PERSISTENT'] ?? false, 
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
        ));
        
        if ($this->connection->inTransaction())
            $this->connection->rollback();
        
        if ($this->driver === self::DRIVER_SQLITE)
            $this->connection->query("PRAGMA foreign_keys = ON");
    }

    /** Returns a string with the primary CLI usage for Install() */
    public static function GetInstallUsage() : string { return "--driver mysql|pgsql|sqlite [--outfile fspath]"; }
    
    /** 
     * Returns the CLI usages specific to each driver 
     * @return array<string>
     */
    public static function GetInstallUsages() : array
    {
        return array(
            "--driver mysql --dbname alphanum (--unix_socket fspath | (--host hostname [--port int])) [--dbuser name] [--dbpass raw] [--persistent bool]",
            "--driver pgsql --dbname alphanum --host hostname [--port int] [--dbuser name] [--dbpass raw] [--persistent bool]",
            "--driver sqlite --dbpath fspath"
        );
    }
    
    /**
     * Creates and tests a new database config from the given user input
     * @param Input $input input parameters
     * @see Database::GetInstallUsage()
     * @throws \Throwable if the database config is not valid and PDO fails
     */
    public static function Install(Input $input) : void
    {
        $driver = $input->GetParam('driver',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL,
            function($arg){ return array_key_exists($arg, self::DRIVERS); });
        
        $params = array('DRIVER'=>$driver);
        
        if ($driver === 'mysql' || $driver === 'pgsql')
        {
            $connect = "dbname=".$input->GetParam('dbname',SafeParam::TYPE_ALPHANUM);
            
            if ($driver === 'mysql' && $input->HasParam('unix_socket'))
            {
                $connect .= ";unix_socket=".$input->GetParam('unix_socket',SafeParam::TYPE_FSPATH);
            }
            else 
            {
                $connect .= ";host=".$input->GetParam('host',SafeParam::TYPE_HOSTNAME);
                
                $port = $input->GetOptParam('port',SafeParam::TYPE_UINT);
                if ($port !== null) $connect .= ";port=$port";
            }
            
            if ($driver === 'mysql') $connect .= ";charset=utf8mb4";
            
            $params['CONNECT'] = $connect;
            
            $params['PERSISTENT'] = $input->GetOptParam('persistent',SafeParam::TYPE_BOOL);
            
            $params['USERNAME'] = $input->GetOptParam('dbuser',SafeParam::TYPE_NAME);
            $params['PASSWORD'] = $input->GetOptParam('dbpass',SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        }
        else if ($driver === 'sqlite')
        {
            $params['CONNECT'] = $input->GetParam('dbpath',SafeParam::TYPE_FSPATH);    
        }
        
        $params = var_export($params,true);
        
        $output = "<?php if (!defined('Andromeda')) die(); return $params;";
        
        $outnam = $input->GetOptParam('outfile',SafeParam::TYPE_FSPATH) ?? self::CONFIG_PATHS[0];
        
        $tmpnam = "$outnam.tmp.php";
        file_put_contents($tmpnam, $output);
        
        try { new Database($tmpnam); } catch (\Throwable $e) {
            unlink($tmpnam); throw DatabaseConfigFailException::Copy($e); }
        
        rename($tmpnam, $outnam);
    }
    
    /** 
     * returns the array of config that was loaded from the config file 
     * @return array<string, mixed> `{driver:string, connect:string, ?username:string, ?password:true, ?persistent:bool}`
     */
    public function GetClientObject() : array
    {
        $config = $this->config;
        
        if ($config['PASSWORD'] ?? null) $config['PASSWORD'] = true;
        
        return $config;
    }
    
    /**
     * returns an array with some PDO attributes for debugging 
     * @return array<string, mixed> `{driver:string, cversion:string, sversion:string, info:string}`
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
        $lines = array_filter(file($path),function($line){ return mb_substr($line,0,2) != "--"; });
        $queries = array_filter(explode(";",preg_replace( "/\r|\n/", "", implode($lines))));
        foreach ($queries as $query) $this->query(trim($query), 0); return $this;
    }
    
    /** Whether or not the DB supports the RETURNING keyword */
    protected function SupportsRETURNING() : bool { return in_array($this->getDriver(), array(self::DRIVER_MYSQL, self::DRIVER_POSTGRESQL)); }
    
    /** Whether or not the DB aborts transactions after an error and requires use of SAVEPOINTs */
    protected function RequiresSAVEPOINT() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB fetches binary/blob fields as streams rather than scalars */
    protected function BinaryAsStreams() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB requires binary input to be escaped */
    protected function BinaryEscapeInput() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB expects using public. as a prefix for table names */
    protected function UsePublicSchema() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
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
    
    const QUERY_READ = 1; const QUERY_WRITE = 2;

    /**
     * Sends an SQL query down to the database, possibly beginning a transaction
     * @param string $sql the SQL query string, with placeholder data values
     * @param int $type whether the query is a read, a write, or both (bitset)
     * @param array<string, string> $data associative array of data replacements for the prepared statement
     * @throws DatabaseReadOnlyException if the query is a write and the DB is read-only
     * @return mixed if the query is a read, an associative array of the result, else count of affected rows
     */
    public function query(string $sql, int $type, ?array $data = null) 
    {
        if (!$this->connection->inTransaction())
            $this->beginTransaction();
            
        $this->logQuery($sql, $data);
        
        if ($type & self::QUERY_WRITE && $this->read_only) 
            throw new DatabaseReadOnlyException();

        $this->startTimingQuery();
            
        $doSavepoint = false;
        
        try 
        {           
            if      ($sql === 'COMMIT') { $this->commit(); $result = null; }
            else if ($sql === 'ROLLBACK') { $this->rollback(); $result = null; }
            else
            {
                if ($this->RequiresSAVEPOINT())
                {
                    $doSavepoint = true;
                    $this->connection->query("SAVEPOINT mysave");
                }
                
                if ($this->BinaryEscapeInput() && $data !== null)
                {
                    foreach ($data as &$value)
                    {
                        if (!mb_check_encoding($value,'UTF-8'))
                        {
                            $value = pg_escape_bytea($value);
                        }
                    }
                }
                
                $query = $this->connection->prepare($sql);
                
                $query->execute($data ?? array());
                
                if ($type & self::QUERY_READ)
                {
                    $result = $query->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($this->BinaryAsStreams()) $this->fetchStreams($result);
                }                    
                else $result = $query->rowCount();
                
                if ($doSavepoint)
                    $this->connection->query("RELEASE SAVEPOINT mysave");
            }
        }
        catch (\PDOException $e)
        {
            if ($doSavepoint)
                $this->connection->query("ROLLBACK TO SAVEPOINT mysave");
                
            $idx = count($this->queries)-1;
            $this->queries[$idx] = array($this->queries[$idx], $e->getMessage());
            throw DatabaseErrorException::Copy($e); 
        }
        
        $this->stopTimingQuery($sql, $type);

        return $result;    
    }
    
    /** Logs a query to the internal query history, logging the actual data values if debug allows */
    private function logQuery(string $sql, ?array $data) : void
    {
        if ($data !== null && Main::GetInstance()->GetDebugLevel() >= Config::ERRLOG_SENSITIVE)
        {            
            foreach ($data as $key=>$val)
            {
                try { Utilities::JSONEncode(array($val)); }
                catch (JSONEncodingException $e) { $val = "(base64)".base64_encode($val); }
                
                if ($val !== null && is_string($val)) $val = str_replace('\\','\\\\',"'$val'");
                
                $sql = Utilities::replace_first(":$key", ($val===null)?'NULL':$val, $sql);
            }
        }
        
        $this->queries[] = $sql;
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
            $this->connection->beginTransaction();
            $this->stopTimingQuery($sql, Database::QUERY_READ, false);
        }
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
            $this->stopTimingQuery($sql, Database::QUERY_WRITE, false);
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
            $this->stopTimingQuery($sql, Database::QUERY_WRITE, false);
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
        return array_pop($this->stats_stack);
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

