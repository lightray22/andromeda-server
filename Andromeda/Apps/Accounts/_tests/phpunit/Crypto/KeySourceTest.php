<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, PDODatabase, TableTypes};

use Andromeda\Core\Exceptions\DecryptionFailedException;

class MyKeySource extends BaseObject
{
    use KeySource, TableTypes\TableNoChildren;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->RegisterFields($fields, self::class);
        
        $this->KeySourceCreateFields();
        
        parent::CreateFields();
    }

    /** @return static */
    public static function Create(ObjectDatabase $database) : self
    {
        return $database->CreateObject(static::class);
    }

    public function pubhasCrypto() : bool { 
        return $this->hasCrypto(); }

    /** @return $this */
    /*public function pubInitializeCrypto(string $key, string $wrapkey) : self { 
        return $this->InitializeCrypto($key, $wrapkey); }*/

    /*public function pubGetUnlockedKey(string $wrapkey) : string {
        return $this->GetUnlockedKey($wrapkey); }*/

    /** @return $this */
    /*public function pubDestroyCrypto() : self {
        return $this->DestroyCrypto(); }*/
}

class KeySourceTest extends \PHPUnit\Framework\TestCase
{
    /*public function testEmptyKeySource() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $obj = MyKeySource::Create($objdb);

        $this->assertFalse($obj->pubhasCrypto());
        $this->expectException(Exceptions\CryptoNotInitializedException::class);
        $obj->pubGetUnlockedKey("test");
    }

    public function testInitCrypto() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $obj = MyKeySource::Create($objdb);
        $obj->pubInitializeCrypto($key = "mykey", $wrapkey = "wraptest");

        $this->expectException(Exceptions\CryptoAlreadyInitializedException::class);
        $obj->pubInitializeCrypto($key, $wrapkey);
    }

    public function testGetUnlockedKey() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $obj = MyKeySource::Create($objdb);
        $obj->pubInitializeCrypto($key = "mykey", $wrapkey = "wraptest");
        $this->assertSame($key, $obj->pubGetUnlockedKey(($wrapkey)));

        $this->expectException(DecryptionFailedException::class);
        $obj->pubGetUnlockedKey("badkey!");
    }

    public function testDestroyCrypto() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $obj = MyKeySource::Create($objdb);
        $obj->pubInitializeCrypto($key = "mykey", $wrapkey = "wraptest");
        $this->assertTrue($obj->pubhasCrypto());

        $obj->pubDestroyCrypto();
        $this->assertFalse($obj->pubhasCrypto());
        $this->expectException(Exceptions\CryptoNotInitializedException::class);
        $obj->pubGetUnlockedKey($wrapkey);
    }*/ // TODO RAY !! fix tests
}

