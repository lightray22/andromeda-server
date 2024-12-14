<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, TableTypes};

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\{AuthObjectFull, AccountKeySource, IKeySource};

/**
 * A recovery key allows account recovery by bypassing a password
 * 
 * Also stores a backup copy of the account's master key, 
 * and as a matter of convention, can byapss two factor
 * 
 * @phpstan-type RecoveryKeyJ array{date_created:float, authkey?:string}
 */
class RecoveryKey extends BaseObject implements IKeySource
{
    use TableTypes\TableNoChildren;
    
    use AccountKeySource, AuthObjectFull { CheckFullKey as BaseCheckFullKey; CheckKeyMatch as BaseCheckKeyMatch; }

    protected static function GetFullKeyPrefix() : string { return "rk"; } 
    
    private const SET_SIZE = 8;
    
    /** Date the recovery key was created */
    private FieldTypes\Timestamp $date_created;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        
        $this->RegisterFields($fields, self::class);
        
        $this->AuthObjectCreateFields();
        $this->AccountKeySourceCreateFields();
        
        parent::CreateFields();
    }
    
    /**
     * Returns a new array of recovery keys of the default set size
     * @param ObjectDatabase $database
     * @param Account $account
     * @return list<static>
     */
    public static function CreateSet(ObjectDatabase $database, Account $account) : array
    {        
        return array_map(function($i)use($database, $account){ 
            return static::Create($database, $account); 
        }, range(0, self::SET_SIZE-1));
    }
 
    /**
     * Creates a single recovery key for an account
     * @return static
     */
    public static function Create(ObjectDatabase $database, Account $account) : self
    {
        $account->CheckLimitRecoveryKeys();

        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        
        $obj->AccountKeySourceCreate(
            $account, $obj->InitAuthKey());
        
        return $obj;
    }

    public function CheckFullKey(string $code) : bool
    {
        $retval = $this->BaseCheckFullKey($code);

        if ($retval) $this->DeleteLater();
        
        return $retval;
    }

    public function CheckKeyMatch(string $key) : bool
    {
        if (!$this->BaseCheckKeyMatch($key)) return false;
        
        if ($this->hasCrypto())
            $this->UnlockCrypto($key); // shouldn't throw if key matches
        
        return true;
    }

    /** Count recovery keys for a given account */
    public static function CountByAccount(ObjectDatabase $database, Account $account) : int
    { 
        return $database->CountObjectsByKey(static::class, 'account', $account->ID());
    }

    /** 
     * Load all recovery keys for a given account 
     * @return array<string, static>
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    { 
        return $database->LoadObjectsByKey(static::class, 'account', $account->ID());
    }

    /** Deletes all recovery keys owned by the given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }

    /**
     * Gets a printable client object for this key
     * @return RecoveryKeyJ
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $retval = array(
            'date_created' => $this->date_created->GetValue()
        );
        
        if ($secret) $retval['authkey'] = $this->GetFullKey();
    
        return $retval;
    }
}

