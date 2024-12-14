<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{JoinObject, FieldTypes, ObjectDatabase, TableTypes};

/** 
 * Class representing a group membership, joining an account and a group
 * @phpstan-type GroupJoinJ array{date_created:float}
 * @extends JoinObject<Account,Group>
 */
class GroupJoin extends JoinObject
{
    use TableTypes\TableNoChildren;
    
    /** The date this session was created */
    private FieldTypes\Timestamp $date_created;

    /** @var FieldTypes\ObjectRefT<Account> */
    private FieldTypes\ObjectRefT $account;

    /** @var FieldTypes\ObjectRefT<Group> */
    private FieldTypes\ObjectRefT $group;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->account = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        $this->group = $fields[] = new FieldTypes\ObjectRefT(Group::class, 'group');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    protected function GetLeftField() : FieldTypes\ObjectRefT { return $this->account; }
    protected function GetRightField() : FieldTypes\ObjectRefT { return $this->group; }

    public function GetAccount() : Account { return $this->account->GetObject(); }
    public function GetGroup() : Group { return $this->group->GetObject(); }

    /** 
     * Loads all accounts joined to the given group
     * @return array<string, Account> array indexed by ID
     */
    public static function LoadAccounts(ObjectDatabase $database, Group $group) : array
    {
        return static::LoadFromJoin($database, 'account', Account::class, 'group', $group);
    }

    /** 
     * Loads all groups joined to the given account
     * @return array<string, Group> array indexed by ID
     */
    public static function LoadGroups(ObjectDatabase $database, Account $account) : array
    {
        return static::LoadFromJoin($database, 'group', Group::class, 'account', $account);
    }

    /** 
     * Loads the join object (group membership) for the given account and group
     * @return ?static join object or null if not joined
     */
    public static function TryLoadByMembership(ObjectDatabase $database, Account $account, Group $group) : ?self
    {
        return static::TryLoadJoinObject($database, 'account', $account, 'group', $group);
    }

    /** 
     * Deletes all group joins for the given account 
     * @return int the number of deleted group joins
     */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : int
    {
        return static::DeleteJoinsFrom($database, 'account', $account);
    }
    
    /** 
     * Deletes all group joins for the given group
     * @return int the number of deleted group joins
     */
    public static function DeleteByGroup(ObjectDatabase $database, Group $group) : int
    {
        return static::DeleteJoinsFrom($database, 'group', $group);
    }
    
    /** @var array<callable(ObjectDatabase, Account, Group, bool): void> */
    private static array $change_handlers = array();
    
    /** 
     * Registers a function to be run when the account is added to or removed from a group 
     * @param callable(ObjectDatabase, Account, Group, bool): void $func
     */
    public static function RegisterChangeHandler(callable $func) : void { self::$change_handlers[] = $func; }

    /** Runs all functions registered to handle the account being added to or removed from a group */
    public static function RunGroupChangeHandlers(ObjectDatabase $database, Account $account, Group $group, bool $added) : void { 
        foreach (self::$change_handlers as $func) $func($database, $account, $group, $added); }
    
    /**
     * Creates a new groupjoin object, adding the account to the group
     * @param Account $account account to add to the group
     * @param Group $group group to be added to
     * @return static
     */
    public static function Create(ObjectDatabase $database, Account $account, Group $group) : self
    {
        if ($group->isDefault())
            throw new Exceptions\ImplicitGroupMembershipException();
        
        $obj = parent::CreateJoin($database, $account, $group, 
            function(self $obj){ $obj->date_created->SetTimeNow(); });

        self::RunGroupChangeHandlers($database, $account, $group, true);

        return $obj;
    }

    public function NotifyPreDeleted() : void
    {
        self::RunGroupChangeHandlers($this->database, $this->GetAccount(), $this->GetGroup(), false);
    }

    /**
     * Returns a printable client object of this group membership
     * @return GroupJoinJ
     */
    public function GetClientObject()
    {
        return array(
            'date_created' => $this->date_created->GetValue()
        );
    }
}
