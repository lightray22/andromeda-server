<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\InstallerApp;

/** The files app installer */
class FilesInstallApp extends InstallerApp
{
    public function getName() : string { return 'files'; }
    
    public function getDependencies() : array { return array('accounts'); }
    
    protected function getConfigClass() : string { return Config::class; }

    protected function getUpgradeScripts() : array
    {
        return array(/*
            '1.0.2' => function() { },
            '1.0.4' => function() { }
        */);        
    }
}
