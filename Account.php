<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/AuthEntity.php");
require_once(ROOT."/apps/accounts/ContactInfo.php");
require_once(ROOT."/apps/accounts/Client.php"); 
require_once(ROOT."/apps/accounts/Config.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/GroupMembership.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/MasterKeySource.php");
require_once(ROOT."/apps/accounts/RecoveryKey.php");

require_once(ROOT."/apps/accounts/auth/Local.php");

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoPublic};
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{Emailer, EmailRecipient};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\{BaseObject, ObjectDatabase};
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\ClientObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class CryptoUnavailableException extends Exceptions\ServerException     { public $message = "CRYPTO_UNAVAILABLE"; }
class CryptoUnlockRequiredException extends Exceptions\ServerException  { public $message = "CRYPTO_UNLOCK_REQUIRED"; }
class RekeyOldPasswordRequired extends Exceptions\ServerException       { public $message = "REKEY_OLD_PASSWORD_REQUIRED"; }

use Andromeda\Core\Database\KeyNotFoundException;
use Andromeda\Core\Database\NullValueException;
use Andromeda\Core\EmailUnavailableException;

class Account extends AuthEntity implements ClientObject
{
    public function GetUsername() : string  { return $this->GetScalar('username'); }
    public function GetFullName() : ?string { return $this->TryGetScalar('fullname'); }
    public function SetFullName(string $data) : self { return $this->SetScalar('fullname',$data); }
    
    public function GetGroupMemberships() : array { return $this->GetObjectRefs('groups'); }    
    public function CountGroupMembeships() : int  { return $this->TryCountObjectRefs('groups'); }
    
    public function GetGroups() : array 
    { 
        $ids = array_values(array_map(function($m){ return $m->GetGroupID(); }, $this->GetGroupMemberships()));        
        return Group::LoadManyByID($this->database, $ids);
    }
    
    public function GetAuthSource() : Auth\Source 
    { 
        $authsource = $this->TryGetObject('authsource');
        if ($authsource !== null) return $authsource;
        else return Auth\Local::Load($this->database);
    }
    
    public function GetClients() : array        { return $this->GetObjectRefs('clients'); }
    public function GetSessions() : array       { return $this->GetObjectRefs('sessions'); }
   
    public function GetContactInfos() : array   { return $this->GetObjectRefs('contactinfos'); }    
    public function CountContactInfos() : int   { return $this->TryCountObjectRefs('contactinfos'); }
    
    private function GetRecoveryKeys() : array  { return $this->GetObjectRefs('recoverykeys'); }
    public function HasRecoveryKeys() : bool    { return $this->TryCountObjectRefs('recoverykeys') > 0; }
    
    public function HasTwoFactor() : bool
    { 
        $twofactors = $this->GetTwoFactors();
        foreach ($twofactors as $twofactor) { 
            if ($twofactor->GetIsValid()) return true; }
        return false;
    }    
    
    private function GetTwoFactors() : array    { return $this->GetObjectRefs('twofactors'); }
    public function ForceTwoFactor() : bool     { return ($this->TryGetFeature('forcetwofactor') ?? false) && $this->HasTwoFactor(); }
    
    public function isAdmin() : bool                    { return $this->TryGetFeature('admin') ?? false; }
    public function isEnabled() : bool                  { return $this->TryGetFeature('enabled') ?? true; }
    public function setEnabled(?bool $val) : self       { return $this->SetFeature('enabled', $val); }
    
    public function getUnlockCode() : ?string               { return $this->TryGetScalar('unlockcode'); }
    public function setUnlockCode(?string $code) : self     { return $this->SetScalar('unlockcode', $code); }
    
    public function getActiveDate() : int       { return $this->GetDate('active'); }
    public function setActiveDate() : self      { return $this->SetDate('active'); }
    public function getLoggedonDate() : int     { return $this->GetDate('loggedon'); }
    public function setLoggedonDate() : self    { return $this->SetDate('loggedon'); }
    public function getPasswordDate() : int     { return $this->GetDate('passwordset'); }
    private function setPasswordDate() : self   { return $this->SetDate('passwordset'); }
    
    public function GetMaxClientAge() : ?int    { return $this->TryGetScalar('max_client_age'); }
    public function GetMaxSessionAge() : ?int   { return $this->TryGetScalar('max_session_age'); }
    public function GetMaxPasswordAge() : ?int  { return $this->TryGetScalar('max_password_age'); }
    
