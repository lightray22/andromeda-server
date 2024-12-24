<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Exceptions\JSONException;
use Andromeda\Core\IOFormat\{Input,InputAuth,InputPath,IOInterface,Output,SafeParam,SafeParams};

/** 
 * The interface for using Andromeda over a web server
 * @phpstan-import-type ScalarArray from Utilities
 * @phpstan-import-type ScalarOrArray from Utilities
 */
class HTTP extends IOInterface
{
    public static function isApplicable() : bool
    {
        return isset($_SERVER['HTTP_USER_AGENT']);
    }
    
    /** @return false */
    public static function isPrivileged() : bool { return false; }
    
    /** @return false */
    public static function isInteractive() : bool { return false; }
    
    /** @return int JSON output by default */
    public static function GetDefaultOutmode() : int { return self::OUTPUT_JSON; }
    
    public function getAddress() : string
    {
        return (string)$_SERVER['REMOTE_ADDR']; // @phpstan-ignore-line always string
    }
    
    public function getUserAgent() : string
    {
        return (string)$_SERVER['HTTP_USER_AGENT']; // @phpstan-ignore-line always string
    }

    /** Retrieves the input object from the request to run */
    protected function LoadInput() : Input
    {
        return $this->LoadHTTPInput($_REQUEST, $_GET, $_FILES, $_SERVER); // @phpstan-ignore-line types missing
    }
    
    /**
     * Retrieves the input object to run from the HTTP request
     * 
     * App and Action must be part of $_GET, everything else can be interchangeably 
     * in $_GET or $_POST except 'password' and 'auth_' which cannot be in $_GET
     * @param array<scalar|array<scalar|array<scalar>>> $req
     * @param array<scalar|array<scalar|array<scalar>>> $get
     * @param array<array<string, scalar>> $files
     * @param array<string, scalar> $server
     */
    public function LoadHTTPInput(array $req, array $get, array $files, array $server) : Input
    {
        if ($server['REQUEST_METHOD'] !== "GET" && 
            $server['REQUEST_METHOD'] !== "POST")
            throw new Exceptions\MethodNotAllowedException();

        if (!array_key_exists('_app',$get) || !array_key_exists('_act',$get))
            throw new Exceptions\MissingAppActionException('missing');
        
        $app = $get['_app']; unset($req['_app']); // app
        $act = $get['_act']; unset($req['_act']); // action

        if (!is_string($app) || !is_string($act))
            throw new Exceptions\MissingAppActionException('invalid');
        
        foreach ($get as $key=>$val)
        {
            $key = (string)$key;
            if (mb_strpos($key,'password') !== false 
                || mb_strpos($key,'auth_') === 0)
                throw new Exceptions\IllegalGetFieldException($key);
        }
        
        $params = new SafeParams();
        $params->LoadArray($req);
        
        foreach ($server as $key=>$val)
        {
            if (mb_strpos($key,"HTTP_X_ANDROMEDA_") === 0)
            {
                $key = explode("_",mb_strtolower($key),4)[3];
                if (($val = base64_decode((string)$val,true)) !== false)
                    $params->AddParam($key, $val);
                else throw new Exceptions\Base64DecodeException($key);
            }
        }
        
        $pfiles = array(); foreach ($files as $key=>$file)
        {
            if (!array_key_exists('tmp_name',$file) ||
                !array_key_exists('name',$file) ||
                !array_key_exists('error',$file))
                throw new Exceptions\FileUploadFormatException();
            
            $fpath = (string)$file['tmp_name'];
            $fname = (string)$file['name'];
            $ferror = (int)$file['error'];
            // https://www.php.net/manual/en/features.file-upload.errors.php
            // TODO can we make use of PHP 8.1's full_path here? browsers can submit PATHS as file names!
                
            if ($ferror !== 0 || !is_uploaded_file($fpath))
                throw new Exceptions\FileUploadFailException((string)$ferror);
            
            $fname = (new SafeParam('name',$fname))->GetFSName();
            $pfiles[(string)$key] = new InputPath($fpath, $fname, true); 
        }
        
        $user = $server['PHP_AUTH_USER'] ?? null;
        $pass = $server['PHP_AUTH_PW'] ?? null;
        
        $auth = ($user !== null && $pass !== null)
            ? new InputAuth((string)$user, (string)$pass) : null;

        return new Input($app, $act, $params, $pfiles, $auth);
    }
    
