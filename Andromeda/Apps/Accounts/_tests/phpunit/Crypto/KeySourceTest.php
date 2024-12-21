<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\{BaseObject, ObjectDatabase, PDODatabase, TableTypes};
use Andromeda\Core\Exceptions\DecryptionFailedException;
use Andromeda\Apps\Accounts\Account;

class MyKeySource extends BaseObject
{
    use AccountKeySource, TableTypes\TableNoChildren;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->RegisterFields($fields, self::class);
        
        $this->AccountKeySourceCreateFields();
        
        parent::CreateFields();
    }

    /** @return static */
    public static function Create(ObjectDatabase $database, Account $account, string $wrappass)
    {
        $obj = $database->CreateObject(static::class);
        $obj->AccountKeySourceCreate($account, $wrappass);
        return $obj;
    }

    public function pubGetMasterKey(bool $raw) : ?string {
        return $raw ? ($this->master_raw ?? null) : $this->master_key->TryGetValue(); }

    /** @return $this */
    public function pubInitializeCrypto(string $wrappass, bool $fast, bool $rekey = false) : self { 
        return $this->BaseInitializeCrypto($wrappass, $fast, $rekey); }

    /** @return $this */
    public function pubUnlockCrypto(string $wrappass, bool $fast) : self {
        return $this->UnlockCrypto($wrappass, $fast); }

    /** @return $this */
    public function pubLockCrypto() : self {
        return $this->LockCrypto(); }

    /** @return $this */
    public function pubDestroyCrypto() : self {
        return $this->DestroyCrypto(); }

    /** @return $this */
    public function pubInitializeCryptoFromAccount(Account $account, string $wrappass, bool $fast) : self
    {
        $this->account->SetObject($account);
        return $this->InitializeCryptoFromAccount($wrappass, $fast);
    }
}

class KeySourceTest extends \PHPUnit\Framework\TestCase
{
    public function testEmptyKeySource1() : void
    {
        $obj = new MyKeySource($this->createMock(ObjectDatabase::class), [], false);

        $this->assertFalse($obj->hasCrypto());
        $this->assertFalse($obj->isCryptoAvailable());

        $this->assertNull($obj->pubGetMasterKey(false));
        $this->assertNull($obj->pubGetMasterKey(true));

        $this->expectException(Exceptions\CryptoUnlockRequiredException::class);
        $obj->EncryptSecret("test", "nonce");
    }

    public function testEmptyKeySource2() : void
    {
        $obj = new MyKeySource($this->createMock(ObjectDatabase::class), [], false);

        $this->expectException(Exceptions\CryptoNotInitializedException::class);
        $obj->pubUnlockCrypto("testkey", true);
    }

    public function testInitCrypto() : void
    {
        $obj = new MyKeySource($this->createMock(ObjectDatabase::class), [], false);
        $obj->pubInitializeCrypto($wrappass = "wraptest", true);

        $rawkey = $obj->pubGetMasterKey(true);
        $enckey = $obj->pubGetMasterKey(false);
        $this->assertSame($rawkey, $obj->GetMasterKey());
        $this->assertNotSame($rawkey, $enckey);

        $this->assertTrue($obj->hasCrypto());
        $this->assertTrue($obj->isCryptoAvailable());

        $obj->pubInitializeCrypto($wrappass = "wraptest", true, true); // rekey
        $this->assertSame($rawkey, $obj->pubGetMasterKey(true));
        $this->assertNotSame($enckey, $obj->pubGetMasterKey(false));

        $this->expectException(Exceptions\CryptoAlreadyInitializedException::class);
        $obj->pubInitializeCrypto($wrappass, true);
    }

    public function testUnlockEncrypt() : void
    {
        $master_nonce = Crypto::GenerateSecretNonce();
        $master_salt = Crypto::GenerateSalt();

        $rawkey = Crypto::GenerateSecretKey();
        $wrapkey = Crypto::DeriveKey($wrappass = "wrapkey123", $master_salt, Crypto::SecretKeyLength(), true);

        $obj = new MyKeySource($this->createMock(ObjectDatabase::class), [
            'master_key' => Crypto::EncryptSecret($rawkey, $master_nonce, $wrapkey),
            'master_nonce' => $master_nonce, 
            'master_salt' => $master_salt
        ], false);

        $this->assertTrue($obj->hasCrypto());
        $this->assertFalse($obj->isCryptoAvailable());

        $obj->pubUnlockCrypto($wrappass, true);
        $this->assertTrue($obj->isCryptoAvailable());

        $ctext = $obj->EncryptSecret($ptext="plain text test",$nonce=Crypto::GenerateSecretNonce());
        $this->assertNotSame($ctext, $ptext);
        $this->assertSame($ptext, $obj->DecryptSecret($ctext,$nonce));

        $obj->pubLockCrypto();

        $this->expectException(DecryptionFailedException::class);
        $obj->pubUnlockCrypto("some bad password", true);
    }

    public function testDestroyCrypto() : void
    {
        $obj = new MyKeySource($this->createMock(ObjectDatabase::class), [], false);
        $obj->pubInitializeCrypto($wrappass = "wraptest", true);

        $this->assertTrue($obj->hasCrypto());
        $this->assertTrue($obj->isCryptoAvailable());

        $obj->pubDestroyCrypto();
        $this->assertFalse($obj->hasCrypto());
        $this->assertFalse($obj->isCryptoAvailable());

        $this->expectException(Exceptions\CryptoNotInitializedException::class);
        $obj->pubUnlockCrypto($wrappass, true);
    }

    public function testFromAccount() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $account = new Account($objdb, ['id'=>'myid12'], false);
        $account->InitializeCrypto($password="test123");
        $this->assertTrue($account->isCryptoAvailable());

        $obj = new MyKeySource($objdb, [], false);
        $obj->pubInitializeCryptoFromAccount($account, $wrappass="wrap123", true);

        $this->assertTrue($obj->hasCrypto());
        $this->assertTrue($obj->isCryptoAvailable());

        $ctext = $account->EncryptSecret($ptext="plain text!",$nonce=Crypto::GenerateSecretNonce());
        $this->assertSame($ptext, $obj->DecryptSecret($ctext, $nonce)); // $obj can decrypt stuff encrypted by account!

        $this->expectException(Exceptions\CryptoAlreadyInitializedException::class);
        $obj->pubInitializeCryptoFromAccount($account, $wrappass, true);
    }

    public function testKeySourceCreate() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $account = new Account($objdb, ['id'=>'myid12'], false);

        $obj = MyKeySource::Create($objdb, $account, $wrappass="test");
        $this->assertSame($account, $obj->GetAccount());
        $this->assertFalse($obj->hasCrypto());

        $account->InitializeCrypto("testpw");
        $obj = MyKeySource::Create($objdb, $account, $wrappass);
        $this->assertTrue($obj->hasCrypto()); // automatic!
    }
}

