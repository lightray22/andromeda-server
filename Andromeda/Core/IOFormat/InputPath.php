<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** 
 * A file given as a path to an actual file 
 * GetData() can be called more than once
 */
class InputPath extends InputFile
{
    private string $path;
    private bool $istemp;
    
    /**
     * @param string $path path to the input file
     * @param ?string $name optional new name of the file
     * @param bool $istemp if true, is a tmp file we can move
     */
    public function __construct(string $path, ?string $name = null, bool $istemp = false) 
    {
        $this->path = $path; 
        $this->istemp = $istemp; 
        $this->name = $name ?? basename($path);
    }
    
    public function __destruct()
    {
        if (is_resource($this->handle))
            fclose($this->handle);
    }
    
    /** Returns the path to the input file */
    public function GetPath() : string { return $this->path; }
    
    /** Returns true if the file is a temp file that can be moved */
    public function isTemp() : bool { return $this->istemp; }
    
    /** 
     * Returns the size of the file to be used 
     * @throws Exceptions\FileReadFailedException if it fails
     * @return non-negative-int
     */
    public function GetSize() : int 
    { 
        $retval = filesize($this->path);
        if ($retval === false)
            throw new Exceptions\FileReadFailedException($this->path);
        else return $retval;
    }

    /**
     * Returns the file's stream resource - seeks to 0!
     * @throws Exceptions\FileReadFailedException if it fails
     * @return resource
     */
    public function GetHandle() 
    {
        if (!is_resource($this->handle))
        {
            $handle = fopen($this->path,'rb');
            if ($handle === false)
                throw new Exceptions\FileReadFailedException($this->path);
            else $this->handle = $handle;
        }
        
        fseek($this->handle, 0);
        return $this->handle;
    }
    
    /** @return array{name:string, path:string, size:int} */
    public function GetClientObject() : array
    {
        return array(
            'name' => $this->name,
            'path' => $this->path,
            'size' => $this->GetSize()
        );
    }
}
