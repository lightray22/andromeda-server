<?php declare(strict_types=1); namespace Andromeda\Apps\Testutil; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseApp, Utilities};
use Andromeda\Core\Database\QueryBuilder;
use Andromeda\Core\Errors\{BaseExceptions\ServerException, ErrorLog};
use Andromeda\Core\Exceptions\UnknownActionException;
use Andromeda\Core\IOFormat\{Input, InputFile, IOInterface, SafeParams};

use Andromeda\Core\Logging\ActionLog as BaseActionLog;
use Andromeda\Apps\Core\ActionLog;

/**
 * Utility app for the python test framework
 * @phpstan-import-type ScalarOrArray from Utilities
 * @phpstan-import-type ErrorLogJ from ErrorLog
 */
class TestutilApp extends BaseApp
{
    public function getName() : string { return 'testutil'; }
    
    public function getVersion() : string { return andromeda_version; }
    
    public function getUsage() : array
    {
        $retval = array(
            'random [--length uint]',
            'testiface [--outmode '.implode('|',array_keys(IOInterface::OUTPUT_TYPES))."] [--clienterror|--servererror]",
            'testactlog --logtest1 alphanum --logtest2 alphanum',
            'geterrors '.ErrorLog::GetPropUsage($this->database).' '.ErrorLog::GetLoadUsage(),
            'clearerrors',
            'clearactions',
            'testdb',
            'benchdb'
        );

        return $retval;
    }
    
    public function Run(Input $input)
    {
        $params = $input->GetParams();
        
        switch ($input->GetAction())
        {
            case 'random':  return $this->Random($params);  
            case 'testiface': return $this->TestInterface($input);
            case 'testactlog': $this->TestActlog($input); return null;
            case 'geterrors': return $this->GetErrors($input->GetParams());
            case 'clearerrors': return $this->ClearErrors();
            case 'clearactions': return $this->ClearActions();
            case 'benchdb': return $this->BenchDatabase();
            case 'testdb': $this->TestDatabase(); return null;
            
            default: throw new UnknownActionException($input->GetAction());
        }
    }

    /** Returns a random string with default length 16 */
    protected function Random(SafeParams $params) : string
    {
        $length = $params->GetOptParam('length',16)->GetUint();

        return Utilities::Random($length);
    }
    
    /**
     * Returns the input given, encoding non-UTF8 as b64, exposing various options
     * @return array{dryrun:bool,params:array<string, ScalarOrArray>,
     *   files:array<array{name:string,data:string}>,auth?:array{user:string,pass:string}}
     * @throws UnknownActionException if --clienterror is true
     * @throws ServerException if --servererror is true
     */
    protected function TestInterface(Input $input) : array
    {
        $params = $input->GetParams();
        $iface = $this->API->GetInterface();

        $outmode = $params->GetOptParam('outmode',null)->FromAllowlistNull(array_keys(IOInterface::OUTPUT_TYPES));
        if ($outmode !== null) $iface->SetOutputMode(IOInterface::OUTPUT_TYPES[$outmode]);

        if ($params->GetOptParam('clienterror',false)->GetBool())
            throw new UnknownActionException($input->GetAction());
        else if ($params->GetOptParam('servererror',false)->GetBool())
            throw new ServerException('TEST_MESSAGE','some details',5000);

        $retval = array();
        $retval['dryrun'] = $iface->isDryRun();
        $retval['params'] = $params->GetClientObject();
        $retval['files'] = array_map(function(InputFile $file) 
        { 
            $data = $file->GetData();
            if (!Utilities::isUTF8($data)) 
                $data = base64_encode($data); // for json
            return array('name'=>$file->GetName(), 'data'=>$data);
        }, $input->GetFiles());
        
        if (($auth = $input->GetAuth()) !== null) $retval['auth'] = array(
            'user'=>$auth->GetUsername(), 'pass'=>base64_encode($auth->GetPassword()));

        return $retval;
    }

