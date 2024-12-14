<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

use Andromeda\Core\{ApiPackage, Utilities};

/**
 * The base class for objects that can be saved/loaded from the database.
 * Objects can be a single table or be split into base/child class database tables.
 * Splitting allows using foreign keys/object links to link to abstract types.
 * Objects can be loaded via child classes, which will join base tables together top-down,
 * or via base classes which will load every corresponding child class together.
 * The final child type does not necessarily need to have its own table.
 * @phpstan-consistent-constructor
 */
abstract class BaseObject
{
    /** 
     * The length of the unique object ID
     * @var positive-int
     */
    protected const IDLength = 12;

    /**
     * Returns the list of classes with database tables for loading this class, ordered base->derived
     * Only classes with a table should implement this (add to result from parent)
     * @return array<class-string<self>>
     */
    public static function GetTableClasses() : array { return array(); }
    
    /**
     * Returns the base table for this class
     * @throws Exceptions\NoBaseTableException if none
     * @return class-string<self>
     */
    final public static function GetBaseTableClass() : string
    {
        $tables = static::GetTableClasses();
        if (count($tables) !== 0) return $tables[0];
        else throw new Exceptions\NoBaseTableException(static::class);
    }

    /**
     * Returns the array of subclasses that exist for this base class, so we can load via it
     * Non-abstract base classes can include self if non-child rows exist
     * Non-abstract classes (no children) should always return empty
     * If using auto-typed rows, the mapping must be stable across versions
     * Classes must have the @ return line in order to pass type checking!
     * @return array<class-string<static>>
     */
    public static function GetChildMap(ObjectDatabase $database) : array { return array(); }
    
    /**
     * Returns this class's unique key map (must add parents!)
     * This base function adds 'id' to the base table, so if overriding, add it!
     * NOTE - there isn't a pre-determined map for non-unique keys, instead the DB will cache as Load/Delete by key is used!
     * @return array<class-string<self>, list<string>>
     */
    public static function GetUniqueKeys() : array
    {
        return array(self::GetBaseTableClass() => array('id'));
    }
    
    /** Returns true iff GetWhereChild/GetRowClass can be used */
    public static function HasTypedRows() : bool { return false; }
    
    /**
     * Given a child class, return a query clause selecting rows for it (and adds token data to the query)
     * Only for base classes that are the final table for > 1 class (TypedChildren)
     * Classes must have the @ return line in order to pass type checking!
     * @param ObjectDatabase $database database reference
     * @param QueryBuilder $q query to add WHERE clause to
     * @param class-string<self> $class child class we want to filter by
     * @return string the clause for row matching (e.g. 'type = 5')
     */
    public static function GetWhereChild(ObjectDatabase $database, QueryBuilder $q, string $class) : string { 
        throw new Exceptions\NotMultiTableException(self::class); }
    
    /**
     * Given a database row, return the child class applicable
     * Only for base classes that are the final table for > 1 class (TypedChildren)
     * @param array<string,?scalar> $row row of data from the database
     * @return class-string<static> child class of row
     */
    public static function GetRowClass(ObjectDatabase $database, array $row) : string { 
        throw new Exceptions\NotMultiTableException(self::class); }

    /**
     * Returns a string suitable as a new object ID (primary key)
     * Object IDs must be unique for all objects of a base class
     */
    protected static function GenerateID() : string
    {
        return Utilities::Random(static::IDLength);
    }

    /**
     * Loads an object by its ID
     * @param ObjectDatabase $database database ref
     * @param string $id the ID of the object
     * @return ?static object or null if not found
     */
    final public static function TryLoadByID(ObjectDatabase $database, string $id) : ?BaseObject
    {
        return $database->TryLoadUniqueByKey(static::class, 'id', $id, true);
    }
    
    /** 
     * Returns all available external auth objects
     * @param ?non-negative-int $limit limit number of objects
     * @param ?non-negative-int $offset offset of limited result set
     * @return array<string, static>
     */
    public static function LoadAll(ObjectDatabase $database, ?int $limit = null, ?int $offset = null) : array
    {
        $q = (new QueryBuilder())->Limit($limit)->Offset($offset);
        return $database->LoadObjectsByQuery(static::class, $q); // empty query
    }
    
    /** Primary reference to the database */
    protected ObjectDatabase $database;
    
    /** The field with the object ID */
    private FieldTypes\StringType $idfield;
    
    /** @var array<string, FieldTypes\BaseField> */
    private array $fieldsByName = array();
    
    /** 
     * Fields for each subclass, ordered derived->base 
     * @var array<class-string<self>, array<FieldTypes\BaseField>>
     */
    private array $fieldsByClass = array();
    
    /** true if the object is newly created and not yet saved */
    private bool $isCreated = false;

    /** true if the object has been deleted */
    private bool $isDeleted = false;
    
    /** true if the object should be deleted when saved */
    private bool $deleteLater = false;
    
