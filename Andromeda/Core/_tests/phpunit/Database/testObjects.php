<?php declare(strict_types=1); namespace Andromeda\Core\Database; require_once("init.php");

class OnDelete { public function Delete() : void { } }

class EasyObject extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    public static function Create(ObjectDatabase $database) : self
    {
        return $database->CreateObject(static::class);
    }
    
    private FieldTypes\NullIntType $uniqueKey;
    private FieldTypes\NullIntType $generalKey;
    private FieldTypes\NullBoolType $generalKey2;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->uniqueKey = $fields[] = new FieldTypes\NullIntType('uniqueKey');
        $this->generalKey = $fields[] = new FieldTypes\NullIntType('generalKey');
        $this->generalKey2 = $fields[] = new FieldTypes\NullBoolType('generalKey2');

        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'uniqueKey';
        return $ret;
    }
    
    private bool $postConstruct = false;
    protected function PostConstruct(bool $created) : void { $this->postConstruct = true; }
    public function didPostConstruct() : bool { return $this->postConstruct; }

    private bool $preDeleted = false;
    public function NotifyPreDeleted() : void { $this->preDeleted = true; }
    public function didPreDelete() : bool { return $this->preDeleted; }
    
    public function DeleteMeLater() : void { $this->DeleteLater(); }
    
    public function GetUniqueKey() : ?int { return $this->uniqueKey->TryGetValue(); }
    public function SetUniqueKey(?int $val) : self { $this->uniqueKey->SetValue($val); return $this; }
    
    public function GetGeneralKey() : ?int { return $this->generalKey->TryGetValue(); }
    public function SetGeneralKey(?int $val) : self { $this->generalKey->SetValue($val); return $this; }
    
    public function GetGeneralKey2() : ?bool { return $this->generalKey2->TryGetValue(); }
    public function SetGeneralKey2(?bool $val) : self { $this->generalKey2->SetValue($val); return $this; }
}

abstract class MyObjectBase extends BaseObject
{
    use TableTypes\TableLinkedChildren;
    
    /** @return array<int, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array 
    {
        return array(MyObjectChild::class); 
    }
        
    private FieldTypes\NullIntType $mykey;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->mykey = $fields[] = new FieldTypes\NullIntType('mykey');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'mykey';
        return $ret;
    }
    
    public function GetMyKey() : ?int { return $this->mykey->TryGetValue(); }
    public function SetMyKey(?int $val) : self { $this->mykey->SetValue($val); return $this; }
}

class MyObjectChild extends MyObjectBase
{
    use TableTypes\TableNoChildren;
}

abstract class PolyObject_ extends BaseObject { }

abstract class PolyObject0 extends PolyObject_
{
    // empty, no table!
    
    /** @return array<int, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        return array(PolyObject1::class); // so we can load via it
    }
    
    private ?OnDelete $ondel = null;
    
    public function SetOnDelete(OnDelete $del) : void { $this->ondel = $del; }
    
    public function NotifyPostDeleted() : void
    {
        if ($this->ondel !== null) $this->ondel->Delete();
        
        parent::NotifyPostDeleted();
    }
}

abstract class PolyObject1 extends PolyObject0
{
    use TableTypes\TableLinkedChildren;
    
    /** @return array<int, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        return array(27=>PolyObject2::class); // skip over PolyObject15
    }
    
    private FieldTypes\NullIntType $testprop1;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->testprop1 = $fields[] = new FieldTypes\NullIntType('testprop1');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public function GetTestProp1() : ?int
    { 
        return $this->testprop1->TryGetValue(); 
    }
    
    public function SetTestProp1(?int $val) : self
    {
        $this->testprop1->SetValue($val); return $this;
    }
}

abstract class PolyObject15 extends PolyObject1
{
    // not part of the "loadable" chain - transparent to DB
    
    private FieldTypes\NullIntType $testprop15;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->testprop15 = $fields[] = new FieldTypes\NullIntType('testprop15');
        
        $this->RegisterChildFields($fields);
        
        parent::CreateFields();
    }
    
    public function GetTestProp15() : ?int
    {
        return $this->testprop15->TryGetValue();
    }
    
    public function SetTestProp15(?int $val) : self
    {
        $this->testprop15->SetValue($val); return $this;
    }
}

abstract class PolyObject2 extends PolyObject15
{
    use TableTypes\TableLinkedChildren;

    /** @return array<int, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        return array(27=>PolyObject3::class);
    }
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
}

abstract class PolyObject3 extends PolyObject2
{
    // does not have its own table! but is part of the loadable chain
    
    /** @return array<int, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        return array(PolyObject4::class);
    }
}

class PolyObject4 extends PolyObject3
{
    use TableTypes\TableIntTypedChildren;

    /** @return array<int, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        // includes self::class - this class is not abstract!
        return array(5=>self::class, 13=>PolyObject5a::class, 18=>PolyObject5b::class);
    }

    private FieldTypes\IntType $testprop4;
    private FieldTypes\NullIntType $testprop4n; // null
    private FieldTypes\Counter $testprop4c; // counter
    
    protected function CreateFields() : void
    {
        $fields = $this->GetTypeFields();
        
        $this->testprop4  = $fields[] = new FieldTypes\IntType('testprop4');
        $this->testprop4n = $fields[] = new FieldTypes\NullIntType('testprop4n');
        $this->testprop4c = $fields[] = new FieldTypes\Counter('testprop4c');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public function GetTestProp4() : int
    {
        return $this->testprop4->GetValue();
    }
    
    public function SetTestProp4(int $val) : self
    {
        $this->testprop4->SetValue($val); return $this;
    }
    
    public function SetTestProp4n(?int $val) : self
    {
        $this->testprop4n->SetValue($val); return $this;
    }
    
    public function DeltaTestProp4c(int $val) : self
    {
        $this->testprop4c->DeltaValue($val); return $this;
    }
}

class PolyObject5a extends PolyObject4
{   
    use TableTypes\TableIntTypedChildren;
    
    /** @return array<int, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        // includes self::class - this class is not abstract!
        return array(100=>self::class, 101=>PolyObject5aa::class, 102=>PolyObject5ab::class);
    }

    public static function Create(ObjectDatabase $database) : self
    {
        return $database->CreateObject(static::class);
    }
    
    private FieldTypes\NullIntType $testprop5;
    
    protected function CreateFields() : void
    {
        $fields = $this->GetTypeFields();
        
        $this->testprop5 = $fields[] = new FieldTypes\NullIntType('testprop5');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'testprop5';
        return $ret;
    }
    
    public function GetTestProp5() : ?int
    {
        return $this->testprop5->TryGetValue();
    }
    
    public function SetTestProp5(?int $val) : self
    {
        $this->testprop5->SetValue($val); return $this;
    }
}

class PolyObject5aa extends PolyObject5a
{
    use TableTypes\NoChildren;
}

class PolyObject5ab extends PolyObject5a
{
    use TableTypes\NoChildren;
}

class PolyObject5b extends PolyObject4
{
    use TableTypes\NoChildren;
}
