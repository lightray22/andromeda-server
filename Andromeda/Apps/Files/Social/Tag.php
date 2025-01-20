<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};

/** A category tag placed on an item */
class Tag extends BaseObject // TODO was StandardObject
{
    protected const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_owner' => new FieldTypes\ObjectRef(Account::class),
            'obj_item' => new FieldTypes\ObjectPoly(Item::Class, 'tags'),
            'tag' => new FieldTypes\StringType() // the text value of the tag
        ));
    }
    
    /** Returns the item for this tag */
    public function GetItem() : Item { return $this->GetObject('item'); }

    /**
     * Creates a new tag on an item
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner creating the tag
     * @param Item $item item being tagged
     * @param string $tag the text value of the tag
     * @return static new tag object
     */
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, string $tag) : self
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('obj_item',FieldTypes\ObjectPoly::GetObjectDBValue($item)),$q->Equals('tag',$tag));
        if (($ex = static::TryLoadUniqueByQuery($database, $q->Where($where))) !== null) return $ex;
        
        return static::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item)->SetScalar('tag',$tag);
    }

    /**
     * Returns a printable client object of this tag
     * @return array<mixed> `{id:id, owner:id, item:id, tag:string, dates:{created:float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'tag' => $this->GetScalar('tag'),
            'dates' => array(
                'created' => $this->GetDateCreated(),
            )
        );
    }
}