    /** Returns the object's associated database */
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    
    /** Returns the associated Api Package */
    public function GetApiPackage() : ApiPackage { return $this->database->GetApiPackage(); }
    
    /**
     * Construct an object from loaded database data
     * @param ObjectDatabase $database database
     * @param array<string, ?scalar> $data db columns
     * @param bool $created true if this is a new object
     */
    public function __construct(ObjectDatabase $database, array $data, bool $created)
    {
        $this->isCreated = $created;
        $this->database = $database;
        
        $this->CreateFields();

        foreach ($data as $column=>$value)
            $this->fieldsByName[$column]->InitDBValue($value);

        if ($created)
            $this->idfield->SetValue(static::GenerateID());
        
        $this->PostConstruct($created);
    }
    
    /** 
     * Performs any subclass-specific initialization
     * @param bool $created true if this is a new object
     */
    protected function PostConstruct(bool $created) : void { }

    /**
     * Instantiates the object's database fields - subclasses should override!
     * Subclasses must create their field objects and send to RegisterFields()
     * Child classes *must* register first, then call their parent's CreateFields
     */
    protected function CreateFields() : void
    {
        $this->idfield = new FieldTypes\StringType('id');
        
        foreach (static::GetTableClasses() as $table)
        {
            // make sure idfield shows up in every fieldsByClass
            $this->RegisterFields(array($this->idfield), $table);
        }
    }
    
    /**
     * Registers fields for the object so the DB can save/load objects
     * @param array<FieldTypes\BaseField> $fields array of fields to register
     * @param class-string<self> $table class table the fields belong to
     */
    final protected function RegisterFields(array $fields, string $table) : void
    {
        $this->fieldsByClass[$table] ??= array();
        
        foreach ($fields as $field)
        {
            if (isset($this->database))
                $field->SetParent($this);
            
            $this->fieldsByClass[$table][] = $field;
            $this->fieldsByName[$field->GetName()] = $field;
        }
    }
    
    /**
     * Helper for base classes without tables to register fields with the last child table
     * @param array<FieldTypes\BaseField> $fields array of fields to register
     * @throws Exceptions\NoChildTableException if $table is null and no previously registered table
     */
    final protected function RegisterChildFields(array $fields) : void
    {
        if (count($this->fieldsByClass) === 0)
            throw new Exceptions\NoChildTableException(static::class);
        
        $table = array_key_last($this->fieldsByClass);
        $this->RegisterFields($fields, $table);
    }
    
    /** Returns this object's base-unique ID */
    public function ID() : string { return $this->idfield->GetValue(); }

    /** Returns the string "id:class" where id is the object ID and class is its short class name */
    final public function __toString() : string 
    { 
        return $this->ID().':'.static::class;
    }
    
    /** Returns the given object's as a string if not null, else null */
    final public static function toString(?self $obj) : ?string 
    {
        return $obj !== null ? (string)$obj : null; 
    }
    
    /** Returns true if this object is newly created and not yet saved */
    final public function isCreated() : bool { return $this->isCreated; }
    
    /** Returns true if this object has been deleted */
    final public function isDeleted() : bool { return $this->isDeleted; }
    
    /** Returns true if this object has a modified field */
    final protected function isModified() : bool
    {
        foreach ($this->fieldsByName as $field)
        {
            if ($field->isModified()) return true;
        }
        return false;
    }

    /** Sets all fields as unmodified */
    final public function SetUnmodified() : void
    {
        foreach ($this->fieldsByName as $field)
            $field->SetUnmodified();
    }

    /** Deletes the object from the database */
    public function Delete() : void
    {
        $this->database->DeleteObject($this);
    }
    
    /** 
     * Schedules the object to be deleted when Save() is called 
     * @return $this
     */
    final protected function DeleteLater(bool $delete = true) : self 
    { 
        $this->deleteLater = $delete; return $this;
    }
    
    /** 
     * Notifies this object that the DB is about to delete it - internal call only
     * Child classes can extend this if they need extra on-delete logic
     */
    public function NotifyPreDeleted() : void { }
    
    /** 
     * Notifies this object that the DB has deleted it - internal call only
     * Child classes can extend this if they need extra on-delete logic - MUST call parent
     */
    public function NotifyPostDeleted() : void
    {
        $this->isDeleted = true;
    }
    
    /**
     * Inserts this object to the DB if created, updates this object if modified
     * @param bool $onlyAlways true if we only want to save alwaysSave fields (see Field saveOnRollback)
     * @throws Exceptions\SaveAfterDeleteException if the object is deleted
     * @return $this
     */
    public function Save(bool $onlyAlways = false) : self
    {
        if ($this->isDeleted)
            throw new Exceptions\SaveAfterDeleteException();
        else if ($this->deleteLater)
            $this->database->DeleteObject($this);
        else $this->database->SaveObject($this, $this->fieldsByClass, $onlyAlways);

        $this->isCreated = false;
        return $this;
    }
}
