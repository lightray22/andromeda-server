<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Exceptions;

/** 
 * The regular internal authentication source
 * 
 * Does not exist in the database. Stores passwords as hashes in the Account object.
 */
class Local implements IAuthSource
{
    public function VerifyAccountPassword(Account $account, string $password) : bool
    {
        $hash = $account->TryGetPasswordHash();
        if ($hash === null) return false;
        
        $correct = password_verify($password, $hash);
        
        if ($correct && password_needs_rehash($hash, PASSWORD_ARGON2ID))
            static::SetPassword($account, $password);
            
        return $correct;
    }
    
    /**
     * Hashes and sets the given password on a given account
     * @param Account $account the account to set
     * @param string $password the password to set
     * @throws Exceptions\PasswordHashFailedException if hashing fails
     */
    public static function SetPassword(Account $account, string $password) : void
    {    
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        if (!is_string($hash)) // @phpstan-ignore-line PHP7.4 only can return false
            throw new Exceptions\PasswordHashFailedException();
        
        $account->SetPasswordHash($hash);
    }
}
