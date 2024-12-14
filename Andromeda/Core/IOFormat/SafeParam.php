<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Exceptions\JSONException;
use Andromeda\Core\IOFormat\Exceptions\{SafeParamInvalidException, SafeParamNullValueException};

/**
 * Class representing a client input parameter
 * 
 * Provides a consistent interface for sanitizing and validating input values
 * A value is considered null if it is an empty string or the string "null"
 * @phpstan-import-type ScalarArray from Utilities
 * @phpstan-import-type ScalarOrArray from Utilities
 */
class SafeParam
{
    /** @var non-empty-string */
    private string $key;
    
    /** 
     * The original constructed value, for GetNullRawValue()
     * @var ?scalar 
     */
    private $origval = null;
    
    /** @var ?non-empty-string */
    private ?string $strval = null;
    /** @var ?ScalarArray */ 
    private ?array $arrval = null;
    private ?SafeParams $objval = null;
    
    /** 
     * Construct a new SafeParam with the given key and value
     * @param string $key name of the input param - MUST NOT CONTAIN "-"
     * @param ScalarOrArray|SafeParams $value
     * @throws SafeParamInvalidException if the key name is invalid
     */
    public function __construct(string $key, $value)
    {
        if ($key === "" || preg_match("%^[a-zA-Z0-9_.]+$%", $key) !== 1)
            throw new SafeParamInvalidException("(key)", 'alphanum');
        
        $this->key = $key;
        
        if (is_array($value)) 
            $this->arrval = $value;
        else if ($value instanceof SafeParams)
            $this->objval = $value;
        else
        {
            $this->origval = $value;
            $strval = ($value === false) ? "false" : (string)$value;
            if ($strval !== "" && $strval !== "null" && $strval !== "NULL")
                $this->strval = $strval;
        }
    }

    private int $loglevel;
    
    /** @var ?array<string, ScalarOrArray> */
    private ?array $logref = null;
    
    /** 
     * Takes an array reference for logging fetched parameters 
     * NOTE this does NOT pay attention to the loglevel, it only
     * passes it to the sub-SafeParams if you use GetObject()!
     * @param ?array<string, ScalarOrArray> $logref
     */
    public function SetLogRef(?array &$logref, int $loglevel) : void
    {
        $this->logref = &$logref;
        $this->loglevel = $loglevel;
    }
    
    /** @param NULL|scalar|array<scalar> $data */
    protected function LogValue($data) : void
    {
        if ($this->logref !== null)
            $this->logref[$this->key] = $data;
    }
    
    /**
     * Returns the given value as a string or null
     * @throws SafeParamInvalidException if other type given
     */
    protected function tryGetStr() : ?string
    {
        if ($this->isNull()) return null;
        if ($this->strval !== null) return $this->strval;
        throw new SafeParamInvalidException($this->key,'string');
    }
    
    /**
     * Returns the given value as an array or null
     * @throws SafeParamInvalidException if other type given
     * @return ?ScalarArray
     */
    protected function tryGetArr() : ?array
    {
        if ($this->isNull()) return null;
        if ($this->arrval !== null) return $this->arrval;
        throw new SafeParamInvalidException($this->key,'array');
    }
    
    /**
     * Returns the given value as an object or null
     * @throws SafeParamInvalidException if other type given
     */
    protected function tryGetObj() : ?SafeParams
    {
        if ($this->isNull()) return null;
        if ($this->objval !== null) return $this->objval;
        throw new SafeParamInvalidException($this->key,'object');
    }
    
    /** 
     * Returns the value as originally given (UNSAFE), NEVER logged
     * @return ScalarOrArray
     */
    public function GetNullRawValue()
    {
        if ($this->origval !== null) 
            return $this->origval;
        if ($this->arrval !== null)
            return $this->arrval;
        if ($this->objval !== null) 
            return $this->objval->GetAllRawValues();
        return null;
    }
    
    /** Returns the raw unchecked value string (or null), NEVER logged */
    public function GetNullRawString() : ?string { return $this->tryGetStr(); }
    
