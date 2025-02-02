<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Config;
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\{Input, SafeParams};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\CryptFields;

/**
 * Allows using an S3-compatible server for backend storage
 * 
 * Uses credcrypt to allow encrypting the keys.
 */
class S3 extends FWrapper
{
    use TableTypes\TableNoChildren;

    /** The endpoint of the S3 connection */
    protected FieldTypes\StringType $endpoint;
    /** Whether or not to use path_style_endpoint */
    protected FieldTypes\NullBoolType $path_style;
    /** The port number to use for the connection */
    protected FieldTypes\NullIntType $port;
    /** Whether or not to connect with TLS */
    protected FieldTypes\NullBoolType $usetls;
    /** The region string of the S3 connection */
    protected FieldTypes\StringType $region;
    /** The bucket string of the S3 connection */
    protected FieldTypes\StringType $bucket;
    /** The access key to use for the connection */
    protected CryptFields\NullCryptStringType $accesskey;
    protected FieldTypes\NullStringType $accesskey_nonce;
    /** The secret key to use for the connection */
    protected CryptFields\NullCryptStringType $secretkey;
    protected FieldTypes\NullStringType $secretkey_nonce;

    protected function CreateFields() : void
    {
        $fields = array();

        $this->endpoint = new FieldTypes\StringType('endpoint');
        $this->path_style = new FieldTypes\NullBoolType('path_style');
        $this->port = new FieldTypes\NullIntType('port');
        $this->usetls = new FieldTypes\NullBoolType('usetls');
        $this->region = new FieldTypes\StringType('region');
        $this->bucket = new FieldTypes\StringType('bucket');
        
        $this->accesskey_nonce = new FieldTypes\NullStringType('accesskey_nonce');
        $this->accesskey = new CryptFields\NullCryptStringType('accesskey',$this->owner,$this->accesskey_nonce);
        $this->secretkey_nonce = new FieldTypes\NullStringType('secretkey_nonce');
        $this->secretkey = new CryptFields\NullCryptStringType('secretkey',$this->owner,$this->secretkey_nonce);

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /** @return list<CryptFields\CryptField> */
    protected function GetCryptFields() : array { 
        return array($this->accesskey, $this->secretkey); }

    /**
     * Returns a printable client object of this S3 storage
     * @return array{id:string} `{endpoint:string, path_style:?bool, port:?int, usetls:bool, \
           region:string, bucket:string, accesskey:string/bool, secretkey:bool}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array // TODO RAY !!
    {
        //$accesskey = $this->isCryptoAvailable() ? $this->TryGetAccessKey() : (bool)($this->TryGetScalar('accesskey'));
        
        return parent::GetClientObject($activate) /*+ $this->GetFieldCryptClientObject() + array(
            'endpoint' => $this->GetScalar('endpoint'),
            'path_style' => $this->TryGetScalar('path_style'),
            'port' => $this->TryGetScalar('port'),
            'usetls' => $this->getUseTLS(),
            'region' => $this->GetScalar('region'),
            'bucket' => $this->GetScalar('bucket'),
            'accesskey' => $accesskey,
            'secretkey' => (bool)($this->TryGetScalar('secretkey'))
        )*/;
    }
    
