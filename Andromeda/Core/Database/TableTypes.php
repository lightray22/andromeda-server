<?php declare(strict_types=1); namespace Andromeda\Core\Database\TableTypes; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, Exceptions, FieldTypes, ObjectDatabase, QueryBuilder};

/** A trait for classes that have a database table */
trait HasTable
{
    public static function GetTableClasses() : array
    {
        $tables = parent::GetTableClasses();
        $tables[] = self::class; return $tables;
    }
}

/** A trait for classes with no typed children (may have linked children) */
trait NoTypedChildren
{
    public static function HasTypedRows() : bool { return false; }
    
    public static function GetWhereChild(ObjectDatabase $database, QueryBuilder $q, string $class) : string
    {
        throw new Exceptions\NotMultiTableException(self::class);
    }
    
    /** @return class-string<self> child class of row */
    public static function GetRowClass(ObjectDatabase $database, array $row) : string
    {
        throw new Exceptions\NotMultiTableException(self::class);
    }
}

/** A trait for classes with no children */
trait NoChildren
{
    use NoTypedChildren;
    
    public static function GetChildMap(ObjectDatabase $database) : array { return array(); }
}

/** A trait for classes with a table and no children */
trait TableNoChildren
{
    use HasTable, NoChildren;
}

/** A trait for base classes with a table whose children in 
 * GetChildMap() all have their own tables linked via foreign key */
trait TableLinkedChildren
{
    use HasTable, NoTypedChildren;
}

/** A trait for base classes with a table whose types in GetChildMap() do not all have their own table,
 * i.e. this is the final (most derived) table for > 1 class, or is both final and non-final.
 * Uses a field named "type" to determine which rows are what for GetWhereChild() */
trait TableTypedChildren
{
    use HasTable;
    
    public static function HasTypedRows() : bool { return true; }

    public static function GetWhereChild(ObjectDatabase $database, QueryBuilder $q, string $class) : string
    {
        $map = array_flip(self::GetChildMap($database));
        
        if (!array_key_exists($class, $map))
            throw new Exceptions\BadPolyClassException($class);
        
        $table = $database->GetClassTableName(self::class);
        return $q->Equals("$table.type",$map[$class],false);
    }
    
    /** @return class-string<self> child class of row */
    public static function GetRowClass(ObjectDatabase $database, array $row) : string
    {
        $type = (string)$row['type']; 
        $map = self::GetChildMap($database);
        
        if (!array_key_exists($type, $map)) // does int/string conversions
            throw new Exceptions\BadPolyTypeException($type);
        
        return $map[$type];
    }

    public function __construct(ObjectDatabase $database, array $data, bool $created = false)
    {
        parent::__construct($database, $data, $created);
        if (!$created) return; // early return

        foreach (self::GetChildMap($this->database) as $type=>$class)
        {
            // determine the type value based on the object
            if ($this instanceof $class) 
            {
                $this->typefield->SetValue($type); 
                if ($class !== self::class) break; 
            }
        }
    }
}

/** 
 * The typefield is normally undefined for all but the most derived class as when joining base tables, 
 * the DB will only give us the type from the most derived table (the field name conflicts between tables).
 * It only actually matters for inserting rows into the DB though which is fine because InitTypeField() will make
 * sure they are set for every table. After that, they can't change, and the value only matters for the top table.
*/

/** TableTypedChildren with an int typefield */
trait TableIntTypedChildren
{
    use TableTypedChildren;
    
    private FieldTypes\IntType $typefield;
    
    /**
     * @return array<FieldTypes\BaseField> an array with the internal type field
     * ... to be merged into the field array by users of this trait
     */
    private function GetTypeFields() : array
    {
        // only the most derived class will have 'type' accessible
        return array($this->typefield = new FieldTypes\IntType('type'));
    }
}

/** TableTypedChildren with an string typefield */
trait TableStringTypedChildren
{
    use TableTypedChildren;

    private FieldTypes\StringType $typefield;
    
    /**
     * @return array<FieldTypes\BaseField> an array with the internal type field
     * ... to be merged into the field array by users of this trait
     */
    private function GetTypeFields() : array
    {
        // only the most derived class will have 'type' accessible
        return array($this->typefield = new FieldTypes\StringType('type'));
    }
}
