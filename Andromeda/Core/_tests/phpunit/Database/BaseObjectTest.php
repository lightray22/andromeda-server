<?php declare(strict_types=1); namespace Andromeda\Core\Database; require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

class BaseObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testGetUniqueKeys() : void
    {
        $this->assertSame(array(
            EasyObject::class => array('id','uniqueKey')
        ), EasyObject::GetUniqueKeys());
        
        $this->assertSame(array(
            PolyObject1::class => array('id'),
            PolyObject5a::class => array('testprop5')
        ), PolyObject5aa::GetUniqueKeys());
    }
    
    public function testGetRowClass() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $this->assertSame(PolyObject4::class, PolyObject4::GetRowClass($database,array('type'=>5)));
        $this->assertSame(PolyObject5a::class, PolyObject4::GetRowClass($database,array('type'=>13)));
        
        $this->expectException(Exceptions\BadPolyTypeException::class);
        PolyObject4::GetRowClass($database,array('type'=>99));
    }
    
    public function testGetWhereChild() : void
    {
        $db = new ObjectDatabase($this->createMock(PDODatabase::class));
        
        $q = new QueryBuilder();
        $this->assertSame("a2obj_core_database_polyobject4.type = :d0", 
            PolyObject4::GetWhereChild($db, $q, PolyObject4::class));
        $this->assertSame(array('d0'=>5), $q->GetParams());
        
        $q = new QueryBuilder();
        $this->assertSame("a2obj_core_database_polyobject4.type = :d0",
            PolyObject4::GetWhereChild($db, $q, PolyObject5a::class));
        $this->assertSame(array('d0'=>13), $q->GetParams());
        
        $q = new QueryBuilder();
        $this->expectException(Exceptions\BadPolyClassException::class);
        PolyObject4::GetWhereChild($db, $q, PolyObject3::class);
    }
    
    public function testGetTableClasses() : void
    {
        $this->assertSame(array(EasyObject::class), 
            EasyObject::GetTableClasses());
        
        $this->assertSame(array(
            PolyObject1::class, PolyObject2::class,
            PolyObject4::class, PolyObject5a::class
        ), PolyObject5a::GetTableClasses());
        
        $this->assertSame(EasyObject::class, EasyObject::GetBaseTableClass());
        $this->assertSame(PolyObject1::class, PolyObject5a::GetBaseTableClass());
        
        $this->expectException(Exceptions\NoBaseTableException::class);
        PolyObject0::GetBaseTableClass();
    }
    
    public function testBasic() : void
    {
        // test Create, construct, ID()
        $database = $this->createMock(ObjectDatabase::class);
        $database->expects($this->once())->method('CreateObject')
            ->willReturnCallback(function()use($database){ return new EasyObject($database,array(),true) ;});
        
        $obj = EasyObject::Create($database);
        $this->assertTrue($obj->isCreated());
        
        $this->assertInstanceOf(EasyObject::class, $obj);
        $this->assertSame(12, strlen($obj->ID()));
        
        $this->assertTrue($obj->didPostConstruct());
    }
    
    public function testToString() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $obj = new EasyObject($database,array(),true); $id = $obj->ID();
        
        $str = "$id:Andromeda\\Core\\Database\\EasyObject";
        $this->assertSame($str, BaseObject::toString($obj));
        $this->assertSame($str, "$obj");
    }

    public function testDeleteLater() : void
    {
        // test Create, construct, ID()
        $database = $this->createMock(ObjectDatabase::class);
        $obj = new EasyObject($database,array(),true); $id = $obj->ID();
        $this->assertTrue($obj->isCreated());

        $database->expects($this->once())->method('SaveObject');
        $database->expects($this->once())->method('DeleteObject');

        $obj->Save(); // regular save
        $this->assertFalse($obj->isCreated());

        $obj->DeleteMeLater();
        $obj->Save(); // deletes
    }

    public function testLoadByID() : void
    {
        $db = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($db);

        $id = "test123";
        $db->expects($this->exactly(1))->method('read')->with( // NOTE id is ambiguous so it has the table name
            "SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE a2obj_core_database_easyobject.\"id\" = :d0",
            array('d0'=>$id))->willReturn([['id'=>$id]]);

        $obj = EasyObject::TryLoadByID($objdb, $id);
        assert($obj !== null);
        $this->assertFalse($obj->isCreated());
    }

    public function testLoadAll() : void
    {
        $db = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($db);

        $limit = 5; $offset = 7;
        $db->expects($this->exactly(2))->method('read')->withConsecutive(
            ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject ", array()],
            ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject LIMIT $limit OFFSET $offset", array()])->willReturn([]);

        EasyObject::LoadAll($objdb);
        EasyObject::LoadAll($objdb, $limit, $offset);
    }
}