    public static function SearchByFullName(ObjectDatabase $database, string $fullname) : array
    {
        return self::LoadManyMatchingAll($database, array('fullname'=>"%$fullname%"), true);
    }
    
    public static function TryLoadByUsername(ObjectDatabase $database, string $username) : ?self
    {
        return self::TryLoadByUniqueKey($database, 'username', $username);
    }
    
    public static function LoadByUsername(ObjectDatabase $database, string $username) : self
    {
        return self::LoadByUniqueKey($database, 'username', $username);
    }
    
    public static function TryLoadByContactInfo(ObjectDatabase $database, string $info) : ?self
    {
        $info = ContactInfo::TryLoadByInfo($database, $info);
        if ($info === null) return null; else return $info->GetAccount();
    }
    
    public static function LoadByContactInfo(ObjectDatabase $database, string $info) : self
    {
        return ContactInfo::LoadByInfo($database, $info)->GetAccount();
    }
    
    public function GetEmailRecipients(bool $redacted = false) : array
    {
        $name = $this->GetFullName();
        $emails = ContactInfo::GetEmails($this->GetContactInfos());
        
        return array_map(function($email) use($name,$redacted){
            if ($redacted) return ContactInfo::RedactEmail($email);
            else return new EmailRecipient($email, $name);
        }, $emails);
    }
    
    public function SendMailTo(Emailer $mailer, string $subject, string $message, ?EmailRecipient $from = null)
    {
        $recipients = $this->GetEmailRecipients();
        
        if (count($recipients) == 0) throw new EmailUnavailableException();
        
        $mailer->SendMail($subject, $message, $recipients, $from);
    }
    
    public static function Create(ObjectDatabase $database, Auth\Source $source, string $username, string $password) : self
    {        
        $account = parent::BaseCreate($database); $config = Config::Load($database);
        
        $account->SetScalar('username',$username)->ChangePassword($password);
        
        if (!($source instanceof Auth\Local)) $account->SetObject('authsource',$source);
        
        foreach (array_filter(array($config->GetDefaultGroup(), $source->GetAccountGroup())) as $group)
            GroupMembership::Create($database, $account, $group);

        if ($account->allowCrypto()) $account->InitializeCrypto($password);        

        return $account;
    }
    
    public function Delete() : void
    {
        foreach ($this->GetSessions() as $session)          $session->Delete();
        foreach ($this->GetClients() as $client)            $client->Delete();
        foreach ($this->GetTwoFactors() as $twofactor)      $twofactor->Delete();
        foreach ($this->GetContactInfos() as $contactinfo)  $contactinfo->Delete();
        foreach ($this->GetGroupMemberships() as $groupm)   $groupm->Delete();
        
        parent::Delete();
    }
    
    const OBJECT_FULL = 0; const OBJECT_SIMPLE = 1;     
    const OBJECT_USER = 0; const OBJECT_ADMIN = 2;
    
    public function GetClientObject(int $level = self::OBJECT_FULL) : array
    {
        $mapobj = function($e) { return $e->GetClientObject(); };
        
        $data = array(
            'id' => $this->ID(),
            'username' => $this->GetUsername(),
            'fullname' => $this->GetFullName(),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(),
            'limits' => $this->GetAllCounterLimits(),
            'features' => $this->GetAllFeatures(),
            'timeout' => $this->GetMaxSessionAge(),
        );   
        
        if ($level % self::OBJECT_ADMIN === self::OBJECT_FULL)
        {
            $data['clients'] = array_map($mapobj, $this->GetClients());
            $data['twofactors'] = array_map($mapobj, $this->GetTwoFactors());
            $data['contactinfos'] = array_map($mapobj, $this->GetContactInfos());
        }
        
        if ($level >= self::OBJECT_ADMIN)
        {
            $data['comment'] = $this->TryGetScalar('comment');
            
            $data['groups'] = array_map(function($e){ return $e->ID(); }, $this->GetGroups());
        }

        return $data;
    }

