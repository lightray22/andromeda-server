<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

/**
 * This class keeps track of performance metrics for the database.
 * Such metrics include read/write query count, and read/write time.
 */
class DBStats
{
    private float $query_start;
    private float $start_timens;
    private float $total_timens = 0;
    
    private int $reads = 0; 
    private int $writes = 0; 
    private float $read_time = 0; 
    private float $write_time = 0; 
    private float $autoloader_time = 0;
    
    /** @var array<array{query:string,time:float}> */
    private array $queries = array();
    
    public const QUERY_READ = 1;
    public const QUERY_WRITE = 2;
    
    /** Constructs a new stats context and logs the current time */
    public function __construct() { $this->start_timens = hrtime(true); }
    
    /** 
     * Stops the overall timers
     * @param bool $autoloader if true, measure autoloader time
     */
    public function stopTiming(bool $autoloader = true) : void
    { 
        if ($autoloader) $this->autoloader_time = get_autoloader_time(); // global
        $this->total_timens = hrtime(true) - $this->start_timens; 
    }

    /** Begins tracking a query by logging the current time */
    public function startQuery() : void { $this->query_start = hrtime(true); }

    /**
     * Ends tracking a query and updates the relevant stats
     * @param string $sql the query sent to the DB for history
     * @param int $type whether the query was a read or write (or both - bitset)
     * @param bool $count if false, log only the time spent and don't increment the query counters
     */
    public function endQuery(string $sql, int $type, bool $count = true) : void
    { 
        $el = (hrtime(true)-$this->query_start)/1e9;
        
        $isRead = (bool)($type & self::QUERY_READ);
        $isWrite = (bool)($type & self::QUERY_WRITE);
        
        if ($isRead && $isWrite) $el /= 2;
        
        if ($isRead) { $this->read_time += $el; if ($count) $this->reads++; }
        if ($isWrite) { $this->write_time += $el; if ($count) $this->writes++; }
     
        $this->queries[] = array('query'=>$sql,'time'=>$el);
    }
    
    /** 
     * Returns the array of queries issued to the database 
     * @return array<array{query:string,time:float}> `[{query:string,time:float}]`
     */
    public function getQueries() : array   { return $this->queries; }

    /** Return the number of database reads */
    public function GetReads() : int       { return $this->reads; }
    
    /** Return the time spent reading from the database */
    public function GetReadTime() : float  { return $this->read_time; }
    
    /** Return the number of database writes */
    public function GetWrites() : int      { return $this->writes; }
    
    /** Return the time spent writing to the database */
    public function GetWriteTime() : float { return $this->write_time; }

    /** Return the time spent running the autoloader */
    public function GetAutoloaderTime() : float { return $this->autoloader_time; }
    
    /** Return the total elapsed time from construct to stopTiming() */
    public function GetTotalTime() : float { return $this->total_timens/1e9; }
    
    /** Return the total non-query (code) time (total-query) */
    public function GetCodeTime() : float
    {
        return $this->total_timens/1e9 - $this->read_time - $this->write_time;
    }
    
    /** 
     * Adds another DBStats' read/write/query stats to this one
     * @param bool $total if true, add total/code time also
     */
    public function Add(self $stats, bool $total) : void
    {
        $this->reads += $stats->reads;
        $this->read_time += $stats->read_time;
        $this->writes += $stats->writes;
        $this->write_time += $stats->write_time;
        $this->autoloader_time += $stats->autoloader_time;

        if ($total) $this->total_timens += $stats->total_timens;

        array_push($this->queries, ...$stats->queries);
    }
}
