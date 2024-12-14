<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** An input file stream */
class InputStream extends InputFile
{
    /** 
     * @param resource $handle
     * @param string $name optional file name
     */
    public function __construct($handle, string $name) { 
        $this->handle = $handle; $this->name = $name; }
        
    public function __destruct()
    { 
        if (is_resource($this->handle))
            fclose($this->handle); 
    }
   
    /** 
     * Returns the file's stream resource 
     * @throws Exceptions\FileReadFailedException if it fails
     * @return resource
     */
    public function GetHandle()
    { 
        if (!is_resource($this->handle))
            throw new Exceptions\FileReadFailedException("stream");
        
        return $this->handle; 
    }
}
