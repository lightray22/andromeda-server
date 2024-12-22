<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; require_once("init.php");

use Andromeda\Core\Database\{ObjectDatabase, PDODatabase};
use Andromeda\Core\IOFormat\IOInterface;
use Andromeda\Apps\Accounts\Account;

class MyClient extends Client
{
    public function pubGetAuthKey() : string { return $this->GetAuthKey(); }
}

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testClientCreate() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('CheckLimitClients');

        $iface = $this->createMock(IOInterface::class);
        $iface->method('GetAddress')->willReturn($address="address172");
        $iface->method('GetUserAgent')->willReturn($uagent="Firefox");

        $client = MyClient::Create($iface, $objdb, $account, $name="testnameee");
        $this->assertSame($account, $client->GetAccount());
        $this->assertNotEmpty($key=$client->pubGetAuthKey());
        $this->assertFalse($client->CheckKeyMatch($iface, "test123"));
        $this->assertTrue($client->CheckKeyMatch($iface, $key));
        
        $this->assertSame($address, $client->GetLastAddress());
        $this->assertSame($uagent, $client->GetUserAgent());
    }

    public function testClientTimeout() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class), false);
        $account = $this->createMock(Account::class);
        $iface = $this->createMock(IOInterface::class);

        $account->method('GetClientTimeout')->willReturn(0); // impossible to pass

        $client = MyClient::Create($iface, $objdb, $account);
        $this->assertTrue($client->CheckKeyMatch($iface, $client->pubGetAuthKey())); // no previous date active
        $this->assertFalse($client->CheckKeyMatch($iface, $client->pubGetAuthKey())); // date active was set, now expired*/
    }
}
