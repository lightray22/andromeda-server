<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/Config.php");
require_once(ROOT."/apps/files/ItemAccess.php");
require_once(ROOT."/apps/files/Item.php");
require_once(ROOT."/apps/files/File.php");
require_once(ROOT."/apps/files/Folder.php");
require_once(ROOT."/apps/files/Comment.php");
require_once(ROOT."/apps/files/Tag.php");
require_once(ROOT."/apps/files/Like.php");
require_once(ROOT."/apps/files/Share.php");

require_once(ROOT."/apps/files/limits/Filesystem.php");
require_once(ROOT."/apps/files/limits/Account.php");

require_once(ROOT."/apps/files/storage/Storage.php"); 
use Andromeda\Apps\Files\Storage\FileReadFailedException;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\{AppBase, Main};
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\EmailRecipient;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/ioformat/interfaces/AJAX.php"); use Andromeda\Core\IOFormat\Interfaces\AJAX;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/apps/accounts/Authenticator.php"); use Andromeda\Apps\Accounts\{Authenticator, AuthenticationFailedException};

use Andromeda\Core\UnknownActionException;
use Andromeda\Core\UnknownConfigException;

use Andromeda\Core\Database\DatabaseException;
use Andromeda\Apps\Accounts\UnknownAccountException;
use Andromeda\Apps\Accounts\UnknownGroupException;

/** Exception indicating that the requested item does not exist */
class UnknownItemException extends Exceptions\ClientNotFoundException       { public $message = "UNKNOWN_ITEM"; }

/** Exception indicating that the requested folder does not exist */
class UnknownFolderException extends Exceptions\ClientNotFoundException     { public $message = "UNKNOWN_FOLDER"; }

/** Exception indicating that the requested object does not exist */
class UnknownObjectException extends Exceptions\ClientNotFoundException     { public $message = "UNKNOWN_OBJECT"; }

/** Exception indicating that the requested parent does not exist */
class UnknownParentException  extends Exceptions\ClientNotFoundException    { public $message = "UNKNOWN_PARENT"; }

/** Exception indicating that the requested destination folder does not exist */
class UnknownDestinationException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_DESTINATION"; }

/** Exception indicating that the requested filesystem does not exist */
class UnknownFilesystemException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_FILESYSTEM"; }

/** Exception indicating that the requested download byte range is invalid */
class InvalidDLRangeException extends Exceptions\ClientException { public $code = 416; public $message = "INVALID_BYTE_RANGE"; }

/** Exception indicating that the input file parameter format is incorrect */
class InvalidFileWriteException extends Exceptions\ClientErrorException     { public $message = "INVALID_FILE_WRITE_PARAMS"; }

/** Exception indicating that the requested byte range for file writing is invalid */
class InvalidFileRangeException extends Exceptions\ClientErrorException     { public $message = "INVALID_FILE_WRITE_RANGE"; }

/** Exception indicating that access to the requested item is denied */
class ItemAccessDeniedException extends Exceptions\ClientDeniedException    { public $message = "ITEM_ACCESS_DENIED"; }

/** Exception indicating that user-added filesystems are not allowed */
class UserStorageDisabledException extends Exceptions\ClientDeniedException   { public $message = "USER_STORAGE_NOT_ALLOWED"; }

/** Exception indicating that random write access is not allowed */
class RandomWriteDisabledException extends Exceptions\ClientDeniedException   { public $message = "RANDOM_WRITE_NOT_ALLOWED"; }

/** Exception indicating that item sharing is not allowed */
class ItemSharingDisabledException extends Exceptions\ClientDeniedException   { public $message = "SHARING_DISABLED"; }

/** Exception indicating that emailing share links is not allowed */
class EmailShareDisabledException extends Exceptions\ClientDeniedException    { public $message = "EMAIL_SHARES_DISABLED"; }

/** Exception indicating that the absolute URL of a share cannot be determined */
class ShareURLGenerateException extends Exceptions\ClientErrorException       { public $message = "CANNOT_OBTAIN_SHARE_URI"; }

/** Exception indicating that sharing to groups is not allowed */
class ShareGroupDisabledException extends Exceptions\ClientDeniedException    { public $message = "SHARE_GROUPS_DISABLED"; }

/** Exception indicating that sharing to everyone is not allowed */
class ShareEveryoneDisabledException extends Exceptions\ClientDeniedException { public $message = "SHARE_EVERYONE_DISABLED"; }

/**
 * App that provides user-facing filesystem services.
 * 
 * Provides a general filesystem API for managing files and folders,
 * as well as admin-level functions like managing filesystems and config.
 * 
 * Supports features like random-level byte access, multiple (user or admin-added)
 * filesystems with various backend storage drivers, social features including
 * likes and comments, sharing of content via links or to users or groups,
 * configurable rules per-account or per-filesystem, and granular statistics
 * gathering and limiting for accounts/groups/filesystems.
 */
class FilesApp extends AppBase
{
    public static function getVersion() : string { return "2.0.0-alpha"; } 
    
    public static function getUsage() : array 
    { 
        return array(
            'install',
            'getconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            '- AUTH for shared items: --sid id [--skey alphanum] [--spassword raw]',
            'upload --file% path [name] --parent id [--overwrite bool]',
            'download --file id [--fstart int] [--flast int]',
            'ftruncate --file id --size int',
            'writefile --data% path --file id [--offset int]',
            'getfilelikes --file id [--limit int] [--offset int]',
            'getfolderlikes --folder id [--limit int] [--offset int]',
            'getfilecomments --file id [--limit int] [--offset int]',
            'getfoldercomments --folder id [--limit int] [--offset int]',
            'fileinfo --file id [--details bool]',
            'getfolder [--folder id | --filesystem id] [--files bool] [--folders bool] [--recursive bool] [--limit int] [--offset int] [--details bool]',
            'getitembypath --path fspath [--folder id] [--isfile bool]',
            'editfilemeta --file id [--description ?text]',
            'editfoldermeta --folder id [--description ?text]',
            'ownfile --file id',
            'ownfolder --folder id',
            'createfolder --parent id --name fsname',
            'deletefile --file id',
            'deletefolder --folder id',
            'renamefile --file id --name fsname [--overwrite bool] [--copy bool]',
            'renamefolder --folder id --name fsname [--overwrite bool] [--copy bool]',
            'movefile --parent id --file id  [--overwrite bool] [--copy bool]',
            'movefolder --parent id --folder id [--overwrite bool] [--copy bool]',
            'likefile --file id --value ?bool',
            'likefolder --folder id --value ?bool',
            'tagfile --file id --tag alphanum',
            'tagfolder --folder id --tag alphanum',
            'deletetag --tag id',
            'commentfile --file id --comment text',
            'commentfolder --folder id --comment text',
            'editcomment --commentid id [--comment text]',
            'deletecomment --commentid id',
            'sharefile --file id (--link bool [--email email] | --account id | --group id | --everyone bool) '.Share::GetSetShareOptionsUsage(),
            'sharefolder --folder id (--link bool [--email email] | --account id | --group id | --everyone bool) '.Share::GetSetShareOptionsUsage(),
            'editshare --share id '.Share::GetSetShareOptionsUsage(),
            'deleteshare --share id',
            'shareinfo --sid id --skey alphanum [--spassword raw]',
            'listshares [--mine bool]',
            'listforeign',
            'getfilesystem [--filesystem id]',
            'getfilesystems [--everyone bool [--limit int] [--offset int]]',
            'createfilesystem '.FSManager::GetCreateUsage(),
            ...FSManager::GetCreateUsages(),
            'deletefilesystem --filesystem id --auth_password raw [--unlink bool]',
            'editfilesystem --filesystem id '.FSManager::GetEditUsage(),
            ...FSManager::GetEditUsages(),
            'getlimits [--account ?id | --group ?id | --filesystem ?id] [--limit int] [--offset int]',
            'gettimedlimits [--account ?id | --group ?id | --filesystem ?id] [--limit int] [--offset int]',
            'gettimedstatsfor [--account id | --group id | --filesystem id] --timeperiod int [--limit int] [--offset int]',
            'gettimedstatsat (--account ?id | --group ?id | --filesystem ?id) --timeperiod int --matchtime int [--limit int] [--offset int]',
            'configlimits (--account id | --group id | --filesystem id) '.Limits\Total::BaseConfigUsage(),
            "\t --account id ".Limits\AccountTotal::GetConfigUsage(),
            "\t --group id ".Limits\GroupTotal::GetConfigUsage(),
            "\t --filesystem id ".Limits\FilesystemTotal::GetConfigUsage(),
            'configtimedlimits (--account id | --group id | --filesystem id) '.Limits\Timed::BaseConfigUsage(),
            "\t --account id ".Limits\AccountTimed::GetConfigUsage(),
            "\t --group id ".Limits\GroupTimed::GetConfigUsage(),
            "\t --filesystem id ".Limits\FilesystemTimed::GetConfigUsage(),
            'purgelimits (--account id | --group id | --filesystem id)',
            'purgetimedlimits (--account id | --group id | --filesystem id) --period int',
        ); 
    }
    
