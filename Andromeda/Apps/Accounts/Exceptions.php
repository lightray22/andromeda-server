<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that an account already exists under this username/email */
class AccountExistsException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that a group already exists with this name */
class GroupExistsException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("GROUP_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that this contact already exists */
class ContactExistsException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CONTACT_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that creating accounts is not allowed */
class AccountCreateDeniedException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_CREATE_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that the requested username is not on the register allow list */
class AccountRegisterAllowException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("USERNAME_NOT_ALLOWLISTED", $details);
    }
}

/** Exception indicating that deleting accounts is not allowed */
class AccountDeleteDeniedException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_DELETE_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that account/group search is not allowed */
class SearchDeniedException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("SEARCH_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that server-side crypto is not allowed */
class CryptoNotAllowedException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("CRYPTO_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that this group membership already exists */
class DuplicateGroupMembershipException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("GROUP_MEMBERSHIP_EXISTS", $details);
    }
}

/** Exception indicating that the group is a default and has implicit memberships */
class ImplicitGroupMembershipException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("DEFAULT_GROUP_IMPLICIT_MEMBERSHIP", $details);
    }
}

/** Exception indicating that the password for an account using external authentication cannot be changed */
class ChangeExternalPasswordException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CANNOT_CHANGE_EXTERNAL_PASSWORD", $details);
    }
}

/** Exception indicating that a recovery key cannot be generated */
class RecoveryKeyCreateException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CANNOT_GENERATE_RECOVERY_KEY", $details);
    }
}

/** Exception indicating that the old password must be provided */
class OldPasswordRequiredException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("OLD_PASSWORD_REQUIRED", $details);
    }
}

/** Exception indicating that a new password must be provided */
class NewPasswordRequiredException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("NEW_PASSWORD_REQUIRED", $details);
    }
}

/** Exception indicating that an email address must be provided */
class ContactRequiredException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("VALID_CONTACT_REQUIRED", $details);
    }
}

/** Exception indicating that the test on the authentication source failed */
class AuthSourceTestFailException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("AUTH_SOURCE_TEST_FAIL", $details);
    }
}

/** Exception indicating that an unknown authentication source was given */
class UnknownAuthSourceException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_AUTHSOURCE", $details);
    }
}

/** Exception indicating that an unknown account was given */
class UnknownAccountException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ACCOUNT", $details);
    }
}

/** Exception indicating that an unknown group was given */
class UnknownGroupException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_GROUP", $details);
    }
}

/** Exception indicating that an unknown client was given */
class UnknownClientException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_CLIENT", $details);
    }
}

/** Exception indicating that an unknown session was given */
class UnknownSessionException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_SESSION", $details);
    }
}

/** Exception indicating that an unknown twofactor was given */
class UnknownTwoFactorException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_TWOFACTOR", $details);
    }
}

/** Exception indicating that an unknown contact was given */
class UnknownContactException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_CONTACT", $details);
    }
}

/** Exception indicating that the group membership does not exist */
class UnknownGroupMembershipException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_GROUPMEMBERSHIP", $details);
    }
}

/** Exception indicating that the request is not allowed with the given authentication */
class AuthenticationFailedException extends BaseExceptions\ClientDeniedException
{
    public function __construct(string $message = "AUTHENTICATION_FAILED", ?string $details = null) {
        parent::__construct($message, $details);
    }
}

/** Exception indicating that the authenticated account is disabled */
class AccountDisabledException extends AuthenticationFailedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_DISABLED", $details);
    }
}

/** Exception indicating that the specified session is invalid */
class InvalidSessionException extends AuthenticationFailedException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_SESSION", $details);
    }
}

/** Exception indicating that admin-level access is required */
class AdminRequiredException extends AuthenticationFailedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ADMIN_REQUIRED", $details);
    }
}

/** Exception indicating that a two factor code was required but not given */
class TwoFactorRequiredException extends AuthenticationFailedException
{
    public function __construct(?string $details = null) {
        parent::__construct("TWOFACTOR_REQUIRED", $details);
    }
}

/** Exception indicating that a password for authentication was required but not given */
class PasswordRequiredException extends AuthenticationFailedException
{
    public function __construct(?string $details = null) {
        parent::__construct("PASSWORD_REQUIRED", $details);
    }
}

/** Exception indicating that the request requires providing crypto details */
class CryptoKeyRequiredException extends AuthenticationFailedException
{
    public function __construct(?string $details = null) {
        parent::__construct("CRYPTO_KEY_REQUIRED", $details);
    }
}

/** Exception indicating that the account does not have crypto initialized */
class CryptoInitRequiredException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CRYPTO_INIT_REQUIRED", $details);
    }
}

/** Exception indicating that the action requires an account to act as */
class AccountRequiredException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_REQUIRED", $details);
    }
}

/** Exception indicating that the action requires a session to use */
class SessionRequiredException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("SESSION_REQUIRED", $details);
    }
}

/** Exception indicating that generating the password hash failed */
class PasswordHashFailedException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("PASSWORD_HASH_FAILED", $details);
    }
}