    /** 
     * If not null, checks that a custom validation function returns true
     * @param callable(string):bool $valfunc custom function
     * @throws SafeParamInvalidException if not valid
     * @return $this
     */
    public function CheckFunction(callable $valfunc) : self
    {
        $str = $this->tryGetStr();
        if ($str !== null && !$valfunc($str))
            throw new SafeParamInvalidException($this->key);
        else return $this;
    }

    /**
     * Checks that the string/array length is below a maximum or null
     * @param int $maxlen maximum length (inclusive)
     * @throws SafeParamInvalidException if not valid or not string/array
     * @return $this
     */
    public function CheckLength(int $maxlen) : self
    {
        if ($this->strval === null && $this->arrval === null)
        {
            if ($this->objval === null) return $this;
            throw new SafeParamInvalidException($this->key,'countable');
        }
        
        if ($this->strval !== null && mb_strlen($this->strval) > $maxlen)
            throw new SafeParamInvalidException($this->key, "max length $maxlen");
        
        if ($this->arrval !== null && count($this->arrval) > $maxlen)
            throw new SafeParamInvalidException($this->key, "max length $maxlen");
            
        return $this;
    }
    
    /** Returns true if the param value is null */
    public function isNull() : bool 
    {
        return $this->strval === null && 
               $this->arrval === null && 
               $this->objval === null;
    }

    /**
     * Checks that the param's value is in the given array or null
     * @template T of list<string>
     * @param T $values allowlisted values
     * @throws SafeParamInvalidException if not valid
     * @return ?value-of<T> the allowlisted value or null
     */
    public function FromAllowlistNull(array $values) : ?string
    {
        $str = $this->tryGetStr();
        
        if ($str !== null && !in_array($str,$values,true))
            throw new SafeParamInvalidException($this->key, implode('|',$values));
        
        $this->LogValue($str); return $str;
    }

    /**
     * Returns a boolean value, see FILTER_VALIDATE_BOOLEAN
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullBool() : ?bool
    {
        $str = $this->tryGetStr();
        
        $value = null; if ($str !== null)
        {
            $value = filter_var($str, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) throw new SafeParamInvalidException($this->key, 'bool');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /** @throws SafeParamInvalidException if not valid */
    protected function GetBaseInt() : ?int
    {
        $str = $this->tryGetStr();
        if ($str === null) return null;
        
        $value = filter_var($str, FILTER_VALIDATE_INT);
        if ($value === false) throw new SafeParamInvalidException($this->key, 'int');
        
        return $value;
    }
    
