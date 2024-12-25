<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\{Config, InstallerApp, Utilities};
use Andromeda\Core\Database\PDODatabase;
use Andromeda\Core\IOFormat\{Input, IOInterface, SafeParams};

/** 
 * The core config installer, also can install/upgrade all apps 
 * @phpstan-import-type ScalarOrArray from Utilities
 */
class CoreInstallApp extends InstallerApp
{
    public function getName() : string { return 'core'; }

    protected function getTemplateFolder() : string { return ROOT.'/Core'; }
    
    /** @return class-string<Config> */
    protected function getConfigClass() : string { return Config::class; }

    public function getUsage() : array
    {
        $retval = array_merge(array(
            'usage [--appname alphanum]',
            'dbconf '.PDODatabase::GetInstallUsage(),
            ...array_map(function($u){ return "(dbconf...) $u"; }, PDODatabase::GetInstallUsages()),
            'scanapps',
        ), parent::getUsage());
    
        $inst_flags = implode(" ",array_map(function(InstallerApp $installer){
            return $installer->getInstallFlags(); }, $this->runner->GetInstallers()));
        
        $upgr_flags = implode(" ",array_map(function(InstallerApp $installer){
            return $installer->getUpgradeFlags(); }, $this->runner->GetInstallers()));
        
        $retval[] = 'setupall [--noenable] '.$inst_flags;
        $retval[] = 'upgradeall '.$upgr_flags;
        
        return $retval;
    }
    
    /**
     * {@inheritDoc}
     * @see InstallerApp::Run()
     */
    public function Run(Input $input) : mixed
    {
        $params = $input->GetParams();
        
        switch ($input->GetAction())
        {
            case 'usage':      return $this->GetUsages($params);
            case 'dbconf':     return $this->ConfigDB($params);
            case 'scanapps':   return $this->ScanApps();
            case 'setupall':   return $this->SetupAll($params);
            case 'upgradeall': return $this->UpgradeAll($params);
            
            default: return parent::Run($input);
        }
    }

    /**
     * Collects usage strings from every installed app and returns them
     * @return string|list<string> array of possible commands
     */
    protected function GetUsages(SafeParams $params)
    {
        $want = $params->HasParam('appname') ? $params->GetParam('appname')->GetAlphanum() : null;
        
        $installers = $this->runner->GetInstallers();

        $output = array(); foreach ($installers as $name=>$installer)
        {
            if ($want !== null && $want !== $name) continue;
            
            $output = array_merge($output, array_map(function(string $line)use($name){
                return "$name $line"; }, $installer->getUsage())); 
        }
        
        if ($this->runner->GetInterface()->GetOutputMode() === IOInterface::OUTPUT_PLAIN)
            $output = implode("\r\n", $output);

        return $output;
    }

    /**
     * Creates a database config with the given input
     * @throws Exceptions\AdminRequiredException if DB config exists and not a privileged interface
     */
    protected function ConfigDB(SafeParams $params) : ?string
    {
        if ($this->runner->HasDatabaseConfig() &&
            !$this->runner->GetInterface()->isPrivileged())
        {
            throw new Exceptions\AdminRequiredException();
        }
        
        return PDODatabase::Install($params);
    }
    
    /** 
     * Scans for available apps to install (in dependency order)
     * @throws Exceptions\AdminRequiredException if DB config exists and not a privileged interface
     * @return list<string>
     */
    protected function ScanApps() : array
    {
        if ($this->runner->HasDatabaseConfig() &&
            !$this->runner->GetInterface()->isPrivileged())
        {
            throw new Exceptions\AdminRequiredException();
        }
        
        return array_keys(static::SortInstallers(
            $this->runner->GetInstallers()));
    }
    
    /** 
     * Returns true if a has a dependency on b (directly or indirectly)
     * @param array<string, InstallerApp> $insts
     */
    public static function HasDependency(array $insts, InstallerApp $a, InstallerApp $b) : bool
    {
        $adeps = $a->getDependencies();
        if (in_array($b->getName(), $adeps, true)) return true;
        
        foreach ($adeps as $adep)
        {
            if (array_key_exists($adep, $insts) && 
                self::HasDependency($insts, $insts[$adep], $b)) return true;
        }
        
        return false;
    }
    
    /**
     * Sort installers by resolving dependencies
     * @param array<string,InstallerApp> $insts
     * @return array<string,InstallerApp>
     */
    public static function SortInstallers(array $insts) : array
    {
        uasort($insts, function(InstallerApp $a, InstallerApp $b)use($insts){
            if (static::HasDependency($insts, $a, $b)) return 1;
            if (static::HasDependency($insts, $b, $a)) return -1;
            return 0;
        });
        return $insts;
    }
    
    /**
     * Installs AND enables all available apps (including core)
     * @return array<string, ScalarOrArray> map of installed apps to their install retval
     * @see Config::ScanApps() 
     */
    protected function SetupAll(SafeParams $params) : array
    {
        // install all existing apps
        $installers = static::SortInstallers(
            $this->runner->GetInstallers());

        $retvals = array_map(function(InstallerApp $installer)use($params){ 
            return $installer->Install($params); }, $installers);

        if ($params->GetOptParam('noenable',false,SafeParams::PARAMLOG_ALWAYS)->GetBool())
            return $retvals; // don't enable all the apps

        // core must be installed now, can load config
        $database = $this->runner->RequireDatabase();
        $config = Config::GetInstance($database);

        foreach (Config::ScanApps() as $appname)
        {
            $config->EnableApp($appname, false);
            $retvals[$appname] ??= null;
        }
        return $retvals;
    }
    
    /**
     * Upgrades all installed apps (including core)
     * @return array<string, ScalarOrArray> map of upgraded apps to their upgrade retval
     */
    protected function UpgradeAll(SafeParams $params) : array
    {
        // upgrade all installed apps
        $installers = static::SortInstallers(
            $this->runner->GetInstallers());
        
        return array_map(function(InstallerApp $installer)use($params){
            return $installer->Upgrade($params); }, $installers);
    }
    
    protected function getUpgradeScripts() : array
    {
        return array(/*
            '1.0.2' => function() { },
            '1.0.4' => function() { }
        */);        
    }
}
