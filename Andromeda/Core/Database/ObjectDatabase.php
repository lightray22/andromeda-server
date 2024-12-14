<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

use Andromeda\Core\ApiPackage;

/**
 * Provides the interfaces between BaseObject and the underlying PDO database
 *
 * Basic functions include loading, caching, updating, creating, and deleting objects.
 * Attention is paid to being efficient and doing as few queries as possible.
 * This class should only be used internally by BaseObjects.
 */
class ObjectDatabase
{
    /** PDO database reference */
    private PDODatabase $db;
    
    /** ApiPackage reference */
    private ApiPackage $apipack;
    
    /** time of construction */
    private float $time;
    
    public function __construct(PDODatabase $db) 
    {
        $this->db = $db; $this->time = microtime(true);
    }
    
    /** Returns the internal database instance */
    public function GetInternal() : PDODatabase { return $this->db; }
    
    /** Gets the timestamp of when the db was constructed */
    public function GetTime() : float { return $this->time; }
    
    /** 
     * Returns the ApiPackage reference 
     * @throws Exceptions\ApiPackageException if not set
     */
    public function GetApiPackage() : ApiPackage 
    {
        if (!isset($this->apipack))
            throw new Exceptions\ApiPackageException();
        
        return $this->apipack;
    }
    
    /** Returns true if the ApiPackage reference is set */
    public function HasApiPackage() : bool { return isset($this->apipack); }
    
    /** Sets the API package for objects to use */
    public function SetApiPackage(ApiPackage $apipack) : self 
    { 
        $this->apipack = $apipack; return $this; 
    }

    /** @var array<string, array<mixed>> */
    private array $caches = array();

    /**
     * Registers a custom "static" cache that is tied to this DB's lifetime
     * @return array<mixed>
     */
    public function &GetCustomCache(string $name) : array
    {
        if (!array_key_exists($name, $this->caches))
            $this->caches[$name] = array();
        return $this->caches[$name];
    }
    
    private bool $rolledBack = false;
    
    /** Commits the internal database */
    public function commit() : void
    {
        $this->db->commit();
    }
    
    /** Rolls back the internal database */
    public function rollback() : void
    {
        $this->rolledBack = true;
        $this->db->rollback();
    }
    
    /** @see PDODatabase::isReadOnly() */
    public function isReadOnly() : bool 
    { 
        return $this->db->isReadOnly();
    }
    
    /** array of objects that were created indexed by splhash
     * @var array<string, BaseObject> */
    private array $created = array();
    
    /** array of objects that were modified indexed by splhash
     * @var array<string, BaseObject> */
    private array $modified = array();
    
    /** 
     * Notify the DB that the given object needs to be updated 
     * @throws Exceptions\DatabaseReadOnlyException if read-only
     * @return $this
     */
    public function notifyModified(BaseObject $object) : self
    {
        if ($this->isReadOnly())
            throw new Exceptions\DatabaseReadOnlyException();

        $this->modified[spl_object_hash($object)] = $object; return $this;
    }

    /**
     * Create/update all objects that notified us as needing it (INTERNAL only)
     * @param bool $onlyAlways true if we only want to save "alwaysSave" properties
     * @return $this
     */
    public function SaveObjects(bool $onlyAlways = false) : self
    {
        if (!$onlyAlways && $this->rolledBack)
            throw new Exceptions\SaveAfterRollbackException();
        
        // insert new objects first for foreign keys
        foreach ($this->created as $obj) $obj->Save($onlyAlways);
        $this->created = array();
        
        foreach ($this->modified as $obj) $obj->Save($onlyAlways);
        $this->modified = array();

        return $this;
    }
    
    /**
     * Return the database table name for a class
     * @template T of BaseObject
     * @param class-string<T> $class the class name for the table
     */
    public function GetClassTableName(string $class) : string
    {
        $class = explode('\\',$class); 
        unset($class[0]); // no Andromeda_ prefix
        $table = "a2obj_".strtolower(implode('_', $class));
        
        return $this->db->UsePublicSchema() ? "public.$table" : $table;
    }

    /**
     * Counts objects using the given query (ignores limit/offset)
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects
     * @param QueryBuilder $query the query used to match objects
     * @return int count of objects
     */
    public function CountObjectsByQuery(string $class, QueryBuilder $query) : int
    {
        $classes = $class::GetTableClasses();
        if (count($classes) === 0 || 
                ($class !== ($topclass = $classes[array_key_last($classes)]) && !$topclass::HasTypedRows()))
        { // count foreach child table
            $childmap = $class::GetChildMap($this);
            $count = 0; foreach ($childmap as $child)
            {
                if ($class === $child) throw new Exceptions\NoBaseTableException($class);
                $count += $this->CountObjectsByQuery($child, $query);
            }
            return $count;
        }
        
        // count from the table directly
        $query = clone $query;  // GetSelectFromAndSetJoins may modify
        $this->GetSelectFromAndSetJoins($class, $query); // ignore retval
        
        // if not the top table, filter only the type we want (mini-SetTypeFiltering)
        if ($topclass !== $class) // we know $topclass::HasTypedRows is true from above
            $query->Where($topclass::GetWhereChild($this, $query, $class));

        $ftable = $this->GetClassTableName($classes[0]);
        $selstr = "SELECT ".($prop = "COUNT($ftable.id)")." FROM $ftable $query";
    
        $res = $this->db->read($selstr, $query->GetParams())[0];
        return (int)$res[$this->db->FullSelectFields() ? $prop : "count"];
    }

