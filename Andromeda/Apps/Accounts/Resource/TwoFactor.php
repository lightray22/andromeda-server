<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

use Andromeda\Apps\Accounts\Account;

/** 
 * Describes an OTP twofactor authentication source for an account 
 * 
 * Accounts can have > 1 of these so the user is able to use multiple devices.
 * If account crypto is available, the secret is stored encrypted in the database.
 * 
 * @phpstan-type TwoFactorJ array{id:string, comment:?string, date_created:float, date_used:?float, secret?:string, qrcodeurl?:string}
 */
class TwoFactor extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    /** Date the twofactor was created */
    protected FieldTypes\Timestamp $date_created;
    /** The optional user label for this twofactor */
    protected FieldTypes\NullStringType $comment;
    /** The twofactor secret for generating codes */
    protected FieldTypes\StringType $secret;
    /** The nonce if the secret is encrypted */
    protected FieldTypes\NullStringType $nonce;
    /** True if this twofactor has been validated */
    protected FieldTypes\BoolType $valid;
    /** The timestamp this twofactor was last used */
    protected FieldTypes\NullTimestamp $date_used;
    /**
     * The account this twofactor is for
     * @var FieldTypes\ObjectRefT<Account> 
     */
    protected FieldTypes\ObjectRefT $account;
    
    /** The raw secret in case it's encrypted */
    protected string $raw_secret;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->comment =      $fields[] = new FieldTypes\NullStringType('comment');
        $this->secret =       $fields[] = new FieldTypes\StringType('secret');
        $this->nonce =        $fields[] = new FieldTypes\NullStringType('nonce');
        $this->valid =        $fields[] = new FieldTypes\BoolType('valid', default:false);
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_used =    $fields[] = new FieldTypes\NullTimestamp('date_used');
        $this->account =      $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** The length of the OTP secret (bytes) */
    protected const SECRET_LENGTH = 32; 
    
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
     * @return ?static the loaded object or null if not found 
     */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->Equals('id',$id));
        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($w));
    }
    
    /** Count two factors for a given account */
    public static function CountByAccount(ObjectDatabase $database, Account $account) : int
    { 
        return $database->CountObjectsByKey(static::class, 'account', $account->ID());
    }

    /** 
     * Load all two factors for a given account 
     * @return array<string, static>
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    { 
        return $database->LoadObjectsByKey(static::class, 'account', $account->ID());
    }

    /** 
     * Deletes all two factors for the given account 
     * @return int the number of deleted two factors
     */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }
    
    /** Creates and returns a new twofactor object for the given account */
    public static function Create(ObjectDatabase $database, Account $account, ?string $comment = null) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->comment->SetValue($comment);
        $obj->account->SetObject($account);
        
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $obj->secret->SetValue($google2fa->generateSecretKey(self::SECRET_LENGTH));
        
        if ($account->hasCrypto()) $obj->InitializeCrypto();
        
        return $obj;
    }
    
    /** Returns the (decrypted) OTP secret */
    protected function GetSecret() : string
    {
        if (!isset($this->raw_secret))
        {
            $nonce = $this->nonce->TryGetValue();
            if ($nonce !== null) // hasCrypto
            {
                $this->raw_secret = $this->GetAccount()->DecryptSecret(
                    $this->secret->GetValue(), $nonce);
            }
            else $this->raw_secret = $this->secret->GetValue();
        }
        
        return $this->raw_secret;
    }
    
    /** Returns a URL for viewing a QR code of the OTP secret */
    protected function GetSecretURL() : string
    {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        return $google2fa->getQRCodeUrl('Andromeda',$this->GetAccount()->GetUsername(),$this->GetSecret());
    }
    
    public function NotifyPreDeleted() : void
    {
        UsedToken::DeleteByTwoFactor($this->database, $this);
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
            if ($usedtoken->GetCode() === $code) return false;

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        if ($google2fa->verifyKey($this->GetSecret(), $code, self::TIME_TOLERANCE) === false)
            return false; // code failed!

        $this->valid->SetValue(true);
        $this->date_used->SetTimeNow();
        
        UsedToken::Create($this->database, $this, $code)->Save();
        
        return true;
    }
    
    /**
     * Returns a printable client object for this twofactor
     * @param bool $secret if true, show the OTP secret
     * @return TwoFactorJ
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
            $data['qrcodeurl'] = $this->GetSecretURL();
        }

        return $data;
    }
}
