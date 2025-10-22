<?php

function plugin_ideas_install() {
    global $DB;

    $native_form_url = Plugin::getWebDir('ideas') . '/front/nova_ideia.php';
    $escaped_native_form_url = $DB->escape($native_form_url);

    $legacy_tables = [
        'glpi_plugin_agilizepulsar_configs'         => 'glpi_plugin_ideas_configs',
        'glpi_plugin_agilizepulsar_views'           => 'glpi_plugin_ideas_views',
        'glpi_plugin_agilizepulsar_likes'           => 'glpi_plugin_ideas_likes',
        'glpi_plugin_agilizepulsar_comments'        => 'glpi_plugin_ideas_comments',
        'glpi_plugin_agilizepulsar_approvals'       => 'glpi_plugin_ideas_approvals',
        'glpi_plugin_agilizepulsar_userpoints'      => 'glpi_plugin_ideas_userpoints',
        'glpi_plugin_agilizepulsar_pointshistory'   => 'glpi_plugin_ideas_pointshistory',
        'glpi_plugin_agilizepulsar_rankingconfig'   => 'glpi_plugin_ideas_rankingconfig',
        'glpi_plugin_agilizepulsar_objectives'      => 'glpi_plugin_ideas_objectives',
        'glpi_plugin_agilizepulsar_fastreplies'     => 'glpi_plugin_ideas_fastreplies',
        'glpi_plugin_agilizepulsar_logs'            => 'glpi_plugin_ideas_logs',
        'glpi_plugin_agilizepulsar_idea_campaigns'  => 'glpi_plugin_ideas_idea_campaigns',
        'glpi_plugin_agilizepulsar_campaigns'       => 'glpi_plugin_ideas_campaigns',
        'glpi_plugin_agilizepulsar_ideas'           => 'glpi_plugin_ideas_ideas',
    ];

    foreach ($legacy_tables as $old => $new) {
        if ($DB->tableExists($old) && !$DB->tableExists($new)) {
            $DB->queryOrDie("ALTER TABLE `$old` RENAME `$new`", $DB->error());
        }
    }

    // 1. Tabela de configuração
    if (!$DB->tableExists('glpi_plugin_ideas_configs')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_configs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `menu_name` varchar(255) DEFAULT 'Pulsar',
            `campaign_category_id` int unsigned DEFAULT 152,
            `idea_category_id` int unsigned DEFAULT 153,
            `idea_form_url` varchar(255) DEFAULT '{$escaped_native_form_url}',
            `parent_group_id` int unsigned DEFAULT 0,
            `view_profile_ids` text,
            `like_profile_ids` text,
            `admin_profile_ids` text,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $DB->queryOrDie($query, $DB->error());

        $DB->insertOrDie('glpi_plugin_ideas_configs', [
            'menu_name'             => 'Pulsar',
            'campaign_category_id'  => 152,
            'idea_category_id'      => 153,
            'idea_form_url'         => $native_form_url,
            'parent_group_id'       => 0,
            'view_profile_ids'      => json_encode([]),
            'like_profile_ids'      => json_encode([]),
            'admin_profile_ids'     => json_encode([])
        ]);
    }

    // Verificar se existe o campo idea_form_url
    if ($DB->tableExists('glpi_plugin_ideas_configs')) {
        if (!$DB->fieldExists('glpi_plugin_ideas_configs', 'idea_form_url')) {
            $DB->queryOrDie("ALTER TABLE `glpi_plugin_ideas_configs` ADD `idea_form_url` varchar(255) DEFAULT '{$escaped_native_form_url}'", $DB->error());

            $DB->updateOrDie(
                'glpi_plugin_ideas_configs',
                ['idea_form_url' => $native_form_url],
                []
            );
        } else {
            $DB->update(
                'glpi_plugin_ideas_configs',
                ['idea_form_url' => $native_form_url],
                ['idea_form_url' => '/marketplace/formcreator/front/formdisplay.php?id=121']
            );
        }

        if (!$DB->fieldExists('glpi_plugin_ideas_configs', 'parent_group_id')) {
            $DB->queryOrDie(
                "ALTER TABLE `glpi_plugin_ideas_configs` ADD `parent_group_id` int unsigned DEFAULT 0",
                $DB->error()
            );

            $DB->updateOrDie(
                'glpi_plugin_ideas_configs',
                ['parent_group_id' => 0],
                []
            );
        }
    }

    // 2. Tabela de visualizações
    if (!$DB->tableExists('glpi_plugin_ideas_views')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_views` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT '0',
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `viewed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_view` (`tickets_id`, `users_id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `users_id` (`users_id`),
            KEY `ticket_date` (`tickets_id`, `viewed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }

    // 3. Tabela de curtidas
    if (!$DB->tableExists('glpi_plugin_ideas_likes')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_likes` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT '0',
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_user` (`tickets_id`, `users_id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `users_id` (`users_id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }

    // 4. Tabela de comentários
    if (!$DB->tableExists('glpi_plugin_ideas_comments')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_comments` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT '0',
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `content` text,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `users_id` (`users_id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }

    // 5. Tabela de aprovações
    if (!$DB->tableExists('glpi_plugin_ideas_approvals')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_approvals` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT '0',
            `step_number` tinyint NOT NULL DEFAULT '1',
            `groups_id` int unsigned NOT NULL DEFAULT '0',
            `users_id_validator` int unsigned NOT NULL DEFAULT '0',
            `status` tinyint NOT NULL DEFAULT '0',
            `comment` varchar(400) DEFAULT NULL,
            `date_validation` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `step_number` (`step_number`),
            KEY `groups_id` (`groups_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }
    
    // 6. Tabela de pontos dos usuários
    if (!$DB->tableExists('glpi_plugin_ideas_userpoints')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_userpoints` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `points_total` int NOT NULL DEFAULT '0',
            `points_month` int NOT NULL DEFAULT '0',
            `points_year` int NOT NULL DEFAULT '0',
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_id` (`users_id`),
            KEY `points_total` (`points_total`),
            KEY `points_month` (`points_month`),
            KEY `points_year` (`points_year`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }
    
    // 7. Tabela de histórico de pontos
    if (!$DB->tableExists('glpi_plugin_ideas_pointshistory')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_pointshistory` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `action_type` varchar(50) NOT NULL,
            `points_earned` int NOT NULL DEFAULT '0',
            `reference_id` int unsigned NOT NULL DEFAULT '0',
            `reference_type` varchar(50) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `users_id` (`users_id`),
            KEY `action_type` (`action_type`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }
    
    // 8. Tabela de configuração de ranking
    if (!$DB->tableExists('glpi_plugin_ideas_rankingconfig')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_rankingconfig` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `action_type` varchar(50) NOT NULL,
            `points_value` int NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `action_type` (`action_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $DB->queryOrDie($query, $DB->error());
        
        $defaults = [
            ['action_type' => 'submitted_idea', 'points_value' => 10],
            ['action_type' => 'approved_idea', 'points_value' => 50],
            ['action_type' => 'like', 'points_value' => 2],
            ['action_type' => 'comment', 'points_value' => 5],
            ['action_type' => 'implemented_idea', 'points_value' => 100]
        ];
        
        foreach ($defaults as $default) {
            $DB->insertOrDie('glpi_plugin_ideas_rankingconfig', $default);
        }
    }
    
    // 9. Tabela de objetivos
    if (!$DB->tableExists('glpi_plugin_ideas_objectives')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_objectives` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `is_active` tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }
    
    // 10. Tabela de respostas rápidas
    if (!$DB->tableExists('glpi_plugin_ideas_fastreplies')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_fastreplies` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `content` text,
            `step_number` tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `step_number` (`step_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }
    
    // 11. Tabela de logs
    if (!$DB->tableExists('glpi_plugin_ideas_logs')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_logs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `action` varchar(255) NOT NULL,
            `details` text,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `users_id` (`users_id`),
            KEY `action` (`action`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());
    }
    
    // ✅ 12. TABELA DE VÍNCULO IDEIAS ↔ CAMPANHAS
    if (!$DB->tableExists('glpi_plugin_ideas_idea_campaigns')) {
        $query = "CREATE TABLE `glpi_plugin_ideas_idea_campaigns` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ideas_id` int(11) NOT NULL COMMENT 'ID do ticket (ideia)',
            `campaigns_id` int(11) NOT NULL COMMENT 'ID do ticket (campanha)',
            `date_creation` datetime DEFAULT NULL,
            `users_id` int(11) DEFAULT NULL COMMENT 'Quem vinculou',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_link` (`ideas_id`, `campaigns_id`),
            KEY `ideas_id` (`ideas_id`),
            KEY `campaigns_id` (`campaigns_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $DB->queryOrDie($query, $DB->error());
    }
    
    return true;
}

function plugin_ideas_uninstall() {
    global $DB;

    $tables = [
        'glpi_plugin_ideas_configs',
        'glpi_plugin_ideas_views',
        'glpi_plugin_ideas_likes',
        'glpi_plugin_ideas_comments',
        'glpi_plugin_ideas_approvals',
        'glpi_plugin_ideas_userpoints',
        'glpi_plugin_ideas_pointshistory',
        'glpi_plugin_ideas_rankingconfig',
        'glpi_plugin_ideas_objectives',
        'glpi_plugin_ideas_fastreplies',
        'glpi_plugin_ideas_logs',
        'glpi_plugin_ideas_idea_campaigns'
    ];

    foreach ($tables as $table) {
        $DB->queryOrDie("DROP TABLE IF EXISTS `$table`", $DB->error());
    }

    return true;
}

function plugin_item_add_ideas(CommonDBTM $item) {
    if (!($item instanceof Ticket)) {
        return true;
    }

    $config = PluginIdeasConfig::getConfig();
    $idea_category_id = (int)($config['idea_category_id'] ?? 0);

    if ($idea_category_id <= 0) {
        return true;
    }

    $ticket_category = (int)($item->fields['itilcategories_id'] ?? 0);
    if ($ticket_category !== $idea_category_id) {
        return true;
    }

    $users_id = (int)($item->fields['users_id_recipient'] ?? 0);
    if ($users_id <= 0) {
        $users_id = (int)($item->fields['users_id_lastupdater'] ?? 0);
    }
    if ($users_id <= 0) {
        $users_id = (int)($item->fields['users_id'] ?? 0);
    }

    $ticket_id = (int)($item->getID() ?: ($item->fields['id'] ?? 0));

    if ($users_id > 0 && $ticket_id > 0) {
        if (PluginIdeasUserPoints::addPoints($users_id, 'submitted_idea', $ticket_id, false)) {
            PluginIdeasLog::logAction('idea_submitted', $users_id, [
                'tickets_id' => $ticket_id
            ]);
        }
    }

    return true;
}

function plugin_item_update_ideas(CommonDBTM $item) {
    if (!($item instanceof Ticket)) {
        return true;
    }

    $config = PluginIdeasConfig::getConfig();
    $idea_category_id = (int)($config['idea_category_id'] ?? 0);

    if ($idea_category_id <= 0) {
        return true;
    }

    $ticket_category = (int)($item->fields['itilcategories_id'] ?? 0);
    if ($ticket_category !== $idea_category_id) {
        return true;
    }

    $old_status = isset($item->oldvalues['status']) ? (int)$item->oldvalues['status'] : null;
    $new_status = isset($item->fields['status']) ? (int)$item->fields['status'] : $old_status;

    if ($new_status === null || $old_status === $new_status) {
        return true;
    }

    $users_id = (int)($item->fields['users_id_recipient'] ?? 0);
    if ($users_id <= 0) {
        $users_id = (int)($item->fields['users_id_lastupdater'] ?? 0);
    }
    if ($users_id <= 0) {
        $users_id = (int)($item->fields['users_id'] ?? 0);
    }

    $ticket_id = (int)($item->getID() ?: ($item->fields['id'] ?? 0));

    if ($users_id <= 0 || $ticket_id <= 0) {
        return true;
    }

    if ($new_status === Ticket::SOLVED) {
        if (PluginIdeasUserPoints::addPoints($users_id, 'approved_idea', $ticket_id, false)) {
            PluginIdeasLog::logAction('idea_approved', $users_id, [
                'tickets_id' => $ticket_id
            ]);
        }
    } elseif ($new_status === Ticket::CLOSED) {
        if (PluginIdeasUserPoints::addPoints($users_id, 'implemented_idea', $ticket_id, false)) {
            PluginIdeasLog::logAction('idea_implemented', $users_id, [
                'tickets_id' => $ticket_id
            ]);
        }
    }

    return true;
}