    /**
     * Returns an integer value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullInt() : ?int
    {
        $value = $this->GetBaseInt();
       
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns a 32-bit integer value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullInt32() : ?int
    {
        $value = $this->GetBaseInt(); 

        if ($value !== null && ($value < -2147483648 || $value > 2147483647))
            throw new SafeParamInvalidException($this->key, 'int32');
    
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns a 16-bit integer value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullInt16() : ?int
    {
        $value = $this->GetBaseInt(); 

        if ($value !== null && ($value < -32768 || $value > 32767))
            throw new SafeParamInvalidException($this->key, 'int16');
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an 8-bit value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullInt8() : ?int
    {
        $value = $this->GetBaseInt(); 
        
        if ($value !== null && ($value < -128 || $value > 127))
            throw new SafeParamInvalidException($this->key, 'int8');
    
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an unsigned integer value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int|NULL
     */
    public function GetNullUint() : ?int
    {
        $value = $this->GetBaseInt(); 

        if ($value !== null && $value < 0)
            throw new SafeParamInvalidException($this->key, 'uint');
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an unsigned 32-bit integer value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int|NULL
     */
    public function GetNullUint32() : ?int
    {
        $value = $this->GetBaseInt(); 

        if ($value !== null && ($value < 0 || $value > 4294967295))
            throw new SafeParamInvalidException($this->key, 'uint32');
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an unsigned 16-bit integer value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int|NULL
     */
    public function GetNullUint16() : ?int
    {
        $value = $this->GetBaseInt(); 
        
        if ($value !== null && ($value < 0 || $value > 65535))
            throw new SafeParamInvalidException($this->key, 'uint16');
    
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an unsigned 8-bit integer value, see FILTER_VALIDATE_INT
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int|NULL
     */
    public function GetNullUint8() : ?int
    {
        $value = $this->GetBaseInt(); 
        
        if ($value !== null && ($value < 0 || $value > 255))
            throw new SafeParamInvalidException($this->key, 'uint8');
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns a float value, see FILTER_VALIDATE_FLOAT
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullFloat() : ?float
    {
        $str = $this->tryGetStr(); 
        
        $value = null; if ($str !== null)
        {
            $value = filter_var($str, FILTER_VALIDATE_FLOAT);
            if ($value === false) throw new SafeParamInvalidException($this->key, 'float');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns a value matching the format  randomly generated by Andromeda
     * @see Utilities::Random()
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullRandstr() : ?string
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $value = trim($value);
            
            if (preg_match("%^[a-zA-Z0-9_]+$%", $value) !== 1)
                throw new SafeParamInvalidException($this->key, 'randstr');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an alphanumeric (plus -_.) value, max length 255
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullAlphanum() : ?string
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $this->CheckLength(255);
            $value = trim($value);
            
            if (preg_match("%^[a-zA-Z0-9\-_.]+$%", $value) !== 1)
                throw new SafeParamInvalidException($this->key, 'alphanum');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns a human name or label (alphanum plus -_'(). and space), max length 255
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullName() : ?string
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $this->CheckLength(255);
            $value = trim($value);
            
            if (preg_match("%^[a-zA-Z0-9\-_'(). ]+$%", $value) !== 1)
                throw new SafeParamInvalidException($this->key, 'name');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an email address, max length 127, see FILTER_VALIDATE_EMAIL
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullEmail() : ?string
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $this->CheckLength(127);
            $value = trim($value);
            
            $value = filter_var($value0=$value, FILTER_VALIDATE_EMAIL);
            if ($value !== $value0) // FILTER_EMAIL checks for ""
                throw new SafeParamInvalidException($this->key, 'email');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * A filesystem item name, forbids /\?*:;{}, directory traversal, max length 255, and FILTER_UNSAFE_RAW/FILTER_FLAG_STRIP_LOW
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullFSName() : ?string
    {
       $value = $this->tryGetStr();
       if ($value !== null)
       {
           $this->CheckLength(255);
           $value = trim($value);
           
           if (preg_match("%[\\\\/?*:;{}]+%", $value) !== 0) // blacklist
               throw new SafeParamInvalidException($this->key, 'fsname');
           
           $value = filter_var($value0=$value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
           if ($value === "" || $value !== $value0 || !Utilities::isUTF8($value))
               throw new SafeParamInvalidException($this->key, 'fsname');
           
           if (basename($value) !== $value || $value === '.' || $value === '..')
               throw new SafeParamInvalidException($this->key, 'fsname');
       }
       
       $this->LogValue($value); return $value;
    }
    
    /**
     * A filesystem path, forbids ?*;{}, :/, max length 65535, and FILTER_UNSAFE_RAW/FILTER_FLAG_STRIP_LOW - ALLOWS ./..
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullFSPath() : ?string
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $this->CheckLength(65535);
            $value = trim($value);
            
            if (preg_match("%[?*;{}]+%", $value) !== 0 || 
                mb_strpos($value, ':/') !== false) // blacklist
                throw new SafeParamInvalidException($this->key, 'fspath');
            
            $value = filter_var($value0=$value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
            if ($value === "" || $value !== $value0 || !Utilities::isUTF8($value))
                throw new SafeParamInvalidException($this->key, 'fspath');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns a hostname, max length 255, see FILTER_VALIDATE_DOMAIN and FILTER_FLAG_HOSTNAME
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullHostname() : ?string
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $this->CheckLength(255);
            $value = trim($value);
            
            $value = filter_var($value0=$value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
            if ($value === "" || $value !== $value0)
                throw new SafeParamInvalidException($this->key, 'hostname');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns an HTML-safe value with HTML-significant characters encoded, checks UTF-8, see FILTER_SANITIZE_SPECIAL_CHARS
     * @throws SafeParamInvalidException if not valid
     */
    public function GetNullHTMLText() : ?string // safe for HTML
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $this->CheckLength(65535);
            
            $value = filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS);
            if ($value === "" || $value === false || !Utilities::isUTF8($value)) // allow sanitizing
                throw new SafeParamInvalidException($this->key, 'text');
        }
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * Returns a string with UTF-8 checked and FILTER_UNSAFE_RAW/FILTER_FLAG_STRIP_LOW
     * @throws SafeParamInvalidException
     * @return ?string
     */
    public function GetNullUTF8String() : ?string
    {
        $value = $this->tryGetStr();
        if ($value !== null)
        {
            $this->CheckLength(65535);
            
            $value = filter_var($value0=$value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
            if ($value === "" || $value !== $value0 || !Utilities::isUTF8($value))
                throw new SafeParamInvalidException($this->key, 'utf8');
        }
        
        $this->LogValue($value); return $value;
    }

    /**
     * Returns the value as a JSON object decoded into SafeParams
     * @throws SafeParamInvalidException if not valid JSON
     */
    public function GetNullObject() : ?SafeParams
    {
        if ($this->objval !== null)
        {
            $obj = $this->objval;
        }
        else if ($this->strval !== null)
        {
            try { $value = Utilities::JSONDecode($this->strval); }
            catch (JSONException $e) { throw new SafeParamInvalidException($this->key, 'json'); }
            
            $obj = new SafeParams();
            $obj->LoadArray($value);
        }
        else // null
        {
            if ($this->arrval === null) { $this->LogValue(null); return null; }
            throw new SafeParamInvalidException($this->key,'json|object');
        }

        if ($this->logref !== null)
        {
            $this->logref[$this->key] = array();
            $obj->SetLogRef($this->logref[$this->key], $this->loglevel); // @phpstan-ignore-line pass by reference converts to mixed?
        }
            
        return $obj;
    }
    
    /**
     * Returns an array of scalars decoded from JSON
     * @template T of scalar
     * @param callable(SafeParam):T $getval function to get scalar from SafeParam
     * @return ?array<T> array of checked scalars
     * @throws SafeParamInvalidException if not valid JSON
     */
    public function GetNullArray(callable $getval) : ?array
    {
        if ($this->arrval !== null)
        {
            $value = $this->arrval;
        }
        else if ($this->strval !== null)
        {
            try { $value = Utilities::JSONDecode($this->strval); }
            catch (JSONException $e) { throw new SafeParamInvalidException($this->key, 'json'); }
        }
        else // null
        {
            if ($this->objval === null) { $this->LogValue(null); return null; }
            throw new SafeParamInvalidException($this->key,'json|array');
        }

        $arr = array();
        foreach ($value as $subval)
        {
            if (!is_scalar($subval)) // this function gets T of scalar
                throw new SafeParamInvalidException($this->key, 'arr[scalar]');
            
            $arr[] = $getval( // ignore key names
                new SafeParam($this->key, $subval));
        }

        $this->LogValue($arr); return $arr;
    }
    
    /**
     * Returns an array of SafeParamses decoded from JSON
     * @return ?array<SafeParams> array of SafeParamses
     * @throws SafeParamInvalidException if not valid JSON
     */
    public function GetNullObjectArray() : ?array
    {
        if ($this->arrval !== null)
        {
            $value = $this->arrval;
        }
        else if ($this->strval !== null)
        {
            try { $value = Utilities::JSONDecode($this->strval); }
            catch (JSONException $e) { throw new SafeParamInvalidException($this->key, 'json'); }
        }
        else // null
        {
            if ($this->objval === null) { $this->LogValue(null); return null; }
            throw new SafeParamInvalidException($this->key,'json|array');
        }

        $arr = array();
        foreach ($value as $subval)
        {
            if (!is_array($subval))
                throw new SafeParamInvalidException($this->key, 'arr[object]');
            
            $arr[] = $params = new SafeParams();
            $params->LoadArray($subval); // @phpstan-ignore-line no recursive ScalarArray
        }
        
        if ($this->logref !== null)
        {
            $sublog = &$this->logref[$this->key];
            
            $sublog = array();
            foreach ($arr as $subval)
            {
                $idx = array_push($sublog, array());
                $subval->SetLogRef($sublog[$idx-1], $this->loglevel);
            }
        }
        
        return $arr;
    }

    /**
     * Checks that the param's value is in the given array
     * @template T of list<string>
     * @param T $values allowlisted values
     * @throws SafeParamInvalidException if not valid
     * @throws SafeParamNullValueException if null
     * @return value-of<T> the allowlisted value
     */
    public function FromAllowlist(array $values) : string
    {
        if (($value = $this->FromAllowlistNull($values)) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * Returns the raw unchecked value string (NOT null), NEVER logged
     * @throws SafeParamNullValueException if null
     */
    public function GetRawString() : string
    {
        if (($value = $this->GetNullRawString()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * Returns a non-null boolean (null becomes true)
     * @see SafeParam::GetNullBool()
     */
    public function GetBool() : bool
    {
        $value = $this->GetNullBool();
        
        if ($value === null) $value = true; // null->true (flags)
        
        $this->LogValue($value); return $value;
    }
    
    /**
     * @see SafeParam::GetNullInt()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetInt() : int
    {
        if (($value = $this->GetNullInt()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullInt32()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetInt32() : int
    {
        if (($value = $this->GetNullInt32()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullInt16()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetInt16() : int
    {
        if (($value = $this->GetNullInt16()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullInt8()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetInt8() : int
    {
        if (($value = $this->GetNullInt8()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullUint()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int
     */
    public function GetUint() : int
    {
        if (($value = $this->GetNullUint()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullUint32()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int
     */
    public function GetUint32() : int
    {
        if (($value = $this->GetNullUint32()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullUint16()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int
     */
    public function GetUint16() : int
    {
        if (($value = $this->GetNullUint16()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullUint8()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     * @return non-negative-int
     */
    public function GetUint8() : int
    {
        if (($value = $this->GetNullUint8()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullFloat()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetFloat() : float
    {
        if (($value = $this->GetNullFloat()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullRandstr()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetRandstr() : string
    {
        if (($value = $this->GetNullRandstr()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullAlphanum()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetAlphanum() : string
    {
        if (($value = $this->GetNullAlphanum()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullName()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetName() : string
    {
        if (($value = $this->GetNullName()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullEmail()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetEmail() : string
    {
        if (($value = $this->GetNullEmail()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullFSName()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetFSName() : string
    {
        if (($value = $this->GetNullFSName()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullFSPath()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetFSPath() : string
    {
        if (($value = $this->GetNullFSPath()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullHostname()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetHostname() : string
    {
        if (($value = $this->GetNullHostname()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullHTMLText()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetHTMLText() : string
    {
        if (($value = $this->GetNullHTMLText()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullUTF8String()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetUTF8String() : string
    {
        if (($value = $this->GetNullUTF8String()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * @see SafeParam::GetNullObject()
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid
     */
    public function GetObject() : SafeParams
    {
        if (($value = $this->GetNullObject()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * Returns an array of scalars decoded from JSON
     * @template T of scalar
     * @param callable(SafeParam):T $getval function to get scalar from SafeParam
     * @return array<T> array of checked scalars
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid JSON
     */
    public function GetArray(callable $getval) : array
    {
        if (($value = $this->GetNullArray($getval)) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
    
    /**
     * Returns an array of SafeParamses decoded from JSON
     * @return array<SafeParams> array of SafeParamses
     * @throws SafeParamNullValueException if null
     * @throws SafeParamInvalidException if not valid JSON
     */
    public function GetObjectArray() : array
    {
        if (($value = $this->GetNullObjectArray()) === null)
            throw new SafeParamNullValueException($this->key);
        else return $value;
    }
}