    /** files app config */ private Config $config;
    
    /** database reference */ private ObjectDatabase $database;
    
    /** Authenticator for the current Run() */ private ?Authenticator $authenticator;
    
    /** function to be called when crypto unlock is required */
    private static $providesCrypto;

    /** informs the files app that account crypto needs to be available */
    public static function needsCrypto(){ $func = static::$providesCrypto; $func(); }
     
    public function __construct(Main $api)
    {
        parent::__construct($api);
        $this->database = $api->GetDatabase();
        
        try { $this->config = Config::GetInstance($this->database); }
        catch (DatabaseException $e) { }     
    }
    
    /**
     * {@inheritDoc}
     * @throws UnknownConfigException if config needs to be initialized
     * @throws UnknownActionException if the given action is not valid
     * @see AppBase::Run()
     */
    public function Run(Input $input)
    {
        // if config is not available, require installing it
        if (!isset($this->config) && $input->GetAction() !== 'install')
            throw new UnknownConfigException(static::class);
        
        if (isset($this->authenticator)) $oldauth = $this->authenticator;
        
        $this->authenticator = Authenticator::TryAuthenticate(
            $this->database, $input, $this->API->GetInterface());
        
        static::$providesCrypto = function(){ $this->authenticator->RequireCrypto(); };

        switch($input->GetAction())
        {
            case 'install':  return $this->Install($input);
            case 'getconfig': return $this->GetConfig($input);
            case 'setconfig': return $this->SetConfig($input);
            
            case 'upload':     return $this->UploadFile($input);  
            case 'download':   return $this->DownloadFile($input);
            case 'ftruncate':  return $this->TruncateFile($input);
            case 'writefile':  return $this->WriteToFile($input);
            case 'createfolder':  return $this->CreateFolder($input);
            
            case 'getfilelikes':   return $this->GetFileLikes($input);
            case 'getfolderlikes': return $this->GetFolderLikes($input);
            case 'getfilecomments':   return $this->GetFileComments($input);
            case 'getfoldercomments': return $this->GetFolderComments($input);
            
            case 'fileinfo':      return $this->GetFileInfo($input);
            case 'getfolder':     return $this->GetFolder($input);
            case 'getitembypath': return $this->GetItemByPath($input);
            
            case 'ownfile':   return $this->OwnFile($input);
            case 'ownfolder': return $this->OwnFolder($input);            
            
            case 'editfilemeta':   return $this->EditFileMeta($input);
            case 'editfoldermeta': return $this->EditFolderMeta($input);
           
            case 'deletefile':   return $this->DeleteFile($input);
            case 'deletefolder': return $this->DeleteFolder($input);            
            case 'renamefile':   return $this->RenameFile($input);
            case 'renamefolder': return $this->RenameFolder($input);
            case 'movefile':     return $this->MoveFile($input);
            case 'movefolder':   return $this->MoveFolder($input);
            
            case 'likefile':      return $this->LikeFile($input);
            case 'likefolder':    return $this->LikeFolder($input);
            case 'tagfile':       return $this->TagFile($input);
            case 'tagfolder':     return $this->TagFolder($input);
            case 'deletetag':     return $this->DeleteTag($input);
            case 'commentfile':   return $this->CommentFile($input);
            case 'commentfolder': return $this->CommentFolder($input);
            case 'editcomment':   return $this->EditComment($input);
            case 'deletecomment': return $this->DeleteComment($input);
            
            case 'sharefile':    return $this->ShareFile($input);
            case 'sharefolder':  return $this->ShareFolder($input);
            case 'editshare':    return $this->EditShare($input);
            case 'deleteshare':  return $this->DeleteShare($input);
            case 'shareinfo':    return $this->ShareInfo($input);
            case 'listshares':   return $this->ListShares($input);
            case 'listforeign':  return $this->ListForeign($input);
            
            case 'getfilesystem':  return $this->GetFilesystem($input);
            case 'getfilesystems': return $this->GetFilesystems($input);
            case 'createfilesystem': return $this->CreateFilesystem($input);
            case 'deletefilesystem': return $this->DeleteFilesystem($input);
            case 'editfilesystem':   return $this->EditFilesystem($input);
            
            case 'getlimits':      return $this->GetLimits($input);
            case 'gettimedlimits': return $this->GetTimedLimits($input);
            case 'gettimedstatsfor': return $this->GetTimedStatsFor($input);
            case 'gettimedstatsat':  return $this->GetTimedStatsAt($input);
            case 'configlimits':      return $this->ConfigLimits($input);
            case 'configtimedlimits': return $this->ConfigTimedLimits($input);
            case 'purgelimits':      return $this->PurgeLimits($input);
            case 'purgetimedlimits': return $this->PurgeTimedLimits($input);
            
            default: throw new UnknownActionException();
        }
        
        if (isset($oldauth)) $this->authenticator = $oldauth; else unset($this->authenticator);
    }
    
    /** Returns an ItemAccess authenticating the given file ID (or null to get from input), throws exceptions on failure */
    private function AuthenticateFileAccess(Input $input, ?string $id = null) : ItemAccess 
    {
        $id ??= $input->GetOptParam('file', SafeParam::TYPE_RANDSTR);
        return $this->AuthenticateItemAccess($input, File::class, $id);
    }
        
    /** Returns an ItemAccess authenticating the given file ID (or null to get from input), returns null on failure */
    private function TryAuthenticateFileAccess(Input $input, ?string $id = null) : ?ItemAccess 
    {
        $id ??= $input->GetOptParam('file', SafeParam::TYPE_RANDSTR);
        return $this->TryAuthenticateItemAccess($input, File::class, $id);
    }
            
    /** Returns an ItemAccess authenticating the given folder ID (or null to get from input), throws exceptions on failure */
    private function AuthenticateFolderAccess(Input $input, ?string $id = null) : ItemAccess 
    { 
        $id ??= $input->GetOptParam('folder', SafeParam::TYPE_RANDSTR);
        return $this->AuthenticateItemAccess($input, Folder::class, $id);
    }
        
    /** Returns an ItemAccess authenticating the given folder ID (or null to get from input), returns null on failure */
    private function TryAuthenticateFolderAccess(Input $input, ?string $id = null) : ?ItemAccess 
    {
        $id ??= $input->GetOptParam('folder', SafeParam::TYPE_RANDSTR);
        return $this->TryAuthenticateItemAccess($input, Folder::class, $id);
    }    
    
    /** Returns an ItemAccess authenticating the given item class/ID, throws exceptions on failure */
    private function AuthenticateItemAccess(Input $input, string $class, ?string $id) : ItemAccess 
    {
        $item = null; if ($id !== null)
        {
            $item = $class::TryLoadByID($this->database, $id);
            if ($item === null) throw new UnknownItemException();
        }
        
        $access = ItemAccess::Authenticate($this->database, $input, $this->authenticator, $item); 

        if (!is_a($access->GetItem(), $class)) throw new UnknownItemException();
        
        return $access;
    }
        
