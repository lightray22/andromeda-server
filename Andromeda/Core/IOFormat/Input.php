<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\Logging\ActionLog;

/** 
 * An abstracted Input object gathered from an interface
 * 
 * An Input object describes the app and action to be run, as well
 * as any input parameters or files, or basic authentication
 */
class Input
{
    /** @see Input::GetApp() */
    private string $app;        
    
    /** The app to be run */
    public function GetApp() : string { return $this->app; }
    
    /** @see Input::GetAction() */
    private string $action;     
    
    /** The app action to be run */
    public function GetAction() : string { return $this->action; }
    
    /** @see Input::GetAuth() */
    private ?InputAuth $auth;   
    
    /** The basic authentication to be used, always logged */
    public function GetAuth() : ?InputAuth 
    {
        if ($this->auth !== null && $this->logger !== null)
            $this->logger->SetAuthUser($this->auth->GetUsername());

        return $this->auth; 
    }
    
    private ?ActionLog $logger = null;

    /** Sets the optional param logger to the given ActionLog */
    public function SetLogger(?ActionLog $logger) : self 
    { 
        $this->logger = $logger;
        if ($logger !== null)
        {
            $loglevel = $logger->GetDetailsLevel();
            $plogref = &$logger->GetParamsLogRef();
            $this->params->SetLogRef($plogref, $loglevel);
        }        
        return $this; 
    }
    
    private SafeParams $params;
    
    /** The inner collection of parameters to be used */
    public function GetParams() : SafeParams { return $this->params; }

    /** @var array<string, InputFile> */
    private array $files;
    
    /** 
     * Returns the array of input files
     * @return array<string, InputFile>
     */
    public function GetFiles() : array { return $this->files; }

    /**
     * Determines whether or not the given key exists as an input file
     * @param string $key the parameter name to check for
     * @return bool true if the param exists as an input file
     */
    public function HasFile(string $key) : bool {
        
        return array_key_exists($key, $this->files); 
    }
        
    /**
     * Adds the given InputFile to the file array
     * @param string $key param name for file
     * @param InputFile $file input file stream
     * @return $this
     */
    public function AddFile(string $key, InputFile $file) : self 
    {
        $this->files[$key] = $file; return $this; 
    }
    
    /** Logs the given InputFile and returns it */
    protected function LogFile(string $key, InputFile $file, int $minlog) : InputFile
    {
        if ($this->logger !== null && $minlog > 0)
        {
            if ($this->logger->GetDetailsLevel() >= $minlog)
            {
                $logref = &$this->logger->GetFilesLogRef();
                $logref[$key] = ($file instanceof InputPath)
                    ? $file->GetClientObject() : null;
            }
        }
        return $file;
    }
    
    /**
     * Gets the file mapped to the parameter name (and immediately logs)
     * @param string $key the parameter key name
     * @throws Exceptions\InputFileMissingException if the key does not exist
     * @return InputFile the uploaded file
     */
    public function GetFile(string $key, int $minlog = SafeParams::PARAMLOG_ONLYFULL) : InputFile
    {
        if (!$this->HasFile($key)) 
            throw new Exceptions\InputFileMissingException($key);
    
        return $this->LogFile($key, $this->files[$key], $minlog);
    }
    
    /**
     * Same as GetFile() but returns null rather than throwing an exception
     * @see Input::GetFile()
     */
    public function TryGetFile(string $key, int $minlog = SafeParams::PARAMLOG_ONLYFULL) : ?InputFile
    {
        if (!$this->HasFile($key)) return null;
        
        return $this->LogFile($key, $this->files[$key], $minlog);
    }
    
    /** 
     * Constructs an input object using the data gathered from the interface
     * @param string $app app name to run (will be sanitized)
     * @param string $action app action name (will be sanitized)
     * @param ?SafeParams $params user input params
     * @param ?array<string, InputFile> $files optional input files
     * @param ?InputAuth $auth optional input authentication
     * @throws Exceptions\SafeParamInvalidException if app/action are not valid
     */
    public function __construct(string $app, string $action, ?SafeParams $params = null, 
                                ?array $files = null, ?InputAuth $auth = null)
    {
        $this->app = strtolower((new SafeParam('app', $app))->GetAlphanum());
        $this->action = strtolower((new SafeParam('act', $action))->GetAlphanum());

        $this->params = $params ?? new SafeParams(); 
        $this->files = $files ?? array();
        $this->auth = $auth;
    }
}
