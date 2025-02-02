<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\SafeParams;

/**
 * The base type that all limits inherit from.
 * 
 * Limits are a highly-configurable generic way to place restrictions on,
 * and gather statistics about, filesystem-related objects.  The object
 * that is the subject of the limits is referred to as the limited object.
 * 
 * The base class merely tracks some statistics (e.g. public download count).
 * 
 * While not currently implemented, this infrastructure could be extended
 * to, for example, set limits on individual files and folders also.
 */
abstract class Base extends BaseObject // TODO was StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_object' => new FieldTypes\ObjectPoly(BasedObject::class),
            'track_items' => new FieldTypes\IntType(),
            'track_dlstats' => new FieldTypes\IntType()
        ));
    }

    /** Initializes the limit by gathering statistics */
    protected abstract function Initialize() : self;    
    
    /** Returns the command usage for SetBaseLimits() */
    public abstract static function GetBaseUsage() : string;
    
    /**
     * Sets the properties that apply to Base limits
     *
     * This is abstract since different concrete limit types may use them differently.
     */
    protected abstract function SetBaseLimits(SafeParams $params) : void;
    
    /** Returns the command usage for BaseConfigLimits() */
    public abstract static function BaseConfigUsage() : string;
    
    /** Configures the common (at some level) limit properites for the given object with the given input */
    protected abstract static function BaseConfigLimits(ObjectDatabase $database, BaseObject $obj, SafeParams $params);
    
    /** Returns the object that is subject to the limits */
    public function GetLimitedObject() : BaseObject { return $this->GetObject('object'); }

    /** Returns true if we should track size, item count, and share count */
    protected function canTrackItems() : bool { return $this->TryGetFeatureBool('track_items') ?? false; }
    
    /** Returns true if we should count public downloads and bandwidth */
    protected function canTrackDLStats() : bool { return $this->TryGetFeatureBool('track_dlstats') ?? false; }

    /** Adds to the size counter, if item tracking is allowed */
    public function CountSize(int $delta, bool $noLimit = false) : self { return $this->canTrackItems() ? $this->DeltaCounter('size',$delta,$noLimit) : $this; }

    /** Increments the item counter, if item tracking is allowed. Decrements if not $count */
    public function CountItem(bool $count = true) : self { return $this->canTrackItems() ? $this->DeltaCounter('items',$count?1:-1) : $this; }
    
    /** Increments the item counter by the given value, if item tracking is allowed */
    public function CountItems(int $items, bool $noLimit = false) : self   { return $this->canTrackItems() ? $this->DeltaCounter('items',$items,$noLimit) : $this; }
    
    /** Increments the share counter, if item tracking is allowed. Decrements if not $count */
    public function CountShare(bool $count = true) : self { return $this->canTrackItems() ? $this->DeltaCounter('shares',$count?1:-1) : $this; }
    
    /** Increments the share counter by the given value, if item tracking is allowed */
    public function CountShares(int $shares, bool $noLimit = false) : self { return $this->canTrackItems() ? $this->DeltaCounter('shares',$shares,$noLimit) : $this; }
            
    /** Increments the public download counter, if download tracking is allowed */
    public function CountPublicDownload() : self { return $this->canTrackDLStats() ? $this->DeltaCounter('pubdownloads') : $this; }
    
    /** Increments the public download counter by the given value, if DL tracking is allowed */
    public function CountPublicDownloads(int $dls, bool $noLimit = false) : self { return $this->canTrackDLStats() ? $this->DeltaCounter('pubdownloads',$dls,$noLimit) : $this; }
        
    /** Adds to the bandwidth counter, if download tracking is allowed */
    public function CountBandwidth(int $delta, bool $noLimit = false) : self { return $this->canTrackDLStats() ? $this->DeltaCounter('bandwidth',$delta,$noLimit) : $this; }
                
    /** 
     * Checks if the given size delta would exceed the size limit 
     * @see StandardObject::CheckCounter()
     */
    public function CheckSize(int $delta) : void { $this->CheckCounter('size',$delta); }
    
    /**
     * Checks if the given bandwidth would exceed the limit
     * @see StandardObject::CheckCounter()
     */
    public function CheckBandwidth(int $delta) : void { $this->CheckCounter('bandwidth',$delta); }    
        
    /** Adds stats from the given file to this limit object */
    public function AddFileCounts(File $file, bool $add = true, bool $noLimit = false) : self
    {
        $mul = $add ? 1 : -1;
        
        if ($this->canTrackItems())
        {
            $this->CountItems($mul,$noLimit);
            
            $this->CountShares($mul*$file->GetNumShares(),$noLimit);
            
            if (!$file->onOwnerFS())
                $this->CountSize($mul*$file->GetSize(),$noLimit);
        }
        
        if ($this->canTrackDLStats())
        {
            $this->CountPublicDownloads($mul*$file->GetPublicDownloads(),$noLimit);
            $this->CountBandwidth($mul*$file->GetBandwidth(),$noLimit);
        }
            
        return $this;
    }
    
    /** Adds stats from the given folder to this limit object */
    public function AddFolderCounts(Folder $folder, bool $add = true, bool $noLimit = false) : self
    {
        $mul = $add ? 1 : -1;
        
        if ($this->canTrackItems())
        {
            $this->CountItems($mul,$noLimit);
            
            $this->CountShares($mul*$folder->GetNumShares(),$noLimit);    
        }
        
        return $this;
    }
    
    /** Adds cumulative stats from the given folder to this limit object */
    public function AddCumulativeFolderCounts(Folder $folder, bool $add = true, bool $noLimit = false) : self
    {
        $mul = $add ? 1 : -1;
        
        if ($this->canTrackItems())
        {
            $this->CountSize($mul*$folder->GetSize(),$noLimit);
            $this->CountItems($mul*$folder->GetNumItems(),$noLimit);
            $this->CountShares($mul*$folder->GetTotalShares(),$noLimit);
        }
        
        if ($this->canTrackDLStats())
        {
            $this->CountPublicDownloads($mul*$folder->GetPublicDownloads(),$noLimit);
            $this->CountBandwidth($mul*$folder->GetBandwidth(),$noLimit);
        }
        
        return $this;
    }
    
    /** Returns the public downloads counter for the limited object */
    protected function GetPublicDownloads() : int { return $this->GetCounter('pubdownloads'); }
    
    /** Returns the bandwidth counter for the limited object */
    protected function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    
    /** Returns the size counter for the limited object */
    protected function GetSize() : int   { return $this->GetCounter('size'); }
    
    /** Returns the item counter for the limited object */
    protected function GetItems() : int  { return $this->GetCounter('items'); }
    
    /** Returns the share counter for the limited object */
    protected function GetShares() : int { return $this->GetCounter('shares'); }  
    
    /** @return array<mixed> `{limited:id}` */
    public function GetClientObject(bool $full) : array
    {
        return array('limited' => $this->GetLimitedObject()->ID());
    }
    
    /** Returns the class of the limited object */
    protected abstract static function GetObjectClass() : string;

    public static function LoadAll(ObjectDatabase $database, ?int $limit = null, ?int $offset = null) : array
    {
        // since different limit types are stored in the same table, load rows matching the correct type of limited object
        $q = new QueryBuilder(); $w = $q->Like('obj_object','%'.FieldTypes\ObjectPoly::GetIDTypeDBValue("",static::GetObjectClass()),true);
        
        return static::LoadByQuery($database, $q->Where($w)->Limit($limit)->Offset($offset));
    }
}
