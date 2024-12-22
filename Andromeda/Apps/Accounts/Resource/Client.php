<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\IOFormat\IOInterface;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\AuthObject;

/**
 * A client registered for authenticating as an account
 *
 * Extends AuthObject, using a hashed key for authentication.
 * Each client can contain exactly one registered session.
 * 
 * The client is separate from the session mainly so that a user
 * can sign-out securely and not need to use two factor (or remember
 * any other client-specific values) on the next sign in
 * 
 * @phpstan-import-type SessionJ from Session
 * @phpstan-type ClientJ array{id:string, name:?string, lastaddr:string, useragent:string, date_created:float, date_loggedon:?float, date_active:?float, authkey?:string, session:?SessionJ}
 */
class Client extends BaseObject
{    
    use TableTypes\TableNoChildren;

    use AuthObject { CheckKeyMatch as BaseCheckKeyMatch; }
    
    /** The last remote address used with this client */
    private FieldTypes\StringType $lastaddr;
    /** The last user agent used with this client */
    private FieldTypes\StringType $useragent;
    /** The date this session was created */
    private FieldTypes\Timestamp $date_created;
    /** The last date this client was active */
    private FieldTypes\NullTimestamp $date_active;
    /** The last date a session was created for this client */
    private FieldTypes\NullTimestamp $date_loggedon;
    /** The optional user label of this client */
    private FieldTypes\NullStringType $name;
    /** 
     * The account this client is for
     * @var FieldTypes\ObjectRefT<Account>  
     */
    private FieldTypes\ObjectRefT $account;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->lastaddr =      $fields[] = new FieldTypes\StringType('lastaddr');
        $this->useragent =     $fields[] = new FieldTypes\StringType('useragent');
        
        $this->date_created =  $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_active =   $fields[] = new FieldTypes\NullTimestamp('date_active');
        $this->date_loggedon = $fields[] = new FieldTypes\NullTimestamp('date_loggedon');
        
        $this->name =          $fields[] = new FieldTypes\NullStringType('name');
        $this->account =       $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        
        $this->RegisterFields($fields, self::class);
        
        $this->AuthObjectCreateFields();
        parent::CreateFields();
    }

    /** 
     * Load all clients for a given account 
     * @return array<string, static>
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    { 
        return $database->LoadObjectsByKey(static::class, 'account', $account->ID());
    }

    /** Count all clients for a given account */
    public static function CountByAccount(ObjectDatabase $database, Account $account) : int
    { 
        return $database->CountObjectsByKey(static::class, 'account', $account->ID());
    }

    /** Delete all clients for a given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : int
    { 
        return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }

    /** 
     * Tries to load the object by the given account and ID
     * @return ?static the loaded object or null if not found 
     */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->Equals('id',$id));
        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($w));
    }
    
    /** Gets the interface address last used with this client */
    public function GetLastAddress() : string { return $this->lastaddr->GetValue(); }
    
    /** Gets the interface user agent last used with this client */
    public function GetUserAgent() : string { return $this->useragent->GetValue(); }
    
    /** Gets the account that owns this client */
    public function GetAccount() : Account { return $this->account->GetObject(); }

    /** Sets the timestamp when the client last created a session to now */
    public function SetLoggedonDate() : self 
    {
        $this->GetAccount()->SetLoggedonDate();
        $this->date_loggedon->SetTimeNow(); return $this; 
    }
    
    /**
     * Prunes old clients from the DB that have expired
     * @param ObjectDatabase $database reference
     * @param Account $account to check clients for
     * @return int the number of deleted clients
     */
    public static function PruneOldFor(ObjectDatabase $database, Account $account) : int
    {
        if (($maxage = $account->GetClientTimeout()) === null) return 0;
        
        $mintime = $database->GetTime() - $maxage;
        
        $q = new QueryBuilder(); $q->Where($q->And(
            $q->Equals('account',$account->ID()),
            $q->LessThan('date_active', $mintime)));
        
        return $database->DeleteObjectsByQuery(static::class, $q);
    }

    /**
     * Creates a new client object
     * @param IOInterface $interface the interface used for the request
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns this client
     * @param string $name custom name to show for the client
     * @return static new Client
     */
    public static function Create(IOInterface $interface, ObjectDatabase $database, Account $account, ?string $name = null) : self
    {
        $account->CheckLimitClients();

        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        
        $obj->lastaddr->SetValue($interface->getAddress());
        $obj->useragent->SetValue($interface->getUserAgent());
        
        $obj->name->SetValue($name);
        $obj->account->SetObject($account);
        
        $obj->InitAuthKey(); return $obj;
    }
    
    public function NotifyPreDeleted() : void
    {
        Session::DeleteByClient($this->database, $this);
    }
    
    /**
     * Authenticates the given info claiming to be this client and checks the timeout
     * @param IOInterface $interface the interface used for the request (logs user agent/IP)
     * @param string $key the client authentication key
     * @return bool true if success, false if invalid
     * @see AuthObject::CheckKeyMatch()
     */
    public function CheckKeyMatch(IOInterface $interface, string $key) : bool
    {        
        if (!$this->BaseCheckKeyMatch($key)) return false;
        
        $time = $this->database->GetTime();
        $maxage = $this->GetAccount()->GetClientTimeout(); 
        $active = $this->date_active->TryGetValue();

        if ($maxage !== null && $active !== null &&
            ($time - $active) >= $maxage) return false;
                
        if (!$this->database->isReadOnly())
        {
            $this->date_active->SetTimeNow();
            $this->useragent->SetValue($interface->getUserAgent());
            $this->lastaddr->SetValue($interface->getAddress());  
        }

        return true;
    }
    
    /**
     * Gets this client as a printable object
     * @return ClientJ
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'name' => $this->name->TryGetValue(),
            'lastaddr' => $this->lastaddr->GetValue(),
            'useragent' => $this->useragent->GetValue(),
            'date_created' => $this->date_created->GetValue(),
            'date_loggedon' => $this->date_loggedon->TryGetValue(),
            'date_active' => $this->date_active->TryGetValue()
        );
        
        if ($secret) $data['authkey'] = $this->GetAuthKey();
        
        $session = Session::TryLoadByClient($this->database, $this);
        $data['session'] = ($session !== null) ? $session->GetClientObject($secret) : null;

        return $data;        
    }
}
