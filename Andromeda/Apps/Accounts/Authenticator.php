<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Exceptions\DecryptionFailedException;
use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\IOFormat\{Input, IOInterface, SafeParams};

use Andromeda\Apps\Accounts\Resource\{Client, Session, RecoveryKey};

/**
 * The class used to authenticate requests
 *
 * This is the API class that should be used in other apps.
 */
class Authenticator
{
    private SafeParams $params;
    
    private ?Account $account = null;
    
    /** @return list<self> */
    private static function &GetInstances(ObjectDatabase $database) : array
    {
        return $database->GetCustomCache(self::class); // @phpstan-ignore-line cast generic to desired
    }

    /** Returns the authenticated user account (or null) */
    public function TryGetAccount() : ?Account { return $this->account; }
    
    /** Returns the authenticated user account (not null) */
    public function GetAccount() : Account
    {
        if ($this->account === null)
            throw new Exceptions\AccountRequiredException();
        return $this->account;
    }

    private ?Account $realaccount = null;
    
    /** Returns the actual account used for the request, not the masqueraded one (or null) */
    public function TryGetRealAccount() : ?Account { return $this->realaccount; }
    
    /** Returns the actual account used for the request, not the masqueraded one (not null) */
    public function GetRealAccount() : Account
    {
        if ($this->realaccount === null)
            throw new Exceptions\AccountRequiredException();
        return $this->realaccount;
    }
    
    /** Returns true if the user is masquering as another user */
    public function isSudoUser() : bool { return $this->account !== $this->realaccount; }
    
    private ?Session $session = null;
    
    /** Returns the session used for the request (or null) */
    public function TryGetSession() : ?Session { return $this->session; }
    
    /** Returns the session used for the request (not null) */
    public function GetSession() : Session
    {
        if ($this->session === null)
            throw new Exceptions\SessionRequiredException();
        return $this->session;
    }
    
    private ?Client $client = null;
    
    /** Returns the client used for the request or null */
    public function TryGetClient() : ?Client { return $this->client; }
    
    /** Returns the client used for the request (not null) */
    public function GetClient() : Client
    {
        if ($this->client === null) 
            throw new Exceptions\SessionRequiredException();
        return $this->client;
    }
    
    /** Returns true if the account used for the request is an admin */
    public function isAdmin() : bool { return $this->account === null || $this->account->isAdmin(); }
    
    /** Returns true if the real account used for the request is an admin */
    public function isRealAdmin() : bool { return $this->realaccount === null || $this->realaccount->isAdmin(); }
    
    private function __construct(SafeParams $params) { $this->params = $params; }
    
