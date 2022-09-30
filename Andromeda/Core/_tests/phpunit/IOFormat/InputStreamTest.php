<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

class InputStreamTest extends \PHPUnit\Framework\TestCase
{
    public function testStream() : void
    {
        $data = "testing123";
        $stream = fopen("data:text/plain,$data",'rb');
        assert(is_resource($stream));
        $strobj = new InputStream($stream);
        
        $this->assertSame($stream, $strobj->GetHandle());
        $this->assertSame($data, $strobj->GetData());
        $this->assertFalse(is_resource($stream)); // @phpstan-ignore-line stream is closed
        
        $this->expectException(Exceptions\FileReadFailedException::class);
        $strobj->GetHandle(); // should be closed
    }
    
    /** @depends testStream */
    public function testStreamDestruct() : void
    {
        $stream = fopen("data:text/plain,",'rb');
        assert(is_resource($stream));
        
        $strobj = new InputStream($stream);
        $strobj->__destruct();
        
        $this->assertFalse(is_resource($stream)); // @phpstan-ignore-line stream is closed
    }
}
