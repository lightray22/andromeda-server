<?php declare(strict_types=1); namespace Andromeda\Core\Errors\BaseExceptions; require_once("init.php");

use Andromeda\Core\IOFormat\Output;

class TestClientException extends ClientException
{
    public function __construct(?string $details = null) 
    {
        parent::__construct("TEST_CLIENT_EXCEPTION", 111, $details);
    }
    
    /** @return $this */
    public function CopyException(BaseException $e, bool $newloc = true) : self
    {
        return parent::CopyException($e, $newloc);
    }
    
    /** @return $this */
    public function AppendException(\Exception $e, bool $newloc = true) : self
    {
        return parent::AppendException($e, $newloc);
    }
}

class TestServerException extends ServerException
{
    public function __construct(?string $details = null)
    {
        parent::__construct("TEST_SERVER_EXCEPTION", $details);
    }
    
    /** @return $this */
    public function CopyException(BaseException $e, bool $newloc = true) : self
    {
        return parent::CopyException($e, $newloc);
    }
    
    /** @return $this */
    public function AppendException(\Exception $e, bool $newloc = true) : self
    {
        return parent::AppendException($e, $newloc);
    }
}

class BaseExceptionsTest extends \PHPUnit\Framework\TestCase
{
    public function testClientExceptions() : void
    {
        $ex = new ClientException($msg="MY_MESSAGE", $code=999, $det="test details"); $line = __LINE__;
        $this->assertSame($code, $ex->getCode());
        $this->assertSame("$msg: $det", $ex->getMessage());
        $this->assertSame(__FILE__, $ex->getFile());
        $this->assertSame($line, $ex->getLine());
        
        $ex = new ClientErrorException();
        $this->assertSame(Output::CODE_CLIENT_ERROR, $ex->getCode());
        $this->assertSame("INVALID_REQUEST", $ex->getMessage());
        
        $ex = new ClientDeniedException();
        $this->assertSame(Output::CODE_CLIENT_DENIED, $ex->getCode());
        $this->assertSame("ACCESS_DENIED", $ex->getMessage());
        
        $ex = new ClientNotFoundException();
        $this->assertSame(Output::CODE_CLIENT_NOTFOUND, $ex->getCode());
        $this->assertSame("NOT_FOUND", $ex->getMessage());
        
        $ex = new NotImplementedException();
        $this->assertSame(Output::CODE_SERVER_UNIMPLEMENTED, $ex->getCode());
        $this->assertSame("NOT_IMPLEMENTED", $ex->getMessage());
        
        $ex = new ServiceUnavailableException();
        $this->assertSame(Output::CODE_SERVER_UNAVAILABLE, $ex->getCode());
        $this->assertSame("SERVICE_UNAVAILABLE", $ex->getMessage());
        
        $ex = new ClientErrorException($msg="MY_MESSAGE", $det="test details");
        $this->assertSame(Output::CODE_CLIENT_ERROR, $ex->getCode());
        $this->assertSame("$msg: $det", $ex->getMessage());
        
        $ex = new ClientDeniedException($msg="MY_MESSAGE", $det="test details");
        $this->assertSame(Output::CODE_CLIENT_DENIED, $ex->getCode());
        $this->assertSame("$msg: $det", $ex->getMessage());
        
        $ex = new ClientNotFoundException($msg="MY_MESSAGE", $det="test details");
        $this->assertSame(Output::CODE_CLIENT_NOTFOUND, $ex->getCode());
        $this->assertSame("$msg: $det", $ex->getMessage());
        
        $ex = new NotImplementedException($msg="MY_MESSAGE", $det="test details");
        $this->assertSame(Output::CODE_SERVER_UNIMPLEMENTED, $ex->getCode());
        $this->assertSame("$msg: $det", $ex->getMessage());
        
        $ex = new ServiceUnavailableException($msg="MY_MESSAGE", $det="test details");
        $this->assertSame(Output::CODE_SERVER_UNAVAILABLE, $ex->getCode());
        $this->assertSame("$msg: $det", $ex->getMessage());
    }
    
    public function testServerExceptions() : void
    {
        $ex = new ServerException($msg="MY_MESSAGE"); $line = __LINE__;
        $this->assertSame(0, $ex->getCode()); // default
        $this->assertSame($msg, $ex->getMessage());
        $this->assertSame(__FILE__, $ex->getFile());
        $this->assertSame($line, $ex->getLine());
        
        $ex = new ServerException($msg="MY_MESSAGE", $det="test details", $code=999);
        $this->assertSame($code, $ex->getCode());
        $this->assertSame("$msg: $det", $ex->getMessage());
        
        $ex = new PHPError($code=999, $msg="MY_MESSAGE", $file="test.php", $line=69);
        $this->assertSame($code, $ex->getCode());
        $this->assertSame($msg, $ex->getMessage());
        $this->assertSame($file, $ex->getFile());
        $this->assertSame($line, $ex->getLine());
    }
    
    public function testClientFromException() : void
    {
        $code2 = 111; $msg2 = "TEST_CLIENT_EXCEPTION";
        
        $ex1 = new ServerException($msg1="MY_MESSAGE", $det1="test details1", 999);
        $ex2 = new TestClientException($det2="test details2");
        $ex2->CopyException($ex1);
        $this->assertSame($code2, $ex2->getCode()); // NOT copied
        $this->assertSame("$msg1: $det1", $ex2->getMessage());
        $this->assertSame($ex2->getLine(), $ex1->getLine());
        
        $ex1 = new ServerException($msg1="MY_MESSAGE", $det1="test details1", 999);
        $ex2 = new TestClientException($det2="test details2");
        $ex2->AppendException($ex1, false);
        $this->assertSame($code2, $ex2->getCode()); // NOT copied
        $this->assertSame("$msg2: $det2: $msg1: $det1", $ex2->getMessage());
        $this->assertNotSame($ex2->getLine(), $ex1->getLine());
    }
    
    public function testServerFromException() : void
    {
        $msg2 = "TEST_SERVER_EXCEPTION";
        
        $ex1 = new ClientException($msg1="MY_MESSAGE", $code1=999, $det1="test details1");
        $ex2 = new TestServerException($det2="test details2");
        $ex2->CopyException($ex1, false);
        $this->assertSame($code1, $ex2->getCode()); // is copied
        $this->assertSame("$msg1: $det1", $ex2->getMessage());
        $this->assertNotSame($ex2->getLine(), $ex1->getLine());
        
        $ex1 = new ClientException($msg1="MY_MESSAGE", $code1=999, $det1="test details1");
        $ex2 = new TestServerException($det2="test details2");
        $ex2->AppendException($ex1);
        $this->assertSame($code1, $ex2->getCode()); // is copied
        $this->assertSame("$msg2: $det2: $msg1: $det1", $ex2->getMessage());
        $this->assertSame($ex2->getLine(), $ex1->getLine());
    }
}