    /**
     * Loads an array of objects matching the given query
     * If using ORDER BY and there are child tables, the returned array may not be sorted!
     * But, the ORDER BY will apply correctly as far as LIMIT/OFFSET and WHAT gets loaded
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects
     * @param QueryBuilder $query the query used to match objects
     * @param ?class-string<T> $baseClass the base class being loaded (internal ONLY!)
     * @return array<string, T> array of objects indexed by their IDs
     */
    public function LoadObjectsByQuery(string $class, QueryBuilder $query, ?string $baseClass = null) : array
    {
        $baseClass ??= $class;
        $childmap = $class::GetChildMap($this);
        
        $castRows = $this->CanLoadByUpcast($class);
        $doChildren = (count($childmap) !== 0) && !$castRows;
        $doSelf = !$doChildren; // may change later
        $objects = array();
        
        if ($doChildren) // need to load for each child with a table
        {
            foreach ($childmap as $child)
            {
                if ($child === $class) { $doSelf = true; continue; }
                $objs = $this->LoadObjectsByQuery($child, $query, $baseClass);
                foreach ($objs as $obj) $objects[$obj->ID()] = $obj; // merge
            }
        }
        
        if ($doSelf) // if concrete, do an actual load for this class
        {
            $query = clone $query; // GetSelectFromAndSetJoins may modify
            $this->SetTypeFiltering($class, $baseClass, $query, false);
            $selstr = $this->GetSelectFromAndSetJoins($class, $query);
            $selstr = "SELECT $selstr $query";
            
            foreach ($this->db->read($selstr, $query->GetParams()) as $row)
            {
                $object = $this->ConstructObject($class, $row, $castRows);
                $objects[$object->ID()] = $object;
            }
        }

        return $objects;
    }

    /**
     * Immediately delete objects matching the given query
     * The objects will be loaded and have their NotifyPreDeleted() run
     * If using ORDER BY and there are child tables, objects may not be deleted in order!
     * But, the ORDER BY will apply correctly as far as LIMIT/OFFSET and WHAT gets deleted
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects to delete
     * @param QueryBuilder $query the query used to match objects
     * @param ?array<string,T> $objects array that will be deleted, if already known (optimization)
     * @return int number of deleted objects
     */
    public function DeleteObjectsByQuery(string $class, QueryBuilder $query, ?array $objects = null) : int
    {
        if ($this->isReadOnly())
            throw new Exceptions\DatabaseReadOnlyException();

        // NOTE we assume ON DELETE CASCADE and do a single query to delete only from the base table
        // Originally, this was similar to Load() where we would do a query for each child and delete child rows
        // manually using multi-delete syntax. This was difficult to get working syntactically in all three DBs, and never
        // actually worked in MySQL because the multi-table syntax does NOT guarantee in which table order it deletes from!
        // CASCADE could hypothetically result in bad deletes if the class for a child table is missing...
        
        $classes = $class::GetTableClasses();
        if (count($classes) === 0 || 
                ($class !== ($topclass = $classes[array_key_last($classes)]) && !$topclass::HasTypedRows()))
        { // delete foreach child table
            $childmap = $class::GetChildMap($this);
            $count = 0; foreach ($childmap as $child)
            {
                if ($class === $child) throw new Exceptions\NoBaseTableException($class);
                $count += $this->DeleteObjectsByQuery($child, $query);
            }
            return $count;
        }

        // We SELECT rows to be deleted, inform them of delete, then DELETE with the equivalent query
        // NOTE this relies on REPEATABLE READ since the two queries must return the same objects!
        $count = count($objects ??= $this->LoadObjectsByQuery($class, $query));
        foreach ($objects as $obj) $obj->NotifyPreDeleted();

        $query = clone $query; // GetSelectFromAndSetJoins may modify
        $this->SetTypeFiltering($class, $class, $query, true); // no self filter
        $this->GetSelectFromAndSetJoins($class, $query); // ignore retval

        $basetbl = $this->GetClassTableName($class::GetBaseTableClass());
        $selstr = "SELECT $basetbl.id FROM $basetbl $query";

        // mariadb doesn't support "LIMIT & IN/ALL/ANY/SOME" unless wrapped in an extra subquery...
        $delstr = "DELETE FROM $basetbl WHERE id IN (SELECT id FROM ($selstr) AS t)";

        if (($ret = $this->db->write($delstr, $query->GetParams())) !== $count)
            throw new Exceptions\DeleteFailedException("$class ret:$ret want:$count");

        foreach ($objects as $obj) $this->RemoveObject($obj);

        return $count;
    }

