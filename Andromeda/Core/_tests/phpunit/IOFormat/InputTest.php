<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

use Andromeda\Core\{ApiPackage,Config};
use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\Logging\ActionLog;

class InputTest extends \PHPUnit\Framework\TestCase
{
    public function testAppAction() : void
    {
        $input = new Input($app='testapp', $action='testact');
        $this->assertSame($app, $input->GetApp());
        $this->assertSame($action, $input->GetAction());
    }
    
    public function testSanitizeApp() : void
    {
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        new Input('app%','action');
    }
    
    public function testSanitizeAction() : void
    {
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        new Input('app','action<');
    }
    
    public function testAuth() : void
    {
        $input = new Input('app','action');
        $this->assertSame(null, $input->GetAuth());
        
        $auth = new InputAuth('username','password');
        $input = new Input('app','action', null,null, $auth);
        $this->assertSame($auth, $input->GetAuth());
    }
    
    public function testFiles() : void
    {
        $input = new Input('app','action');
        $this->assertSame(array(), $input->GetFiles());
        
        $this->assertFalse($input->HasFile('test'));
        $this->assertNull($input->TryGetFile('test'));
        $this->assertFalse($input->GetParams()->HasParam('test'));
        
        $file1 = $this->createStub(InputStream::class);
        $file2 = $this->createStub(InputStream::class);
        
        $files = array('file1'=>$file1);
        $input = new Input('app','action',null,$files);
        $this->assertSame($files, $input->GetFiles());

        $this->assertTrue($input->HasFile('file1'));
        $this->assertSame($file1, $input->GetFile('file1'));
        $this->assertSame($file1, $input->TryGetFile('file1'));
        
        $this->assertFalse($input->HasFile('file2'));
        $this->assertNull($input->TryGetFile('file2'));
        
        $input->AddFile('file2', $file2);
        
        $files['file2'] = $file2;
        $this->assertSame($files, $input->GetFiles());
        
        $this->assertTrue($input->HasFile('file2'));
        $this->assertSame($file2, $input->GetFile('file2'));
        $this->assertSame($file2, $input->TryGetFile('file2'));
        
        $this->expectException(Exceptions\InputFileMissingException::class);
        $input->GetFile('file3');
    }
    
    public function testParams() : void
    {
        $input = new Input('app','action');
        $this->assertInstanceOf(SafeParams::class, $input->GetParams());
        $this->assertSame(array(), $input->GetParams()->GetClientObject());
        
        $params = new SafeParams();
        $input = new Input('app','action',$params);
        $this->assertSame($input->GetParams(), $params);
    }
    
    private function getLogger(int $level) : ActionLog
    {
        // it would be great if we could mock the ActionLog
        // but it seems ->willReturn() cannot return a reference
        
        $config = $this->createStub(Config::class);
        $config->method('GetActionLogDetails')->willReturn($level);
        
        $apipack = $this->createStub(ApiPackage::class);
        $apipack->method('GetConfig')->willReturn($config);
        
        $database = $this->createStub(ObjectDatabase::class);
        $database->method('GetApiPackage')->willReturn($apipack);
        
        return new ActionLog($database, array(), false);
    }
    
    public function testAuthLogger() : void
    {
        $actlog = $this->createMock(ActionLog::class);
        $actlog->expects($this->once())->method('SetAuthUser')->with($user='myuser');
        
        $auth = new InputAuth($user,'passwords123');
        $input = new Input('app','action',null,null,$auth);
        $input->SetLogger($actlog);
        
        $this->assertSame($auth, $input->GetAuth());
    }
    
    public function testFileLogger() : void
    {
        $file1 = $this->createStub(InputStream::class);
        $file2 = $this->createMock(InputPath::class);
        $file2->method('GetClientObject')->willReturn($file2arr=array('mydata'));
        
        $files = array('test1'=>$file1,'test2'=>$file2);
        $logarr = array('test1'=>null,'test2'=>$file2arr);
        $input = new Input('app','action',null,$files);
        
        $input->SetLogger($logger = $this->getLogger(0));
        $input->GetFile('test1',SafeParams::PARAMLOG_ONLYFULL);
        $input->GetFile('test2',SafeParams::PARAMLOG_ONLYFULL);
        $this->assertEmpty($logger->GetFilesLogRef());
        $input->GetFile('test1',SafeParams::PARAMLOG_ALWAYS);
        $input->GetFile('test2',SafeParams::PARAMLOG_ALWAYS);
        $this->assertEmpty($logger->GetFilesLogRef());
        
        $input->SetLogger($logger = $this->getLogger(Config::ACTLOG_DETAILS_BASIC));
        $input->GetFile('test1',SafeParams::PARAMLOG_ONLYFULL);
        $input->GetFile('test2',SafeParams::PARAMLOG_ONLYFULL);
        $this->assertEmpty($logger->GetFilesLogRef());
        $input->GetFile('test1',SafeParams::PARAMLOG_ALWAYS);
        $input->GetFile('test2',SafeParams::PARAMLOG_ALWAYS);
        $this->assertSame($logarr, $logger->GetFilesLogRef());
        
        $input->SetLogger($logger = $this->getLogger(Config::ACTLOG_DETAILS_FULL));
        $input->TryGetFile('test1',SafeParams::PARAMLOG_ONLYFULL);
        $input->TryGetFile('test2',SafeParams::PARAMLOG_ONLYFULL);
        $this->assertSame($logarr, $logger->GetFilesLogRef());
    }
    
    public function testParamLogger() : void
    {
        $logarr = array('test1'=>'abc');
        $params = (new SafeParams())->LoadArray($logarr);
        $input = new Input('app','action',$params);

        $input->SetLogger($logger = $this->getLogger(0));
        $params->GetParam('test1',SafeParams::PARAMLOG_ONLYFULL)->GetAlphanum();
        $this->assertEmpty($logger->GetParamsLogRef());
        $params->GetParam('test1',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        $this->assertEmpty($logger->GetParamsLogRef());
        
        $input->SetLogger($logger = $this->getLogger(Config::ACTLOG_DETAILS_BASIC));
        $params->GetParam('test1',SafeParams::PARAMLOG_ONLYFULL)->GetAlphanum();
        $this->assertEmpty($logger->GetParamsLogRef());
        $params->GetParam('test1',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        $this->assertSame($logarr, $logger->GetParamsLogRef());
        
        $input->SetLogger($logger = $this->getLogger(Config::ACTLOG_DETAILS_FULL));
        $params->GetParam('test1',SafeParams::PARAMLOG_ONLYFULL)->GetAlphanum();
        $this->assertSame($logarr, $logger->GetParamsLogRef());
    }
}
