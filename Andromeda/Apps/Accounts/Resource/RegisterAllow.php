<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;

/** 
 * RegisterAllow entry for allowing account signups
 * @phpstan-type RegisterAllowJ array{date_created:float, type:key-of<self::TYPES>, value:string}
 */
class RegisterAllow extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    /** A allowlisted username */
    public const TYPE_USERNAME = 64; // make space for future contact types

    public const TYPES = Contact::TYPES + array('username'=>self::TYPE_USERNAME);
    
    /** The type of allowlist entry */
    private FieldTypes\IntType $type; // keep it simple, no polymorphism
    /** The value of the allowlist entry */
    private FieldTypes\StringType $value;
    /** Date the entry was created */
    private FieldTypes\Timestamp $date_created;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->type = $fields[] =  new FieldTypes\IntType('type');
        $this->value = $fields[] = new FieldTypes\StringType('value');
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /**
     * Creates a new allowlist entry
     * @param ObjectDatabase $database database reference
     * @param value-of<self::TYPES> $type entry type enum
     * @param string $value value of allowlist entry
     */
    public static function Create(ObjectDatabase $database, int $type, string $value) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->type->SetValue($type);
        $obj->value->SetValue($value);
        return $obj;
    }

    public static function GetUsage() : string
    {
        return implode("|",array("--username alphanum", Contact::GetFetchUsage()));
    }

    /**
     * Fetches a type/value pair from input (depends on the param name given)
     * @throws Exceptions\ContactNotGivenException if nothing valid was found
     * @return array{type:value-of<self::TYPES>, value:string}
     */
    public static function FetchPairFromParams(SafeParams $params) : array
    {
        if ($params->HasParam('username')) 
        { 
            $value = $params->GetParam('username',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
            return array('type'=>self::TYPE_USERNAME, 'value'=>$value);
        }

        $cpair = Contact::FetchPairFromParams($params);
        return array('type'=>Contact::ChildClassToType($cpair['class']), 'value'=>$cpair['address']);
    }

    /**
     * Checks whether an allowlist entry exists
     * @param ObjectDatabase $database database reference
     * @param value-of<self::TYPES> $type type enum of allowlist entry
     * @param string $value value of allowlist entry
     * @return bool true if it exists (is allowlisted)
     */
    public static function ExistsTypeAndValue(ObjectDatabase $database, int $type, string $value) : bool
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('type',$type),$q->Equals('value',$value));
        
        return ($database->TryLoadUniqueByQuery(self::class, $q) !== null);
    }
    
    /**
     * Removes an allowlist entry, if it exists 
     * @param ObjectDatabase $database database reference
     * @param value-of<self::TYPES> $type type enum of allowlist entry
     * @param string $value value of allowlist entry
     */
    public static function DeleteByTypeAndValue(ObjectDatabase $database, int $type, string $value) : bool
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('type',$type),$q->Equals('value',$value));
        
        return $database->TryDeleteUniqueByQuery(self::class, $q);
    }
    
    /**
     * Returns a printable client object for this entry
     * @return RegisterAllowJ
     */
    public function GetClientObject() : array
    {
        return array(
            'date_created' => $this->date_created->GetValue(),
            'type' => array_flip(self::TYPES)[$this->type->GetValue()], 
            'value' => $this->value->GetValue()
        );
    }    
}
