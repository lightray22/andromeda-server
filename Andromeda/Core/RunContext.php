<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\DBStats;
use Andromeda\Core\IOFormat\Input;
use Andromeda\Core\Logging\ActionLog;

/** Supplemental objects for the current app action being run */
class RunContext 
{ 
    private Input $input;     
    private ?ActionLog $actionlog = null;
    private ?DBStats $actionMetrics = null;
    private ?DBStats $commitMetrics = null;
    
    public function __construct(Input $input)
    {
        $this->input = $input;
    }
    
    /** Returns the input object being run */
    public function GetInput() : Input { return $this->input; }
    
    /** Returns the action log created for this run */
    public function TryGetActionLog() : ?ActionLog { return $this->actionlog; }

    /** 
     * Sets the action log to the given object or null
     * @param ?ActionLog $log the log object to set
     */
    public function SetActionLog(?ActionLog $log) : void { 
        $this->actionlog = $log; }
    
    /** Sets the action metrics created for this context */
    public function SetActionMetrics(DBStats $metrics) : void { 
        $this->actionMetrics = $metrics; }

    /** Returns true if action metrics were set */
    public function HasActionMetrics() : bool { 
        return $this->actionMetrics !== null; }

    /** 
     * Returns the action metrics created for this run
     * @throws Exceptions\MissingMetricsException if it wasn't set
     */
    public function GetActionMetrics() : DBStats 
    { 
        if ($this->actionMetrics === null) 
            throw new Exceptions\MissingMetricsException();
        else return $this->actionMetrics;
    }
    
    /** Sets the commit metrics created for this context */
    public function SetCommitMetrics(DBStats $metrics) : void { 
        $this->commitMetrics = $metrics; }
        
    /** Returns true if commit metrics were set */
    public function HasCommitMetrics() : bool { 
        return $this->commitMetrics !== null; }

    /** 
     * Returns the commit metrics created for this run
     * @throws Exceptions\MissingMetricsException if it wasn't set
     */
    public function GetCommitMetrics() : DBStats 
    { 
        if ($this->commitMetrics === null) 
            throw new Exceptions\MissingMetricsException();
        else return $this->commitMetrics;
    }
}
