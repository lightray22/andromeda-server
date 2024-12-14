<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\IOFormat\Input;
use Andromeda\Core\Logging\ActionLog; // phpstan
use Andromeda\Core\Database\ObjectDatabase;

/** 
 * The base class from which apps must inherit
 * @phpstan-import-type ScalarOrArray from Utilities
 */
abstract class BaseApp
{
    /** Reference to the main API package */
    protected ApiPackage $API;
    /** Reference to the main database */
    protected ObjectDatabase $database;

    /** All apps are constructed when Andromeda runs */
    public function __construct(ApiPackage $api)
    {
        $this->API = $api; $this->database = $api->GetDatabase();
    }
    
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    
    /**
     * Run an action on the app with the given input
     * @param Input $input the user input
     * @return ScalarOrArray the value to be output to the user
     */
    public abstract function Run(Input $input);

    /**
     * Returns an array of strings showing the CLI usage of the app
     * @return list<string> possible commands
     */
    public abstract function getUsage() : array;
    
    /** @return string the lowercase name of the app */
    public abstract function getName() : string;
    
    /** @return string the app's version string */
    public abstract function getVersion() : string;
    
    /** 
     * Return this app's ActionLog extension class name, if used (or null)
     * If not null, the app is responsible for creating its ActionLog entries
     * @return ?class-string<ActionLog>
     */
    public function getLogClass() : ?string { return null; }

    /** Returns true if the app should create its action log */
    public function wantActionLog() : bool
    {
        $context = $this->API->GetAppRunner()->TryGetContext();

        return $this->API->isActionLogEnabled() && 
            $context !== null && $context->TryGetActionLog() === null;
    }

    /** 
     * Sets the app-created action log in the context
     * @return $this
     */
    public function setActionLog(ActionLog $actlog) : self
    {
        $context = $this->API->GetAppRunner()->TryGetContext();
        if ($context !== null) $context->SetActionLog($actlog);
        return $this;
    }

    /** Returns the current action log if set */
    public function getActionLog() : ?ActionLog
    {
        $context = $this->API->GetAppRunner()->TryGetContext();
        return ($context !== null) ? $context->TryGetActionLog() : null;
    }

    /** Tells the app to commit any changes made outside the database */
    public function commit() : void { }
    
    /** Tells the app to rollback any changes made outside the database */
    public function rollback() : void { }
}
