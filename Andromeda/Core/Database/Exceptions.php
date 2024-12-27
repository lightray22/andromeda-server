<?php declare(strict_types=1); namespace Andromeda\Core\Database\Exceptions; if (!defined('Andromeda')) die();

use PDOException;

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that the a write was requested to a read-only database */
class DatabaseReadOnlyException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("READ_ONLY_DATABASE", $details);
    }
}

/** Exception indicating that the given counter exceeded its limit */
class CounterOverLimitException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("COUNTER_EXCEEDS_LIMIT", $details);
    }
}

/** Exception indicating that database install config failed */
class DatabaseInstallException extends BaseExceptions\ClientErrorException
{
    public function __construct(DatabaseConnectException $e) {
        parent::__construct(""); $this->CopyException($e);
    }
}

/** Exception indicating the requested database path doesn't exist */
class DatabasePathException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_PATH_INVALID", $details);
    }
}



/** Exception indicating that a BaseObject was not set up properly */
abstract class ObjectSetupException extends BaseExceptions\ServerException { }

/** Exception indicating that the class cannot select from child classes */
class NotMultiTableException extends ObjectSetupException
{
    public function __construct(?string $details = null) {
        parent::__construct("TABLE_NOT_MULTI_CLASS", $details);
    }
}

/** Exception indicating that no fields were previously registered with a table */
class NoChildTableException extends ObjectSetupException
{
    public function __construct(?string $details = null) {
        parent::__construct("NO_CHILD_TABLE", $details);
    }
}

/** Exception indicating a base table is not given for this class */
class NoBaseTableException extends ObjectSetupException
{
    public function __construct(?string $details = null) {
        parent::__construct("NO_BASE_TABLE", $details);
    }
}

/** Exception indicating the requested class is not a child */
class BadPolyClassException extends ObjectSetupException
{
    public function __construct(?string $details = null) {
        parent::__construct("BAD_POLY_CLASS", $details);
    }
}

/** Exception indicating the given row has a bad type value */
class BadPolyTypeException extends ObjectSetupException
{
    public function __construct(?string $details = null) {
        parent::__construct("BAD_POLY_TYPE", $details);
    }
}

/** Exception indicating the API package is not yet set */
class ApiPackageException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("API_PACKAGE_NOT_SET", $details);
    }
}


/** Base class for database connection initialization exceptions */
abstract class DatabaseConnectException extends BaseExceptions\ServiceUnavailableException { }

/** Exception indicating that the database configuration is not found */
class DatabaseMissingException extends DatabaseConnectException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_CONFIG_MISSING", $details);
    }
}

/** Exception indicating that the database config is invalid */
class DatabaseConfigException extends DatabaseConnectException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_CONFIG_INVALID", $details);
    }
}

/** Exception indicating that the database connection failed to initialize */
class PDODatabaseConnectException extends DatabaseConnectException
{
    public function __construct(?PDOException $e = null) {
        parent::__construct("DATABASE_CONNECT_FAILED");
        if ($e !== null) $this->AppendException($e);
    }
}

/** Exception indicating the file could not be imported because it's missing */
class ImportFileMissingException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("IMPORT_FILE_MISSING", $details);
    }
}

/** Base class representing a run-time database error */
abstract class DatabaseException extends BaseExceptions\ServerException
{
    public function __construct(string $message = "DATABASE_ERROR", ?string $details = null) {
        parent::__construct($message, $details);
    }
}

/** Exception indicating that the update failed to match any objects */
class UpdateFailedException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_UPDATE_FAILED", $details);
    }
}

/** Exception indicating that the row insert failed */
class InsertFailedException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_INSERT_FAILED", $details);
    }
}

/** Exception indicating that the row delete failed */
class DeleteFailedException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_DELETE_FAILED", $details);
    }
}

/** Exception indicating that loading via a foreign key link failed */
class ForeignKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_FOREIGN_KEY_FAILED", $details);
    }
}

/** Exception that the DB provided null for a non-null field */
class FieldDataNullException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("FIELD_DATA_NULL", $details);
    }
}

/** Exception indicating that PDO failed to execute the given query */
class DatabaseQueryException extends DatabaseException
{
    public function __construct(PDOException $e) {
        parent::__construct("DATABASE_QUERY_ERROR");
        $this->AppendException($e);
    }
}

/** Exception indicating that fetching results from the query failed */
class DatabaseFetchException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_FETCH_FAILED", $details);
    }
}

/** Exception indicating the database had an integrity violation */
class DatabaseIntegrityException extends DatabaseException
{
    public function __construct(PDOException $e) {
        parent::__construct("DATABASE_INTEGRITY_VIOLATION");
        $this->AppendException($e);
    }
}

/** Andromeda cannot rollback and then commit/save since database/objects state is not sufficiently reset */
class SaveAfterRollbackException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("SAVE_AFTER_ROLLBACK", $details);
    }
}

/** Exception indicating that Save() was called on a deleted object */
class SaveAfterDeleteException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("SAVE_AFTER_DELETE", $details);
    }
}

/** Exception indicating that the requested class does not match the loaded object */
class ObjectTypeException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DBOBJECT_TYPE_MISMATCH", $details);
    }
}

/** Exception indicating that multiple objects were loaded for by-unique query */
class MultipleUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("MULTIPLE_UNIQUE_OBJECTS", $details);
    }
}

/** Exception indicating the given unique key is not registered for this class */
class UnknownUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_UNIQUE_KEY", $details);
    }
}

/** Exception indicating that the requested object is null */
class SingletonNotFoundException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("SINGLETON_NOT_FOUND", $details);
    }
}
