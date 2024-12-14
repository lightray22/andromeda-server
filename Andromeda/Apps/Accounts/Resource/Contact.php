<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\AuthObjectFull;

/** 
 * An object describing a contact method for a user account
 * @phpstan-type ContactJ array{id:string}
 */
abstract class Contact extends BaseObject
{
    use TableTypes\TableIntTypedChildren;
    
    use AuthObjectFull { CheckFullKey as BaseCheckFullKey; }
    
    protected static function GetFullKeyPrefix() : string { return "ci"; }
    
    public const TYPE_EMAIL = 0;
    
    public const TYPES = array('email'=>self::TYPE_EMAIL);
    
    /** @return array<value-of<self::TYPES>, class-string<self>> */
    public static function GetChildMap(?ObjectDatabase $database = null) : array
    {
        return array(self::TYPE_EMAIL => EmailContact::class);
    }

    /** 
     * Returns the TYPE enum value for a given child class
     * @param class-string<self> $class
     * @return value-of<self::TYPES>
     */
    public static function ChildClassToType(string $class) : int
    {
        return array_flip(self::GetChildMap())[$class];
    }
    
    /** Address of the contact */
    private FieldTypes\StringType $address;
    /** If this contact is publically viewable */
    private FieldTypes\BoolType $public;
    /** True if this contact should be used as a "from" address */
    private FieldTypes\NullBoolType $asfrom;
    /** Timestamp this contact was created */
    private FieldTypes\Timestamp $date_created;
    /** 
     * Account this contact belongs to
     * @var FieldTypes\ObjectRefT<Account> 
     */
    private FieldTypes\ObjectRefT $account;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->address = $fields[] = new FieldTypes\StringType('address');
        $this->public = $fields[] = new FieldTypes\BoolType('public', false, false);
        $this->asfrom = $fields[] = new FieldTypes\NullBoolType('from');
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->account = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** @return positive-int */
    protected static function GetKeyLength() : int { return 8; }
    
    public function CheckFullKey(string $code) : bool
    {
        if (!$this->BaseCheckFullKey($code)) return false;
        
        $this->SetAuthKey(null);
        
        $this->GetAccount()->NotifyValidContact();
        
        return true;
    }

