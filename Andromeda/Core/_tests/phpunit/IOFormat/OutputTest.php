<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

use Andromeda\Core\Errors\BaseExceptions;

class TestClientException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("TEST_EXCEPTION", $details);
    }
}

class OutputTest extends \PHPUnit\Framework\TestCase
{
    public function testSuccess() : void
    {
        $output = Output::Success('myretval');
        
        $this->assertTrue($output->isOK());
        $this->assertSame(Output::CODE_SUCCESS ,$output->GetCode());
        $this->assertSame('myretval', $output->GetAppdata());
        
        $this->assertSame(array('ok'=>true,'code'=>Output::CODE_SUCCESS,
            'appdata'=>'myretval'), $output->GetAsArray());
    }
    
    public function testMultiSuccess() : void
    {
        $output = Output::Success(array('myretval','myretval2'));
        
        $this->assertTrue($output->isOK());
        $this->assertSame(Output::CODE_SUCCESS, $output->GetCode());
        
        $this->assertSame(array('myretval','myretval2'), $output->GetAppdata());
        
        $this->assertSame(array('ok'=>true,'code'=>Output::CODE_SUCCESS,
            'appdata'=>array('myretval','myretval2')), $output->GetAsArray());
    }
    
    public function testClientException() : void
    {
        $output = Output::ClientException(new TestClientException(), array('mydebug'))->SetMetrics(array('mymetrics'));
        
        $this->assertFalse($output->isOK());
        
        $this->assertSame(Output::CODE_CLIENT_ERROR, $output->GetCode());
        $this->assertSame('TEST_EXCEPTION', $output->GetMessage());
        
        $this->assertSame(array('ok'=>false,'code'=>Output::CODE_CLIENT_ERROR,'message'=>'TEST_EXCEPTION',
            'metrics'=>array('mymetrics'), 'debug'=>array('mydebug')), $output->GetAsArray());
    }
    
    public function testServerException() : void
    {
        $output = Output::ServerException(array('mydebug'))->SetMetrics(array('mymetrics'));
        
        $this->assertFalse($output->isOK());
        
        $this->assertSame(Output::CODE_SERVER_ERROR, $output->GetCode());
        $this->assertSame('SERVER_ERROR', $output->GetMessage());
        
        $this->assertSame(array('ok'=>false,'code'=>Output::CODE_SERVER_ERROR,'message'=>'SERVER_ERROR',
            'metrics'=>array('mymetrics'), 'debug'=>array('mydebug')), $output->GetAsArray());
    }
    
    public function testGetAsString() : void
    {
        $this->assertSame('SUCCESS', Output::Success(null)->GetAsString());
        $this->assertSame('FALSE', Output::Success(false)->GetAsString());
        $this->assertSame('TRUE', Output::Success(true)->GetAsString());
        $this->assertSame('myretval', Output::Success('myretval')->GetAsString());
        
        $arr=array('mykey'=>'myval');
        $this->assertSame(Output::Success($arr)->GetAsString(),print_r($arr,true));

        // with debug/metrics, don't narrow down to appdata
        $output = Output::Success($arr)->SetMetrics(array('mymetrics'));
        $this->assertNotSame($output->GetAsString(), print_r($arr,true)); // added metrics
        $this->assertSame($output->GetAsString(), print_r($output->GetAsArray(),true));
        // outprop still narrows appdata itself w/ metrics
        $this->assertSame($output->GetAsString('mykey'), print_r($output->GetAsArray('mykey'),true));

        $output = Output::ServerException();
        $this->assertSame($output->GetAsString(), 'ERROR: SERVER_ERROR');
        $this->assertSame($output->GetAsString('mykey'), 'ERROR: SERVER_ERROR'); // ignore outprop on error
        
        // with outprop+error+debug, ignore outprop
        $output = Output::ServerException(array('mydebug'));
        $this->assertSame($output->GetAsString('mykey'), print_r($output->GetAsArray(),true));
    }
    
    public function testOutprop() : void
    {
        $output = Output::ServerException();
        $this->assertSame(array('ok'=>false,'code'=>500,'message'=>'SERVER_ERROR'), $output->GetAsArray("key1")); // ignore outprop on error

        $appdata = array('key1'=>array('key2'=>5));
        $output = Output::Success($appdata);
        
        $this->assertSame($output->GetAsString("key1"),print_r($appdata["key1"],true));
        $this->assertSame('5', $output->GetAsString("key1.key2"));
        
        $this->assertSame(array('ok'=>true,'code'=>200,'appdata'=>$appdata['key1']), $output->GetAsArray("key1"));
        $this->assertSame(array('ok'=>true,'code'=>200,'appdata'=>5), $output->GetAsArray("key1.key2"));
        
        $this->expectException(Exceptions\InvalidOutpropException::class);
        $output->GetAsArray("key2");
    }
    
    public function testParseArrayGood() : void
    {
        $data = array('ok'=>true,'code'=>Output::CODE_SUCCESS,'appdata'=>'myretval');
        
        $output = Output::ParseArray($data);
        
        $this->assertTrue($output->isOK());
        $this->assertSame(Output::CODE_SUCCESS, $output->GetCode());
        $this->assertSame($data['appdata'], $output->GetAppdata());
        $this->assertSame($data, $output->GetAsArray());
        
        $data = array('ok'=>true,'code'=>Output::CODE_SUCCESS,'appdata'=>array('mykey',array('key'=>'val')));
        
        $output = Output::ParseArray($data);
        
        $this->assertTrue($output->isOK());
        $this->assertSame(Output::CODE_SUCCESS, $output->GetCode());
        $this->assertSame($data['appdata'], $output->GetAppdata());
        $this->assertSame($data, $output->GetAsArray());
        
        $data = array('ok'=>false,'code'=>Output::CODE_CLIENT_ERROR,'message'=>'EXCEPTION!');
        
        $this->expectException(BaseExceptions\ClientException::class);
        $this->expectExceptionCode($data['code']); 
        $this->expectExceptionMessage($data['message']);
        
        $output = Output::ParseArray($data);
    }
    
    public function testParseArrayBad1() : void
    {
        $this->expectException(Exceptions\InvalidParseException::class);
        
        Output::ParseArray(array());
    }
    
    public function testParseArrayBad2() : void
    {
        $this->expectException(Exceptions\InvalidParseException::class);
        
        Output::ParseArray(array('ok'=>true,'code'=>Output::CODE_SUCCESS));
    }
    
    public function testParseArrayBad3() : void
    {
        $this->expectException(Exceptions\InvalidParseException::class);

        Output::ParseArray(array('ok'=>false,'code'=>Output::CODE_SUCCESS,'appdata'=>true));
    }
    
    public function testParseArrayBad4() : void
    {
        $this->expectException(Exceptions\InvalidParseException::class);
        
        Output::ParseArray(array('ok'=>true,'code'=>Output::CODE_CLIENT_ERROR,'message'=>'exception'));
    }
}
