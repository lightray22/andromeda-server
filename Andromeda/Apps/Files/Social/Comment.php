<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

/** A user comment on an item */
class Comment extends BaseObject // TODO was StandardObject
{
    protected const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_owner'  => new FieldTypes\ObjectRef(Account::class),
            'obj_item' => new FieldTypes\ObjectPoly(Item::Class, 'comments'),
            'comment' => new FieldTypes\StringType(),
            'date_modified' => new FieldTypes\Timestamp()
        ));
    }
    
    /** Sets the value of this comment and updates the modified date */
    public function SetComment(string $val){ return $this->SetScalar('comment', $val)->SetDate('modified'); }
    
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, string $comment) : self
    {
        return static::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item)->SetComment($comment);
    }
    
    /** Tries to load a comment object by the given account and comment ID */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('obj_owner',FieldTypes\ObjectPoly::GetObjectDBValue($account)),$q->Equals('id',$id));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($where));
    }
    
    /**
     * Returns a printable client object of this comment
     * @return array<mixed> `{id:id,owner:id,item:id,comment:string,dates{created:float,modified:?float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'comment' => $this->GetScalar('comment'),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'modified' => $this->TryGetDate('modified')
            ),
        );
    }
}