    /** Returns an ItemAccess authenticating the given item class/ID, returns null on failure */
    private function TryAuthenticateItemAccess(Input $input, string $class, ?string $id) : ?ItemAccess 
    {
        $item = null; if ($id !== null)
        {
            $item = $class::TryLoadByID($this->database, $id);
            if ($item === null) throw new UnknownItemException();
        }
        
        $access = ItemAccess::TryAuthenticate($this->database, $input, $this->authenticator, $item); 
        
        if ($access !== null && !is_a($access->GetItem(), $class)) return null;
        
        return $access;
    }
    
    /** Returns an ItemAccess authenticating the given object */
    private function AuthenticateItemObjAccess(Input $input, Item $item) : ItemAccess
    {
        return ItemAccess::Authenticate($this->database, $input, $this->authenticator, $item);
    }
    
    /** Returns an ItemAccess authenticating the given object, returns null on failure */
    private function TryAuthenticateItemObjAccess(Input $input, Item $item) : ?ItemAccess
    {
        return ItemAccess::TryAuthenticate($this->database, $input, $this->authenticator, $item);
    }

    /**
     * Installs the app by importing its SQL file and creating config
     * @throws UnknownActionException if config already exists
     */
    public function Install(Input $input) : void
    {
        if (isset($this->config)) throw new UnknownActionException();
        
        $this->database->importTemplate(ROOT."/apps/files");
        
        Config::Create($this->database)->Save();
    }        
    