    /**
     * Attemps to load a from contact for the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account account of interest
     * @return ?static contact to use as "from" or none if not set
     */
    public static function TryLoadFromByAccount(ObjectDatabase $database, Account $account) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->IsTrue('asfrom'));
        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($w));
    }
    
    /** 
     * Returns the contact object matching the given address and type, or null
     * @return ?static
     */
    public static function TryLoadByAddress(ObjectDatabase $database, string $address) : ?self
    {
        $q = new QueryBuilder(); $w = $q->Equals('address',$address);
        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($w));
    }
    
    /** 
     * Attempts to load a contact by the given ID for the given account
     * @return ?static
     */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('id',$id),$q->Equals('account',$account->ID()));
        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($w));
    }

    /** Count contacts for a given account */
    public static function CountByAccount(ObjectDatabase $database, Account $account) : int
    { 
        return $database->CountObjectsByKey(static::class, 'account', $account->ID());
    }

    /** 
     * Load all contacts for a given account 
     * @return array<string, static>
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    { 
        return $database->LoadObjectsByKey(static::class, 'account', $account->ID());
    }

    /** 
     * Deletes all contacts for the given account 
     * @return int the number of deleted contacts
     */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }
    
    /**
     * Returns all accounts matching the given public contact address
     * @param ObjectDatabase $database database reference
     * @param string $address contact address to match (wildcard)
     * @param positive-int $limit return up to this many
     * @return array<string, Account>
     */
    public static function LoadAccountsMatchingValue(ObjectDatabase $database, string $address, int $limit) : array
    {
        $q = new QueryBuilder(); $address = QueryBuilder::EscapeWildcards($address).'%'; // search by prefix
        
        $w = $q->And($q->Like('address',$address,true),$q->IsTrue('public'));
        
        $contacts = $database->LoadObjectsByQuery(static::class, $q->Where($w)->Limit($limit));
        
        $retval = array(); foreach ($contacts as $contact)
        { 
            $account = $contact->GetAccount(); 
            $retval[$account->ID()] = $account; 
        }; 
        return $retval;
    }
    
    /** Returns the contact address string */
    protected function GetAddress() : string { return $this->address->GetValue(); }
    
    /** Returns true if the contact has been validated */
    public function GetIsValid() : bool { return $this->authkey->TryGetValue() === null; }
    
    /** Returns whether or not the contact is public */
    public function GetIsPublic() : bool { return $this->public->GetValue(); }
    
    /** 
     * Sets whether this contact should be publically searchable 
     * @return $this
     */
    public function SetIsPublic(bool $val) : self { $this->public->SetValue($val); return $this; }
    
    /** 
     * Sets whether this contact should be used as from (can only be one) 
     * @return $this
     */
    public function SetUseAsFrom(bool $val) : self
    {
        if ($val)
        {
            $old = static::TryLoadFromByAccount($this->database, $this->GetAccount());
            if ($old !== null) $old->SetUseAsFrom(false);
        }
        else $val = null;
        
        $this->asfrom->SetValue($val); return $this;
    }
    
    /** Returns the account that owns the contact */
    public function GetAccount() : Account { return $this->account->GetObject(); }

    /**
     * Creates a new contact
     * @param ObjectDatabase $database database reference
     * @param Account $account account of contact
     * @param string $address the contact address
     * @param bool $verify true to send a validation message
     * @return static
     */
    public static function Create(ObjectDatabase $database, Account $account, string $address, bool $verify = false) : self
    {
        $account->CheckLimitContacts();
        
        $contact = $database->CreateObject(static::class);
        $contact->date_created->SetTimeNow();
        
        $contact->account->SetObject($account);
        $contact->address->SetValue($address);
        
        if ($verify)
        {
            $contact->InitAuthKey();
            $key = $contact->TryGetFullKey();
            
            $subject = "Andromeda Contact Validation Code";
            $body = "Your validation code is: $key";
            
            $contact->SendMessage($subject, null, $body);
        }
        
        return $contact;
    }

    abstract public static function SubclassGetFetchUsage() : string;

    public static function GetFetchUsage() : string
    { 
        return implode("|", array_map(function(string $class){ return $class::SubclassGetFetchUsage(); }, self::GetChildMap()));
    }
    
    /**
     * Fetches a type/address pair from input (depends on the param name given)
     * @throws Exceptions\ContactNotGivenException if nothing valid was found
     * @return ?array{class:class-string<self>, address:string}
     */
    abstract public static function SubclassFetchPairFromParams(SafeParams $params) : ?array;
    
    /**
     * Fetches a type/address pair from input (depends on the param name given)
     * @throws Exceptions\ContactNotGivenException if nothing valid was found
     * @return array{class:class-string<self>, address:string}
     */
    public static function FetchPairFromParams(SafeParams $params) : array
    {
        foreach (self::GetChildMap() as $class)
        {
            $ret = $class::SubclassFetchPairFromParams($params);
            if ($ret !== null) return $ret;
        }
        
        throw new Exceptions\ContactNotGivenException();
    }

    /**
     * Loads a contact from input (depends on the param name given)
     * @param array{class:class-string<self>, address:string} $pair contact address
     * @throws Exceptions\ContactNotGivenException if nothing valid was found
     */
    public static function TryLoadFromPair(ObjectDatabase $database, array $pair) : ?self
    {
        return $pair['class']::TryLoadByAddress($database, $pair['address']);
    }

    /**
     * Creates a new contact from a class/address pair
     * @param ObjectDatabase $database database reference
     * @param Account $account account of contact
     * @param array{class:class-string<self>, address:string} $pair contact address
     * @param bool $verify true to send a validation message
     */
    public static function CreateFromPair(ObjectDatabase $database, Account $account, array $pair, bool $verify = false) : self
    {
        return $pair['class']::Create($database, $account, $pair['address'], $verify);
    }

    /**
     * Sends a message to this contact
     * @see Contact::SendMessageMany()
     */
    public function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void
    {
        static::SendMessageMany($subject, $html, $plain, array($this), false, $from);
    }

    /**
     * Sends a message to the given array of contacts
     * @param string $subject subject line
     * @param string $html html message (optional)
     * @param string $plain plain text message
     * @param array<self> $recipients array of contacts
     * @param Account $from account sending the message
     * @param bool $bcc true to use BCC for recipients
     */
    public static function SendMessageMany(string $subject, ?string $html, string $plain, array $recipients, bool $bcc, ?Account $from = null) : void
    {
        foreach (self::GetChildMap() as $type)
        {
            $subrecipients = array_filter($recipients, 
                function(Contact $c)use($type){ return $c instanceof $type; });
            $type::SubclassSendMessageMany($subject, $html, $plain, $subrecipients, $bcc, $from);
        }
    }

    /**
     * Sends a message to the given array of contacts
     * @param string $subject subject line
     * @param string $html html message (optional)
     * @param string $plain plain text message
     * @param array<static> $recipients array of contacts
     * @param Account $from account sending the message
     * @param bool $bcc true to use BCC for recipients
     */
    abstract public static function SubclassSendMessageMany(string $subject, ?string $html, string $plain, array $recipients, bool $bcc, ?Account $from = null) : void;

    /**
     * Gets this contact as a printable object
     * @return ContactJ
     */
    public function GetClientObject() : array // TODO RAY !! GetClientObject
    {
        return array(
            'id' => $this->ID(),
            /*'type' => self::TYPES[$this->GetType()],
            'address' => $this->GetAddress(),
            'valid' => $this->GetIsValid(),
            'asfrom' => (bool)($this->TryGetScalar('asfrom')),
            'public' => $this->GetIsPublic(),
            'dates' => array(
                'created' => $this->GetDateCreated()
            )*/
        );
    }
}
