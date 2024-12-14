<?php declare(strict_types=1); namespace Andromeda\Apps\Core\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Exceptions as CoreExceptions;
use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_MAILER", $details);
    }
}

/** Client error indicating that the mailer config failed */
class MailSendFailException extends BaseExceptions\ClientErrorException
{
    public function __construct(CoreExceptions\MailSendException $e) {
        parent::__construct(""); $this->CopyException($e);
    }
}

/** Exception indicating that admin-level access (or local CLI if no accounts app) is required */
class AdminRequiredException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ADMIN_REQUIRED", $details);
    }
}

/** Exception indicating an invalid app name was given */
class InvalidAppException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_APPNAME", $details);
    }
}