    /**
     * Gets config for this app
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function GetConfig(Input $input) : array
    {
        $admin = $this->authenticator !== null && $this->authenticator->isAdmin();

        return $this->config->GetClientObject($admin);
    }
    
    /**
     * Sets config for this app
     * @throws AuthenticationFailedException if not admin
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function SetConfig(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequireAdmin();

        return $this->config->SetConfig($input)->GetClientObject(true);
    }
    
    /**
     * Uploads a new file to the given folder. Bandwidth is counted.
     * @throws AuthenticationFailedException if not signed in and public upload not allowed
     * @throws ItemAccessDeniedException if accessing via share and share does not allow upload
     * @return array File newly created file
     * @see File::GetClientObject()
     */
    protected function UploadFile(Input $input) : array
    {
        $account = ($this->authenticator === null) ? null : $this->authenticator->GetAccount();
        
        $access = $this->AuthenticateFolderAccess($input, $input->GetOptParam('parent',SafeParam::TYPE_RANDSTR));
        $parent = $access->GetItem(); $share = $access->GetShare();
        
        if (!$this->authenticator && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
        
        $overwrite = $input->GetOptParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        if ($share !== null && (!$share->CanUpload() || ($overwrite && !$share->CanModify()))) 
            throw new ItemAccessDeniedException();
        
        $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;
        
        $file = $input->GetFile('file');
        
        $parent->CountBandWidth(filesize($file->GetPath()));
        
        return File::Import($this->database, $parent, $owner, $file, $overwrite)->GetClientObject();
    }
    
    /**
     * Downloads a file or part of a file
     * 
     * Can accept an input byte range. Also accepts the HTTP_RANGE header.
     * @throws ItemAccessDeniedException if accessing via share and read is not allowed
     * @throws InvalidDLRangeException if the given byte range is invalid
     */
    protected function DownloadFile(Input $input) : void
    {
        // TODO CLIENT - since this is not AJAX, we might want to redirect to a page when doing a 404, etc. - better than plaintext
        
        $iface = $this->API->GetInterface();
        $oldmode = $iface->GetOutputMode();
        $iface->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        
        $access = $this->AuthenticateFileAccess($input); 
        $file = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();

        // first determine the byte range to read
        $fsize = $file->GetSize();
        $fstart = $input->GetNullParam('fstart',SafeParam::TYPE_INT) ?? 0;
        $flast  = $input->GetNullParam('flast',SafeParam::TYPE_INT) ?? $fsize-1;
        
        if (isset($_SERVER['HTTP_RANGE']))
        {
            $ranges = explode('=',$_SERVER['HTTP_RANGE']);
            if (count($ranges) != 2 || trim($ranges[0]) != "bytes")
                throw new InvalidDLRangeException();
            
            $ranges = explode('-',$ranges[1]);
            if (count($ranges) != 2) throw new InvalidDLRangeException();
            
            $fstart = intval($ranges[0]); 
            $flast2 = intval($ranges[1]); 
            if ($flast2) $flast = $flast2;
        }

        if ($fstart < 0 || $flast+1 < $fstart || $flast >= $fsize)
            throw new InvalidDLRangeException();
                
        // check required bandwidth ahead of time
        $length = $flast-$fstart+1;
        $file->CheckBandwidth($length);
        
        $fschunksize = $file->GetChunkSize();
        $chunksize = $this->config->GetRWChunkSize();
        
        $align = ($fschunksize !== null);        
        // transfer chunk size must be an integer multiple of the FS chunk size
        if ($align) $chunksize = ceil($chunksize/$fschunksize)*$fschunksize;
        
        $debugdl = ($input->GetOptParam('debugdl',SafeParam::TYPE_BOOL) ?? false) &&
            $this->API->GetDebugLevel() >= \Andromeda\Core\Config::LOG_DEVELOPMENT;
        
        $partial = $fstart != 0 || $flast != $fsize-1;

        // send necessary headers
        if (!$debugdl)
        {
            $iface->SetOutputMode(null);
            
            if ($partial)
            {
                http_response_code(206);
                header("Content-Range: bytes $fstart-$flast/$fsize");
            }
            
            header("Content-Length: $length");
            header("Accept-Ranges: bytes");
            header("Cache-Control: max-age=0");
            header("Content-Type: application/octet-stream");
            header('Content-Disposition: attachment; filename="'.$file->GetName().'"');
            header('Content-Transfer-Encoding: binary');
        }
        else $iface->SetOutputMode($oldmode);
        
        if (!$partial) $file->CountDownload((isset($share) && $share !== null));
        
        // register the data output to happen after the main commit so that we don't get to the
        // end of the download and then fail to insert a stats row and miss counting bandwidth
        $this->API->GetInterface()->RegisterOutputHandler(function(Output $output) 
            use($file,$fstart,$flast,$chunksize,$align,$debugdl)
        {            
            set_time_limit(0); ignore_user_abort(true);

            for ($byte = $fstart; $byte <= $flast; )
            {
                if (connection_aborted()) break;
                
                // the next read should begin on a chunk boundary if aligned
                if (!$align) $nbyte = $byte + $chunksize;
                else $nbyte = (intdiv($byte, $chunksize) + 1) * $chunksize;

                $rlen = min($nbyte - $byte, $flast - $byte + 1); 
                
                $data = $file->ReadBytes($byte, $rlen); 
                
                if (strlen($data) != $rlen)
                    throw new FileReadFailedException();

                $byte += $rlen; $file->CountBandwidth($rlen);
                
                if (!$debugdl) { echo $data; flush(); }
            }
        });
    }
    
    /**
     * Writes new data to an existing file - data is posted as a file
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws RandomWriteDisabledException if random write is not allowed on the file
     * @throws ItemAccessDeniedException if acessing via share and share doesn't allow modify
     * @throws InvalidFileWriteException if not exactly one file was posted
     * @throws InvalidFileRangeException if the given byte range is invalid
     * @return array File
     * @see File::GetClientObject()
     */
    protected function WriteToFile(Input $input) : array
    {
        $access = $this->AuthenticateFileAccess($input);
        $file = $access->GetItem(); $share = $access->GetShare();
        
        $account = $this->authenticator ? $this->authenticator->GetAccount() : null;
        
        if (!$account && !$file->GetAllowPublicModify())
            throw new AuthenticationFailedException();
            
        if (!$file->GetAllowRandomWrite($account))
            throw new RandomWriteDisabledException();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();        

        $filepath = $input->GetFile('data')->GetPath();
        
        $wstart = $input->GetNullParam('offset',SafeParam::TYPE_INT) ?? 0;
        $length = filesize($filepath); $wlast = $wstart + $length - 1;

        if ($wstart < 0 || $wlast+1 < $wstart)
            throw new InvalidFileRangeException();
        
        $file->CountBandwidth($length);
        
        if (!$wstart && $length >= $file->GetSize())
        {
            if ($share !== null && !$share->CanUpload()) throw new ItemAccessDeniedException();
            
            return $file->SetContents($filepath)->GetClientObject();
        }
        else
        {            
            $fschunksize = $file->GetChunkSize();
            $chunksize = $this->config->GetRWChunkSize();
            
            $align = ($fschunksize !== null);
            if ($align) $chunksize = ceil($chunksize/$fschunksize)*$fschunksize;
            
            $inhandle = fopen($filepath, 'rb');
            
            for ($wbyte = $wstart; $wbyte <= $wlast; )
            {
                $rstart = $wbyte - $wstart;
                
                // the next write should begin on a chunk boundary if aligned
                if (!$align) $nbyte = $wbyte + $chunksize;
                else $nbyte = (intdiv($wbyte, $chunksize) + 1) * $chunksize;
                
                $wlen = min($nbyte - $wbyte, $length - $rstart);
                
                fseek($inhandle, $rstart);
                $data = fread($inhandle, $wlen);
                
                if (strlen($data) != $wlen) throw new FileReadFailedException();
                
                $file->WriteBytes($wbyte, $data); $wbyte += $wlen;
            }
            
            return $file->GetClientObject();
        }
    }
    
    /**
     * Truncates (resizes a file)
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws RandomWriteDisabledException if random writes are not enabled on the file
     * @throws ItemAccessDeniedException if access via share and share does not allow modify
     * @throws InvalidFileRangeException if the given size is < 0
     * @return array File
     * @see File::GetClientObject()
     */
    protected function TruncateFile(Input $input) : array
    {   
        $access = $this->AuthenticateFileAccess($input);
        $file = $access->GetItem(); $share = $access->GetShare();

        $account = $this->authenticator ? $this->authenticator->GetAccount() : null;
        
        if (!$account && !$file->GetAllowPublicModify())
            throw new AuthenticationFailedException();
        
        if (!$file->GetAllowRandomWrite($account))
            throw new RandomWriteDisabledException();
            
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();

        $size = $input->GetParam('size',SafeParam::TYPE_INT);
        
        if ($size < 0) throw new InvalidFileRangeException();
        
        $file->SetSize($size);
        
        return $file->GetClientObject();
    }

    /**
     * Returns file metadata
     * @throws ItemAccessDeniedException if accessing via share and reading is not allowed
     * @return array File
     * @see File::GetClientObject()
     */
    protected function GetFileInfo(Input $input) : ?array
    {
        $access = $this->AuthenticateFileAccess($input);
        $file = $access->GetItem(); $share = $access->GetShare();

        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        
        $details = $input->GetOptParam('details',SafeParam::TYPE_BOOL) ?? false;
        
        return $file->GetClientObject($details);
    }

    /**
     * Lists folder metadata and optionally the items in a folder (or filesystem root)
     * @throws ItemAccessDeniedException if accessing via share and reading is not allowed
     * @throws AuthenticationFailedException if public access and no folder ID is given
     * @throws UnknownFilesystemException if the given filesystem does not exist
     * @throws UnknownFolderException if the given folder does not exist
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function GetFolder(Input $input) : ?array
    {
        if ($input->GetOptParam('folder',SafeParam::TYPE_RANDSTR))
        {
            $access = $this->AuthenticateFolderAccess($input);
            $folder = $access->GetItem(); $share = $access->GetShare();
            
            if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        }
        else
        {
            if ($this->authenticator === null) throw new AuthenticationFailedException();
            $account = $this->authenticator->GetAccount();
            
            $filesys = $input->GetOptParam('filesystem',SafeParam::TYPE_RANDSTR);
            if ($filesys !== null)
            {
                $filesys = FSManager::TryLoadByAccountAndID($this->database, $account, $filesys, true);  
                if ($filesys === null) throw new UnknownFilesystemException();
            }
                
            $folder = RootFolder::GetRootByAccountAndFS($this->database, $account, $filesys);
        }

        if ($folder === null) throw new UnknownFolderException();
        
        $files = $input->GetOptParam('files',SafeParam::TYPE_BOOL) ?? true;
        $folders = $input->GetOptParam('folders',SafeParam::TYPE_BOOL) ?? true;
        $recursive = $input->GetOptParam('recursive',SafeParam::TYPE_BOOL) ?? false;
        
        $limit = $input->GetNullParam('limit',SafeParam::TYPE_INT);
        $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
        $details = $input->GetOptParam('details',SafeParam::TYPE_BOOL) ?? false;
        
        $public = isset($share) && $share !== null;

        if ($public && ($files || $folders)) $folder->CountPublicVisit();
        
        return $folder->GetClientObject($files,$folders,$recursive,$limit,$offset,$details);
    }

    /**
     * Reads an item by a path (rather than by ID) - can specify a root folder
     * 
     * This is the primary helper routine for the FUSE client - the first
     * component of the path (if not using a root folder) is the filesystem name
     * @throws ItemAccessDeniedException if access via share and read is not allowed
     * @throws AuthenticationFailedException if public access and no root is given
     * @throws UnknownFilesystemException if the given filesystem is not found
     * @throws UnknownFolderException if the given folder is not found
     * @throws UnknownItemException if the given item path is invalid
     * @return array File|Folder with {isfile:bool}
     * @see File::GetClientObject()
     * @see Folder::GetClientObject()
     */
    protected function GetItemByPath(Input $input) : array
    {
        $path = array_filter(explode('/', $input->GetParam('path',SafeParam::TYPE_FSPATH)));
        
        if (($raccess = $this->TryAuthenticateFolderAccess($input)) !== null)
        {
            $folder = $raccess->GetItem(); $share = $raccess->GetShare();
            if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        }
        else // no root folder given
        {
            if ($this->authenticator === null) throw new AuthenticationFailedException();
            $account = $this->authenticator->GetAccount();
            
            if (!count($path)) 
            {
                $retval = RootFolder::GetSuperRootClientObject($this->database, $account);
                
                $retval['isfile'] = false; return $retval;
            }
            else
            {
                $filesystem = FSManager::TryLoadByAccountAndName($this->database, $account, array_shift($path));
                if ($filesystem === null) throw new UnknownFilesystemException();
                
                $folder = RootFolder::GetRootByAccountAndFS($this->database, $account, $filesystem);
            }           
        }        
        
        if ($folder === null) throw new UnknownFolderException();
        
        $name = array_pop($path);

        foreach ($path as $subfolder)
        {
            $subfolder = Folder::TryLoadByParentAndName($this->database, $folder, $subfolder);
            if ($subfolder === null) throw new UnknownFolderException(); else $folder = $subfolder;
        }
        
        $item = null; $isfile = $input->GetOptParam('isfile',SafeParam::TYPE_BOOL);
        
        if ($name === null) 
        {
            $item = ($isfile !== true) ? $folder : null; // trailing / for folder
        }
        else
        {
            if ($isfile === null || $isfile) $item = File::TryLoadByParentAndName($this->database, $folder, $name);
            if ($item === null && !$isfile)  $item = Folder::TryLoadByParentAndName($this->database, $folder, $name);
        }
        
        if ($item === null) throw new UnknownItemException();

        if ($item instanceof File) 
        {
            $retval = $item->GetClientObject();
        }
        else if ($item instanceof Folder)
        {
            if (isset($share) && $share !== null) $item->CountPublicVisit();
            $retval = $item->GetClientObject(true,true);
        }
        
        $retval['isfile'] = ($item instanceof File); return $retval;
    }
    
    /**
     * Edits file metadata
     * @see FilesApp::EditItemMeta()
     */
    protected function EditFileMeta(Input $input) : ?array
    {
        return $this->EditItemMeta($this->AuthenticateFileAccess($input), $input);
    }
    
    /**
     * Edits folder metadata
     * @see FilesApp::EditItemMeta()
     */
    protected function EditFolderMeta(Input $input) : ?array
    {
        return $this->EditItemMeta($this->AuthenticateFolderAccess($input), $input);
    }
    
    /**
     * Edits item metadata
     * @param ItemAccess $access access object for item
     * @throws ItemAccessDeniedException if accessing via share and can't modify
     * @return array|NULL Item
     * @see Item::GetClientObject()
     */
    private function EditItemMeta(ItemAccess $access, Input $input) : ?array
    {
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        if ($input->HasParam('description')) $item->SetDescription($input->GetNullParam('description',SafeParam::TYPE_TEXT));
        
        return $item->GetClientObject();
    }    
    
    /**
     * Takes ownership of a file
     * @return array File
     * @see File::GetClientObject()
     */
    protected function OwnFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetParam('file',SafeParam::TYPE_RANDSTR);
        $file = File::TryLoadByID($this->database, $id);
        if ($file === null) throw new UnknownItemException();
        
        if ($file->isWorldAccess() || $file->GetParent()->GetOwner() !== $account)
            throw new ItemAccessDeniedException();
            
        return $file->SetOwner($account)->GetClientObject();
    }
    
