<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\{ApiPackage, BaseApp, Config, Emailer, EmailRecipient, Utilities};
use Andromeda\Core\Database\PDODatabase;
use Andromeda\Core\Errors\ErrorLog;
use Andromeda\Core\Exceptions as CoreExceptions;
use Andromeda\Core\IOFormat\{Input, Output, SafeParams, OutputHandler, IOInterface};
use Andromeda\Core\Logging\ActionLog as BaseActionLog;

use Andromeda\Apps\Accounts\Authenticator;

/**
 * Server management/info app included with the framework.
 * 
 * Handles getting/setting config/logs, app enable/disable.
 * Depends on the accountsApp being present but this could be refactored away easily.
 * If the accountsApp is disabled, only privileged interfaces are considered admin
 * 
 * @phpstan-import-type ActionLogJ from BaseActionLog
 * @phpstan-import-type ConfigJ from Config
 * @phpstan-import-type EmailerJ from Emailer
 * @phpstan-import-type ErrorLogJ from ErrorLog
 * @phpstan-import-type PDODatabaseInfoJ from PDODatabase
 * @phpstan-import-type PDODatabaseConfigJ from PDODatabase
 */
class CoreApp extends BaseApp
{
    private Config $config;
    
    public function getName() : string { return 'core'; }
    
    public function getVersion() : string { return andromeda_version; }

    /** @return class-string<ActionLog> */
    public function getLogClass() : string { return ActionLog::class; }

    public function getUsage() : array
    {
        return array(
            'usage [--appname alphanum]',
            'phpinfo',
            'serverinfo',
            'scanapps',
            'enableapp --appname alphanum',
            'disableapp --appname alphanum',
            'getconfig',
            'getdbconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            'testmail [--mailid id] [--dest email] [--testkey int]',
            'getmailers',
            'createmailer [--test email] '.Emailer::GetCreateUsage(),
            ...array_map(function($u){ return "(createmailer) $u"; },Emailer::GetCreateUsages()),
            'deletemailer --mailid id',
            'geterrors '.ErrorLog::GetPropUsage($this->database).' '.ErrorLog::GetLoadUsage(),
            'counterrors '.ErrorLog::GetPropUsage($this->database).' '.ErrorLog::GetCountUsage(),
            'getactions '.BaseActionLog::GetPropUsage($this->database).' '.BaseActionLog::GetLoadUsage().' [--expand bool]',
            ...array_map(function($u){ return "(getactions) $u"; },BaseActionLog::GetAppPropUsages($this->database)),
            'countactions '.BaseActionLog::GetPropUsage($this->database).' '.BaseActionLog::GetCountUsage(),
            ...array_map(function($u){ return "(countactions) $u"; },BaseActionLog::GetAppPropUsages($this->database)),
        );
    }
    
    public function __construct(ApiPackage $api)
    {
        parent::__construct($api);
        
        $this->config = Config::GetInstance($this->database);
    }
    
    /**
     * {@inheritDoc}
     * @throws CoreExceptions\UnknownActionException if the given action is not valid
     * @see BaseApp::Run()
     */
    public function Run(Input $input)
    {
        // if the accounts app is installed, use it for authentication
        if (array_key_exists('accounts', $this->API->GetAppRunner()->GetApps()))
        {
            $authenticator = Authenticator::TryAuthenticate(
                $this->database, $input, $this->API->GetInterface());
            
            $isAdmin = $authenticator !== null && $authenticator->isAdmin();
        }
        else // not using the accounts app, check interface privilege
        {
            $authenticator = null; 
            $isAdmin = $this->API->GetInterface()->isPrivileged();
        }
        
        $actionlog = null; if ($this->wantActionLog())
        {
            $actionlog = ActionLog::Create($this->database, $this->API->GetInterface(), $input);
            $actionlog->SetAuth($authenticator)->SetAdmin($isAdmin);
            $this->setActionLog($actionlog);
        }
        
        $params = $input->GetParams();

        switch ($input->GetAction())
        {
            case 'usage':    return $this->GetUsages($params);
            
            case 'phpinfo':    $this->PHPInfo($isAdmin); return null;
            case 'serverinfo': return $this->ServerInfo($isAdmin);
            
            case 'scanapps':    return $this->ScanApps($isAdmin);
            case 'enableapp':   return $this->EnableApp($params, $isAdmin);
            case 'disableapp':  return $this->DisableApp($params, $isAdmin);
            
            case 'getconfig':   return $this->GetConfig($isAdmin);
            case 'getdbconfig': return $this->GetDBConfig($isAdmin);
            case 'setconfig':   return $this->SetConfig($params, $isAdmin);
            
            case 'testmail':   $this->TestMail($params, $isAdmin, $authenticator, $actionlog); return null;
            case 'getmailers':   return $this->GetMailers($isAdmin); 
            case 'createmailer': return $this->CreateMailer($params, $isAdmin, $authenticator, $actionlog);
            case 'deletemailer': $this->DeleteMailer($params, $isAdmin, $actionlog); return null;
            
            case 'geterrors':     return $this->GetErrors($params, $isAdmin);
            case 'counterrors':   return $this->CountErrors($params, $isAdmin);
            
            case 'getactions':    return $this->GetActions($params, $isAdmin);
            case 'countactions':  return $this->CountActions($params, $isAdmin);
            
            default: throw new CoreExceptions\UnknownActionException($input->GetAction());
        }
    }
        
