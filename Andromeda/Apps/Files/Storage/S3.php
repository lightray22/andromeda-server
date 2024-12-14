<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Config;
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase};
use Andromeda\Core\Errors\ErrorManager;
use Andromeda\Core\IOFormat\{Input, SafeParams};

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Accounts/Crypto/FieldCrypt.php"); use Andromeda\Apps\Accounts\Crypto\OptFieldCrypt;

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/Apps/Files/Storage/Exceptions.php");
require_once(ROOT."/Apps/Files/Storage/FWrapper.php");
require_once(ROOT."/Apps/Files/Storage/Traits.php");

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ 
    if (!$init) S3::DecryptAccount($database, $account); });

abstract class S3Base extends FWrapper { use NoFolders; } // TODO does not need to be a trait?

/**
 * Allows using an S3-compatible server for backend storage
 * 
 * Uses fieldcrypt to allow encrypting the keys.
 */
class S3 extends S3Base
{
    use OptFieldCrypt;
    
    protected static function getEncryptedFields() : array { return array('accesskey','secretkey'); }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), self::GetFieldCryptFieldTemplate(), array(
            'endpoint' => new FieldTypes\StringType(),
            'path_style' => new FieldTypes\BoolType(),
            'port' => new FieldTypes\IntType(),
            'usetls' => new FieldTypes\BoolType(),
            'region' => new FieldTypes\StringType(),
            'bucket' => new FieldTypes\StringType(),
            'accesskey' => new FieldTypes\StringType(),
            'secretkey' => new FieldTypes\StringType()
        ));
    }
    
    /**
     * Returns a printable client object of this S3 storage
     * @return array<mixed> `{endpoint:string, path_style:?bool, port:?int, usetls:bool, \
           region:string, bucket:string, accesskey:string/bool, secretkey:bool}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        $accesskey = $this->isCryptoAvailable() ? $this->TryGetAccessKey() : (bool)($this->TryGetScalar('accesskey'));
        
        return parent::GetClientObject($activate) + $this->GetFieldCryptClientObject() + array(
            'endpoint' => $this->GetScalar('endpoint'),
            'path_style' => $this->TryGetScalar('path_style'),
            'port' => $this->TryGetScalar('port'),
            'usetls' => $this->getUseTLS(),
            'region' => $this->GetScalar('region'),
            'bucket' => $this->GetScalar('bucket'),
            'accesskey' => $accesskey,
            'secretkey' => (bool)($this->TryGetScalar('secretkey'))
        );
    }
    
    /** Returns the S3 bucket identifier */
    protected function GetBucket() : string { return $this->GetScalar('bucket'); }
    
    /** Returns the S3 access key (or null) */
    protected function TryGetAccessKey() : ?string { return $this->TryGetEncryptedScalar('accesskey'); }
    
    /** Returns the S3 secret key (or null) */
    protected function TryGetSecretKey() : ?string { return $this->TryGetEncryptedScalar('secretkey'); }
    
    /** Sets the S3 access key to the given value */
    protected function SetAccessKey(?string $key) : self { return $this->SetEncryptedScalar('accesskey',$key); }
    
    /** Sets the S3 secret key to the given value */
    protected function SetSecretKey(?string $key) : self { return $this->SetEncryptedScalar('secretkey',$key); }
    
    /** Returns true if the connection should use TLS */
    protected function getUseTLS() : bool { return (bool)($this->TryGetScalar('usetls') ?? true); }
    
    /** Returns the given path with no leading, trailing or duplicate / */
    protected static function cleanPath(string $path) : string { return implode('/',array_filter(explode('/',$path))); }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." ".self::GetFieldCryptCreateUsage().
        " --endpoint fspath --bucket alphanum --region alphanum [--path_style ?bool]".
        " [--port ?uint16] [--usetls ?bool] [--accesskey ?randstr] [--secretkey ?randstr]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        $params = $input->GetParams();
        
        return parent::Create($database, $input, $filesystem)->FieldCryptCreate($params)
            ->SetScalar('endpoint', self::cleanPath($params->GetParam('endpoint')->GetFSPath()))
            ->SetScalar('path_style', $params->GetOptParam('path_style',null)->GetNullBool())
            ->SetScalar('port', $params->GetOptParam('port',null)->GetNullUint16())
            ->SetScalar('usetls', $params->GetOptParam('usetls',null)->GetNullBool())
            ->SetScalar('region', $params->GetParam('region')->GetAlphanum())
            ->SetScalar('bucket', $params->GetParam('bucket')->GetAlphanum())
            ->SetAccessKey($params->GetOptParam('accesskey',null,SafeParams::PARAMLOG_NEVER)->GetNullRandstr())
            ->SetSecretKey($params->GetOptParam('secretkey',null,SafeParams::PARAMLOG_NEVER)->GetNullRandstr());
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." ".self::GetFieldCryptEditUsage().
        " [--endpoint fspath] [--bucket alphanum] [--region alphanum] [--path_style ?bool]".
        " [--port ?uint16] [--usetls ?bool] [--accesskey ?randstr] [--secretkey ?randstr]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('endpoint')) $this->SetScalar('endpoint', self::cleanPath($params->GetParam('endpoint')->GetFSPath()));
        
        if ($params->HasParam('region')) $this->SetScalar('region', $params->GetParam('region')->GetAlphanum());
        if ($params->HasParam('bucket')) $this->SetScalar('bucket', $params->GetParam('bucket')->GetAlphanum());
        
        if ($params->HasParam('port')) $this->SetScalar('port', $params->GetParam('port')->GetNullUint16());
        if ($params->HasParam('usetls')) $this->SetScalar('usetls', $params->GetParam('usetls')->GetNullBool());
        if ($params->HasParam('path_style')) $this->SetScalar('path_style', $params->GetParam('path_style')->GetNullBool());
        
        if ($params->HasParam('accesskey')) $this->SetScalar('accesskey', $params->GetParam('accesskey',SafeParams::PARAMLOG_NEVER)->GetNullRandstr());
        if ($params->HasParam('secretkey')) $this->SetScalar('secretkey', $params->GetParam('secretkey',SafeParams::PARAMLOG_NEVER)->GetNullRandstr());
        
        $this->FieldCryptEdit($params); 
        return parent::Edit($input);
    }
    
    /** s3 connection resource */ private $s3;
    
    /** The stream wrapper ID */ private string $streamID;
    
    /** Checks for the SMB client extension */
    public function PostConstruct() : void
    {
        if (!class_exists('\\Aws\\S3\\S3Client')) throw new S3AwsSdkException();
    }
    
    public function Activate() : self
    {
        if (isset($this->s3)) return $this;
        
        $params = array(
            'version' => '2006-03-01', 
            'region' => $this->GetScalar('region')    
        );
        
        $endpoint = $this->GetScalar('endpoint');        
        
        if (($port = $this->TryGetScalar('port')) !== null) 
        {
            $endpoint = explode('/', $endpoint);
            $endpoint[0] .= ":$port";
            $endpoint = implode('/', $endpoint);
        }
        
        $params['endpoint'] = $endpoint;
        
        $params['scheme'] = $this->getUseTLS() ? 'https' : 'http';
        
        if (($pathstyle = $this->TryGetScalar('path_style')) !== null)
            $params['use_path_style_endpoint'] = (bool)($pathstyle);
        
        $accesskey = $this->TryGetAccessKey();
        $secretkey = $this->TryGetSecretKey();
        
        if ($accesskey !== null || $secretkey !== null)
        {
            $params['credentials'] = array();
            if ($accesskey !== null) $params['credentials']['key'] = $accesskey;
            if ($secretkey !== null) $params['credentials']['secret'] = $secretkey;
        }

        $api = Main::GetInstance(); $debug = $api->GetDebugLevel();
        
        if ($debug >= Config::ERRLOG_SENSITIVE)
            $params['debug'] = array('logfn'=>function(string $str){
                ErrorManager::GetInstance()->LogDebugInfo("S3 SDK: $str"); });
                
        $this->s3 = new \Aws\S3\S3Client($params);
        
        $this->streamID = str_replace('_','-',$this->ID());        
        \Aws\S3\StreamWrapper::Register($this->s3, $this->streamID);
        
        if (!is_readable($this->GetFullURL()))
            throw new S3ConnectException();
        
        return $this;
    }
    
    protected function GetFullURL(string $path = "") : string
    {
        return $this->streamID."://".$this->GetBucket().'/'.$path;
    }
    
    /**
     * Runs an S3 client function
     * @param string $func name of S3Client function
     * @param array $params params to pass
     * @throws S3ErrorException if the SDK throws any S3Exception
     * @return \Aws\ResultInterface S3 result
     */
    protected function tryS3(string $func, array $params) : \Aws\ResultInterface
    {
        try { return $this->s3->$func($params); }
        catch (\Aws\S3\Exception\S3Exception $e) {
            throw S3ErrorException::Append($e); }
    }
    
    /**
     * Returns an array of the params used for all requests (bucket and key)
     * @param string $path key/path of object
     * @return array<mixed> `{Bucket:string,Key:string}`
     */
    protected function getStdParams(string $path) : array
    {
        return array('Bucket' => $this->GetBucket(), 'Key' => $path);
    }
    
    protected function assertReadable() : void
    {
        try { $this->ReadFolder(''); } 
        catch (FolderReadFailedException $e) { 
            throw TestReadFailedException::Copy($e); }
    }
    
    protected function assertWriteable() : void { $this->TestWriteable(); }

    public function ItemStat(string $path) : ItemStat
    {
        if ($this->isFolder($path)) return new ItemStat();
        
        else return parent::ItemStat($path);
    }
    
    protected function SubReadFolder(string $path = "") : array
    {
        if (!$this->isFolder($path))
            throw new FoldersUnsupportedException();
            
        else return parent::SubReadFolder('');
    } 
    
    protected function SubReadBytes(string $path, int $start, int $length) : string
    {
        // we completely ignore the FWrapper's read system because
        // the amazon SDK fread() does not allow us to seek ahead
        
        $this->ClosePath($path);
        
        $params = $this->getStdParams($path);
        
        $params['Range'] = "bytes=$start-".($start+$length-1);
        
        $result = $this->tryS3('getObject', $params);
        
        $data = (string)$result->get('Body');
        
        if (strlen($data) !== $length)
        {
            ErrorManager::GetInstance()->LogDebugInfo(array(
                'read'=>strlen($data), 'wanted'=>$length));
            
            throw new FileReadFailedException();
        }
        
        return $data;
    }
    
    protected static function supportsReadWrite() : bool { return false; }
    protected static function supportsSeekReuse() : bool { return false; }
    
    protected function OpenReadHandle(string $path){ throw new FileOpenFailedException(); }
    protected function OpenWriteHandle(string $path){  throw new FileOpenFailedException(); }
    
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        if (!$isWrite) throw new FileReadFailedException();
        
        if ($offset) throw new S3ModifyException();
        
        $handle = fopen($this->GetFullURL($path),'w');
        
        return new FileContext($handle, 0, true);
    }
    
    protected function SubCreateFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if ($this->isFile($path)) $this->SubDeleteFile($path);
        
        $this->GetContext($path, 0, true); return $this;
    }
    
    protected function SubTruncate(string $path, int $length) : self { throw new S3ModifyException(); }    
}
