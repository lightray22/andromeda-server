<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

use Andromeda\Apps\Accounts\Account;

/** 
 * Describes an OTP twofactor authentication source for an account 
 * 
 * Accounts can have > 1 of these so the user is able to use multiple devices.
 * If account crypto is available, the secret is stored encrypted in the database.
 */
class TwoFactor extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    /** Date the twofactor was created */
    private FieldTypes\Timestamp $date_created;
    /** The optional user label for this twofactor */
    private FieldTypes\NullStringType $comment;
    /** The twofactor secret for generating codes */
    private FieldTypes\StringType $secret;
    /** The nonce if the secret is encrypted */
    private FieldTypes\NullStringType $nonce;
    /** True if this twofactor has been validated */
    private FieldTypes\BoolType $valid;
    /** The timestamp this twofactor was last used */
    private FieldTypes\NullTimestamp $date_used;
    /**
     * The account this twofactor is for
     * @var FieldTypes\ObjectRefT<Account> 
     */
    private FieldTypes\ObjectRefT $account;
    
    /** The raw secret in case it's encrypted */
    private string $raw_secret;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->comment =      $fields[] = new FieldTypes\NullStringType('comment');
        $this->secret =       $fields[] = new FieldTypes\StringType('secret');
        $this->nonce =        $fields[] = new FieldTypes\NullStringType('nonce');
        $this->valid =        $fields[] = new FieldTypes\BoolType('valid', false, false);
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_used =    $fields[] = new FieldTypes\NullTimestamp('date_used');
        $this->account =      $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** The length of the OTP secret */
    private const SECRET_LENGTH = 32; 
    
    /** the time tolerance for codes, as a multiple of 30-seconds */
    public const TIME_TOLERANCE = 2;
    
    /** Gets the account that owns this object */
    public function GetAccount() : Account { return $this->account->GetObject(); }
    
    /** Gets the comment/label the user assigned to this object */
    public function GetComment() : ?string { return $this->comment->TryGetValue(); }

    /** Returns whether this twofactor has been validated */
    public function GetIsValid() : bool { return $this->valid->GetValue(); }
    
    /** Returns whether the OTP secret is stored encrypted */
    public function hasCrypto() : bool { return $this->nonce->TryGetValue() !== null; }
    
    /** 
     * Tries to load a two factor object by the given account and ID
     * @return ?static the loaded object or null if not found */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->Equals('id',$id));
        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($w));
    }
    
    /** Creates and returns a new twofactor object for the given account */
    public static function Create(ObjectDatabase $database, Account $account, ?string $comment = null) : self
    {
        $obj = static::BaseCreate($database);
        $obj->date_created->SetTimeNow();
        $obj->comment->SetValue($comment);
        $obj->account->SetObject($account);
        
        $ga = new \PHPGangsta_GoogleAuthenticator();
        $obj->secret->SetValue($ga->createSecret(self::SECRET_LENGTH));
        
        if ($account->hasCrypto()) $obj->InitializeCrypto();
        
        return $obj;
    }
    
    /** Returns the (decrypted) OTP secret */
    private function GetSecret() : string
    {
        if (!isset($this->raw_secret))
        {
            if ($this->hasCrypto())
            {
                $this->raw_secret = $this->GetAccount()->DecryptSecret(
                    $this->secret->GetValue(), $this->nonce->TryGetValue());
            }
            else $this->raw_secret = $this->secret->GetValue();
        }
        
        return $this->raw_secret;
    }
    
    public function NotifyDeleted() : void
    {
        UsedToken::DeleteByTwoFactor($this->database, $this); // TODO not gonna work! foreign keys must be removed first!
        
        parent::NotifyDeleted();
    }
    
    /** Stores the secret as encrypted by the owner */
    public function InitializeCrypto() : self
    {
        $nonce = Crypto::GenerateSecretNonce();
        
        $secret_crypt = $this->GetAccount()->EncryptSecret(
            $this->GetSecret(), $nonce);  
        
        $this->nonce->SetValue($nonce);
        $this->secret->SetValue($secret_crypt);
        
        return $this;
    }
    
    /** Stores the secret as plaintext (not encrypted) */
    public function DestroyCrypto() : self
    {
        $this->secret->SetValue($this->GetSecret());
        $this->nonce->SetValue(null);
        return $this;
    }
    
    /** Checks and returns whether the given twofactor code is valid */
    public function CheckCode(string $code) : bool
    {
        UsedToken::PruneOldCodes($this->database);

        foreach (UsedToken::LoadByTwoFactor($this->database, $this) as $usedtoken)
        {
            if ($usedtoken->GetCode() === $code) return false;
        }

        $ga = new \PHPGangsta_GoogleAuthenticator();
        
        if (!$ga->verifyCode($this->GetSecret(), $code, self::TIME_TOLERANCE)) return false;

        $this->valid->SetValue(true);
        $this->date_used->SetTimeNow();
        
        UsedToken::Create($this->database, $this, $code);       
        
        return true;
    }
    
    /** Returns a Google URL for viewing a QR code of the OTP secret */
    public function GetURL() : string
    {
        $ga = new \PHPGangsta_GoogleAuthenticator();
        
        return $ga->getQRCodeGoogleUrl("Andromeda", $this->GetSecret());
    }
    
    /**
     * Returns a printable client object for this twofactor
     * @param bool $secret if true, show the OTP secret
     * @return array<mixed> `{id:id, comment:?string, date_created:float, date_used:?float}` \
        if $secret, add `{secret:string, qrcodeurl:string}`
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'comment' => $this->GetComment(),
            'date_created' => $this->date_created->GetValue(),
            'date_used' => $this->date_used->TryGetValue()
        );
        
        if ($secret) 
        {
            $data['secret'] = $this->GetSecret();
            $data['qrcodeurl'] = $this->GetURL();
        }

        return $data;
    }
}
