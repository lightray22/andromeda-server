<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/Core/Logging/BaseLog.php"); use Andromeda\Core\Logging\BaseLog;

require_once(ROOT."/Core/Exceptions/ErrorManager.php");

/** Represents an error log entry in the database */
final class ErrorLog extends BaseLog
{
    use TableNoChildren;
    
    /** time of the request */
    private FieldTypes\FloatType $time;
    /** user address for the request */
    private FieldTypes\StringType $addr;
    /** user agent for the request */
    private FieldTypes\StringType $agent;
    /** command app */
    private FieldTypes\NullStringType $app;
    /** command action */
    private FieldTypes\NullStringType $action;
    /** error code string */
    private FieldTypes\StringType $code;
    /** the file with the error */
    private FieldTypes\StringType $file;
    /** the error message */
    private FieldTypes\StringType $message;
    /** a basic backtrace */
    private FieldTypes\JsonArray $trace_basic;
    /** full backtrace including all arguments */
    private FieldTypes\NullJsonArray $trace_full;
    /** objects in memory in the database */
    private FieldTypes\NullJsonArray $objects;
    /** db queries that were performed */
    private FieldTypes\NullJsonArray $queries;
    /** all client input parameters */
    private FieldTypes\NullJsonArray $params;
    /** the custom API log */
    private FieldTypes\NullJsonArray $log;
    
    /** @var FieldTypes\BaseField[] our copy of our fields */
    private array $fields;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->time = $fields[] =        new FieldTypes\Date('time');
        $this->addr = $fields[] =        new FieldTypes\StringType('addr');
        $this->agent = $fields[] =       new FieldTypes\StringType('agent');
        $this->app = $fields[] =         new FieldTypes\NullStringType('app');
        $this->action = $fields[] =      new FieldTypes\NullStringType('action');
        $this->code = $fields[] =        new FieldTypes\StringType('code');
        $this->file = $fields[] =        new FieldTypes\StringType('file');
        $this->message = $fields[] =     new FieldTypes\StringType('message');
        $this->trace_basic = $fields[] = new FieldTypes\JsonArray('trace_basic');
        $this->trace_full = $fields[] =  new FieldTypes\NullJsonArray('trace_full');
        $this->objects = $fields[] =     new FieldTypes\NullJsonArray('objects');
        $this->queries = $fields[] =     new FieldTypes\NullJsonArray('queries');
        $this->params = $fields[] =      new FieldTypes\NullJsonArray('params');
        $this->log = $fields[] =         new FieldTypes\NullJsonArray('log');
        
