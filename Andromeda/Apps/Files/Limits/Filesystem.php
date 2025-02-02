<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\IOFormat\SafeParams;

/** Filesystem limits common between total and timed */
trait FilesystemCommon
{
    protected static function GetObjectClass() : string { return FSManager::class; }
    
    /** Returns the limited filesystem */
    protected function GetFilesystem() : FSManager { return $this->GetObject('object'); }    
    
    public static function GetBaseUsage() : string { return "[--track_items bool] [--track_dlstats bool]"; }
    
    protected function SetBaseLimits(SafeParams $params) : void
    {
        if ($params->HasParam('track_items') || $this->isCreated())
        {
            $this->SetFeatureBool('track_items', $params->GetParam('track_items')->GetBool());
            
            if ($this->isFeatureModified('track_items')) $init = true;
        }
        
        if ($params->HasParam('track_dlstats') || $this->isCreated())
        {
            $this->SetFeatureBool('track_dlstats', $params->GetParam('track_dlstats')->GetBool());
            
            if ($this->isFeatureModified('track_dlstats')) $init = true;
        }
        
        if ($init ?? false) $this->Initialize();
    }
        
    public static function ConfigLimits(ObjectDatabase $database, FSManager $filesystem, SafeParams $params) : self
    {
        return static::BaseConfigLimits($database, $filesystem, $params);
    }
    
    /**
     * @param bool $full if false, don't show track_items/track_dlstats
     * @return array<mixed> `config:{track_items:bool,track_dlstats:bool}`
     * @see Total::GetClientObject()
     * @see Timed::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $data = parent::GetClientObject($full);
        
        if ($full)
        {
            $data['config']['track_items'] = $this->GetFeatureBool('track_items');
            $data['config']['track_dlstats'] = $this->GetFeatureBool('track_dlstats');
        }
        
        return $data;
    }
}

/** Concrete class providing filesystem config and total stats */
class FilesystemTotal extends Total
{ 
    use FilesystemCommon; 

    /** Loads the total limit object for the given filesystem (or null if none exists) */
    public static function LoadByFilesystem(ObjectDatabase $database, FSManager $filesystem) : ?self
    {
        return static::LoadByClient($database, $filesystem);
    }
    
    /** Initializes the FS total stats by adding stats from all root folders */
    protected function Initialize() : self
    {
        parent::Initialize();
        
        if (!$this->canTrackItems()) return $this;
        
        $roots = RootFolder::LoadRootsByFSManager($this->database, $this->GetFilesystem());
        
        foreach ($roots as $root) $this->AddCumulativeFolderCounts($root,true);
        
        return $this;
    }
}

/** Concrete class providing timed filesystem limits */
class FilesystemTimed extends Timed 
{ 
    use FilesystemCommon; 

    /**
     * Loads all timed limits for the given filesystem
     * @param ObjectDatabase $database database reference
     * @param FSManager $filesystem filesystem of interest
     * @return array<string, FilesystemTimed> timed limits
     */
    public static function LoadAllForFilesystem(ObjectDatabase $database, FSManager $filesystem) : array
    {
        return static::LoadAllForClient($database, $filesystem);
    }    
    
    public static function GetTimedUsage() : string { return "[--max_stats_age ".static::MAX_AGE_FOREVER." (forever)|0 (none)|int]"; }
    
    protected function SetTimedLimits(SafeParams $params) : void
    {
        if ($params->HasParam('max_stats_age')) 
        {
            $this->SetScalar('max_stats_age', $params->GetParam('max_stats_age')->GetInt());
        }
    }
}

