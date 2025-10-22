<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasIdeaCampaign {

    public static function getTable(): string {
        return 'glpi_plugin_ideas_idea_campaigns';
    }

    public static function linkIdeaToCampaign(int $idea_id, int $campaign_id, int $user_id): bool {
        global $DB;

        if ($idea_id <= 0 || $campaign_id <= 0 || $user_id <= 0) {
            PluginIdeasLogger::error('idea_campaign_link_validation_failed', 'IDs inválidos', [
                'idea_id' => $idea_id,
                'campaign_id' => $campaign_id,
                'user_id' => $user_id
            ]);
            return false;
        }

        if (!PluginIdeasTicket::isIdea($idea_id)) {
            PluginIdeasLogger::error('idea_campaign_link_validation_failed', "Ticket #$idea_id não é uma ideia", [
                'idea_id' => $idea_id,
                'campaign_id' => $campaign_id
            ]);
            return false;
        }

        if (!PluginIdeasTicket::isCampaign($campaign_id)) {
            PluginIdeasLogger::error('idea_campaign_link_validation_failed', "Ticket #$campaign_id não é uma campanha", [
                'idea_id' => $idea_id,
                'campaign_id' => $campaign_id
            ]);
            return false;
        }

        if (self::isIdeaLinkedToCampaign($idea_id, $campaign_id)) {
            return true;
        }

        $data = [
            'ideas_id'       => $idea_id,
            'campaigns_id'   => $campaign_id,
            'users_id'       => $user_id,
            'date_creation'  => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')
        ];

        $result = $DB->insert(self::getTable(), $data);

        if (!$result) {
            PluginIdeasLogger::error('idea_campaign_link_insert_failed', "Falha no INSERT do banco de dados", [
                'idea_id' => $idea_id,
                'campaign_id' => $campaign_id,
                'user_id' => $user_id,
                'table' => self::getTable(),
                'data' => $data,
                'mysql_error' => $DB->error()
            ]);
        } elseif (class_exists('PluginIdeasLog')) {
            PluginIdeasLog::logAction('idea_campaign_linked', $user_id, $data);
        }

        return (bool) $result;
    }

    public static function unlinkIdeaFromCampaign(int $idea_id, int $campaign_id, int $user_id): bool {
        global $DB;

        if ($idea_id <= 0 || $campaign_id <= 0) {
            return false;
        }

        $deleted = $DB->delete(self::getTable(), [
            'ideas_id'    => $idea_id,
            'campaigns_id'=> $campaign_id
        ]);

        if ($deleted && class_exists('PluginIdeasLog')) {
            PluginIdeasLog::logAction('idea_campaign_unlinked', $user_id, [
                'idea_id'     => $idea_id,
                'campaign_id' => $campaign_id
            ]);
        }

        return (bool) $deleted;
    }

    public static function getIdeaCampaigns(int $idea_id): array {
        global $DB;

        if ($idea_id <= 0) {
            return [];
        }

        $iterator = $DB->request([
            'SELECT' => [
                'ic.campaigns_id',
                'ic.date_creation',
                'ic.users_id',
                't.name AS campaign_name',
                't.time_to_resolve'
            ],
            'FROM' => self::getTable() . ' AS ic',
            'LEFT JOIN' => [
                'glpi_tickets AS t' => [
                    'FKEY' => [
                        't' => 'id',
                        'ic' => 'campaigns_id'
                    ]
                ]
            ],
            'WHERE' => ['ic.ideas_id' => $idea_id]
        ]);

        $results = [];
        foreach ($iterator as $row) {
            // Normalizar nomes de campos para compatibilidade
            $row['campaign_id'] = $row['campaigns_id'] ?? null;
            $row['linked_by'] = $row['users_id'] ?? null;
            $row['linked_at'] = $row['date_creation'] ?? null;
            $row['campaign_deadline'] = $row['time_to_resolve'] ? Html::convDateTime($row['time_to_resolve']) : null;
            unset($row['time_to_resolve']);
            $results[] = $row;
        }

        return $results;
    }

    public static function getCampaignIdeas(int $campaign_id): array {
        global $DB;

        if ($campaign_id <= 0) {
            return [];
        }

        $iterator = $DB->request([
            'SELECT' => [
                'ic.ideas_id',
                'ic.date_creation',
                'ic.users_id',
                't.name AS idea_name',
                't.status'
            ],
            'FROM' => self::getTable() . ' AS ic',
            'LEFT JOIN' => [
                'glpi_tickets AS t' => [
                    'FKEY' => [
                        't' => 'id',
                        'ic' => 'ideas_id'
                    ]
                ]
            ],
            'WHERE' => ['ic.campaigns_id' => $campaign_id]
        ]);

        $results = [];
        foreach ($iterator as $row) {
            // Normalizar nomes de campos para compatibilidade
            $row['idea_id'] = $row['ideas_id'] ?? null;
            $row['linked_by'] = $row['users_id'] ?? null;
            $row['linked_at'] = $row['date_creation'] ?? null;
            $results[] = $row;
        }

        return $results;
    }

    public static function isIdeaLinkedToCampaign(int $idea_id, int $campaign_id): bool {
        global $DB;

        if ($idea_id <= 0 || $campaign_id <= 0) {
            return false;
        }

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'ideas_id'    => $idea_id,
                'campaigns_id'=> $campaign_id
            ],
            'LIMIT'  => 1
        ]);

        return count($iterator) > 0;
    }

    public static function countIdeaCampaigns(int $idea_id): int {
        global $DB;

        if ($idea_id <= 0) {
            return 0;
        }

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => self::getTable(),
            'WHERE'  => ['ideas_id' => $idea_id]
        ]);

        $total = 0;
        foreach ($iterator as $_row) {
            $total++;
        }

        return $total;
    }

    public static function countCampaignIdeas(int $campaign_id): int {
        global $DB;

        if ($campaign_id <= 0) {
            return 0;
        }

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => self::getTable(),
            'WHERE'  => ['campaigns_id' => $campaign_id]
        ]);

        $total = 0;
        foreach ($iterator as $_row) {
            $total++;
        }

        return $total;
    }

    public static function getLinkForIdea(int $idea_id): array {
        global $DB;

        if ($idea_id <= 0) {
            return [];
        }

        $iterator = $DB->request([
            'SELECT' => [
                'ic.id AS link_id',
                'ic.ideas_id',
                'ic.campaigns_id',
                'ic.date_creation',
                'ic.users_id',
                'campaign.name AS campaign_name',
                'campaign.time_to_resolve'
            ],
            'FROM' => self::getTable() . ' AS ic',
            'LEFT JOIN' => [
                'glpi_tickets AS campaign' => [
                    'FKEY' => [
                        'campaign' => 'id',
                        'ic'       => 'campaigns_id'
                    ]
                ]
            ],
            'WHERE' => ['ic.ideas_id' => $idea_id],
            'ORDER' => 'ic.date_creation DESC',
            'LIMIT' => 1
        ]);

        // Debug log
        PluginIdeasLogger::info('get_link_for_idea_query', sprintf('Buscando vínculo para ideia #%d', $idea_id), [
            'idea_id' => $idea_id,
            'count' => count($iterator),
            'table' => self::getTable()
        ]);

        if (count($iterator) === 0) {
            return [];
        }

        $row = $iterator->current();

        // Normalizar nomes de campos para compatibilidade
        $row['campaign_id'] = $row['campaigns_id'] ?? null;
        $row['idea_id'] = $row['ideas_id'] ?? null;
        $row['linked_by'] = $row['users_id'] ?? null;
        $row['linked_at'] = $row['date_creation'] ?? null;
        $row['campaign_deadline'] = $row['time_to_resolve'] ? Html::convDateTime($row['time_to_resolve']) : null;

        // Debug log
        PluginIdeasLogger::info('get_link_for_idea_result', sprintf('Vínculo encontrado para ideia #%d', $idea_id), [
            'idea_id' => $idea_id,
            'campaign_id' => $row['campaign_id'],
            'campaign_name' => $row['campaign_name']
        ]);

        unset($row['time_to_resolve']);

        return $row;
    }
}
