<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;

/** 
 * An object used to join together N other object classes (many to many relationship)
 * @template T of BaseObject
 * @template U of BaseObject
 */
abstract class JoinObject extends BaseObject
{
    /** @return array<class-string<static>, array<class-string<BaseObject>, array<string, BaseObject>>> */
    private static function &GetCache(ObjectDatabase $database) : array
    {
        return $database->GetCustomCache(self::class); // @phpstan-ignore-line cast generic to desired
    }

    /** @return FieldTypes\ObjectRefT<T> */
    protected abstract function GetLeftField() : FieldTypes\ObjectRefT;
    /** @return FieldTypes\ObjectRefT<U> */
    protected abstract function GetRightField() : FieldTypes\ObjectRefT;

    /**
     * Loads the join object that joins together the other classes
     * @param string $matchprop1 name of the column to match with
     * @param T $matchobj1 the object to match with
     * @param string $matchprop2 name of the column to match with
     * @param U $matchobj2 the object to match with
     */
    protected static function TryLoadJoinObject(ObjectDatabase $database, string $matchprop1, BaseObject $matchobj1, string $matchprop2, BaseObject $matchobj2) : ?static
    {
        $q = new QueryBuilder();
        $q->Where($q->Equals($matchprop1, $matchobj1->ID()));
        $q->Where($q->Equals($matchprop2, $matchobj2->ID()));

        return $database->TryLoadUniqueByQuery(static::class, $q);
    }

    /** 
     * Loads all objects that are joined to the other classes
     * @param string $matchprop name of the column to match with
     * @param class-string<T>|class-string<U> $destclass class type to load
     * @param string $matchprop name of the column to match with
     * @param T|U $matchobj the object to match with
     * @return ($matchobj is T ? array<string, U> : array<string, T>) array of joined objects
     */
    protected static function LoadFromJoin(ObjectDatabase $database, string $destprop, string $destclass, string $matchprop, BaseObject $matchobj) : array
    {
        $cache = &self::GetCache($database);
        $matchclass = $matchobj::class;

        if (isset($cache[static::class][$matchclass]))
            return $cache[static::class][$matchclass]; // @phpstan-ignore-line missing class-map feature

        $q = new QueryBuilder();
        $q->Where($q->Equals($matchprop, $matchobj->ID()));
        $q->Join($database, static::class, $destprop, $destclass::GetBaseTableClass(), 'id');

        $objs = $database->LoadObjectsByQuery($destclass, $q);
        return $cache[static::class][$matchclass] = $objs;
    }

    /**
     * Deletes all join objects matching the given criteria
     * @param string $matchprop name of the column to match with
     * @param T|U $matchobj the object to match with
     */
    protected static function DeleteJoinsFrom(ObjectDatabase $database, string $matchprop, BaseObject $matchobj) : int
    {
        $cache = &self::GetCache($database);

        $q = new QueryBuilder();
        $q->Where($q->Equals($matchprop, $matchobj->ID()));

        $btable = $matchobj::GetBaseTableClass();
        $cache[static::class][$btable] = array();
        
        return $database->DeleteObjectsByQuery(static::class, $q);
    }

    /** 
     * Creates a new join object, saves it, adds to the cache if loaded
     * @param T $obj1 left side of the join
     * @param U $obj2 right side of the join
     * @param ?callable(static): void $initfunc function to be run before saving
     */
    protected static function CreateJoin(ObjectDatabase $database, BaseObject $obj1, BaseObject $obj2, ?callable $initfunc = null) : static
    {
        $cache = &self::GetCache($database);

        $obj = $database->CreateObject(static::class);

        if (isset($cache[static::class][$obj1::class]))
            $cache[static::class][$obj1::class][$obj2->ID()] = $obj2;
        if (isset($cache[static::class][$obj2::class]))
            $cache[static::class][$obj2::class][$obj1->ID()] = $obj1;

        $obj->GetLeftField()->SetObject($obj1);
        $obj->GetRightField()->SetObject($obj2);

        if ($initfunc !== null) $initfunc($obj);

        return $obj->Save();
    }

    public function NotifyPostDeleted() : void
    {
        $obj1 = $this->GetLeftField()->GetObject();
        $obj2 = $this->GetRightField()->GetObject();

        $cache = &self::GetCache($this->database);
        unset($cache[static::class][$obj1::class][$obj2->ID()]);
        unset($cache[static::class][$obj2::class][$obj1->ID()]);

        parent::NotifyPostDeleted();
    }
}
