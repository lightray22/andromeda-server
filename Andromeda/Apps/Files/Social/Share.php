<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\SafeParams;

/**
 * A share granting access to an item
 *
 * Share targets can be via links with auth keys, with users,
 * or with groups of users.  They optionally can have extra
 * passwords, and expire based on certain criteria.  Shares also
 * track specific permissions for the access (see functions).
 * Folder shares also share all content under them.
 */
class Share extends BaseObject
{
    use AuthObject;
    
    protected const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_item' => new FieldTypes\ObjectPoly(Item::Class, 'shares'), // item being shared
            'obj_owner' => new FieldTypes\ObjectRef(Account::class),    // the account that made the share
            'obj_dest' => new FieldTypes\ObjectPoly(AuthEntity::class), // the group or account target of the share
            'label' => new FieldTypes\StringType(), // user-supplied label
            'password' => new FieldTypes\StringType(0), // possible password set on the share
            'date_accessed' => new FieldTypes\Timestamp(null, true),
            'count_accessed' => new FieldTypes\Counter(), // the count of accesses
            'limit_accessed' => new FieldTypes\Limit(),     // the maximum number of accesses
            'date_expires' => new FieldTypes\Timestamp(),   // the timestamp past which the share is not valid
            'read' => new FieldTypes\BoolType(true),
            'upload' => new FieldTypes\BoolType(false),
            'modify' => new FieldTypes\BoolType(false),
            'social' => new FieldTypes\BoolType(true),
            'reshare' => new FieldTypes\BoolType(false),
            'keepowner' => new FieldTypes\BoolType(true)
        ));
    }
    
    /** Returns true if this share is via a link rather than to an account/group */
    public function IsLink() : bool { return (bool)($this->TryGetScalar('authkey')); }
    
    /** Returns the item being shared */
    public function GetItem() : Item { return $this->GetObject('item'); }
    
    /** Returns the ID of the item being shared */
    public function GetItemID() : string { return $this->GetObjectID('item'); }
    
    /** Returns the account that created the share */
    public function GetOwner() : Account { return $this->GetObject('owner'); }
    
    /** Returns the ID of the account that created the share */
    public function GetOwnerID() : string { return $this->GetObjectID('owner'); }
    
    /** Returns the destination user/group of this share */
    public function GetDest() : ?AuthEntity { return $this->TryGetObject('dest'); }
    
    /** Returns true if the share grants read access to the item */
    public function CanRead() : bool { return $this->GetFeatureBool('read'); }
    
    /** Returns true if the share grants upload (create new files) to the item */
    public function CanUpload() : bool { return $this->GetFeatureBool('upload'); }
    
    /** Returns true if the share grants write access to the item */
    public function CanModify() : bool { return $this->GetFeatureBool('modify'); }
    
    /** Returns true if the share allows social features (comments, likes) on the item */
    public function CanSocial() : bool { return $this->GetFeatureBool('social'); }
    
    /** Returns true if the share allows the target to re-share the item */
    public function CanReshare() : bool { return $this->GetFeatureBool('reshare'); }
    
    /** True if the uploader should stay the owner, else the owner of the parent is the owner */
    public function KeepOwner() : bool { return $this->GetFeatureBool('keepowner'); }

    /** Returns true if the share is expired, either by access count or expiry time  */
    public function IsExpired() : bool
    {
        $expires = $this->TryGetDate('expires');
        if ($expires !== null && Main::GetInstance()->GetTime() > $expires) return true;
        
        return !$this->CheckCounter('accessed', 1, false);
    }
    
    /** Sets the share's access date to now and increments the access counter */
    public function SetAccessed() : self 
    {
        if ($this->IsExpired()) throw new Exceptions\ShareExpiredException();
        return $this->SetDate('accessed')->DeltaCounter('accessed');
    }

    /**
     * Creates a new share to a share target
     * @param ObjectDatabase $database database reference
     * @param Account $owner the owner of the share
     * @param Item $item the item being shared
     * @param AuthEntity $dest account or group target, or null for everyone
     * @return static new share object
     */
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, ?AuthEntity $dest) : self
    {
        if ($item->isWorldAccess()) throw new Exceptions\SharePublicItemException();
        
        $q = new QueryBuilder(); $w = $q->And(
            $q->IsNull('authkey'), $q->Equals('owner',$owner->ID()),
            $q->Equals('item',FieldTypes\ObjectPoly::GetObjectDBValue($item)),
            $q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($dest)));
            
        if (static::TryLoadUniqueByQuery($database, $q->Where($w)) !== null) throw new Exceptions\ShareExistsException(); 
        
        return static::BaseCreate($database,false)->SetObject('owner',$owner)->SetObject('item',$item->CountShare())->SetObject('dest',$dest);
    }

    /**
     * Returns a new link-based share object
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner of the share
     * @param Item $item item being shared
     * @return static new share object
     */
    public static function CreateLink(ObjectDatabase $database, Account $owner, Item $item) : self
    {
        return static::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item->CountShare());
    }
    
    /** Deletes the share */
    public function Delete() : void
    {
        if ($this->HasObject('item'))
            $this->GetItem()->CountShare(false);
        
        parent::Delete();
    }
    
    /** Returns true if this share requires a password to access */
    public function NeedsPassword() : bool { return (bool)($this->TryGetScalar('password')); }
    
    /** Returns true if the given password matches this share */
    public function CheckPassword(string $password) : bool
    {
        $hash = $this->GetScalar('password');        
        $correct = password_verify($password, $hash);
        if ($correct) $this->SetPassword($password, true);
        return $correct;
    }
    
    /**
     * Sets the given password for this share
     * @param string $password the password to set
     * @param bool $check if true, only set the password if a rehash is required
     * @return $this
     */
    protected function SetPassword(?string $password, bool $check = false) : self
    {
        if ($password === null) return $this->SetScalar('password',null);
             
        if (!$check || password_needs_rehash($this->GetScalar('password'), PASSWORD_ARGON2ID))
            $this->SetScalar('password', password_hash($password, PASSWORD_ARGON2ID));
        
        return $this;
    }
    
    /** Returns the command usage for SetOptions() */
    public static function GetSetOptionsUsage() : string { return "[--read bool] [--upload bool] [--modify bool] [--social bool] [--reshare bool] [--keepowner bool] ".
                                                                  "[--label ?text] [--spassword ?raw] [--expires ?float] [--maxaccess ?uint32]"; }
    
    /**
     * Modifies share permissions and properties from the given input
     * @param Share $access if not null, the share object granting this request access
     * @return $this
     */
    public function SetOptions(SafeParams $params, ?Share $access = null) : self
    {
        if ($params->HasParam('read'))
            $this->SetFeatureBool('read', $params->GetParam('read')->GetBool() && ($access === null || $access->CanRead()));
        // TODO double check logic here, why allow setting to false? seems weird
    
        if ($params->HasParam('upload'))
            $this->SetFeatureBool('upload', $params->GetParam('upload')->GetBool() && ($access === null || $access->CanUpload()));
        
        if ($params->HasParam('modify'))
            $this->SetFeatureBool('modify', $params->GetParam('modify')->GetBool() && ($access === null || $access->CanModify()));
        
        if ($params->HasParam('social'))
            $this->SetFeatureBool('social', $params->GetParam('social')->GetBool() && ($access === null || $access->CanSocial()));
        
        if ($params->HasParam('reshare'))
            $this->SetFeatureBool('reshare', $params->GetParam('reshare')->GetBool() && ($access === null || $access->CanReshare()));
        
        if ($params->HasParam('keepowner'))
            $this->SetFeatureBool('keepowner', $params->GetParam('keepowner')->GetBool() && ($access === null || $access->KeepOwner()));

        if ($params->HasParam('label')) $this->SetScalar('label',
            $params->GetParam('label')->GetNullHTMLText());
        
        if ($params->HasParam('spassword')) $this->SetPassword(
            $params->GetParam('spassword',SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        
        if ($params->HasParam('expires')) $this->SetDate('expires',
            $params->GetParam('expires')->GetNullFloat());
        
        if ($params->HasParam('maxaccess')) $this->SetCounterLimit('maxaccess',
            $params->GetParam('maxaccess')->GetNullUint32());
        
        return $this;
    }

    /**
     * Returns all shares owned by the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account account owner
     * @return array<string, Share> shares indexed by ID
     */
    public static function LoadByAccountOwner(ObjectDatabase $database, Account $account) : array
    {
        return static::LoadByObject($database, 'owner', $account);
    }
    
    /**
     * Returns all shares targeted at the given account or any of its groups
     * @param ObjectDatabase $database database reference
     * @param Account $account share target
     * @return array<string, Share> shares indexed by ID
     */
    public static function LoadByAccountDest(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); 
        
        $q->Join($database, GroupJoin::class, 'objs_groups', self::class, 'obj_dest', Group::class);        
        $w = $q->Equals($database->GetClassTableName(GroupJoin::class).'.objs_accounts', $account->ID());

        $shares = static::LoadByQuery($database, $q->Where($w));
        
        $q = new QueryBuilder(); 
        
        $defgroups = $q->Or(...array_map(function(Group $group)use($q){ 
            return $q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($group));
        }, array_values($account->GetDefaultGroups())));

        $w = $q->Or($q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($account)),
                    $defgroups, $q->And($q->IsNull('authkey'),$q->IsNull('dest')));

        return $shares + static::LoadByQuery($database, $q->Where($w));
    }

    /**
     * Checks whether this share gives access to the given item to the given account
     * 
     * Access to the item is given if this share is for the item or one of its parents,
     * and the share target is null (everyone), the given account, or a group held by the account
     * @param Account $account account requesting access
     * @param Item $item item requesting access for
     * @return bool true if access is granted
     */
    public function Authenticate(Account $account, Item $item) : bool
    {
        if ($this->IsLink()) return false;
        
        do
        {
            if ($item->ID() === $this->GetItemID())
            {
                $destobj = $this->GetDest();
                
                if ($destobj === null || $destobj === $account) return true;
                
                else if ($destobj instanceof Group && $account->HasGroup($destobj)) return true;
            }
        }
        while (($item = $item->GetParent()) !== null);
        
        return false;
    }
    
    /**
     * Checks whether this share gives access to the given item and checks the key
     * 
     * Access to the item is given if this share is for the item or one of its parents
     * @param string $key key that authenticates the share
     * @param Item $item item being requested access for
     * @return bool true if access is granted
     */
    public function AuthenticateByLink(string $key, Item $item) : bool
    {
        if (!$this->IsLink() || !$this->CheckKeyMatch($key)) return false;
        
        do { if ($item->ID() === $this->GetItemID()) return true; }
        while (($item = $item->GetParent()) !== null);
        
        return false;
    }
    
    /**
     * Returns a printable client object of this share
     * @param bool $item if true, show the item client object
     * @param bool $owner if true, we are showing the owner of the share
     * @return array<mixed> `{id:id, owner:id, item:Item|id, itemtype:enum, islink:bool, needpass:bool, dest:?id, desttype:?string, \
        expired:bool, dates:{created:float, expires:?float}, config:{read:bool, upload:bool, modify:bool, social:bool, reshare:bool, keepowner:bool}}` \
        if owner, add: `{label:text, dates:{accessed:?float}, counters:{accessed:int}, limits:{accessed:?int}}`
     * @see Item::SubGetClientObject()
     */
    public function GetClientObject(bool $item = false, bool $owner = true, bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'owner' => $this->GetOwnerID(),
            
            'item' => $item ? $this->GetItem()->GetClientObject() : $this->GetObjectID('item'),
            'itemtype' => Utilities::ShortClassName($this->GetObjectType('item')),
            
            'islink' => $this->IsLink(),
            'needpass' => $this->NeedsPassword(),
            
            'dest' => $this->TryGetObjectID('dest'),
            'desttype' => Utilities::ShortClassName($this->TryGetObjectType('dest')),
            
            'expired' => $this->IsExpired(),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'expires' => $this->TryGetDate('expires')
            ),
            'config' => array(
                'read' => $this->CanRead(),
                'upload' => $this->CanUpload(),
                'modify' => $this->CanModify(),
                'social '=> $this->CanSocial(),
                'reshare' => $this->CanReshare(),
                'keepowner' => $this->KeepOwner()
            )
        );
        
        if ($owner)
        {
            $data['label'] = $this->TryGetScalar('label');
            
            $data['dates']['accessed'] = $this->TryGetDate('accessed');
            
            $data['counters'] = array(
                'accessed' => $this->GetCounter('accessed'));
            
            $data['limits'] = array(
                'accessed' => $this->TryGetCounterLimit('accessed'));
        }

        if ($secret) $data['authkey'] = $this->GetAuthKey();
        
        return $data;
    }
}
