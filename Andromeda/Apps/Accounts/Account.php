<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\{Crypto, EmailRecipient, Utilities};
use Andromeda\Core\Exceptions\DecryptionFailedException;
use Andromeda\Core\Database\{FieldTypes, FieldTypes\NullBaseField, ObjectDatabase, TableTypes, QueryBuilder};
use Andromeda\Core\Database\Exceptions\CounterOverLimitException;

use Andromeda\Apps\Accounts\AuthSource\External;
use Andromeda\Apps\Accounts\Crypto\{KeySource, IKeySource};
use Andromeda\Apps\Accounts\Crypto\Exceptions\{CryptoAlreadyInitializedException, CryptoNotInitializedException, CryptoUnlockRequiredException};
use Andromeda\Apps\Accounts\Resource\{Contact, EmailContact, Client, RecoveryKey, Session, TwoFactor};

/**
 * Class representing a user account in the database
 * 
 * Can inherit properties from groups.  Can have any number
 * of registered clients, can have registered two factor, 
 * can provide secret-key crypto services, provides contact info
 * 
 * @phpstan-import-type PolicyBaseJ from PolicyBase
 * @phpstan-import-type RecoveryKeyJ from RecoveryKey
 * @phpstan-import-type TwoFactorJ from TwoFactor
 * @phpstan-import-type ContactJ from Contact
 * @phpstan-import-type ClientJ from Client
 * @phpstan-type PublicAccountJ array{id:string, username:string, dispname:?string, contacts:list<string>}
 * @phpstan-type UserAccountJ array{id:string, username:string, dispname:?string, crypto?:bool, policy?:PolicyBaseJ, date_created?:float, date_loggedon?:?float, date_active?:?float, date_passwordset?:?float, recoverykeys?:array<string,RecoveryKeyJ>, twofactors?:array<string,TwoFactorJ>, contacts?:array<string,ContactJ>, clients?:array<string,ClientJ>}
 * @phpstan-type AdminAccountJ array{comment:?string, date_modified:?float, groups:list<string>, policy_from?:array{session_timeout:?string, client_timeout:?string, max_password_age:?string, limit_clients:?string, limit_contacts:?string, limit_recoverykeys:?string, admin:?string, disabled:?string, forcetf:?string, allowcrypto:?string, userdelete:?string, account_search:?string, group_search:?string}}
 */
class Account extends PolicyBase implements IKeySource
{
    use KeySource 
    { 
        isCryptoAvailable as BaseIsCryptoAvailable;
        EncryptSecret as BaseEncryptSecret;
        DecryptSecret as BaseDecryptSecret;
        DestroyCrypto as BaseDestroyCrypto;
    }

    use TableTypes\TableNoChildren;

    /** The primary username of the account */
    private FieldTypes\StringType $username;
    /** The user-set full descriptive name of the user */
    private FieldTypes\NullStringType $fullname;
    /** The password hash used for the account (null if external) */
    private FieldTypes\NullStringType $password;
    /** 
     * @var FieldTypes\NullObjectRefT<External> 
     * The external auth source used for the account 
     */
    private FieldTypes\NullObjectRefT $authsource;
    /** The date the account last had its password changed */
    private FieldTypes\NullTimestamp $date_passwordset;
    /** The date the account last had a new client/session created */
    private FieldTypes\NullTimestamp $date_loggedon;
    /** The date the account last was active (made any request) */
    private FieldTypes\NullTimestamp $date_active;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->username = $fields[] = new FieldTypes\StringType('username');
        $this->fullname = $fields[] = new FieldTypes\NullStringType('fullname');
        $this->password = $fields[] = new FieldTypes\NullStringType('password');
        $this->authsource = $fields[] = new FieldTypes\NullObjectRefT(External::class, 'authsource');
        $this->date_passwordset = $fields[] = new FieldTypes\NullTimestamp('date_passwordset');
        $this->date_loggedon = $fields[] = new FieldTypes\NullTimestamp('date_loggedon');
        $this->date_active = $fields[] = new FieldTypes\NullTimestamp('date_active', saveOnRollback:true);
        $this->RegisterFields($fields, self::class);
        
