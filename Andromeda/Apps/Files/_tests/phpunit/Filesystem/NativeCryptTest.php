<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem; require_once("init.php");

use Andromeda\Core\{Crypto, Utilities};
use Andromeda\Core\IOFormat\InputPath;
use Andromeda\Apps\Files\Storage\Storage;
use Andromeda\Apps\Files\Items\File;

class NativeCryptTest extends \PHPUnit\Framework\TestCase
{
    /** @var array<string, resource> */
    private array $paths = array(); // array of FS path -> memory buffer
    /** @var array<string, int> */
    private array $sizes = array(); // array of file ID -> file size 
    
    private NativeCrypt $filesystem;
    
    public function setUp() : void
    {        
        $storage = $this->createMock(Storage::class);
        
        $storage->method('CreateFile')->will($this->returnCallback(
            function(string $path)use($storage)
        {
            $handle = fopen("php://memory",'rb+');
            assert(is_resource($handle));
            $this->paths[$path] = $handle;
            return $storage;
        }));
        
        $storage->method('ReadBytes')->will($this->returnCallback(
            function(string $path, int $start, int $length)
        {
            $handle = $this->paths[$path];            
            fseek($handle, $start);
            return ($length > 0) ? fread($handle, $length) : "";
        }));
        
        $storage->method('WriteBytes')->will($this->returnCallback(
            function(string $path, int $start, string $data)use($storage)
        {
            $handle = $this->paths[$path];
            fseek($handle, $start);            
            fwrite($handle, $data);  
            return $storage;
        }));
        
        $storage->method('Truncate')->will($this->returnCallback(
            function(string $path, int $length)use($storage)
        {
            $handle = $this->paths[$path];            
            ftruncate($handle, $length); // @phpstan-ignore-line length > 0
            return $storage;
        }));
        
        $this->filesystem = new NativeCrypt($storage,
            Crypto::GenerateSecretKey(), self::CHUNK_SIZE);
    }
    
    /** @var list<string> */
    private array $files = array();
    
    public function tearDown() : void
    {
        foreach ($this->files as $file) @unlink($file);
    }
    
    protected function getTmpFile() : string
    {
        $file = tempnam(sys_get_temp_dir(), 'a2test');
        assert(is_string($file));
        return $this->files[] = $file;
    }

    /** Create a mock file that implements ID/GetSize/SetSize */
    protected function createFile() : File
    {
        $file = $this->createMock(File::class);
        
        $id = Utilities::Random(20);
        $file->method('ID')->willReturn($id);
        
        $file->method('SetSize')->will($this->returnCallback(
            function(int $size,bool $notify)use($file)
        {
            assert($notify);
            assert($size >= 0);
            $this->sizes[$file->ID()] = $size;
            return $file;
        }));
        
        $file->method('GetSize')->will($this->returnCallback(
            function()use($file) { return $this->sizes[$file->ID()]; }));
        
        return $file;
    }

    /** 
     * Creates data of the given size and does ImportFile on that file
     * @param non-negative-int $size 
     */
    protected function randomImport(File $file, int $size) : string
    {
        $path = self::getTmpFile();
        $data = Utilities::Random($size);        
        file_put_contents($path, $data);
        
        $this->filesystem->ImportFile($file, new InputPath($path));
        $file->SetSize(strlen($data),notify:true);
        return $data;
    }

    /** 
     * Tests CopyFile with a file of the given size
     * @param non-negative-int $size 
     */
    protected function tryCopyFile(int $size) : void
    {
        $file0 = $this->createFile();
        $data = $this->randomImport($file0, $size);

        $file1 = $this->createFile();
        $this->filesystem->CopyFile($file0, $file1);
        $file1->SetSize($size,notify:true);
        
        $ret = $this->filesystem->ReadBytes($file1, 0, $size);
        $this->assertSame($data, $ret);
    }

