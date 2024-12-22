<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes, QueryBuilder};

use Andromeda\Apps\Accounts\Resource\Contact;

/**
 * A group of user accounts
 * 
 * Used primarily to manage config for multiple accounts at once, in a many-to-many relationship.
 * Groups use a priority number to resolve conflicting properties.
 * 
 * @phpstan-import-type PolicyBaseJ from PolicyBase
 * @phpstan-type PublicGroupJ array{id:string, name:string}
 * @phpstan-type AdminGroupJ array{priority:int, comment:?string, date_created:float, date_modified:?float, accounts?:list<string>, policy:PolicyBaseJ}
 */
class Group extends PolicyBase
{
    use TableTypes\TableNoChildren;
    
    /** The short name of the group */
    private FieldTypes\StringType $name;
    /** The priority of the group's permissions */
    private FieldTypes\IntType $priority;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->name = $fields[] = new FieldTypes\StringType('name');
        $this->priority = $fields[] = new FieldTypes\IntType('priority');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'name';
        return $ret;
    }
    
    /** Gets the short name of the group */
    public function GetDisplayName() : string { return $this->name->GetValue(); }
    
    /** 
     * Sets the short name of the group
     * @return $this 
     */
    public function SetDisplayName(string $name) : self { $this->name->SetValue($name); return $this; }
    
    /** Gets the priority assigned to the group. Higher number means conflicting config takes precedent */
    public function GetPriority() : int { return $this->priority->GetValue(); }
    
    /** 
     * Sets the priority assigned to the group 
     * @return $this
     */
    public function SetPriority(int $priority) : self { $this->priority->SetValue($priority); return $this; }
    
    /**
     * Gets the list of accounts that are implicitly part of this group
     * @return array<string, Account> Accounts indexed by ID or null if not a default group
     */
    public function GetDefaultAccounts() : ?array
    {
        if (Config::GetInstance($this->database)->GetDefaultGroup() === $this)
            return Account::LoadAll($this->database);
        
        foreach (AuthSource\External::LoadAll($this->database) as $authman)
        {
            if ($authman->GetDefaultGroup() === $this)
                return Account::LoadByAuthSource($this->database, $authman);
        }
        
        return null; // not a default group
    }

    /** Returns true if this group is used as a default (implicit memberships) */
    public function isDefault() : bool
    {
        if (Config::GetInstance($this->database)->GetDefaultGroup() === $this)
            return true;
        
        foreach (AuthSource\External::LoadAll($this->database) as $authman)
        {
            if ($authman->GetDefaultGroup() === $this)
                return true;
        }
        
        return false; // not a default group
    }

    /**
     * Gets the list of all accounts in this group
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetAccounts() : array { return $this->GetDefaultAccounts() ?? $this->GetJoinedAccounts(); }
    
    /**
     * Gets the list of accounts that are explicitly part of this group
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetJoinedAccounts() : array { return GroupJoin::LoadAccounts($this->database, $this); }
    
    /** Tries to load a group by name, returning null if not found */
    public static function TryLoadByName(ObjectDatabase $database, string $name) : ?self
    {
        return $database->TryLoadUniqueByKey(static::class, 'name', $name);
    }
    
    /**
     * Loads all groups matching the given name
     * @param ObjectDatabase $database database reference
     * @param string $name name to match (wildcard)
     * @param positive-int $limit max number to load - returns nothing if exceeded
     * @return array<string, static>
     */
    public static function LoadAllMatchingName(ObjectDatabase $database, string $name, int $limit) : array
    {
        $q = new QueryBuilder(); $name = QueryBuilder::EscapeWildcards($name).'%'; // search by prefix
        
        $loaded = $database->LoadObjectsByQuery(static::class, $q->Where($q->Like('name',$name,true))->Limit($limit+1));
        
        return (count($loaded) >= $limit+1) ? array() : $loaded;
    }

    /**
     * Gets contact objects for all accounts in this group
     * @return array<string, Contact> indexed by ID
     * @see Account::GetContacts()
     */
    public function GetContacts() : array
    {
        $output = array();
        
        foreach ($this->GetAccounts() as $account)
            $output += $account->GetContacts();
        
        return $output;
    }
    
    /**
     * Sends a message to all of this group's accounts' valid contacts (with BCC)
     * @see Contact::SendMessageMany()
     */
    public function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void
    {
        Contact::SendMessageMany($subject, $html, $plain, $this->GetContacts(), true, $from);
    }
    
    /** Creates and returns a new group with the given name, priority */
    public static function Create(ObjectDatabase $database, string $name, ?int $priority = null) : self
    {
        $group = $database->CreateObject(static::class);
        $group->date_created->SetTimeNow();
        
        $group->name->SetValue($name);
        $group->priority->SetValue($priority ?? 0);
        
        return $group;
    }
    
    /** Initializes a newly created group by running group change handlers on its implicit accounts */
    public function PostDefaultCreateInitialize() : self
    {
        foreach (($this->GetDefaultAccounts() ?? []) as $account)
            GroupJoin::RunGroupChangeHandlers($this->database, $account, $this, true);
        
        return $this;
    }
    
    /** @var array<callable(ObjectDatabase, self): void> */
    private static array $delete_handlers = array();
    
    /** 
     * Registers a function to be run when a group is deleted
     * @param callable(ObjectDatabase, self): void $func
     */
    public static function RegisterDeleteHandler(callable $func) : void { 
        self::$delete_handlers[] = $func; }
    
    public function NotifyPreDeleted() : void
    {
        GroupJoin::DeleteByGroup($this->database, $this);

        foreach (($this->GetDefaultAccounts() ?? []) as $account)
            GroupJoin::RunGroupChangeHandlers($this->database, $account, $this, false);
            
        foreach (self::$delete_handlers as $func) 
            $func($this->database, $this);
    }
    
    /**
     * Gets this group as a printable object (public)
     * @return PublicGroupJ
     */
    public function GetPublicClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'name' => $this->GetDisplayName()
        );
    }

    /** 
     * Gets this group as a printable object (admin)
     * @return \Union<PublicGroupJ, AdminGroupJ>
     */
    public function GetAdminClientObject(bool $accounts = false) : array
    {
        $retval = $this->GetPublicClientObject();

        $retval += array(
            'priority' => $this->priority->GetValue(),
            'comment' => $this->comment->TryGetValue(),
            'date_created' => $this->date_created->GetValue(),
            'date_modified' => $this->date_modified->TryGetValue(),
            'policy' => array(
                'session_timeout' => $this->session_timeout->TryGetValue(),
                'client_timeout' => $this->client_timeout->TryGetValue(),
                'max_password_age' => $this->max_password_age->TryGetValue(),
                'limit_clients' => $this->limit_clients->TryGetValue(),
                'limit_contacts' => $this->limit_contacts->TryGetValue(),
                'limit_recoverykeys' => $this->limit_recoverykeys->TryGetValue(),

                'admin' => $this->admin->TryGetValue(),
                'disabled' => $this->disabled->TryGetValue(),
                'forcetf' => $this->forcetf->TryGetValue(),
                'allowcrypto' => $this->allowcrypto->TryGetValue(),
                'userdelete' => $this->userdelete->TryGetValue(),
                'account_search' => $this->account_search->TryGetValue(),
                'group_search' => $this->group_search->TryGetValue()
            )
        );
        
        if ($accounts) 
            $retval['accounts'] = array_keys($this->GetAccounts());

        return $retval;
    }
}
