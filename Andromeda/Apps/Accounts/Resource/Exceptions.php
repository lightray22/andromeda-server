<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that a valid contact value was not given */
class ContactNotGivenException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CONTACT_NOT_GIVEN", $details);
    }
}
