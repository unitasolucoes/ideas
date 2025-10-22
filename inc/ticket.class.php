<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasTicket {

    public static function isIdea($tickets_id) {
        $config = PluginIdeasConfig::getConfig();
        $CATEGORY_IDEA = $config['idea_category_id'];

        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return false;
        }

        return $ticket->fields['itilcategories_id'] == $CATEGORY_IDEA;
    }

    public static function isCampaign($tickets_id) {
        $config = PluginIdeasConfig::getConfig();
        $CATEGORY_CAMPAIGN = $config['campaign_category_id'];

        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return false;
        }

        return $ticket->fields['itilcategories_id'] == $CATEGORY_CAMPAIGN;
    }

    public static function getIdeas($filters = []) {
        global $DB;

        $config = PluginIdeasConfig::getConfig();
        $CATEGORY_IDEA = $config['idea_category_id'];

        $where = ['itilcategories_id' => $CATEGORY_IDEA];
        
        if (isset($filters['campaign_id'])) {
            $where['id'] = new QuerySubQuery([
                'SELECT' => 'tickets_id',
                'FROM' => 'glpi_items_tickets',
                'WHERE' => [
                    'itemtype' => 'Ticket',
                    'items_id' => $filters['campaign_id']
                ]
            ]);
        }
        
        if (isset($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        
        if (isset($filters['users_id'])) {
            $where['users_id_recipient'] = $filters['users_id'];
        }
        
        $iterator = $DB->request([
            'FROM' => 'glpi_tickets',
            'WHERE' => $where,
            'ORDER' => 'date DESC'
        ]);
        
        $ideas = [];
        foreach ($iterator as $data) {
            $ideas[] = self::enrichTicketData($data);
        }
        
        return $ideas;
    }
    
    public static function getCampaigns($filters = []) {
        global $DB;

        $config = PluginIdeasConfig::getConfig();
        $CATEGORY_CAMPAIGN = $config['campaign_category_id'];

        $where = ['itilcategories_id' => $CATEGORY_CAMPAIGN];
        
        if (isset($filters['is_active'])) {
            $where['status'] = [Ticket::INCOMING, Ticket::ASSIGNED, Ticket::PLANNED, Ticket::WAITING];
        }
        
        $iterator = $DB->request([
            'FROM' => 'glpi_tickets',
            'WHERE' => $where,
            'ORDER' => 'date DESC'
        ]);
        
        $campaigns = [];
        foreach ($iterator as $data) {
            $campaigns[] = self::enrichTicketData($data);
        }
        
        return $campaigns;
    }
    
    public static function getIdeasByCampaign($campaign_id) {
        global $DB;

        if ($campaign_id <= 0) {
            return [];
        }

        $config        = PluginIdeasConfig::getConfig();
        $CATEGORY_IDEA = (int) ($config['idea_category_id'] ?? 0);

        if ($CATEGORY_IDEA <= 0) {
            return [];
        }

        $ideas = [];

        $sql = sprintf(
            'SELECT tt.tickets_id_2 AS idea_id
             FROM glpi_tickets_tickets AS tt
             LEFT JOIN glpi_tickets AS idea ON idea.id = tt.tickets_id_2
             WHERE tt.tickets_id_1 = %d
               AND idea.itilcategories_id = %d
             ORDER BY idea.date DESC',
            (int) $campaign_id,
            (int) $CATEGORY_IDEA
        );

        $iterator = $DB->request($sql);

        if ($iterator === false) {
            $iterator = [];
        }

        foreach ($iterator as $data) {
            $ticket = new Ticket();
            if ($ticket->getFromDB((int) $data['idea_id'])) {
                $ideas[] = self::enrichTicketData($ticket->fields);
            }
        }

        if (empty($ideas)) {
            $ideas = self::getIdeasByCampaignLegacy($campaign_id, $CATEGORY_IDEA);
        }

        return $ideas;
    }

    private static function getIdeasByCampaignLegacy(int $campaign_id, int $idea_category_id): array {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'tickets_id',
            'FROM'   => 'glpi_items_tickets',
            'WHERE'  => [
                'itemtype' => 'Ticket',
                'items_id' => $campaign_id
            ]
        ]);

        $ideas = [];
        foreach ($iterator as $data) {
            $ticket = new Ticket();
            if ($ticket->getFromDB((int) $data['tickets_id'])
                && (int) ($ticket->fields['itilcategories_id'] ?? 0) === $idea_category_id
                && (int) ($ticket->fields['is_deleted'] ?? 0) === 0) {
                $ideas[] = self::enrichTicketData($ticket->fields);
            }
        }

        return $ideas;
    }

    public static function enrichTicketData($ticket_data) {
        global $DB;

        $ticket_data['likes_count']   = PluginIdeasLike::countByTicket($ticket_data['id']);
        $ticket_data['comments_count'] = PluginIdeasComment::countByTicket($ticket_data['id']);
        $ticket_data['views_count']    = PluginIdeasView::countByTicket($ticket_data['id']);
        $ticket_data['has_liked']      = PluginIdeasLike::userHasLiked($ticket_data['id'], Session::getLoginUserID());

        $ticket_data['campaign_id']       = null;
        $ticket_data['campaign_name']     = null;
        $ticket_data['campaign_deadline'] = null;
        $ticket_data['ideas_count']       = null;
        $ticket_data['author_id']         = null;
        $ticket_data['author_name']       = null;
        $ticket_data['author_initials']   = null;

        $config               = PluginIdeasConfig::getConfig();
        $idea_category_id     = (int) ($config['idea_category_id'] ?? 0);
        $campaign_category_id = (int) ($config['campaign_category_id'] ?? 0);

        if (!empty($ticket_data['id'])) {
            $author_id = null;

            $requester_iterator = $DB->request([
                'SELECT' => ['users_id'],
                'FROM'   => 'glpi_tickets_users',
                'WHERE'  => [
                    'tickets_id' => (int) $ticket_data['id'],
                    'type'       => Ticket_User::REQUESTER
                ],
                'ORDER' => 'id ASC',
                'LIMIT' => 1
            ]);

            if (count($requester_iterator)) {
                $author_id = (int) ($requester_iterator->current()['users_id'] ?? 0);
            }

            if (!$author_id && !empty($ticket_data['users_id_requester'])) {
                $author_id = (int) $ticket_data['users_id_requester'];
            }

            if (!$author_id && !empty($ticket_data['users_id_recipient'])) {
                $author_id = (int) $ticket_data['users_id_recipient'];
            }

            if ($author_id) {
                $user = new User();
                if ($user->getFromDB($author_id)) {
                    $ticket_data['author_id']       = $author_id;
                    $ticket_data['author_name']     = $user->getFriendlyName();
                    $ticket_data['author_initials'] = PluginIdeasConfig::getUserInitials(
                        $user->fields['firstname'] ?? '',
                        $user->fields['realname'] ?? ''
                    );
                }
            }
        }

        if ((int) ($ticket_data['itilcategories_id'] ?? 0) === $campaign_category_id) {
            $ticket_data['ideas_count'] = self::countIdeasByCampaign((int) $ticket_data['id']);
        }

        // Buscar campanha usando a relação nativa pai-filho do GLPI
        if ((int) ($ticket_data['itilcategories_id'] ?? 0) === $idea_category_id) {
            $campaign_link = self::getCampaignForIdea($ticket_data['id']);
            if (!empty($campaign_link)) {
                $ticket_data['campaign_id']       = (int) $campaign_link['campaign_id'];
                $ticket_data['campaign_name']     = $campaign_link['campaign_name'];
                $ticket_data['campaign_deadline'] = $campaign_link['campaign_deadline'];
            }
        }

        return $ticket_data;
    }

    public static function countIdeasByCampaign(int $campaign_id): int {
        global $DB;

        if ($campaign_id <= 0) {
            return 0;
        }

        $config = PluginIdeasConfig::getConfig();
        $CATEGORY_IDEA = (int) ($config['idea_category_id'] ?? 0);

        if ($CATEGORY_IDEA <= 0) {
            return 0;
        }

        // Usar a tabela glpi_tickets_tickets para contar ideias vinculadas
        $iterator = $DB->request([
            'SELECT' => [new \QueryExpression('COUNT(*) AS total')],
            'FROM'   => 'glpi_tickets_tickets AS tt',
            'LEFT JOIN' => [
                'glpi_tickets AS t' => [
                    'FKEY' => [
                        't'  => 'id',
                        'tt' => 'tickets_id_2'
                    ]
                ]
            ],
            'WHERE'  => [
                'tt.tickets_id_1'     => $campaign_id,
                't.is_deleted'        => 0,
                't.itilcategories_id' => $CATEGORY_IDEA
            ]
        ]);

        if (count($iterator)) {
            $row = $iterator->current();
            return (int) ($row['total'] ?? 0);
        }

        return 0;
    }

    // Método antigo (mantido como fallback)
    private static function getCampaignForIdeaLegacy($tickets_id) {
        global $DB;

        if ($tickets_id <= 0) {
            return [];
        }

        $iterator = $DB->request([
            'SELECT' => [
                'link_id'          => 'it.id',
                'campaign_id'      => 'it.items_id',
                'campaign_name'    => 't.name',
                'campaign_deadline'=> 't.time_to_resolve'
            ],
            'FROM' => 'glpi_items_tickets AS it',
            'LEFT JOIN' => [
                'glpi_tickets AS t' => [
                    'FKEY' => [
                        't' => 'id',
                        'it' => 'items_id'
                    ]
                ]
            ],
            'WHERE' => [
                'it.tickets_id' => $tickets_id,
                'it.itemtype' => 'Ticket'
            ],
            'LIMIT' => 1
        ]);

        if (count($iterator) === 0) {
            return [];
        }

        $data = $iterator->current();

        if (!empty($data['campaign_deadline'])) {
            $data['campaign_deadline'] = Html::convDateTime($data['campaign_deadline']);
        } else {
            $data['campaign_deadline'] = null;
        }

        return $data;
    }

    // Método público - usa a relação nativa Ticket_Ticket do GLPI
    public static function getCampaignForIdea($tickets_id) {
        global $DB;

        if ($tickets_id <= 0) {
            return [];
        }

        // Buscar na tabela glpi_tickets_tickets onde a ideia é tickets_id_2 (filho)
        $iterator = $DB->request([
            'SELECT' => [
                'tt.tickets_id_1 AS campaign_id',
                'campaign.name AS campaign_name',
                'campaign.time_to_resolve AS campaign_deadline'
            ],
            'FROM' => 'glpi_tickets_tickets AS tt',
            'LEFT JOIN' => [
                'glpi_tickets AS campaign' => [
                    'FKEY' => [
                        'campaign' => 'id',
                        'tt'       => 'tickets_id_1'
                    ]
                ]
            ],
            'WHERE' => ['tt.tickets_id_2' => $tickets_id],
            'LIMIT' => 1
        ]);

        if (count($iterator) === 0) {
            return [];
        }

        $data = $iterator->current();

        // Se não há campanha pai, retorna vazio
        if (empty($data['campaign_id'])) {
            return [];
        }

        if (!empty($data['campaign_deadline'])) {
            $data['campaign_deadline'] = Html::convDateTime($data['campaign_deadline']);
        } else {
            $data['campaign_deadline'] = null;
        }

        return $data;
    }
    
    public static function getFormAnswers($tickets_id) {
        global $DB;
        
        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return [];
        }
        
        return [
            'Descrição' => $ticket->fields['content']
        ];
    }
    
    public static function getCoauthors($tickets_id) {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'users_id',
            'FROM' => 'glpi_tickets_users',
            'WHERE' => [
                'tickets_id' => $tickets_id,
                'type' => CommonITILActor::OBSERVER
            ]
        ]);

        $coauthors = [];
        foreach ($iterator as $data) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $coauthors[] = $user->fields;
            }
        }

        return $coauthors;
    }

    public static function getStatusPresentation($status_id) {
        $status_id = (int) $status_id;

        $badge_class = 'info';
        $badge_icon  = 'fa-circle-info';

        switch ($status_id) {
            case Ticket::SOLVED:
                $badge_class = 'success';
                $badge_icon  = 'fa-circle-check';
                break;

            case Ticket::CLOSED:
                $badge_class = 'implemented';
                $badge_icon  = 'fa-circle-check';
                break;

            case Ticket::WAITING:
            case Ticket::PLANNED:
                $badge_class = 'warn';
                $badge_icon  = 'fa-circle-exclamation';
                break;

            default:
                $badge_class = 'info';
                $badge_icon  = 'fa-circle-info';
                break;
        }

        return [
            'class' => $badge_class,
            'icon'  => $badge_icon,
            'label' => Ticket::getStatus($status_id)
        ];
    }

    /**
     * Retorna a definição das etapas do fluxo da ideia.
     */
    public static function getWorkflowStageDefinitions(): array {
        return [
            'rascunho' => [
                'label'       => __('Rascunho', 'ideas'),
                'description' => __('Preencha e salve as principais informações da sua proposta.', 'ideas'),
            ],
            'avaliacao_inicial' => [
                'label'       => __('Avaliação Inicial', 'ideas'),
                'description' => __('A equipe responsável analisa o alinhamento com a campanha.', 'ideas'),
            ],
            'avaliacao_tecnica' => [
                'label'       => __('Aprovação Técnica', 'ideas'),
                'description' => __('A área impactada analisa viabilidade, riscos e recursos necessários.', 'ideas'),
            ],
            'comite' => [
                'label'       => __('Aprovação do Comitê', 'ideas'),
                'description' => __('O comitê valida o alinhamento estratégico e prioriza os próximos passos.', 'ideas'),
            ],
            'implementacao' => [
                'label'       => __('Implementação', 'ideas'),
                'description' => __('A ideia ganha vida e passa a ser monitorada em produção.', 'ideas'),
            ],
        ];
    }

    private static function getWorkflowStatusMap(): array {
        $map = [
            Ticket::WAITING   => 'rascunho',
            Ticket::INCOMING  => 'avaliacao_inicial',
            Ticket::ASSIGNED  => 'avaliacao_tecnica',
            Ticket::PLANNED   => 'comite',
            Ticket::SOLVED    => 'implementacao',
            Ticket::CLOSED    => 'implementacao',
        ];

        if (defined('Ticket::ACCEPTED')) {
            $map[Ticket::ACCEPTED] = 'avaliacao_tecnica';
        }

        if (defined('Ticket::CANCELLED')) {
            $map[Ticket::CANCELLED] = 'comite';
        }

        return $map;
    }

    public static function getWorkflowStageByStatus(int $status_id): string {
        $map = self::getWorkflowStatusMap();

        if (isset($map[$status_id])) {
            return $map[$status_id];
        }

        return 'avaliacao_inicial';
    }

    public static function getWorkflowTimeline(int $status_id): array {
        $definitions  = self::getWorkflowStageDefinitions();
        $stageKeys    = array_keys($definitions);
        $currentStage = self::getWorkflowStageByStatus($status_id);

        if (!in_array($currentStage, $stageKeys, true)) {
            $currentStage = reset($stageKeys) ?: 'rascunho';
        }

        $currentIndex = array_search($currentStage, $stageKeys, true);

        $steps = [];
        foreach ($stageKeys as $index => $key) {
            $state = 'pending';
            if ($index < $currentIndex) {
                $state = 'complete';
            } elseif ($index === $currentIndex) {
                $state = 'current';
            }

            $steps[] = [
                'key'         => $key,
                'label'       => $definitions[$key]['label'],
                'description' => $definitions[$key]['description'],
                'state'       => $state,
                'position'    => $index + 1,
            ];
        }

        return [
            'current_key'   => $currentStage,
            'current_label' => $definitions[$currentStage]['label'],
            'steps'         => $steps,
        ];
    }
}
