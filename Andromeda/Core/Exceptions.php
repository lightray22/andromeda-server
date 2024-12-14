<?php declare(strict_types=1); namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that a requried extension is missing */
class MissingExtensionException extends BaseExceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("MISSING_PHP_EXTENSION", $details);
    }
}

/** An exception indicating that the requested action is invalid for this app */
class UnknownActionException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ACTION", $details);
    }
}

/** Exception indicating that the configured data directory is not valid */
class UnwriteableDatadirException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATADIR_NOT_WRITEABLE", $details);
    }
}

/** Exception indicating that an app dependency was not met */
class AppDependencyException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_DEPENDENCY_FAILURE", $details);
    }
}

/** Exception indicating that a mailer was requested but it is disabled */
class EmailDisabledException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("EMAIL_DISABLED", $details);
    }
}

/** Exception indicating that a mailer was requested but none are configured */
class MailerUnavailableException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("MAILER_UNAVAILABLE", $details);
    }
}

/** Exception indicating that the requested app is invalid */
class UnknownAppException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_APP", $details);
    }
}

/** Exception indicating that the server is configured as disabled */
class MaintenanceException extends BaseExceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("SERVER_MAINTENANCE", $details);
    }
}

/** An exception indicating that the app is not installed and needs to be */
class InstallRequiredException extends BaseExceptions\ServiceUnavailableException
{
    public function __construct(string $appname) {
        parent::__construct("INSTALL_REQUIRED", $appname);
    }
}

/** Exception indicating that the app database upgrade scripts must be run */
class UpgradeRequiredException extends BaseExceptions\ServiceUnavailableException
{
    private string $oldVersion;
    public function getOldVersion() : string { return $this->oldVersion; }
    public function __construct(string $appname, string $oldVersion) 
    {
        $this->oldVersion = $oldVersion;
        parent::__construct("UPGRADE_REQUIRED", $appname);
    }
}

/** An exception indicating that the app is already installed */
class InstalledAlreadyException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INSTALLED_ALREADY", $details);
    }
}

/** Exception indicating that the app is already upgraded to current */
class UpgradedAlreadyException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("UPGRADED_ALREADY", $details);
    }
}

/** Exception indicating that decryption failed */
class DecryptionFailedException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("DECRYPTION_FAILED", $details);
    }
}

/** Exception indicating that sending mail failed */
abstract class MailSendException extends BaseExceptions\ServerException
{
    public function __construct(string $message = "MAIL_SEND_FAILURE", ?string $details = null) {
        parent::__construct($message, $details);
    }
}

/** Exception indicating the Emailer is SMTP but has no hosts */
class MissingHostsException extends MailSendException
{
    public function __construct(?string $details = null) {
        parent::__construct("MAILER_MISSING_HOSTS", $details);
    }
}

/** Exception indicating PHPMailer sending returned false */
class PHPMailerFalseException extends MailSendException
{
    public function __construct(string $details) {
        parent::__construct("MAIL_SEND_FAILURE", $details);
    }
}

/** Exception thrown by the PHPMailer library when sending */
class PHPMailThrowException extends MailSendException
{
    public function __construct(\PHPMailer\PHPMailer\Exception $e) {
        parent::__construct(); $this->AppendException($e);
    }
}

/** Exception indicating that no recipients were given */
class EmptyRecipientsException extends MailSendException
{
    public function __construct(?string $details = null) {
        parent::__construct("NO_RECIPIENTS_GIVEN", $details);
    }
}

/** Exception indicating that the configured mailer driver is invalid */
class InvalidMailTypeException extends MailSendException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_MAILER_TYPE", $details);
    }
}

/** Exception indicating that the server failed to load a configured app */
class FailedAppLoadException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FAILED_LOAD_APP", $details);
    }
}

/** Exception indicating that scanning apps failed */
class FailedScanAppsException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FAILED_SCAN_APPS", $details);
    }
}

/** Exception indicating that the given version string is invalid */
class InvalidVersionException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("VERSION_STRING_INVALID", $details);
    }
}

/** Exception indicating the given context is missing metrics */
class MissingMetricsException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("MISSING_METRICS", $details);
    }
}

/** Converts a JSON failure into an exception */
class JSONException extends BaseExceptions\ServerException
{
    public function __construct()
    {
        parent::__construct("JSON_FAIL",
            json_last_error_msg(), json_last_error());
    }
}

/** Exception indicating the output buffer failed */
class OutputBufferException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("OUTPUT_BUFFER_FAIL", $details);
    }
}
