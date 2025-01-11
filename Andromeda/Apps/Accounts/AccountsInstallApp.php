<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\InstallerApp;
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Core\Utilities;

/**
 * The accounts app installer
 * @phpstan-import-type ScalarOrArray from Utilities
 */
class AccountsInstallApp extends InstallerApp
{
    public function getName() : string { return 'accounts'; }
    
    protected function getConfigClass() : string { return Config::class; }
    
    protected function getInstallFlags() : string { return '[--username alphanum --password raw]'; }
    
    public function getDependencies() : array { return array('core'); }
    
    /**
     * {@inheritDoc}
     * Also optionally creates an admin account
     * @see InstallerApp::Install()
     * @return ScalarOrArray
     */
    protected function Install(SafeParams $params)
    {
        parent::Install($params);
        
        $db = $this->runner->RequireDatabase();
        
        if ($params->HasParam('username'))
        {
            $username = $params->GetParam("username", SafeParams::PARAMLOG_ALWAYS)->CheckLength(127)->GetAlphanum();
            $password = $params->GetParam("password", SafeParams::PARAMLOG_NEVER)->GetRawString();
            
            return Account::Create($db, $username, $password)->SetAdmin(true)->GetUserClientObject();
        }
        else return null;
    }

    protected function getUpgradeScripts() : array
    {
        return array(/*
            '1.0.2' => function() { },
            '1.0.4' => function() { }
        */);        
    }
}