    /**
     * Loads a unique object matching the given query
     * @see ObjectDatabase::LoadObjectsByQuery()
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param QueryBuilder $query the query used to match objects
     * @throws Exceptions\MultipleUniqueKeyException if > 1 object is loaded
     * @return ?T loaded object or null
     */
    public function TryLoadUniqueByQuery(string $class, QueryBuilder $query) : ?BaseObject
    {
        $objs = $this->LoadObjectsByQuery($class, $query);
        if (count($objs) > 1) throw new Exceptions\MultipleUniqueKeyException($class);
        return (count($objs) === 1) ? array_values($objs)[0] : null;
    }
    
    /**
     * Deletes a unique object matching the given query
     * @see ObjectDatabase::DeleteObjectsByQuery()
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param QueryBuilder $query the query used to match objects
     * @throws Exceptions\MultipleUniqueKeyException if > 1 object is loaded
     * @return bool true if an object was deleted
     */
    public function TryDeleteUniqueByQuery(string $class, QueryBuilder $query) : bool
    {
        $count = $this->DeleteObjectsByQuery($class, $query);
        if ($count > 1) throw new Exceptions\MultipleUniqueKeyException($class);
        return $count !== 0;
    }

    /**
     * Returns true iff the given class can be loaded by upcasting rows
     * to child classes rather than doing per-child queries (OPTIMIZATION)
     * @param class-string<BaseObject> $class class to be loaded
     * @return bool true iff every child has no table or children (is final)
     */
    private function CanLoadByUpcast(string $class) : bool
    {
        if (!$class::HasTypedRows()) return false;
        
        $childmap = $class::GetChildMap($this);
        if (count($childmap) === 0) return false;
        
        $tables = $class::GetTableClasses();
        if (count($tables) === 0) return false;
        
        foreach ($childmap as $child)
        {
            $subquery = ($class !== $child) &&                
                (count($child::GetChildMap($this)) !== 0 || // has children
                 count($tables) !== count($child::GetTableClasses())); // has table
            
            if ($subquery) return false;
        }
        return true;
    }

    /**
     * Compiles JOINs for a class and returns the FROM table list
     * @template T of BaseObject
     * @param class-string<T> $class class name to build a query for
     * @param QueryBuilder $query pre-existing query to add JOINS to
     * @return string (table list) FROM (base table)
     */
    private function GetSelectFromAndSetJoins(string $class, QueryBuilder $query) : string
    {
        $classes = $class::GetTableClasses();
        if (count($classes) === 0) throw new Exceptions\NoBaseTableException($class);
        $basetbl = $this->GetClassTableName($classes[0]);
        
        // specify which tables to select in case of extra joins
        $selstr = implode(', ',array_map(function(string $sclass){
            return $this->GetClassTableName($sclass).'.*'; }, $classes));

        // join together every base class table
        $bclass = null; foreach ($classes as $pclass)
        {
            if ($bclass !== null) // join base -> parent (child fields override)
                $query->Join($this, $pclass,'id', $bclass,'id',false);
            $bclass = $pclass;
        }
        
        return "$selstr FROM $basetbl";
    }

    /**
     * Sets/fixes the WHERE/LIMIT/OFFSET/ORDER for polymorphism
     * @template T of BaseObject
     * @param class-string<T> $class class name to build a query for
     * @param class-string<T> $bclass the base class the user requested
     * @param QueryBuilder $query pre-existing query to add to (may modify!)
     * @param bool $fullSelf if true, don't allow type filter to self::class
     */
    private function SetTypeFiltering(string $class, string $bclass, QueryBuilder $query, bool $fullSelf) : void
    {       
        $classes = $class::GetTableClasses();
        if (count($classes) === 0) throw new Exceptions\NoBaseTableException($class);
        // if loading by upcast, don't select the type here since it will be done later
        $selectType = !$this->CanLoadByUpcast($class);
        
        // we want the limit/offset to apply to the class being loaded as,
        // not the current child table... use a subquery to accomplish this
        if (($query->GetLimit() !== null || $query->GetOffset() !== null) &&
            ($class !== $bclass || ($selectType && $class::HasTypedRows())))
        {            
            // the limited base class must have a table
            $bclasses = $bclass::GetTableClasses();
            if (count($bclasses) === 0 || $bclass !== $bclasses[array_key_last($bclasses)])
                throw new Exceptions\NoBaseTableException($bclass);
            
            $subquery = clone $query;
            $query->Where(null)->Limit(null)->Offset(null)->OrderBy(null);
            
            $this->GetSelectFromAndSetJoins($bclass, $subquery);
            $btable = $this->GetClassTableName($bclass);
            
            $subqstr = "SELECT $btable.id FROM $btable $subquery";
            $query->Where("$btable.id IN (SELECT id FROM ($subqstr) AS t)");
        }
        
        // if not the top table, filter only the type we want
        $topclass = $classes[array_key_last($classes)];
        if ($selectType && $topclass::HasTypedRows() 
                && ($class !== $topclass || !$fullSelf))
            $query->Where($topclass::GetWhereChild($this, $query, $class));
    }

