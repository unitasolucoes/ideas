<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasUserPoints extends CommonDBTM {
    
    static $rightname = 'ticket';
    
    public static function getTypeName($nb = 0) {
        return __('User Points', 'ideas');
    }
    
    public static function addPoints($users_id, $action_type, $reference_id = 0, $allow_duplicate = true) {
        $config = PluginIdeasRankingConfig::getPointsValue($action_type);

        if ($config <= 0) {
            return false;
        }

        if (!$allow_duplicate
            && PluginIdeasPointsHistory::hasRecord($users_id, $action_type, $reference_id)) {
            return false;
        }
        
        $userPoints = self::getOrCreateUserPoints($users_id);
        
        $userPoints->fields['points_total'] += $config;
        $userPoints->fields['points_month'] += $config;
        $userPoints->fields['points_year'] += $config;
        $userPoints->fields['date_mod'] = $_SESSION['glpi_currenttime'];
        
        $updated = $userPoints->update($userPoints->fields);
        
        if ($updated) {
            PluginIdeasPointsHistory::record([
                'users_id' => $users_id,
                'action_type' => $action_type,
                'points_earned' => $config,
                'reference_id' => $reference_id,
                'reference_type' => 'Ticket'
            ]);
        }
        
        return true;
    }
    
    public static function removePoints($users_id, $action_type, $reference_id = 0) {
        $points_value = PluginIdeasRankingConfig::getPointsValue($action_type);
        
        if ($points_value <= 0) {
            return false;
        }
        
        $userPoints = self::getOrCreateUserPoints($users_id);
        
        $userPoints->fields['points_total'] = max(0, $userPoints->fields['points_total'] - $points_value);
        $userPoints->fields['points_month'] = max(0, $userPoints->fields['points_month'] - $points_value);
        $userPoints->fields['points_year']  = max(0, $userPoints->fields['points_year'] - $points_value);
        $userPoints->fields['date_mod']     = $_SESSION['glpi_currenttime'];
        
        $updated = $userPoints->update($userPoints->fields);
        
        if ($updated) {
            PluginIdeasPointsHistory::record([
                'users_id'       => $users_id,
                'action_type'    => 'removed_' . $action_type,
                'points_earned'  => -$points_value,
                'reference_id'   => $reference_id,
                'reference_type' => 'Ticket'
            ]);
        }
        
        return $updated;
    }
    
    public static function getOrCreateUserPoints($users_id) {
        global $DB;

        $userPoints = new self();

        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['users_id' => $users_id]
        ]);

        if (count($iterator) > 0) {
            $data = $iterator->current();
            $userPoints->getFromDB($data['id']);
        } else {
            $newID = $userPoints->add([
                'users_id' => $users_id,
                'points_total' => 0,
                'points_month' => 0,
                'points_year' => 0,
                'date_mod' => $_SESSION['glpi_currenttime']
            ]);

            if ($newID) {
                $userPoints->getFromDB($newID);
            }
        }
        
        return $userPoints;
    }
    
    public static function getRanking($period = 'total', $limit = 10) {
        global $DB;
        
        $field_map = [
            'total' => 'points_total',
            'month' => 'points_month',
            'year' => 'points_year'
        ];
        
        $field = $field_map[$period] ?? 'points_total';
        
        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => [$field => ['>', 0]],
            'ORDER' => "$field DESC", 
            'LIMIT' => $limit
        ]);
        
        $ranking = [];
        $position = 1;
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $ranking[] = [
                    'position' => $position++,
                    'users_id' => $data['users_id'],
                    'user_name' => $user->getFriendlyName(),
                    'points' => $data[$field]
                ];
            }
        }
        
        return $ranking;
    }
    
    public static function resetMonthlyPoints() {
        global $DB;
        
        $DB->update(
            self::getTable(),
            ['points_month' => 0],
            ['points_month' => ['>', 0]]
        );
        
        return true;
    }
    
    public static function resetYearlyPoints() {
        global $DB;
        
        $DB->update(
            self::getTable(),
            ['points_year' => 0],
            ['points_year' => ['>', 0]]
        );
        
        return true;
    }
}