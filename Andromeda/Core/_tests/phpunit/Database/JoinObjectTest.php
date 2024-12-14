<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

class TestObject1 extends BaseObject
{ 
    use TableTypes\TableNoChildren;
}

class TestObject2 extends BaseObject
{ 
    use TableTypes\TableNoChildren;
}

/** @extends JoinObject<TestObject1,TestObject2> */
class TestJoinObject extends JoinObject
{
    use TableTypes\TableNoChildren;
    
    /** @var FieldTypes\ObjectRefT<TestObject1> */
    private FieldTypes\ObjectRefT $obj1;

    /** @var FieldTypes\ObjectRefT<TestObject2> */
    private FieldTypes\ObjectRefT $obj2;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->obj1 = $fields[] = new FieldTypes\ObjectRefT(TestObject1::class, 'obj1');
        $this->obj2 = $fields[] = new FieldTypes\ObjectRefT(TestObject2::class, 'obj2');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    protected function GetLeftField() : FieldTypes\ObjectRefT { return $this->obj1; }
    protected function GetRightField() : FieldTypes\ObjectRefT { return $this->obj2; }

    public function GetTestObject1() : TestObject1 { return $this->obj1->GetObject(); }
    public function GetTestObject2() : TestObject2 { return $this->obj2->GetObject(); }

    /** @return array<string, TestObject1> */
    public static function LoadTestObject1(ObjectDatabase $database, TestObject2 $obj2) : array
    {
        return static::LoadFromJoin($database, 'obj1', TestObject1::class, 'obj2', $obj2);
    }

    /** @return array<string, TestObject2> */
    public static function LoadTestObject2(ObjectDatabase $database, TestObject1 $obj1) : array
    {
        return static::LoadFromJoin($database, 'obj2', TestObject2::class, 'obj1', $obj1);
    }

    /** @return ?static */
    public static function TryLoadByJoin(ObjectDatabase $database, TestObject1 $obj1, TestObject2 $obj2) : ?self
    {
        return static::TryLoadJoinObject($database, 'obj1', $obj1, 'obj2', $obj2);
    }

    public static function DeleteByTestObject1(ObjectDatabase $database, TestObject1 $obj1) : int
    {
        return static::DeleteJoinsFrom($database, 'obj1', $obj1);
    }
    
    public static function DeleteByTestObject2(ObjectDatabase $database, TestObject2 $obj2) : int
    {
        return static::DeleteJoinsFrom($database, 'obj2', $obj2);
    }
    
    public static function Create(ObjectDatabase $database, TestObject1 $obj1, TestObject2 $obj2) : self
    {
        return parent::CreateJoin($database, $obj1, $obj2);
    }
}

class JoinObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testLoadObjects() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $obj2 = new TestObject2($objdb,array('id'=>$id2="osuid77456"),false);

        $qstr = "SELECT a2obj_core_database_testobject1.* FROM a2obj_core_database_testobject1 "
            ."JOIN a2obj_core_database_testjoinobject ON a2obj_core_database_testjoinobject.\"obj1\" = a2obj_core_database_testobject1.\"id\" WHERE \"obj2\" = :d0";

        $database->expects($this->once())->method('read')
            ->with($qstr,["d0"=>$id2])->willReturn(array(['id'=>$id="obj123"]));

        $objs = TestJoinObject::LoadTestObject1($objdb, $obj2);
        $objs = TestJoinObject::LoadTestObject1($objdb, $obj2); // cached!
        $this->assertCount(1, $objs); $obj1 = $objs[$id];
        $this->assertInstanceOf(TestObject1:: class, $obj1);
    }

    public function testLoadByJoin() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $qstr = "SELECT a2obj_core_database_testjoinobject.* FROM a2obj_core_database_testjoinobject WHERE (\"obj1\" = :d0 AND \"obj2\" = :d1)";

        $obj1 = $this->createMock(TestObject1::class);
        $obj1->method('ID')->willReturn($id1="589375897");
        $obj2 = $this->createMock(TestObject2::class);
        $obj2->method('ID')->willReturn($id2="567567");

        $database->expects($this->once())->method('read')
            ->with($qstr,["d0"=>$id1,"d1"=>$id2])->willReturn(array(['id'=>$id="obj123"]));
            
        $jobj = TestJoinObject::TryLoadByJoin($objdb, $obj1, $obj2);
        $this->assertInstanceOf(TestJoinObject:: class, $jobj);
    }

    public function testDeleteByObject() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $obj2 = new TestObject2($objdb,array('id'=>$id2="osuid7nf8"),false);

        $qstr = "DELETE FROM a2obj_core_database_testjoinobject WHERE id IN (SELECT id FROM ".
            "(SELECT a2obj_core_database_testjoinobject.id FROM a2obj_core_database_testjoinobject WHERE \"obj2\" = :d0) AS t)";

        $database->expects($this->once())->method('write')
            ->with($qstr,["d0"=>$id2])->willReturn(0);

        TestJoinObject::DeleteByTestObject2($objdb, $obj2);
    }

    public function testCreateDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $database->expects($this->exactly(2))->method('read')
            ->willReturnOnConsecutiveCalls([['id'=>$id1="tetsetstse"]],[['id'=>$id2="34534534"]]);
        $obj1 = TestObject1::TryLoadByID($objdb, $id1);
        $obj2 = TestObject2::TryLoadByID($objdb, $id2);
        assert($obj1 !== null); assert($obj2 !== null);

        $qstr1 = 'INSERT INTO a2obj_core_database_testjoinobject ("obj1","obj2","id") VALUES (:d0,:d1,:d2)';
        $qstr2 = 'DELETE FROM a2obj_core_database_testjoinobject WHERE "id" = :d0';
        $database->expects($this->exactly(2))->method('write')->withConsecutive([$qstr1],[$qstr2])->willReturn(1);

        $obj = TestJoinObject::Create($objdb, $obj1, $obj2);

        $obj->Delete(); // delete self, uncached
    }

    public function testCaching() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $obj2 = new TestObject2($objdb,array('id'=>$id2="testobject2ABC"),false);

        $qstr1 = "SELECT a2obj_core_database_testobject1.* FROM a2obj_core_database_testobject1 "
            ."JOIN a2obj_core_database_testjoinobject ON a2obj_core_database_testjoinobject.\"obj1\" = a2obj_core_database_testobject1.\"id\" WHERE \"obj2\" = :d0";
        $qstr2 = 'SELECT a2obj_core_database_testobject1.* FROM a2obj_core_database_testobject1 WHERE a2obj_core_database_testobject1."id" = :d0';
        $qstr3 = 'SELECT a2obj_core_database_testobject2.* FROM a2obj_core_database_testobject2 WHERE a2obj_core_database_testobject2."id" = :d0';
        $qstr4 = 'SELECT a2obj_core_database_testjoinobject.* FROM a2obj_core_database_testjoinobject WHERE "obj2" = :d0';

        $database->expects($this->exactly(4))->method('read')
            ->withConsecutive([$qstr1], [$qstr2], [$qstr3], [$qstr4])
            ->willReturnOnConsecutiveCalls(
                [['id'=>$id1A="testobj1A"]], // LoadTestObject1
                [['id'=>$id1B='testobj1B']], // TryLoadByID
                [['id'=>$id2]], // GetRight
                [['id'=>"testjoin",'obj1'=>$id1A,'obj2'=>$id2]], // pre-delete load
            );
        
        $database->expects($this->exactly(3))->method('write')->willReturn(1); // create, delete, delete

        $obj1s = TestJoinObject::LoadTestObject1($objdb, $obj2); // calls read
        $this->assertCount(1, $obj1s);

        $obj1A = TestObject1::TryLoadByID($objdb, $id1B); // calls read
        assert($obj1A !== null);
        $jobj = TestJoinObject::Create($objdb, $obj1A, $obj2);
        $obj1s = TestJoinObject::LoadTestObject1($objdb, $obj2); // CACHED, no read
        $this->assertCount(2, $obj1s);

        $jobj->Delete(); // calls read (GetRight)
        $obj1s = TestJoinObject::LoadTestObject1($objdb, $obj2); // CACHED, no read
        $this->assertCount(1, $obj1s);

        TestJoinObject::DeleteByTestObject2($objdb, $obj2); // calls read (select before delete) - GetRight is cached
        $obj1s = TestJoinObject::LoadTestObject1($objdb, $obj2); // CACHED, no read
        $this->assertCount(0, $obj1s);
    }
}