    /**
     * Tests writing to an encrypted file, checking results with ReadBytes()
     * 1) Tests ImportFile by importing a plaintext file of length size0
     * 2) Tests WriteBytes(offset,wlen) on the encrypted file
     * 3) Tests Truncate() shrinking the file back to its original size (if it grew)
     * @param non-negative-int $size0 initial size of file
     * @param non-negative-int $offset offset to test writing to
     * @param non-negative-int $wlen length of data to try writing
     */
    protected function tryWriting(int $size0, int $offset, int $wlen) : void
    {
        // first import a mock file and check its contents
        $file = $this->createFile();
        $data = $this->randomImport($file, $size0);

        $ret = $this->filesystem->ReadBytes($file, 0, $size0);
        $this->assertSame($data, $ret);
        
        // next write to the file and check its new contents
        // if offset0 > size0, WriteBytes runs SetSize (Truncate)
        
        $size = max($size0, $offset + $wlen);        
        if ($size > $size0) $data = str_pad($data, $size, "\0");
        
        $wdata = Utilities::Random($wlen);
        
        $data = substr_replace($data, $wdata, $offset, strlen($wdata));
        $this->filesystem->WriteBytes($file, $offset, $wdata); 
        $file->SetSize($size,notify:true);

        $ret = $this->filesystem->ReadBytes($file, 0, $size);
        $this->assertSame($data, $ret);
        
        // test shrinking back to size0
        if ($size > $size0) 
        {
            $data = substr($data, 0, $size0);
            $this->filesystem->Truncate($file, $size0); 
            $file->SetSize($size0,notify:true);
            
            $ret = $this->filesystem->ReadBytes($file, 0, $size0);
            $this->assertSame($data, $ret);
        }
    }
    
    private const CHUNK_SIZE = 10; // bytes, assumed below
    
    public function testCopyFile() : void
    {
        $tests = [0, 1, 8, 9, 10, 11, 19, 20, 21, 25, 29, 30, 31, 99, 100];
        foreach ($tests as $size) $this->tryCopyFile($size);
    }
    
    /** test tryWriting with various zero-cases */
    public function testZeroes() : void
    {
        $this->tryWriting(size0:0, offset:0,wlen:0);
        // see comment below about general pattern

        $this->tryWriting(size0:1, offset:0,wlen:0);
        $this->tryWriting(size0:1, offset:0,wlen:1);
        $this->tryWriting(size0:0, offset:1,wlen:1);

        $this->tryWriting(size0:5, offset:2,wlen:0);
        $this->tryWriting(size0:5, offset:0,wlen:5);
        $this->tryWriting(size0:0, offset:5,wlen:5);

        $this->tryWriting(size0:9, offset:4,wlen:0);
        $this->tryWriting(size0:9, offset:0,wlen:9);
        $this->tryWriting(size0:0, offset:9,wlen:9);

        $this->tryWriting(size0:10, offset:5,wlen:0);
        $this->tryWriting(size0:10, offset:0,wlen:10);
        $this->tryWriting(size0:0, offset:10,wlen:10);

        $this->tryWriting(size0:11, offset:5,wlen:0);
        $this->tryWriting(size0:11, offset:0,wlen:11);
        $this->tryWriting(size0:0, offset:11,wlen:11);

        $this->tryWriting(size0:55, offset:27,wlen:0);
        $this->tryWriting(size0:55, offset:0,wlen:55);
        $this->tryWriting(size0:0, offset:55,wlen:55);

        $this->tryWriting(size0:59, offset:29,wlen:0);
        $this->tryWriting(size0:59, offset:0,wlen:59);
        $this->tryWriting(size0:0, offset:59,wlen:59);

        $this->tryWriting(size0:60, offset:30,wlen:0);
        $this->tryWriting(size0:60, offset:0,wlen:60);
        $this->tryWriting(size0:0, offset:60,wlen:60);

        $this->tryWriting(size0:61, offset:30,wlen:0);
        $this->tryWriting(size0:61, offset:0,wlen:61);
        $this->tryWriting(size0:0, offset:61,wlen:61);
    }
    
    public function testWithinChunk() : void
    {
        $this->tryWriting(size0:3, offset:0,wlen:3);
        $this->tryWriting(size0:3, offset:3,wlen:3);
        $this->tryWriting(size0:3, offset:3,wlen:3);

        // import, write inside file
        $this->tryWriting(size0:8, offset:2,wlen:3);
        // import, write starting inside EOF, expand, shrink
        $this->tryWriting(size0:8, offset:3,wlen:8); 
        // import, extend, write starting at EOF, shrink
        $this->tryWriting(size0:3, offset:8,wlen:8); 

        $this->tryWriting(size0:10, offset:3,wlen:3);
        $this->tryWriting(size0:10, offset:3,wlen:10);
        $this->tryWriting(size0:3, offset:10,wlen:10);

        $this->tryWriting(size0:10, offset:0,wlen:9);
        $this->tryWriting(size0:10, offset:9,wlen:10);
        $this->tryWriting(size0:9, offset:10,wlen:10);

        $this->tryWriting(size0:46, offset:1,wlen:43);
        $this->tryWriting(size0:46, offset:43,wlen:46);
        $this->tryWriting(size0:43, offset:46,wlen:46);

        $this->tryWriting(size0:18, offset:3,wlen:11);
        $this->tryWriting(size0:18, offset:11,wlen:18);
        $this->tryWriting(size0:11, offset:18,wlen:18);

        $this->tryWriting(size0:20, offset:4,wlen:11);
        $this->tryWriting(size0:20, offset:11,wlen:20);
        $this->tryWriting(size0:11, offset:20,wlen:20);

        $this->tryWriting(size0:20, offset:3,wlen:14);
        $this->tryWriting(size0:20, offset:14,wlen:20);
        $this->tryWriting(size0:14, offset:20,wlen:20);
    }
    