    /** Tests action log details level */
    protected function TestActlog(Input $input) : void
    {
        if (($actlog = $this->getActionLog()) !== null)
        {
            $input->GetFile('myfile',SafeParams::PARAMLOG_ALWAYS);
            $input->GetParams()->GetParam('logtest1',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum(); // basic/full
            $input->GetParams()->GetParam('logtest2')->GetAlphanum(); // only FULL
            $actlog->LogDetails('mytest1','mydetails1'); // basic/full
            $actlog->LogDetails('mytest2','mydetails2',true); // only FULL
        }
    }
    
    /**
     * Returns the server error log, possibly filtered by input
     * @return array<string, ErrorLogJ>
     */
    protected function GetErrors(SafeParams $params) : array
    {
        return array_map(function(ErrorLog $e){ return $e->GetClientObject(); },
            ErrorLog::LoadByParams($this->database, $params));
    }
    
    /** Clears the ErrorLog */
    protected function ClearErrors() : int
    {
        return ErrorLog::DeleteAll($this->API->GetDatabase());
    }

    /** Cleras the ActionLog */
    protected function ClearActions() : int
    {
        return BaseActionLog::DeleteAll($this->API->GetDatabase());
    }

    /** Measures the time for 10,000 JOIN/SELECTs */
    protected function BenchDatabase() : float
    {
        $start = hrtime(true);

        $db = $this->API->GetDatabase();
        for ($i = 0; $i < 10000; $i++)
            $db->LoadObjectsByQuery(ActionLog::class, new QueryBuilder()); // poly(JOIN)

        return (hrtime(true)-$start)/1e9;
    }

    /**
     * Runs a series of query tests on the database (REMOVES ActionLogs!)
     * The database unit tests are good but they don't actually interact with the underlying databases
     * so to make sure the queries are really valid, we must do some checks during integration testing
     */
    protected function TestDatabase() : void
    {
        $db = $this->API->GetDatabase();
        $iface = $this->API->GetInterface();

        $db->DeleteObjectsByQuery(BaseActionLog::class, new QueryBuilder());
        $db->DeleteObjectsByQuery(ActionLog::class, new QueryBuilder()); // poly

        $baseInput = new Input('testutil','mytest1');
        $childInput = new Input('core','mytest2');

        $countBase = 8; $countChild = 12;
        for ($i = 0; $i < $countBase; $i++)
            BaseActionLog::Create($db, $iface, $baseInput)->ForceDBSave();
        for ($i = 0; $i < $countChild; $i++)
            ActionLog::Create($db, $iface, $childInput)->ForceDBSave(); // INSERT

        if (($count = $db->CountObjectsByQuery(BaseActionLog::class, new QueryBuilder())) !== $countBase+$countChild)
            throw new ServerException("fail1 $count");
        if (($count = $db->CountObjectsByQuery(ActionLog::class, new QueryBuilder())) !== $countChild)
            throw new ServerException("fail2 $count");

        $baseObjs = $db->LoadObjectsByQuery(BaseActionLog::class, new QueryBuilder());
        if (($count = count($baseObjs)) !== $countBase+$countChild) 
            throw new ServerException("fail3 $count");
        $childObjs = $db->LoadObjectsByQuery(ActionLog::class, new QueryBuilder());
        if (($count = count($childObjs)) !== $countChild) 
            throw new ServerException("fail4 $count");

        array_values($childObjs)[0]->SetAdmin(true)->SetAuthUser("mytest")->ForceDBSave(); // UPDATE child+base

        $q = (new QueryBuilder())->Limit($lim=5); // DELETE w/subquery test
        $limObjs = $db->LoadObjectsByQuery(BaseActionLog::class, $q); $count = count($limObjs);
        if ($count !== $lim) throw new ServerException("fail5 $count");
        $count2 = $db->DeleteObjectsByQuery(BaseActionLog::class, $q); // subquery test
        if ($count !== $count2) throw new ServerException("fail6 $count $count2");

        foreach ($baseObjs as $id=>$obj) // test correct objects were deleted
        {
            $good = array_key_exists($id,$limObjs) ? $obj->isDeleted() : !$obj->isDeleted();
            if (!$good) throw new ServerException("fail7 wrong deletion");
        }

        $db->DeleteObjectsByQuery(ActionLog::class, new QueryBuilder()); // poly
        if ($db->CountObjectsByQuery(ActionLog::class, new QueryBuilder()) !== 0 ||
            count($db->LoadObjectsByQuery(ActionLog::class, new QueryBuilder())) !== 0)
            throw new ServerException("fail8 not empty");

        $db->DeleteObjectsByQuery(BaseActionLog::class, new QueryBuilder()); // poly
        if ($db->CountObjectsByQuery(BaseActionLog::class, new QueryBuilder()) !== 0 ||
            count($db->LoadObjectsByQuery(BaseActionLog::class, new QueryBuilder())) !== 0)
            throw new ServerException("fail9 not empty");

        $obj = ActionLog::Create($db, $this->API->GetInterface(), $childInput);
        $obj->ForceDBSave(); $db->DeleteObject($obj); // DELETE single
    }
}
