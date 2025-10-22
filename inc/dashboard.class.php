<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasDashboard extends CommonGLPI {

    public static function dashboardCards() {
        $cards = [];

        $cards['plugin_ideas_stats'] = [
            'widgettype' => ['bigNumber'],
            'label'      => __('Estatísticas Pulsar', 'ideas'),
            'provider'   => 'PluginIdeasDashboard::cardStats'
        ];

        return $cards;
    }

    public static function cardStats(array $params = []) {
        $ideas = PluginIdeasTicket::getIdeas();

        return [
            'number' => count($ideas),
            'label'  => sprintf(__('%d ideias ativas', 'ideas'), count($ideas)),
            'url'    => Plugin::getWebDir('ideas') . '/front/feed.php'
        ];
    }
}
