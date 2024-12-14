<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Config;
use Andromeda\Core\Errors\BaseExceptions;
use Andromeda\Core\IOFormat\Interfaces\CLI;

/** Exception indicating that the command line usage is incorrect */
class IncorrectCLIUsageException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null)
    {
        $usage = implode(PHP_EOL,array(
            "general usage: php index.php [global flags+] app action [action params+]",
            null,
            "global flags: [--dryrun] [--dbconf fspath] [--outprop a.b.c] ".
            "[--outmode ".implode('|',array_keys(CLI::OUTPUT_TYPES))."] ".
            "[--debug ".implode('|',array_keys(Config::DEBUG_TYPES))."] ".
            "[--metrics ".implode('|',array_keys(Config::METRICS_TYPES))."]",
            null,
            "action params: [--\$param value] [--\$param@ file] [--\$param!] [--\$param% file [name]] [--\$param- [name]]",
            "\t param@ puts the content of the file in the parameter",
            "\t param! will prompt interactively or read stdin for the parameter value",
            "\t param% gives the file path as a direct file input (optionally with a new name)",
            "\t param- will attach the stdin stream as a direct file input (default name is 'data')",
            null,
            "get version:   php index.php version",
            "get actions:   php index.php core usage"
        ));
        
        if ($details !== null)
            $usage .= PHP_EOL.PHP_EOL."usage failure details: $details";
        
        parent::__construct($usage);
    }
}

/** Exception indicating that the given file is not valid */
class InvalidFileException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INACCESSIBLE_FILE", $details);
    }
}

/** Exception indicating that the app or action parameters are missing */
class MissingAppActionException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_OR_ACTION_MISSING", $details);
    }
}

/** Exception indicating that the parameter cannot be part of $_GET */
class IllegalGetFieldException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ILLEGAL_GET_FIELD", $details);
    }
}

/** Exception indicating the input file request format is wrong */
class FileUploadFormatException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("BAD_FILE_INPUT_FORMAT", $details);
    }
}

/** Exception indicating the field contains invalid base64 */
class Base64DecodeException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("BASE64_DECODE_FAIL", $details);
    }
}

/** Exception indicating the HTTP method used is not allowed */
class MethodNotAllowedException extends BaseExceptions\ClientException
{
    public function __construct(?string $details = null) {
        parent::__construct("METHOD_NOT_ALLOWED", 405, $details);
    }
}

/** Exception indicating that the remote response is invalid */
class RemoteInvalidException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_REMOTE_RESPONSE", $details);
    }
}

/** Exception indicating there was an error with the uploaded file */
class FileUploadFailException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_UPLOAD_ERROR", $details);
    }
}
