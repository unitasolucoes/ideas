<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasConfig extends CommonDBTM {
    static $rightname = 'plugin_ideas_config';

    private const CACHE_KEY = 'plugin_ideas_config';

    /**
     * Sobrescreve o método add para permitir inserção sem verificar direitos do GLPI
     * A verificação de permissão é feita no settings.php via canAdmin()
     */
    public function add(array $input, $options = [], $history = true) {
        global $DB;

        if (!isset($input['menu_name'])) {
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

    public static function getConfig() {
        global $DB, $GLPI_CACHE;

        if (isset($GLPI_CACHE)) {
            $cached = $GLPI_CACHE->get(self::CACHE_KEY);
            if ($cached !== null) {
                return $cached;
            }
        }

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'LIMIT' => 1
        ]);

        if (count($iterator) > 0) {
            $config = $iterator->current();
        } else {
            $config = self::getDefaultConfig();
        }

        if (!isset($config['parent_group_id'])) {
            $config['parent_group_id'] = 0;
        }

        if (isset($GLPI_CACHE)) {
            $GLPI_CACHE->set(self::CACHE_KEY, $config);
        }

        return $config;
    }

    private static function getDefaultConfig(): array {
        return [
            'menu_name'                    => 'Pulsar',
            'campaign_category_id'         => 152,
            'idea_category_id'             => 153,
            'idea_form_url'                => Plugin::getWebDir('ideas') . '/front/nova_ideia.php',
            'parent_group_id'              => 0,
            'view_profile_ids'             => json_encode([]),
            'like_profile_ids'             => json_encode([]),
            'admin_profile_ids'            => json_encode([])
        ];
    }

    public static function updateConfig($data) {
        $config = new self();
        $existing = self::getConfig();
        $payload = array_merge($existing, $data);

        if (isset($existing['id'])) {
            $payload['id'] = $existing['id'];
            $result = $config->update($payload);
        } else {
            $result = $config->add($payload);
        }

        if ($result) {
            self::clearCache();
        }

        return $result;
    }

    public static function saveSetting(string $setting, $value): bool {
        $current = self::getConfig();
        $current[$setting] = $value;
        return (bool) self::updateConfig($current);
    }

    public static function canView($user_profile_id = null) {
        if ($user_profile_id === null) {
            return parent::canView();
        }

        $config  = self::getConfig();
        $allowed = json_decode($config['view_profile_ids'] ?? '[]', true) ?: [];

        return empty($allowed) || in_array($user_profile_id, $allowed);
    }

    public static function canLike($user_profile_id) {
        $config  = self::getConfig();
        $allowed = json_decode($config['like_profile_ids'] ?? '[]', true) ?: [];

        return empty($allowed) || in_array($user_profile_id, $allowed);
    }

    public static function canAdmin($user_profile_id) {
        $config  = self::getConfig();
        $allowed = json_decode($config['admin_profile_ids'] ?? '[]', true) ?: [];

        return empty($allowed) || in_array($user_profile_id, $allowed);
    }

    public static function clearCache(): void {
        global $GLPI_CACHE;
        if (isset($GLPI_CACHE)) {
            $GLPI_CACHE->delete(self::CACHE_KEY);
        }
    }

    public static function getParentGroupId(): int {
        $config = self::getConfig();
        return (int) ($config['parent_group_id'] ?? 0);
    }

    public static function getIdeaFormUrl(): string {
        $config    = self::getConfig();
        $configured = trim($config['idea_form_url'] ?? '');
        $native    = Plugin::getWebDir('ideas') . '/front/nova_ideia.php';

        if ($configured === '' || strpos($configured, '/formcreator/front/') !== false) {
            return $native;
        }

        return $configured;
    }

    public static function getCampaignFormUrl(): string {
        return Plugin::getWebDir('ideas') . '/front/nova_campanha.php';
    }

    /**
     * Gera iniciais padronizadas do usuário para avatar
     * 
     * @param string $firstname Nome do usuário
     * @param string $realname Sobrenome do usuário
     * @return string Iniciais (2 caracteres em maiúsculo)
     */
    public static function getUserInitials($firstname = '', $realname = '') {
        $firstname = trim($firstname ?? '');
        $realname = trim($realname ?? '');
        
        // Se tiver primeiro nome e sobrenome
        if (!empty($firstname) && !empty($realname)) {
            return strtoupper(substr($firstname, 0, 1) . substr($realname, 0, 1));
        }
        
        // Se tiver apenas sobrenome
        if (!empty($realname)) {
            return strtoupper(substr($realname, 0, 2));
        }
        
        // Se tiver apenas primeiro nome
        if (!empty($firstname)) {
            return strtoupper(substr($firstname, 0, 2));
        }
        
        // Fallback
        return '??';
    }
}

