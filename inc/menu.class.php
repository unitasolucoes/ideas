<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasMenu extends CommonGLPI {

    static $rightname = 'ticket';

    public static function getMenuName() {
        $config = PluginIdeasConfig::getConfig();
        return $config['menu_name'] ?? __('Pulsar', 'ideas');
    }

    public static function getMenuContent() {
        $profile_id = $_SESSION['glpiactiveprofile']['id'] ?? 0;

        if (!PluginIdeasConfig::canView($profile_id)) {
            return false;
        }

        $config = PluginIdeasConfig::getConfig();
        $menu_name = $config['menu_name'] ?? __('Pulsar', 'ideas');
        $menu_icon = $config['menu_icon'] ?? 'fa-solid fa-rocket';

        $menu = [
            'title' => $menu_name,
            'page'  => Plugin::getWebDir('ideas') . '/front/feed.php',
            'icon'  => $menu_icon,
        ];

        $menu['options'] = [];

        $menu['options']['feed'] = [
            'title' => __('Feed de Ideias', 'ideas'),
            'page'  => Plugin::getWebDir('ideas') . '/front/feed.php',
            'icon'  => 'ti ti-home'
        ];

        $menu['options']['nova_ideia'] = [
            'title' => __('Nova Ideia', 'ideas'),
            'page'  => Plugin::getWebDir('ideas') . '/front/nova_ideia.php',
            'icon'  => 'ti ti-bulb'
        ];

        $menu['options']['minhas_ideias'] = [
            'title' => __('Minhas Ideias', 'ideas'),
            'page'  => Plugin::getWebDir('ideas') . '/front/my_ideas.php',
            'icon'  => 'ti ti-user'
        ];

        $menu['options']['campanhas'] = [
            'title' => __('Campanhas', 'ideas'),
            'page'  => Plugin::getWebDir('ideas') . '/front/campaigns.php',
            'icon'  => 'ti ti-flag'
        ];

        if (PluginIdeasConfig::canAdmin($profile_id)) {
            $menu['options']['nova_campanha'] = [
                'title' => __('Nova Campanha', 'ideas'),
                'page'  => Plugin::getWebDir('ideas') . '/front/nova_campanha.php',
                'icon'  => 'ti ti-flag-plus'
            ];

            $menu['options']['config'] = [
                'title' => __('Configurações', 'ideas'),
                'page'  => Plugin::getWebDir('ideas') . '/front/settings.php',
                'icon'  => 'ti ti-settings'
            ];
        }

        return $menu;
    }

    public static function removeRightsFromSession() {
        return true;
    }
}