    /**
     * Sends the no-cache header and, if UserOutput, the HTTP code
     * @param Output $output output object
     */
    private function InitOutput(Output $output) : void
    {
        if ($this->outmode === 0)
            http_response_code($output->GetCode());
        
        header("Cache-Control: no-cache");
    }
    
    public function UserOutput(Output $output, bool $skipHeaders = false) : bool
    {
        if (!$skipHeaders) $this->InitOutput($output);
        
        return parent::UserOutput($output);
    }
    
    public function FinalOutput(Output $output, bool $skipHeaders = false) : void
    {
        if (!$skipHeaders) $this->InitOutput($output);

        if ($this->outmode === self::OUTPUT_PLAIN)
        {
            if (!$skipHeaders) // unit testing
            {
                mb_http_output('UTF-8');
                header("Content-Type: text/plain");
                http_response_code($output->GetCode());
            }

            echo $output->GetAsString();
        }
        else if ($this->outmode === self::OUTPUT_PRINTR) 
        {
            if (!$skipHeaders) // unit testing
            {
                mb_http_output('UTF-8');
                header("Content-Type: text/plain");
            }
            
            echo print_r($output->GetAsArray(), true);
        }
        else if ($this->outmode === self::OUTPUT_JSON)
        {
            if (!$skipHeaders) // unit testing
            {
                mb_http_output('UTF-8');
                header("Content-Type: application/json");
            }
            
            // apps MUST ensure they don't return anything non-utf8
            echo Utilities::JSONEncode($output->GetAsArray());
        }
    }
    
    /** 
     * Build a remote Andromeda request URL
     * @param string $url the base URL of the API
     * @param Input $input the input object describing the request
     * @param bool $params if true, add all params from the $input to the URL
     * @return string the compiled URL string
     */
    public static function GetRemoteURL(string $url, Input $input, bool $params = true)
    {
        $get = array('_app'=>$input->GetApp(), '_act'=>$input->GetAction());
        if ($params) $get += $input->GetParams()->GetClientObject();
        return $url.(mb_strpos($url,'?') === false ?'?':'&').http_build_query($get);
    }

    /**
     * Send a request to a remote Andromeda API
     * @param string $url the base URL of the API
     * @param Input $input the input describing the request
     * @throws Exceptions\RemoteInvalidException if decoding the response fails
     * @return ScalarArray the decoded remote response
     */
    public static function RemoteRequest(string $url, Input $input) : array
    {
        $url = static::GetRemoteURL($url, $input, false);

        $data = static::HTTPPost($url, $input->GetParams()->GetClientObject());
        if ($data === null) throw new Exceptions\RemoteInvalidException();

        try { return Utilities::JSONDecode($data); }
        catch (JSONException $e) { throw new Exceptions\RemoteInvalidException(); }
    }

    /**
     * Helper function to send an HTTP post request
     * @param string $url the URL of the request
     * @param array<string, ScalarOrArray> $post array of data to place in the POST body
     * @return ?string the remote response or null on failure
     */
    public static function HTTPPost(string $url, array $post) : ?string
    {
        foreach ($post as &$val) $val ??= "null";
        
        $options = array('http'=>array(
            'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            'method' => 'POST', 'content' => http_build_query($post)
        ));

        $result = file_get_contents($url, false, stream_context_create($options));

        if ($result === false) return null; else return $result;
    }
}