    /**
     * Takes ownership of a folder
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function OwnFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetParam('folder',SafeParam::TYPE_RANDSTR);
        $folder = Folder::TryLoadByID($this->database, $id);
        if ($folder === null) throw new UnknownItemException();
        
        if ($folder->isWorldAccess()) throw new ItemAccessDeniedException();
        
        $parent = $folder->GetParent();
        if ($parent === null || $parent->GetOwner() !== $account)
            throw new ItemAccessDeniedException();
            
        if ($input->GetOptParam('recursive',SafeParam::TYPE_BOOL))
        {
            $folder->SetOwnerRecursive($account);
        }
        else $folder->SetOwner($account);
        
        return $folder->SetOwner($account)->GetClientObject();
    }    

    /**
     * Creates a folder in the given parent
     * @throws AuthenticationFailedException if public access and public upload not allowed
     * @throws ItemAccessDeniedException if accessing via share and share upload not allowed
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function CreateFolder(Input $input) : array
    {
        $account = ($this->authenticator === null) ? null : $this->authenticator->GetAccount();
        
        $access = $this->AuthenticateFolderAccess($input, $input->GetOptParam('parent',SafeParam::TYPE_RANDSTR));
        $parent = $access->GetItem(); $share = $access->GetShare();
        
        if (!$this->authenticator && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
        
        if ($share !== null && !$share->CanUpload()) throw new ItemAccessDeniedException();

        $name = $input->GetParam('name',SafeParam::TYPE_FSNAME);
        
        $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;

        return SubFolder::Create($this->database, $parent, $owner, $name)->GetClientObject();
    }
    
    /**
     * Deletes a file
     * @see FilesApp::DeleteItem()
     */
    protected function DeleteFile(Input $input) : void
    {
        $this->DeleteItem(File::class, 'file', $input);
    }
    
    /**
     * Deletes a folder
     * @see FilesApp::DeleteItem()
     */
    protected function DeleteFolder(Input $input) : void
    {
        $this->DeleteItem(Folder::class, 'folder', $input);
    }
    
    /**
     * Deletes an item.
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws ItemAccessDeniedException if access via share and share modify is not allowed
     */
    private function DeleteItem(string $class, string $key, Input $input) : void
    {       
        $item = $input->GetParam($key,SafeParam::TYPE_RANDSTR);

        $access = static::AuthenticateItemAccess($input, $class, $item);
        $itemobj = $access->GetItem(); $share = $access->GetShare();
        
        if (!$this->authenticator && !$itemobj->GetAllowPublicModify())
            throw new AuthenticationFailedException();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();

        $itemobj->Delete();
    }
    
    /**
     * Renames (or copies) a file
     * @see FilesApp::RenameItem()
     * @return array File
     * @see File::GetClientObject()
     */
    protected function RenameFile(Input $input) : array
    {
        return $this->RenameItem(File::class, 'file', $input);
    }
    
