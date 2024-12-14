<?php declare(strict_types=1); namespace Andromeda\Core; require_once("init.php");

class VersionInfoTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic() : void
    {
        $version = new VersionInfo($v="3.2");
        
        $this->assertSame($version->getMajor(), 3);
        $this->assertSame($version->getMinor(), 2);
        $this->assertNull($version->getPatch());
        $this->assertNull($version->getExtra());
        $this->assertSame((string)$version, "3.2");
        
        $this->assertSame($version->getCompatVer(), '3.2');
        $this->assertSame(VersionInfo::toCompatVer($v), '3.2');
    }
    
    public function testFull() : void
    {
       $version = new VersionInfo($v="3.2.1-alpha");
       
       $this->assertSame($version->getMajor(), 3);
       $this->assertSame($version->getMinor(), 2);
       $this->assertSame($version->getPatch(), 1);
       $this->assertSame($version->getExtra(), 'alpha');
       $this->assertSame((string)$version, "3.2.1-alpha");
       
       $this->assertSame($version->getCompatVer(), '3.2');
       $this->assertSame(VersionInfo::toCompatVer($v), '3.2');
    }
    
    public function testNumeric() : void
    {
       $this->expectException(Exceptions\InvalidVersionException::class);
       new VersionInfo("3.a.2-alpha");
    }
    
    public function testMajorMinor() : void
    {
       $this->expectException(Exceptions\InvalidVersionException::class);
       new VersionInfo("5");
    }
}
