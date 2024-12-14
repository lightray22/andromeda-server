<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Apps\Accounts\Account;

/** The basic authentication interface */
interface IAuthSource
{
    /**
     * Verify the password given
     * @param Account $account the account to check (contains the username or other info)
     * @param string $password the password to check
     * @return bool true if the password check is valid
     */
    public function VerifyAccountPassword(Account $account, string $password) : bool;
}
