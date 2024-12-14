<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\Config;

/** Describes an abstract PHP I/O interface abstraction */
abstract class IOInterface
{
    /** Constructs and returns a singleton of the appropriate interface type */
    final public static function TryGet() : ?self
    {
        if (Interfaces\HTTP::isApplicable()) return new Interfaces\HTTP();
        else if (Interfaces\CLI::isApplicable()) return new Interfaces\CLI();
        else return null;
    }

    /** Returns whether or not the interface is in use for this request */
    abstract public static function isApplicable() : bool;
    
    /** Returns whether or not the interface grants privileged access */
    abstract public static function isPrivileged() : bool;

    /** Returns true if the interface is considered "interactive" */
    abstract public static function isInteractive() : bool;

    /** The parsed input object */
    protected ?Input $input = null;

    /** 
     * Retrieves the input object to run (if not cached)
     * 
     * Also modifies internal state related to the input (debug, dryrun, etc.)
     * which can be transferred to config via AdjustConfig()
     * @return Input
     */
    public function GetInput() : Input
    {
        if ($this->input === null)
            $this->input = $this->LoadInput(); // cache
        return $this->input;
    }
    
    /** Sets temporary config parameters requested by the interface */
    public function AdjustConfig(Config $config) : self { return $this; }
    
    /** Retrieves the input object to run (subclasses implement) */
    abstract protected function LoadInput() : Input;
    
    /** Returns the address of the user on the interface */
    abstract public function getAddress() : string;    
    
    /** Returns the user agent string on the interface */
    abstract public function getUserAgent() : string;
    
    /** Returns true if the interface requested a dry-run */
    public function isDryRun() : bool { return false; }
    
    /** Returns the debugging level requested by the interface */
    public function GetDebugLevel() : int { return 0; }
    
    /** Returns the perf metrics level requested by the interface */
    public function GetMetricsLevel() : int { return 0; }
    
    /** Returns the path to the DB config file requested by the interface */
    public function GetDBConfigFile() : ?string { return null; }
    
    public const OUTPUT_TYPES = array(
        'none'=>0, 
        'plain'=>self::OUTPUT_PLAIN, 
        'json'=>self::OUTPUT_JSON, 
        'printr'=>self::OUTPUT_PRINTR);
    
    public const OUTPUT_PLAIN = 1; 
    public const OUTPUT_JSON = 2; 
    public const OUTPUT_PRINTR = 3;

    /** Gets the default output mode for the interface */
    abstract public static function GetDefaultOutmode() : int;
    
    protected int $outmode;
    
    public function __construct(){ $this->outmode = static::GetDefaultOutmode(); }
    
    /** Returns the output mode currently selected */
    public function GetOutputMode() : int { return $this->outmode; }

    /** 
     * Sets the output mode to the given mode or 0 for (none)
     * @throws Exceptions\MultiOutputException if mode is not 0 and a user output function with non-null bytes is set
     */
    public function SetOutputMode(int $mode) : self 
    {
        if ($mode !== 0 && $this->userfunc !== null && $this->userfunc->GetBytes() !== null)
            throw new Exceptions\MultiOutputException($mode);

        $this->outmode = $mode; return $this; 
    }

    /** The custom user output function if set */
    private ?OutputHandler $userfunc = null;
    
    /** 
     * Sets a user output handler function to run after the initial commit 
     * NOTE Sets the output mode to null if bytes !== null
     * @throws Exceptions\MultiOutputException if set more than once
     */
    public function SetOutputHandler(?OutputHandler $f = null) : self 
    {
        if ($f !== null && $this->userfunc !== null)
            throw new Exceptions\MultiOutputException();

        if ($f !== null && $f->GetBytes() !== null)
            $this->outmode = 0;
        
        $this->userfunc = $f; return $this; 
    }

    /** 
     * Tells the interface to run the custom user output functions
     * @return bool true if a custom function was run (regardless of output bytes)
     */
    public function UserOutput(Output $output) : bool
    {
        if ($this->userfunc !== null)
        {
            $this->userfunc->DoOutput($output);
            flush(); // flush output
        }

        return ($this->userfunc !== null);
    }
    
    /** Tells the interface to print its final output and exit */
    public abstract function FinalOutput(Output $output) : void;
}
