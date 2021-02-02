<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/accounts/KeySource.php");

/**
 * Implements an account session, the primary implementor of authentication
 *
 * Also stores a copy of the account's master key, encrypted by the session key.
 * This allowed account crypto to generally be unlocked for any user command.
 */
class Session extends KeySource
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(  
            'dates__active' => new FieldTypes\Scalar(null, true),
            'account' => new FieldTypes\ObjectRef(Account::class, 'sessions'),
            'client' => new FieldTypes\ObjectRef(Client::class, 'session', false)
        ));
    }
    
    /** Returns the client that owns this session */
    public function GetClient() : Client { return $this->GetObject('client'); }

    /** Returns the last timestamp this session was active */
    public function getActiveDate() : int { return $this->GetDate('active'); }
    
    /** Sets the timestamp the session was active to now */
    public function setActiveDate() : Session { return $this->SetDate('active'); }
    
    /** Create a new session for the given account and client */
    public static function Create(ObjectDatabase $database, Account $account, Client $client) : Session
    {
        return parent::CreateKeySource($database, $account)->SetObject('client',$client);
    }
    
    /** Deletes all sessions for the given account except the given session */
    public static function DeleteByAccountExcept(ObjectDatabase $database, Account $account, Session $session) : void
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->NotEquals('id',$session->ID()));
        parent::DeleteByQuery($database, $q->Where($w));
    }

    /** Authenticates the given key against the session, returning the result */
    public function CheckKeyMatch(string $key) : bool
    {
        $max = $this->GetAccount()->GetMaxSessionAge();
        
        if ($max !== null && Main::GetInstance()->GetTime() - $this->getActiveDate() > $max)
        {
            $this->Delete(); return false;
        }
        
        return parent::CheckKeyMatch($key);
    }
    
    /**
     * Returns a printable client object for this session
     * @return array `{id:string,client:id,dates:{created:int,active:int]}`
     * @see AuthObject::GetClientObject()
     */
    public function GetClientObject(bool $secret = false) : array
    {
        return array_merge(parent::GetClientObject($secret), array(
            'id' => $this->ID(),
            'client' => $this->GetClient()->ID(),
            'dates' => $this->GetAllDates(),
        ));
    }
}
