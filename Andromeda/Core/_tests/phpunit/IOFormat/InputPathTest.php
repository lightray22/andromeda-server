<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

class InputPathTest extends \PHPUnit\Framework\TestCase
{
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
    
    public function testBasic() : void
    {
        $fpath = $this->getTmpFile();
        
        $fobj = new InputPath($fpath);
        $this->assertSame($fpath, $fobj->GetPath());
        $this->assertSame(basename($fpath), $fobj->GetName());
        $this->assertFalse($fobj->isTemp());
        
        $handle = $fobj->GetHandle();
        $fobj->__destruct();
        $this->assertFalse(is_resource($handle));
        
        $this->assertSame(array('name'=>basename($fpath),'path'=>$fpath,'size'=>0), $fobj->GetClientObject());
    }
    
    /** @depends testBasic */
    public function testFull() : void
    {
        $data = "Testing123";
        $name = "other name.txt";
        $fpath = $this->getTmpFile();
        file_put_contents($fpath, $data);
        
        $fobj = new InputPath($fpath, $name, true);
        $this->assertSame($fpath, $fobj->GetPath());
        $this->assertSame($name, $fobj->GetName());
        $this->assertTrue($fobj->isTemp());
        
        $this->assertSame(strlen($data), $fobj->GetSize());
        
        for ($i = 0; $i < 5; $i++) // files work more than once
        {
            $handle = $fobj->GetHandle();
            $this->assertSame($data, fread($handle, strlen($data)));
            $this->assertSame($data, $fobj->GetData());
        }
        
        $this->assertSame(array('name'=>$name,'path'=>$fpath,'size'=>strlen($data)), $fobj->GetClientObject());
    }
}
