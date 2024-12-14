<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that crypto cannot be unlocked because it does not exist */
class CryptoNotInitializedException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("CRYPTO_NOT_INITIALIZED", $details);
    }
}

/** Exception indicating that crypto cannot be initialized because it already exists */
class CryptoAlreadyInitializedException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("CRYPTO_ALREADY_INITIALIZED", $details);
    }
}

/** Exception indicating that crypto must be unlocked by the client */
class CryptoUnlockRequiredException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("CRYPTO_UNLOCK_REQUIRED", $details);
    }
}

/** Exception indicating that the raw (non-hashed) key does not exist in memory */
class RawKeyNotAvailableException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("AUTHOBJECT_KEY_NOT_AVAILABLE", $details);
    }
}
