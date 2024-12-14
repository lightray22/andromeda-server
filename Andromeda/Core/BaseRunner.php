<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\IOFormat\Input;

/**
 * A base runner that can handle Run(Input), 
 * commit/rollback, and tracks run contexts
 */
abstract class BaseRunner
{
    /** The RunContext currently being executed */
    protected ?RunContext $context = null;
    
    /** Returns the RunContext that is currently being executed, if any */
    public function TryGetContext() : ?RunContext { return $this->context; }

    public function __construct()
    {
        register_shutdown_function(function(){
            if ($this->context !== null) $this->rollback(); });
        
        if (function_exists('pcntl_signal')) pcntl_signal(SIGTERM, function(){
            if ($this->context !== null) $this->rollback(); });
    }
    
    /**
     * Run the given Input command
     * @param Input $input the user input command to run
     * @return mixed
     */
    public abstract function Run(Input $input);
    
    /**
     * Rolls back the current transaction
     * @param ?\Throwable $e the exception that caused the rollback (or null)
     */
    public abstract function rollback(?\Throwable $e = null) : void;
    
    /** Commits the current transaction */
    protected abstract function commit() : void;
}
