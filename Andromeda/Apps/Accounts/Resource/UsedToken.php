<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

/** Object for tracking used two factor codes, to prevent replay attacks */
class UsedToken extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    /** Date the token was used */
    private FieldTypes\Timestamp $date_created;
    /** The two factor code that was used */
    private FieldTypes\StringType $code;
    /** 
     *  The twofactor this token was used for
     * @var FieldTypes\ObjectRefT<TwoFactor> 
     */
    private FieldTypes\ObjectRefT $twofactor;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->code         = $fields[] = new FieldTypes\StringType('code');
        $this->twofactor    = $fields[] = new FieldTypes\ObjectRefT(TwoFactor::class, 'twofactor');
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Returns the value of the used token */
    public function GetCode() : string { return $this->code->GetValue(); }
    
    /** Returns the two factor code this is associated with */
    public function GetTwoFactor() : TwoFactor { return $this->twofactor->GetObject(); }
    
    /** Prunes old codes from the database that are too old to be valid anyway */
    public static function PruneOldCodes(ObjectDatabase $database) : int
    {
        $mintime = $database->GetTime()-(TwoFactor::TIME_TOLERANCE*2*30);
        $q = new QueryBuilder(); $q->Where($q->LessThan('date_created', $mintime));
        
        return $database->DeleteObjectsByQuery(static::class, $q);
    }
    
    /** Logs a used token with the given twofactor object and code */
    public static function Create(ObjectDatabase $database, TwoFactor $twofactor, string $code) : self
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        
        $obj->code->SetValue($code);
        $obj->twofactor->SetObject($twofactor);
        
        $obj->date_created->isInitialized(); // no-op fix PHPStan value not read
        
        return $obj;        
    }
    
    /**
     * Loads all used codes for a twofactor
     * @param ObjectDatabase $database database reference
     * @param TwoFactor $twofactor twofactor parent
     * @return array<string, static> used tokens
     */
    public static function LoadByTwoFactor(ObjectDatabase $database, TwoFactor $twofactor) : array
    {
        return $database->LoadObjectsByKey(static::class, 'twofactor', $twofactor->ID());
    }
    
    /**
     * Delete all used codes for a twofactor
     * @param ObjectDatabase $database database reference
     * @param TwoFactor $twofactor twofactor parent
     * @return int number of deleted codes
     */
    public static function DeleteByTwoFactor(ObjectDatabase $database, TwoFactor $twofactor) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'twofactor', $twofactor->ID());
    }
}