    protected function GetScalar(string $field)
    {
        if ($this->ExistsScalar($field)) $value = parent::GetScalar($field);
        else if ($this->ExistsScalar($field."__inherits")) $value = $this->InheritableSearch($field)->GetValue();
        else throw new KeyNotFoundException($field);
        
        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetScalar(string $field)
    {
        if ($this->ExistsScalar($field)) return parent::TryGetScalar($field);
        else if ($this->ExistsScalar($field."__inherits")) return $this->InheritableSearch($field)->GetValue();
        else return null;
    }
    
    protected function GetObject(string $field) : BaseObject
    {
        if ($this->ExistsObject($field)) $value = parent::GetObject($field);
        else if ($this->ExistsObject($field."__inherits")) $value = $this->InheritableSearch($field, true)->GetValue();
        else throw new KeyNotFoundException($field);

        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetObject(string $field) : ?BaseObject
    {
        if ($this->ExistsObject($field)) return parent::TryGetObject($field);
        else if ($this->ExistsObject($field."__inherits")) return $this->InheritableSearch($field, true)->GetValue();
        else return null;
    }
    
    protected function TryGetInheritsScalarFrom(string $field) : ?AuthEntity
    {
        return $this->InheritableSearch($field)->GetSource();
    }
    
    protected function TryGetInheritsObjectFrom(string $field) : ?AuthEntity
    {
        return $this->InheritableSearch($field, true)->GetSource();
    }
    
    private function InheritableSearch(string $field, bool $useobj = false) : InheritedProperty
    {
        if ($useobj) $value = parent::TryGetObject($field."__inherits");
        else $value = parent::TryGetScalar($field."__inherits");
        
        if ($value !== null) return new InheritedProperty($value, $this);
        
        $priority = PHP_INT_MIN; $source = null;
        
        foreach ($this->GetGroups() as $group)
        {
            if ($useobj) $temp_value = $group->TryGetMembersObject($field);
            else $temp_value = $group->TryGetMembersScalar($field);
            
            $temp_priority = $group->GetPriority();
            
            if ($temp_value !== null && $temp_priority > $priority)
            {
                $value = $temp_value; $source = $group; 
                $priority = $temp_priority;
            }
        }

        return new InheritedProperty($value, $source);
    }
    
    public function CheckTwoFactor(string $code) : bool
    {
        foreach ($this->GetTwoFactors() as $twofactor) { 
            if ($twofactor->CheckCode($code)) return true; }
        
        return false;
    }
    
    public function CheckRecoveryCode(string $key) : bool
    {
        if (!$this->HasRecoveryKeys()) return false;    

        foreach ($this->GetRecoveryKeys() as $source)
        {
            if ($source->CheckCode($key)) return true;
        }
        
        return false;
    }
    
    public function VerifyPassword(string $password) : bool
    {
        return $this->GetAuthSource()->VerifyPassword($this->GetUsername(), $password);
    }    
    
    public function CheckPasswordAge() : bool
    {
        if (!($this->GetAuthSource() instanceof Auth\Local)) return true;
        
        $date = $this->getPasswordDate(); $max = $this->GetMaxPasswordAge();
        
        if ($date < 0) return false; else return ($max === null || time()-$date < $max);
    }
    
    const CRYPTO_FORCE_OFF = 0; const CRYPTO_DEFAULT_OFF = 1; const CRYPTO_DEFAULT_ON = 2; const CRYPTO_FORCE_ON = 3;
    
    public function allowCrypto() : bool    { return ($this->TryGetFeature('encryption') ?? self::CRYPTO_FORCE_OFF) >= self::CRYPTO_DEFAULT_OFF; }
    public function useCrypto() : bool      { return ($this->TryGetFeature('encryption') ?? self::CRYPTO_FORCE_OFF) >= self::CRYPTO_DEFAULT_ON; }
    
    public function hasCrypto() : bool { return $this->TryGetScalar('master_key') !== null; }
    
    private $cryptoAvailable = false; public function CryptoAvailable() : bool { return $this->cryptoAvailable; }
    
    public function ChangePassword(string $new_password, string $old_password = "") : Account
    {
        if ($this->hasCrypto())
        {
            if (!$this->CryptoAvailable())
            {
                if (!$old_password) throw new RekeyOldPasswordRequired();
                $this->UnlockCryptoFromPassword($old_password); 
            }
            $this->CryptoRekey($new_password);
        }
        
        if ($this->GetAuthSource() instanceof Auth\Local)
            $this->SetScalar('password', Auth\Local::HashPassword($new_password));

        return $this->setPasswordDate();
    }
    
    public function EncryptSecret(string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable || !$this->allowCrypto()) throw new CryptoUnavailableException();    
        
        $master = $this->GetScalar('master_key');
        return CryptoSecret::Encrypt($data, $nonce, $master);
    }
    
    public function DecryptSecret(string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnavailableException();
        
        $master = $this->GetScalar('master_key');
        return CryptoSecret::Decrypt($data, $nonce, $master);
    }

    public function EncryptKeyFor(AuthEntity $recipient, string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable || !$recipient->hasCrypto()) throw new CryptoUnavailableException();
        if (!$this->allowCrypto() || !$recipient->useCrypto()) throw new CryptoUnavailableException();
        return CryptoPublic::Encrypt($data, $nonce, $recipient->GetPublicKey(), $this->GetScalar('private_key'));
    }
    
