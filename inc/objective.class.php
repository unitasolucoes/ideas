<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasObjective extends CommonDBTM {
    
    static $rightname = 'config';
    
    public static function getTypeName($nb = 0) {
        return __('Strategic Objective', 'ideas');
    }
    
    public static function getActiveObjectives() {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['is_active' => 1],
            'ORDER' => 'name ASC'
        ]);
        
        $objectives = [];
        foreach ($iterator as $data) {
            $objectives[] = $data;
        }
        
        return $objectives;
    }
    
    public static function getAllObjectives() {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'ORDER' => 'name ASC'
        ]);
        
        $objectives = [];
        foreach ($iterator as $data) {
            $objectives[] = $data;
        }
        
        return $objectives;
    }
}