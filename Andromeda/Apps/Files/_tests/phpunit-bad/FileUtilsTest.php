<?php declare(strict_types=1); namespace Andromeda\Apps\Files; require_once("init.php");

use Andromeda\Core\Utilities;

class FileUtilsTest extends \PHPUnit\Framework\TestCase
{
    public function testChunkSize() : void
    {
        $this->assertSame(0, FileUtils::GetChunkSize(0, null));
        $this->assertSame(100, FileUtils::GetChunkSize(100, null));
        $this->assertSame(100, FileUtils::GetChunkSize(100, 100));
        
        // FS chunk size is bigger (unusual)
        $this->assertSame(120, FileUtils::GetChunkSize(100, 120));
        $this->assertSame(150, FileUtils::GetChunkSize(100, 150));
        $this->assertSame(250, FileUtils::GetChunkSize(100, 250));
        
        // RW chunk size is bigger (normal) - output 2x
        $this->assertSame(200, FileUtils::GetChunkSize(101, 100));
        $this->assertSame(200, FileUtils::GetChunkSize(150, 100));
        $this->assertSame(200, FileUtils::GetChunkSize(199, 100));
        $this->assertSame(200, FileUtils::GetChunkSize(200, 100));
        
        // RW chunksize is bigger - output 3x-higher
        $this->assertSame(300, FileUtils::GetChunkSize(201, 100));
        $this->assertSame(300, FileUtils::GetChunkSize(300, 100));
        $this->assertSame(600, FileUtils::GetChunkSize(501, 100));
        $this->assertSame(600, FileUtils::GetChunkSize(600, 100));
    }    
    
    protected function tryChunkedRead(int $datasize, int $offset, int $length, int $chunksize, bool $align, array $reads) : void
    {
        $file = $this->createMock(File::class);
        
        $data = Utilities::Random($datasize);
        
        $file->method('ReadBytes')->will($this->returnCallback(
            function(int $byte, int $len)use($data){ return substr($data,$byte,$len); }));
        
        $file->expects($this->exactly(count($reads)))->method('ReadBytes')->withConsecutive(...$reads);
        
        $output = Utilities::CaptureOutput(function()use($file,$offset,$length,$chunksize,$align){ 
            FileUtils::DoChunkedRead($file, $offset, $offset+$length-1, $chunksize, $align, false); });
        
        $this->assertSame($output, substr($data,$offset,$length));
    }
    
    public function testChunkedRead() : void
    {
        // base zero cases
        $this->tryChunkedRead(0, 0, 0, 1, false, array());
        $this->tryChunkedRead(0, 0, 0, 1, true, array());
        $this->tryChunkedRead(0, 0, 0, 100, false, array());
        $this->tryChunkedRead(0, 0, 0, 100, true, array());
        
        // test single chunk, not aligned
        $this->tryChunkedRead(100, 0, 5, 10, false, array([0,5]));
        $this->tryChunkedRead(100, 5, 5, 10, false, array([5,5]));
        $this->tryChunkedRead(100, 50, 6, 8, false, array([50,6]));
        
        // test single chunk -> double chunk aligned
        $this->tryChunkedRead(100, 8, 5, 10, false, array([8,5]));
        $this->tryChunkedRead(100, 8, 5, 10, true, array([8,2],[10,3]));
        
        // test multi chunk not aligned
        $this->tryChunkedRead(100, 3, 29, 10, false, array([3,10],[13,10],[23,9]));
        $this->tryChunkedRead(100, 3, 30, 10, false, array([3,10],[13,10],[23,10]));
        $this->tryChunkedRead(100, 3, 31, 10, false, array([3,10],[13,10],[23,10],[33,1]));
        $this->tryChunkedRead(100, 3, 32, 10, false, array([3,10],[13,10],[23,10],[33,2]));
        
        // test multi chunk aligned
        $this->tryChunkedRead(100, 0, 30, 10, true, array([0,10],[10,10],[20,10]));
        $this->tryChunkedRead(100, 0, 31, 10, true, array([0,10],[10,10],[20,10],[30,1]));        
        $this->tryChunkedRead(100, 5, 30, 10, true, array([5,5],[10,10],[20,10],[30,5]));
        $this->tryChunkedRead(100, 9, 37, 12, true, array([9,3],[12,12],[24,12],[36,10]));
        $this->tryChunkedRead(100, 33, 37, 12, true, array([33,3],[36,12],[48,12],[60,10]));
    }
    
