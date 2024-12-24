<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\{AuthObject, AccountKeySource, IKeySource, Exceptions\RawKeyNotAvailableException};

/**
 * Implements an account session, the primary implementor of authentication
 *
 * Also stores a copy of the account's master key, encrypted by the session key.
 * This allowed account crypto to generally be unlocked for any user command.
 * 
 * @phpstan-type SessionJ array{id:string, client:string, date_created:float, date_active:?float, authkey?:string}
 */
class Session extends BaseObject implements IKeySource
{
    use TableTypes\TableNoChildren;
    
    use AccountKeySource { UnlockCrypto as BaseUnlockCrypto; }
    use AuthObject { CheckKeyMatch as BaseCheckKeyMatch; }
    
    /** The date this session was created */
    private FieldTypes\Timestamp $date_created;
    /** The date this session was used for a request or null */
    private FieldTypes\NullTimestamp $date_active;
    /** 
     * The client that owns this session 
     * @var FieldTypes\ObjectRefT<Client>
     */
    private FieldTypes\ObjectRefT $client;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_active =  $fields[] = new FieldTypes\NullTimestamp('date_active', saveOnRollback:true);
        $this->client =       $fields[] = new FieldTypes\ObjectRefT(Client::class, 'client');
        
        $this->RegisterFields($fields, self::class);
        
        $this->AuthObjectCreateFields();
        $this->AccountKeySourceCreateFields();
        
        parent::CreateFields();
    }
    
    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'client';
        return $ret;
    }
    
    /** Returns the client that owns this session */
    public function GetClient() : Client { return $this->client->GetObject(); }
    
    /** Create a new session for the given account and client */
    public static function Create(ObjectDatabase $database, Account $account, Client $client) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->client->SetObject($client);

        $client->SetLoggedonDate();
        
        $obj->AccountKeySourceCreate(
            $account, $obj->InitAuthKey(), true);
        
        return $obj;
    }
    
    /** Returns a session matching the given client or null if none exists */
    public static function TryLoadByClient(ObjectDatabase $database, Client $client) : ?self
    {
        return $database->TryLoadUniqueByKey(static::class, 'client', $client->ID());
    }
    
    /** Deletes the session matching the given client (return true if found) */
    public static function DeleteByClient(ObjectDatabase $database, Client $client) : bool
    {
        return $database->TryDeleteUniqueByKey(static::class, 'client', $client->ID());
    }
    
    /** 
     * Load all sessions for a given account 
     * @return array<string, static>
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    { 
        return $database->LoadObjectsByKey(static::class, 'account', $account->ID());
    }

    /** 
     * Deletes all sessions for the given account 
     * @return int the number of deleted sessions
     */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }
    
    /** 
     * Deletes all sessions for the given account except the given session 
     * @return int the number of deleted sessions
     */
    public static function DeleteByAccountExcept(ObjectDatabase $database, Account $account, Session $session) : int
    {
        $q = new QueryBuilder(); $w = $q->And(
            $q->Equals('account',$account->ID()),
            $q->NotEquals('id',$session->ID()));
        
        return $database->DeleteObjectsByQuery(static::class, $q->Where($w));
    }

    /** 
     * Prunes old sessions from the DB that have expired 
     * @param ObjectDatabase $database reference
     * @param Account $account to check sessions for
     * @return int the number of deleted sessions
     */
    public static function PruneOldFor(ObjectDatabase $database, Account $account) : int
    {
        if (($maxage = $account->GetSessionTimeout()) === null) return 0;
        
        $mintime = $database->GetTime() - $maxage;
        
        $q = new QueryBuilder(); $q->Where($q->And(
            $q->Equals('account',$account->ID()),
            $q->LessThan('date_active', $mintime)));
        
        return $database->DeleteObjectsByQuery(static::class, $q);
    }

    /**
     * Authenticates the given info claiming to be this session and checks the timeout
     * @param string $key the session authentication key
     * @return bool true if success, false if invalid
     * @see AuthObject::CheckKeyMatch()
     */
    public function CheckKeyMatch(string $key) : bool
    {
        if (!$this->BaseCheckKeyMatch($key)) return false;
        
        $time = $this->database->GetTime();
        $maxage = $this->GetAccount()->GetSessionTimeout(); 
        $active = $this->date_active->TryGetValue();

        if ($maxage !== null && $active !== null &&
            ($time - $active) >= $maxage) return false;
        
        if (!$this->database->isReadOnly())
        {
            $this->date_active->SetTimeNow();
            $this->GetAccount()->SetActiveDate();
        }

        return true;
    }

    /**
     * Initializes crypto for the session
     * The account must have crypto unlocked, and this session must have its raw auth key available
     * @return $this
     */
    public function InitializeCrypto() : self
    {
        return $this->InitializeCryptoFromAccount($this->GetAuthKey(), true);
    }
    
    /**
     * Attempts to unlock crypto using the previously checked key, sets the account key source
     * @throws RawKeyNotAvailableException if CheckKeyMatch was not run with the key
     * @return $this
     */
    public function UnlockCrypto() : self
    {
        if (!isset($this->authkey_raw))
            throw new RawKeyNotAvailableException();

        $this->BaseUnlockCrypto($this->authkey_raw, true);
        $this->account->GetObject()->SetCryptoKeySource($this);
        return $this;
    }

    /**
     * Returns a printable client object for this session from its account
     * @return SessionJ
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'client' => $this->client->GetObjectID(),
            'date_created' => $this->date_created->GetValue(),
            'date_active' => $this->date_active->TryGetValue()
        );
        
        if ($secret) $retval['authkey'] = $this->GetAuthKey();
        
        return $retval;
    }
}