    /**
     * Renames (or copies) a folder
     * @see FilesApp::RenameItem()
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function RenameFolder(Input $input) : array
    {
        return $this->RenameItem(Folder::class, 'folder', $input);
    }
    
    /**
     * Renames or copies an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @param string $keys input param for an array of items
     * @throws ItemAccessDeniedException if access via share and share upload/modify is not allowed
     * @throws AuthenticationFailedException if public access and public upload/modify is not allowed
     */
    private function RenameItem(string $class, string $key, Input $input) : array
    {
        $copy = $input->GetOptParam('copy',SafeParam::TYPE_BOOL) ?? false;

        $id = $input->GetParam($key, SafeParam::TYPE_RANDSTR);
        $access = static::AuthenticateItemAccess($input, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if (!$item->GetParentID()) throw new ItemAccessDeniedException();
        
        $name = $input->GetParam('name',SafeParam::TYPE_FSNAME);
        $overwrite = $input->GetOptParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        if ($copy)
        {
            $paccess = $this->AuthenticateItemObjAccess($input, $item->GetParent());
            
            $parent = $paccess->GetItem(); $pshare = $paccess->GetShare();
            
            if (!$this->authenticator && !$parent->GetAllowPublicUpload())
                throw new AuthenticationFailedException();
            
            if ($pshare !== null && !$pshare->CanUpload()) throw new ItemAccessDeniedException();           
            
            $account = ($this->authenticator === null) ? null : $this->authenticator->GetAccount();            
            
            $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;            
            
            $retval = $item->CopyToName($owner, $name, $overwrite);
        }
        else
        {
            if (!$this->authenticator && !$parent->GetAllowPublicModify())
                throw new AuthenticationFailedException();
            
            if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
            
            $retval = $item->SetName($name, $overwrite);
        }
        
        return $retval->GetClientObject();
    }
    
    /**
     * Moves (or copies) a file
     * @see FilesApp::MoveItem()
     * @return array File
     * @see File::GetClientObject()
     */
    protected function MoveFile(Input $input) : array
    {
        return $this->MoveItem(File::class, 'file', $input);
    }
    
    /**
     * Moves (or copies) a folder
     * @see FilesApp::MoveItem()
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function MoveFolder(Input $input) : array
    {
        return $this->MoveItem(Folder::class, 'folder', $input);
    }
    
    /**
     * Moves or copies an item.
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify/upload not allowed
     * @throws ItemAccessDeniedException if access via share and share modify/upload not allowed
     */
    private function MoveItem(string $class, string $key, string $keys, Input $input) : array
    {
        $copy = $input->GetOptParam('copy',SafeParam::TYPE_BOOL) ?? false;
        
        $item = $input->GetParam($key,SafeParam::TYPE_RANDSTR);
        
        $paccess = $this->AuthenticateFolderAccess($input, $input->GetOptParam('parent',SafeParam::TYPE_RANDSTR));
        $parent = $paccess->GetItem(); $pshare = $paccess->GetShare();
        
        if (!$this->authenticator && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
            
        if ($pshare !== null && !$pshare->CanUpload()) throw new ItemAccessDeniedException();
        
        $overwrite = $input->GetOptParam('overwrite',SafeParam::TYPE_BOOL) ?? false;        
        $account = ($this->authenticator === null) ? null : $this->authenticator->GetAccount();

        $access = static::AuthenticateItemAccess($input, $class, $item);
        $itemobj = $access->GetItem(); $share = $access->GetShare();
        
        if (!$copy && !$this->authenticator && !$itemobj->GetAllowPublicModify())
            throw new AuthenticationFailedException();
        
        if (!$copy && $share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();            
        
        if ($copy) $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;
        
        return ($copy ? $itemobj->CopyToParent($owner, $parent, $overwrite)
                      : $itemobj->SetParent($parent, $overwrite))->GetClientObject();
    }
    
    /** 
     * Likes or dislikes a file 
     * @see FilesApp::LikeItem()
     */
    protected function LikeFile(Input $input) : array
    {
        return $this->LikeItem(File::class, 'file', $input);
    }
    
    /** 
     * Likes or dislikes a folder
     * @see FilesApp::LikeItem()
     */
    protected function LikeFolder(Input $input) : array
    {
        return $this->LikeItem(Folder::class, 'folder', $input);
    }
    
    /**
     * Likes or dislikes an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws ItemAccessDeniedException if access via share if social is not allowed
     * @return array ?Like
     * @see Like::GetClientObject()
     */
    private function LikeItem(string $class, string $key, Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetParam($key, SafeParam::TYPE_RANDSTR);
        $access = static::AuthenticateItemAccess($input, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanSocial()) throw new ItemAccessDeniedException();
        
        $value = $input->GetOptParam('value',SafeParam::TYPE_BOOL);
        
        $like = Like::CreateOrUpdate($this->database, $account, $item, $value);
        return ($like !== null) ? $like->GetClientObject() : null;
    }
    
    /** 
     * Adds a tag to a file
     * @see FilesApp::TagItem()
     */
    protected function TagFile(Input $input) : array
    {
        return $this->TagItem(File::class, 'file', $input);
    }
    
    /** 
     * Adds a tag to a folder
     * @see FilesApp::TagItem() 
     */
    protected function TagFolder(Input $input) : array
    {
        return $this->TagItem(Folder::class, 'folder', $input);
    }
    
    /**
     * Adds a tag to an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws ItemAccessDeniedException if access via share and share modify is not allowed
     * @return array Tag
     * @see Tag::GetClientObject()
     */
    private function TagItem(string $class, string $key,  Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $tag = $input->GetParam('tag', SafeParam::TYPE_ALPHANUM, SafeParam::MaxLength(127));
        
        $item = $input->GetParam($key,SafeParam::TYPE_RANDSTR);

        $access = static::AuthenticateItemAccess($input, $class, $item);
        $itemobj = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        return Tag::Create($this->database, $account, $itemobj, $tag)->GetClientObject();
    }
    
    /**
     * Deletes an item tag
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the given tag is not found
     * @throws ItemAccessDeniedException if access via share and share modify is not allowed
     */
    protected function DeleteTag(Input $input) : void
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $id = $input->GetParam('tag', SafeParam::TYPE_RANDSTR);
        $tag = Tag::TryLoadByID($this->database, $id);
        if ($tag === null) throw new UnknownItemException();

        $item = $tag->GetItem(); $access = $this->AuthenticateItemObjAccess($input, $item);
        
        $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        $tag->Delete();
    }
    
    /**
     * Adds a comment to a file
     * @see FilesApp::CommentItem()
     */
    protected function CommentFile(Input $input) : array
    {
        return $this->CommentItem(File::class, 'file', $input);
    }
    
    /**
     * Adds a comment to a folder
     * @see FilesApp::CommentFolder()
     */
    protected function CommentFolder(Input $input) : array
    {
        return $this->CommentItem(Folder::class, 'folder', $input);
    }
    
    /**
     * Adds a comment to an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws ItemAccessDeniedException if access via share and share social is not allowed
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    private function CommentItem(string $class, string $key, Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetParam($key, SafeParam::TYPE_RANDSTR);
        $access = static::AuthenticateItemAccess($input, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanSocial()) throw new ItemAccessDeniedException();
        
        $comment = $input->GetParam('comment', SafeParam::TYPE_TEXT);       
        $cobj = Comment::Create($this->database, $account, $item, $comment);
        
        return $cobj->GetClientObject();
    }
    
    /**
     * Edits an existing comment properties
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the comment is not found
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    protected function EditComment(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
                
        $id = $input->GetParam('commentid',SafeParam::TYPE_RANDSTR);
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new UnknownItemException();
        
        $comment = $input->GetOptParam('comment', SafeParam::TYPE_TEXT);
        if ($comment !== null) $cobj->SetComment($comment);
        
        return $cobj->GetClientObject();
    }
    
    /**
     * Deletes a comment
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the comment is not found
     */
    protected function DeleteComment(Input $input) : void
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetParam('commentid',SafeParam::TYPE_RANDSTR);
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new UnknownItemException();
        
        $cobj->Delete();
    }
    
    /**
     * Returns comments on a file
     * @see FilesApp::GetItemComments()
     */
    protected function GetFileComments(Input $input) : array
    {
        return $this->GetItemComments($this->AuthenticateFileAccess($input), $input);
    }
    
    /**
     * Returns comments on a folder
     * @see FilesApp::GetItemComments()
     */
    protected function GetFolderComments(Input $input) : array
    {
        return $this->GetItemComments($this->AuthenticateFolderAccess($input), $input);
    }
    
    /**
     * Returns comments on an item
     * @param ItemAccess $access file or folder access object
     * @throws ItemAccessDeniedException if access via share and can't read
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    private function GetItemComments(ItemAccess $access, Input $input) : array
    {
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        
        $limit = $input->GetNullParam('limit',SafeParam::TYPE_INT);
        $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
        
        $comments = $item->GetComments($limit, $offset);

        return array_map(function(Comment $c){ return $c->GetClientObject(); }, $comments);
    }
    
    /**
     * Returns likes on a file
     * @see FilesApp::GetItemLikes()
     */
    protected function GetFileLikes(Input $input) : array
    {
        return $this->GetItemLikes($this->AuthenticateFileAccess($input), $input);
    }
    
    /**
     * Returns likes on a folder
     * @see FilesApp::GetItemLikes()
     */
    protected function GetFolderLikes(Input $input) : array
    {
        return $this->GetItemLikes($this->AuthenticateFolderAccess($input), $input);
    }
    
    /**
     * Returns likes on an item
     * @param ItemAccess $access file or folder access object
     * @throws ItemAccessDeniedException if access via share and can't read
     * @return array Like
     * @see Like::GetClientObject()
     */
    private function GetItemLikes(ItemAccess $access, Input $input) : array
    {
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        
        $limit = $input->GetNullParam('limit',SafeParam::TYPE_INT);
        $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
        
        $likes = $item->GetLikes($limit, $offset);
        
        return array_map(function(Like $c){ return $c->GetClientObject(); }, $likes);
    }
    
    /**
     * Creates shares for a file
     * @see FilesApp::ShareItem()
     */
    protected function ShareFile(Input $input) : array
    {
        return $this->ShareItem(File::class, 'file', $input);
    }
    
    /**
     * Creates shares for a folder
     * @see FilesApp::ShareItem()
     */
    protected function ShareFolder(Input $input) : array
    {
        return $this->ShareItem(Folder::class, 'folder', $input);
    }
    
    /**
     * Creates shares for an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify/upload not allowed
     * @throws UnknownDestinationException if the given share target is not found
     * @throws UnknownItemException if the given item to share is not found
     * @throws EmailShareDisabledException if emailing shares is not enabled
     * @throws ShareURLGenerateException if the URL to email could be not determined
     * @return array Share
     * @see Share::GetClientObject()
     */
    private function ShareItem(string $class, string $key, Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $item = $input->GetParam($key,SafeParam::TYPE_RANDSTR);
        
        $destacct = $input->GetOptParam('account',SafeParam::TYPE_RANDSTR);
        $destgroup = $input->GetOptParam('group',SafeParam::TYPE_RANDSTR);
        $everyone = $input->GetOptParam('everyone',SafeParam::TYPE_BOOL) ?? false;
        $islink = $input->GetOptParam('link',SafeParam::TYPE_BOOL) ?? false;
        
        if ($destgroup !== null && !$account->GetAllowGroupSearch())
            throw new ShareGroupDisabledException();
            
        $dest = null; if (!$islink)
        {
            if ($destacct !== null)       $dest = Account::TryLoadByID($this->database, $destacct);
            else if ($destgroup !== null) $dest = Group::TryLoadByID($this->database, $destgroup);
            if ($dest === null && !$everyone) throw new UnknownDestinationException();
        }

        $access = static::AuthenticateItemAccess($input, $class, $item);
        
        $oldshare = $access->GetShare(); $item = $access->GetItem();
        if ($oldshare !== null && !$oldshare->CanReshare())
            throw new ItemAccessDeniedException();
        
        if (!$item->GetAllowItemSharing($account))
            throw new ItemSharingDisabledException();
            
        if ($dest === null && !$item->GetAllowShareEveryone($account))
            throw new ShareEveryoneDisabledException();
        
        if ($islink) $share = Share::CreateLink($this->database, $account, $item);
        else $share = Share::Create($this->database, $account, $item, $dest);
        
        return $share->SetShareOptions($input, $oldshare);
        
        $shares = array($share); $retval = $share->GetClientObject(false, $islink);
        
        if ($islink && ($email = $input->GetOptParam('email',SafeParam::TYPE_EMAIL)) !== null)
        {
            if (!Limits\AccountTotal::LoadByAccount($this->database, $account, true)->GetAllowEmailShare())
                throw new EmailShareDisabledException();
            
            $account = $this->authenticator->GetAccount();
            $subject = $account->GetDisplayName()." shared files with you"; 
            
            $body = implode("<br />",array_map(function(Share $share)
            {                
                $url = $this->config->GetAPIUrl();
                if (!$url) throw new ShareURLGenerateException();
                
                $cmd = (new Input('files','download'))->AddParam('sid',$share->ID())->AddParam('skey',$share->GetAuthKey());
                
                return "<a href='".AJAX::GetRemoteURL($url, $cmd)."'>".$share->GetItem()->GetName()."</a>";
            }, $shares)); 
            
            // TODO CLIENT - param for the client to have the URL point at the client
            // TODO CLIENT - HTML - configure a directory where client templates reside

            $this->API->GetConfig()->GetMailer()->SendMail($subject, $body, true,
                array(new EmailRecipient($email)), $account->GetEmailFrom(), false);
        }
        
        return $retval;
    }    

    /**
     * Edits properties of an existing share
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the given share is not found
     * @throws ItemAccessDeniedException if not allowed
     * @return array Share
     * @see Share::GetClientObject()
     */
    protected function EditShare(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetOptParam('share',SafeParam::TYPE_RANDSTR);
        $share = Share::TryLoadByID($this->database, $id);
        if ($share === null) throw new UnknownItemException();        
        
        // allowed to edit the share if you have owner level access to the item, or own the share
        $origshare = $this->AuthenticateItemObjAccess($input, $share->GetItem())->GetShare();        
        if ($origshare !== null && $share->GetOwner() !== $account) throw new ItemAccessDeniedException();
        
        return $share->SetShareOptions($input, $origshare)->GetClientObject();
    }
    
    /**
     * Deletes an existing share
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the given share is not found
     * @throws ItemAccessDeniedException if not allowed
     */
    protected function DeleteShare(Input $input) : void
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetOptParam('share',SafeParam::TYPE_RANDSTR);
        $share = Share::TryLoadByID($this->database, $id);
        if ($share === null) throw new UnknownItemException();

        // if you don't own the share, you must have owner-level access to the item        
        if ($share->GetOwner() !== $account)
        {
            if ($this->AuthenticateItemObjAccess($input, $share->GetItem())->GetShare() !== null)
                throw new ItemAccessDeniedException();
        }
        
        $share->Delete();
    }
    
