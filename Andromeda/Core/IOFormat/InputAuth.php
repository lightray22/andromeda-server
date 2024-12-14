<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** A username and password combination */
class InputAuth 
{
    private string $username; 
    private string $password;
    
    public function __construct(string $username, string $password) 
    { 
        $this->username = $username; 
        $this->password = $password; 
    }
        
    public function GetUsername() : string { return $this->username; }
    public function GetPassword() : string { return $this->password; }
}