    public function testOneChunk() : void
    {
        $this->tryWriting(size0:11, offset:1,wlen:8);
        $this->tryWriting(size0:11, offset:8,wlen:11);
        $this->tryWriting(size0:8, offset:11,wlen:11);

        $this->tryWriting(size0:16, offset:3,wlen:10);
        $this->tryWriting(size0:16, offset:10,wlen:16);
        $this->tryWriting(size0:10, offset:16,wlen:16);

        $this->tryWriting(size0:25, offset:7,wlen:11);
        $this->tryWriting(size0:25, offset:11,wlen:25);
        $this->tryWriting(size0:11, offset:25,wlen:25);

        $this->tryWriting(size0:25, offset:5,wlen:14);
        $this->tryWriting(size0:25, offset:14,wlen:25);
        $this->tryWriting(size0:14, offset:25,wlen:25);

        $this->tryWriting(size0:25, offset:3,wlen:19);
        $this->tryWriting(size0:25, offset:19,wlen:25);
        $this->tryWriting(size0:19, offset:25,wlen:25);

        $this->tryWriting(size0:25, offset:2,wlen:20);
        $this->tryWriting(size0:25, offset:20,wlen:25);
        $this->tryWriting(size0:20, offset:25,wlen:25);

        $this->tryWriting(size0:59, offset:9,wlen:41);
        $this->tryWriting(size0:59, offset:41,wlen:59);
        $this->tryWriting(size0:41, offset:59,wlen:59);

        $this->tryWriting(size0:59, offset:6,wlen:47);
        $this->tryWriting(size0:59, offset:47,wlen:59);
        $this->tryWriting(size0:47, offset:59,wlen:59);

        $this->tryWriting(size0:59, offset:5,wlen:49);
        $this->tryWriting(size0:59, offset:49,wlen:59);
        $this->tryWriting(size0:49, offset:59,wlen:59);
    }
    
    public function testManyChunks() : void
    {
        $this->tryWriting(size0:50, offset:23,wlen:3);
        $this->tryWriting(size0:50, offset:3,wlen:50);
        $this->tryWriting(size0:3, offset:50,wlen:50);

        $this->tryWriting(size0:50, offset:20,wlen:9);
        $this->tryWriting(size0:50, offset:9,wlen:50);
        $this->tryWriting(size0:9, offset:50,wlen:50);

        $this->tryWriting(size0:77, offset:33,wlen:10);
        $this->tryWriting(size0:77, offset:10,wlen:77);
        $this->tryWriting(size0:10, offset:77,wlen:77);

        $this->tryWriting(size0:77, offset:31,wlen:14);
        $this->tryWriting(size0:77, offset:14,wlen:77);
        $this->tryWriting(size0:14, offset:77,wlen:77);

        $this->tryWriting(size0:77, offset:29,wlen:19);
        $this->tryWriting(size0:77, offset:19,wlen:77);
        $this->tryWriting(size0:19, offset:77,wlen:77);

        $this->tryWriting(size0:59, offset:9,wlen:40);
        $this->tryWriting(size0:59, offset:40,wlen:59);
        $this->tryWriting(size0:40, offset:59,wlen:59);

        $this->tryWriting(size0:99, offset:29,wlen:40);
        $this->tryWriting(size0:99, offset:40,wlen:99);
        $this->tryWriting(size0:40, offset:99,wlen:99);

        $this->tryWriting(size0:99, offset:29,wlen:41);
        $this->tryWriting(size0:99, offset:41,wlen:99);
        $this->tryWriting(size0:41, offset:99,wlen:99);

        $this->tryWriting(size0:99, offset:26,wlen:47);
        $this->tryWriting(size0:99, offset:47,wlen:99);
        $this->tryWriting(size0:47, offset:99,wlen:99);

        $this->tryWriting(size0:99, offset:25,wlen:49);
        $this->tryWriting(size0:99, offset:49,wlen:99);
        $this->tryWriting(size0:49, offset:99,wlen:99);
    }
}