    /**
     * Retrieves metadata on a share object (from a link)
     * @return array Share
     * @see Share::GetClientObject()
     */
    protected function ShareInfo(Input $input) : array
    {
        $access = ItemAccess::Authenticate($this->database, $input, $this->authenticator);
        
        return $access->GetShare()->GetClientObject(true);
    }
    
    /**
     * Returns a list of shares
     * 
     * if $mine, show all shares we created, else show all shares we're the target of
     * @throws AuthenticationFailedException if not signed in
     * @return array [id:Share]
     * @see Share::GetClientObject()
     */
    protected function ListShares(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $mine = $input->GetOptParam('mine',SafeParam::TYPE_BOOL) ?? false;
        
        if ($mine) $shares = Share::LoadByAccountOwner($this->database, $account);
        else $shares = Share::LoadByAccountDest($this->database, $account);
        
        return array_map(function($share){ return $share->GetClientObject(true); }, $shares);
    }
    
    /**
     * Returns a list of all items where the user owns the item but not the parent
     * 
     * These are items that the user uploaded into someone else's folder, but owns
     * @throws AuthenticationFailedException if not signed in 
     * @return array `{files:[id:File],folders:[id:Folder]}`
     * @see File::GetClientObject()
     * @see Folder::GetClientObject()
     */
    protected function ListForeign(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $files = File::LoadForeignByOwner($this->database, $account);
        $folders = Folder::LoadForeignByOwner($this->database, $account);
        
        $files = array_map(function(File $file){ return $file->GetClientObject(); }, $files);
        $folders = array_map(function(Folder $folder){ return $folder->GetClientObject(); }, $folders);
        
        return array('files'=>$files, 'folders'=>$folders);
    }
    
    /**
     * Returns filesystem metadata (default if none specified)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownFilesystemException if no filesystem was specified or is the default
     * @return array FSManager
     * @see FSManager::GetClientObject()
     */
    protected function GetFilesystem(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        if (($filesystem = $input->GetOptParam('filesystem',SafeParam::TYPE_RANDSTR)) !== null)
        {
            $filesystem = FSManager::TryLoadByID($this->database, $filesystem);
        }
        else $filesystem = FSManager::LoadDefaultByAccount($this->database, $account);
        
        if ($filesystem === null) throw new UnknownFilesystemException();
        
        $isadmin = $this->authenticator->isAdmin() || $account === $filesystem->GetOwner();
        
        return $filesystem->GetClientObject($isadmin);
    }
    
    /**
     * Returns a list of all filesystems available
     * @throws AuthenticationFailedException if not signed in
     * @return array [id:FSManager]
     * @see FSManager::GetClientObject()
     */
    protected function GetFilesystems(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();

        if ($this->authenticator->isAdmin() && $input->GetOptParam('everyone',SafeParam::TYPE_BOOL))
        {
            $limit = $input->GetNullParam('limit',SafeParam::TYPE_INT);
            $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
            
            $filesystems = FSManager::LoadAll($this->database, $limit, $offset);
        }
        else $filesystems = FSManager::LoadByAccount($this->database, $account);
        
        return array_map(function($filesystem){ return $filesystem->GetClientObject(); }, $filesystems);
    }
    
    /**
     * Creates a new filesystem
     * @throws AuthenticationFailedException if not signed in
     * @throws UserStorageDisabledException if not admin and user storage is not allowed
     * @return array FSManager
     * @see FSManager::GetClientObject()
     */
    protected function CreateFilesystem(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        $isadmin = $this->authenticator->isAdmin();
        
        $global = ($input->GetOptParam('global', SafeParam::TYPE_BOOL) ?? false) && $isadmin;

        if (!Limits\AccountTotal::LoadByAccount($this->database, $account, true)->GetAllowUserStorage() && !$global)
            throw new UserStorageDisabledException();
            
        $filesystem = FSManager::Create($this->database, $input, $global ? null : $account);
        return $filesystem->GetClientObject(true);
    }

    /**
     * Edits an existing filesystem
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownFilesystemException if the given filesystem is not found
     * @return array FSManager
     * @see FSManager::GetClientObject()
     */
    protected function EditFilesystem(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $fsid = $input->GetParam('filesystem', SafeParam::TYPE_RANDSTR);
        
        if ($this->authenticator->isAdmin())
            $filesystem = FSManager::TryLoadByID($this->database, $fsid);
        else $filesystem = FSManager::TryLoadByAccountAndID($this->database, $account, $fsid);
        
        if ($filesystem === null) throw new UnknownFilesystemException();

        return $filesystem->Edit($input)->GetClientObject(true);
    }

    /**
     * Removes a filesystem (and potentially its content)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownFilesystemException if the given filesystem is not found
     */
    protected function DeleteFilesystem(Input $input) : void
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequirePassword();
        $account = $this->authenticator->GetAccount();
        
        $fsid = $input->GetParam('filesystem', SafeParam::TYPE_RANDSTR);
        
        if ($this->authenticator->isAdmin())
            $filesystem = FSManager::TryLoadByID($this->database, $fsid);
        else $filesystem = FSManager::TryLoadByAccountAndID($this->database, $account, $fsid);
        
        $unlink = $input->GetOptParam('unlink', SafeParam::TYPE_BOOL) ?? false;