        $this->fields = $fields;
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Returns the common command usage for LoadByInput() and CountByInput() */
    public static function GetPropUsage() : string { return "[--mintime float] [--maxtime float] [--code raw] [--addr raw] [--agent raw] [--app alphanum] [--action alphanum] [--message text] [--asc bool]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input, bool $join = true) : array
    {
        $criteria = array();
        
        if ($input->HasParam('maxtime')) $criteria[] = $q->LessThan('time', $input->GetParam('maxtime',SafeParam::TYPE_FLOAT));
        if ($input->HasParam('mintime')) $criteria[] = $q->GreaterThan('time', $input->GetParam('mintime',SafeParam::TYPE_FLOAT));
        
        if ($input->HasParam('code')) $criteria[] = $q->Equals('code', $input->GetParam('code',SafeParam::TYPE_RAW));
        if ($input->HasParam('addr')) $criteria[] = $q->Equals('addr', $input->GetParam('addr',SafeParam::TYPE_RAW));
        if ($input->HasParam('agent')) $criteria[] = $q->Like('agent', $input->GetParam('agent',SafeParam::TYPE_RAW));
        
        if ($input->HasParam('app')) $criteria[] = $q->Equals('app', $input->GetNullParam('app',SafeParam::TYPE_ALPHANUM));
        if ($input->HasParam('action')) $criteria[] = $q->Equals('action', $input->GetNullParam('action',SafeParam::TYPE_ALPHANUM));
        
        if ($input->HasParam('message')) $criteria[] = $q->Like('message', $input->GetParam('message',SafeParam::TYPE_TEXT));
        
        $q->OrderBy("time", !($input->GetOptParam('asc',SafeParam::TYPE_BOOL) ?? false)); // always sort by time, default desc
        
        return $criteria;
    }

    /** Converts all objects in the array to strings and checks UTF-8 */
    private static function &arrayStrings(array &$data) : array
    {
        foreach ($data as &$val)
        {
            if (is_object($val)) 
            {
                $val = method_exists($val,'__toString') 
                    ? (string)$val : get_class($val);
            }
            else if (is_array($val)) 
                self::arrayStrings($val);
            
            if (!Utilities::isUTF8($val))
                $val = base64_encode($val);
        }
        return $data;
    }
    
    /**
     * Creates an errorLog object from the given exception
     *
     * What is logged depends on the configured debug level
     * @param ?Main $api reference to the main API
     * @param \Throwable $e the exception being debugged
     * @return self new error log entry object
     */
    public static function Create(?Main $api, \Throwable $e) : self
    {
        $obj = new self(null, array('id'=>static::GenerateID()));

        $obj->time->SetValue($api ? $api->GetTime() : microtime(true));
        $obj->addr->SetValue($api ? $api->GetInterface()->GetAddress() : "");
        $obj->agent->SetValue($api ? $api->GetInterface()->GetUserAgent() : "");
        
        $obj->code->SetValue((string)$e->getCode());
        $obj->message->SetValue($e->getMessage());
        $obj->file->SetValue($e->getFile()."(".$e->getLine().")");

        $input = ($api && ($context = $api->GetContext()) !== null) ? $context->GetInput() : null;
        
        if ($input !== null)
        {
            $obj->app->SetValue($input->GetApp());
            $obj->action->SetValue($input->GetAction());
        }
        
        $details = $api && $api->GetDebugLevel() >= Config::ERRLOG_DETAILS;
        $sensitive = $api && $api->GetDebugLevel() >= Config::ERRLOG_SENSITIVE;
        
        if ($details)
        {
            if ($api && $api->HasDatabase())
            {
                $obj->objects->SetValue($api->GetDatabase()->getLoadedObjects());
                $obj->queries->SetValue($api->GetDatabase()->GetInternal()->getAllQueries());
            }
        }
        
        if ($sensitive && $input !== null)
        {
            $params = $input->GetParams()->GetClientObject();
            $obj->params->SetValue(self::arrayStrings($params));
        }
        
        $obj->trace_basic->SetValue(explode("\n",$e->getTraceAsString()));
        
        if ($details)
        {
            $trace_full = $e->getTrace();
            
            foreach ($trace_full as &$val)
            {
                if (!$sensitive) unset($val['args']);
                
                else if (array_key_exists('args', $val))
                    self::arrayStrings($val['args']);
            }
            
            $obj->trace_full->SetValue($trace_full);
        }
        
        return $obj;
    }
    
    /**
     * Sets the supplemental debug log
     * @param int $level debug level for output
     * @param array $debuglog array of debug details
     * @return $this
     */
    public function SetDebugLog(int $level, array $debuglog) : self
    {   
        if ($level >= Config::ERRLOG_DETAILS && $debuglog !== null)
            $this->log->SetValue($debuglog);
        
        return $this;
    }
    
    /**
     * Force-saves this entry to the given database
     * @param ObjectDatabase $database database to save to
     * @return $this
     */
    public function SaveToDatabase(ObjectDatabase $database) : self
    {
        $idf = (new FieldTypes\StringType('id')); $idf->SetValue($this->ID());
        
        $fields = $this->fields; $fields['id'] = $idf;
        
        $database->InsertObject($this, array(self::class => $fields)); return $this;
    }
    
    /**
     * Returns the printable client object of this error log
     * @param ?int $level debug level for output, null for unfiltered
     * @return array `{time:float,addr:string,agent:string,code:string,file:string,message:string,app:?string,action:?string,trace_basic:array}`
        if details or null level, add `{trace_full:array,objects:?array,queries:?array,log:?array}`
        if sensitive or null level, add `{params:?array}`
     */
    public function GetClientObject(?int $level = null) : array
    {
        $retval = array(
            'time' => $this->time->GetValue(),
            'addr' => $this->addr->GetValue(),
            'agent' => $this->agent->GetValue(),
            'code' => $this->code->GetValue(),
            'file' => $this->file->GetValue(),
            'message' => $this->message->GetValue(),
            'app' => $this->app->TryGetValue(),
            'action' => $this->action->TryGetValue(),
            'trace_basic' => $this->trace_basic->GetValue()
        );
        
        $details = $level === null || $level >= Config::ERRLOG_DETAILS;
        $sensitive = $level === null || $level >= Config::ERRLOG_SENSITIVE;

        if ($details)
        {
            $trace_full = $this->trace_full->TryGetValue();
            if (!$sensitive) foreach ($trace_full as &$val) unset($val['args']);
            $retval['trace_full'] = $trace_full;
            
            $retval['objects'] = $this->objects->TryGetValue();
            $retval['queries'] = $this->queries->TryGetValue();
            $retval['log'] = $this->log->TryGetValue();
        }

        if ($sensitive)
        {
            $retval['params'] = $this->params->TryGetValue();
        }
        
        return $retval;
    }
}
