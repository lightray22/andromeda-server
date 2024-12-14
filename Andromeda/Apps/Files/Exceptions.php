<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that the requested item does not exist */
class UnknownItemException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ITEM", $details);
    }
}

/** Exception indicating that the requested file does not exist */
class UnknownFileException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_FILE", $details);
    }
}

/** Exception indicating that the requested folder does not exist */
class UnknownFolderException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_FOLDER", $details);
    }
}

/** Exception indicating that the requested object does not exist */
class UnknownObjectException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_OBJECT", $details);
    }
}

/** Exception indicating that the requested filesystem does not exist */
class UnknownFilesystemException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_FILESYSTEM", $details);
    }
}

/** Exception indicating that the requested download byte range is invalid */
class InvalidDLRangeException extends BaseExceptions\ClientException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_BYTE_RANGE", 416, $details);
    }
}

/** Exception indicating that access to the requested item is denied */
class ItemAccessDeniedException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_ACCESS_DENIED", $details);
    }
}

/** Exception indicating that user-added filesystems are not allowed */
class UserStorageDisabledException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("USER_STORAGE_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that random write access is not allowed */
class RandomWriteDisabledException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("RANDOM_WRITE_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that item sharing is not allowed */
class ItemSharingDisabledException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("SHARING_DISABLED", $details);
    }
}

/** Exception indicating that emailing share links is not allowed */
class EmailShareDisabledException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("EMAIL_SHARES_DISABLED", $details);
    }
}

/** Exception indicating that the absolute URL of a share cannot be determined */
class ShareURLGenerateException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CANNOT_OBTAIN_SHARE_URL", $details);
    }
}

/** Exception indicating invalid share target params were given */
class InvalidShareTargetException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_SHARE_TARGET_PARAMS", $details);
    }
}

/** Exception indicating that sharing to the given target is not allowed */
class ShareTargetDisabledException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("SHARE_TARGET_DISABLED", $details);
    }
}

/** Exception indicating that the folder destination is invalid */
class InvalidDestinationException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_FOLDER_DESTINATION", $details);
    }
}

/** Exception indicating that files cannot be moved across filessytems */
class CrossFilesystemException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILESYSTEM_MISMATCH", $details);
    }
}

/** Exception indicating that the item target name already exists */
class DuplicateItemException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that the item was deleted when refreshed from storage */
class DeletedByStorageException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_DELETED_BY_STORAGE", $details);
    }
}

/** Exception indicating that the given share password is invalid */
class InvalidSharePasswordException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_SHARE_PASSWORD", $details);
    }
}

/** Exception indicating that the operation is not valid on a root folder */
class InvalidRootOpException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ROOT_FOLDER_OP_INVALID", $details);
    }
}

/** Exception indicating that the requested share has expired */
class ShareExpiredException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("SHARE_EXPIRED", $details);
    }
}

/** Exception indicating that the requested share already exists */
class ShareExistsException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("SHARE_EXISTS", $details);
    }
}

/** Exception indicating that a share was requested for a public item */
class SharePublicItemException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CANNOT_SHARE_PUBLIC_ITEM", $details);
    }
}

/** Exception indicating that only one file/folder access can logged */
class ItemLogFullException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_LOG_SLOT_FULL", $details);
    }
}

/** Exception indicating that an unknown item type was given */
class BadItemTypeException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ITEM_TYPE", $details);
    }
}