    public function DecryptKeyFrom(AuthEntity $sender, string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable || !$sender->hasCrypto()) throw new CryptoUnavailableException();
        return CryptoPublic::Decrypt($data, $nonce, $this->GetScalar('private_key'), $sender->GetPublicKey());
    }   
    
    public function GetEncryptedMasterKey(string $nonce, string $key) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnavailableException();
        return CryptoSecret::Encrypt($this->GetScalar('master_key'), $nonce, $key);
    }
    
    public function UnlockCryptoFromPassword(string $password) : bool
    {
        if ($this->cryptoAvailable) return true; 
        
        if (!$this->hasCrypto())
        {
            if (!$this->allowCrypto()) return false;
            else $this->InitializeCrypto($password);
        }

        $master = $this->GetScalar('master_key');
        $master_nonce = $this->GetScalar('master_nonce');
        $master_salt = $this->GetScalar('master_salt');
        
        $password_key = CryptoSecret::DeriveKey($password, $master_salt);        
        $master = CryptoSecret::Decrypt($master, $master_nonce, $password_key);
        
        return $this->UnlockCryptoFromKey($master);
    }
    
    public function UnlockCryptoFromKeySource(MasterKeySource $source, string $key) : bool
    {
        if ($this->cryptoAvailable) return true;        
        if (!$this->hasCrypto()) return false;
        
        $master = $source->GetUnlockedKey($key);
        
        return $this->UnlockCryptoFromKey($master);
    }
    
    private function UnlockCryptoFromKey(string $master) : bool
    {
        $private = $this->GetScalar('private_key');
        $private_nonce = $this->GetScalar('private_nonce');

        $private = CryptoSecret::Decrypt($private, $private_nonce, $master);
        
        $this->SetScalar('master_key',  $master, true);
        $this->SetScalar('private_key', $private,true);
        
        $this->cryptoAvailable = true; return true;
    }
    
    private function InitializeCrypto(string $password) : self
    {
        $master_salt = CryptoSecret::GenerateSalt(); $this->SetScalar('master_salt', $master_salt);
        
        $master_nonce = CryptoSecret::GenerateNonce(); $this->SetScalar('master_nonce',  $master_nonce);   
        $private_nonce = CryptoSecret::GenerateNonce(); $this->SetScalar('private_nonce', $private_nonce);
        
        $password_key = CryptoSecret::DeriveKey($password, $master_salt);
        
        $master = CryptoSecret::GenerateKey();
        $master_encrypted = CryptoSecret::Encrypt($master, $master_nonce, $password_key);
        
        $keypair = CryptoPublic::GenerateKeyPair();
        $public = $keypair['public']; $private = $keypair['private'];        
        $private_encrypted = CryptoSecret::Encrypt($private, $private_nonce, $master);        

        $this->SetScalar('public_key',    $public);
        $this->SetScalar('private_key',   $private_encrypted);        
        $this->SetScalar('master_key',    $master_encrypted); 
        
        $this->SetScalar('master_key',  $master, true); sodium_memzero($master); 
        $this->SetScalar('private_key', $private,true); sodium_memzero($private);       
        
        $this->cryptoAvailable = true; 
        
        foreach ($this->GetRecoveryKeys() as $recovery) $recovery->EnableCrypto();
        foreach ($this->GetTwoFactors() as $twofactor) $twofactor->EnableCrypto();

        return $this;
    }
    
    private function CryptoRekey(string $password) : self
    {
        if (!$this->cryptoAvailable) throw new CryptoUnavailableException();
        
        $master_salt = CryptoSecret::GenerateSalt(); $this->SetScalar('master_salt', $master_salt);        
        $master_nonce = CryptoSecret::GenerateNonce(); $this->SetScalar('master_nonce', $master_nonce);
        
        $password_key = CryptoSecret::DeriveKey($password, $master_salt);
        
        $master = $this->GetScalar('master_key');
        $master_encrypted = CryptoSecret::Encrypt($master, $master_nonce, $password_key);
        
        $this->SetScalar('master_key', $master_encrypted); 
        $this->SetScalar('master_key', $master, true); 
        
        return $this;
    }
    
    public function __destruct()
    {
        $this->scalars['master_key']->EraseValue();
        $this->scalars['private_key']->EraseValue();
    }
}

