<?php declare(strict_types=1); namespace Andromeda\Core\Logging\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that it was requested to modify the log after it was written to disk */
class LogAfterWriteException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("REQLOG_AFTER_WRITE", $details);
    }
}

/** Exception indicating the log file cannot be written more than once */
class MultiFileWriteException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LOG_FILE_MULTI_WRITE", $details);
    }
}

/** SaveMetrics requires the database to not already be undergoing a transaction */
class MetricsTransactionException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("METRICS_IN_TRANSACTION", $details);
    }
}