    /** master array of objects in memory to prevent duplicates
     * @var array<class-string<BaseObject>, array<string, BaseObject>> */
    private array $objectsByBase = array();

    /**
     * Returns an array of loaded object info for debugging, by class
     * @return array<class-string<BaseObject>, array<string, class-string<BaseObject>>>
     */
    public function getLoadedObjects() : array
    {
        $retval = array(); 
        foreach ($this->objectsByBase as $bclass=>$objs)
            foreach ($objs as $obj)
        {
            $retval[$bclass][$obj->ID()] = get_class($obj);
        }
        return $retval;
    }
    
    /** Returns the total count of loaded objects */
    public function getLoadedCount() : int
    {
        $retval = 0;
        foreach ($this->objectsByBase as $objs)
            $retval += count($objs);
        return $retval;
    }
    
    /**
     * Constructs a new object from a row if not already loaded
     * @template T of BaseObject
     * @param class-string<T> $class final row class to instantiate
     * @param array<string, ?scalar> $row row of data from DB
     * @param bool $upcast if true, upcast rows before constructing
     * @throws Exceptions\ObjectTypeException if already loaded a different type
     * @return T instantiated object
     */
    private function ConstructObject(string $class, array $row, bool $upcast) : BaseObject
    {
        $id = (string)$row['id'];

        $base = $class::GetBaseTableClass();
        $this->objectsByBase[$base] ??= array();
        
        // if this object is already loaded, don't replace it
        if (array_key_exists($id, $this->objectsByBase[$base]))
            $retobj = $this->objectsByBase[$base][$id];
        else
        {
            if ($upcast) $class = $class::GetRowClass($this,$row);
            
            $retobj = new $class($this, $row, false);
            $this->objectsByBase[$base][$id] = $retobj;
            $this->RegisterUniqueKeys($base);
            $this->SetUniqueKeysFromData($retobj, $row);
        }
        
        if (!($retobj instanceof $class))
            throw new Exceptions\ObjectTypeException("$retobj not $class");

        return $retobj;
    }
    
    /**
     * Removes the object from our arrays and calls NotifyPostDeleted()
     * @param BaseObject $object object that has been deleted
     */
    private function RemoveObject(BaseObject $object) : void
    {
        unset($this->created[spl_object_hash($object)]);
        unset($this->modified[spl_object_hash($object)]);
        
        $base = $object::GetBaseTableClass();
        unset($this->objectsByBase[$base][$object->ID()]);
        $this->UnsetAllObjectKeyFields($object);
        
        $object->NotifyPostDeleted();
    }

    /**
     * Creates a new object and registers for saving
     * @template T of BaseObject
     * @param class-string<T> $class BaseObject class to create
     * @return T instantiated object
     * @throws Exceptions\DatabaseReadOnlyException if read-only
     */
    public function CreateObject(string $class) : BaseObject
    {
        if ($this->isReadOnly())
            throw new Exceptions\DatabaseReadOnlyException();
    
        $obj = new $class($this, array(), true);
        $this->created[spl_object_hash($obj)] = $obj;
        return $obj;
    }

    /**
     * Immediately deletes a single object from the database
     * @param BaseObject $object the object to delete
     * @throws Exceptions\DeleteFailedException if nothing is deleted
     * @throws Exceptions\DatabaseReadOnlyException if read-only
     * @return $this
     */
    public function DeleteObject(BaseObject $object) : self
    {
        if ($this->isReadOnly())
            throw new Exceptions\DatabaseReadOnlyException();

        $object->NotifyPreDeleted();

        if (!array_key_exists(spl_object_hash($object),$this->created))
        {
            $class = get_class($object);
            $query = new QueryBuilder();
            $query->Where($query->Equals('id',$object->ID()));

            // assume ON DELETE CASCADE - delete from basetbl
            $basetbl = $this->GetClassTableName($class::GetBaseTableClass());
            $delstr = "DELETE FROM $basetbl $query";

            if ($this->db->write($delstr, $query->GetParams()) !== 1)
                throw new Exceptions\DeleteFailedException($class);
        }

        $this->RemoveObject($object);
        return $this;
    }

