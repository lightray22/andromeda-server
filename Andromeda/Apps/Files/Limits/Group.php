<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\{SafeParam, SafeParams};

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Accounts/Groups/Group.php"); use Andromeda\Apps\Accounts\Groups\Group;
require_once(ROOT."/Apps/Accounts/Groups/GroupStuff.php"); use Andromeda\Apps\Accounts\Groups\GroupJoin;

require_once(ROOT."/Apps/Files/Limits/Total.php");
require_once(ROOT."/Apps/Files/Limits/Timed.php");
require_once(ROOT."/Apps/Files/Limits/AuthObj.php");

interface IGroupCommon 
{ 
    /** Track stats for component accounts by inheriting this property */
    public const TRACK_ACCOUNTS = 1;
    
    /** Track stats for components accounts and also the group as a whole */
    public const TRACK_WHOLE_GROUP = 2;
    
    public const TRACK_TYPES = array('none'=>0,
        'accounts'=>self::TRACK_ACCOUNTS, 
        'wholegroup'=>self::TRACK_WHOLE_GROUP);  
}

/**
 * Group limits common between total and timed
 * 
 * The extra complexity with groups is because they operate as sums of their component
 * accounts, and must manage updating themselves when account memberships are changed.
 * 
 * Group limits apply to its component accounts individually, not the group as a whole.
 */
trait GroupCommon
{
    protected static function GetObjectClass() : string { return Group::class; }
    
    /** Returns the ID of the limited group */
    public function GetGroupID() : string { return $this->GetObjectID('object'); }
    
    /** Returns the limited group object */
    public function GetGroup() : Group  { return $this->GetObject('object'); }
    
    /** Returns the inheritance priority of the limited group */
    public function GetPriority() : int { return $this->GetGroup()->GetPriority(); }

    protected function canTrackItems() : bool { return ($this->TryGetFeatureInt('track_items') ?? 0) >= self::TRACK_WHOLE_GROUP; }
    protected function canTrackDLStats() : bool { return ($this->TryGetFeatureInt('track_dlstats') ?? 0) >= self::TRACK_WHOLE_GROUP; }

    // the group's limits apply only to its component accounts
    protected function IsCounterOverLimit(string $name, int $delta = 0) : bool { return false; }
    
    /**
     * Updates the group's stats by adding or subtracting an account's stats
     * @param Base $aclim the account limits
     * @param bool $add true to add, false to subtract
     */
    protected function BaseProcessAccountChange(Base $aclim, bool $add) : void
    {  
        $mul = $add ? 1 : -1;
        $this->CountPublicDownloads($mul*$aclim->GetPublicDownloads());
        $this->CountBandwidth($mul*$aclim->GetBandwidth());
        $this->CountSize($mul*$aclim->GetSize());
        $this->CountItems($mul*$aclim->GetItems());
        $this->CountShares($mul*$aclim->GetShares());
    }
    
    public static function GetBaseUsage() : string { return "[--track_items ?(".implode('|',array_keys(self::TRACK_TYPES)).")] ".
                                                            "[--track_dlstats ?(".implode('|',array_keys(self::TRACK_TYPES)).")]"; }
    
    protected static function GetTrackParam(SafeParam $param) : ?int
    {
        $valstr = $param->FromWhitelistNull(array_keys(self::TRACK_TYPES));
        
        return ($valstr !== null) ? self::TRACK_TYPES[$valstr] : null;
    }
    
    protected function SetBaseLimits(SafeParams $params) : void
    {        
        if ($params->HasParam('track_items'))
        {
            $this->SetFeatureInt('track_items', static::GetTrackParam($params->GetParam('track_items')));
            
            if ($this->isFeatureModified('track_items')) $init = true;
        }
        
        if ($params->HasParam('track_dlstats'))
        {
            $this->SetFeatureInt('track_dlstats', static::GetTrackParam($params->GetParam('track_dlstats')));
            
            if ($this->isFeatureModified('track_dlstats')) $init = true;
        }
        
        if ($init ?? false) $this->Initialize();
    }
    
    /** Configures limits for the given group with the given input */
    public static function ConfigLimits(ObjectDatabase $database, Group $group, SafeParams $params) : self
    {
        return static::BaseConfigLimits($database, $group, $params);
    }    
    
    /** 
     * Deletes the group limits and potentially removes account limits 
     * @see AccountCommon::ProcessGroupRemove()
     */
    public function Delete() : void
    {
        foreach ($this->GetAccounts() as $aclim)
            $aclim->ProcessGroupRemove($this);
        
        parent::Delete();
    }
    
    /**
     * @param bool $full if false, don't show track_items/track_dlstats
     * @return array add: `{config:{track_items:?enum,track_dlstats:?enum}}`
     * @see Total::GetClientObject()
     * @see Timed::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $retval = parent::GetClientObject($full);
        
        if ($full) foreach (array('track_items','track_dlstats') as $prop)
        {
            $val = $this->TryGetFeatureInt($prop);
            
            $retval['config'][$prop] = ($val !== null) ?
                array_flip(self::TRACK_TYPES)[$val] : null;
        }

        return $retval;
    }
}

/** Concrete class providing group config and total stats */
class GroupTotal extends AuthEntityTotal implements IGroupCommon
{ 
    use GroupCommon; 
    
    /** cache of account limits that apply to this group */
    protected array $acctlims;

    public function ProcessAccountChange(AccountTotal $aclim, bool $add) : void
    {
        $this->BaseProcessAccountChange($aclim, $add);
    }
    
