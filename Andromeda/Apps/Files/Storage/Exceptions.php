<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Client exception indicating that a write was attempted to a read-only storage */
class ReadOnlyException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("READ_ONLY_FILESYSTEM", $details);
    }
}

/** Exception indicating that this storage does not support folder functions */
class FoldersUnsupportedException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("STORAGE_FOLDERS_UNSUPPORTED", $details);
    }
}

/** Exception indicating that a random write was requested (FTP does not support it) */
class FTPAppendOnlyException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_WRITE_APPEND_ONLY", $details);
    }
}

/** Exception indicating that FTP does not support file copy */
class FTPCopyFileException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_NO_COPY_SUPPORT", $details);
    }
}

/** Exception indicating that objects cannot be modified */
class S3ModifyException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("S3_OBJECTS_IMMUTABLE", $details);
    }
}

/** Base exception indicating a storage failure */
abstract class StorageException extends BaseExceptions\ServerException { }

/** Exception indicating that reading folder contents failed */
class FolderReadFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_READ_FAILED", $details);
    }
}

/** Exception indicating that creating the folder failed */
class FolderCreateFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_CREATE_FAILED", $details);
    }
}

/** Exception indicating that deleting the folder failed */
class FolderDeleteFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_DELETE_FAILED", $details);
    }
}

/** Exception indicating that moving the folder failed */
class FolderMoveFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_MOVE_FAILED", $details);
    }
}

/** Exception indicating that renaming the folder failed */
class FolderRenameFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_RENAME_FAILED", $details);
    }
}

/** Exception indicating that copying the folder failed */
class FolderCopyFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_COPY_FAILED", $details);
    }
}

/** Exception indicating that creating the file failed */
class FileCreateFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_CREATE_FAILED", $details);
    }
}

/** Exception indicating that deleting the file failed */
class FileDeleteFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_DELETE_FAILED", $details);
    }
}

/** Exception indicating that moving the file failed */
class FileMoveFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_MOVE_FAILED", $details);
    }
}

/** Exception indicating that renaming the file failed */
class FileRenameFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_RENAME_FAILED", $details);
    }
}

/** Exception indicating that reading from the file failed */
class FileReadFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_READ_FAILED", $details);
    }
}

/** Exception indicating that writing to the file failed */
class FileWriteFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_WRITE_FAILED", $details);
    }
}

/** Exception indicating that copying the file failed */
class FileCopyFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_COPY_FAILED", $details);
    }
}

/** Exception indicating that stat failed */
class ItemStatFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_STAT_FAILED", $details);
    }
}

/** Exception indicating that finding free space failed */
class FreeSpaceFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FREE_SPACE_FAILED", $details);
    }
}

/** Exception indicating that the file handle failed to open */
class FileOpenFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_OPEN_FAILED", $details);
    }
}

/** Exception indicating that the file handle failed to seek */
class FileSeekFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_SEEK_FAILED", $details);
    }
}

/** Exception indicating that the file handle failed to close */
class FileCloseFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_CLOSE_FAILED", $details);
    }
}

/** Exception that wraps S3 SDK exceptions */
class S3ErrorException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("S3_SDK_EXCEPTION", $details);
    }
}

/** Exception indicating that activating the storage failed */
abstract class ActivateException extends StorageException { }

/** Exception indicating that the tested storage is not readable */
class TestReadFailedException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("STORAGE_TEST_READ_FAILED", $details);
    }
}

/** Exception indicating that the tested storage is not writeable */
class TestWriteFailedException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("STORAGE_TEST_WRITE_FAILED", $details);
    }
}

/** Exception indicating only admins can create local storage */
class LocalNonAdminException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("LOCAL_STORAGE_ADMIN_ONLY", $details);
    }
}

/** Exception indicating that the FTP extension is not installed */
class FTPExtensionException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating that the FTP server connection failed */
class FTPConnectionFailure extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_CONNECTION_FAILURE", $details);
    }
}

/** Exception indicating that authentication on the FTP server failed */
class FTPAuthenticationFailure extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_AUTHENTICATION_FAILURE", $details);
    }
}

/** Exception indicating that the S3 SDK is missing */
class S3AwsSdkException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("S3_AWS_SDK_MISSING", $details);
    }
}

/** Exception indicating that S3 failed to connect or read the base path */
class S3ConnectException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("S3_CONNECT_FAILED", $details);
    }
}

/** Exception indicating that the SSH connection failed */
class SSHConnectionFailure extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("SSH_CONNECTION_FAILURE", $details);
    }
}

/** Exception indicating that SSH authentication failed */
class SSHAuthenticationFailure extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("SSH_AUTHENTICATION_FAILURE", $details);
    }
}

/** Exception indicating that the server's public key has changed */
class HostKeyMismatchException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("SSH_HOST_KEY_MISMATCH", $details);
    }
}

/** Exception indicating that the libsmbclient extension is missing */
class SMBExtensionException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("SMB_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating that the SMB state initialization failed */
class SMBStateInitException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("SMB_STATE_INIT_FAILED", $details);
    }
}

/** Exception indicating that SMB failed to connect or read the base path */
class SMBConnectException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("SMB_CONNECT_FAILED", $details);
    }
}
