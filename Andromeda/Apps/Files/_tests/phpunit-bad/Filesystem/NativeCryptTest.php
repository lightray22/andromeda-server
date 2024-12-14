<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem; require_once("init.php");

use Andromeda\Core\{Crypto, Utilities};
use Andromeda\Core\IOFormat\InputPath;
require_once(ROOT."/Apps/Files/Storage/Storage.php"); use Andromeda\Apps\Files\Storage\Storage;
require_once(ROOT."/Apps/Files/File.php"); use Andromeda\Apps\Files\File;

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php");

class NativeCryptTest extends \PHPUnit\Framework\TestCase
{
    private array $paths = array(); // array of FS path -> memory buffer
    private array $sizes = array(); // array of file ID -> file size 
    
    private NativeCrypt $fsimpl;
    
    public function SetUp() : void
    {        
        $storage = $this->createMock(Storage::class);
        
        $storage->method('CreateFile')->will($this->returnCallback(
            function(string $path)use($storage)
        {
            $this->paths[$path] = fopen("php://memory",'rb+');
            
            return $storage;
        }));
        
        $storage->method('ReadBytes')->will($this->returnCallback(
            function(string $path, int $start, int $length)
        {
            $handle = $this->paths[$path];            
            fseek($handle, $start);
            
            return fread($handle, $length);
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
            ftruncate($handle, $length);  
            
            return $storage;
        }));
        
        $filesystem = $this->createMock(FSManager::class);
        $filesystem->method('GetStorage')->willReturn($storage);
        
        $this->fsimpl = new NativeCrypt($filesystem, Crypto::GenerateSecretKey(), self::CHUNK_SIZE);
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
    
    protected function createFile(string $data) : File
    {
        $file = $this->createMock(File::class);
        
        $id = Utilities::Random(20);
        $file->method('ID')->willReturn($id);
        
        $path = self::getTmpFile(); file_put_contents($path, $data);
        
        $file->method('SetSize')->will($this->returnCallback(
            function(int $size,bool $notify)use($file)
        {
            if (!$notify) $this->fsimpl->Truncate($file, $size);
            
            $this->sizes[$file->ID()] = $size; return $file;
        }));
        
        $file->method('GetSize')->will($this->returnCallback(
            function()use($file) { return $this->sizes[$file->ID()]; }));
        
        $infile = new InputPath($path);
        
        $this->fsimpl->ImportFile($file->SetSize(strlen($data),true), $infile);
        
        return $file;
    }
    
    protected function tryWriting(int $size0, int $offset, int $wlen) : void
    {
        // first import a mock file and check its contents
        
        $data = Utilities::Random($size0);        
        $file = $this->createFile($data);
        
        $ret = $this->fsimpl->ReadBytes($file, 0, $size0);        
        $this->assertSame($data, $ret);
        
        // next write to the file and check its new contents
        // if offset0 > size0, WriteBytes runs SetSize (Truncate)
        
        $size = max($size0, $offset + $wlen);        
        if ($size > $size0) $data = str_pad($data, $size, "\0");
        
        $wdata = Utilities::Random($wlen);
        
        $data = substr_replace($data, $wdata, $offset, strlen($wdata));
        $this->fsimpl->WriteBytes($file, $offset, $wdata); 
        $file->SetSize($size,true);

        $ret = $this->fsimpl->ReadBytes($file, 0, $size);
        $this->assertSame($data, $ret);
        
        if ($size > $size0) // test shrinking back to size0
        {
            $data = substr($data, 0, $size0);
            $this->fsimpl->Truncate($file, $size0); 
            $file->SetSize($size0, true);
            
            $ret = $this->fsimpl->ReadBytes($file, 0, $size0);
            $this->assertSame($data, $ret);
        }
    }
    
    protected function tryWritingPair(int $size0, int $size) : void
    {
        $this->tryWriting($size, (int)($size-$size0)/2, $size0); // import, write inside file
        $this->tryWriting($size, $size0, $size);                 // import, write from inside EOF, shrink
        $this->tryWriting($size0, $size, $size);                 // import, extend, write at EOF, shrink
    }   
    
    private const CHUNK_SIZE = 10; // bytes
    
    public function testTruncateZeroes() : void
    {
        $this->tryWriting(0, 0, 0);
        $this->tryWritingPair(0, 1);
        $this->tryWritingPair(0, 5);
        $this->tryWritingPair(0, 9);
        $this->tryWritingPair(0, 10);
        $this->tryWritingPair(0, 11);
        $this->tryWritingPair(0, 55);
        $this->tryWritingPair(0, 59);
        $this->tryWritingPair(0, 60);
        $this->tryWritingPair(0, 61);
    }
    
    public function testTruncateWithinChunk() : void
    {
        $this->tryWritingPair(3, 3);
        $this->tryWritingPair(3, 8);
        $this->tryWritingPair(3, 10);
        $this->tryWritingPair(9, 10);
        $this->tryWritingPair(43, 46);
        $this->tryWritingPair(11, 18);
        $this->tryWritingPair(11, 20);
        $this->tryWritingPair(14, 20);
    }
    
    public function testTruncateOneChunk() : void
    {
        $this->tryWritingPair(8, 11);
        $this->tryWRitingPair(10, 16);
        $this->tryWritingPair(11, 25);
        $this->tryWritingPair(14, 25);
        $this->tryWritingPair(19, 25);
        $this->tryWritingPair(20, 25);
        $this->tryWritingPair(41, 59);
        $this->tryWritingPair(47, 59);
        $this->tryWritingPair(49, 59);
    }
    
    public function testTruncateManyChunks() : void
    {
        $this->tryWritingPair(3, 50);
        $this->tryWritingPair(9, 50);
        $this->tryWritingPair(10, 77);
        $this->tryWritingPair(14, 77);
        $this->tryWritingPair(19, 77);
        $this->tryWritingPair(40, 59);
        $this->tryWritingPair(40, 99);
        $this->tryWritingPair(41, 99);
        $this->tryWritingPair(47, 99);
        $this->tryWritingPair(49, 99);
    }
}
