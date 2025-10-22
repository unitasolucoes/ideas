<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasPointsHistory extends CommonDBTM {
    
    static $rightname = 'ticket';
    
    public static function getTypeName($nb = 0) {
        return __('Points History', 'ideas');
    }
    
    public static function record(array $data) {
        $history = new self();

        if (!isset($data['date_creation'])) {
            $data['date_creation'] = $_SESSION['glpi_currenttime'];
        }

        return $history->add($data);
    }

    public static function hasRecord($users_id, $action_type, $reference_id = 0) {
        global $DB;

        if ($users_id <= 0 || empty($action_type)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => [
                'users_id' => $users_id,
                'action_type' => $action_type,
                'reference_id' => $reference_id
            ],
            'LIMIT' => 1
        ]);

        return count($iterator) > 0;
    }
    
    public static function getByUser($users_id, $limit = 50) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['users_id' => $users_id],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => $limit
        ]);
        
        $history = [];
        foreach ($iterator as $data) {
            $history[] = $data;
        }
        
        return $history;
    }
    
    public static function getByTicket($tickets_id, $limit = 50) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => [
                'reference_id' => $tickets_id,
                'reference_type' => 'Ticket'
            ],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => $limit
        ]);
        
        $history = [];
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $data['user_name'] = $user->getFriendlyName();
                $history[] = $data;
            }
        }
        
        return $history;
    }
}