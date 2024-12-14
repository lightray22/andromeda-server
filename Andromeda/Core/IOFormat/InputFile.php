<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** Basic file input */
abstract class InputFile
{
    /** @var ?resource */
    protected $handle = null;
    protected string $name;
    
    /**
     * Returns the file's stream resource
     * @return resource
     */
    public abstract function GetHandle();
    
    /**
     * Returns the entire stream contents (ONCE!)
     * Can only be read ONCE! stream is closed after this
     * @throws Exceptions\FileReadFailedException if it fails
     */
    public function GetData() : string
    {
        $handle = $this->GetHandle();

        $retval = stream_get_contents($handle);
        
        if ($retval === false)
            throw new Exceptions\FileReadFailedException("stream");
        
        fclose($handle); 
        $this->handle = null;
        
        return $retval;
    }

    /** Returns the name of the file to be used */
    public function GetName() : string { return $this->name; }
}
