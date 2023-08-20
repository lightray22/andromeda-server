<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that the created auth source is invalid */
class InvalidAuthSourceException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("AUTHSOURCE_FAILED", $details);
    }
}

/** Exception indicating the PHP FTP extension is missing */
class FTPExtensionException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating the FTP connection failed to connect */
class FTPConnectionFailure extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_CONNECTION_FAILURE", $details);
    }
}

/** Exception indicating the IMAP extension does not exist */
class IMAPExtensionException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("IMAP_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating IMAP encountered an error */
class IMAPErrorException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("IMAP_EXTENSION_ERROR", $details);
    }
}

/** Exception indicating that the LDAP extension does not exist */
class LDAPExtensionException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LDAP_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating that the LDAP connection failed */
class LDAPConnectionFailure extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LDAP_CONNECTION_FAILURE", $details);
    }
}

/** Exception indicating that LDAP encountered an error */
class LDAPErrorException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LDAP_EXTENSION_ERROR", $details);
    }
}