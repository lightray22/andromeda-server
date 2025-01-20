<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\SafeParams;

/**
 * Account limits common between total and timed
 * 
 * Most of the extra complexity with accounts comes from the fact that accounts can 
 * inherit properties from groups. Also, functions are provided that load both an 
 * account's limits and the limits of all groups that apply to it.
 * 
 * Account limit objects are automatically created if a group 
 * that the account is part of has a limit object.
 */
trait AccountCommon
{
    use GroupInherit;
    
    protected static function GetObjectClass() : string { return Account::class; }
    
    /** Returns the ID of the limited account */
    protected function GetAccountID() : string { return $this->GetObjectID('object'); }
    
    /** Returns the limited account */
    protected function GetAccount() : Account { return $this->GetObject('object'); }

    // want to show the actual limited object to the client, not the limiters
    protected function TryGetInheritsScalarFrom(string $field) : ?BaseObject
    {
        $obj = $this->TryGetInheritable($field)->GetSource();
        
        if ($obj === null) return null;
        assert($obj instanceof Base); 
        return $obj->GetLimitedObject();
    }

    /**
     * Returns a printable client object that includes property inherit sources
     * @param bool $full if true, show property inherit sources - always show $full for parent!
     * @return array<mixed> `{config:{track_items:bool,track_dlstats:bool}, config_from:"id:class", limits_from:"id:class"}`
     * @see Total::GetClientObject()
     * @see Timed::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $data = parent::GetClientObject(true);

        $data['config']['track_items'] = $this->GetFeatureBool('track_items');
        $data['config']['track_dlstats'] = $this->GetFeatureBool('track_dlstats');
        
        if ($full)
        {
            $data['config_from'] = Utilities::array_map_keys(function($p){
                return static::toString($this->TryGetInheritsScalarFrom("$p")); }, array_keys($data['config']));
            
            $data['limits_from'] = Utilities::array_map_keys(function($p){
                return static::toString($this->TryGetInheritsScalarFrom("limit_$p")); }, array_keys($data['limits']));
        }
        
        return $data;
    }

    public static function GetBaseUsage() : string { return "[--track_items ?bool] [--track_dlstats ?bool]"; }
    
    protected function SetBaseLimits(SafeParams $params) : void
    {
        if ($params->HasParam('track_items'))
        {
            $this->SetFeatureBool('track_items', $params->GetParam('track_items')->GetNullBool());
            
            if ($this->isFeatureModified('track_items')) $init = true;
        }        
        
        if ($params->HasParam('track_dlstats')) 
        {
            $this->SetFeatureBool('track_dlstats', $params->GetParam('track_dlstats')->GetNullBool());
            
            if ($this->isFeatureModified('track_dlstats')) $init = true;
        }
        
        if ($init ?? false) $this->Initialize();
    }    
    
    /** Configures limits for the given account with the given input */
    public static function ConfigLimits(ObjectDatabase $database, Account $account, SafeParams $params) : self
    {
        return static::BaseConfigLimits($database, $account, $params);
    }    

    /**
     * Processes a group membership removal, possibly deleting this account limit
     * 
     * The account limit will be deleted if it does not have any properties that
     * were set specifically for it, and no other group limits applicable to it exist
     * @param Base $grlim group to remove
     */
    protected function BaseProcessGroupRemove(Base $grlim) : void
    {
        // see if the account has any properties specific to it
        foreach (array_keys($this->GetInheritedFields()) as $field)
        {
            if ($this->TryGetInheritsScalarFrom($field) === $this) return;
        }
        
        // see if the account is subject to any other group limits
        foreach ($this->GetGroups() as $grlim2)
            if ($grlim2 !== $grlim) return;
        
        $this->Delete();
    }
}

/** Concrete class providing account config and total stats */
class AccountTotal extends AuthEntityTotal
{ 
    use AccountCommon;
    
    public function ProcessGroupRemove(GroupTotal $grlim) : void
    {
        $this->BaseProcessGroupRemove($grlim);
    }
    
    /** cache of group limits that apply to this account */
    protected array $grouplims;

    /** 
     * loads group limits via a JOIN, caches, and returns them
     * @return array<string, GroupTotal> group limits indexed by ID
     */
    protected function GetGroups() : array
    {
        if (!isset($this->grouplims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->Equals($this->database->GetClassTableName(GroupJoin::class).'.objs_accounts', $this->GetAccountID()))
                ->Join($this->database, GroupJoin::class, 'objs_groups', GroupTotal::class, 'obj_object', Group::class);
            
            $this->grouplims = GroupTotal::LoadByQuery($this->database, $q);
            
            foreach ($this->GetAccount()->GetDefaultGroups() as $group)
            {
                $grouplim = GroupTotal::LoadByGroup($this->database, $group);
                if ($grouplim !== null) $this->grouplims[$grouplim->ID()] = $grouplim;
            }
        }
        
        return $this->grouplims;
    }
    
