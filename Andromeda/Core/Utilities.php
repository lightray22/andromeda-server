<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

/** 
 * Abstract with some global static utility functions
 * @phpstan-type ScalarArray array<NULL|scalar|array<NULL|scalar|array<mixed>>>
 * @phpstan-type ScalarArrayN1 array<NULL|scalar|array<NULL|scalar|array<NULL|scalar|array<mixed>>>>
 * @phpstan-type ScalarArrayN2 array<NULL|scalar|array<NULL|scalar|array<NULL|scalar|array<NULL|scalar|array<mixed>>>>>
 * @phpstan-type ScalarOrArray NULL|scalar|ScalarArray
 */
abstract class Utilities
{
    /** @var non-empty-string&literal-string */
    private static string $chars = "0123456789abcdefghijkmnopqrstuvwxyz_"; // 36 (5 bits/char)

    /** 
     * Returns a random string with the given length 
     * @param non-negative-int $length
     */
    public static function Random(int $length) : string
    {
        $range = strlen(self::$chars)-1;
        
        $string = ""; for ($i = 0; $i < $length; $i++)
            $string .= self::$chars[random_int(0, $range)];
        
        return $string;        
    }
    
    /** Returns true iff $data is valid UTF-8 or null */
    public static function isUTF8(?string $data) : bool
    {
        return $data === null || mb_check_encoding($data,'UTF-8');
    }
    
    /**
     * Encodes an array as a JSON string
     * @param ScalarArray $jarr json array
     * @throws Exceptions\JSONException
     * @return string json string
     */
    public static function JSONEncode(array $jarr) : string
    {
        if (!is_string($jstr = json_encode($jarr)))
            throw new Exceptions\JSONException();
        return $jstr;
    }
    
    /**
     * Decodes a JSON string as an array
     * @param string $jstr json string
     * @throws Exceptions\JSONException
     * @return ScalarArray json array
     */
    public static function JSONDecode(string $jstr) : array
    {
        if (!is_array($jarr = json_decode($jstr, true)))
            throw new Exceptions\JSONException();
        return $jarr;; // @phpstan-ignore-line manually prove type
    }
    
    /**
     * @template T
     * @param array<?T> $arr array input 
     * @return ?T the last element of an array or null if it's empty 
     */
    public static function array_last(array $arr)
    {
        if (count($arr) === 0) return null;
        
        return $arr[array_key_last($arr)];
    }

    /** 
     * Converts all objects in the array to scalars recursively AND checks UTF-8, to make it printable
     * @template T of array-key
     * @param array<T, mixed> $data
     * @return array<T, ScalarOrArray>
     */
    public static function toScalarArray(array $data) : array
    {
        foreach ($data as &$val)
        {
            if (is_array($val))
            {
                $val = self::toScalarArray($val);
            }
            else if (is_object($val))
            {
                $val = method_exists($val,'__toString')
                    ? (string)$val : $val::class;
            }
            else if (is_scalar($val))
            {
                if (is_string($val) && !Utilities::isUTF8($val))
                    $val = base64_encode($val);
            }
            else $val = print_r($val,true);
        }
        return $data; // @phpstan-ignore-line manually prove type
    }
    
    /** Returns a class name with the namespace stripped */
    public static function ShortClassName(string $class) : string 
    { 
        $ret = self::array_last(explode("\\",$class));
        assert($ret !== null); // explode will return >= 1
        return $ret;
    }
    
    /** Returns the given string with the first character capitalized */
    public static function FirstUpper(string $str) : string 
    {
        return mb_strtoupper(mb_substr($str,0,1)).mb_substr($str,1); 
    }
    
    /** Returns the given string trimmed and with each character after a space capitalized */
    public static function CapitalizeWords(string $str) : string
    { 
        return implode(" ",array_map(function(string $p){ 
            return Utilities::FirstUpper($p); }, explode(" ", mb_trim($str)))); 
    }
    
    /**
     * Returns a size string converted to bytes
     * @param string $val a size string e.g. 32M or 4K
     * @return int formatted as bytes e.g. 33554432 or 4096
     */
    public static function return_bytes(string $val) : int
    {
        $val = mb_trim($val); 
        if ($val === "") return 0;
        
        $num = (int)($val);
        switch (mb_substr($val, -1)) {
            case 'T': $num *= 1024;
            case 'G': $num *= 1024;
            case 'M': $num *= 1024;
            case 'K': $num *= 1024;
        }; return $num;
    }
    
    /** Equivalent to str_replace but only does one replacement */
    public static function replace_first(string $search, string $replace, string $subject) : string
    {
        if ($search === "" || $subject === "") return $subject;
        
        if (($pos = mb_strpos($subject, $search)) !== false)
        {
            return mb_substr($subject, 0, $pos).$replace.
                mb_substr($subject, $pos+mb_strlen($search));
        }
        else return $subject;
    }

    /**
     * Escape a string replacing delims with \\ (and correctly handling existing escape characters)
     * @param string $str input string to escape
     * @param list<string> $delims list of delimeters to escape
     */
    public static function escape_all(string $str, array $delims) : string
    {
        // TODO FUTURE not multibyte safe, will replace part of UTF-8 characters
        $str = str_replace("\\", "\\\\", $str);
        foreach ($delims as $delim)
            $str = str_replace($delim,"\\$delim", $str);
        return $str;
    }
    
    /** 
     * Captures and returns any echoes or prints in the given function 
     * @throws Exceptions\OutputBufferException if the PHP ob functions fail
     */
    public static function CaptureOutput(callable $func) : string
    {
        if (ob_start() === false) 
            throw new Exceptions\OutputBufferException("ob_start fail"); 
        
        $func(); $retval = ob_get_clean();
        
        if ($retval === false)
            throw new Exceptions\OutputBufferException("ob_get_clean fail");
        
        return $retval;
    }
    
    /** 
     * Performs a key-based array map
     * @template T
     * @param callable(string):T $func function to map onto each key
     * @param list<string> $keys array of keys to feed into $func
     * @return array<string, T>
     */
    public static function array_map_keys(callable $func, array $keys) : array
    {
        $retval = array_combine($keys, array_map($func, $keys)); 
        
        // ASSERT: array_combine must be an array when both arrays have the same size
        assert(is_array($retval)); return $retval; // @phpstan-ignore-line PHP8 never returns false
    }
    
    /**
     * Returns true if the given array has 0..N keys and scalar values
     * @param array<mixed> $arr
     * @return bool
     */
    public static function is_plain_array(array $arr) : bool
    {
        if (count($arr) === 0) return true;
        if (!isset($arr[0])) return false; // shortcut
        if (!is_scalar($arr[0])) return false; // shortcut
        
        if (array_keys($arr) !== range(0, count($arr)-1)) return false;
        foreach ($arr as $val) if (!is_scalar($val)) return false;
        return true;
    }

    /** 
     * Runs the given function with no execution timeouts or user aborts 
     * @template T
     * @param callable():T $func function to run
     * @return T returned value from $func
     */
    public static function RunNoTimeout(callable $func)
    {
        $tl = (int)ini_get('max_execution_time'); set_time_limit(0);
        $ua = (bool)ignore_user_abort(); ignore_user_abort(true);
        
        $retval = $func();
        
        set_time_limit($tl); ignore_user_abort($ua);
        
        return $retval;
    }
}
