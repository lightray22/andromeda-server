<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\TableTypes;
use Andromeda\Apps\Accounts\AuthActionLog;

/** Core app access log for use with the accounts app installed */
class ActionLog extends AuthActionLog
{
    use TableTypes\TableNoChildren;
    
    protected function CreateFields() : void
    {
        $this->RegisterFields(array(), self::class);
        
        parent::CreateFields();
    }
    
    /** Sets whether the action is being considered admin */
    public function SetAdmin(bool $isAdmin) : self
    {
        $this->admin->SetValue($isAdmin ? true : null); return $this;
    }
}
