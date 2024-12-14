<?php declare(strict_types=1); namespace Andromeda\Core\Errors\BaseExceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\IOFormat\Output;

/** The base class for Andromeda exceptions */
abstract class BaseException extends \Exception 
{   
    /**
     * Sets this exception's message (not code) from another andromeda exception
     * @param self $e exception to copy
     * @param bool $newloc if true, also copy the exception file/line
     * @return $this
     */
    protected function CopyException(self $e, bool $newloc = true) : self
    {
        $this->message = $e->getMessage();
        
        if ($newloc)
        {
            $this->file = $e->getFile();
            $this->line = $e->getLine();
        }
        
        return $this;
    }
    
    /**
     * Appends this exception's message (not code) from another exception
     * @param \Exception $e exception to copy
     * @param bool $newloc if true, also copy the exception file/line
     * @return $this
     */
    protected function AppendException(\Exception $e, bool $newloc = true) : self
    {
        $this->message .= ': '.$e->getMessage();
        
        if ($newloc)
        {
            $this->file = $e->getFile();
            $this->line = $e->getLine();
        }
        
        return $this;
    }
}

/** 
 * Base class for errors caused by the client's request
 * The code is shown to clients and should be an HTTP-equivalent.
 * Exceptions that are technically server-side issues but are "expected"
 * or otherwise not loggable-errors should use ClientExceptions.
 */
class ClientException extends BaseException 
{ 
    public function __construct(string $message, int $code, ?string $details = null)
    {
        if ($details !== null && $details !== "") 
            $message .= ": $details";
        
        parent::__construct($message, $code);
    }
}

/** Base class for generally invalid client requests (HTTP 400) */
class ClientErrorException extends ClientException
{
    public function __construct(string $message = "INVALID_REQUEST", ?string $details = null)
    {
        parent::__construct($message, Output::CODE_CLIENT_ERROR, $details);
    }
}

/** Base class for client requests that are denied (HTTP 403) */
class ClientDeniedException extends ClientException
{
    public function __construct(string $message = "ACCESS_DENIED", ?string $details = null)
    {
        parent::__construct($message, Output::CODE_CLIENT_DENIED, $details);
    }
}

/** Base class for client requests referencing something not found (HTTP 404) */
class ClientNotFoundException extends ClientException
{
    public function __construct(string $message = "NOT_FOUND", ?string $details = null)
    {
        parent::__construct($message, Output::CODE_CLIENT_NOTFOUND, $details);
    }
}

/** 
 * Exception indicating something is not implemented (HTTP 501)
 * These are not unexpected, so use ClientException 
 */
class NotImplementedException extends ClientException
{
    public function __construct(string $message = "NOT_IMPLEMENTED", ?string $details = null)
    {
        parent::__construct($message, Output::CODE_SERVER_UNIMPLEMENTED, $details);
    }
}

/** 
 * Exception indicating that the server is temporarily unable to respond to this request (HTTP 503)
 * These are not "unexpected", so use ClientException
 */
class ServiceUnavailableException extends ClientException
{
    public function __construct(string $message = "SERVICE_UNAVAILABLE", ?string $details = null)
    {
        parent::__construct($message, Output::CODE_SERVER_UNAVAILABLE, $details);
    }
}

/**
 * Base class for errors caused by server-side programming issues
 * These are for code that needs to be fixed, and can be logged.
 * Clients are always shown 500/SERVER_ERROR so code is not an HTTP code.
 */
class ServerException extends BaseException
{
    public function __construct(string $message, ?string $details = null, int $code = 0)
    {
        if ($details !== null && $details !== "")
            $message .= ": $details";
        
        parent::__construct($message, $code);
    }
    
    /**
     * Sets this exception's code,message from another exception
     * @see BaseException::CopyException
     * @return $this
     */
    protected function CopyException(BaseException $e, bool $newloc = true) : self
    {
        parent::CopyException($e, $newloc);
        
        $this->code = $e->getCode();

        return $this;
    }
    
    /**
     * Appends this exception's code,message from another exception
     * @see BaseException::AppendException()
     * @return $this
     */
    protected function AppendException(\Exception $e, bool $newloc = true) : self
    {
        parent::AppendException($e, $newloc);
        
        $this->code = $e->getCode();
        
        return $this;
    }
}

/** Internal-only exception used for getting a backtrace */
class BreakpointException extends ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("BREAKPOINT_EXCEPTION", $details);
    }
}

/** Represents an non-exception error from PHP */
class PHPError extends BaseException
{
    public function __construct(int $code, string $string, string $file, int $line)
    {
        parent::__construct($string, $code);
        
        $this->file = $file;
        $this->line = $line;
    }
}
