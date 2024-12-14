<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

/** 
 * A user-like (or dislike) on an item 
 * 
 * These are tracked per-like rather than as just counters
 * on items to prevent duplicates (and show who liked what)
 */
class Like extends BaseObject // TODO was StandardObject
{
    protected const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_owner' => new FieldTypes\ObjectRef(Account::class),
            'obj_item' => new FieldTypes\ObjectPoly(Item::Class, 'likes'),
            'value' => new FieldTypes\BoolType() // true if this is a like, false if a dislike
        ));
    }

    /**
     * Likes an item by creating or updating a like object
     * @param ObjectDatabase $database database reference
     * @param Account $owner the person doing the like
     * @param Item $item the item being liked
     * @param bool $value true if like, false if dislike, null to unset
     * @return ?static like object if $value is not null
     */
    public static function CreateOrUpdate(ObjectDatabase $database, Account $owner, Item $item, ?bool $value) : ?self
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('obj_owner',$owner->ID()),$q->Equals('obj_item',FieldTypes\ObjectPoly::GetObjectDBValue($item)));
        
        // load an existing like (can only like an item once)
        $likeobj = static::TryLoadUniqueByQuery($database, $q->Where($where));
        
        // create a new one if it doesn't exist
        $likeobj ??= static::BaseCreate($database)->SetObject('obj_owner',$owner)->SetObject('obj_item',$item);
                
        // "un-count" the old like first
        $item->CountLike($likeobj->TryGetScalar('value') ?? 0, true);
        
        if ($value !== null)
        {
            $item->CountLike($value);
            
            return $likeobj->SetScalar('value',$value)->SetDate('created');
        }
        else { $likeobj->Delete(); return null; }
    }
    
    /**
     * Returns a printable client object of this like
     * @return array<mixed> `{owner:id, item:id, value:bool, dates:{created:float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'value' => (bool)$this->GetScalar('value'),
            'dates' => array(
                'created' => $this->GetDateCreated()
            ),
        );
    }
}
