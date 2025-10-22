<?php

define('PLUGIN_IDEAS_VERSION', '1.0.0');
define('PLUGIN_IDEAS_MIN_GLPI_VERSION', '10.0.0');
define('PLUGIN_IDEAS_MAX_GLPI_VERSION', '10.0.99');

function plugin_init_ideas() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['ideas'] = true;

    Plugin::registerClass('PluginIdeasConfig');
    Plugin::registerClass('PluginIdeasMenu');
    Plugin::registerClass('PluginIdeasRedirect');
    Plugin::registerClass('PluginIdeasTicket');
    Plugin::registerClass('PluginIdeasIdeaCampaign');
    Plugin::registerClass('PluginIdeasLike');
    Plugin::registerClass('PluginIdeasComment');
    Plugin::registerClass('PluginIdeasUserPoints');
    Plugin::registerClass('PluginIdeasPointsHistory');
    Plugin::registerClass('PluginIdeasRankingConfig');
    Plugin::registerClass('PluginIdeasObjective');
    Plugin::registerClass('PluginIdeasFastReply');
    Plugin::registerClass('PluginIdeasApproval');
    Plugin::registerClass('PluginIdeasLog');
    Plugin::registerClass('PluginIdeasView');
    Plugin::registerClass('PluginIdeasTicketTab', ['addtabon' => 'Ticket']);
    Plugin::registerClass('PluginIdeasDashboard');

    $plugin = new Plugin();
    if ($plugin->isInstalled('ideas') && $plugin->isActivated('ideas')) {
        $profile_id = $_SESSION['glpiactiveprofile']['id'] ?? 0;

        if (PluginIdeasConfig::canView($profile_id)) {
            $PLUGIN_HOOKS['menu_toadd']['ideas'] = [
                'management' => 'PluginIdeasMenu'
            ];
        }

        $PLUGIN_HOOKS['add_css']['ideas'] = [
            'css/pulsar.css',
            'css/forms.css'
        ];

        $PLUGIN_HOOKS['add_javascript']['ideas'] = [
            'js/pulsar.js'
        ];

        $PLUGIN_HOOKS['pre_item_form']['ideas'] = [
            'PluginIdeasRedirect',
            'maybeRedirect'
        ];

        $PLUGIN_HOOKS['pre_show_item']['ideas'] = [
            'PluginIdeasRedirect',
            'redirectFromFormList'
        ];

        $PLUGIN_HOOKS['item_display']['ideas'] = [
            'PluginFormcreatorForm' => [
                'PluginIdeasRedirect',
                'redirectFromFormDisplay'
            ]
        ];

        $PLUGIN_HOOKS['dashboard_cards']['ideas'] = ['PluginIdeasDashboard', 'dashboardCards'];
    }
}

function plugin_version_ideas() {
    return [
        'name'           => 'Unitá - Campanhas e Ideias',
        'version'        => PLUGIN_IDEAS_VERSION,
        'author'         => 'Unitá Soluções Digitais',
        'license'        => 'Comercial',
        'homepage'       => 'https://unitasolucoes.com.br',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_IDEAS_MIN_GLPI_VERSION,
                'max' => PLUGIN_IDEAS_MAX_GLPI_VERSION,
            ]
        ]
    ];
}

function plugin_ideas_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_IDEAS_MIN_GLPI_VERSION, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_IDEAS_MAX_GLPI_VERSION, 'ge')) {
        echo "GLPI version not compatible. Requires " . PLUGIN_IDEAS_MIN_GLPI_VERSION . " to " . PLUGIN_IDEAS_MAX_GLPI_VERSION;
        return false;
    }
    return true;
}

function plugin_ideas_check_config($verbose = false) {
    return true;
}