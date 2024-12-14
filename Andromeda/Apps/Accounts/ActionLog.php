<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\TableTypes;

/** Access log for the accounts app */
class ActionLog extends AuthActionLog
{
    use TableTypes\TableNoChildren;
    
    protected function CreateFields() : void
    {
        $this->RegisterFields(array(), self::class);
        
        parent::CreateFields();
    }
}
