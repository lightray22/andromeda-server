<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\{Emailer, EmailRecipient};
use Andromeda\Core\IOFormat\SafeParams;

use Andromeda\Apps\Accounts\Account;

class EmailContact extends Contact
{
    public static function SubclassGetFetchUsage() : string { return "--email email"; }
    
    /**
     * Fetches a type/address pair from input (depends on the param name given)
     * @return ?array{class:class-string<self>, address:string}
     */
    public static function SubclassFetchPairFromParams(SafeParams $params) : ?array
    {
        if ($params->HasParam('email')) 
        { 
            $class = EmailContact::class;
            $address = $params->GetParam('email',SafeParams::PARAMLOG_ALWAYS)->GetEmail();
            return array('class'=>$class, 'address'=>$address);
        }
        else return null;
    }

    /**
     * Sends a message to the given array of email contacts
     * @param string $subject subject line
     * @param string $html html message (optional)
     * @param string $plain plain text message
     * @param array<static> $recipients array of contacts
     * @param Account $fromacct account sending the message
     * @param bool $bcc true to use BCC for recipients
     */
    public static function SubclassSendMessageMany(string $subject, ?string $html, string $plain, array $recipients, bool $bcc, ?Account $fromacct = null) : void
    {
        $message = $html ?? $plain;
        $ishtml = ($html !== null);

        if (count($recipients) === 0) return;
        $database = array_values($recipients)[0]->database; // mild cheat
        $mailer = Emailer::LoadAny($database);
        
        $recipients = array_map(function(self $contact){
            return $contact->GetAsEmailRecipient(); }, $recipients);
        
        $fromcontact = ($fromacct !== null) ? static::TryLoadFromByAccount($database, $fromacct) : null;
        $frommail = ($fromcontact !== null) ? $fromcontact->GetAsEmailRecipient() : null;

        $mailer->SendMail($subject, $message, $ishtml, $recipients, $bcc, $frommail);
    }

    /** Returns this contact as an Emailer recipient */
    public function GetAsEmailRecipient() : EmailRecipient
    {
        return new EmailRecipient($this->GetAddress(), $this->GetAccount()->GetDisplayName());
    }
}