    /**
     * Collects usage strings from every installed app and returns them
     * @return string|list<string> array of possible commands
     */
    protected function GetUsages(SafeParams $params)
    {
        $want = $params->HasParam('appname') ? $params->GetParam('appname')->GetAlphanum() : null;
        
        $apps = $this->API->GetAppRunner()->GetApps();

        $output = array(); foreach ($apps as $name=>$app)
        {
            if ($want !== null && $want !== $name) continue;
            
            $output = array_merge($output, array_map(function(string $line)use($name){
                return "$name $line"; }, $app->getUsage())); 
        }

        if ($this->API->GetInterface()->GetOutputMode() === IOInterface::OUTPUT_PLAIN)
            $output = implode("\r\n", $output);

        return $output;
    }

    /**
     * Prints the phpinfo() page (and sets outmode to NONE)
     * @throws Exceptions\AdminRequiredException if not admin-level access
     */
    protected function PHPInfo(bool $isAdmin) : void
    {
        $this->API->GetInterface()->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();

        $retval = Utilities::CaptureOutput(function(){ phpinfo(); });
        
        $this->API->GetInterface()->SetOutputHandler(new OutputHandler(
            function()use($retval){ return strlen($retval); }, 
            function(Output $output)use($retval){ echo $retval; }));
    }
    
    /**
     * Gets miscellaneous server identity information
     * @throws Exceptions\AdminRequiredException if not admin-level access
     * @return array{uname:string, php_version:string, zend_version:string, 
     *   server:array<string,scalar>, load:?list<int>, db:PDODatabaseInfoJ}
     */
    protected function ServerInfo(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        /** @var array<string,scalar> */
        $server = array_filter($_SERVER, function($key){ 
            return mb_strpos((string)$key, 'andromeda_') !== 0; }, ARRAY_FILTER_USE_KEY);
        unset($server['argv']); unset($server['argc']);

        /** @var list<int>|false */
        $loadb = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        return array(
            'server' => $server,
            'uname' => php_uname(),
            'load' => is_array($loadb) ? $loadb : null,
            'php_version' => phpversion(),
            'zend_version' => zend_version(),
            'db' => $this->database->GetInternal()->getInfo()
        );
    }
    
    /**
     * Sends a test email via a given mailer
     * @throws Exceptions\AdminRequiredException if not an admin via the accounts app
     * @throws Exceptions\UnknownMailerException if the given mailer is invalid
     * @throws Exceptions\MailSendFailException if sending the email fails
     */
    protected function TestMail(SafeParams $params, bool $isAdmin, ?Authenticator $authenticator, ?BaseActionLog $actionlog) : void
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();

        if ($authenticator === null || $params->HasParam('dest'))
        {
            $dest = $params->GetParam('dest')->GetEmail();
            $dests = array(new EmailRecipient($dest));
        }
        else $dests = $authenticator->GetAccount()->GetContactEmails();
        
        $subject = "Andromeda Email Test";
        $body = "This is a test email from Andromeda";

        if ($params->HasParam('testkey'))
            $subject .= " ".$params->GetParam('testkey')->GetInt();
        
        if ($params->HasParam('mailid'))
        {
            $mailer = $params->GetParam('mailid')->GetRandstr();
            $mailer = Emailer::TryLoadByID($this->database, $mailer);
            
            if ($mailer === null) 
                throw new Exceptions\UnknownMailerException();
        }
        else $mailer = Emailer::LoadAny($this->database);
        
        if ($actionlog !== null) $actionlog->LogDetails('mailer',$mailer->ID());
        
