<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasLog extends CommonDBTM {
    
    static $rightname = 'config';
    
    public static function getTypeName($nb = 0) {
        return __('Log', 'ideas');
    }
    
    public static function logAction($action, $users_id, $details = []) {
        $log = new self();

        $data = [
            'users_id' => $users_id,
            'action' => $action,
            'details' => json_encode($details),
            'date_creation' => $_SESSION['glpi_currenttime']
        ];

        // Usar método da classe pai sem conflito
        return $log->add($data);
    }
    
    public static function getRecent($limit = 100) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'ORDER' => 'date_creation DESC',
            'LIMIT' => $limit
        ]);
        
        $logs = [];
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $data['user_name'] = $user->getFriendlyName();
            }
            $data['details'] = json_decode($data['details'], true);
            $logs[] = $data;
        }
        
        return $logs;
    }
    
    public static function getByUser($users_id, $limit = 50) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['users_id' => $users_id],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => $limit
        ]);
        
        $logs = [];
        foreach ($iterator as $data) {
            $data['details'] = json_decode($data['details'], true);
            $logs[] = $data;
        }
        
        return $logs;
    }
    
    public static function getByAction($action, $limit = 50) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['action' => $action],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => $limit
        ]);
        
        $logs = [];
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $data['user_name'] = $user->getFriendlyName();
            }
            $data['details'] = json_decode($data['details'], true);
            $logs[] = $data;
        }
        
        return $logs;
    }
    
    public static function cleanOldLogs($days = 90) {
        global $DB;
        
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $DB->delete(
            self::getTable(),
            ['date_creation' => ['<', $date]]
        );
    }
}