<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; require_once("init.php");

use Andromeda\Core\Database\{ObjectDatabase, PDODatabase};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Apps\Accounts\Account;

class ContactTest_Contact extends Contact
{
    public static function SubclassGetFetchUsage() : string { return ""; }
    
    public static function SubclassFetchPairFromParams(SafeParams $params) : ?array { return null; }

    public static function SubclassSendMessageMany(string $subject, ?string $html, string $plain, array $recipients, bool $bcc, ?Account $fromacct = null) : void { }

    public string $lastmessage;

    public function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void
    {
        $this->lastmessage = $plain;
        parent::SendMessage($subject, $html, $plain, $from);
    }
}

class ContactTest extends \PHPUnit\Framework\TestCase
{
    public function testContactCreate() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('CheckLimitContacts');

        $contact = ContactTest_Contact::Create($objdb, $account, $address="test@test.com");

        $this->assertSame($contact->GetAddress(), $address);
        $this->assertTrue($contact->GetIsValid());
        $this->assertFalse($contact->GetIsPublic());

        $contact->SetIsPublic(true);
        $this->assertTrue($contact->GetIsPublic());
    }

    public function testCreateVerify() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $account = $this->createMock(Account::class);
        $contact = ContactTest_Contact::Create($objdb, $account, $address="test@test.com", true);
        $this->assertIsString($contact->lastmessage);
        $this->assertFalse($contact->GetIsValid());

        $key = $contact->GetFullKey();
        $this->assertNotEmpty($key);
        $this->assertFalse($contact->CheckFullKey("test123"));
        $this->assertFalse($contact->CheckKeyMatch($key)); // not full
        
        $account->expects($this->once())->method('NotifyValidContact');
        $this->assertTrue($contact->CheckFullKey($key));
        $this->assertTrue($contact->GetIsValid());
        $this->assertNull($contact->TryGetFullKey());
    }

    public function testUseAsFrom() : void
    {
        $objdb = new ObjectDatabase($db=$this->createMock(PDODatabase::class), false);
        $account = $this->createMock(Account::class);
        $account->method('ID')->willReturn('acct123');

        Contact::TryLoadFromByAccount($objdb, $account); // init cache
        $contact1 = EmailContact::Create($objdb, $account, $address="test@test.com")->Save();
        $contact2 = EmailContact::Create($objdb, $account, $address="test@test.com")->Save();

        $db->expects($this->exactly(2))->method('read')->willReturn([], [['id'=>$contact1->ID()]]);

        $this->assertFalse($contact1->GetUseAsFrom());
        $this->assertFalse($contact2->GetUseAsFrom());

        $contact1->SetUseAsFrom(); // loads from - no return
        $this->assertTrue($contact1->GetUseAsFrom());
        $this->assertFalse($contact2->GetUseAsFrom());

        $contact2->SetUseAsFrom(); // loads from - returns 1
        $this->assertTrue($contact2->GetUseAsFrom());
        $this->assertFalse($contact1->GetUseAsFrom());
    }
}