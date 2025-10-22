<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasApproval extends CommonDBTM {
    
    static $rightname = 'ticket';
    
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    
    public static function getTypeName($nb = 0) {
        return __('Approval', 'ideas');
    }
    
    public static function addApproval($tickets_id, $step_number, $groups_id, $users_id_validator, $status, $comment) {
        $approval = new self();
        
        $data = [
            'tickets_id' => $tickets_id,
            'step_number' => $step_number,
            'groups_id' => $groups_id,
            'users_id_validator' => $users_id_validator,
            'status' => $status,
            'comment' => substr($comment, 0, 400),
            'date_validation' => $_SESSION['glpi_currenttime'],
            'date_creation' => $_SESSION['glpi_currenttime']
        ];
        
        $result = $approval->add($data);
        
        if ($result) {
            PluginIdeasLog::logAction('approval_added', $users_id_validator, [
                'tickets_id' => $tickets_id,
                'step_number' => $step_number,
                'status' => $status
            ]);
        }
        
        return $result;
    }
    
    public static function getByTicket($tickets_id) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['tickets_id' => $tickets_id],
            'ORDER' => ['step_number ASC', 'date_creation DESC']
        ]);
        
        $approvals = [];
        foreach ($iterator as $data) {
            $user = new User();
            $group = new Group();
            
            if ($user->getFromDB($data['users_id_validator'])) {
                $data['validator_name'] = $user->getFriendlyName();
            }
            
            if ($group->getFromDB($data['groups_id'])) {
                $data['group_name'] = $group->fields['name'];
            }
            
            $approvals[] = $data;
        }
        
        return $approvals;
    }
    
    public static function getByStep($tickets_id, $step_number) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => [
                'tickets_id' => $tickets_id,
                'step_number' => $step_number
            ],
            'ORDER' => 'date_creation DESC'
        ]);
        
        $approvals = [];
        foreach ($iterator as $data) {
            $approvals[] = $data;
        }
        
        return $approvals;
    }
    
    public static function getCurrentStep($tickets_id) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['tickets_id' => $tickets_id],
            'ORDER' => 'step_number DESC',
            'LIMIT' => 1
        ]);
        
        if (count($iterator) > 0) {
            $data = $iterator->current();
            return (int)$data['step_number'];
        }
        
        return 1;
    }
}