    public static function GetCreateUsage() : string { return
        " --endpoint fspath --bucket alphanum --region alphanum [--path_style ?bool]".
        " [--port ?uint16] [--usetls ?bool] [--accesskey ?randstr] [--secretkey ?randstr]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : self
    {
        $params = $input->GetParams();
        $obj = parent::Create($database, $input, $owner);
        
        $obj->endpoint->SetValue(self::cleanPath($params->GetParam('endpoint')->GetFSPath()));
        $obj->path_style->SetValue($params->GetOptParam('path_style',null)->GetNullBool());
        $obj->port->SetValue($params->GetOptParam('port',null)->GetNullUint16());
        $obj->usetls->SetValue($params->GetOptParam('usetls',null)->GetNullBool());
        $obj->region->SetValue($params->GetParam('region')->GetAlphanum());
        $obj->bucket->SetValue($params->GetParam('bucket')->GetAlphanum());
        $obj->accesskey->SetValue($params->GetOptParam('accesskey',null,SafeParams::PARAMLOG_NEVER)->GetNullRandstr());
        $obj->secretkey->SetValue($params->GetOptParam('secretkey',null,SafeParams::PARAMLOG_NEVER)->GetNullRandstr());

        return $obj;
    }
    
    public static function GetEditUsage() : string { return
        " [--endpoint fspath] [--bucket alphanum] [--region alphanum] [--path_style ?bool]".
        " [--port ?uint16] [--usetls ?bool] [--accesskey ?randstr] [--secretkey ?randstr]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('endpoint'))
            $this->endpoint->SetValue(self::cleanPath($params->GetParam('endpoint')->GetFSPath()));
        if ($params->HasParam('path_style'))
            $this->path_style->SetValue($params->GetParam('path_style')->GetNullBool());
        if ($params->HasParam('port'))
            $this->port->SetValue($params->GetParam('port')->GetNullUint16());
        if ($params->HasParam('usetls'))
            $this->usetls->SetValue($params->GetParam('usetls')->GetNullBool());
        
        if ($params->HasParam('region'))
            $this->region->SetValue($params->GetParam('region')->GetAlphanum());
        if ($params->HasParam('bucket'))
            $this->bucket->SetValue($params->GetParam('bucket')->GetAlphanum());
        
        if ($params->HasParam('accesskey'))
            $this->accesskey->SetValue($params->GetParam('accesskey',SafeParams::PARAMLOG_NEVER)->GetNullRandstr());
        if ($params->HasParam('secretkey'))
            $this->secretkey->SetValue($params->GetParam('secretkey',SafeParams::PARAMLOG_NEVER)->GetNullRandstr());
        
        return parent::Edit($input);
    }
    
    /** Checks for the SMB client extension */
    public function PostConstruct(bool $created) : void
    {
        if (!class_exists('\\Aws\\S3\\S3Client'))
            throw new Exceptions\S3AwsSdkException();
    }
    
    private ?\Aws\S3\S3Client $s3client;
    private ?string $streamID;
    
    public function Activate() : self { $this->GetClient(); return $this; }

    /** @return \Aws\S3\S3Client */
    protected function GetClient() : \Aws\S3\S3Client
    {
        if ($this->s3client !== null) return $this->s3client;
        
        $params = array(
            'version' => '2006-03-01', 
            'region' => $this->region->GetValue()    
        );
        
        $endpoint = $this->endpoint->GetValue();
        if (($port = $this->port->TryGetValue()) !== null) 
        {
            $endpoint = explode('/', $endpoint);
            $endpoint[0] .= ":$port";
            $endpoint = implode('/', $endpoint);
        }
        
        $params['endpoint'] = $endpoint;
        $params['scheme'] = ($this->usetls->TryGetValue() ?? true) ? 'https' : 'http';
        if (($pathstyle = $this->path_style->TryGetValue()) !== null)
            $params['use_path_style_endpoint'] = $pathstyle;
        
        $accesskey = $this->accesskey->TryGetValue();
        $secretkey = $this->secretkey->TryGetValue();
        if ($accesskey !== null || $secretkey !== null)
        {
            $params['credentials'] = array();
            if ($accesskey !== null) $params['credentials']['key'] = $accesskey;
            if ($secretkey !== null) $params['credentials']['secret'] = $secretkey;
        }

        $api = $this->GetApiPackage();
        if ($api->GetDebugLevel() >= Config::ERRLOG_SENSITIVE)
            $params['debug'] = array('logfn'=>function(string $str)use($api){
                $api->GetErrorManager()->LogDebugHint("S3 SDK: $str"); });

        $this->s3client = new \Aws\S3\S3Client($params);
        if (!is_readable($this->GetFullURL()))
            throw new Exceptions\S3ConnectException();
        return $this->s3client;
    }

    protected function GetStreamID() : string
    {
        if ($this->streamID !== null) return $this->streamID;
        
        $this->streamID = str_replace('_','-',$this->ID());        
        \Aws\S3\StreamWrapper::register($this->GetClient(), $this->streamID);
        return $this->streamID;
    }

    protected function GetFullURL(string $path = "") : string
    {
        return $this->GetStreamID()."://".$this->bucket->GetValue().'/'.$path;
    }
    
    /**
     * Returns an array of the params used for all requests (bucket and key)
     * @param string $path key/path of object
     * @return array{Bucket:string,Key:string}`
     */
    protected function getStdParams(string $path) : array
    {
        return array('Bucket'=>$this->bucket->GetValue(), 'Key'=>$path);
    }
    
    public function supportsFolders() : bool { return false; }  

    public function isFolder(string $path) : bool
    {
        return count(array_filter(explode('/',$path))) === 0; // root only
    }
    
    protected function assertReadable() : void
    {
        try { $this->ReadFolder(''); } 
        catch (Exceptions\FolderReadFailedException $e) { 
            throw new Exceptions\TestReadFailedException($e); }
    }
    
    protected function assertWriteable() : void { $this->TestWriteable(); }

    public function ItemStat(string $path) : ItemStat
    {
        if ($this->isFolder($path))
            return new ItemStat(); // root
        
        else return parent::ItemStat($path);
    }
    
    protected function SubReadFolder(string $path = "") : array
    {
        if (!$this->isFolder($path))
            throw new Exceptions\FoldersUnsupportedException();
            
        else return parent::SubReadFolder(""); // root
    } 
    
    protected function SubCreateFolder(string $path) : Storage { 
        throw new Exceptions\FoldersUnsupportedException(); }

    protected function SubCreateFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if ($this->isFile($path))
            $this->SubDeleteFile($path);
        
        $this->GetContext($path, 0, true); return $this;
    }
    