    /**
     * loads account limits via a JOIN, caches, and returns them
     * @return array<string, AccountTotal> account limits indexed by ID
     */
    protected function GetAccounts() : array
    {
        if (!isset($this->acctlims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->Equals($this->database->GetClassTableName(GroupJoin::class).'.objs_groups', $this->GetGroupID()))
                ->Join($this->database, GroupJoin::class, 'objs_accounts', AccountTotal::class, 'obj_object', Account::class);
            
            $this->acctlims = AccountTotal::LoadByQuery($this->database, $q);
            
            foreach ($this->GetGroup()->GetDefaultAccounts() ?? array() as $account)
            {
                $acctlim = AccountTotal::LoadByAccount($this->database, $account, false);
                if ($acctlim !== null) $this->acctlims[$acctlim->ID()] = $acctlim;
            }
        }
        
        return $this->acctlims;
    }
    
    /** register a group change handler that updates this specific object's accountlim cache */
    protected function PostConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            if ($this->isDeleted() || $group !== $this->GetGroup()) return;
            
            $aclim = AccountTotal::LoadByAccount($database, $account);
            if ($aclim === null || !isset($this->acctlims)) return;
            
            if ($added) $this->acctlims[$aclim->ID()] = $aclim;
            else unset($this->acctlims[$aclim->ID()]);
        });
    }
    
    /** Returns true if the group's members are allowed to email share links */
    public function GetAllowEmailShare() : ?bool { return $this->TryGetFeatureBool('emailshare'); }
    
    /** Returns true if the group's members are allowed to add new filesystems */
    public function GetAllowUserStorage() : ?bool { return $this->TryGetFeatureBool('userstorage'); }

    /** Returns the total limits for the given group (or none) */
    public static function LoadByGroup(ObjectDatabase $database, Group $group) : ?self
    {
        return static::LoadByClient($database, $group);
    }

    /** Initializes group limits by adding a limit for each member account and adding stats */
    protected function Initialize() : self
    {
        parent::Initialize();
        
        // force create rows for each account
        foreach ($this->GetGroup()->GetAccounts() as $account)
        {
            $aclim = AccountTotal::ForceLoadByAccount($this->database, $account);
            
            $this->ProcessAccountChange($aclim->Initialize(), true);
        }

        return $this;
    }
}

/** Concrete class providing timed group member limits */
class GroupTimed extends AuthEntityTimed implements IGroupCommon
{ 
    use GroupCommon;
    
    /** cache of account limits that apply to this group */
    protected array $acctlims;
    
    public function ProcessAccountChange(AccountTimed $aclim, bool $add) : void
    {
        $this->BaseProcessAccountChange($aclim, $add);
    }
    
    /**
     * loads account limits via a JOIN, caches, and returns them
     * @return array<string, AccountTimed> account limits indexed by ID
     */
    protected function GetAccounts() : array
    {
        if (!isset($this->acctlims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->And($q->Equals('timeperiod',$this->GetTimePeriod()),$q->Equals($this->database->GetClassTableName(GroupJoin::class).'.objs_groups', $this->GetGroupID())))
                ->Join($this->database, GroupJoin::class, 'objs_accounts', AccountTimed::class, 'obj_object', Account::class);
            
            $this->acctlims = AccountTimed::LoadByQuery($this->database, $q);
            
            foreach ($this->GetGroup()->GetDefaultAccounts() ?? array() as $account)
            {
                $acctlim = AccountTimed::LoadByAccount($this->database, $account, $this->GetTimePeriod());
                if ($acctlim !== null) $this->acctlims[$acctlim->ID()] = $acctlim;
            }
        }
        
        return $this->acctlims;
    }
    
    /** register a group change handler that updates this specific object's accountlim cache */
    protected function PostConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            if ($this->isDeleted() || $group !== $this->GetGroup()) return;
            
            $aclim = AccountTimed::LoadByAccount($database, $account, $this->GetTimePeriod());
            if ($aclim === null || !isset($this->acctlims)) return;
            
            if ($added) $this->acctlims[$aclim->ID()] = $aclim;
            else unset($this->acctlims[$aclim->ID()]);
        });
    }

    /** Loads the timed limit for the given group and time period */
    public static function LoadByGroup(ObjectDatabase $database, Group $group, int $period) : ?self
    {
        return static::LoadByClientAndPeriod($database, $group, $period);
    }
    
    /**
     * Returns all timed limits for the given group
     * @param ObjectDatabase $database database reference
     * @param Group $group group of interest
     * @return array<string, GroupTimed> limits indexed by ID
     */
    public static function LoadAllForGroup(ObjectDatabase $database, Group $group) : array
    {
        return static::LoadAllForClient($database, $group);
    }

    /** Initializes group limits by adding a limit for each member account */
    protected function Initialize() : self
    {        
        // force create rows for each account
        foreach ($this->GetGroup()->GetAccounts() as $account)
        {
            $aclim = AccountTimed::ForceLoadByAccount($this->database, $account, $this->GetTimePeriod());
            
            $aclim->Initialize();
        }            
        
        return $this;
    }
    
    protected static function BaseConfigLimits(ObjectDatabase $database, BaseObject $obj, SafeParams $params) : self
    {
        $glim = parent::BaseConfigLimits($database, $obj, $params);
        
        // prune stats for member accounts also
        foreach ($glim->GetAccounts() as $aclim)
        {
            if ($aclim->GetsMaxStatsAgeFrom() === $glim)
                TimedStats::PruneStatsByLimit($database, $aclim);
        }
        
        return $glim;
    }
}

/** Handle deleting limits when a group is deleted */
Group::RegisterDeleteHandler(function(ObjectDatabase $database, Group $group)
{
    GroupTotal::DeleteByClient($database, $group);
    GroupTimed::DeleteByClient($database, $group);
});
