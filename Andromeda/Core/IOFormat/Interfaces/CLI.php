<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) die();

use Andromeda\Core\{Config, Utilities};
use Andromeda\Core\IOFormat\{Input,InputPath,InputStream,Output,IOInterface,SafeParam,SafeParams};
use Andromeda\Core\IOFormat\Interfaces\Exceptions\IncorrectCLIUsageException;

/** The interface for using Andromeda via local console */
class CLI extends IOInterface
{
    public static function isApplicable() : bool
    {
        global $argv; return php_sapi_name() === "cli" && isset($argv);
    }
    
    /** @return true */
    public static function isPrivileged() : bool { return true; }

    /** @return true */
    public static function isInteractive() : bool { return true; }
    
    /** @return int plain text output by default */
    public static function GetDefaultOutmode() : int { return self::OUTPUT_PLAIN; }
    
    public function getAddress() : string
    {
        $retval = "CLI";
        
        if (array_key_exists("COMPUTERNAME",$_SERVER)) 
            $retval .= " ".$_SERVER["COMPUTERNAME"];

        if (array_key_exists("USERNAME",$_SERVER))
            $retval .= " ".$_SERVER["USERNAME"];
        else if (array_key_exists("USER",$_SERVER))
            $retval .= " ".$_SERVER["USER"];
        
        if (is_string($_SERVER["SSH_CLIENT"] ?? null))
            $retval .= " ".explode(' ',$_SERVER["SSH_CLIENT"])[0];

        return $retval;
    }
    
    public function getUserAgent() : string
    {
        $retval = "CLI";
        
        if (array_key_exists("OS",$_SERVER))
            $retval .= " ".$_SERVER["OS"];
        
        if (array_key_exists("SHELL",$_SERVER))
            $retval .= " ".$_SERVER["SHELL"];

        return $retval;
    }
    
    private ?string $outprop = null;
    
    private bool $dryRun = false;
    public function isDryRun() : bool { return $this->dryRun; }
    
    private ?int $debug = null;
    public function GetDebugLevel() : int { return $this->debug ?? Config::ERRLOG_ERRORS; }
    
    private ?int $metrics = null;
    public function GetMetricsLevel() : int { return $this->metrics ?? 0; }
    
    private ?string $dbconf = null;
    public function GetDBConfigFile() : ?string { return $this->dbconf; }
    
    public function AdjustConfig(Config $config) : self
    {
        if ($this->debug !== null && in_array($this->debug, Config::DEBUG_TYPES, true))
            $config->SetDebugLevel($this->debug, true);
        
        if ($this->metrics !== null && in_array($this->metrics, Config::METRICS_TYPES, true))
            $config->SetMetricsLevel($this->metrics, true);
        
        return $this;
    }

    protected function LoadInput() : Input
    {
        global $argv; return $this->LoadFullInput($argv, $_SERVER, STDIN); // @phpstan-ignore-line types missing
    }
    
    /** Strips -- off the given string and returns (or null if not found) */
    private static function getKey(string $str) : ?string
    {
        if (mb_substr($str,0,2) !== "--") return null; 
        else return explode("=",mb_substr($str,2),2)[0];
    }
    
    /** 
     * Returns the next args value (or null if not found) 
     * at or after $i (MUST EXIST) and increments if the value was at i+1
     * @param list<string> $args
     */
    private static function getNextValue(array $args, int &$i) : ?string
    {
        if (self::getKey($args[$i]) !== null &&
            mb_strpos($args[$i],"=") !== false) // --key=val
        {
            return explode('=',$args[$i],2)[1];
        }
        else if (isset($args[$i+1]) && self::getKey($args[$i+1]) === null) // --key val
        {
            return $args[++$i];
        }
        return null; // no value
    }

