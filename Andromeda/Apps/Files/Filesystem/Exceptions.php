<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that the given filesystem name is invalid */
class InvalidNameException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_FILESYSTEM_NAME", $details);
    }
}

/** Exception indicating that the underlying storage connection failed */
class InvalidStorageException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("STORAGE_ACTIVATION_FAILED", $details);
    }
}

/** Exception indicating that the stored filesystem type is not valid */
class InvalidFSTypeException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_FILESYSTEM_TYPE", $details);
    }
}
