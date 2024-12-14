<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use PHPMailer\PHPMailer; // via autoloader

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;

/** 
 * A configured email service stored in the database 
 * 
 * Manages PHPMailer configuration and wraps its usage
 * @phpstan-import-type ScalarArray from Utilities
 * @phpstan-type EmailerJ array{id:string, type:string, hosts:?ScalarArray, username:?string, password:bool,
 *    from_address:string, from_name:?string, use_reply:bool, date_created:float}
 */
class Emailer extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    protected const IDLength = 4;
    
    private PHPMailer\PHPMailer $mailer;
    
    private const SMTP_TIMEOUT = 15;
    private const DEFAULT_FROM = 'Andromeda';
    
    public const TYPE_PHPMAIL = 0; 
    public const TYPE_SENDMAIL = 1; 
    public const TYPE_QMAIL = 2; 
    public const TYPE_SMTP = 3;
    
    private const MAIL_TYPES = array(
        'phpmail'=>self::TYPE_PHPMAIL, 
        'sendmail'=>self::TYPE_SENDMAIL, 
        'qmail'=>self::TYPE_QMAIL, 
        'smtp'=>self::TYPE_SMTP);
    
    /** Date of object creation */
    private FieldTypes\Timestamp $date_created;
    /** Type of emailer (see usage) */
    private FieldTypes\IntType $type;
    /** 
     * Array of hostnames to try, in order 
     * @var FieldTypes\NullJsonArray<list<string>>
     */
    private FieldTypes\NullJsonArray $hosts;
    /** Optional SMTP username */
    private FieldTypes\NullStringType $username;
    /** Optional SMTP password */
    private FieldTypes\NullStringType $password;
    /** From email address */
    private FieldTypes\StringType $from_address;
    /** Optional from email name */
    private FieldTypes\NullStringType $from_name;
    /** If true, add a Reply-To header */
    private FieldTypes\NullBoolType $use_reply;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $fields[] = $this->date_created = new FieldTypes\Timestamp('date_created');
        $fields[] = $this->type =         new FieldTypes\IntType('type');
        $fields[] = $this->hosts =        new FieldTypes\NullJsonArray('hosts');
        $fields[] = $this->username =     new FieldTypes\NullStringType('username');
        $fields[] = $this->password =     new FieldTypes\NullStringType('password');
        $fields[] = $this->from_address = new FieldTypes\StringType('from_address');
        $fields[] = $this->from_name =    new FieldTypes\NullStringType('from_name');
        $fields[] = $this->use_reply =    new FieldTypes\NullBoolType('use_reply');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Returns a string with the CLI usage for creating an emailer */
    public static function GetCreateUsage() : string { return "--type ".implode('|',array_keys(self::MAIL_TYPES))." --from_address email [--from_name ?name] [--use_reply ?bool]"; }
    
    /** 
     * Returns a array of strings with the CLI usage for each specific driver
     * @return array<int,string>
     */
    public static function GetCreateUsages() : array { return array("--type smtp ((--host hostname [--port ?uint16] [--proto ?ssl|tls]) | --hosts json[]) [--username ?utf8] [--password ?raw]"); }
    
    /** Creates a new email backend in the database with the given input (see CLI usage) */
    public static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        $mailer = $database->CreateObject(static::class);
        $mailer->date_created->SetTimeNow();
        
        $type = self::MAIL_TYPES[$params->GetParam('type')->FromAllowlist(array_keys(self::MAIL_TYPES))];
        
        $mailer->type->SetValue($type);
        
        $mailer->from_address->SetValue($params->GetParam('from_address')->GetEmail());
        
        $mailer->from_name->SetValue($params->GetOptParam('from_name',null)->GetNullName());
        $mailer->use_reply->SetValue($params->GetOptParam('use_reply',null)->GetNullBool());

        if ($type === self::TYPE_SMTP)
        {
            $mailer->username->SetValue($params->GetOptParam('username',null)->GetNullUTF8String());
            $mailer->password->SetValue($params->GetOptParam('password',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString());

            if ($params->HasParam('hosts'))
            {
                $hosts = $params->GetParam('hosts')->GetObjectArray();
                
                $hosts = array_map(function(SafeParams $p){ 
                    return self::BuildHostFromParams($p); }, $hosts);
            }
            else $hosts = array(self::BuildHostFromParams($params));
                
            $mailer->hosts->SetArray($hosts);
        }
        
        return $mailer;
    }
    
    /** 
     * Returns any available (random) Emailer object 
     * @throws Exceptions\MailerUnavailableException if none configured
     */
    public static function LoadAny(ObjectDatabase $database) : self
    {
        $mailers = static::LoadAll($database);
        
        if (count($mailers) === 0) 
            throw new Exceptions\MailerUnavailableException();
        
        return $mailers[array_rand($mailers)];
    }

    /** Build a PHPMailer-formatted host string from an input */
    private static function BuildHostFromParams(SafeParams $params) : string
    {
        $host = $params->GetParam('host')->GetHostname();
        
        $port = $params->GetOptParam('port',null)->GetNullUint16();
        $proto =  $params->GetOptParam('proto',null)->FromAllowlistNull(array('tls','ssl'));
        
        if ($port !== null) $host .= ":$port";
        if ($proto !== null) $host = "$proto://$host";
        return $host;
    }
    
    /** Returns whether or not to use the server from address for reply-to */
    private function GetUseReply() : bool { return $this->use_reply->TryGetValue() ?? false; }
    
    /**
     * Gets the config as a printable client object
     * @return EmailerJ
     */
    public function GetClientObject() : array
    {
        return array(
            'id'           => $this->ID(),
            'date_created' => $this->date_created->GetValue(),
            'type' =>         array_flip(self::MAIL_TYPES)[$this->type->GetValue()],
            'hosts' =>        $this->hosts->TryGetArray(),
            'username' =>     $this->username->TryGetValue(),
            'password' =>     (bool)$this->password->TryGetValue(),
            'from_address' => $this->from_address->GetValue(),
            'from_name' =>    $this->from_name->TryGetValue(),
            'use_reply' =>    $this->GetUseReply()
        );        
    }
    
    /** Initializes the PHPMailer instance */
    private function Initialize() : void
    {        
        $mailer = new PHPMailer\PHPMailer(true);
        
        $mailer->Timeout = self::SMTP_TIMEOUT;
        
        switch ($type = $this->type->GetValue())
        {
            case self::TYPE_PHPMAIL: $mailer->isMail(); break;
            case self::TYPE_SENDMAIL: $mailer->isSendmail(); break;
            case self::TYPE_QMAIL: $mailer->isQmail(); break;
            case self::TYPE_SMTP: $mailer->isSMTP(); break;
            default: throw new Exceptions\InvalidMailTypeException();
        }
        
        $debug = $this->GetApiPackage()->GetDebugLevel() >= Config::ERRLOG_DETAILS;
        $mailer->SMTPDebug = $debug ? PHPMailer\SMTP::DEBUG_CONNECTION : 0;
        
        $mailer->Debugoutput = function(string $str, int $level)
        { 
            if (!Utilities::isUTF8($str)) $str = base64_encode($str);
            $this->GetApiPackage()->GetErrorManager()->LogDebugHint("PHPMailer $level: $str"); 
        };
        
        $mailer->setFrom(
            $this->from_address->GetValue(), 
            $this->from_name->TryGetValue() ?? self::DEFAULT_FROM);
        
        if ($type === self::TYPE_SMTP)
        {
            if (($username = $this->username->TryGetValue()) !== null)
            {
                $mailer->SMTPAuth = true;
                $mailer->Username = $username;
                
                if (($password = $this->password->TryGetValue()) !== null)
                    $mailer->Password = $password;
            }
            
            if (($hostsJ = $this->hosts->TryGetArray()) === null)
                throw new Exceptions\MissingHostsException();
            else
            {
                $hosts = array(); foreach ($hostsJ as $host) // check array shape
                {
                    if (is_string($host)) $hosts[] = $host;
                    else throw new Exceptions\MissingHostsException();
                }
                $mailer->Host = implode(';', $hosts);
            }
        }
    
        $this->mailer = $mailer;
    }
    
    /**
     * Send an email
     * @param string $subject the subject line of the message
     * @param string $message the body of the message
     * @param array<EmailRecipient> $recipients name/address pairs to mail to
     * @param EmailRecipient $from a different from to send as
     * @param bool $isHtml true if the body is HTML
     * @param bool $usebcc true if the recipients should use BCC
     * @throws Exceptions\EmailDisabledException if email is not enabled
     * @throws Exceptions\EmptyRecipientsException if no recipients were given
     * @throws Exceptions\MailSendException if sending the message fails
     */
    public function SendMail(string $subject, string $message, bool $isHtml, array $recipients, bool $usebcc, ?EmailRecipient $from = null) : void
    {
        if (!$this->GetApiPackage()->GetConfig()->GetEnableEmail())
            throw new Exceptions\EmailDisabledException();
        
        if (count($recipients) === 0) 
            throw new Exceptions\EmptyRecipientsException();
        
        if (!isset($this->mailer)) $this->Initialize();
        $mailer = $this->mailer;
        
        if ($from !== null) 
        {
            $mailer->addReplyTo($from->GetAddress(), $from->TryGetName() ?? "");
        }
        else if ($this->GetUseReply())
        {
            $mailer->addReplyTo(
                $this->from_address->GetValue(),
                $this->from_name->TryGetValue() ?? self::DEFAULT_FROM); 
        }
        
        foreach ($recipients as $recipient) 
        {
            if ($usebcc) $mailer->addBCC($recipient->GetAddress(), $recipient->TryGetName() ?? "");
            else $mailer->addAddress($recipient->GetAddress(), $recipient->TryGetName() ?? "");
        }
        
        $mailer->Subject = $subject; 
        
        if ($isHtml) $mailer->msgHTML($message);
        else $mailer->Body = $message;
        
        try 
        { 
            if (!$mailer->send())
                throw new Exceptions\PHPMailerFalseException($mailer->ErrorInfo);
        }
        catch (PHPMailer\Exception $e) { 
            throw new Exceptions\PHPMailThrowException($e); }
        
        $mailer->clearAddresses(); 
        $mailer->clearAttachments();
    }
}
