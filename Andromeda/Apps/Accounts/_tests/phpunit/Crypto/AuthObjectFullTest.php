<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, PDODatabase, TableTypes};

class MyAuthObjectFull extends BaseObject
{ 
    use AuthObjectFull, TableTypes\TableNoChildren;
    
    protected static function GetFullKeyPrefix() : string { return "test"; }
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->RegisterFields($fields, self::class);
        
        $this->AuthObjectCreateFields();
        
        parent::CreateFields();
    }
    
    /** @return static */
    public static function Create(ObjectDatabase $database, bool $init) : self
    {
        $obj = $database->CreateObject(static::class);
        
        if ($init) $obj->InitAuthKey();
        
        return $obj;
    }
    
    public function pubTryGetAuthKey() : ?string {
        return $this->TryGetAuthKey(); }

    /** @return $this */
    public function pubSetAuthKey(?string $key) : self {
        return $this->SetAuthKey($key); }
    
    public function pubTryGetFullKey() : ?string {
        return $this->TryGetFullKey(); }
}

class AuthObjectFullTest extends \PHPUnit\Framework\TestCase
{
    public function testTryGetFullKey() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $obj = MyAuthObjectFull::Create($objdb, true);
        
        $id = $obj->ID(); $key = $obj->pubTryGetAuthKey();
        $this->assertSame("test:$id:$key", $obj->pubTryGetFullKey());
        
        $obj->pubSetAuthKey(null);
        $this->assertNull($obj->pubTryGetFullKey());
    }
    
    public function testCheckFullKey() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $obj = MyAuthObjectFull::Create($objdb, true);
        
        $id = $obj->ID();
        $key = $obj->pubTryGetAuthKey();
        $full = "test:$id:$key";
        
        $this->assertTrue($obj->CheckFullKey($full));
        $obj->pubSetAuthKey(null);
        $this->assertFalse($obj->CheckFullKey($full));
    }
    
    public function testGetIDFromFullKey() : void
    {
        $this->assertNull(MyAuthObjectFull::TryGetIDFromFullKey(""));
        $this->assertNull(MyAuthObjectFull::TryGetIDFromFullKey("test:test2")); // too short
        $this->assertNull(MyAuthObjectFull::TryGetIDFromFullKey("aa:id123:fullkey456")); // wrong tag
        $this->assertSame($id="myid123", MyAuthObjectFull::TryGetIDFromFullKey("test:$id:fullkey456"));
    }
}