        if ($filesystem === null) throw new UnknownFilesystemException();
        
        $filesystem->Delete($unlink);
    }
    
    /**
     * Common function for loading and authenticating the limited object and limit class referred to by input
     * @param bool $allowAuto if true, return the current account if no object is specified
     * @param bool $allowMany if true, allow selecting all of the given type
     * @param bool $timed if true, return a timed limit class (not total)
     * @throws UnknownGroupException if the given group is not found
     * @throws UnknownAccountException if the given account is not found
     * @throws UnknownFilesystemException if the given filesystem is not found
     * @throws UnknownObjectException if nothing valid was specified
     * @return array `{class:string, obj:object}`
     */
    private function GetLimitObject(Input $input, bool $allowAuto, bool $allowMany, bool $timed) : array
    {
        $obj = null;
        
        $admin = $this->authenticator->isAdmin();
        $account = $this->authenticator->GetAccount();

        if ($input->HasParam('group'))
        {
            if (($group = $input->GetNullParam('group',SafeParam::TYPE_RANDSTR)) !== null)
            {
                $obj = Group::TryLoadByID($this->database, $group);
                if ($obj === null) throw new UnknownGroupException();
            }
            
            $class = $timed ? Limits\GroupTimed::class : Limits\GroupTotal::class;
            
            if (!$admin) throw new UnknownGroupException();            
        }
        else if ($input->HasParam('account'))
        {
            if (($account = $input->GetNullParam('account',SafeParam::TYPE_RANDSTR)) !== null)
            {
                $obj = Account::TryLoadByID($this->database, $account);
                if ($obj === null) throw new UnknownAccountException();
            }
            
            $class = $timed ? Limits\AccountTimed::class : Limits\AccountTotal::class;
            
            if (!$admin && ($obj === null || $obj !== $account)) throw new UnknownAccountException();
        }
        else if ($input->HasParam('filesystem'))
        {
            if (($filesystem = $input->GetNullParam('filesystem',SafeParam::TYPE_RANDSTR)) !== null)
            {
                $obj = FSManager::TryLoadByID($this->database, $filesystem);
                if ($obj === null) throw new UnknownFilesystemException();
            }
            
            $class = $timed ? Limits\FilesystemTimed::class : Limits\FilesystemTotal::class;
            
            if (!$admin && ($obj === null || ($obj->GetOwnerID() !== null && $obj->GetOwnerID() !== $account->ID()))) throw new UnknownFilesystemException();
        }
        else if ($allowAuto) 
        {
            $obj = $this->authenticator->GetAccount(); 
            $class = $timed ? Limits\AccountTimed::class : Limits\AccountTotal::class;
        }
        else throw new UnknownObjectException();
        
        if (!$allowMany && $obj === null) throw new UnknownObjectException();
        
        return array('obj' => $obj, 'class' => $class);
    }
    
    /**
     * Loads the total limit object or objects for the given objects
     * @throws AuthenticationFailedException if not signed in
     * @return array|NULL Limit | [id:Limit]
     * @see Limits\Base::GetClientObject()
     */
    protected function GetLimits(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $isadmin = $this->authenticator->isAdmin();
        
        $obj = $this->GetLimitObject($input, true, true, false);
        $class = $obj['class']; $obj = $obj['obj'];
        
        if ($obj !== null)
        {
            $lim = $class::LoadByClient($this->database, $obj);
            return ($lim !== null) ? $lim->GetClientObject($isadmin) : null;
        }
        else
        {
            $count = $input->GetNullParam('limit',SafeParam::TYPE_INT);
            $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
            $lims = $class::LoadAll($this->database, $count, $offset);
            return array_map(function(Limits\Total $obj)use($isadmin){ 
                return $obj->GetClientObject($isadmin); },$lims);
        }
    }
    
    /**
     * Loads the timed limit object or objects for the given objects
     * @throws AuthenticationFailedException if not signed in
     * @return array|NULL Limit | [id:Limit]
     * @see Limits\Base::GetClientObject()
     */
    protected function GetTimedLimits(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $isadmin = $this->authenticator->isAdmin();
        
        $obj = $this->GetLimitObject($input, true, true, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        if ($obj !== null)
        {
            $lims = $class::LoadAllForClient($this->database, $obj);
        }
        else
        {
            $count = $input->GetNullParam('limit',SafeParam::TYPE_INT);
            $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
            $lims = $class::LoadAll($this->database, $count, $offset);
        }

        return array_map(function(Limits\Timed $lim)use($isadmin){ 
            return $lim->GetClientObject($isadmin); }, array_values($lims));
    }
    
    /**
     * Returns all stored time stats for an object
     * @throws AuthenticationFailedException if not signed in
     * @return array|NULL [id:TimedStats]
     * @see Limits\TimedStats::GetClientObject()
     */
    protected function GetTimedStatsFor(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $obj = $this->GetLimitObject($input, true, false, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        $period = $input->GetParam('timeperiod',SafeParam::TYPE_INT);
        $lim = $class::LoadByClientAndPeriod($this->database, $obj, $period);
        
        if ($lim === null) return null;

        $count = $input->GetNullParam('limit',SafeParam::TYPE_INT);
        $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
        
        return array_map(function(Limits\TimedStats $stats){ return $stats->GetClientObject(); },
            Limits\TimedStats::LoadAllByLimit($this->database, $lim, $count, $offset));        
    }
    
    /**
     * Returns timed stats for the given object or objects at the given time
     * @throws AuthenticationFailedException if not signed in
     * @return array|NULL TimedStats | [id:TimedStats]
     * @see Limits\TimedStats::GetClientObject()
     */
    protected function GetTimedStatsAt(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $period = $input->GetParam('timeperiod',SafeParam::TYPE_INT);
        $attime = $input->GetParam('matchtime',SafeParam::TYPE_INT);
        
        $obj = $this->GetLimitObject($input, false, true, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        if ($obj !== null)
        {
            $lim = $class::LoadByClientAndPeriod($this->database, $obj, $period);
            if ($lim === null) return null;
            
            $stats = Limits\TimedStats::LoadByLimitAtTime($this->database, $lim, $attime);
            return ($stats !== null) ? $stats->GetClientObject() : null;
        }
        else
        {
            $count = $input->GetNullParam('limit',SafeParam::TYPE_INT);
            $offset = $input->GetNullParam('offset',SafeParam::TYPE_INT);
            
            $retval = array(); 
            
            foreach ($class::LoadAllForPeriod($this->database, $period, $count, $offset) as $lim)
            {
                $stats = Limits\TimedStats::LoadByLimitAtTime($this->database, $lim, $attime);
                if ($stats !== null) $retval[$lim->GetLimitedObject()->ID()] = $stats->GetClientObject();
            }
            
            return $retval;
        }   
    }

    /**
     * Configures total limits for the given object
     * @throws AuthenticationFailedException if not admin
     * @return array Limits
     * @see Limits\Base::GetClientObject()
     */
    protected function ConfigLimits(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, false, false, false);
        $class = $obj['class']; $obj = $obj['obj'];
        
        return $class::ConfigLimits($this->database, $obj, $input)->GetClientObject();
    }    
    
    /**
     * Configures timed limits for the given object
     * @throws AuthenticationFailedException if not admin
     * @return array Limits
     * @see Limits\Base::GetClientObject()
     */
    protected function ConfigTimedLimits(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, false, false, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        return $class::ConfigLimits($this->database, $obj, $input)->GetClientObject();
    }
    
    /**
     * Deletes all total limits for the given object
     * @throws AuthenticationFailedException if not admin
     */
    protected function PurgeLimits(Input $input) : void
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, false, false, false);
        $class = $obj['class']; $obj = $obj['obj'];
        
        $class::DeleteByClient($this->database, $obj);
    }    
    
    /**
     * Deletes all timed limits for the given object
     * @throws AuthenticationFailedException if not admin
     */
    protected function PurgeTimedLimits(Input $input) : void
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, false, false, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        $period = $input->GetParam('period', SafeParam::TYPE_INT);
        $class::DeleteClientAndPeriod($this->database, $obj, $period);
    }
}
