<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\{Config, Utilities};

/**
 * A thin class that manages a collection of SafeParam objects
 * 
 * This class exists rather than having $params directly in Input
 * because a param can itself contain a collection of other params
 * (see SafeParam::TYPE_OBJECT) represented by another SafeParams
 */
class SafeParams
{
    /** 
     * Loads params from an array of input values
     * @param array<scalar, NULL|scalar|array<scalar, NULL|scalar|array<scalar, mixed>>> $arr 
     */
    public function LoadArray(array $arr) : self
    {
        foreach ($arr as $key=>$val)
        {
            if (is_array($val) && !Utilities::is_plain_array($val))
            {
                $obj = new self();
                $val = $obj->LoadArray($val); // @phpstan-ignore-line recursive types not supported
            }
            
            $this->AddParam((string)$key, $val); // scalar
        }
        
        return $this;
    }
    
    /** @var array<SafeParam> */
    private array $params = array();
    
    /** Returns true if the named parameter exists */
    public function HasParam(string $key) : bool
    {
        return array_key_exists($key, $this->params);
    }

    /**
     * Adds the parameter to this object with the given name and value 
     * @param NULL|scalar|array<scalar, NULL|scalar|array<scalar, mixed>>|SafeParams $value
     */
    public function AddParam(string $key, $value) : self
    {
        $this->params[$key] = new SafeParam($key, $value); return $this;
    }

    private int $loglevel;
    
    /** @var ?array<string, mixed> */
    private ?array $logref = null;
    
    /** 
     * Takes an array reference for logging fetched parameters 
     * @param ?array<string, mixed> $logref
     */
    public function SetLogRef(?array &$logref, int $loglevel) : self
    {
        $this->logref = &$logref;
        $this->loglevel = $loglevel;
        return $this;
    }
    
    /** Never log this input parameter (always used for RAW) */
    public const PARAMLOG_NEVER = 0;
    
    /** Log the parameter only if details is full */
    public const PARAMLOG_ONLYFULL = Config::RQLOG_DETAILS_FULL;
    
    /** Log the parameter if log details are enabled */
    public const PARAMLOG_ALWAYS = Config::RQLOG_DETAILS_BASIC;
    
    /**
     * Gets the requested parameter (present and not null)
     * @param string $key the parameter key name
     * @param int $minlog minimum log level for logging (0 for never)
     * @throws Exceptions\SafeParamKeyMissingException if the parameter is missing
     */
    public function GetParam(string $key, int $minlog = self::PARAMLOG_ONLYFULL) : SafeParam
    {
        if (!$this->HasParam($key))
            throw new Exceptions\SafeParamKeyMissingException($key);
        
        $param = $this->params[$key];
        
        if ($this->logref !== null && $minlog > 0 && $this->loglevel >= $minlog)
        {
            $param->SetLogRef($this->logref, $this->loglevel);
        }
        
        return $param;
    }
    
    /**
    * Gets the requested parameter ($default if not present)
    * @param string $key the parameter key name
    * @param ?scalar $default parameter value if not given
    * @param int $minlog minimum log level for logging (0 for never)
    */
    public function GetOptParam(string $key, $default, int $minlog = self::PARAMLOG_ONLYFULL) : SafeParam
    {
        if (!$this->HasParam($key))
        {
            // (string)false is '', which is null, which is true for GetBool()!
            $defstr = ($default === false) ? "false" : (string)$default;
            
            return new SafeParam($key, $defstr);
        }
        
        $param = $this->params[$key];
        
        if ($this->logref !== null && $minlog > 0 && $this->loglevel >= $minlog)
        {
            $param->SetLogRef($this->logref, $this->loglevel);
        }

        return $param;
    }

    /** 
     * Returns a plain associative array of each parameter's name mapped to its raw value
     * @return array<string, mixed>
     */
    public function GetClientObject() : array
    {
        return array_map(function(SafeParam $param){ 
            return $param->GetNullRawValue(); }, $this->params);
    }
}
