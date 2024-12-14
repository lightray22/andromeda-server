<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

use Andromeda\Core\Config;

class SafeParamsTest extends \PHPUnit\Framework\TestCase
{
    public function testHasParam() : void
    {
        $obj = new SafeParams();
        $obj->AddParam('test','value');
        
        $this->assertTrue($obj->HasParam('test'));
        $this->assertSame('value',$obj->GetParam('test')->GetRawString());
        $this->assertSame('value',$obj->GetOptParam('test','def')->GetRawString());
    }
    
    public function testNotHasParam() : void
    {
        $obj = new SafeParams();
        
        $this->assertFalse($obj->HasParam('test'));

        $this->assertSame(null,$obj->GetOptParam('test',null)->GetNullRawString());
        $this->assertSame(null,$obj->GetOptParam('test',null)->GetNullBool());
        
        $this->assertSame(0,$obj->GetOptParam('test',0)->GetInt());
        $this->assertSame(1,$obj->GetOptParam('test',1)->GetInt());
        
        $this->assertSame(false,$obj->GetOptParam('test',false)->GetBool());
        $this->assertSame(false,$obj->GetOptParam('test',false)->GetNullBool());
        $this->assertSame(true,$obj->GetOptParam('test',true)->GetBool());
        $this->assertSame(true,$obj->GetOptParam('test',true)->GetNullBool());
        
        $this->assertSame('test',$obj->GetOptParam('test','test')->GetRawString());
        
        $this->expectException(Exceptions\SafeParamKeyMissingException::class);
        $obj->GetParam('test');
    }

    public function testGetAllRawValues() : void
    {       
        $obj = (new SafeParams())->AddParam('test1','75')->AddParam('test2',['57','99'])
            ->AddParam('test3',(new SafeParams())->AddParam('test3b',250));
        $this->assertSame(array('test1'=>'75','test2'=>['57','99'],'test3'=>['test3b'=>250]), $obj->GetAllRawValues());
    }
    
    public function testGetClientObject() : void
    {
        $obj = (new SafeParams())->AddParam('test',hex2bin('deadbeef00fe')); // base64 non-utf8
        $this->assertSame(array('test'=>'3q2+7wD+'), $obj->GetClientObject());
    }

    public function testLoadArray() : void
    {
        $arr = array('test1'=>75, 'test2'=>77, 'myarr'=>array(5,6,7,8), 'myobj'=>array('t1'=>5,'t2'=>7));
        $obj = (new SafeParams())->LoadArray($arr);
        $this->assertSame($arr, $obj->GetAllRawValues());
    }
    
    protected function isLogged(int $level, int $minlog) : bool
    {
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, $level); assert(is_array($log));
        $obj->AddParam('test','55')->GetParam('test',$minlog)->GetInt();
        $log1 = array_key_exists('test', $log) && $log['test'] === 55;
        
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, $level); assert(is_array($log));
        $obj->AddParam('test','55')->GetOptParam('test',false,$minlog)->GetInt();
        $log2 = array_key_exists('test', $log) && $log['test'] === 55;
        
        $this->assertSame($log1, $log2); return $log1;
    }
    
    public function testLogging() : void
    {
        $this->assertTrue($this->isLogged(Config::ACTLOG_DETAILS_FULL, SafeParams::PARAMLOG_ALWAYS));
        $this->assertTrue($this->isLogged(Config::ACTLOG_DETAILS_FULL, SafeParams::PARAMLOG_ONLYFULL));
        $this->assertFalse($this->isLogged(Config::ACTLOG_DETAILS_FULL, SafeParams::PARAMLOG_NEVER));
        
        $this->assertTrue($this->isLogged(Config::ACTLOG_DETAILS_BASIC, SafeParams::PARAMLOG_ALWAYS));
        $this->assertFalse($this->isLogged(Config::ACTLOG_DETAILS_BASIC, SafeParams::PARAMLOG_ONLYFULL));
        $this->assertFalse($this->isLogged(Config::ACTLOG_DETAILS_BASIC, SafeParams::PARAMLOG_NEVER));
        
        $this->assertFalse($this->isLogged(0, SafeParams::PARAMLOG_ALWAYS));
        $this->assertFalse($this->isLogged(0, SafeParams::PARAMLOG_ONLYFULL));
        $this->assertFalse($this->isLogged(0, SafeParams::PARAMLOG_NEVER));
    }
    
    public function testNestedLog() : void
    {
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, Config::ACTLOG_DETAILS_FULL);
        
        $obj->AddParam('obj','{"test":75}')->GetParam('obj')->GetObject()->GetParam('test')->GetInt();
        
        $this->assertSame(array('obj'=>array('test'=>75)), $log);
    }
}
