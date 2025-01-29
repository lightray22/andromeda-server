<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto\CryptFields; require_once("init.php");

use Andromeda\Core\Database\{BaseObject, ObjectDatabase};
use Andromeda\Core\Database\FieldTypes\{NullStringType, NullObjectRefT};
use Andromeda\Apps\Accounts\Account;

class CryptFieldsTest extends \PHPUnit\Framework\TestCase
{
    public function testBasicNull() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $accountf = new NullObjectRefT(Account::class, 'account');
        $noncef = new NullStringType('myfield_nonce');
        $accountf->SetParent($parent);
        $noncef->SetParent($parent);

        $account = $this->createMock(Account::class);
        $database->method('TryLoadUniqueByKey')->willReturn($account); // accountf GetObject
        $accountf->SetObject($account);
        $account->method('isCryptoAvailable')->willReturn(false);

        $field = new NullCryptStringType('myfield',$accountf,$noncef);

        $this->assertFalse($field->isEncrypted());
        $this->assertTrue($field->isValueReady()); // not encrypted
        $this->assertNull($field->GetDBValue());
        $this->assertNull($field->TryGetValue());

        $this->assertTrue($field->SetValue($val="mytest"));
        $this->assertSame($val, $field->TryGetValue());

        $field->SetValue(null);
        $this->assertFalse($field->SetEncrypted(true));
        $this->assertTrue($field->isEncrypted());
        $this->assertNull($field->GetDBValue());

        $field = new CryptStringType('myfield2',$accountf,$noncef);
        $this->assertFalse($field->isInitialized());
    }

    // TODO RAY !! test null account

    public function testEncryptDecrypt() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $accountf = new NullObjectRefT(Account::class, 'account');
        $noncef = new NullStringType('myfield_nonce');
        $accountf->SetParent($parent);
        $noncef->SetParent($parent);

        $account = $this->createMock(Account::class);
        $database->method('TryLoadUniqueByKey')->willReturn($account); // accountf GetObject
        $accountf->SetObject($account);
        $account->method('isCryptoAvailable')->willReturn(true);

        $field = new CryptStringType('myfield',$accountf,$noncef);
        $noncef->InitDBValue("mynonce678");
        $field->InitDBValue($enc="ENCRYPTED");
        $this->assertTrue($field->isInitialized());

        $account->method('DecryptSecret')->willReturn($dec = "mytestval");
        $this->assertTrue($field->isEncrypted());
        $this->assertTrue($field->isValueReady()); // crypto available
        $this->assertSame($enc,$field->GetDBValue());
        $this->assertSame($dec,$field->GetValue()); // decrypts

        $account->method('EncryptSecret')->willReturn($enc2 = "ENC2348783");
        $account->method('DecryptSecret')->willReturn($dec2 = "mytest222");
        $this->assertTrue($field->SetValue($dec2));
        $this->assertSame($dec2, $field->GetValue());
        $this->assertSame($enc2, $field->GetDBValue());

        $this->assertTrue($field->SetEncrypted(false));
        $this->assertSame($dec2,$field->GetValue());
        $this->assertTrue($field->SetEncrypted(true));
        $this->assertSame($dec2,$field->GetValue());
    }
}
