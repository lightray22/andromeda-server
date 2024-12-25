<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; require_once("init.php");

use Andromeda\Core\Database\{ObjectDatabase, PDODatabase};
use Andromeda\Apps\Accounts\{Account, Config};

class RecoveryKeyTest_RecoveryKey extends RecoveryKey
{
    public function pubLockCrypto() : void { $this->LockCrypto(); }
}

class RecoveryKeyTest_Account extends Account
{
    public function pubLockCrypto() : void { $this->LockCrypto(); }
}

class RecoveryKeyTest extends \PHPUnit\Framework\TestCase
{
    public function testRecoveryKeyCreate() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $account = $this->createMock(Account::class);
        $account->expects($this->any())->method('CheckLimitRecoveryKeys');

        $keys = RecoveryKeyTest_RecoveryKey::CreateSet($objdb, $account);
        assert(count($keys) === RecoveryKey::SET_SIZE);
        $key = $keys[0];

        $this->assertNotEmpty($fkey=$key->GetFullKey());
        $this->assertFalse($key->CheckFullKey("test123"));
        $this->assertTrue($key->CheckFullKey($fkey));
    }

    public function testRecoveryKeyCrypto() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class), false);
        $account = new RecoveryKeyTest_Account($objdb, ['id'=>'acct125'], false);
        Config::Create($objdb)->Save(); // init singleton

        $key = RecoveryKeyTest_RecoveryKey::Create($objdb, $account);
        $this->assertFalse($key->hasCrypto());

        $account->InitializeCrypto("test123");
        $key = RecoveryKeyTest_RecoveryKey::Create($objdb, $account);
        $this->assertTrue($key->isCryptoAvailable());

        $key->pubLockCrypto();
        $account->pubLockCrypto();
        $this->assertTrue($key->CheckFullKey($key->GetFullKey())); // authkey unlocks
        $this->assertTrue($key->isCryptoAvailable());
        $this->assertTrue($account->isCryptoAvailable()); // from the recovery key
    }
}
