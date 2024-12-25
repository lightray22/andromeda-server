<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, TableTypes};

class AuthObjectTest_AuthObject extends BaseObject
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
    
    public function pubGetAuthHash() : ?string {
        return $this->authkey->TryGetValue(); }

    public function pubTryGetAuthKey() : ?string {
        return $this->TryGetAuthKey(); }

    public function pubInitAuthKey() : string {
        return $this->InitAuthKey(); }
    
    /** @return $this */
    public function pubSetAuthKey(?string $key) : self {
        return $this->SetAuthKey($key); }
}

class AuthObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testEmpty() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, [], false);

        $this->assertNull($obj->pubTryGetAuthKey());
        $this->assertNull($obj->pubGetAuthHash());
        
        $this->assertFalse($obj->CheckKeyMatch(""));
        $this->assertFalse($obj->CheckKeyMatch("test"));
    }
    
    public function testBasic() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, [], false);
        $obj->pubInitAuthKey();

        $key = $obj->pubTryGetAuthKey();
        $this->assertIsString($key);
        
        $this->assertNotEquals($key, $obj->pubGetAuthHash());
        
        $this->assertTrue($obj->CheckKeyMatch($key));
        
        $this->assertFalse($obj->CheckKeyMatch(""));
        $this->assertFalse($obj->CheckKeyMatch('0'));
        $this->assertFalse($obj->CheckKeyMatch("test"));
        $this->assertFalse($obj->CheckKeyMatch($key.'0'));
        $this->assertFalse($obj->CheckKeyMatch('0'.$key));
        $this->assertFalse($obj->CheckKeyMatch(strtoupper($key)));
    }
    
    public function testSetKey() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, [], false);
        
        $key = "mytest123"; $obj->pubSetAuthKey($key);
        $this->assertSame($key, $obj->pubTryGetAuthKey());
        $this->assertTrue($obj->CheckKeyMatch($key));
        
        $obj->pubSetAuthKey(null);
        $this->assertNull($obj->pubTryGetAuthKey());
    }
    
    public function testFromHash() : void
    {
        $key = "1qo95feuuz5ixu9d4o530sxvbto8b99j";
        $hash = '$argon2id$v=19$m=1024,t=1,p=1$SEREcGtDQ2hQaHRDcmZYcQ$rbYiVNjqfVeKKTrseQ0z+eiYGIGhHCzPCoe+5bfOknc';
        
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, array('id'=>'test123','authkey'=>$hash), false);
        
        $exc = false; try { $obj->pubTryGetAuthKey(); } // key not available yet
        catch (Exceptions\RawKeyNotAvailableException $e) { $exc = true; }
        $this->assertTrue($exc);
        
        $this->assertSame($hash, $obj->pubGetAuthHash());
        $this->assertTrue($obj->CheckKeyMatch($key));
        $this->assertSame($key, $obj->pubTryGetAuthKey()); // have key now
    }
    
    public function testRehash() : void
    {
        $key = "testKey123456789";
        $hash = '$2y$10$JvPO9nS5Papx9Z4KrwLhAOc2DIkJm5kRm1hv8z/dGcMqH23MHEaFi';
        
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, array('id'=>'test123','authkey'=>$hash), false);
        
        $this->assertSame($hash, $obj->pubGetAuthHash());
        $this->assertTrue($obj->CheckKeyMatch($key));
        
        $this->assertNotEquals($hash, $obj->pubGetAuthHash()); // re-hash
        $this->assertTrue($obj->CheckKeyMatch($key));
    }

    public function testTryGetFullKey() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, ['id'=>$id='test123'], false);
        $obj->pubInitAuthKey();
        
        $key = $obj->pubTryGetAuthKey();
        $this->assertSame("test:$id:$key", $obj->TryGetFullKey());
        
        $obj->pubSetAuthKey(null);
        $this->assertNull($obj->TryGetFullKey());

        $this->expectException(Exceptions\RawKeyNotAvailableException::class);
        $obj->GetFullKey();
    }
    
    public function testCheckFullKey() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, ['id'=>$id='test123'], false);
        $obj->pubInitAuthKey();
        
        $key = $obj->pubTryGetAuthKey();
        $full = "test:$id:$key";
        
        $this->assertTrue($obj->CheckFullKey($full));
        $obj->pubSetAuthKey(null);
        $this->assertFalse($obj->CheckFullKey($full));
    }
    
    public function testGetIDFromFullKey() : void
    {
        $this->assertNull(AuthObjectTest_AuthObject::TryGetIDFromFullKey(""));
        $this->assertNull(AuthObjectTest_AuthObject::TryGetIDFromFullKey("test:test2")); // too short
        $this->assertNull(AuthObjectTest_AuthObject::TryGetIDFromFullKey("aa:id123:fullkey456")); // wrong tag
        $this->assertSame($id="myid123", AuthObjectTest_AuthObject::TryGetIDFromFullKey("test:$id:fullkey456"));
    }
}
