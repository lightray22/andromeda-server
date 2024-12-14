<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Files/Limits/Total.php");
require_once(ROOT."/Apps/Files/Limits/Timed.php");

/** GroupTotal and AccountTotal have some extra common config not applicable to filesystems */
abstract class AuthEntityTotal extends Total
{
    public static function GetDBClass() : string { return self::class; }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'emailshare' => new FieldTypes\BoolType(),
            'userstorage' => new FieldTypes\BoolType()
        ));
    }

    public static function GetConfigUsage() : string { return static::GetBaseUsage()." [--emailshare ?bool] [--userstorage ?bool]"; }
    
    public static function BaseConfigLimits(ObjectDatabase $database, BaseObject $obj, SafeParams $params) : self
    {
        $lim = parent::BaseConfigLimits($database, $obj, $params);
        
        if ($params->HasParam('emailshare')) $lim->SetFeatureBool('emailshare', $params->GetParam('emailshare')->GetNullBool());
        if ($params->HasParam('userstorage')) $lim->SetFeatureBool('userstorage', $params->GetParam('userstorage')->GetNullBool());
        
        return $lim;
    }
}

abstract class AuthEntityTimed extends Timed
{
    // USAGE: -1 means keep forever, 0 means don't keep, null means no value/inherit, otherwise int for max age
    public static function GetTimedUsage() : string { return "[--max_stats_age ".static::MAX_AGE_FOREVER." (forever)|0 (none)|?int]"; }
    
    protected function SetTimedLimits(SafeParams $params) : void
    {
        if ($params->HasParam('max_stats_age')) 
        {
            $this->SetScalar('max_stats_age', $params->GetParam('max_stats_age')->GetNullInt());
        }
    }
}