        $this->KeySourceCreateFields();
        parent::CreateFields();
    }
    
    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'username';
        return $ret;
    }
    
    public const DISABLE_PERMANENT = 1;
    public const DISABLE_PENDING_CONTACT = 2;
    
    public const DEFAULT_SEARCH_MAX = 3;
    
    /** Returns the account's username */
    public function GetUsername() : string  { return $this->username->GetValue(); }
    
    /** Returns the account's full name if set, else its username */
    public function GetDisplayName() : string { return $this->fullname->TryGetValue() ?? $this->GetUsername(); }
    
    /** 
     * Sets the account's full name
     * @return $this
     */
    public function SetFullName(string $name) : self { $this->fullname->SetValue($name); return $this; }
    
    /**
     * Loads the groups that the account implicitly belongs to
     * @return array<string, Group> groups indexed by ID
     */
    public function GetDefaultGroups() : array
    {
        $retval = array();
        
        $default = Config::GetInstance($this->database)->GetDefaultGroup();
        if ($default !== null) $retval[$default->ID()] = $default;
        
        $authman = $this->GetAuthSource();
        if ($authman instanceof AuthSource\External) 
        {
            $default = $authman->GetDefaultGroup();
            if ($default !== null) $retval[$default->ID()] = $default;
        }
        
        return $retval;
    }
    
    /**
     * Returns a list of all groups that the account belongs to
     * @return array<string, Group> groups indexed by ID
     */
    public function GetGroups() : array { return array_merge($this->GetDefaultGroups(), $this->GetJoinedGroups()); }
    
    /**
     * Returns a list of all groups that the account explicitly belongs to
     * @return array<string, Group> groups indexed by ID
     */
    public function GetJoinedGroups() : array { return GroupJoin::LoadGroups($this->database, $this); }
    
    /** Returns true if the account is a member of the given group */
    public function HasGroup(Group $group) : bool { return array_key_exists($group->ID(), $this->GetGroups()); }
    
    /**
     * Returns a field from an account or group based on group inheritance
     * @template T of NullBaseField
     * @param callable(PolicyBase):T $getfield function to get the field
     * @return ?T field from correct source or null if unset
     */
    protected function GetInheritableField(callable $getfield) : ?NullBaseField
    {
        $actfield = $getfield($this);
        if ($actfield->TryGetValue() !== null) return $actfield;

        $actfield->TryGetValue();

        /** @var ?T */
        $grpfield = null;
        $priority = null;

        foreach ($this->GetGroups() as $tempgroup)
        {
            $tempfield = $getfield($tempgroup);
            $temppriority = $tempgroup->GetPriority();
            if (($tempfield->TryGetValue() !== null) && 
                ($priority === null || $temppriority > $priority))
            {
                $grpfield = $tempfield;
                $priority = $temppriority;
            }
        }

        return ($grpfield !== null) ? $grpfield : null;
    }

    /**
    * Returns a an account or group based on group inheritance
    * @template T of NullBaseField
    * @param callable(PolicyBase):T $getfield function to get the field
    * @return ?PolicyBase source of field or null if unset
    */
    protected function GetInheritableSource(callable $getfield) : ?PolicyBase
    {
        $actfield = $getfield($this);
        if ($actfield->TryGetValue() !== null) return $this;

        /** @var ?Group */
        $group = null;
        $priority = null;

        foreach ($this->GetGroups() as $tempgroup)
        {
            $tempfield = $getfield($tempgroup);
            $temppriority = $tempgroup->GetPriority();
            if (($tempfield->TryGetValue() !== null) && 
                ($priority === null || $temppriority > $priority))
            {
                $group = $tempgroup;
                $priority = $temppriority;
            }
        }

        return ($group !== null) ? $group : null;
    }

    /** Returns the auth source the account authenticates against */
    public function GetAuthSource() : AuthSource\IAuthSource
    { 
        $authsource = $this->authsource->TryGetObject();
        if ($authsource !== null) return $authsource;
        else return (new AuthSource\Local());
    }
    
    /**
     * Returns an array of clients registered to the account
     * @return array<string, Client> clients indexed by ID
     */
    public function GetClients() : array { return Client::LoadByAccount($this->database, $this); }
    
    /**
     * Returns an array of sessions registered to the account
     * @return array<string, Session> sessions indexed by ID
     */
    public function GetSessions() : array { return Session::LoadByAccount($this->database, $this); }
    
    /** Returns true if the account has any recovery keys */
    public function HasRecoveryKeys() : bool { return RecoveryKey::CountByAccount($this->database, $this) > 0; }

    /**
     * Returns an array of recovery keys for the account
     * @return array<string, RecoveryKey> keys indexed by ID
     */
    private function GetRecoveryKeys() : array { return RecoveryKey::LoadByAccount($this->database, $this); }

    /** Returns true if the account has any two factor */
    public function HasTwoFactor() : bool { return TwoFactor::CountByAccount($this->database, $this) > 0; }

    /**
     * Returns an array of twofactors for the account
     * @return array<string, TwoFactor> twofactors indexed by ID
     */
    private function GetTwoFactors() : array { return TwoFactor::LoadByAccount($this->database, $this); }
    
    /** True if two factor should be required to create a session even for a pre-existing client */
    public function GetForceUseTwoFactor() : bool
    { 
        $default = false;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->forcetf; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** True if account-based server-side crypto is allowed */
    public function GetAllowCrypto() : bool 
    { 
        $default = true;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->allowcrypto; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** Returns 0 if account search is disabled, or N if up to N matches are allowed */
    public function GetAllowAccountSearch() : int
    { 
        $default = self::DEFAULT_SEARCH_MAX;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->account_search; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }

    /** Returns 0 if group search is disabled, or N if up to N matches are allowed */
    public function GetAllowGroupSearch() : int
    { 
        $default = self::DEFAULT_SEARCH_MAX;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->group_search; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** Returns true if the user is allowed to delete their account */
    public function GetAllowUserDelete() : bool
    { 
        $default = true;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->userdelete; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** True if this account has administrator privileges */
    public function isAdmin() : bool
    { 
        $default = false;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->admin; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }

    /** @return int enum code for account disable */
    public function isDisabled() : int
    {
        $default = 0;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->disabled; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** Sets this account's admin-status to the given value */
    public function SetAdmin(?bool $val) : self { $this->admin->SetValue($val); return $this; }
    
    /** Sets the account's disabled status to the given enum value */
    public function SetDisabled(?int $val = self::DISABLE_PERMANENT) : self { $this->disabled->SetValue($val); return $this; }    
    
    /** Gets the timestamp when this user was last active */
    public function GetActiveDate() : ?float { return $this->date_active->TryGetValue(); }
    
    /** Sets the last-active timestamp to now */
    public function SetActiveDate() : self { $this->date_active->SetTimeNow(); return $this; }

    /** Gets the timestamp when this user last created a session */
    public function GetLoggedonDate() : ?float { return $this->date_loggedon->TryGetValue(); }
    
    /** Sets the timestamp of last-login to now */
    public function SetLoggedonDate() : self { $this->date_loggedon->SetTimeNow(); return $this; }
    
    /** Returns the timestamp that the account's password was last set */
    private function GetPasswordDate() : ?float { return $this->date_passwordset->TryGetValue(); }

    /** Sets the account's last password change date to 0, potentially forcing a password reset */
    public function ResetPasswordDate() : self { $this->date_passwordset->SetValue(0); return $this; }
    
    /** Returns the maximum allowed time since a client was last active for it to be valid */
    public function GetClientTimeout() : ?int
    { 
        $default = null;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->client_timeout; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** Returns the maximum allowed time since a session was last active for it to be valid */
    public function GetSessionTimeout() : ?int
    { 
        $default = null;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->session_timeout; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** Returns the maximum allowed age of the account's password */
    private function GetMaxPasswordAge() : ?int
    {
        $default = null;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->max_password_age; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }
    
    /** Returns the maximum allowed number of sessions */
    private function GetLimitClients() : ?int
    {
        $default = null;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->limit_clients; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }

    /** Returns the maximum allowed number of contacts */
    private function GetLimitContacts() : ?int
    {
        $default = null;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->limit_contacts; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }

    /** Returns the maximum allowed number of recoverykeys */
    private function GetLimitRecoveryKeys() : ?int
    {
        $default = null;
        $f = $this->GetInheritableField(function(PolicyBase $b){ return $b->limit_recoverykeys; });
        return ($f !== null) ? $f->TryGetValue() ?? $default : $default;
    }

    /** 
     * Checks that the current client count + delta is within the limit 
     * @throws CounterOverLimitException if over the limit
     */
    public function CheckLimitClients(int $delta = 1) : void
    {
        if (($limit = $this->GetLimitClients()) === null) return;
        if (Client::CountByAccount($this->database, $this)+$delta > $limit)
            throw new CounterOverLimitException('clients');
    }

    /** 
     * Checks that the current contacts count + delta is within the limit 
     * @throws CounterOverLimitException if over the limit
     */
    public function CheckLimitContacts(int $delta = 1) : void
    {
        if (($limit = $this->GetLimitContacts()) === null) return;
        if (Contact::CountByAccount($this->database, $this)+$delta > $limit)
            throw new CounterOverLimitException('contacts');
    }

    /** 
     * Checks that the current recovery key count + delta is within the limit 
     * @throws CounterOverLimitException if over the limit
     */
    public function CheckLimitRecoveryKeys(int $delta = 1) : void
    {
        if (($limit = $this->GetLimitRecoveryKeys()) === null) return;
        if (RecoveryKey::CountByAccount($this->database, $this)+$delta > $limit)
            throw new CounterOverLimitException('recoverykeys');
    }

    /**
     * Attempts to load an account with the given username
     * @param ObjectDatabase $database database reference
     * @param string $username username to load for
     * @return ?static loaded account or null if not found
     */
    public static function TryLoadByUsername(ObjectDatabase $database, string $username) : ?self
    {
        return $database->TryLoadUniqueByKey(static::class, 'username', $username);
    }

    /**
     * Returns all accounts whose username, fullname or contacts match the given info
     * @param ObjectDatabase $database database reference
     * @param string $info username/other info to match by (wildcard)
     * @param positive-int $limit max # to load - returns nothing if exceeded
     * @return array<string, self>
     */
    public static function LoadAllMatchingInfo(ObjectDatabase $database, string $info, int $limit) : array
    {
        $info = QueryBuilder::EscapeWildcards($info).'%'; // search by prefix
        
        $q1 = new QueryBuilder(); 
        $loaded = $database->LoadObjectsByQuery(static::class, $q1->Where($q1->Like('username',$info,true))->Limit($limit+1)); // +1 to detect going over
        if (count($loaded) > $limit) return array(); // not specific enough
        if (($limit -= count($loaded)) <= 0) return $loaded;
        assert($limit >= 0); // guaranteed by line above
        
        $q2 = new QueryBuilder(); 
        $loaded += $database->LoadObjectsByQuery(static::class, $q2->Where($q2->Like('fullname',$info,true))->Limit($limit+1));
        if (count($loaded) > $limit) return array(); // not specific enough
        if (($limit -= count($loaded)) <= 0) return $loaded;
        assert($limit >= 0); // guaranteed by line above
        
        $loaded += Contact::LoadAccountsMatchingValue($database, $info, $limit+1);
        if (count($loaded) > $limit) return array(); // not specific enough
        
        return $loaded;
    }
    
    /**
     * Returns an array of all accounts based on the given auth source
     * @param ObjectDatabase $database database reference
     * @param AuthSource\External $authsrc authentication source
     * @return array<string, static> accounts indexed by ID
     */
    public static function LoadByAuthSource(ObjectDatabase $database, AuthSource\External $authsrc) : array
    {
        return $database->LoadObjectsByKey(static::class, 'authsource', $authsrc->ID());
    }
    
    /**
     * Deletes all accounts using the given auth source
     * @param ObjectDatabase $database database reference
     * @param AuthSource\External $authsrc authentication source
     */
    public static function DeleteByAuthSource(ObjectDatabase $database, AuthSource\External $authsrc) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'authsource', $authsrc->ID());
    }   
    
    /**
     * Returns all contacts for this account
     * @param bool $valid if true return only validated contacts
     * @return array<string, Contact> contacts indexed by ID
     */
    public function GetContacts(bool $valid = true) : array
    {
        $contacts = Contact::LoadByAccount($this->database, $this);
        
        if ($valid) $contacts = array_filter($contacts, 
            function(Contact $contact){ return $contact->GetIsValid(); });
        
        return $contacts;
    }
    
    /**
     * Returns EmailReceipient objects for all email contacts
     * @return array<string, EmailRecipient>
     */
    public function GetContactEmails() : array
    {
        $emails = array_filter($this->GetContacts(), 
            function(Contact $contact){ return $contact instanceof EmailContact; });
        
        return array_map(function(EmailContact $contact){ 
            return $contact->GetAsEmailRecipient(); }, $emails);
    }

    /**
     * Sends a message to all of this account's valid contacts
     * @see Contact::SendMessageMany()
     */
    public function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void
    {
        Contact::SendMessageMany($subject, $html, $plain, $this->GetContacts(), false, $from);
    }    

    /** Sets this account to enabled if it was disabled pending a valid contact */
    public function NotifyValidContact() : self
    {
        if ($this->isDisabled() === self::DISABLE_PENDING_CONTACT)
            $this->SetDisabled(null);
        
        return $this;
    }
        
    /**
     * Creates a new user account
     * @param ObjectDatabase $database database reference
     * @param string $username the account's username
     * @param string $password the account's password, if not external auth
     */
    public static function Create(ObjectDatabase $database, string $username, string $password) : static
    {
        $account = static::CreateCommon($database, $username);
        $account->ChangePassword($password);
        return $account;
    }
        
    /**
     * Creates a new external user account
     * @param ObjectDatabase $database database reference
     * @param string $username the account's username
     * @param AuthSource\External $source the auth source for the account
     */
    public static function CreateExternal(ObjectDatabase $database, string $username, AuthSource\External $source) : static
    {
        $account = static::CreateCommon($database, $username);
        $account->authsource->SetObject($source);
        return $account;
    }

    /**
     * Creates a new user account (no password set)
     * @param ObjectDatabase $database database reference
     * @param string $username the account's username
     */
    protected static function CreateCommon(ObjectDatabase $database, string $username) : static
    {
        $account = $database->CreateObject(static::class);
        $account->date_created->SetTimeNow();
        $account->username->SetValue($username);

        foreach ($account->GetDefaultGroups() as $group)
            GroupJoin::RunGroupChangeHandlers($database, $account, $group, true);

        return $account;
    }
    
    /** @var array<callable(ObjectDatabase, self): void> */
    private static array $delete_handlers = array();
    
    /** 
     * Registers a function to be run when an account is deleted 
     * @param callable(ObjectDatabase, self): void $func
     */
    public static function RegisterDeleteHandler(callable $func) : void { self::$delete_handlers[] = $func; }
    
    public function NotifyPreDeleted() : void
    {
        Client::DeleteByAccount($this->database, $this);
        Contact::DeleteByAccount($this->database, $this);
        TwoFactor::DeleteByAccount($this->database, $this);
        RecoveryKey::DeleteByAccount($this->database, $this);
        GroupJoin::DeleteByAccount($this->database, $this);
        
        foreach ($this->GetDefaultGroups() as $group)
            GroupJoin::RunGroupChangeHandlers($this->database, $this, $group, false);
        
        foreach (self::$delete_handlers as $func) 
            $func($this->database, $this);
    }

    /** Returns true if the account has a validated two factor and recovery keys */
    public function HasValidTwoFactor() : bool
    {
        foreach ($this->GetTwoFactors() as $twofactor) {
            if ($twofactor->GetIsValid()) return true; }
        return false;
    }    
    
    /**
     * Checks a two factor code
     * @param string $code the given twofactor code
     * @param bool $force if true, accept non-valid twofactor sources
     * @return bool true if there is a valid twofactor (or force) and the code is valid
     */
    public function CheckTwoFactor(string $code, bool $force = false) : bool
    {
        if (!$force && !$this->HasValidTwoFactor()) return false;  
        
        foreach ($this->GetTwoFactors() as $twofactor) { 
            if ($twofactor->CheckCode($code)) return true; }        
        return false;
    }
    
    /** Returns true if the given recovery key matches one (and they exist) */
    public function CheckRecoveryKey(string $fullkey) : bool
    {
        $obj = RecoveryKey::TryLoadByFullKey($this->database, $fullkey, $this);
        return ($obj === null) ? false : $obj->CheckFullKey($fullkey);
    }
    
    /** Returns true if the given password is correct for this account */
    public function VerifyPassword(string $password) : bool
    {
        return $this->GetAuthSource()->VerifyAccountPassword($this, $password);
    }    
    
    /** Returns true if the account's password is not out of date, or is using external auth */
    public function CheckPasswordAge() : bool
    {
        if (!($this->GetAuthSource() instanceof AuthSource\Local)) return true;
        
        $date = $this->GetPasswordDate(); 
        $max = $this->GetMaxPasswordAge();
        
        if ($date <= 0) return false;
        else return ($max === null || $this->database->GetTime()-$date < $max);
    }
    
    /** 
     * Re-keys the account's crypto if it exists, and re-hashes its password (if using local auth)
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     */
    public function ChangePassword(string $new_password) : Account
    {
        if ($this->hasCrypto())
            $this->InitializeCrypto($new_password, rekey:true); // keeps same key
        
        if ($this->GetAuthSource() instanceof AuthSource\Local)
            AuthSource\Local::SetPassword($this, $new_password);

        $this->date_passwordset->SetTimeNow(); 
        return $this;
    }
    
    /** Gets the account's password hash (null if external auth) */
    public function TryGetPasswordHash() : ?string { return $this->password->TryGetValue(); }
    
    /** Sets the account's password hash to the given value */
    public function SetPasswordHash(string $hash) : self { $this->password->SetValue($hash); return $this; }
    
    /** Alternate available key source */
    private IKeySource $keysource;

    /**
     * Sets the given key source as an alternate key source (must already be unlocked)
     * @return $this
     */
    public function SetCryptoKeySource(IKeySource $source) : self {
        $this->keysource = $source; return $this; }
    
    /** Returns true if crypto has been unlocked in this request and is available for operations */
    public function isCryptoAvailable() : bool { 
        return isset($this->keysource) || $this->BaseIsCryptoAvailable(); }
    
    /** 
     * Unlocks crypto from the given account password
     * @throws DecryptionFailedException if decryption fails (password is wrong)
     * @throws CryptoNotInitializedException if no key material exists
     */
    public function UnlockCryptoFromPassword(string $password) : self {
        return $this->UnlockCrypto($password); }

    /**
     * Encrypts a value using the account's crypto
     * @param string $data the plaintext to be encrypted
     * @param string $nonce the nonce to use for crypto
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     * @return string the ciphertext encrypted with the account's secret key
     */
    public function EncryptSecret(string $data, string $nonce) : string
    {
        if (isset($this->keysource))
            return $this->keysource->EncryptSecret($data, $nonce);
        return $this->BaseEncryptSecret($data, $nonce);
    }
    
    /**
     * Decrypts a value using the account's crypto
     * @param string $data the ciphertext to be decrypted
     * @param string $nonce the nonce used for encryption
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     * @return string the plaintext decrypted with the account's key
     */
    public function DecryptSecret(string $data, string $nonce) : string
    {
        if (isset($this->keysource))
            return $this->keysource->DecryptSecret($data, $nonce);
        return $this->BaseDecryptSecret($data, $nonce);
    }

    /** @var array<callable(ObjectDatabase, self, bool): void> */
    private static array $crypto_handlers = array();
    
    /** 
     * Registers a function to be run when crypto is enabled/disabled on the account
     * @param callable(ObjectDatabase, self, bool): void $func
     */
    public static function RegisterCryptoHandler(callable $func) : void { self::$crypto_handlers[] = $func; }

    /**
     * Initializes secret-key crypto on the account
     * 
     * Accounts have a master-key for secret-key crypto. The master-key is generated randomly
     * and then wrapped using a key derived from the user's password and a nonce/salt.
     * Requests that require use of account crypto therefore must have the user's password
     * or some other key source material transmitted in each request.  The crypto is of course
     * done server-side, but the raw keys are only ever available in memory, not in the database.
     * @param string $password the password to derive keys from
     * @param bool $rekey true if crypto exists and we want to keep the same master key
     * @throws CryptoUnlockRequiredException if crypto is not unlocked
     * @throws CryptoAlreadyInitializedException if crypto already exists and not re-keying
     */
    public function InitializeCrypto(string $password, bool $rekey = false) : self
    {
        $this->BaseInitializeCrypto($password, rekey:$rekey);

        foreach ($this->GetTwoFactors() as $twofactor) 
            $twofactor->InitializeCrypto();
        
        foreach (self::$crypto_handlers as $func) 
            $func($this->database, $this, true);
        
        return $this;
    }
    
    /** Disables crypto on the account (and all subobjects), decrypting things, stripping all keys */
    public function DestroyCrypto() : self
    {
        foreach (self::$crypto_handlers as $func) 
            $func($this->database, $this, false);

        foreach ($this->GetSessions() as $session)         $session->DestroyCrypto();
        foreach ($this->GetTwoFactors() as $twofactor)     $twofactor->DestroyCrypto();
        foreach ($this->GetRecoveryKeys() as $recoverykey) $recoverykey->DestroyCrypto();

        $this->BaseDestroyCrypto();
        return $this;
    }

    /**
     * Gets the account's policy settings (resolved inheritance)
     * @return PolicyBaseJ
     */
    public function GetPolicyClientObject() : array
    {
        return array(
            'session_timeout' => $this->GetSessionTimeout(),
            'client_timeout' => $this->GetClientTimeout(),
            'max_password_age' => $this->GetMaxPasswordAge(),
            'limit_clients' => $this->GetLimitClients(),
            'limit_contacts' => $this->GetLimitContacts(),
            'limit_recoverykeys' => $this->GetLimitRecoveryKeys(),

            'admin' => $this->isAdmin(),
            'disabled' => $this->isDisabled(),
            'forcetf' => $this->GetForceUseTwoFactor(),
            'allowcrypto' => $this->GetAllowCrypto(),
            'userdelete' => $this->GetAllowUserDelete(),
            'account_search' => $this->GetAllowAccountSearch(),
            'group_search' => $this->GetAllowGroupSearch()
        );
    }

    /**
     * Gets this account as a printable object (public)
     * @return PublicAccountJ
     */
    public function GetPublicClientObject() : array
    {
        $contacts = array_values(array_filter($this->GetContacts(), 
            function(Contact $c){ return $c->GetIsPublic(); }));

        return array(
            'id' => $this->ID(),
            'username' => $this->username->GetValue(),
            'dispname' => $this->fullname->TryGetValue(),
            'contacts' => array_map(function(Contact $c){ return $c->GetAddress(); }, $contacts)
        );
    }

    /**
     * Gets this account as a printable object (user)
     * @param bool $full if true, show recoverykeys/twofactors/contacts/clients
     * @return UserAccountJ
     */
    public function GetUserClientObject(bool $full = false) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'username' => $this->username->GetValue(),
            'dispname' => $this->fullname->TryGetValue()
        );

        if ($full) $retval += array(
            'policy' => $this->GetPolicyClientObject(),
            'crypto' => ($this->master_key->TryGetValue() !== null),

            'date_created' => $this->date_created->GetValue(),
            'date_loggedon' => $this->date_loggedon->TryGetValue(),
            'date_active' => $this->date_active->TryGetValue(),
            'date_passwordset' => $this->date_passwordset->TryGetValue(),

            'recoverykeys' => array_map(function(RecoveryKey $r){ return $r->GetClientObject(); }, $this->GetRecoveryKeys()),
            'twofactors' => array_map(function(TwoFactor $t){ return $t->GetClientObject(); }, $this->GetTwoFactors()),
            'contacts' => array_map(function(Contact $c){ return $c->GetClientObject(); }, $this->GetContacts(false)),
            'clients' => array_map(function(Client $c){ return $c->GetClientObject(); }, $this->GetClients()),
        );

        return $retval;
    }

    /**
     * Gets this account as a printable object (admin)
     * @param bool $full if true, show permissions, twofactors, clients, contacts
     * @return \Union<UserAccountJ, AdminAccountJ>
     */
    public function GetAdminClientObject(bool $full = false) : array
    {
        $retval = $this->GetUserClientObject($full);

        $retval += array(
            'comment' => $this->comment->TryGetValue(),
            'date_modified' => $this->date_modified->TryGetValue(),
            'groups' => array_keys($this->GetGroups())
        );

        if ($full)
        {
            $session_timeout = $this->GetInheritableSource(function(PolicyBase $b){ return $b->session_timeout; });
            $client_timeout = $this->GetInheritableSource(function(PolicyBase $b){ return $b->client_timeout; });
            $max_password_age = $this->GetInheritableSource(function(PolicyBase $b){ return $b->max_password_age; });
            $limit_clients = $this->GetInheritableSource(function(PolicyBase $b){ return $b->limit_clients; });
            $limit_contacts = $this->GetInheritableSource(function(PolicyBase $b){ return $b->limit_contacts; });
            $limit_recoverykeys = $this->GetInheritableSource(function(PolicyBase $b){ return $b->limit_recoverykeys; });

            $admin = $this->GetInheritableSource(function(PolicyBase $b){ return $b->admin; });
            $disabled = $this->GetInheritableSource(function(PolicyBase $b){ return $b->disabled; });
            $forcetf = $this->GetInheritableSource(function(PolicyBase $b){ return $b->forcetf; });
            $allowcrypto = $this->GetInheritableSource(function(PolicyBase $b){ return $b->allowcrypto; });
            $userdelete = $this->GetInheritableSource(function(PolicyBase $b){ return $b->userdelete; });
            $account_search = $this->GetInheritableSource(function(PolicyBase $b){ return $b->account_search; });
            $group_search = $this->GetInheritableSource(function(PolicyBase $b){ return $b->group_search; });

            $retval['policy_from'] = array(
                'session_timeout' => ($session_timeout !== null) ? (string)$session_timeout : null,
                'client_timeout' => ($client_timeout !== null) ? (string)$client_timeout : null,
                'max_password_age' => ($max_password_age !== null) ? (string)$max_password_age : null,
                'limit_clients' => ($limit_clients !== null) ? (string)$limit_clients : null,
                'limit_contacts' => ($limit_contacts !== null) ? (string)$limit_contacts : null,
                'limit_recoverykeys' => ($limit_recoverykeys !== null) ? (string)$limit_recoverykeys : null,
                
                'admin' => ($admin !== null) ? (string)$admin : null,
                'disabled' => ($disabled !== null) ? (string)$disabled : null,
                'forcetf' => ($forcetf !== null) ? (string)$forcetf : null,
                'allowcrypto' => ($allowcrypto !== null) ? (string)$allowcrypto : null,
                'userdelete' => ($userdelete !== null) ? (string)$userdelete : null,
                'account_search' => ($account_search !== null) ? (string)$account_search : null,
                'group_search' => ($group_search !== null) ? (string)$group_search : null,
            );
        }

        return $retval;
    }
}
