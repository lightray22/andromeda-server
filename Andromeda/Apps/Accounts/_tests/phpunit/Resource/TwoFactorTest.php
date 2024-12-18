<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; require_once("init.php");

use Andromeda\Core\Database\{ObjectDatabase, PDODatabase};
use Andromeda\Apps\Accounts\Account;

class MyTwoFactor extends TwoFactor
{
    public function pubGetSecret() : string { return $this->GetSecret(); }
}

class TwoFactorTest extends \PHPUnit\Framework\TestCase
{
    public function testCheckCode() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class),false);
        $account = new Account($objdb, ['id'=>'test123'], false);

        $tf = MyTwoFactor::Create($objdb, $account, $comment="test comment")->Save();
        $this->assertSame($comment, $tf->GetComment());
        $this->assertFalse($tf->GetIsValid());
        
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $this->assertFalse($tf->CheckCode('test123'));

        $this->assertTrue($tf->CheckCode($google2fa->getCurrentOtp($tf->pubGetSecret())));
        $this->assertTrue($tf->GetIsValid());

        $this->assertFalse($tf->CheckCode($google2fa->oathTotp($tf->pubGetSecret(),42))); // too old
        $this->assertFalse($tf->CheckCode($google2fa->oathTotp($tf->pubGetSecret(),PHP_INT_MAX))); // too new
    }

    public function testCrypto() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class),false);
        $account = new Account($objdb, ['id'=>'test123'], false);

        $tf = MyTwoFactor::Create($objdb, $account)->Save();
        $this->assertFalse($tf->hasCrypto());

        $account->InitializeCrypto("mypassword");
        $tf->InitializeCrypto();
        $this->assertTrue($tf->hasCrypto());
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $this->assertTrue($tf->CheckCode($google2fa->getCurrentOtp($tf->pubGetSecret()))); // still works?

        $tf = MyTwoFactor::Create($objdb, $account)->Save();
        $this->assertTrue($tf->hasCrypto()); // created after account already had crypto
        $tf->DestroyCrypto();
        $this->assertFalse($tf->hasCrypto());
        $this->assertTrue($tf->CheckCode($google2fa->getCurrentOtp($tf->pubGetSecret()))); // still works?
    }

    public function testUsedTokens() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class),false);
        $account = new Account($objdb, ['id'=>'test123'], false);

        $tf = MyTwoFactor::Create($objdb, $account)->Save();
        UsedToken::LoadByTwoFactor($objdb, $tf); // init empty cache so we don't have to mock PDO Database returns

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $this->assertTrue($tf->CheckCode($code=$google2fa->getCurrentOtp($tf->pubGetSecret())));
        $this->assertFalse($tf->CheckCode($code)); // can't use it twice!
    }
}