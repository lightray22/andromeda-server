<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Files/Limits/Base.php");

/**
 * The basic kind of limit not subject to a timeperiod, also stores config
 *
 * Stores counters that are cumulative over all-time and allows limiting
 * those that make sense (bandwidth cannot decrease so that cannot be limited here).
 * Also allows storing config that is specific to the limited object.
 */
abstract class Total extends Base
{
    /** array<limited object ID, self] */
    protected static $cache = array();
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'date_download' => new FieldTypes\Timestamp(),
            'date_upload' => new FieldTypes\Timestamp(),
            'itemsharing' => new FieldTypes\BoolType(),
            'share2groups' => new FieldTypes\BoolType(),
            'share2everyone' => new FieldTypes\BoolType(),
            'publicupload' => new FieldTypes\BoolType(),
            'publicmodify' => new FieldTypes\BoolType(),
            'randomwrite' => new FieldTypes\BoolType(),
            'count_pubdownloads' => new FieldTypes\Counter(),
            'count_bandwidth' => new FieldTypes\Counter(true),
            'count_size' => new FieldTypes\Counter(),
            'count_items' => new FieldTypes\Counter(),
            'count_shares' => new FieldTypes\Counter(),
            'limit_size' => new FieldTypes\Limit(),
            'limit_items' => new FieldTypes\Limit(),
            'limit_shares' => new FieldTypes\Limit()
        ));
    }
    
    /** 
     * Loads the limit object corresponding to the given limited object 
     * @return static
     */
    public static function LoadByClient(ObjectDatabase $database, BaseObject $obj) : ?self
    {
        if (!array_key_exists($obj->ID(), static::$cache))
        {
            static::$cache[$obj->ID()] = static::TryLoadUniqueByObject($database, 'object', $obj, true);
        }
        
        return static::$cache[$obj->ID()];
    }
    
    /** Deletes the limit object corresponding to the given limited object */
    public static function DeleteByClient(ObjectDatabase $database, BaseObject $obj) : void
    {
        if (array_key_exists($obj->ID(), static::$cache)) static::$cache[$obj->ID()] = null;
        
        static::DeleteByObject($database, 'object', $obj, true);
    }

    /** Updates the last download timestamp to now */
    public function SetDownloadDate() : self { return $this->canTrackDLStats() ? $this->SetDate('download') : $this; }
    
    /** Updates the last upload (file create) timestamp to now */
    public function SetUploadDate() : self { return $this->canTrackDLStats() ? $this->SetDate('upload') : $this; }
    
    /** Returns true if the limited object should allow random writes to files */
    public function GetAllowRandomWrite() : ?bool { return $this->TryGetFeatureBool('randomwrite'); }
    
    /** Returns true if the limited object should allow public modification of files */
    public function GetAllowPublicModify() : ?bool { return $this->TryGetFeatureBool('publicmodify'); } 
    
    /** Returns true if the limited object should allow public upload to folders */
    public function GetAllowPublicUpload() : ?bool { return $this->TryGetFeatureBool('publicupload'); }
    
    /** Returns true if the limited object should allow sharing items */
    public function GetAllowItemSharing() : ?bool { return $this->TryGetFeatureBool('itemsharing'); }
    
    /** Returns true if the limited object should allow sharing to groups */
    public function GetAllowShareToGroups() : ?bool { return $this->TryGetFeatureBool('share2groups'); }
    
    /** Returns true if the limited object should allow sharing to everyone */
    public function GetAllowShareToEveryone() : ?bool { return $this->TryGetFeatureBool('share2everyone'); }
    
    /** 
     * Creates and caches a new limit object for the given limited object 
     * @return static
     */
    protected static function Create(ObjectDatabase $database, BaseObject $obj) : self
    {
        $newobj = static::BaseCreate($database)->SetObject('object',$obj);
        
        static::$cache[$obj->ID()] = $newobj; return $newobj;
    }
    
    public static function GetConfigUsage() : string { return static::GetBaseUsage(); }

    public static function BaseConfigUsage() : string { return "[--randomwrite ?bool] [--publicmodify ?bool] [--publicupload ?bool] ".
                                                               "[--itemsharing ?bool] [--share2groups ?bool] [--share2everyone ?bool] ".
                                                               "[--max_size ?uint] [--max_items ?uint32] [--max_shares ?uint32]"; }
        
    protected static function BaseConfigLimits(ObjectDatabase $database, BaseObject $obj, SafeParams $params) : self
    {
        $lim = static::LoadByClient($database, $obj) ?? static::Create($database, $obj);
        
        $lim->SetBaseLimits($params);
        
        if ($params->HasParam('randomwrite')) $lim->SetFeatureBool('randomwrite', $params->GetParam('randomwrite')->GetNullBool());
        if ($params->HasParam('publicmodify')) $lim->SetFeatureBool('publicmodify', $params->GetParam('publicmodify')->GetNullBool());
        if ($params->HasParam('publicupload')) $lim->SetFeatureBool('publicupload', $params->GetParam('publicupload')->GetNullBool());
        if ($params->HasParam('itemsharing')) $lim->SetFeatureBool('itemsharing', $params->GetParam('itemsharing')->GetNullBool());
        if ($params->HasParam('share2groups')) $lim->SetFeatureBool('share2groups', $params->GetParam('share2groups')->GetNullBool());
        if ($params->HasParam('share2everyone')) $lim->SetFeatureBool('share2everyone', $params->GetParam('share2everyone')->GetNullBool());
        
        if ($params->HasParam('max_size')) $lim->SetCounterLimit('size', $params->GetParam('max_size')->GetNullUint());
        if ($params->HasParam('max_items')) $lim->SetCounterLimit('items', $params->GetParam('max_items')->GetNullUint32());
        if ($params->HasParam('max_shares')) $lim->SetCounterLimit('shares', $params->GetParam('max_shares')->GetNullUint32());

        if ($lim->isCreated()) $lim->Initialize();
        
        return $lim;
    }
    
    /** Sets all stats counters to zero */
    protected function Initialize() : self
    {
        foreach (array('items','size','shares','pubdownloads','bandwidth') as $prop) 
            $this->DeltaCounter($prop, $this->GetCounter($prop)*-1);
        
        if (!$this->canTrackDLStats())
        {
            foreach (array('upload','download') as $prop) 
                $this->SetDate($prop, null);
        }
        
        return $this;
    }
    
    /**
     * Returns a printable client object of this timed limit
     * @param bool $full if false, show config only (no dates,counters,limits)
     * @return array<mixed> `{dates:{created:float, download:?float, upload:?float}, config:{itemsharing:?bool, \
        share2everyone:?bool, share2groups:?bool, publicupload:?bool, publicmodify:?bool, randomwrite:?bool},
        limits:{size:?int, items:?int, shares:?int}, counters:{size:int, items:int, shares:int, pubdownloads:int, bandwidth:int}}`
     * @see Base::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $retval = parent::GetClientObject($full) + array(
            'config' => Utilities::array_map_keys(function($p){ return $this->TryGetFeatureBool($p); },
                array('itemsharing','share2everyone','share2groups',
                      'publicupload','publicmodify','randomwrite'))
        );
        
        if ($full)
        {
            $retval['dates'] = array(
                'created' => $this->GetDateCreated(),
                'download' => $this->TryGetDate('download'),
                'upload' => $this->TryGetDate('upload')
            );
            
            $retval['counters'] = Utilities::array_map_keys(function($p){ return $this->GetCounter($p); },
                array('size','items','shares','pubdownloads','bandwidth'));
            
            $retval['limits'] = Utilities::array_map_keys(function($p){ return $this->TryGetCounterLimit($p); },
                array('size','items','shares'));
        }
        
        return $retval;
    }
}
