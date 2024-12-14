<?php declare(strict_types=1); namespace Andromeda\Apps\Core; require_once("init.php");

use Andromeda\Core\InstallerApp;

class CoreInstallAppTest extends \PHPUnit\Framework\TestCase
{
    public function testHasDependency() : void
    {
        $a = $this->createMock(InstallerApp::class);
        $a->method('getName')->willReturn('a');
        
        $b = $this->createMock(InstallerApp::class);
        $b->method('getName')->willReturn('b');
        $b->method('getDependencies')->willReturn(array('d'));
        
        $c = $this->createMock(InstallerApp::class);
        $c->method('getName')->willReturn('c');
        $c->method('getDependencies')->willReturn(array('e'));
        
        $d = $this->createMock(InstallerApp::class);
        $d->method('getName')->willReturn('d');
        $d->method('getDependencies')->willReturn(array('c','e'));
        
        $e = $this->createMock(InstallerApp::class);
        $e->method('getName')->willReturn('e');
        $e->method('getDependencies')->willReturn(array('a'));
        
        $installers = array('a'=>$a, 'b'=>$b, 'c'=>$c, 'd'=>$d, 'e'=>$e);
        
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $a, $b));
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $a, $c));
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $a, $d));
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $a, $e));
        
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $b, $a)); // B->D->E->A
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $b, $c)); // B->D->C
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $b, $d)); // B->D
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $b, $e)); // B->D->E
        
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $c, $a)); // C->E->A
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $c, $b));
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $c, $d));
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $c, $e)); // C->E
        
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $d, $a)); // D->E->A
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $d, $b));
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $d, $c)); // D->C
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $d, $e)); // D->E
        
        $this->assertTrue(CoreInstallApp::HasDependency($installers, $e, $a)); // E->A
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $e, $b));
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $e, $c));
        $this->assertFalse(CoreInstallApp::HasDependency($installers, $e, $d));
    }

    public function testSortInstallers() : void
    {
        $a = $this->createMock(InstallerApp::class);
        $a->method('getName')->willReturn('a');
        
        $b = $this->createMock(InstallerApp::class);
        $b->method('getName')->willReturn('b');
        $b->method('getDependencies')->willReturn(array('d'));
        
        $c = $this->createMock(InstallerApp::class);
        $c->method('getName')->willReturn('c');
        $c->method('getDependencies')->willReturn(array('e'));
        
        $d = $this->createMock(InstallerApp::class);
        $d->method('getName')->willReturn('d');
        $d->method('getDependencies')->willReturn(array('c','e'));
        
        $e = $this->createMock(InstallerApp::class);
        $e->method('getName')->willReturn('e');
        $e->method('getDependencies')->willReturn(array('a'));
        
        $installers = array('a'=>$a, 'b'=>$b, 'c'=>$c, 'd'=>$d, 'e'=>$e);
        
        // D->B, E->C, C->D, E->D, A->E ... expect A E C D B
        $expect = array('a','e','c','d','b');
        $installers = CoreInstallApp::SortInstallers($installers);
        
        $this->assertSame($expect, array_keys($installers));
        $this->assertSame($expect, array_map(function(InstallerApp $i){ 
            return $i->getName(); },array_values($installers)));
    }
}
