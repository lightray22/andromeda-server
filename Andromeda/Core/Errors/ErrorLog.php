<?php declare(strict_types=1); namespace Andromeda\Core\Errors; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\Logging\BaseLog;

/** 
 * Represents an error log entry in the database 
 * @phpstan-import-type ScalarArray from Utilities
 * @phpstan-import-type ScalarOrArray from Utilities
 * @phpstan-type ErrorLogJ array{time:float,addr:string,agent:string,code:int,file:string,message:string,app:?string,action:?string,trace_basic:array<int,string>,
 *  trace_full?:?ScalarArray,objects?:?array<string,array<string,string>>,queries?:?array<string|array{query:string,error:string}>,hints?:?ScalarArray, params?:?array<string, ScalarOrArray>}
 */
class ErrorLog extends BaseLog
{
    protected const IDLength = 12;
    
    use TableTypes\TableNoChildren;
    
    /** time of the error */
    private FieldTypes\Timestamp $time;
    /** user address for the request */
    private FieldTypes\StringType $addr;
    /** user agent for the request */
    private FieldTypes\StringType $agent;
    /** command app */
    private FieldTypes\NullStringType $app;
    /** command action */
    private FieldTypes\NullStringType $action;
    /** error code */
    private FieldTypes\IntType $code;
    /** the file with the error */
    private FieldTypes\StringType $file;
    /** the error message */
    private FieldTypes\StringType $message;
    /** 
     * basic backtrace 
     * @var FieldTypes\JsonArray<array<int,string>>
     */
    private FieldTypes\JsonArray $trace_basic;
    /** 
     * full backtrace including all arguments 
     * @var FieldTypes\NullJsonArray<array<int,array<string,mixed>>>
     */
    private FieldTypes\NullJsonArray $trace_full;
    /** 
     * objects in memory in the database 
     * @var FieldTypes\NullJsonArray<array<mixed>>
     */
    private FieldTypes\NullJsonArray $objects;
    /** 
     * db queries that were performed 
     * @var FieldTypes\NullJsonArray<array<mixed>>
     */
    private FieldTypes\NullJsonArray $queries;
    /** 
     * all client input parameters 
     * @var FieldTypes\NullJsonArray<array<mixed>>
     */
    private FieldTypes\NullJsonArray $params;
    /** 
     * the custom API log 
     * @var FieldTypes\NullJsonArray<array<mixed>>
     */
    private FieldTypes\NullJsonArray $hints;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $fields[] = $this->time =        new FieldTypes\Timestamp('time');
        $fields[] = $this->addr =        new FieldTypes\StringType('addr');
        $fields[] = $this->agent =       new FieldTypes\StringType('agent');
        $fields[] = $this->app =         new FieldTypes\NullStringType('app');
        $fields[] = $this->action =      new FieldTypes\NullStringType('action');
        $fields[] = $this->code =        new FieldTypes\IntType('code');
        $fields[] = $this->file =        new FieldTypes\StringType('file');
        $fields[] = $this->message =     new FieldTypes\StringType('message');
        $fields[] = $this->trace_basic = new FieldTypes\JsonArray('trace_basic');
        $fields[] = $this->trace_full =  new FieldTypes\NullJsonArray('trace_full');
        $fields[] = $this->objects =     new FieldTypes\NullJsonArray('objects');
        $fields[] = $this->queries =     new FieldTypes\NullJsonArray('queries');
        $fields[] = $this->params =      new FieldTypes\NullJsonArray('params');
        $fields[] = $this->hints =       new FieldTypes\NullJsonArray('hints');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Returns the common command usage for LoadByParams() and CountByParams() */
    public static function GetPropUsage(ObjectDatabase $database) : string { 
        return "[--mintime float] [--maxtime float] [--code int32] [--addr utf8] [--agent utf8] [--app alphanum] [--action alphanum] [--message utf8] [--asc bool]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $isCount = false) : array
    {
        $criteria = array();
        
        if ($params->HasParam('maxtime')) $criteria[] = $q->LessThan('time', $params->GetParam('maxtime')->GetFloat());
        if ($params->HasParam('mintime')) $criteria[] = $q->GreaterThan('time', $params->GetParam('mintime')->GetFloat());
        
        if ($params->HasParam('code')) $criteria[] = $q->Equals('code', $params->GetParam('code')->GetInt32());
        if ($params->HasParam('addr')) $criteria[] = $q->Equals('addr', $params->GetParam('addr')->GetUTF8String());
        if ($params->HasParam('agent')) $criteria[] = $q->Like('agent', $params->GetParam('agent')->GetUTF8String());
        
        if ($params->HasParam('app')) $criteria[] = $q->Equals('app', $params->GetParam('app')->GetNullAlphanum());
        if ($params->HasParam('action')) $criteria[] = $q->Equals('action', $params->GetParam('action')->GetNullAlphanum());
        
        if ($params->HasParam('message')) $criteria[] = $q->Like('message', $params->GetParam('message')->GetUTF8String());
        
        if (!$isCount) $q->OrderBy("time", !$params->GetOptParam('asc',false)->GetBool()); // always sort by time, default desc

        return $criteria;
    }

    /**
     * Create a new DB error log entry from an ErrorInfo
     * @param ObjectDatabase $database database reference
     * @param ErrorInfo $info error log info to copy
     * @return static new ErrorLog database object
     */
    public static function Create(ObjectDatabase $database, ErrorInfo $info) : self
    {
        $obj = $database->CreateObject(static::class);
        
        $obj->time->SetValue($info->GetTime());
        $obj->addr->SetValue($info->GetAddr());
        $obj->agent->SetValue($info->GetAgent());
        $obj->app->SetValue($info->TryGetApp());
        $obj->action->SetValue($info->TryGetAction());
        $obj->code->SetValue($info->GetCode());
        $obj->file->SetValue($info->GetFile());
        $obj->message->SetValue($info->GetMessage());
        $obj->trace_basic->SetArray($info->GetTraceBasic());
        $obj->trace_full->SetArray($info->TryGetTraceFull());
        $obj->objects->SetArray($info->TryGetObjects());
        $obj->queries->SetArray($info->TryGetQueries());
        $obj->params->SetArray($info->TryGetParams());
        $obj->hints->SetArray($info->TryGetHints());
        
        return $obj;
    }

    /**
     * Returns the printable client object of this error log
     * @return ErrorLogJ
     */
    public function GetClientObject() : array
    {
        $retval = array(
            'time' => $this->time->GetValue(),
            'addr' => $this->addr->GetValue(),
            'agent' => $this->agent->GetValue(),
            'app' => $this->app->TryGetValue(),
            'action' => $this->action->TryGetValue(),
            'code' => $this->code->GetValue(),
            'file' => $this->file->GetValue(),
            'message' => $this->message->GetValue(),
            'trace_basic' => $this->trace_basic->GetArray(),
            'trace_full' => $this->trace_full->TryGetArray(),
            'objects' => $this->objects->TryGetArray(),
            'queries' => $this->queries->TryGetArray(),
            'params' => $this->params->TryGetArray(),
            'hints' => $this->hints->TryGetArray()
        );

        return $retval; // @phpstan-ignore-line technically the arrays loaded from the DB could be saved with the wrong shapes
    }
}