    /** register a group change handler that updates this specific object's grouplim cache */
    protected function PostConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            if ($this->isDeleted() || $account !== $this->GetAccount()) return;
            
            $grlim = GroupTotal::LoadByGroup($database, $group);
            if ($grlim === null || !isset($this->grouplims)) return;
            
            if ($added) $this->grouplims[$grlim->ID()] = $grlim;
            else unset($this->grouplims[$grlim->ID()]);
        });
    }

    protected function GetInheritedFields() : array { return array(
        'itemsharing' => true,
        'share2groups' => true,
        'share2everyone' => true,
        'emailshare' => true,
        'publicupload' => true,
        'publicmodify' => true,
        'randomwrite' => true,
        'userstorage' => true,
        'track_items' => false,
        'track_dlstats' => false,
        'limit_size' => null,
        'limit_items' => null,
        'limit_shares' => null,
    ); }
    
    public function GetAllowRandomWrite() : bool { return $this->GetFeatureBool('randomwrite'); }
    public function GetAllowPublicModify() : bool { return $this->GetFeatureBool('publicmodify'); }
    public function GetAllowPublicUpload() : bool { return $this->GetFeatureBool('publicupload'); }
    public function GetAllowItemSharing() : bool { return $this->GetFeatureBool('itemsharing'); }
    public function GetAllowShareToGroups() : bool { return $this->GetFeatureBool('share2groups'); }
    public function GetAllowShareToEveryone() : bool { return $this->GetFeatureBool('share2everyone'); }
    
    /** Returns true if this account is allowed to email share links */
    public function GetAllowEmailShare() : bool { return $this->GetFeatureBool('emailshare'); }
    
    /** Returns true if this account is allowed to add new filesystems */
    public function GetAllowUserStorage() : bool { return $this->GetFeatureBool('userstorage'); }
    
    /**
     * Returns the total limits object for this account
     * @param ObjectDatabase $database database reference
     * @param Account $account account of interest
     * @param bool $require if true and no limit exists, a fake object will be returned to retrieve defaults
     * @see AccountTotalDefault
     * @return ?self limit object or null
     */
    public static function LoadByAccount(ObjectDatabase $database, ?Account $account, bool $require = true) : ?self
    {
        $obj = ($account !== null) ? $obj = self::LoadByClient($database, $account) : null;
        
        // optionally return a fake object so the caller can get default limits/config
        if ($obj === null && $require) $obj = new AccountTotalDefault($database);
        
        return $obj;
    }
    
    /** 
     * Loads a limit object for the given account, creating it if it does not exist 
     * @return static
     */
    public static function ForceLoadByAccount(ObjectDatabase $database, Account $account) : self
    {
        return static::LoadByClient($database, $account) ?? static::Create($database, $account);
    }
    
    /**
     * Loads all limit objects for the given account, including its groups
     * @param ObjectDatabase $database database reference
     * @param Account $account account of interest
     * @return array<string, AuthEntityTotal> limits indexed by ID
     */
    public static function LoadByAccountAll(ObjectDatabase $database, Account $account) : array
    {
        $retval = array();
        
        $aclim = static::LoadByAccount($database, $account, false);
        
        if ($aclim !== null)
        {
            $retval = $aclim->GetGroups();
            $retval[$aclim->ID()] = $aclim;
        }        
        return $retval;
    }
  
    /** Initializes the account limit by adding stats from all FS items that it owns */
    public function Initialize() : self
    {
        parent::Initialize();
        
        if (!$this->canTrackItems()) return $this;
        
        $files = File::LoadByOwner($this->database, $this->GetAccount());
        $folders = Folder::LoadByOwner($this->database, $this->GetAccount());
        
        foreach ($files as $file) $this->AddFileCounts($file,true);
        foreach ($folders as $folder) $this->AddFolderCounts($folder,true);
                
        return $this;
    }
}

/** A fake empty account limits that returns default property values */
class AccountTotalDefault extends AccountTotal
{
    // TODO // public function __construct(ObjectDatabase $database) { parent::__construct($database, array()); }
    
    protected function GetGroups() : array { return array(); }
}

/** Concrete class providing timed account limits */
class AccountTimed extends AuthEntityTimed
{
    use AccountCommon { GetClientObject as protected CommonGetClientObject; }
    
    public function ProcessGroupRemove(GroupTimed $grlim) : void
    {
        $this->BaseProcessGroupRemove($grlim);
    }

