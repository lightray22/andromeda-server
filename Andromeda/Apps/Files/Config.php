<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseConfig, Utilities, VersionInfo};
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
 use Andromeda\Core\IOFormat\SafeParams;

/** App config stored in the database */
class Config extends BaseConfig
{
    public static function getAppname() : string { return 'files'; }
    
    public static function getVersion() : string {
        return VersionInfo::toCompatVer(andromeda_version); }
    
    use TableTypes\TableNoChildren;

    /** The URL of the client to use in links */
    private FieldTypes\NullStringType $apiurl;
    /** The file read/write chunk size */
    private FieldTypes\IntType $rwchunksize;
    /** The default crypto filesystem chunk size */
    private FieldTypes\IntType $crchunksize;
    /** The maximum allowed upload size (not enforceable) */
    private FieldTypes\NullIntType $upload_maxsize;
    /** True if timed stats as a whole is enabled */
    private FieldTypes\BoolType $timedstats;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->apiurl = $fields[] =         new FieldTypes\NullStringType('apiurl');
        $this->rwchunksize = $fields[] =    new FieldTypes\IntType('rwchunksize',false, 4*1024*1024); // 4M
        $this->crchunksize = $fields[] =    new FieldTypes\IntType('crchunksize',false, 1*1024*1024); // 1M
        $this->upload_maxsize = $fields[] = new FieldTypes\NullIntType('upload_maxsize');
        $this->timedstats = $fields[] =     new FieldTypes\BoolType('timedstats',false, false);
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** Creates a new Config singleton */
    public static function Create(ObjectDatabase $database) : static
    {
        return $database->CreateObject(static::class);
    }
    
    /** Returns the command usage for SetConfig() */
    public static function GetSetConfigUsage() : string { return 
        "[--rwchunksize uint32] [--crchunksize uint32] ".
        "[--upload_maxsize ?uint] [--timedstats bool] [--apiurl ?url]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(SafeParams $params) : self
    {
        if ($params->HasParam('apiurl')) 
            $this->apiurl->SetValue($params->GetParam('apiurl')->GetNullUTF8String()); // TODO add and use URL SafeParam?
        
        if ($params->HasParam('rwchunksize')) 
            $this->rwchunksize->SetValue($params->GetParam('rwchunksize')->GetUint32());
        
        if ($params->HasParam('crchunksize')) 
            $this->crchunksize->SetValue($params->GetParam('crchunksize')->GetUint32());
        
        if ($params->HasParam('upload_maxsize')) 
            $this->upload_maxsize->SetValue($params->GetParam('upload_maxsize')->GetNullUint());
        
        if ($params->HasParam('timedstats')) 
            $this->timedstats->SetValue($params->GetParam('timedstats')->GetBool());
        
        return $this;
    }

    /** Returns the block size that should be used for file reads and writes */
    public function GetRWChunkSize() : int { return $this->rwchunksize->GetValue(); }
    
    /** Returns the default block size for encrypted filesystems */
    public function GetCryptoChunkSize() : int { return $this->crchunksize->GetValue(); }
    
    /** Returns whether the timed-stats system as a whole is enabled */
    public function GetAllowTimedStats() : bool { return $this->timedstats->GetValue(); }
        
    /** Returns the URL this server API is accessible from over HTTP */
    public function GetAPIUrl() : ?string { return $this->apiurl->TryGetValue(); }
    
    /**
     * Return the maximum allowed upload size as min(php post_max, php upload_max, admin config)
     * @return ?int max upload size in bytes or null if none
     */
    public function GetMaxUploadSize() : ?int
    {
        $iniA = ini_get('post_max_size');
        $iniB = ini_get('upload_max_filesize');

        $maxes = array();
        $maxes[] = ($iniA !== false) ? Utilities::return_bytes($iniA) : 0;
        $maxes[] = ($iniA !== false) ? Utilities::return_bytes($iniA) : 0;
        $maxes[] = $this->upload_maxsize->TryGetValue() ?? 0;

        $maxes = array_filter($maxes, function(int $a){ return $a > 0; });
        return (count($maxes) === 0) ? null : min($maxes);
    }

    /**
     * Returns a printable client object for this config
     * @param bool $admin if true, show admin-only values
     * @return array<mixed> `{upload_maxbytes:?int, upload_maxfiles:int}` \
        if admin, add `{rwchunksize:int, crchunksize:int, upload_maxsize:?int, \
            date_created:float, apiurl:?string, config:{timedstats:bool}}`
     */
    public function GetClientObject(bool $admin = false) : array
    {
        $retval = array(
            'upload_maxbytes' => $this->GetMaxUploadSize(),
            'upload_maxfiles' => ini_get('max_file_uploads') // TODO this could be false, also cast to int?
        );
        
        if ($admin)
        {
            $retval['date_created'] = $this->date_created->GetValue();
            $retval['apiurl'] = $this->apiurl->TryGetValue();
            $retval['rwchunksize'] = $this->rwchunksize->GetValue();
            $retval['crchunksize'] = $this->crchunksize->GetValue();
            $retval['upload_maxsize'] = $this->upload_maxsize->TryGetValue();
            $retval['timedstats'] = $this->timedstats->GetValue();
        }
        
        return $retval;
    }
}
