<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** Class for custom app output routines */
class OutputHandler
{
    /** @var callable() : ?non-negative-int */
    private $getbytes; 
    /** @var callable(Output) : void */
    private $output;
    
    /**
     * @param callable() : ?non-negative-int $getbytes get the number of bytes that will be output (can be 0) or null if no output
     * @param callable(Output) : void $output function to display custom output
     */
    public function __construct(callable $getbytes, callable $output)
    {
        $this->getbytes = $getbytes;
        $this->output = $output; 
    }
    
    /** 
     * Return the number of bytes that will be output
     * @return ?non-negative-int 
     */
    public function GetBytes() : ?int { return ($this->getbytes)(); }
    
    /** Do the actual output routine */
    public function DoOutput(Output $output) : void { ($this->output)($output); }
}