    /** Returns the object from which this account limit inherits its max stats age */
    public function GetsMaxStatsAgeFrom() : ?AuthEntityTimed
    {
        return $this->TryGetInheritable('max_stats_age')->GetSource();
    }
    
    /** cache of group limits that apply to this account */
    protected array $grouplims;
        
    /**
     * loads group limits via a JOIN, caches, and returns them
     * @return array<string, GroupTimed> group limits indexed by ID
     */
    protected function GetGroups() : array
    {
        if (!isset($this->grouplims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->And($q->Equals('timeperiod',$this->GetTimePeriod()),$q->Equals($this->database->GetClassTableName(GroupJoin::class).'.objs_accounts', $this->GetAccountID())))
                ->Join($this->database, GroupJoin::class, 'objs_groups', GroupTimed::class, 'obj_object', Group::class);
            
            $this->grouplims = GroupTimed::LoadByQuery($this->database, $q);
            
            foreach ($this->GetAccount()->GetDefaultGroups() as $group)
            {
                $grouplim = GroupTimed::LoadByGroup($this->database, $group, $this->GetTimePeriod());
                if ($grouplim !== null) $this->grouplims[$grouplim->ID()] = $grouplim;
            }
        }
        
        return $this->grouplims;
    }
        
    /** register a group change handler that updates this specific object's grouplim cache */
    protected function PostConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            if ($this->isDeleted() || $account !== $this->GetAccount()) return;
            
            $grlim = GroupTimed::LoadByGroup($database, $group, $this->GetTimePeriod());
            if ($grlim === null || !isset($this->grouplims)) return;
            
            if ($added) $this->grouplims[$grlim->ID()] = $grlim;
            else unset($this->grouplims[$grlim->ID()]);
        });
    }
    
    protected function GetInheritedFields() : array { return array(
        'max_stats_age' => null,
        'track_items' => false,
        'track_dlstats' => false,
        'limit_pubdownloads' => null,
        'limit_bandwidth' => null
    ); }
    
    /** Returns the Timed limits for the given account and time period */
    public static function LoadByAccount(ObjectDatabase $database, Account $account, int $period) : ?self
    {
        return static::LoadByClientAndPeriod($database, $account, $period);
    }    
    
    /** Returns the Timed limits for the given account and time period, creating if it does not exist */
    public static function ForceLoadByAccount(ObjectDatabase $database, Account $account, int $period) : self
    {
        $obj = static::LoadByClientAndPeriod($database, $account, $period);        
        $obj ??= static::CreateTimed($database, $account, $period);        
        return $obj;
    }

    /**
     * Returns all timed limits for the given account and its groups
     * @param ObjectDatabase $database database reference
     * @param Account $account account of interest
     * @return array<string, AuthEntityTimed> limits indexed by ID
     */
    public static function LoadAllForAccountAll(ObjectDatabase $database, Account $account) : array
    {
        $retval = static::LoadAllForClient($database, $account);
        
        foreach ($retval as $aclim)
            $retval += $aclim->GetGroups();
        
        return $retval;
    }
    
    /**
     * Returns a printable client object that includes property inherit sources
     * @return array<mixed> `{max_stats_age_from:"id:class"}`
     * @see AccountCommon::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $data = $this->CommonGetClientObject($full);
        
        if ($full) $data['max_stats_age_from'] = static::toString($this->TryGetInheritsScalarFrom("max_stats_age"));

        return $data;
    }
}

// handle auto creating/deleting account limits and updating group stats when a group membership changes
Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
{
    if (($grlim = GroupTotal::LoadByGroup($database, $group)) !== null)
    {    
        if ($added) 
        {
            $aclim = AccountTotal::ForceLoadByAccount($database, $account);
            $grlim->ProcessAccountChange($aclim, true);
        }
        else if (($aclim = AccountTotal::LoadByAccount($database, $account)) !== null) 
        {
            $grlim->ProcessAccountChange($aclim, false);
            $aclim->ProcessGroupRemove($grlim);
        }
    }
    
    foreach (GroupTimed::LoadAllForGroup($database, $group) as $grlim)
    {
        if ($added) 
        {
            $aclim = AccountTimed::ForceLoadByAccount($database, $account, $grlim->GetTimePeriod());
            $grlim->ProcessAccountChange($aclim, true);
        }
        else if (($aclim = AccountTimed::LoadByAccount($database, $account, $grlim->GetTimePeriod())) !== null)
        {
            $grlim->ProcessAccountChange($aclim, false);
            $aclim->ProcessGroupRemove($grlim);
        }
    }
});

/** Handle deleting limits when an account is deleted */
Account::RegisterDeleteHandler(function(ObjectDatabase $database, Account $account)
{
    AccountTotal::DeleteByClient($database, $account);
    AccountTimed::DeleteByClient($database, $account);
});
