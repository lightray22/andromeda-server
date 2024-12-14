<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

/** Class for parsing a version string into components */
class VersionInfo
{
    private string $version;
    
    private int $major;
    private int $minor;
    private ?int $patch;
    private ?string $extra;
    
    public function __construct(string $version)
    {
        $this->version = $version;
        
        $version = explode('-',$version,2);
        $this->extra = $version[1] ?? null;
        
        $version = explode('.',$version[0],3);
        
        foreach ($version as $v) if (!is_numeric($v)) 
            throw new Exceptions\InvalidVersionException('numeric digit');

        if (!isset($version[0]) || !isset($version[1]))
            throw new Exceptions\InvalidVersionException('empty major/minor');
        
        $this->major = (int)$version[0];
        $this->minor = (int)$version[1];
        
        if (isset($version[2])) 
            $this->patch = (int)$version[2];
        else $this->patch = null;
    }
    
    public function __toString() : string { return $this->version; }
    
    /** Returns the major version number */
    public function getMajor() : int { return $this->major; }
    /** Returns the minor version number */
    public function getMinor() : int { return $this->minor; }
    /** Returns the patch version number */
    public function getPatch() : ?int { return $this->patch; }
    /** Returns the extra version string if set */
    public function getExtra() : ?string { return $this->extra; }
    
    /** Returns the Major.Minor compatibility version string */
    public function getCompatVer() : string 
    { 
        return $this->major.'.'.$this->minor; 
    }
    
    /** @see VersionInfo::getCompatVer() */
    public static function toCompatVer(string $version) : string 
    {
        return (new self($version))->getCompatVer(); 
    }
}
