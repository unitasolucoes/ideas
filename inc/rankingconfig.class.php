<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasRankingConfig extends CommonDBTM {

    static $rightname = 'plugin_ideas_config';

    /**
     * Sobrescreve o método add para permitir inserção sem verificar direitos do GLPI
     * A verificação de permissão é feita no settings.php via canAdmin()
     */
    public function add(array $input, $options = [], $history = true) {
        global $DB;

        if (!isset($input['action_type']) || !isset($input['points_value'])) {
            return false;
        }

        return $DB->insert(
            $this->getTable(),
            $input
        );
    }

    /**
     * Sobrescreve o método update para permitir atualização sem verificar direitos do GLPI
     * A verificação de permissão é feita no settings.php via canAdmin()
     */
    public function update(array $input, $history = true, $options = []) {
        global $DB;

        if (!isset($input['id'])) {
            return false;
        }

        $id = $input['id'];
        unset($input['id']);

        return $DB->update(
            $this->getTable(),
            $input,
            ['id' => $id]
        );
    }

    public static function getTable($classname = null) {
        return 'glpi_plugin_ideas_rankingconfig';
    }

    public static function getTypeName($nb = 0) {
        return __('Ranking Configuration', 'ideas');
    }

    public static function getPointsValue($action_type) {
        global $DB;

        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['action_type' => $action_type],
            'LIMIT' => 1
        ]);

        if (count($iterator) > 0) {
            $data = $iterator->current();
            return (int) $data['points_value'];
        }

        return 0;
    }

    public static function getAllConfig() {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'ORDER' => 'action_type ASC'
        ]);

        $configs = [];
        foreach ($iterator as $data) {
            $configs[$data['action_type']] = $data;
        }

        return $configs;
    }

    public static function updatePointsValue($action_type, $points_value) {
        global $DB;

        $iterator = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['action_type' => $action_type],
            'LIMIT' => 1
        ]);

        if (count($iterator) > 0) {
            $data   = $iterator->current();
            $config = new self();

            return $config->update([
                'id'           => $data['id'],
                'points_value' => (int) $points_value
            ]);
        }

        $config = new self();
        return $config->add([
            'action_type'  => $action_type,
            'points_value' => (int) $points_value
        ]);
    }
}