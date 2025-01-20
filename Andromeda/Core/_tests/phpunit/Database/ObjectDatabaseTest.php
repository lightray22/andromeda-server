<?php declare(strict_types=1); namespace Andromeda\Core\Database; require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

use Andromeda\Core\ApiPackage;

class ObjectDatabaseTest extends \PHPUnit\Framework\TestCase
{
    public function testGetClassTable() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $this->assertSame(
            'a2obj_core_database_polyobject1',
            $objdb->GetClassTableName(PolyObject1::class));
        
        $database = $this->createMock(PDODatabase::class);
        $database->method('UsePublicSchema')->willReturn(true);
        $objdb = new ObjectDatabase($database);
        
        $this->assertSame(
            'public.a2obj_core_database_polyobject1',
            $objdb->GetClassTableName(PolyObject1::class));
    }
    
    public function testApiPackage() : void
    {
        $db = $this->createMock(PDODatabase::class);
        $apipack = $this->createMock(ApiPackage::class);
        
        $objdb = new ObjectDatabase($db);
        $objdb->SetApiPackage($apipack);
        $this->assertTrue($objdb->HasApiPackage());
        $this->assertSame($apipack, $objdb->GetApiPackage());
        
        $objdb = new ObjectDatabase($db);
        $this->assertFalse($objdb->HasApiPackage());
        
        $this->expectException(Exceptions\ApiPackageException::class);
        $objdb->GetApiPackage();
    }

    private const select1 = array(
        'SELECT a2obj_core_database_polyobject1.*, a2obj_core_database_polyobject2.*, a2obj_core_database_polyobject4.*, a2obj_core_database_polyobject5a.* '.
        'FROM a2obj_core_database_polyobject1 '.
        'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
        'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
        'JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id '.
        'WHERE "testprop1" > :d0', array('d0'=>3));
        
    private const select2 = array(
        'SELECT a2obj_core_database_polyobject1.*, a2obj_core_database_polyobject2.*, a2obj_core_database_polyobject4.* '.
        'FROM a2obj_core_database_polyobject1 '.
        'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
        'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
        'WHERE ("testprop1" > :d0 AND a2obj_core_database_polyobject4.type = :d1)', array('d0'=>3, 'd1'=>18));
    
    private const select3 = array(
        'SELECT a2obj_core_database_polyobject1.*, a2obj_core_database_polyobject2.*, a2obj_core_database_polyobject4.* '.
        'FROM a2obj_core_database_polyobject1 '.
        'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
        'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
        'WHERE ("testprop1" > :d0 AND a2obj_core_database_polyobject4.type = :d1)', array('d0'=>3, 'd1'=>5));
    
    private const id1 = 'testid1234';
    private const id2 = 'testid4567';
    
    private const row1 = array(
        /*1*/'id'=>self::id1,'testprop1'=>5,'type'=>27, // @phpstan-ignore-line intentional redefinition
        /*2*/'id'=>self::id1,'testprop15'=>15,'type'=>27,
        /*4*/'id'=>self::id1,'testprop4'=>41,'type'=>13, 
        /*5*/'id'=>self::id1,'testprop5'=>7,'type'=>101); // PolyObject5aa
    
    private const row2 = array(
        /*1*/'id'=>self::id2,'testprop1'=>10,'type'=>27, // @phpstan-ignore-line intentional redefinition
        /*2*/'id'=>self::id2,'testprop15'=>16,'type'=>27, 
        /*4*/'id'=>self::id2,'testprop4'=>42,'type'=>18); // PolyObject5b

    public function testLoadByQuery() : void
    {
        // loading by every base class should yield the same results!
        foreach (array(PolyObject0::class, PolyObject1::class, PolyObject2::class, PolyObject3::class, PolyObject4::class) as $class)
        {
            $database = $this->createMock(PDODatabase::class);
            $objdb = new ObjectDatabase($database);
            
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
            
            $rows = array();
            $rows[0] = self::row2; $rows[0]['type'] = 18;  // 5b
            $rows[1] = self::row2; $rows[1]['type'] = 5;   // 4
            $rows[2] = self::row1; $rows[2]['type'] = 100; // 5a
            $rows[3] = self::row2; $rows[3]['type'] = 18;  // 5b
            $rows[4] = self::row1; $rows[4]['type'] = 102; // 5ab
            $rows[5] = self::row1; $rows[5]['type'] = 101; // 5aa
            $rows[6] = self::row2; $rows[6]['type'] = 5;   // 4
            $rows[7] = self::row1; $rows[7]['type'] = 101; // 5aa
            $rows[8] = self::row2; $rows[8]['type'] = 18;  // 5b
            $rows[9] = self::row1; $rows[9]['type'] = 102; // 5ab
            
            foreach ($rows as $idx=>&$arr)
            {
                $arr['id'] = $arr['id'].$idx;
                $arr['testprop4'] = $idx;
            }
            
            $database->expects($this->exactly(3))->method('read')
                ->withConsecutive(
                    self::select1, // PolyObject5a/5aa/5ab
                    self::select2, // PolyObject5b (4 type)
                    self::select3) // PolyObject4 (4 type)
                ->willReturnOnConsecutiveCalls(
                    [$rows[2], $rows[4], $rows[5], $rows[7], $rows[9]],
                    [$rows[0], $rows[3], $rows[8]],
                    [$rows[1], $rows[6]]);
                
            $objs = $objdb->LoadObjectsByQuery($class, $q);
            $this->assertSame(count($rows), count($objs));
            
            foreach ($rows as $idx=>$row)
            {
                $id = $row['id']; $obj = $objs[$id];
                $this->assertInstanceOf(PolyObject4::class, $obj);
                assert($obj instanceof PolyObject4); // @phpstan-ignore-line test anyway
                
                $this->assertSame($idx, $obj->GetTestProp4());
                
                if ($obj instanceof PolyObject5a)
                {
                    $this->assertSame(5, $obj->GetTestProp1());
                    $this->assertSame(15, $obj->GetTestProp15());
                    $this->assertSame(7, $obj->GetTestProp5());
                }
                else
                {
                    $this->assertSame(10, $obj->GetTestProp1());
                    $this->assertSame(16, $obj->GetTestProp15());
                }
            }
            
            $this->assertSame(PolyObject5b::class,  get_class($objs[$rows[0]['id']]));
            $this->assertSame(PolyObject4::class,   get_class($objs[$rows[1]['id']]));
            $this->assertSame(PolyObject5a::class,  get_class($objs[$rows[2]['id']]));
            $this->assertSame(PolyObject5b::class,  get_class($objs[$rows[3]['id']]));
            $this->assertSame(PolyObject5ab::class, get_class($objs[$rows[4]['id']]));
            $this->assertSame(PolyObject5aa::class, get_class($objs[$rows[5]['id']]));
            $this->assertSame(PolyObject4::class,   get_class($objs[$rows[6]['id']]));
            $this->assertSame(PolyObject5aa::class, get_class($objs[$rows[7]['id']]));
            $this->assertSame(PolyObject5b::class,  get_class($objs[$rows[8]['id']]));
            $this->assertSame(PolyObject5ab::class, get_class($objs[$rows[9]['id']]));
        }
    }

    public function testObjectIdentity() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $id = 'testid5678';
        
        $qstr = 'SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject ';
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                [ $qstr, array() ],
                [ $qstr, array() ]
                )
            ->willReturnOnConsecutiveCalls(
                array(array('id'=>$id)),
                array(array('id'=>$id))
            );
        
        $objs = $objdb->LoadObjectsByQuery(EasyObject::class, $q);
        $this->assertSame(1, count($objs)); $obj = $objs[$id];
        
        $objs2 = $objdb->LoadObjectsByQuery(EasyObject::class, $q);
        $this->assertSame(1, count($objs2)); $obj2 = $objs2[$id];
        
        // Test that loading the same object twice does not reconstruct it
        $this->assertSame($obj, $obj2);
    }

    public function testCountByQuery() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $database->method('FullSelectFields')->willReturn(true);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $q->Where($q->Equals('mytest',5));
        
        $qstr1 = 'SELECT COUNT(a2obj_core_database_polyobject1.id) FROM a2obj_core_database_polyobject1 WHERE "mytest" = :d0';
        
        $qstr2 = 'SELECT COUNT(a2obj_core_database_polyobject1.id) FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id WHERE "mytest" = :d0';
        
        $qstr4 = 'SELECT COUNT(a2obj_core_database_polyobject1.id) FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id WHERE "mytest" = :d0';
        
        $qstr5a = 'SELECT COUNT(a2obj_core_database_polyobject1.id) FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
            'JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id WHERE "mytest" = :d0';
        
        $qstr5b = 'SELECT COUNT(a2obj_core_database_polyobject1.id) FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
            'WHERE ("mytest" = :d0 AND a2obj_core_database_polyobject4.type = :d1)';
        
        $database->expects($this->exactly(7))->method('read')
            ->withConsecutive(
                [ $qstr1, array('d0'=>5) ],
                [ $qstr1, array('d0'=>5) ],
                [ $qstr2, array('d0'=>5) ],
                [ $qstr4, array('d0'=>5) ],
                [ $qstr4, array('d0'=>5) ],
                [ $qstr5a, array('d0'=>5) ],
                [ $qstr5b, array('d0'=>5,'d1'=>18) ],
             )
            ->willReturn([array('COUNT(a2obj_core_database_polyobject1.id)'=>1)]);
            
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject0::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject1::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject2::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject3::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject4::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject5a::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject5b::class, $q));
    }
    
    public function testInsertObject() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = PolyObject5a::Create($objdb);
        
        $id = $obj->ID();
        // not steting TestProp1, default null
        $obj->SetTestProp15(15); // part of 2's table!
        $obj->SetTestProp4(100); 
        $obj->SetTestProp4n(null); // set null (default!)
        $obj->DeltaTestProp4c(5); // counter

        $database->expects($this->exactly(4))->method('write')
            ->withConsecutive(
                [ 'INSERT INTO a2obj_core_database_polyobject1 ("id") VALUES (:d0)', array('d0'=>$id) ],
                [ 'INSERT INTO a2obj_core_database_polyobject2 ("testprop15","id") VALUES (:d0,:d1)', array('d0'=>15,'d1'=>$id) ],
                [ 'INSERT INTO a2obj_core_database_polyobject4 ("type","testprop4","testprop4c","id") VALUES (:d0,:d1,:d2,:d3)', array('d0'=>13,'d1'=>100,'d2'=>5,'d3'=>$id) ],
                [ 'INSERT INTO a2obj_core_database_polyobject5a ("type","id") VALUES (:d0,:d1)', array('d0'=>100,'d1'=>$id) ]
            )->willReturn(1);
        
        $obj->Save();
    }
    
    public function testUpdateObject() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = new PolyObject5a($objdb, array('id'=>($id='testid123'),'testprop1'=>5,'testprop4'=>6,'testprop4n'=>6,'testprop5'=>7));
        
        $database->expects($this->exactly(3))->method('write')
            ->withConsecutive(
                [ 'UPDATE a2obj_core_database_polyobject5a SET "testprop5"=:d0 WHERE id=:id', array('d0'=>11,'id'=>$id) ],
                [ 'UPDATE a2obj_core_database_polyobject4 SET "testprop4"=:d0, "testprop4n"=NULL, "testprop4c"="testprop4c"+:d1 WHERE id=:id', array('d0'=>10,'d1'=>23,'id'=>$id) ],
                [ 'UPDATE a2obj_core_database_polyobject2 SET "testprop15"=:d0 WHERE id=:id', array('d0'=>15,'id'=>$id) ],
            )->willReturn(1);
            
        $obj->Save(); // nothing
        $obj->SetTestProp15(15); // 2's table
        $obj->SetTestProp4(10);
        $obj->SetTestProp4n(null); // set NULL
        $obj->DeltaTestProp4c(23); // counter +=
        $obj->SetTestProp5(11);
        $obj->Save();
        $obj->Save(); // nothing
    }

    public function testSaveObject() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        $obj = EasyObject::Create($objdb); $id = $obj->ID();
        
        $database->expects($this->exactly(3))->method('write')->withConsecutive(
            [ 'INSERT INTO a2obj_core_database_easyobject ("id") VALUES (:d0)', array('d0'=>$id) ],
            [ 'UPDATE a2obj_core_database_easyobject SET "generalKey"=:d0 WHERE id=:id', array('d0'=>55,'id'=>$id) ],
            [ 'DELETE FROM a2obj_core_database_easyobject WHERE "id" = :d0', array('d0'=>$id) ]
        )->willReturn(1);
        
        $obj->Save(true); // nothing
        $obj->Save(); // insert
        $obj->Save(); // nothing
        $obj->SetGeneralKey(55);
        $obj->Save(); // update

        $obj->Delete();   
        $this->assertTrue($obj->didPreDelete());
        $this->assertTrue($obj->isDeleted());
        
        $this->expectException(Exceptions\SaveAfterDeleteException::class);
        $obj->Save(); // throws exception
    }
    
    public function testSaveAllObjects() : void
    {   
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $obj1 = PolyObject5a::Create($objdb); $id1 = $obj1->ID(); $obj1->SetTestProp4(100);
        $obj2 = new PolyObject5a($objdb, array('id'=>($id2='testid1234'),'testprop1'=>5,'testprop4'=>101,'testprop5'=>7)); $obj2->SetTestProp1(55);
        $obj3 = new PolyObject5a($objdb, array('id'=>($id3='testid5678'),'testprop1'=>15,'testprop4'=>102,'testprop5'=>17)); $obj3->SetTestProp1(65);
        $obj4 = PolyObject5ab::Create($objdb); $id4 = $obj4->ID(); $obj4->SetTestProp4(103);
        $obj5 = new PolyObject5a($objdb, array('id'=>($id5='testid9999'),'testprop1'=>15,'testprop4'=>104,'testprop5'=>17)); $obj5->DeltaTestProp4c(10);
        
        $database->expects($this->exactly(11))->method('write')
            ->withConsecutive( // insert first, in order, then updates
                [ 'INSERT INTO a2obj_core_database_polyobject1 ("id") VALUES (:d0)', array('d0'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject2 ("id") VALUES (:d0)', array('d0'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject4 ("type","testprop4","id") VALUES (:d0,:d1,:d2)', array('d0'=>13,'d1'=>100,'d2'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject5a ("type","id") VALUES (:d0,:d1)', array('d0'=>100,'d1'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject1 ("id") VALUES (:d0)', array('d0'=>$id4) ],
                [ 'INSERT INTO a2obj_core_database_polyobject2 ("id") VALUES (:d0)', array('d0'=>$id4) ],
                [ 'INSERT INTO a2obj_core_database_polyobject4 ("type","testprop4","id") VALUES (:d0,:d1,:d2)', array('d0'=>13,'d1'=>103,'d2'=>$id4) ],
                [ 'INSERT INTO a2obj_core_database_polyobject5a ("type","id") VALUES (:d0,:d1)', array('d0'=>102,'d1'=>$id4) ],
                [ 'UPDATE a2obj_core_database_polyobject1 SET "testprop1"=:d0 WHERE id=:id', array('d0'=>55,'id'=>$id2) ],
                [ 'UPDATE a2obj_core_database_polyobject1 SET "testprop1"=:d0 WHERE id=:id', array('d0'=>65,'id'=>$id3) ],
                [ 'UPDATE a2obj_core_database_polyobject4 SET "testprop4c"="testprop4c"+:d0 WHERE id=:id', array('d0'=>10,'id'=>$id5) ]
            )->willReturn(1);
        
        $objdb->SaveObjects(); // insert/update
        $objdb->SaveObjects(); // nothing to do now
    }
    
    public function testDeleteObject() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = new PolyObject5a($objdb, array('id'=>($id='testid1234')));
        
        $database->expects($this->once())->method('write')
            ->with("DELETE FROM a2obj_core_database_polyobject1 WHERE \"id\" = :d0", array('d0'=>$id))->willReturn(1);
        
        $obj->Delete();
        $this->assertTrue($obj->isDeleted());
    }

    public function testDeleteCreated() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        $obj = EasyObject::Create($objdb); $id = $obj->ID();
        
        $database->expects($this->exactly(0))->method('write');

        $obj->Delete(); // created, no delete call
        $this->assertTrue($obj->didPreDelete());
        $this->assertTrue($obj->isDeleted());
    }
    
    public function testDeleteLoaded() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        $obj = EasyObject::Create($objdb); $id = $obj->ID();

        $database->expects($this->exactly(2))->method('write')->withConsecutive(
            [ 'INSERT INTO a2obj_core_database_easyobject ("id") VALUES (:d0)', array('d0'=>$id) ],
            [ 'DELETE FROM a2obj_core_database_easyobject WHERE "id" = :d0', array('d0'=>$id) ]
        )->willReturn(1);
        
        $obj->Save(); // is now "loaded"

        $obj->Delete(); // delete query
        $this->assertTrue($obj->didPreDelete());
        $this->assertTrue($obj->isDeleted());
    }

    public function testDeleteByQuery() : void
    {
        $delqueries = array(
            PolyObject0::class => "DELETE FROM a2obj_core_database_polyobject1 WHERE id IN (SELECT id FROM ".
                "(SELECT a2obj_core_database_polyobject1.id FROM a2obj_core_database_polyobject1 WHERE \"testprop1\" > :d0) AS t)",
            PolyObject1::class => "DELETE FROM a2obj_core_database_polyobject1 WHERE id IN (SELECT id FROM ".
                "(SELECT a2obj_core_database_polyobject1.id FROM a2obj_core_database_polyobject1 WHERE \"testprop1\" > :d0) AS t)",
            PolyObject2::class => "DELETE FROM a2obj_core_database_polyobject1 WHERE id IN (SELECT id FROM ".
                "(SELECT a2obj_core_database_polyobject1.id FROM a2obj_core_database_polyobject1 ".
                "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id WHERE \"testprop1\" > :d0) AS t)",
            PolyObject3::class => "DELETE FROM a2obj_core_database_polyobject1 WHERE id IN (SELECT id FROM ".
                "(SELECT a2obj_core_database_polyobject1.id FROM a2obj_core_database_polyobject1 ".
                "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
                "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id WHERE \"testprop1\" > :d0) AS t)",
            PolyObject4::class => "DELETE FROM a2obj_core_database_polyobject1 WHERE id IN (SELECT id FROM ".
                "(SELECT a2obj_core_database_polyobject1.id FROM a2obj_core_database_polyobject1 ".
                "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
                "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id WHERE \"testprop1\" > :d0) AS t)",
        );

        foreach ($delqueries as $class=>$delquery)
        {
            $database = $this->createMock(PDODatabase::class);
            $objdb = new ObjectDatabase($database);
            
            // first we load the objects in advance so we can check isDeleted()
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
            
            // loading by every base class should yield the same results!
            $database->expects($this->exactly(6))->method('read') // 3x for Load(), 3x for Delete()
                ->withConsecutive(self::select1, self::select2, self::select3, self::select1, self::select2, self::select3)
                ->willReturnOnConsecutiveCalls([self::row1], [self::row2], [], [self::row1], [self::row2], []);
                
            $database->expects($this->exactly(1))->method('write')
                ->with($delquery,array('d0'=>3))
                ->willReturn(2);
            
            $objs = $objdb->LoadObjectsByQuery($class, $q); // SELECT 3x
            $this->assertSame(2, count($objs));
            $id1 = self::id1; $obj1 = $objs[$id1];
            $id2 = self::id2; $obj2 = $objs[$id2];

            $ondel1 = $this->createMock(OnDelete::class); $ondel1->expects($this->once())->method('Delete'); $obj1->SetOnDelete($ondel1);
            $ondel2 = $this->createMock(OnDelete::class); $ondel2->expects($this->once())->method('Delete'); $obj2->SetOnDelete($ondel2);
            
            // now we delete the objects and hope it deletes the same ones
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));

            $numDeleted = $objdb->DeleteObjectsByQuery($class, $q); // SELECT 3x, DELETE
            $this->assertSame($numDeleted, 2);
            $this->assertTrue($obj1->isDeleted());
            $this->assertTrue($obj2->isDeleted());
        }
    }
    
    public function testLoadedObjects() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(3))->method('read')
            ->withConsecutive(self::select1, self::select2, self::select3)
            ->willReturnOnConsecutiveCalls([self::row1], [self::row2], []);
            
        $database->expects($this->exactly(1))->method('write')->willReturn(1);
            
        $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
        
        $objdb->LoadObjectsByQuery(PolyObject3::class, $q);
        $eid = EasyObject::Create($objdb)->Save()->ID();
        
        $this->assertSame(3, $objdb->getLoadedCount());
        
        $loaded = array(
            PolyObject1::class => array(
                self::id1 => PolyObject5aa::class,
                self::id2 => PolyObject5b::class
            ),
            EasyObject::class => array(
                $eid => EasyObject::class
            )
        );
        
        $this->assertSame($loaded, $objdb->getLoadedObjects());
    }
    
    public function testEmptyKeyLoad() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0", array('d0'=>5)],
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0", array('d0'=>5)])
            ->willReturnOnConsecutiveCalls([], []);
        
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
        
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }
    
    public function testLoadUniqueByQuery() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(3))->method('read')
            ->with("SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0", array('d0'=>5))
            ->willReturnOnConsecutiveCalls([], [array('id'=>'test')], [array('id'=>'test1'),array('id'=>'test2')]);
        
        $q = new QueryBuilder(); $q->Where($q->Equals('uniqueKey',5));
        
        $this->assertNull($objdb->TryLoadUniqueByQuery(EasyObject::class, $q)); // 0
        
        $obj = $objdb->TryLoadUniqueByQuery(EasyObject::class, $q);
        $this->assertInstanceOf(EasyObject::class, $obj); // 1
        
        $this->expectException(Exceptions\MultipleUniqueKeyException::class);
        $objdb->TryLoadUniqueByQuery(EasyObject::class, $q); // 2
    }
    
    public function testDeleteUniqueByQuery() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $database->expects($this->exactly(3))->method('read')
            ->with("SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0", array('d0'=>5))
            ->willReturnOnConsecutiveCalls([], [array('id'=>'test')], [array('id'=>'test1'),array('id'=>'test2')]);
        
        $database->expects($this->exactly(3))->method('write')
            ->with("DELETE FROM a2obj_core_database_easyobject WHERE id IN (SELECT id FROM ".
                "(SELECT a2obj_core_database_easyobject.id FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0) AS t)", array('d0'=>5))
            ->willReturnOnConsecutiveCalls(0, 1, 2);
            
        $q = new QueryBuilder(); $q->Where($q->Equals('uniqueKey',5));
        
        $this->assertFalse($objdb->TryDeleteUniqueByQuery(EasyObject::class, $q)); // 0 
        $this->assertTrue($objdb->TryDeleteUniqueByQuery(EasyObject::class, $q)); // 1
        
        $this->expectException(Exceptions\MultipleUniqueKeyException::class);
        $objdb->TryDeleteUniqueByQuery(EasyObject::class, $q); // 2
    }
    
    public function testNonUniqueKeyLoad() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $selstr = "SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)], [$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([array('id'=>$id1='test123','generalKey'=>5), array('id'=>$id2='test456','generalKey'=>5)], []);
            
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs); $obj1 = $objs[$id1]; $obj2 = $objs[$id2];
        
        $this->assertInstanceOf(EasyObject::class, $obj1);
        $this->assertSame($id1, $obj1->ID());
        $this->assertSame(5, $obj1->GetGeneralKey());
        
        $this->assertInstanceOf(EasyObject::class, $obj2);
        $this->assertSame($id2, $obj2->ID());
        $this->assertSame(5, $obj2->GetGeneralKey());
        
        // load where generalKey is 6, get nothing back...
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 6));
        
        //should return the same objects w/o loading from DB
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs2);
        $this->assertSame($obj1, $objs2[$id1]);
        $this->assertSame($obj2, $objs2[$id2]);
    }
    
    public function testNonUniqueKeyDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0", array('d0'=>5)],
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0", array('d0'=>6)]
            )->willReturnOnConsecutiveCalls(
                [array('id'=>$id='test123','generalKey'=>5)],
                [array('id'=>$id='test124','generalKey'=>6)]
            );
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive(
                ["DELETE FROM a2obj_core_database_easyobject WHERE id IN (SELECT id FROM ".
                    "(SELECT a2obj_core_database_easyobject.id FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0) AS t)", array('d0'=>5)],
                ["DELETE FROM a2obj_core_database_easyobject WHERE id IN (SELECT id FROM ".
                    "(SELECT a2obj_core_database_easyobject.id FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0) AS t)", array('d0'=>6)],
            )->willReturn(1);
        
        // not cached, delete will be by query
        $this->assertSame(1,$objdb->DeleteObjectsByKey(EasyObject::class, 'generalKey', 5)); // SELECT+DELETE(q)
        $this->assertSame(0,$objdb->DeleteObjectsByKey(EasyObject::class, 'generalKey', 5)); // no-op (known empty)
        
        // load an object to cache, delete directly by cache
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 6); // SELECT
        $this->assertCount(1, $objs);
        $this->assertSame(1,$objdb->DeleteObjectsByKey(EasyObject::class, 'generalKey', 6)); // DELETE(q)
        $this->assertTrue($objs[$id]->isDeleted());
    }
    
    public function testNonUniqueKeyCount() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $database->method('FullSelectFields')->willReturn(false);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                ["SELECT COUNT(a2obj_core_database_easyobject.id) FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0", array('d0'=>5)], 
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0", array('d0'=>5)])
            ->willReturnOnConsecutiveCalls(
                [array('count'=>1)], // postgres style
                [array('id'=>'test123','generalKey'=>5)]);
        
        $this->assertSame(1, $objdb->CountObjectsByKey(EasyObject::class, 'generalKey', 5));
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5); // now count is cached
        $this->assertSame(1, $objdb->CountObjectsByKey(EasyObject::class, 'generalKey', 5));
    }

    public function testUniqueKeyLoad() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $selstr = "SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)],[$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([array('id'=>$id='test123','uniqueKey'=>5)], []);
        
        $obj = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        
        $this->assertInstanceOf(EasyObject::class, $obj); 
        $this->assertSame($id, $obj->ID());
        $this->assertSame(5, $obj->GetUniqueKey());
        
        // load where uniqueKey is 6, get nothing back...
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6));
        
        //should return the same object w/o loading from DB
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
        
        $this->expectException(Exceptions\UnknownUniqueKeyException::class);
        $objdb->TryLoadUniqueByKey(EasyObject::class, 'testKey', 5);
    }

    public function testUniqueKeyDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0", array('d0'=>5)],
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0", array('d0'=>6)]
            )->willReturnOnConsecutiveCalls(
                [array('id'=>$id='test123','uniqueKey'=>5)],
                [array('id'=>$id='test124','uniqueKey'=>6)]
            );
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive(
                ["DELETE FROM a2obj_core_database_easyobject WHERE id IN (SELECT id FROM ".
                    "(SELECT a2obj_core_database_easyobject.id FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0) AS t)", array('d0'=>5)],
                ["DELETE FROM a2obj_core_database_easyobject WHERE \"id\" = :d0", array('d0'=>'test124')]
            )->willReturn(1);
        
        // not cached, delete will be by query
        $this->assertTrue($objdb->TryDeleteUniqueByKey(EasyObject::class, 'uniqueKey', 5)); // SELECT+DELETE(q)
        $this->assertFalse($objdb->TryDeleteUniqueByKey(EasyObject::class, 'uniqueKey', 5)); // no-op (cached)
        
        // load an object to cache, delete directly by cache
        $obj = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6); // SELECT
        $this->assertNotNull($obj);
        $this->assertTrue($objdb->TryDeleteUniqueByKey(EasyObject::class, 'uniqueKey', 6)); // DELETE(1)
        $this->assertTrue($obj->isDeleted());
    }
    
    public function testUniqueKeyConstructID() : void
    {
        // when constructing objects, ID is an automatic unique key
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')
            ->with(...self::select1)->willReturn([array('id'=>$id='test123','testprop1'=>3,'type'=>101)]);
            
        $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
        
        $objs = $objdb->LoadObjectsByQuery(PolyObject5a::class, $q);
        $this->assertCount(1, $objs); $obj = $objs[$id];
        
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(PolyObject1::class, 'id', $obj->ID()));
    }
    
    public function testUniqueKeyInsertID() : void
    {
        // ID should always considered unique
        
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(0))->method('read');
        $database->expects($this->exactly(1))->method('write')->willReturn(1);
        
        $obj = EasyObject::Create($objdb)->Save();
        
        $this->assertSame($obj, EasyObject::TryLoadByID($objdb, $obj->ID()));
    }
    
    public function testUniqueKeyInsertUnique() : void
    {
        // check that loading the object adds its registered unique keys
                
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $selstr1 = "SELECT a2obj_core_database_myobjectbase.*, a2obj_core_database_myobjectchild.* FROM a2obj_core_database_myobjectbase ".
            "JOIN a2obj_core_database_myobjectchild ON a2obj_core_database_myobjectchild.id = a2obj_core_database_myobjectbase.id WHERE \"mykey\" = :d0";
        
        $selstr2 = "SELECT a2obj_core_database_myobjectbase.*, a2obj_core_database_myobjectchild.* FROM a2obj_core_database_myobjectbase ".
            "JOIN a2obj_core_database_myobjectchild ON a2obj_core_database_myobjectchild.id = a2obj_core_database_myobjectbase.id";
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr1, array('d0'=>5)],[$selstr2, array()])
            ->willReturnOnConsecutiveCalls([], [array('id'=>$id='test123','mykey'=>7)]);
            
        $this->assertNull($objdb->TryLoadUniqueByKey(MyObjectBase::class, 'mykey', 5));
        
        $objs = $objdb->LoadObjectsByQuery(MyObjectChild::class, new QueryBuilder());
        $this->assertCount(1, $objs); $obj = $objs[$id];

        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(MyObjectBase::class, 'mykey', 7));
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(MyObjectChild::class, 'mykey', 7));
    }
    
    private const polySelect1 = array(
        "SELECT a2obj_core_database_polyobject1.*, a2obj_core_database_polyobject2.*, a2obj_core_database_polyobject4.*, a2obj_core_database_polyobject5a.* ".
        "FROM a2obj_core_database_polyobject1 ".
        "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
        "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id ".
        "JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id ".
        "WHERE \"testprop5\" = :d0", array('d0'=>55));
    
    private const polySelect2 = array(
        "SELECT a2obj_core_database_polyobject1.*, a2obj_core_database_polyobject2.*, a2obj_core_database_polyobject4.*, a2obj_core_database_polyobject5a.* ".
        "FROM a2obj_core_database_polyobject1 ".
        "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
        "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id ".
        "JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id ".
        "WHERE (\"testprop5\" = :d0 AND a2obj_core_database_polyobject5a.type = :d1)", array('d0'=>75,'d1'=>101));
    
    public function testNonUniqueKeyPolyLoad() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
            
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $obj1 = $objs[$id1];
        $this->assertInstanceOf(PolyObject5a::class, $obj1);
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);
        $this->assertSame(55, $obj1->GetTestProp5());
        
        // will NOT call the database again (child classes)
        $objs = $objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $this->assertSame($obj1, $objs[$id1]);
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
    }

    public function testNonUniqueKeyPolyDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);

        $database->expects($this->exactly(1))->method('write')
            ->with("DELETE FROM a2obj_core_database_polyobject1 WHERE id IN (SELECT id FROM ".
                        "(SELECT a2obj_core_database_polyobject1.id FROM a2obj_core_database_polyobject1 ".
                        "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
                        "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id ".
                        "JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id ".
                        "WHERE (\"testprop5\" = :d0 AND a2obj_core_database_polyobject5a.type = :d1)) AS t)", 
                    array('d0'=>55,'d1'=>101))
            ->willReturn(1);

        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55); // SELECT
        $this->assertCount(1, $objs); $obj1 = $objs[$id1];

        // delete by child class should use the cache (no extra SELECT)
        $this->assertSame(1, $objdb->DeleteObjectsByKey(PolyObject5aa::class, 'testprop5', 55)); // DELETE(q)
        $this->assertTrue($obj1->isDeleted());

        $this->assertCount(0, $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55)); // no-op
    }

    public function testUniqueKeyPolyLoad() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        
        $obj1 = $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertInstanceOf(PolyObject5a::class, $obj1);
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);
        $this->assertSame($id1, $obj1->ID());
        $this->assertSame(55, $obj1->GetTestProp5());
        
        // will NOT call the database again (child classes)
        $obj2 = $objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 55);
        $this->assertSame($obj1, $obj2);
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 55));
    }

    public function testUniqueKeyPolyDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        $database->expects($this->exactly(1))->method('write')
            ->with("DELETE FROM a2obj_core_database_polyobject1 WHERE \"id\" = :d0", array('d0'=>$id1))
            ->willReturn(1);

        $obj = $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertNotNull($obj);
        $this->assertSame($id1, $obj->ID());

        // delete by child class should use the cache (delete directly)
        $this->assertTrue($objdb->TryDeleteUniqueByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertTrue($obj->isDeleted());

        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55));
    }

    public function testNonUniqueKeyLoadDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')
            ->with("SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0", array('d0'=>5))
            ->willReturn([array('id'=>$id1='test123','generalKey'=>5), array('id'=>$id2='test456','generalKey'=>5)]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs); $obj1 = $objs[$id1]; $obj2 = $objs[$id2];
        
        $this->assertInstanceOf(EasyObject::class, $obj1);
        $this->assertInstanceOf(EasyObject::class, $obj2);
        
        $delstr = "DELETE FROM a2obj_core_database_easyobject WHERE \"id\" = :d0";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive([$delstr, array('d0'=>$id1)], [$delstr, array('d0'=>$id2)])
            ->willReturn(1);
        
        $obj1->Delete();
        // should only get one result back now
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(1, $objs2);
        $this->assertSame($obj2, $objs2[$id2]);
        
        $obj2->Delete();
        // should get an empty array now
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
    }
    
    public function testUniqueKeyLoadDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')
            ->with("SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0", array('d0'=>5))
            ->willReturn([array('id'=>$id='test123','uniqueKey'=>5)]);
            
        $obj = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        $this->assertInstanceOf(EasyObject::class, $obj); 
        $this->assertNotNull($obj);

        $database->expects($this->once())->method('write')
            ->with("DELETE FROM a2obj_core_database_easyobject WHERE \"id\" = :d0", array('d0'=>$id))
            ->willReturn(1);
            
        $obj->Delete();
        // should return null w/o reloading from DB
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }

    public function testNonUniqueKeyPolyLoadDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        $database->method('write')->willReturn(1);
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $obj1 = $objs[$id1];
        
        $obj1->Delete();
        
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55));
        
        // will NOT call the database (child classes)
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
    }
    
    public function testUniqueKeyPolyLoadDelete() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(self::polySelect1, self::polySelect2)
            ->willReturnOnConsecutiveCalls([array('id'=>'test123','type'=>101,'testprop5'=>55)], []);
        $database->method('write')->willReturn(1);
        
        $obj1 = $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertInstanceOf(PolyObject5a::class, $obj1);
        
        // check that loading via 5aa leaves the registered base class as 5a
        $objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 75);
        
        $obj1->Delete();
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55));
        
        // will NOT call the database (child classes)
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 55));
    }
    
    public function testNonUniqueKeyLoadModify() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $selstr = "SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)],[$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([$ar1=array('id'=>$id1='test123','generalKey'=>5), array('id'=>$id2='test456','generalKey'=>5)], [$ar1]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs); $obj1 = $objs[$id1]; $obj2 = $objs[$id2];
        
        $this->assertInstanceOf(EasyObject::class, $obj1);
        $this->assertInstanceOf(EasyObject::class, $obj2);
        
        $updstr = "UPDATE a2obj_core_database_easyobject SET \"generalKey\"=:d0 WHERE id=:id";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive([$updstr, array('id'=>$id1,'d0'=>6)],[$updstr, array('id'=>$id2,'d0'=>6)])
            ->willReturn(1);
        
        $obj1->SetGeneralKey(6)->Save();

        // generalKey value 5 should now return one w/o reloading from DB
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(1, $objs2); $this->assertSame($obj2, $objs2[$id2]);
        
        // generalKey value 6 should now return one but must reload from DB (could be others)
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 6);
        $this->assertCount(1, $objs2); $this->assertSame($obj1, $objs2[$id1]);
        
        $obj2->SetGeneralKey(6)->Save();
        
        // generalKey value 5 should now return empty w/o reloading
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
        
        // generalKey value 6 should now return both w/o reloading
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 6);
        $this->assertCount(2, $objs2);
        $this->assertSame($obj1, $objs2[$id1]);
        $this->assertSame($obj2, $objs2[$id2]);
    }
    
    public function testUniqueKeyLoadModify() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $selstr = "SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)],[$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([array('id'=>$id='test123','uniqueKey'=>5)], []);
            
        $obj = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        $this->assertInstanceOf(EasyObject::class, $obj);
        $this->assertNotNull($obj);
        
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6));
        
        $database->expects($this->once())->method('write')
            ->with("UPDATE a2obj_core_database_easyobject SET \"uniqueKey\"=:d0 WHERE id=:id", array('id'=>$id,'d0'=>6))
            ->willReturn(1);
        
        $obj->SetUniqueKey(6)->Save();

        // uniqueKey value 5 should now return null w/o reloading from DB
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
        
        // should return the object with the new value w/o reloading from DB
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6));
    }
    
    public function testNonUniqueKeyPolyLoadModify() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        $database->method('write')->willReturn(1);
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $obj1 = $objs[$id1];
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);
        
        $obj1->SetTestProp5(66)->Save();
        
        // the child classes should return empty w/o re-calling the DB
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55));
        
        // the object will not be cached under 66 as it's not loaded yet, so nothing else to check
    }
    
    public function testUniqueKeyPolyLoadModify() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        $database->method('write')->willReturn(1);
        
        $obj1 = $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);
        $this->assertSame($obj1->ID(), $id1);
        
        $obj1->SetTestProp5(66)->Save();
        
        // the child class should return the object w/ the new value w/o re-calling the DB
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 66));
        $this->assertSame($obj1, $objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 66));
        $this->assertSame($obj1, $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 66));
    }
    
    public function testNonUniqueKeyLoadInsert() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $obj1 = EasyObject::Create($objdb); $id1 = $obj1->ID();
        $obj2 = EasyObject::Create($objdb); $id2 = $obj2->ID();
        
        $selstr = "SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"generalKey\" = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>33)], [$selstr, array('d0'=>5)])
            ->willReturnOnConsecutiveCalls([], [array('id'=>$id1,'generalKey'=>5)]);
            
        // unlike unique, querying 33 does not set us up to load 5 w/o a query
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 33)); // different value!

        $insstr = "INSERT INTO a2obj_core_database_easyobject (\"generalKey\",\"id\") VALUES (:d0,:d1)";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive([$insstr, array('d0'=>5,'d1'=>$id1)], [$insstr, array('d0'=>5,'d1'=>$id2)])
            ->willReturn(1);

        // load via generalKey 5 - still requires a query since there could be others!
        $obj1->SetGeneralKey(5)->Save();
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(1, $objs2); $this->assertSame($obj1, $objs2[$id1]);
        
        // load via generalKey 5 - should return both objects w/o a query now
        $obj2->SetGeneralKey(5)->Save();
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs2);
        $this->assertSame($obj1, $objs2[$id1]);
        $this->assertSame($obj2, $objs2[$id2]);
    }

    public function testUniqueKeyLoadInsert() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = EasyObject::Create($objdb);
        
        $database->expects($this->exactly(0))->method('read');

        $database->expects($this->exactly(1))->method('write')->willReturn(1)
            ->with("INSERT INTO a2obj_core_database_easyobject (\"uniqueKey\",\"id\") VALUES (:d0,:d1)", array('d0'=>5,'d1'=>$obj->ID()));
        
        $obj->SetUniqueKey(5)->Save();
        
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }

    public function testNonUniqueKeyPolyLoadInsert() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)->willReturn([]);
        $database->method('write')->willReturn(1);
        
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55));
        
        $obj = PolyObject5aa::Create($objdb)->SetTestProp5(55); $obj->SetTestProp4(0)->Save();
        
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $this->assertSame($obj, $objs[$obj->ID()]);
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $this->assertSame($obj, $objs[$obj->ID()]);
    }
    
    public function testUniqueKeyPolyLoadInsert() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)->willReturn([]);
        $database->method('write')->willReturn(1);
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55));
        
        $obj = PolyObject5aa::Create($objdb)->SetTestProp5(55); $obj->SetTestProp4(0)->Save();
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 55));
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55));
    }
    
    public function testNonUniqueNullKey() : void
    {
        // test inserting objects with values null/0/false and test they're treatly differently
        
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(6))->method('read')->willReturn([]);
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', null); // init empty caches
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 0);
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 1);
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey2', null);
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey2', false);
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey2', true);
        
        $obj1 = EasyObject::Create($objdb)->SetGeneralKey(null)->SetGeneralKey2(null); $id1 = $obj1->ID();
        $obj2 = EasyObject::Create($objdb)->SetGeneralKey(0)->SetGeneralKey2(false); $id2 = $obj2->ID();
        
        $insstr1 = "INSERT INTO a2obj_core_database_easyobject (\"id\") VALUES (:d0)";
        $insstr2 = "INSERT INTO a2obj_core_database_easyobject (\"generalKey\",\"generalKey2\",\"id\") VALUES (:d0,:d1,:d2)";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive(
                [$insstr1, array('d0'=>$id1)], 
                [$insstr2, array('d0'=>0,'d1'=>0,'d2'=>$id2)])
            ->willReturn(1);
        $obj1->Save(); $obj2->Save();
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', null);
        $this->assertCount(1, $objs); $this->assertSame($obj1, $objs[$id1]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 0);
        $this->assertCount(1, $objs); $this->assertSame($obj2, $objs[$id2]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey2', null);
        $this->assertCount(1, $objs); $this->assertSame($obj1, $objs[$id1]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey2', false);
        $this->assertCount(1, $objs); $this->assertSame($obj2, $objs[$id2]);
        
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 1));
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey2', true));
    }
    
    public function testUniqueNullKey() : void
    {
        // NULL is not actually a unique value! can have more than one
        // setting a unique key to/from null should move it between the cache types
        
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $database->method('write')->willReturn(1);
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" = :d0", array('d0'=>5)],
                ["SELECT a2obj_core_database_easyobject.* FROM a2obj_core_database_easyobject WHERE \"uniqueKey\" IS NULL", array()]) // LoadObjectsByKey
            ->willReturnOnConsecutiveCalls(
                [array('id'=>$id1='test123','uniqueKey'=>5)], 
                [array('id'=>$id2='test456','uniqueKey'=>null), array('id'=>$id3='test789','uniqueKey'=>null)]);
        
        $obj1 = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        $this->assertInstanceOf(EasyObject::class, $obj1); 
        $this->assertSame($id1, $obj1->ID());
        $this->assertSame(5, $obj1->GetUniqueKey());

        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'uniqueKey', null);
        $this->assertCount(2, $objs);
        $obj2 = $objs[$id2]; $obj3 = $objs[$id3];
        $this->assertSame($id2, $obj2->ID());
        $this->assertSame($id3, $obj3->ID());
        $this->assertNull($obj2->GetUniqueKey());
        $this->assertNull($obj3->GetUniqueKey());
        
        $obj1->SetUniqueKey(null)->Save(); // move obj1 to non-unique array
        $this->assertCount(3, $objdb->LoadObjectsByKey(EasyObject::class, 'uniqueKey', null));
        
        $obj2->SetUniqueKey(5)->Save(); // move obj2 to unique array
        $this->assertSame($obj2, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }

    public function testLimitOffsetBaseSubquery() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $q->Limit(3)->Offset(2);
        
        $q->Join($objdb, EasyObject::class, 'prop1', MyObjectBase::class, 'prop2'); // test preserved in subquery
        
        $database->expects($this->once())->method('read')
            ->with('SELECT a2obj_core_database_myobjectbase.*, a2obj_core_database_myobjectchild.* FROM a2obj_core_database_myobjectbase '.
                'JOIN a2obj_core_database_easyobject ON a2obj_core_database_easyobject."prop1" = a2obj_core_database_myobjectbase."prop2" '.
                'JOIN a2obj_core_database_myobjectchild ON a2obj_core_database_myobjectchild.id = a2obj_core_database_myobjectbase.id '.
                'WHERE a2obj_core_database_myobjectbase.id IN '.
                    '(SELECT id FROM (SELECT a2obj_core_database_myobjectbase.id '.
                    'FROM a2obj_core_database_myobjectbase '. // no child class join in subquery
                    'JOIN a2obj_core_database_easyobject ON a2obj_core_database_easyobject."prop1" = a2obj_core_database_myobjectbase."prop2" '.
                    'LIMIT 3 OFFSET 2) AS t)', []);
        
        $objdb->LoadObjectsByQuery(MyObjectBase::class, $q);
    }
    
    public function testLimitOffsetChildTableSubquery() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $q->Limit(3)->Offset(2)->OrderBy('testprop');
        
        $database->expects($this->once())->method('read')
        ->with("SELECT a2obj_core_database_polyobject1.*, a2obj_core_database_polyobject2.*, a2obj_core_database_polyobject4.*, a2obj_core_database_polyobject5a.* FROM a2obj_core_database_polyobject1 ".
            "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
            "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id ".
            "JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id ".
            "ORDER BY \"testprop\" LIMIT 3 OFFSET 2", []);
        
        $objdb->LoadObjectsByQuery(PolyObject5a::class, $q); // loading via cast, no subquery
    }
    
    public function testLimitOffsetChildTableNoSubquery() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $q->Limit(3)->Offset(2);
        
        $database->expects($this->once())->method('read')
        ->with("SELECT a2obj_core_database_myobjectbase.*, a2obj_core_database_myobjectchild.* FROM a2obj_core_database_myobjectbase ".
            "JOIN a2obj_core_database_myobjectchild ON a2obj_core_database_myobjectchild.id = a2obj_core_database_myobjectbase.id LIMIT 3 OFFSET 2", []);
        
        $objdb->LoadObjectsByQuery(MyObjectChild::class, $q); // final table, no subquery
    }

    public function testLimitOffsetChildNoTableNoSubquery() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $q->Limit(3)->Offset(2);
        
        $database->expects($this->once())->method('read')
        ->with("SELECT a2obj_core_database_polyobject1.*, a2obj_core_database_polyobject2.*, a2obj_core_database_polyobject4.*, a2obj_core_database_polyobject5a.* FROM a2obj_core_database_polyobject1 ".
            "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
            "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id ".
            "JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id ".
            "WHERE a2obj_core_database_polyobject5a.type = :d0 LIMIT 3 OFFSET 2", array('d0'=>101));
        
        $objdb->LoadObjectsByQuery(PolyObject5aa::class, $q); // final table, no subquery
    }
}