    protected function SubReadBytes(string $path, int $start, int $length) : string
    {
        // we completely ignore the FWrapper's read system because
        // the amazon SDK fread() does not allow us to seek ahead
        
        $this->ClosePath($path);
        
        $params = $this->getStdParams($path);
        $params['Range'] = "bytes=$start-".($start+$length-1);
        
        try { $result = $this->GetClient()->getObject($params); }
        catch (\Aws\S3\Exception\S3Exception $e) {
            throw new Exceptions\S3ErrorException($e); }

        if (!$result->hasKey('body') || !is_string($data = $result->get('Body')))
            throw new Exceptions\S3ErrorException();
        
        if (strlen($data) !== $length)
        {
            throw new Exceptions\FileReadFailedException(
                "read ".strlen($data).", wanted $length");
        }
        
        return $data;
    }
    
    protected function SubTruncate(string $path, int $length) : self { 
        throw new Exceptions\S3ModifyException(); }  

    protected function SubDeleteFolder(string $path) : Storage { 
        throw new Exceptions\FoldersUnsupportedException(); }

    protected function SubRenameFolder(string $old, string $new) : Storage { 
        throw new Exceptions\FoldersUnsupportedException(); }
    
    protected function SubMoveFile(string $old, string $new) : Storage { 
        throw new Exceptions\FoldersUnsupportedException(); }
    
    protected function SubMoveFolder(string $old, string $new) : Storage { 
        throw new Exceptions\FoldersUnsupportedException(); }

    protected static function supportsReadWrite() : bool { return false; }
    protected static function supportsSeekReuse() : bool { return false; }
    
    protected function OpenReadHandle(string $path){ throw new Exceptions\FileOpenFailedException(); }
    protected function OpenWriteHandle(string $path){  throw new Exceptions\FileOpenFailedException(); }
    
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        if (!$isWrite) throw new Exceptions\FileReadFailedException();
        if ($offset !== 0) throw new Exceptions\S3ModifyException();
        
        if (!is_resource($handle = fopen($this->GetFullURL($path),'w')))
            throw new Exceptions\FileReadFailedException();
        return new FileContext($handle, 0, true);
    }
}
