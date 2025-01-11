<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\InstallerApp;
use Andromeda\Core\Utilities;

/**
 * The files app installer
 * @phpstan-import-type ScalarOrArray from Utilities
 */
class FilesInstallApp extends InstallerApp
{
    public function getName() : string { return 'files'; }
    
    protected function getConfigClass() : string { return Config::class; }
    
    public function getDependencies() : array { return array('accounts'); }
    
    protected function getUpgradeScripts() : array
    {
        return array(/*
            '1.0.2' => function() { },
            '1.0.4' => function() { }
        */);        
    }
}