    /**
     * The primary authentication routine
     *
     * Loads the specified session, checks validity, updates dates.
     * Note that only a session must be provided, not the client that owns it.
     * @param ObjectDatabase $database database reference
     * @param Input $input the input containing auth details
     * @param IOInterface $interface the interface used for the request
     * @throws Exceptions\InvalidSessionException if the given session details are invalid
     * @throws Exceptions\AccountDisabledException if the given account is disabled
     * @throws Exceptions\UnknownAccountException if the given sudo account is not valid
     * @returns new authenticator object or null if not authenticated
     */
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, IOInterface $interface) : ?self
    {
        Config::GetInstance($database); // assert installed
        
        $params = $input->GetParams();
        
        $sessionid = $params->HasParam('auth_sessionid') ? $params->GetParam('auth_sessionid',SafeParams::PARAMLOG_NEVER)->GetRandstr() : null;
        $sessionkey = $params->HasParam('auth_sessionkey') ? $params->GetParam('auth_sessionkey',SafeParams::PARAMLOG_NEVER)->GetRandstr() : null;
        
        if (($auth = $input->GetAuth()) !== null)
        {
            $sessionid ??= $auth->GetUsername();
            $sessionkey ??= $auth->GetPassword();
        }
        
        $sudouser = $params->HasParam('auth_sudouser') ? AccountsApp::getUsername($params->GetParam('auth_sudouser',SafeParams::PARAMLOG_ALWAYS)) : null;
        $sudoacct = $params->HasParam('auth_sudoacct') ? $params->GetParam('auth_sudoacct',SafeParams::PARAMLOG_ALWAYS)->GetRandstr() : null;
        
        $auth = new Authenticator($params);
        
        if ($sessionid !== null && $sessionkey !== null)
        {
            $auth->session = Session::TryLoadByID($database, $sessionid);
            
            if ($auth->session === null || !$auth->session->CheckKeyMatch($sessionkey)) 
                throw new Exceptions\InvalidSessionException();
            
            $auth->account = $auth->session->GetAccount();
            
            if ($auth->account->isDisabled() !== 0) 
                throw new Exceptions\AccountDisabledException();
            
            if (($sudouser !== null || $sudoacct !== null) && !$auth->account->isAdmin())
                throw new Exceptions\AdminRequiredException();
        }
        else if (!$interface->isPrivileged())
            return null; // not authenticated

        // on a privileged interface, account can be null.  Might be non-null if using a session or sudouser/sudoacct
        // on a non-privileged interface, account cannot be null, must use a session.  Admins only can use sudouser/sudoacct

        if ($sudouser !== null)
        {
            $auth->account = Account::TryLoadByUsername($database, $sudouser);
            if ($auth->account === null) throw new Exceptions\UnknownAccountException();           
        }
        else if ($sudoacct !== null)
        {
            $auth->account = Account::TryLoadByID($database, $sudoacct);
            if ($auth->account === null) throw new Exceptions\UnknownAccountException();
        }
        
        self::GetInstances($database)[] = $auth;
        return $auth;
    }

    /**
     * Requires that the user is an administrator
     * @throws Exceptions\AdminRequiredException if not an admin
     */
    public function RequireAdmin() : self
    {
        if (!$this->isAdmin()) throw new Exceptions\AdminRequiredException(); return $this;
    }
    
    /**
     * Requires that the user posts a twofactor code, if the account uses twofactor
     * @throws Exceptions\TwoFactorRequiredException if twofactor was not given
     * @throws Exceptions\AuthenticationFailedException if the given twofactor was invalid
     */
    public function TryRequireTwoFactor() : self
    {
        if ($this->realaccount === null) return $this;
        
        static::StaticTryRequireTwoFactor($this->params, $this->realaccount, $this->session); return $this;
    }
    
    /** @see Authenticator::TryRequireTwoFactor() */
    public static function StaticTryRequireTwoFactor(SafeParams $params, Account $account, ?Session $session = null) : void
    {
        if (!$account->HasValidTwoFactor()) return; 
        
        static::StaticTryRequireCrypto($params, $account, $session);
        
        if (!$params->HasParam('auth_twofactor')) 
            throw new Exceptions\TwoFactorRequiredException();
        
        $twofactor = $params->GetParam('auth_twofactor',SafeParams::PARAMLOG_NEVER)->GetAlphanum(); // not an int (leading zeroes)
        
        if (!$account->CheckTwoFactor($twofactor)) 
            throw new Exceptions\AuthenticationFailedException();
    }
    
    /**
     * Requires that the user provides their password
     * @throws Exceptions\PasswordRequiredException if the password is not given
     * @throws Exceptions\AuthenticationFailedException if the password is invalid
     */
    public function RequirePassword() : self
    {
        if ($this->realaccount === null) return $this;

        if (!$this->params->HasParam('auth_password')) 
            throw new Exceptions\PasswordRequiredException();
        
        $password = $this->params->GetParam('auth_password',SafeParams::PARAMLOG_NEVER)->GetRawString();

        if (!$this->realaccount->VerifyPassword($password))
                throw new Exceptions\AuthenticationFailedException();

        return $this;
    }
    
    /**
     * Same as RequireCrypto() but does nothing if the account does not have crypto
     * @see Authenticator::RequireCrypto()
     */
    public function TryRequireCrypto() : self
    {
        if ($this->account === null) return $this;
        
        return (!$this->account->hasCrypto()) ? $this : $this->RequireCrypto();
    }
    
    /**
     * Same as StaticRequireCrypto() but does nothing if the account does not have crypto
     * @see Authenticator::StaticRequireCrypto()
     */
    public static function StaticTryRequireCrypto(SafeParams $params, Account $account, ?Session $session = null) : void
    {
        if ($account->hasCrypto()) static::StaticRequireCrypto($params, $account, $session);
    }
    
    /**
     * Requires that the account's crypto is unlocked for the request (and exists)
     *
     * Account crypto can be unlocked via a session, a recovery key, or a password
     * @throws Exceptions\AuthenticationFailedException if the given keysource is not valid
     * @throws Exceptions\CryptoKeyRequiredException if no key source was given
     */
    public function RequireCrypto() : self
    {
        if ($this->account === null) throw new Exceptions\AccountRequiredException();
        
        static::StaticRequireCrypto($this->params, $this->account, $this->session); return $this;    
    }
    
    /** @see Authenticator::RequireCrypto() */
    public static function StaticRequireCrypto(SafeParams $params, Account $account, ?Session $session = null) : void
    {
        if ($account->isCryptoAvailable()) return; // already available
        
        if (!$account->hasCrypto())
            throw new Exceptions\CryptoInitRequiredException();
        
        if ($session !== null && $session->isCryptoAvailable())
        {
            $account->SetCryptoKeySource($session);
        }
        else if ($params->HasParam('auth_recoverykey'))
        {
            $recoverykey = $params->GetParam('auth_recoverykey',SafeParams::PARAMLOG_NEVER)->GetUTF8String();

            $keyobj = RecoveryKey::TryLoadByFullKey($account->GetDatabase(), $recoverykey, $account);
            if ($keyobj === null) throw new Exceptions\AuthenticationFailedException();
            
            if (!$keyobj->CheckFullKey($recoverykey)) // unlocks crypto
                throw new Exceptions\AuthenticationFailedException();
    
            $account->SetCryptoKeySource($keyobj);
        }
        else if ($params->HasParam('auth_password'))
        {
            $password = $params->GetParam('auth_password',SafeParams::PARAMLOG_NEVER)->GetRawString();
            
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e) { 
                throw new Exceptions\AuthenticationFailedException(); }
        }
        
        if (!$account->isCryptoAvailable())
            throw new Exceptions\CryptoKeyRequiredException();
    }
  
    /** 
     * Runs TryRequireCrypto() on all instantiated authenticators 
     * for $account and throws if not unlocked 
     */
    public static function RequireCryptoFor(Account $account) : void
    {
        foreach (self::GetInstances($account->GetDatabase()) as $auth) 
        {
            if ($auth->TryGetAccount() === $account)
                $auth->TryRequireCrypto();
        }
        
        if (!$account->isCryptoAvailable())
            throw new Exceptions\CryptoKeyRequiredException();
    }
}
