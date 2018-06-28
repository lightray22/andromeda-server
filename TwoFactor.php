<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\{StandardObject, ClientObject};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

if (!file_exists(ROOT."/apps/accounts/libraries/GoogleAuthenticator/PHPGangsta/GoogleAuthenticator.php")) 
    die("Missing library: GoogleAuthenticator - git submodule init/update?");
require_once(ROOT."/apps/accounts/libraries/GoogleAuthenticator/PHPGangsta/GoogleAuthenticator.php"); use PHPGangsta_GoogleAuthenticator;

class UsedToken extends StandardObject
{
    public function GetCode() : string          { return $this->GetScalar('code'); }
    public function GetTwoFactor() : TwoFactor  { return $this->GetObject('twofactor'); }
    
    public static function Create(ObjectDatabase $database, TwoFactor $twofactor, string $code) : UsedToken 
    {
        return parent::BaseCreate($database)
            ->SetScalar('code',$code)
            ->SetObject('twofactor',$twofactor);            
    }
}

class TwoFactor extends StandardObject implements ClientObject
{
    const SECRET_LENGTH = 32; const TIME_TOLERANCE = 2;
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    public function GetComment() : ?string { return $this->TryGetScalar("comment"); }
    
    private function GetUsedTokens() : array { return $this->GetObjectRefs('usedtokens'); }
    
    public function useCrypto() : bool { return $this->GetAccount()->useCrypto(); }
    public function hasCrypto() : bool { return $this->TryGetScalar('nonce') !== null; }
    
    public function GetIsValid() : bool     { return $this->GetScalar('valid'); }
    public function SetIsValid(bool $data = true) : self { return $this->SetScalar('valid',$data); }
    
    public static function Create(ObjectDatabase $database, Account $account, string $comment = null) : TwoFactor
    {
        $ga = new PHPGangsta_GoogleAuthenticator();
        
        $secret = $ga->createSecret(self::SECRET_LENGTH); $nonce = null;
        
        if ($account->useCrypto())
        {
            $nonce = CryptoSecret::GenerateNonce();
            $secret = $account->EncryptSecret($secret, $nonce);
        }
        
        return parent::BaseCreate($database) 
            ->SetScalar('secret',$secret)
            ->SetScalar('nonce',$nonce)
            ->SetScalar('comment',$comment)
            ->SetObject('account',$account);
    }
    
    public function GetURL() : string
    {
        $ga = new PHPGangsta_GoogleAuthenticator();
        
        return $ga->getQRCodeGoogleUrl("Andromeda", $this->GetScalar('secret'));
    }
        
    public function CheckCode(string $code) : bool
    {
        $ga = new PHPGangsta_GoogleAuthenticator();
        
        $account = $this->GetAccount(); $secret = $this->GetScalar('secret');        

        if ($this->hasCrypto())
        {
            if (!$account->CryptoAvailable()) throw new CryptoUnlockRequiredException();
            $secret = $account->DecryptSecret($secret, $this->GetScalar('nonce'));
        }        

        foreach ($this->GetUsedTokens() as $usedtoken)
        {
            if ($usedtoken->GetDateCreated() < time()-(self::TIME_TOLERANCE*2*30)) $usedtoken->Delete();            
            else if ($usedtoken->GetCode() === $code) return false;
        }

        if (!$ga->verifyCode($secret, $code, self::TIME_TOLERANCE)) return false;
        
        if ($this->useCrypto() && !$this->hasCrypto()) $this->EnableCrypto();
        else if (!$this->useCrypto() && $this->hasCrypto()) $this->DisableCrypto();
        
        UsedToken::Create($this->database, $this, $code);

        $this->SetIsValid();
        
        return true;
    }
    
    public function EnableCrypto() : void
    {
        if ($this->hasCrypto()) return;
        $account = $this->GetAccount(); if (!$account->CryptoAvailable()) return;
        $nonce = CryptoSecret::GenerateNonce(); $this->SetScalar('nonce', $nonce);
        $this->SetScalar('secret', $account->EncryptSecret($this->GetScalar('secret'), $nonce));
    }
    
    public function DisableCrypto() : void
    {
        if (!$this->hasCrypto()) return;
        $account = $this->GetAccount(); if (!$account->CryptoAvailable()) throw new CryptoUnlockRequiredException();
        $secret = $account->DecryptSecret($this->GetScalar('secret'), $this->GetScalar('nonce'));
        $this->SetScalar('secret', $secret); $this->SetScalar('nonce', null);
    }
    
    const OBJECT_METADATA = 0; const OBJECT_WITHSECRET = 1;
    
    public function GetClientObject(int $level = self::OBJECT_METADATA) : array
    {
        $data = array(
            'id' => $this->ID(),
            'comment' => $this->GetComment(),
            'dates' => $this->GetAllDates(),
        );
        
        if ($level === self::OBJECT_WITHSECRET) $data['qrcodeurl'] = $this->GetURL();

        return $data;
    }
    
    public function Delete() : void
    {
        foreach ($this->GetUsedTokens() as $token) $token->Delete();
        
        parent::Delete();
    }
}
