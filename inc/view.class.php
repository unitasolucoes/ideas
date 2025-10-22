<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasView extends CommonDBTM {
    static $rightname = 'ticket';

    public static function addView($tickets_id, $users_id) {
        global $DB;

        if (empty($tickets_id) || empty($users_id)) {
            return false;
        }

        $exists = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'tickets_id' => $tickets_id,
                'users_id'   => $users_id
            ],
            'LIMIT' => 1
        ]);

        if (count($exists) > 0) {
            return true;
        }

        $view = new self();
        $data = [
            'tickets_id' => $tickets_id,
            'users_id'   => $users_id
        ];

        if (isset($_SESSION['glpi_currenttime'])) {
            $data['viewed_at'] = $_SESSION['glpi_currenttime'];
        }

        return $view->add($data);
    }

public static function countByTicket($tickets_id) {
    global $DB;

    $iterator = $DB->request([
        'SELECT' => [new \QueryExpression('COUNT(DISTINCT ' . $DB->quoteName('users_id') . ') as total')],
        'FROM'   => self::getTable(),
        'WHERE'  => ['tickets_id' => $tickets_id]
    ]);

    if (count($iterator) > 0) {
        $row = $iterator->current();
        return (int)($row['total'] ?? 0);
    }

    return 0;
}
    public static function getByTicket($tickets_id) {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['users_id', 'viewed_at'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['tickets_id' => $tickets_id],
            'ORDER'  => 'viewed_at DESC'
        ]);

        $views = [];
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $data['user_name'] = $user->getFriendlyName();
                $views[] = $data;
            }
        }

        return $views;
    }
}
