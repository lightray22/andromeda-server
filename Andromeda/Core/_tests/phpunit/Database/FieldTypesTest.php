<?php declare(strict_types=1); namespace Andromeda\Core\Database\FieldTypes; require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

use Andromeda\Core\Database\{BaseObject, Exceptions, ObjectDatabase, TableTypes};

class TestObject1 extends BaseObject { use TableTypes\TableNoChildren; }
class TestObject2 extends BaseObject { use TableTypes\TableNoChildren; }

class FieldTypesTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = new TestObject1($database, array('id'=>$id='test123'), false);
        $field = (new StringType('myfield'))->SetParent($parent);
        $this->assertSame("myfield", $field->GetName());
        
        $this->assertSame(0, $field->GetDelta());
        $this->assertFalse($field->isModified());
    }
    
    public function testAlwaysSave() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new StringType('myfield'))->SetParent($parent);
        $this->assertFalse($field->isAlwaysSave());
        
        $field = (new StringType('myfield',true))->SetParent($parent);
        $this->assertTrue($field->isAlwaysSave());
    }
    
    public function testSetUnmodified() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new StringType('myfield'))->SetParent($parent);

        $field->SetValue('test');
        $this->assertSame(1, $field->GetDelta());
        
        $field->SetUnmodified();
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testNotifyModified() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = new TestObject1($database, array(), false);
        $field = (new StringType('myfield'))->SetParent($parent);
        
        $database->expects($this->once())->method('notifyModified');
        
        $this->assertTrue($field->SetValue('test'));
        $this->assertFalse($field->SetValue('test'));
        $this->assertTrue($field->isModified());
    }

    public function testNullStringValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullStringType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue(null)); 
        $this->assertSame(null, $field->TryGetValue());
        
        $this->assertTrue($field->SetValue('test')); 
        $this->assertSame('test', $field->TryGetValue());
        
        $this->assertFalse($field->SetValue('test')); 
        $this->assertSame('test', $field->TryGetValue());
        
        $this->assertTrue($field->SetValue('test2'));
        $this->assertSame('test2', $field->TryGetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testNullStringTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullStringType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue('test',true));
        $this->assertSame('test', $field->TryGetValue());
        $this->assertSame(null, $field->TryGetValue(false));
        $this->assertSame(null, $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testNullStringDBValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullStringType('myfield'))->SetParent($parent);
        
        $field->InitDBValue(null); $this->assertNull($field->TryGetValue()); $this->assertNull($field->GetDBValue());
        $field->InitDBValue(''); $this->assertSame('', $field->TryGetValue()); $this->assertSame('', $field->GetDBValue());
        $field->InitDBValue('5'); $this->assertSame('5', $field->TryGetValue()); $this->assertSame('5', $field->GetDBValue());
        
        $field->InitDBValue(0); $this->assertSame('0', $field->TryGetValue()); // bad type?
    }
    
    public function testNullStringDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new NullStringType('myfield'))->SetParent($parent);
        $this->assertNull($field->TryGetValue());

        $field = (new NullStringType('myfield', default:'a'))->SetParent($parent);
        $this->assertSame('a', $field->TryGetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testStringValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new StringType('myfield'))->SetParent($parent);
        
        $this->assertTrue($field->SetValue('test'));
        $this->assertSame('test', $field->GetValue());
        
        $this->assertFalse($field->SetValue('test'));
        $this->assertSame('test', $field->GetValue());
        
        $this->assertTrue($field->SetValue('test2'));
        $this->assertSame('test2', $field->GetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testStringTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new StringType('myfield'))->SetParent($parent);
        
        $field->InitDBValue('init');
        
        $this->assertFalse($field->SetValue('test',true));
        $this->assertSame('test', $field->GetValue());
        $this->assertSame('init', $field->GetValue(false));
        $this->assertSame('init', $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testStringDBValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new StringType('myfield'))->SetParent($parent);
        
        $field->SetValue('tmp',true);
        $this->assertTrue($field->isInitialized());
        $this->assertFalse($field->isInitialized(false));
        
        $field->InitDBValue(''); $this->assertSame('', $field->GetValue()); $this->assertSame('', $field->GetDBValue());
        $field->InitDBValue('5'); $this->assertSame('5', $field->GetValue()); $this->assertSame('5', $field->GetDBValue());
        
        $this->assertTrue($field->isInitialized());
        
        $field->InitDBValue(0); $this->assertSame('0', $field->GetValue()); // bad type?
    }
    
    public function testStringDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);

        $field = (new StringType('myfield', default:'a'))->SetParent($parent);
        $this->assertSame('a', $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testNullBoolValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullBoolType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue(null));
        $this->assertSame(null, $field->TryGetValue());
        
        $this->assertTrue($field->SetValue(true));
        $this->assertSame(true, $field->TryGetValue());
        
        $this->assertFalse($field->SetValue(true));
        $this->assertSame(true, $field->TryGetValue());
        
        $this->assertTrue($field->SetValue(false));
        $this->assertSame(false, $field->TryGetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testNullBoolTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullBoolType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue(true,true));
        $this->assertSame(true, $field->TryGetValue());
        $this->assertSame(null, $field->TryGetValue(false));
        $this->assertSame(null, $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testNullBoolDBValue() : void
    {
        $field = new NullBoolType('myfield');
        
        $field->InitDBValue(null); $this->assertNull($field->TryGetValue()); $this->assertNull($field->GetDBValue());
        $field->InitDBValue(0); $this->assertSame(false, $field->TryGetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue(1); $this->assertSame(true, $field->TryGetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue(99); $this->assertSame(true, $field->TryGetValue()); $this->assertSame(1, $field->GetDBValue());
        
        $field->InitDBValue(''); $this->assertSame(false, $field->TryGetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('0'); $this->assertSame(false, $field->TryGetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('1'); $this->assertSame(true, $field->TryGetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue('99'); $this->assertSame(true, $field->TryGetValue()); $this->assertSame(1, $field->GetDBValue());
    }
    
    public function testNullBoolDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new NullBoolType('myfield'))->SetParent($parent);
        $this->assertNull($field->TryGetValue());
        
        $field = (new NullBoolType('myfield', default:false))->SetParent($parent);
        $this->assertSame(false, $field->TryGetValue());
        $this->assertSame(1, $field->GetDelta());
        
        $field = (new NullBoolType('myfield', default:true))->SetParent($parent);
        $this->assertSame(true, $field->TryGetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testBoolValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new BoolType('myfield'))->SetParent($parent);
        
        $this->assertTrue($field->SetValue(false));
        $this->assertSame(false, $field->GetValue());
        
        $this->assertFalse($field->SetValue(false));
        $this->assertSame(false, $field->GetValue());
        
        $this->assertTrue($field->SetValue(true));
        $this->assertSame(true, $field->GetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testBoolTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new BoolType('myfield'))->SetParent($parent);
        
        $field->InitDBValue(0);
        
        $this->assertFalse($field->SetValue(true,true));
        $this->assertSame(true, $field->GetValue());
        $this->assertSame(false, $field->GetValue(false));
        $this->assertSame(0, $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testBoolDBValue() : void
    {
        $field = new BoolType('myfield');
        
        $field->SetValue(false,true);
        $this->assertTrue($field->isInitialized());
        $this->assertFalse($field->isInitialized(false));
        
        $field->InitDBValue(0); $this->assertSame(false, $field->GetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue(1); $this->assertSame(true, $field->GetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue(99); $this->assertSame(true, $field->GetValue()); $this->assertSame(1, $field->GetDBValue());
        
        $field->InitDBValue(''); $this->assertSame(false, $field->GetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('0'); $this->assertSame(false, $field->GetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('1'); $this->assertSame(true, $field->GetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue('99'); $this->assertSame(true, $field->GetValue()); $this->assertSame(1, $field->GetDBValue());
        
        $this->assertTrue($field->isInitialized());
    }
    
    public function testBoolDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new BoolType('myfield', default:false))->SetParent($parent);
        $this->assertSame(false, $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
        
        $field = (new BoolType('myfield', default:true))->SetParent($parent);
        $this->assertSame(true, $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testNullIntValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullIntType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue(null));
        $this->assertSame(null, $field->TryGetValue());
        
        $this->assertTrue($field->SetValue(1));
        $this->assertSame(1, $field->TryGetValue());
        
        $this->assertFalse($field->SetValue(1));
        $this->assertSame(1, $field->TryGetValue());
        
        $this->assertTrue($field->SetValue(0));
        $this->assertSame(0, $field->TryGetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testNullIntTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullIntType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue(1,true));
        $this->assertSame(1, $field->TryGetValue());
        $this->assertSame(null, $field->TryGetValue(false));
        $this->assertSame(null, $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }

    public function testNullIntDBValue() : void
    {
        $field = new NullIntType('myfield');
        
        $field->InitDBValue(null); $this->assertNull($field->TryGetValue()); $this->assertNull($field->GetDBValue());
        $field->InitDBValue(0); $this->assertSame(0, $field->TryGetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue(1); $this->assertSame(1, $field->TryGetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue(99); $this->assertSame(99, $field->TryGetValue()); $this->assertSame(99, $field->GetDBValue());
        
        $field->InitDBValue(''); $this->assertSame(0, $field->TryGetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('0'); $this->assertSame(0, $field->TryGetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('1'); $this->assertSame(1, $field->TryGetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue('99'); $this->assertSame(99, $field->TryGetValue()); $this->assertSame(99, $field->GetDBValue());
    }
    
    public function testNullIntDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new NullIntType('myfield'))->SetParent($parent);
        $this->assertNull($field->TryGetValue());

        $field = (new NullIntType('myfield', default:0))->SetParent($parent);
        $this->assertSame(0, $field->TryGetValue());
        $this->assertSame(1, $field->GetDelta());
        
        $field = (new NullIntType('myfield', default:1))->SetParent($parent);
        $this->assertSame(1, $field->TryGetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testIntValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $this->assertTrue($field->SetValue(0));
        $this->assertSame(0, $field->GetValue());
        
        $this->assertFalse($field->SetValue(0));
        $this->assertSame(0, $field->GetValue());
        
        $this->assertTrue($field->SetValue(1));
        $this->assertSame(1, $field->GetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testIntTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $field->InitDBValue(0);
        
        $this->assertFalse($field->SetValue(1,true));
        $this->assertSame(1, $field->GetValue());
        $this->assertSame(0, $field->GetValue(false));
        $this->assertSame(0, $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }

    public function testIntDBValue() : void
    {
        $field = new IntType('myfield');
        
        $field->SetValue(0,true);
        $this->assertTrue($field->isInitialized());
        $this->assertFalse($field->isInitialized(false));
        
        $field->InitDBValue(0); $this->assertSame(0, $field->GetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue(1); $this->assertSame(1, $field->GetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue(99); $this->assertSame(99, $field->GetValue()); $this->assertSame(99, $field->GetDBValue());
        
        
        $field->InitDBValue(''); $this->assertSame(0, $field->GetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('0'); $this->assertSame(0, $field->GetValue()); $this->assertSame(0, $field->GetDBValue());
        $field->InitDBValue('1'); $this->assertSame(1, $field->GetValue()); $this->assertSame(1, $field->GetDBValue());
        $field->InitDBValue('99'); $this->assertSame(99, $field->GetValue()); $this->assertSame(99, $field->GetDBValue());
        
        $this->assertTrue($field->isInitialized());
    }
    
    public function testIntDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new IntType('myfield', default:0))->SetParent($parent);
        $this->assertSame(0, $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
        
        $field = (new IntType('myfield', default:1))->SetParent($parent);
        $this->assertSame(1, $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testNullFloatValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullFloatType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue(null));
        $this->assertSame(null, $field->TryGetValue());
        
        $this->assertTrue($field->SetValue(1.1));
        $this->assertSame(1.1, $field->TryGetValue());
        
        $this->assertFalse($field->SetValue(1.1));
        $this->assertSame(1.1, $field->TryGetValue());
        
        $this->assertTrue($field->SetValue(0));
        $this->assertSame(0.0, $field->TryGetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testNullFloatTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullFloatType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->SetValue(1.1,true));
        $this->assertSame(1.1, $field->TryGetValue());
        $this->assertSame(null, $field->TryGetValue(false));
        $this->assertSame(null, $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testNullFloatDBValue() : void
    {
        $field = new NullFloatType('myfield');
        
        $field->InitDBValue(null); $this->assertNull($field->TryGetValue()); $this->assertNull($field->GetDBValue());
        $field->InitDBValue(0.0); $this->assertSame(0.0, $field->TryGetValue()); $this->assertSame(0.0, $field->GetDBValue());
        $field->InitDBValue(1.1); $this->assertSame(1.1, $field->TryGetValue()); $this->assertSame(1.1, $field->GetDBValue());
        
        $field->InitDBValue(''); $this->assertSame(0.0, $field->TryGetValue()); $this->assertSame(0.0, $field->GetDBValue());
        $field->InitDBValue('0.0'); $this->assertSame(0.0, $field->TryGetValue()); $this->assertSame(0.0, $field->GetDBValue());
        $field->InitDBValue('1.1'); $this->assertSame(1.1, $field->TryGetValue()); $this->assertSame(1.1, $field->GetDBValue());
    }
    
    public function testNullFloatDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new NullFloatType('myfield'))->SetParent($parent);
        $this->assertNull($field->TryGetValue());
        
        $field = (new NullFloatType('myfield', default:0.0))->SetParent($parent);
        $this->assertSame(0.0, $field->TryGetValue());
        $this->assertSame(1, $field->GetDelta());
        
        $field = (new NullFloatType('myfield', default:1.1))->SetParent($parent);
        $this->assertSame(1.1, $field->TryGetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testFloatValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new FloatType('myfield'))->SetParent($parent);
        
        $this->assertTrue($field->SetValue(0.0));
        $this->assertSame(0.0, $field->GetValue());
        
        $this->assertFalse($field->SetValue(0.0));
        $this->assertSame(0.0, $field->GetValue());
        
        $this->assertTrue($field->SetValue(1.1));
        $this->assertSame(1.1, $field->GetValue());
        
        $this->assertSame(2, $field->GetDelta());
    }
    
    public function testFloatTempValue() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new FloatType('myfield'))->SetParent($parent);
        
        $field->InitDBValue(0.0);
        
        $this->assertFalse($field->SetValue(1.1,true));
        $this->assertSame(1.1, $field->GetValue());
        $this->assertSame(0.0, $field->GetValue(false));
        $this->assertSame(0.0, $field->GetDBValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testFloatDBValue() : void
    {
        $field = new FloatType('myfield');
        
        $field->SetValue(0.0,true);
        $this->assertTrue($field->isInitialized());
        $this->assertFalse($field->isInitialized(false));
        
        $field->InitDBValue(0.0); $this->assertSame(0.0, $field->GetValue()); $this->assertSame(0.0, $field->GetDBValue());
        $field->InitDBValue(1.1); $this->assertSame(1.1, $field->GetValue()); $this->assertSame(1.1, $field->GetDBValue());
        
        $field->InitDBValue(''); $this->assertSame(0.0, $field->GetValue()); $this->assertSame(0.0, $field->GetDBValue());
        $field->InitDBValue('0.0'); $this->assertSame(0.0, $field->GetValue()); $this->assertSame(0.0, $field->GetDBValue());
        $field->InitDBValue('1.1'); $this->assertSame(1.1, $field->GetValue()); $this->assertSame(1.1, $field->GetDBValue());
        
        $this->assertTrue($field->isInitialized());
    }
    
    public function testFloatDefault() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $field = (new FloatType('myfield', default:0.0))->SetParent($parent);
        $this->assertSame(0.0, $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
        
        $field = (new FloatType('myfield', default:1.1))->SetParent($parent);
        $this->assertSame(1.1, $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
    }
    
    public function testNullTimestamp() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $objdb = $this->createMock(ObjectDatabase::class);
        $parent->method('GetDatabase')->willReturn($objdb);
        $objdb->method('GetTime')->willReturn($val=5.56);
        
        $field = (new NullTimestamp('myfield'))->SetParent($parent);
        
        $this->assertSame(null, $field->TryGetValue());
        $field->SetTimeNow();
        $this->assertSame($val, $field->TryGetValue());
    }
    
    public function testTimestamp() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $objdb = $this->createMock(ObjectDatabase::class);
        $parent->method('GetDatabase')->willReturn($objdb);
        $objdb->method('GetTime')->willReturn($val2=5.56);
        
        $field = (new Timestamp('myfield', default:$val1=7.62))->SetParent($parent);
        
        $this->assertSame($val1, $field->GetValue());
        $field->SetTimeNow();
        $this->assertSame($val2, $field->GetValue());
    }

    public function testCounter() : void
    {
        $field = new Counter('myfield');
        
        $this->assertFalse($field->isModified());
        
        $this->assertSame(0, $field->GetValue());
        $this->assertSame(0, $field->GetDelta());
        $this->assertSame(0, $field->GetDBValue());
        
        $field->DeltaValue(5);
        $this->assertTrue($field->isModified());
        $this->assertSame(5, $field->GetDelta());
        $this->assertSame(5, $field->GetDBValue());
        $this->assertSame(5, $field->GetValue());
        
        $field->InitDBValue(100);
        $this->assertFalse($field->isModified());
        $this->assertSame(0, $field->GetDelta());
        $this->assertSame(0, $field->GetDBValue());
        $this->assertSame(100, $field->GetValue());
        
        $field->DeltaValue();
        $this->assertSame(1, $field->GetDelta());
        $this->assertSame(1, $field->GetDBValue());
        $this->assertSame(101, $field->GetValue());
        
        $field->DeltaValue(49);
        $this->assertSame(50, $field->GetDelta());
        $this->assertSame(50, $field->GetDBValue());
        $this->assertSame(150, $field->GetValue());
        
        $field->DeltaValue(-100);
        $this->assertSame(-50, $field->GetDelta());
        $this->assertSame(-50, $field->GetDBValue());
        $this->assertSame(50, $field->GetValue());
        
        $field->SetUnmodified();
        $this->assertSame(0, $field->GetDelta());
        $this->assertSame(0, $field->GetDBValue());
        $this->assertSame(50, $field->GetValue());

        $field->InitDBValue(''); $this->assertSame(0, $field->GetValue());
        $field->InitDBValue('0'); $this->assertSame(0, $field->GetValue());
        $field->InitDBValue('1'); $this->assertSame(1, $field->GetValue());
        $field->InitDBValue('99'); $this->assertSame(99, $field->GetValue());
    }
    
    public function testCounterLimitCheck() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $limit = (new NullIntType('mylimit'))->SetParent($parent);
        $counter = (new Counter('mycounter', limit:$limit))->SetParent($parent);
        
        $this->assertTrue($counter->CheckDelta(9999));
        
        $limit->SetValue(10);        
        $this->assertTrue($counter->CheckDelta(1));
        $this->assertTrue($counter->CheckDelta(10));
        $this->assertFalse($counter->CheckDelta(11, false));
        
        $counter->InitDBValue(20);        
        $this->assertTrue($counter->CheckDelta(-1));
        $this->assertTrue($counter->CheckDelta(0));
        $this->assertFalse($counter->CheckDelta(1, false));

        $this->expectException(Exceptions\CounterOverLimitException::class);
        $counter->CheckDelta(1);
    }
    
    public function testCounterLimitDelta() : void
    {
        $parent = $this->createMock(BaseObject::class);
        
        $limit = (new NullIntType('mylimit', default:10))->SetParent($parent);
        $this->assertSame(10, $limit->TryGetValue());
        
        $counter = (new Counter('mycounter', limit:$limit))->SetParent($parent);
        
        $counter->DeltaValue(10); // okay
        
        $counter->InitDBValue(0);
        $counter->DeltaValue(20, true); // ignore
        
        $this->expectException(Exceptions\CounterOverLimitException::class);
        $counter->InitDBValue(0);
        $counter->DeltaValue(20);
    }

    public function testJsonArray() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new JsonArray('myjson'))->SetParent($parent);
        
        $field->InitDBValue("[]");
        $this->assertSame(array(), $field->GetArray());
        
        $json = '{"key1":"val1","key2":5}';
        $field->InitDBValue($json);
        $this->assertSame(array('key1'=>'val1','key2'=>5), $field->GetArray());
        $this->assertSame($json, $field->GetDBValue());
        
        $array = array('key3'=>'val3','key4'=>7);
        $field->SetArray($array);
        $this->assertSame($array, $field->GetArray());
        $this->assertSame('{"key3":"val3","key4":7}', $field->GetDBValue());
    }
    
    public function testNullJsonArray() : void
    {
        $parent = $this->createMock(BaseObject::class);
        $field = (new NullJsonArray('myjson'))->SetParent($parent);
        
        $this->assertSame(null, $field->TryGetArray()); // default
        $this->assertSame(null, $field->GetDBValue()); // default
        
        $field->InitDBValue("");
        $this->assertSame(null, $field->TryGetArray());
        $this->assertSame(null, $field->GetDBValue()); 
        
        $json = '{"key1":"val1","key2":5}';
        $field->InitDBValue($json);
        $this->assertSame(array('key1'=>'val1','key2'=>5), $field->TryGetArray());
        $this->assertSame($json, $field->GetDBValue());

        $field->SetArray(null);
        $this->assertSame(null, $field->GetDBValue());
        $this->assertSame(null, $field->TryGetArray());
        
        $array = array('key3'=>'val3','key4'=>7);
        $field->SetArray($array);
        $this->assertSame($array, $field->TryGetArray());
        $this->assertSame('{"key3":"val3","key4":7}', $field->GetDBValue());
    }
    
    public function testNullObjectInit() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
 
        $field = (new NullObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        $this->assertSame(TestObject1::class, $field->GetClass());
        
        $this->assertNull($field->TryGetObject()); // default
        $this->assertNull($field->TryGetObjectID());
        
        $field->InitDBValue($id='test123');
        $this->assertSame($id, $field->TryGetObjectID());
        $this->assertSame($id, $field->GetDBValue());
        
        $database->expects($this->once())
            ->method('TryLoadUniqueByKey')
            ->with(TestObject1::class, 'id', $id)
            ->willReturn($obj = $this->createMock(TestObject1::class));
        
        $this->assertSame($obj, $field->TryGetObject());
    }
    
    public function testNullObjectValue() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $field = (new NullObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        
        $obj = new TestObject1($database, array('id'=>$id='test456'), false);
        $database->expects($this->exactly(0))->method('TryLoadUniqueByKey'); // cached
        
        $field->SetObject($obj);
        $this->assertSame($obj, $field->TryGetObject());
        $this->assertSame($obj, $field->TryGetObject()); // cached
        $this->assertSame($id, $field->TryGetObjectID());
        
        $field->SetObject(null);
        $this->assertSame(null, $field->TryGetObject());
        $this->assertSame(null, $field->TryGetObjectID());

        // test not setting a newly created object
        $obj = new TestObject1($database, array('id'=>$id), true);
        $field->SetObject($obj);
        $this->assertSame($obj, $field->TryGetObject()); // works w/o saving $obj
    }
    
    public function testObjectInit() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $field = (new ObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        $this->assertSame(TestObject1::class, $field->GetClass());
        
        $this->assertFalse($field->isInitialized());
        
        $field->InitDBValue($id='test123');
        $this->assertSame($id, $field->GetObjectID());
        $this->assertSame($id, $field->GetDBValue());
        
        $this->assertTrue($field->isInitialized());
        
        $obj = $this->createStub(TestObject1::class);
        
        $database->expects($this->once())
            ->method('TryLoadUniqueByKey')
            ->with(TestObject1::class, 'id', $id)
            ->willReturn($obj);
            
        $this->assertSame($obj, $field->GetObject());
        $this->assertSame($obj, $field->GetObject()); // cached
    }
    
    public function testObjectValue() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $field = (new ObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        
        $obj = new TestObject1($database, array('id'=>$id='test456'), false);
        $database->expects($this->exactly(0))->method('TryLoadUniqueByKey'); // cached

        $field->SetObject($obj);
        $this->assertSame($obj, $field->GetObject());
        $this->assertSame($id, $field->GetObjectID());
        
        // test not setting a newly created object
        $obj = new TestObject1($database, array('id'=>$id), true);
        $field->SetObject($obj);
        $this->assertSame($obj, $field->GetObject()); // works w/o saving $obj
    }
}