    /**
     * Retrieves the input object to run
     * @param list<string> $argv
     * @param array<string, scalar> $server
     * @param resource $stdin
     */
    public function LoadFullInput(array $argv, array $server, $stdin) : Input
    {
        $argIdx = 1;
        
        // global params for outmode, debug, config file, etc. come first
        for (; $argIdx < count($argv); $argIdx++)
        {
            $key = self::getKey($argv[$argIdx]);
            
            if ($key === null) break; else switch ($key)
            {
                case 'dryrun': $this->dryRun = true; break;
                
                case 'outprop':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no outprop value');
                    $outprop = (new SafeParam('outprop',$val))->GetAlphanum();
                    $this->outprop = $outprop;
                    break;
                }
                case 'outmode':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no outmode value');
                    $outmode = (new SafeParam('outmode',$val))->FromAllowlist(array_keys(self::OUTPUT_TYPES));
                    $this->outmode = self::OUTPUT_TYPES[$outmode];
                    break;
                }
                case 'debug':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no debug value');
                    $debug = (new SafeParam('debug',$val))->FromAllowlist(array_keys(Config::DEBUG_TYPES));
                    $this->debug = Config::DEBUG_TYPES[$debug];
                    break;
                }
                case 'metrics':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no metrics value');
                    $metrics = (new SafeParam('metrics',$val))->FromAllowlist(array_keys(Config::METRICS_TYPES));
                    $this->metrics = Config::METRICS_TYPES[$metrics];
                    break;
                }
                case 'dbconf':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no dbconf path');
                    $this->dbconf = (new SafeParam('dbconf',$val))->GetFSPath();
                    break;
                }
                default: throw new IncorrectCLIUsageException('invalid global arg');
            }
        }
        
        assert(is_int($argIdx));
        // now process the actual app/action command(s)
        for (; $argIdx < count($argv); $argIdx++)
        {
            switch ($argv[$argIdx])
            {
                case 'version': die("Andromeda ".andromeda_version.PHP_EOL);
                default: return self::LoadAppInput(array_slice($argv,$argIdx), $server,$stdin);
            }
        }

        throw new IncorrectCLIUsageException('missing app/action');
    }

    /** 
     * Fetches an Input object by reading it from the command line 
     * @param list<string> $argv
     * @param array<string, scalar> $server
     * @param resource $stdin
     */
    private function LoadAppInput(array $argv, array $server, $stdin) : Input
    {
        $app = array_shift($argv);
        $action = array_shift($argv);
        if ($app === null || $action === null)
            throw new IncorrectCLIUsageException('missing app/action');

        // add environment variables to argv
        foreach ($server as $key=>$value)
        {
            if (mb_strpos($key,"andromeda_") === 0)
            {
                $key = explode('_',$key,2);
                if ($value === false) $value = "false";
                // unshift to put these at the front so actual CLI values override
                array_unshift($argv, "--".$key[1], (string)$value);
            }
        };
        
        $params = new SafeParams(); $files = array();
 
        for ($i = 0; $i < count($argv); $i++)
        {
            $param = self::getKey($argv[$i]); 
            if ($param === null) throw new IncorrectCLIUsageException(
                "expected key at action arg $i");
            else if ($param === "") throw new IncorrectCLIUsageException(
                "empty key at action arg $i");
            
            $special = mb_substr($param, -1);

            // optionally load a param value from a file instead
            if ($special === '@')
            {
                $param = mb_substr($param,0,-1); 
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty @ key at action arg $i");
                
                $val = self::getNextValue($argv,$i); assert(is_int($i));
                if ($val === null) throw new IncorrectCLIUsageException(
                    "expected @ value at action arg $i");
                
                if (!is_file($val) || ($fdat = file_get_contents($val)) === false) 
                    throw new Exceptions\InvalidFileException($val);
                
                $params->AddParam($param, $fdat);
            }
            // optionally get a param value interactively
            else if ($special === '!')
            {
                $param = mb_substr($param,0,-1);
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty ! key at action arg $i");
                
                echo "enter $param...".PHP_EOL; $inp = fgets($stdin);
                $val = ($inp === false) ? null : trim($inp, PHP_EOL);
                
                $params->AddParam($param, $val);
            }
            // optionally send the app a path/name of a file instead
            else if ($special === '%')
            {
                $param = mb_substr($param,0,-1);
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty % key at action arg $i");
                
                $path = self::getNextValue($argv,$i); assert(is_int($i));
                if ($path === null) throw new IncorrectCLIUsageException(
                    "expected % value at action arg $i");
                
                if (!is_file($path)) throw new Exceptions\InvalidFileException($path);
                
                $filename = self::getNextValue($argv,$i) ?? basename($path);
                $filename = (new SafeParam('name',$filename))->GetFSName();
                
                $files[$param] = new InputPath($path, $filename, false);
            }
            // optionally attach stdin to a file instead
            else if ($special === '-')
            {
                $param = mb_substr($param,0,-1);
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty - key at action arg $i");
                
                $filename = self::getNextValue($argv,$i) ?? "data";
                $filename = (new SafeParam('name',$filename))->GetFSName();
            
                $files[$param] = new InputStream($stdin, $filename);
            }
            else // plain argument
            {
                $val = self::getNextValue($argv,$i);
                $params->AddParam($param, $val);
            }
        }
        
        return new Input($app, $action, $params, $files);
    }
    
    /** @param bool $exit if true, exit() with the proper code and allow using stderr */
    public function FinalOutput(Output $output, bool $exit = true) : void
    {
        if ($this->outmode === 0 && $exit)
        {
            // even in NONE output, write errors to stderr
            if (!$output->isOK()) file_put_contents("php://stderr",
                $output->GetAsString().PHP_EOL);
        }
        else if ($this->outmode === self::OUTPUT_PLAIN)
        {
            echo $output->GetAsString($this->outprop).PHP_EOL;
        }
        else if ($this->outmode === self::OUTPUT_PRINTR)
        {
            $outdata = $output->GetAsArray($this->outprop);
            echo print_r($outdata, true).PHP_EOL;
        }
        else if ($this->outmode === self::OUTPUT_JSON)
        {
            // apps MUST ensure they don't return anything non-utf8
            echo Utilities::JSONEncode(
                $output->GetAsArray($this->outprop)).PHP_EOL;
        }
        
        if ($exit) exit($output->isOK() ? 0 : 1); 
    }
}
