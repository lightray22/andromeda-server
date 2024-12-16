<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; require_once("init.php");

use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Apps\Accounts\Account;

class LocalTest extends \PHPUnit\Framework\TestCase
{
    public function testLocalAuth() : void
    {
        $auth = new Local();
        $objdb = $this->createMock(ObjectDatabase::class);
        $account = new Account($objdb, [], false);
        
        $this->assertFalse($auth->VerifyAccountPassword($account,"")); // hash is null
        $this->assertFalse($auth->VerifyAccountPassword($account,"test"));

        $auth->SetPassword($account, $password="test123");
        $this->assertTrue($auth->VerifyAccountPassword($account, $password));
        $this->assertFalse($auth->VerifyAccountPassword($account, "wrong password"));
    }
}