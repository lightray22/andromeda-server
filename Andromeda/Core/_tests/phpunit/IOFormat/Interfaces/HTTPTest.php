<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; require_once("init.php");

use Andromeda\Core\Utilities;
use Andromeda\Core\IOFormat\{Input, InputAuth, Output, SafeParam, SafeParams};

class HTTPTest extends \PHPUnit\Framework\TestCase
{
    public function testStatics() : void
    {
        $this->assertFalse(HTTP::isPrivileged()); // @phpstan-ignore-line test anyway
        $this->assertFalse(HTTP::isInteractive()); // @phpstan-ignore-line test anyway
        $this->assertSame(HTTP::OUTPUT_JSON, HTTP::GetDefaultOutmode());
        
        $http = new HTTP();
        $this->assertFalse($http->isDryRun());
        $this->assertNull($http->GetDBConfigFile());
        $this->assertSame(0, $http->GetDebugLevel());
        $this->assertSame(0, $http->GetMetricsLevel());
    }

    public function testMethod() : void
    {
        $this->expectException(Exceptions\MethodNotAllowedException::class);
        (new HTTP())->LoadHTTPInput([], [], [], ['REQUEST_METHOD'=>'PUT']);
    }
    
    public function testMissingApp() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInput([], ['_app'=>'myapp'], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testMissingAction() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInput([], ['_act'=>'myact'], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testAppActionStrings() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInput([], ['_app'=>'myapp','_act'=>[]], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testAppActionGet() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInput(['_app'=>'myapp','_act'=>'myact'], [], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testIllegalGetField() : void
    {
        $this->expectException(Exceptions\IllegalGetFieldException::class);
        (new HTTP())->LoadHTTPInput([], ['_app'=>'myapp','_act'=>'myact','password'=>'test'], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testBadBase64Field() : void
    {
        $this->expectException(Exceptions\Base64DecodeException::class);
        (new HTTP())->LoadHTTPInput([], ['_app'=>'myapp','_act'=>'myact'], [], ['REQUEST_METHOD'=>'GET',"HTTP_X_ANDROMEDA_XYZ"=>"bad!"]);
    }
    
    public function testBasicInput() : void
    {
        $get = array('_app'=>$app='myapp', '_act'=>$act='myact');
        $req = array('password'=>'test', 'param2'=>['0'=>'0','1'=>'1'], 'obj'=>['0'=>'2','3'=>'4']);
        
        $server = array('REQUEST_TIME'=>0,'HTTP_USER_AGENT'=>"abc","HTTP_X_ANDROMEDA_XYZ"=>base64_encode("123"));
        $input = (new HTTP())->LoadHTTPInput($req+$get, $get, [], $server+['REQUEST_METHOD'=>'POST']);
        
        $this->assertSame($app, $input->GetApp());
        $this->assertSame($act, $input->GetAction());
        
        $params = $input->GetParams();
        $obj = $params->GetParam('obj')->GetObject();
        
        $this->assertSame([0,1], $params->GetParam('param2')->GetArray(
            function(SafeParam $p){ return $p->GetInt(); }));
        
        $this->assertSame(2, $obj->GetParam('0')->GetInt());
        $this->assertSame(4, $obj->GetParam('3')->GetInt());
        
        $this->assertSame(123, $params->GetParam('xyz')->GetInt());
        $this->assertSame($req+["xyz"=>"123"], $params->GetClientObject());
    }
    
    public function testGetAuth() : void
    {
        $get = array('_app'=>'test','_act'=>'act1'); $req = $get;        
        $server = array('REQUEST_METHOD'=>'GET','PHP_AUTH_USER'=>$user='myuser','PHP_AUTH_PW'=>$pass='mypass');
        $input = (new HTTP())->LoadHTTPInput($req, $get, array(), $server); // can't test files...
        
        $this->assertSame('test',$input->GetApp());        
        $this->assertSame('act1',$input->GetAction());
        
        $auth = $input->GetAuth(); assert($auth instanceof InputAuth);
        $this->assertSame($user,$auth->GetUsername());
        $this->assertSame($pass,$auth->GetPassword());
        $this->assertSame($auth,$input->GetAuth());
    }
    
    public function testFiles() : void
    {
        // can't really test file input due to is_uploaded_file() check
        $this->expectException(Exceptions\FileUploadFailException::class);
        
        $files = array('myfile'=>array('tmp_name'=>'test.txt','name'=>'test.txt','error'=>0));
        (new HTTP())->LoadHTTPInput([], ['_app'=>'myapp','_act'=>'myact'], $files, ['REQUEST_METHOD'=>'GET']);
    }
    
    /** @param ?array<mixed> $arrval */
    protected function testOutput(HTTP $iface, int $mode, ?string $strval, ?array $arrval, string $want) : void
    {
        $iface->SetOutputMode($mode);
        $output = $this->createStub(Output::class);
        if ($strval !== null) $output->method('GetAsString')->willReturn($strval);
        if ($arrval !== null) $output->method('GetAsArray')->willReturn($arrval);
        
        $output = Utilities::CaptureOutput(function()use($iface,$output){
            $iface->FinalOutput($output,true); }); // no header output
        $this->assertSame($want,$output);
    }
    
    public function testFinalOutput() : void
    {
        $iface = new HTTP();
        
        $this->testOutput($iface, 0, $str='mystring', null, '');
        $this->testOutput($iface, HTTP::OUTPUT_PLAIN, $str, null, $str);
        $arr=[1,2,3,4]; 
        $this->testOutput($iface, HTTP::OUTPUT_PLAIN, print_r($arr,true), null, print_r($arr,true));
        $this->testOutput($iface, HTTP::OUTPUT_PRINTR, null, $arr, print_r($arr,true));
        $this->testOutput($iface, HTTP::OUTPUT_JSON, $str='[1,2,3]', $arr=[1,2,3], $str);
    }

    public function testRemoteURL() : void
    {
        $params = (new SafeParams())->LoadArray(['test'=>5,'arr'=>['test2'=>7]]);
        $input = new Input('myapp','myact', $params);
        
        $base = "http://test.com/index.php";
        $appact = "_app=myapp&_act=myact";
        $paramstr = "test=5&arr%5Btest2%5D=7";
        
        $this->assertSame("?$appact", 
            HTTP::GetRemoteURL("", $input, false));
        
        $this->assertSame("?$appact&$paramstr",
            HTTP::GetRemoteURL("", $input, true));
        
        $this->assertSame("$base?$appact",
            HTTP::GetRemoteURL($base, $input, false));
        
        $this->assertSame("$base?yo=6&$appact&$paramstr",
            HTTP::GetRemoteURL("$base?yo=6", $input, true));
    }
}
