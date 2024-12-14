<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

/** A name and address pair email recipient */
class EmailRecipient
{
    private ?string $name; 
    private string $address;
    
    /** Returns the name of the recipient, if set */
    public function TryGetName() : ?string { return $this->name; }
    
    /** Returns the email address of the recipient */
    public function GetAddress() : string { return $this->address; }
    
    public function __construct(string $address, ?string $name = null) 
    {
        $this->address = $address; 
        $this->name = $name; 
    }
        
    /** Returns the format $addr or "$name <$addr>" */
    public function ToString() : string
    {
        $addr = $this->GetAddress(); 
        $name = $this->TryGetName();
        
        if ($name === null) return $addr;
        else return "$name <$addr>";
    }
}
