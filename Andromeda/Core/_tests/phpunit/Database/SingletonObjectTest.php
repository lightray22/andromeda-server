<?php declare(strict_types=1); namespace Andromeda\Core\Database; require_once("init.php");

class TestSingleton1 extends SingletonObject 
{
    use TableTypes\TableNoChildren; 
}

class TestSingleton2 extends SingletonObject 
{
    use TableTypes\TableNoChildren;
}

class SingletonObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testMissing() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        
        $this->expectException(Exceptions\SingletonNotFoundException::class);
        
        TestSingleton1::GetInstance($database);
    }
    
    public function testCreate() : void
    {
        $database = $this->createMock(ObjectDatabase::class);

        $database->expects($this->exactly(0))->method('TryLoadUniqueByKey');
        
        $obj = new TestSingleton2($database, array(), true);
        
        $this->assertInstanceOf(TestSingleton2::class, $obj);
        
        $this->assertSame($obj, TestSingleton2::GetInstance($database));
        $this->assertSame($obj, TestSingleton2::GetInstance($database));
    }
    
    public function testLoad() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        new TestSingleton2($database, array(), true);
        
        // only one read because TestSingleton2 was created above
        $database->expects($this->exactly(1))->method('TryLoadUniqueByKey')
            ->willReturn(new TestSingleton1($database, array('id'=>$id='A')));
        
        $obj1 = TestSingleton1::GetInstance($database);
        $obj2 = TestSingleton2::GetInstance($database);
        
        $this->assertSame($obj1, TestSingleton1::GetInstance($database));
        $this->assertSame($obj2, TestSingleton2::GetInstance($database));
        
        $this->assertInstanceOf(TestSingleton1::class, $obj1);
        $this->assertInstanceOf(TestSingleton2::class, $obj2);
        
        $this->assertSame($id, $obj1->ID());
        $this->assertSame($id, $obj2->ID());
    }
}
