<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, TableTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Apps\Accounts\Resource\Contact;

/** 
 * Base class for account/groups containing properties that can be set per-account or per-group
 * @phpstan-type PolicyBaseJ array{session_timeout:?int, client_timeout:?int, max_password_age:?int, limit_clients:?int, limit_contacts:?int, limit_recoverykeys:?int, admin:?bool, disabled:?int, forcetf:?bool, allowcrypto:?bool, userdelete:?bool, account_search:?int, group_search:?int}
 */
abstract class PolicyBase extends BaseObject
{
    use TableTypes\TableLinkedChildren;
    
    /** @return list<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        return array(Account::class, Group::class);
    }
    
    /** Timestamp that the object was created */
    protected FieldTypes\Timestamp $date_created;
    /** Timestamp these properties were last modified */
    protected FieldTypes\NullTimestamp $date_modified;
    /** Admin-added comment for this policy entity */
    protected FieldTypes\NullStringType $comment;
    /** True if this entity is granted admin privileges */
    protected FieldTypes\NullBoolType $admin;
    /** True if the entity is disabled (not allowed access) */
    protected FieldTypes\NullIntType $disabled;
    /** true if two-factor is required to create sessions (not just clients) */
    protected FieldTypes\NullBoolType $forcetf;
    /** true if server-side account crypto is enabled */
    protected FieldTypes\NullBoolType $allowcrypto;
    /** whether looking up accounts by name is allowed */
    protected FieldTypes\NullIntType $account_search;
    /** whether looking up groups by name is allowed */
    protected FieldTypes\NullIntType $group_search;
    /** whether the user is allowed to delete their account */
    protected FieldTypes\NullBoolType $userdelete;
    /** maximum number of sessions for the account */
    protected FieldTypes\NullIntType $limit_clients;
    /** maximum number of contacts for the account */
    protected FieldTypes\NullIntType $limit_contacts;
    /** maximum number of recovery keys for the account */
    protected FieldTypes\NullIntType $limit_recoverykeys;
    /** server-side timeout - max time for a session to be inactive */
    protected FieldTypes\NullIntType $session_timeout;
    /** server-side timeout - max time for a client to be inactive */
    protected FieldTypes\NullIntType $client_timeout;
    /** max time since the account's password changed */
    protected FieldTypes\NullIntType $max_password_age;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_modified = $fields[] = new FieldTypes\NullTimestamp('date_modified');
        $this->comment = $fields[] = new FieldTypes\NullStringType('comment');
        $this->admin = $fields[] = new FieldTypes\NullBoolType('admin');
        $this->disabled = $fields[] = new FieldTypes\NullIntType('disabled');
        $this->forcetf = $fields[] = new FieldTypes\NullBoolType('forcetf');
        $this->allowcrypto = $fields[] = new FieldTypes\NullBoolType('allowcrypto');
        $this->account_search = $fields[] = new FieldTypes\NullIntType('accountsearch');
        $this->group_search = $fields[] = new FieldTypes\NullIntType('groupsearch');
        $this->userdelete = $fields[] = new FieldTypes\NullBoolType('userdelete');
        $this->limit_clients = $fields[] = new FieldTypes\NullIntType('limit_clients');
        $this->limit_contacts = $fields[] = new FieldTypes\NullIntType('limit_contacts');
        $this->limit_recoverykeys = $fields[] = new FieldTypes\NullIntType('limit_recoverykeys');
        $this->session_timeout = $fields[] = new FieldTypes\NullIntType('session_timeout');
        $this->client_timeout = $fields[] = new FieldTypes\NullIntType('client_timeout');
        $this->max_password_age = $fields[] = new FieldTypes\NullIntType('max_password_age');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /** defines command usage for SetProperties() */
    public static function GetPropUsage() : string { return "[--comment ?text] [--session_timeout ?uint] [--client_timeout ?uint] [--max_password_age ?uint] ".
                                                            "[--limit_clients ?uint8] [--limit_contacts ?uint8] [--limit_recoverykeys ?uint8] ".
                                                            "[--admin ?bool] [--disabled ?bool] [--forcetf ?bool] [--allowcrypto ?bool] ".
                                                            "[--account_search ?uint8] [--group_search ?uint8] [--userdelete ?bool]"; }

    /** 
     * Sets the value of an inherited property for the object 
     * @return $this
     */
    public function SetProperties(SafeParams $params) : self
    {
        if ($params->HasParam('comment'))
            $this->comment->SetValue($params->GetParam("comment")->GetNullHTMLText());

        foreach (array($this->session_timeout, $this->client_timeout, $this->max_password_age) as $field)
            if ($params->HasParam($field->GetName())) 
                $field->SetValue($params->GetParam($field->GetName())->GetNullUint());

        foreach (array($this->limit_clients, $this->limit_contacts, $this->limit_recoverykeys, $this->account_search, $this->group_search) as $field)
            if ($params->HasParam($field->GetName())) 
                $field->SetValue($params->GetParam($field->GetName())->GetNullUint8());

        foreach (array($this->admin, $this->disabled, $this->forcetf, $this->allowcrypto, $this->userdelete) as $field)
            if ($params->HasParam($field->GetName())) 
                $field->SetValue($params->GetParam($field->GetName())->GetNullBool());
    
        $this->date_modified->SetTimeNow();
        return $this;
    }

    /** Gets the comment for the entity (or null) */
    public function GetComment() : ?string { 
        return $this->comment->TryGetValue(); }
    
    /** Returns the descriptive name of this policy entity */
    public abstract function GetDisplayName() : string;

    /** @return array<string, Contact> contacts indexed by ID */
    public abstract function GetContacts() : array;
    
    /**
     * Sends a message to all of this entity's valid contacts
     * @see Contact::SendMessageMany()
     */
    public abstract function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void;
}