    protected function tryChunkedWrite(int $fsize, int $offset, int $length, int $chunksize, bool $align, array $writes) : void
    {
        $fdata0 = Utilities::Random($fsize); // original file data
        $wdata = Utilities::Random($length); // data to write
        
        $fdata1 = substr_replace($fdata0,$wdata,$offset,strlen($wdata)); // resulting output
        
        $whandle = fopen("php://memory",'rb+');
        fwrite($whandle, $wdata); fseek($whandle, 0);
        
        // map the expected write lengths to the actual data argument
        foreach ($writes as &$write)
            $write[1] = substr($wdata, $write[0]-$offset, $write[1]);
        
        $file = $this->createMock(File::class);
        
        $file->method('WriteBytes')->will($this->returnCallback(
            function(int $offset2, string $wdata2)use(&$fdata0,$file)
            { $fdata0 = substr_replace($fdata0, $wdata2, $offset2, strlen($wdata2)); return $file; }
        ));
        
        $file->expects($this->exactly(count($writes)))->method('WriteBytes')->withConsecutive(...$writes);
        
        $written = FileUtils::DoChunkedWrite($whandle, $file, $offset, $chunksize, $align); 
        
        $this->assertSame($length, $written);
        $this->assertSame($fdata0, $fdata1);
    }
    
    public function testChunkedWrite() : void
    {
        // base zero cases
        $this->tryChunkedWrite(0, 0, 0, 1, true, array());
        
        // test single chunk, not aligned
        $this->tryChunkedWrite(0, 0, 5, 10, false, array([0,5]));
        $this->tryChunkedWrite(3, 0, 5, 10, false, array([0,5]));
        $this->tryChunkedWrite(5, 5, 5, 10, false, array([5,5]));
        $this->tryChunkedWrite(12, 5, 5, 10, false, array([5,5]));   
        $this->tryChunkedWrite(6, 5, 10, 10, false, array([5,10]));
        
        // test single chunk -> double chunk aligned
        $this->tryChunkedWrite(100, 8, 10, 10, false, array([8,10]));
        $this->tryChunkedWrite(100, 8, 10, 10, true, array([8,2],[10,8]));
        
        // test multi chunk not aligned
        $this->tryChunkedWrite(100, 8, 45, 10, false, array([8,10],[18,10],[28,10],[38,10],[48,5]));
        $this->tryChunkedWrite(100, 10, 45, 10, false, array([10,10],[20,10],[30,10],[40,10],[50,5]));
        $this->tryChunkedWrite(100, 15, 45, 10, false, array([15,10],[25,10],[35,10],[45,10],[55,5]));        
        
        // test multi chunk aligned
        $this->tryChunkedWrite(100, 8, 35, 10, true, array([8,2],[10,10],[20,10],[30,10],[40,3]));
        $this->tryChunkedWrite(100, 10, 35, 10, true, array([10,10],[20,10],[30,10],[40,5]));
        $this->tryChunkedWrite(100, 13, 35, 10, true, array([13,7],[20,10],[30,10],[40,8]));
        $this->tryChunkedWrite(100, 29, 31, 10, true, array([29,1],[30,10],[40,10],[50,10]));  
        $this->tryChunkedWrite(100, 30, 31, 10, true, array([30,10],[40,10],[50,10],[60,1]));  
        $this->tryChunkedWrite(100, 29, 32, 10, true, array([29,1],[30,10],[40,10],[50,10],[60,1]));
    }    
}