        try { $mailer->SendMail($subject, $body, false, $dests, false); }
        catch (CoreExceptions\MailSendException $e) { 
            throw new Exceptions\MailSendFailException($e); }
    }
    
    /** 
     * Scans for available apps to enable/disable
     * @see Config::ScanApps() 
     * @return list<string>
     */
    protected function ScanApps(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        return Config::ScanApps();
    }
    
    /**
     * Registers (enables) an app
     * @throws Exceptions\AdminRequiredException if not an admin
     * @throws Exceptions\InvalidAppException if the app is not valid
     * @return list<string> array of enabled apps
     */
    protected function EnableApp(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();

        $app = $params->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        
        try { $this->config->EnableApp($app); }
        catch (CoreExceptions\FailedAppLoadException $e) { 
            throw new Exceptions\InvalidAppException($app); }

        return $this->config->GetApps();
    }
    
    /**
     * Unregisters (disables) an app
     * @throws Exceptions\AdminRequiredException if not an admin
     * @throws Exceptions\InvalidAppException if the app is not valid
     * @return list<string> array of enabled apps
     */
    protected function DisableApp(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        $app = $params->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();

        if ($app === static::getName()) // cannot disable core!
            throw new Exceptions\InvalidAppException($app);
        
        $this->config->DisableApp($app);
        
        return $this->config->GetApps();
    }
    
    /**
     * Loads server config
     * @return ConfigJ
     */
    protected function GetConfig(bool $isAdmin) : array
    {
        return $this->config->GetClientObject($isAdmin);
    }
    
    /**
     * Loads server DB config
     * @return PDODatabaseConfigJ
     */
    protected function GetDBConfig(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        return $this->database->GetInternal()->GetConfig();
    }

    /**
     * Sets server config
     * @throws Exceptions\AdminRequiredException if not an admin
     * @return ConfigJ
     */
    protected function SetConfig(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        return $this->config->SetConfig($params)->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured mailers
     * @throws Exceptions\AdminRequiredException if not an admin
     * @return array<string, EmailerJ> indexed by ID
     */
    protected function GetMailers(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        return array_map(function($m){ return $m->GetClientObject(); }, 
            Emailer::LoadAll($this->database));
    }
    
    /**
     * Creates a new emailer config
     * @throws Exceptions\AdminRequiredException if not an admin
     * @return EmailerJ
     */
    protected function CreateMailer(SafeParams $params, bool $isAdmin, ?Authenticator $authenticator, ?BaseActionLog $actionlog) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        $emailer = Emailer::Create($this->database, $params)->Save();
        
        if ($params->HasParam('test'))
        {
            $dest = $params->GetParam('test')->GetEmail();
            
            $params->AddParam('mailid',$emailer->ID())->AddParam('dest',$dest);
            
            $this->TestMail($params, $isAdmin, $authenticator, $actionlog);
        }

        if ($actionlog !== null) $actionlog->LogDetails('mailer',$emailer->ID()); 
        
        return $emailer->GetClientObject();
    }
    
    /**
     * Deletes a configured emailer
     * @throws Exceptions\AdminRequiredException if not an admin 
     * @throws Exceptions\UnknownMailerException if given an invalid emailer
     */
    protected function DeleteMailer(SafeParams $params, bool $isAdmin, ?BaseActionLog $actionlog) : void
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        $mailid = $params->GetParam('mailid',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $mailer = Emailer::TryLoadByID($this->database, $mailid);
        if ($mailer === null) throw new Exceptions\UnknownMailerException();
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('mailer', $mailer->GetClientObject(), true); // @phpstan-ignore-line no recursive ScalarArray
        
        $mailer->Delete();
    }
    
    /**
     * Returns all error logs matching the given input, default 100 max and sort by time DESC
     * @throws Exceptions\AdminRequiredException if not an admin 
     * @return array<string, ErrorLogJ> indexed by ID
     */
    protected function GetErrors(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        return array_map(function(ErrorLog $e){ return $e->GetClientObject(); },
            ErrorLog::LoadByParams($this->database, $params));
    }
    
    /**
     * Counts all error logs matching the given input
     * @throws Exceptions\AdminRequiredException if not an admin
     * @return int error log entry count
     */
    protected function CountErrors(SafeParams $params, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        return ErrorLog::CountByParams($this->database, $params);
    }
    
    /**
     * Returns all action logs matching the given input, default 100 max and sort by time DESC
     * @throws Exceptions\AdminRequiredException if not admin
     * @return array<string, ActionLogJ> indexed by ID
     */
    protected function GetActions(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        $expand = $params->GetOptParam('expand',false)->GetBool();
        
        return array_map(function(BaseActionLog $e)use($expand){ return $e->GetClientObject($expand); },
            BaseActionLog::LoadByParams($this->database, $params));
    }
    
    /**
     * Counts all action logs matching the given input
     * @throws Exceptions\AdminRequiredException if not admin
     * @return int action log entry count
     */
    protected function CountActions(SafeParams $params, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new Exceptions\AdminRequiredException();
        
        return BaseActionLog::CountByParams($this->database, $params);
    }
}