    /**
     * Saves the given object in the database
     * @param BaseObject $object object to insert or update
     * @param array<class-string<BaseObject>, array<FieldTypes\BaseField>> $fieldsByClass 
        fields to save of object for each table class, order derived->base (ALL! not only modified)
     * @param bool $onlyAlways true if we only want to save alwaysSave fields (see Field saveOnRollback)
     * @throws Exceptions\UpdateFailedException if the update row fails
     * @throws Exceptions\InsertFailedException if the insert row fails
     * @throws Exceptions\DatabaseReadOnlyException if read-only
     * @return $this
     */
    public function SaveObject(BaseObject $object, array $fieldsByClass, bool $onlyAlways = false) : self
    {
        if ($this->isReadOnly())
            throw new Exceptions\DatabaseReadOnlyException();

        if (array_key_exists(spl_object_hash($object),$this->created))
            return $onlyAlways ? $this : $this->InsertObject($object, $fieldsByClass);
        else return $this->UpdateObject($object, $fieldsByClass);
    }
    
    /**
     * Updates the given object in the database
     * @param BaseObject $object object to update
     * @param array<class-string<BaseObject>, array<FieldTypes\BaseField>> $fieldsByClass 
        fields to save of object for each table class, order derived->base (will only save modified)
     * @param bool $onlyAlways true if we only want to save alwaysSave fields (see Field saveOnRollback)
     * @throws Exceptions\UpdateFailedException if the update row fails
     * @return $this
     */
    protected function UpdateObject(BaseObject $object, array $fieldsByClass, bool $onlyAlways = false) : self
    {
        foreach ($fieldsByClass as $class=>$fields)
        {
            $fields = array_filter($fields, function(FieldTypes\BaseField $field)use($onlyAlways) {
                return $field->isModified() && (!$onlyAlways || $field->isAlwaysSave()); });
            if (count($fields) === 0) continue; // nothing to update

            $data = array('id'=>$object->ID()); 
            $sets = array(); $i = 0;
            
            foreach ($fields as $field)
            {
                $key = $field->GetName();
                $val = $field->GetDBValue();
                
                if ($val === null)
                {
                    $sets[] = "\"$key\"=NULL";
                }
                else if ($field instanceof FieldTypes\Counter)
                {
                    $sets[] = "\"$key\"=\"$key\"+:d$i";
                    $data['d'.$i++] = $val;
                }
                else
                {
                    $sets[] = "\"$key\"=:d$i";
                    $data['d'.$i++] = $val;
                }
            }
            
            $setstr = implode(', ',$sets);
            $table = $this->GetClassTableName($class);
            $query = "UPDATE $table SET $setstr WHERE id=:id";
            
            if ($this->db->write($query, $data) !== 1)
                throw new Exceptions\UpdateFailedException($class);
            
            $this->UnsetObjectKeyFields($object, $fields);
            $this->SetObjectKeyFields($object, $fields);
        }
        
        unset($this->modified[spl_object_hash($object)]);
        $object->SetUnmodified();
        return $this;
    }
    
    /**
     * Inserts the given object to the database
     * @param BaseObject $object object to insert
     * @param array<class-string<BaseObject>, array<FieldTypes\BaseField>> $fieldsByClass 
          fields to save of object for each table class, order derived->base (ALL! not only modified)
     * @throws Exceptions\InsertFailedException if the insert row fails
     * @return $this
     */
    protected function InsertObject(BaseObject $object, array $fieldsByClass) : self
    {
        $base = $object::GetBaseTableClass();
        $this->objectsByBase[$base][$object->ID()] = $object;
        $this->RegisterUniqueKeys($base);

        foreach (array_reverse($fieldsByClass) as $class=>$fields)
        {
            $columns = array(); 
            $indexes = array();
            $data = array(); $i = 0;
            
            foreach ($fields as $field)
            {
                if (!$field->isModified()) continue;

                $key = $field->GetName();
                $val = $field->GetDBValue();
                
                $columns[] = "\"$key\"";
                if ($val === null)
                {
                    $indexes[] = "NULL";
                }
                else
                {
                    $indexes[] = ':d'.$i;
                    $data['d'.$i++] = $val;
                }
            }
            
            $colstr = implode(',',$columns);
            $idxstr = implode(',',$indexes);
            $table = $this->GetClassTableName($class);
            $query = "INSERT INTO $table ($colstr) VALUES ($idxstr)";

            if ($this->db->write($query, $data) !== 1)
                throw new Exceptions\InsertFailedException($class);
            
            // NOTE this is the reason we need ALL fields not just modified
            // the caches weren't populated because this object wasn't "loaded"
            $this->SetObjectKeyFields($object, $fields);
        }
        
        unset($this->created[spl_object_hash($object)]);
        unset($this->modified[spl_object_hash($object)]);
        $object->SetUnmodified();
        return $this;
    }
    
    /** Everything BELOW this point places a basic
     * key-cache layer on top of the ObjectDatabase */
    
    /** Array of class, then non-unique key, then value to object array
     * @var array<class-string<BaseObject>, array<string, array<string, array<string, BaseObject>>>> */
    private array $objectsByKey = array();

    /** Array of object to property key/val being used in objectsByKey
     * @var array<string, array<string, string>> */
    private array $objectsKeyValues = array();
    
    /** array of class, then unique key, then value to object or null
     * @var array<class-string<BaseObject>, array<string, array<string, ?BaseObject>>> */
    private array $uniqueByKey = array();
    
    /** array of object to property key/val being used in uniqueByKey
     * @var array<string, array<string, string>> */
    private array $uniqueKeyValues = array();
    
    /** array of class and property key to its base class
     * @var array<class-string<BaseObject>, array<string, class-string<BaseObject>>> */
    private array $keyBaseClasses = array();
    
    /**
     * Converts a scalar value to a unique string
     * Null will return a different result than any non-null values
     * @param ?scalar $value index data value
     */
    private static function ValueToIndex($value) : string
    {
        if ($value === null) return '';
        // want false/0 to match as true/1 do
        if ($value === false) $value = 0;
        // want null to be a distinct string
        return 'k'.$value;
    }
    
    /**
     * Counts objects matching the given key/value (uses cache if loaded)
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return int object count
     */
    public function CountObjectsByKey(string $class, string $key, $value) : int
    {
        $validx = self::ValueToIndex($value);
        $this->RegisterNonUniqueKey($class, $key);
        
        if (array_key_exists($validx, $this->objectsByKey[$class][$key]))
            return count($this->objectsByKey[$class][$key][$validx]);
        else
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            return $this->CountObjectsByQuery($class, $q);
        }
    }

    /**
     * Loads and caches objects matching the given key/value
     * Also caches results so it only calls the DB once for a given key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return array<string, T> loaded objects indexed by ID
     */
    public function LoadObjectsByKey(string $class, string $key, $value) : array
    {
        $validx = self::ValueToIndex($value);
        $this->RegisterNonUniqueKey($class, $key);
        
        if (!array_key_exists($validx, $this->objectsByKey[$class][$key]))
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $objs = $this->LoadObjectsByQuery($class, $q);
            
            $this->SetNonUniqueKeyObjects($class, $key, $validx, $objs);
            
            foreach ($objs as $obj)
                $this->objectsKeyValues[spl_object_hash($obj)][$key] = $validx;
        }

        return $this->objectsByKey[$class][$key][$validx]; // @phpstan-ignore-line missing class-map feature
    }
    
    /**
     * Deletes objects matching the given key/value
     * Also updates the cache so the DB is not called if this key/value is loaded
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return int number of deleted objects
     */
    public function DeleteObjectsByKey(string $class, string $key, $value) : int
    {
        $validx = self::ValueToIndex($value);
        $this->RegisterNonUniqueKey($class, $key);

        if (!array_key_exists($validx, $this->objectsByKey[$class][$key]))
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $count = $this->DeleteObjectsByQuery($class, $q);
        }
        else if (($count = count($this->objectsByKey[$class][$key][$validx])) !== 0)
        {
            // rely on REPEATABLE READ - delete query result should match the cache
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            
            if (($count2 = $this->DeleteObjectsByQuery($class, $q, 
                    $this->objectsByKey[$class][$key][$validx])) !== $count)
                throw new Exceptions\DeleteFailedException("$class want:$count ret:$count2");
        }
        else $count = 0; // nothing to delete
        
        $this->objectsByKey[$class][$key][$validx] = array();
        return $count;
    }

    /**
     * Loads and caches a unique object matching the given unique key/value
     * Also caches results so it only calls the DB once for a given key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param string $key data key to match
     * @param scalar $value data value to match
     * @param bool $ambiguousKey the key is ambiguous, prefix it with its table name
     * @throws Exceptions\UnknownUniqueKeyException if the key is not registered
     * @throws Exceptions\MultipleUniqueKeyException if > 1 object is loaded
     * @return ?T loaded object or null
     */
    public function TryLoadUniqueByKey(string $class, string $key, $value, bool $ambiguousKey = false) : ?BaseObject
    {
        $this->RegisterUniqueKeys($class);
        
        if (!array_key_exists($key, $this->uniqueByKey[$class]))
            throw new Exceptions\UnknownUniqueKeyException("$class $key");
        
        $validx = self::ValueToIndex($value);

        if (!array_key_exists($validx, $this->uniqueByKey[$class][$key]))
        {
            $qkey = $ambiguousKey ? $this->GetClassTableName($class).".\"$key\"" : "\"$key\"";
            $q = new QueryBuilder(); $q->Where($q->Equals($qkey, $value, false));
            $objs = $this->LoadObjectsByQuery($class, $q);
            
            if (count($objs) > 1) throw new Exceptions\MultipleUniqueKeyException("$class $key");
            $obj = (count($objs) === 1) ? array_values($objs)[0] : null;
            
            $this->SetUniqueKeyObject($class, $key, $validx, $obj);

            if ($obj !== null)
                $this->uniqueKeyValues[spl_object_hash($obj)][$key] = $validx;
        }

        return $this->uniqueByKey[$class][$key][$validx]; // @phpstan-ignore-line missing class-map feature
    }
    
    /**
     * Deletes a unique object matching the given unique key/value
     * Also updates the cache so the DB is not called if this key/value is loaded
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param string $key data key to match
     * @param scalar $value data value to match
     * @throws Exceptions\UnknownUniqueKeyException if the key is not registered
     * @throws Exceptions\MultipleUniqueKeyException if > 1 object is loaded
     * @return bool true if an object was deleted
     */
    public function TryDeleteUniqueByKey(string $class, string $key, $value) : bool
    {
        $this->RegisterUniqueKeys($class);
        
        if (!array_key_exists($key, $this->uniqueByKey[$class]))
            throw new Exceptions\UnknownUniqueKeyException("$class $key");
            
        $validx = self::ValueToIndex($value);

        if (array_key_exists($validx, $this->uniqueByKey[$class][$key]))
        {
            $obj = $this->uniqueByKey[$class][$key][$validx];
            $count = ($obj !== null) ? 1 : 0;
            if ($obj !== null) $this->DeleteObject($obj);
        }
        else
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $count = $this->DeleteObjectsByQuery($class, $q);
            if ($count > 1) throw new Exceptions\MultipleUniqueKeyException("$class $key");
        }
        
        $this->uniqueByKey[$class][$key][$validx] = null; 
        
        return $count !== 0;
    }
    
    /**
     * Sets the registered base class for a class key if not already set or lower than existing
     * @template T of BaseObject
     * @param class-string<T> $class class to register the key for
     * @param string $key key name being registered
     * @param class-string<T> $bclass key's base class to register
     */
    private function SetKeyBaseClass(string $class, string $key, string $bclass) : void
    {
        if (!array_key_exists($key, $this->keyBaseClasses[$class] ?? array()) ||
            is_a($this->keyBaseClasses[$class][$key], $bclass, true))
        {
            $this->keyBaseClasses[$class][$key] = $bclass;
        }
    }
    
    /**
     * Sets the given array of objects for a non-unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx index value of the key field
     * @param array<string, T> $objs array of objects to set
     */
    private function SetNonUniqueKeyObjects(string $class, string $key, string $validx, array $objs) : void
    {
        $this->objectsByKey[$class][$key][$validx] = $objs;
        
        foreach ($class::GetChildMap($this) as $child)
        {
            if ($child !== $class)
            {
                $objs2 = array_filter($objs, function(BaseObject $obj)use($child){ return $obj instanceof $child; });
                $this->SetNonUniqueKeyObjects($child, $key, $validx, $objs2);
            }    
        }
    }
    
    /**
     * Adds the given object to a non-unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx index value of the key field
     * @param T $obj object to add to the array
     */
    private function AddNonUniqueKeyObject(string $class, string $key, string $validx, BaseObject $obj) : void
    {
        $this->objectsByKey[$class][$key][$validx][$obj->ID()] = $obj;
        
        foreach ($class::GetChildMap($this) as $child)
        {
            if ($child !== $class)
            {
                if ($obj instanceof $child)
                    $this->AddNonUniqueKeyObject($child, $key, $validx, $obj);
            }
        }
    }
    
    /**
     * Removes an object from a non-unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx index value of the key field
     * @param T $object object being removed
     */
    private function RemoveNonUniqueKeyObject(string $class, string $key, string $validx, BaseObject $object) : void
    {
        unset($this->objectsByKey[$class][$key][$validx][$object->ID()]);
        
        foreach ($class::GetChildMap($this) as $child)
        {
            if ($child !== $class)
                $this->RemoveNonUniqueKeyObject($child, $key, $validx, $object);
        }
    }
    
    /**
     * Registers a class's unique keys so caching can happen
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     */
    private function RegisterUniqueKeys(string $class) : void
    {
        if (!array_key_exists($class, $this->uniqueByKey))
        {
            $this->uniqueByKey[$class] = array();
            
            foreach ($class::GetUniqueKeys() as $bclass=>$keys) 
                foreach ($keys as $key)
            {
                $this->SetKeyBaseClass($class, $key, $bclass);
                $this->uniqueByKey[$class][$key] = array();
            }
                
            foreach ($class::GetChildMap($this) as $child)
            {
                if ($child !== $class)
                    $this->RegisterUniqueKeys($child);
            }
        }
    }

    /**
     * Registers a class's non-unique key so caching can happen
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param ?class-string<T> $bclass base class for property
     */
    private function RegisterNonUniqueKey(string $class, string $key, ?string $bclass = null) : void
    {
        $bclass ??= $class;
        $this->SetKeyBaseClass($class, $key, $bclass); 
        $this->objectsByKey[$class][$key] ??= array();

        foreach ($class::GetChildMap($this) as $child)
        {
            if ($child !== $class)
                $this->RegisterNonUniqueKey($child, $key, $bclass);
        }
    }

    /**
     * Sets the given object to a unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx index value of the key field
     * @param ?T $obj object to set as the cached object or null
     */
    private function SetUniqueKeyObject(string $class, string $key, string $validx, ?BaseObject $obj) : void
    {
        $this->uniqueByKey[$class][$key][$validx] = $obj;
        
        foreach ($class::GetChildMap($this) as $child)
        {
            if ($child !== $class)
            {
                $obj2 = ($obj !== null && $obj instanceof $child) ? $obj : null;
                $this->SetUniqueKeyObject($child, $key, $validx, $obj2);
            }
        }
    }
    
    /**
     * Adds the given object's row data to any existing unique key caches
     * @param BaseObject $object object being created
     * @param array<string, ?scalar> $data row from database
     */
    private function SetUniqueKeysFromData(BaseObject $object, array $data) : void
    {
        $objstr = spl_object_hash($object);
        $class = get_class($object);
        
        foreach ($data as $key=>$value)
        {
            if ($value !== null && array_key_exists($key, $this->uniqueByKey[$class]))
            {
                $validx = self::ValueToIndex($value);
                $this->uniqueKeyValues[$objstr][$key] = $validx;
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->SetUniqueKeyObject($bclass, $key, $validx, $object);
            }
        }
    }
    
    /**
     * Adds the given object's fields to any existing key caches
     * @param BaseObject $object object being created
     * @param array<FieldTypes\BaseField> $fields field values
     */
    private function SetObjectKeyFields(BaseObject $object, array $fields) : void
    {
        $objstr = spl_object_hash($object);
        $class = get_class($object);
        
        $this->objectsByKey[$class] ??= array();
        $this->uniqueByKey[$class] ??= array();

        foreach ($fields as $field)
        {
            $key = $field->GetName();
            $value = $field->GetDBValue();
            $validx = self::ValueToIndex($value);
            
            if (array_key_exists($key, $this->objectsByKey[$class]) &&
                array_key_exists($validx, $this->objectsByKey[$class][$key]))
                // must have loaded for this value since there could be others (non-unique)
            {
                $this->objectsKeyValues[$objstr][$key] = $validx;
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->AddNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
            
            if ($value !== null && array_key_exists($key, $this->uniqueByKey[$class]))
            // don't need to have loaded for this value since there can only be one (unique)
            {
                $this->uniqueKeyValues[$objstr][$key] = $validx;
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->SetUniqueKeyObject($bclass, $key, $validx, $object);
            }
        }
    }

    /**
     * Unsets the value of a unique key cache (to null)
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx index value of the key field
     */
    private function UnsetUniqueKeyObject(string $class, string $key, string $validx) : void
    {
        $this->uniqueByKey[$class][$key][$validx] = null;
        
        foreach ($class::GetChildMap($this) as $child)
        {
            if ($child !== $class)
                $this->UnsetUniqueKeyObject($child, $key, $validx);
        }
    }

    /**
     * Removes the fields for the given object from any existing key-based caches
     * Uses the cached "old-value" for the fields rather than their current value
     * @param BaseObject $object object being created
     * @param array<FieldTypes\BaseField> $fields field values
     */
    private function UnsetObjectKeyFields(BaseObject $object, array $fields) : void
    {
        $objstr = spl_object_hash($object);
        $class = get_class($object);

        $this->objectsKeyValues[$objstr] ??= array();
        $this->uniqueKeyValues[$objstr] ??= array();

        foreach ($fields as $field)
        {
            $key = $field->GetName();
            
            if (array_key_exists($key, $this->objectsKeyValues[$objstr]))
            {
                $validx = $this->objectsKeyValues[$objstr][$key];
                unset($this->objectsKeyValues[$objstr][$key]);
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->RemoveNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
            
            if (array_key_exists($key, $this->uniqueKeyValues[$objstr]))
            {
                $validx = $this->uniqueKeyValues[$objstr][$key];
                unset($this->uniqueKeyValues[$objstr][$key]);
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->UnsetUniqueKeyObject($bclass, $key, $validx);
            }
        }
    }
    
    /**
     * Removes the given object (all fields) from the key-based caches
     * @param BaseObject $object object to remove
     */
    private function UnsetAllObjectKeyFields(BaseObject $object) : void
    {
        $objstr = spl_object_hash($object);
        $class = get_class($object);
        
        if (array_key_exists($objstr, $this->objectsKeyValues))
        {
            foreach ($this->objectsKeyValues[$objstr] as $key=>$validx)
            {
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->RemoveNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
        }
        
        if (array_key_exists($objstr, $this->uniqueKeyValues))
        {
            foreach ($this->uniqueKeyValues[$objstr] as $key=>$validx)
            {
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->UnsetUniqueKeyObject($bclass, $key, $validx);
            }
        }
        
        unset($this->objectsKeyValues[$objstr]);
        unset($this->uniqueKeyValues[$objstr]);
    }
}
