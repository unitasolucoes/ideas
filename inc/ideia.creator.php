<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/logger.php';

class PluginIdeasIdeiaCreator {

    private const MAX_ATTACHMENT_SIZE = 104857600; // 100 MB

    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
    ];

    /**
     * @param array $dados Dados enviados pelo formulário
     * @param array $files Arquivos recebidos em $_FILES['anexos']
     *
     * @return array
     */
    public static function createIdeiaTicket(array $dados, array $files = []): array {
        global $CFG_GLPI;

        try {
            $acao = 'enviar';

            self::validateBasic($dados, $acao);

            $ticketData = self::prepareTicketData($dados);
            $ticket = new Ticket();
            $ticketId = $ticket->add($ticketData);

            if (!$ticketId) {
                throw new RuntimeException(__('Não foi possível criar o ticket da ideia.', 'ideas'));
            }

            self::addRequesterToTicket($ticketId);
            $attachments = self::processAttachments($ticketId, $files);

            // Vincular à campanha usando Ticket_Ticket (relação nativa do GLPI)
            $campanhaId = (int) ($dados['campanha_id'] ?? 0);
            if ($campanhaId > 0) {
                self::linkToCampaign($ticketId, $campanhaId);
            }

            if (class_exists('PluginIdeasUserPoints')) {
                PluginIdeasUserPoints::addPoints(Session::getLoginUserID(), 'submitted_idea', $ticketId, false);
            }

            if (class_exists('PluginIdeasLog')) {
                PluginIdeasLog::logAction('idea_created', Session::getLoginUserID(), [
                    'ticket_id'   => $ticketId,
                    'campaign_id' => (int) ($dados['campanha_id'] ?? 0)
                ]);
            }

            self::addAuthors($ticketId, $dados['autores'] ?? []);

            PluginIdeasLogger::info(
                'idea_creation_success',
                sprintf('Ideia criada (ticket #%d)', $ticketId),
                [
                    'ticket_id'   => $ticketId,
                    'campaign_id' => (int) ($dados['campanha_id'] ?? 0),
                    'payload'     => PluginIdeasLogger::sanitizeArray([
                        'titulo'                 => $dados['titulo'] ?? null,
                        'area_impactada'         => $dados['area_impactada'] ?? null,
                        'autores'                => array_values(array_map('intval', (array) ($dados['autores'] ?? [])))
                    ]),
                    'attachments' => ['count' => $attachments]
                ]
            );

            $message = __('Ideia enviada com sucesso!', 'ideas');

            return [
                'success'       => true,
                'ticket_id'     => $ticketId,
                'ticket_link'   => $CFG_GLPI['url_base'] . Plugin::getWebDir('ideas') . '/front/idea.php?id=' . $ticketId,
                'anexos_count'  => $attachments,
                'message'       => $message
            ];
        } catch (Throwable $exception) {
            PluginIdeasLogger::error(
                'idea_creation_exception',
                $exception->getMessage(),
                [
                    'payload' => PluginIdeasLogger::sanitizeArray($dados)
                ],
                $exception
            );

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    private static function validateBasic(array $dados, string $acao): void {
        Session::checkLoginUser();

        $titulo = trim($dados['titulo'] ?? '');

        if ($titulo === '') {
            throw new InvalidArgumentException(__('Informe o título da ideia.', 'ideas'));
        }

        $problema = trim(strip_tags($dados['problema_identificado'] ?? ''));
        $solucao = trim(strip_tags($dados['solucao_proposta'] ?? ''));
        $beneficios = trim(strip_tags($dados['beneficios_resultados'] ?? ''));
        $campanhaId = (int) ($dados['campanha_id'] ?? 0);
        $areaImpactadaId = (int) ($dados['area_impactada'] ?? 0);

        if ($problema === '') {
            throw new InvalidArgumentException(__('Descreva o problema identificado.', 'ideas'));
        }

        if ($solucao === '') {
            throw new InvalidArgumentException(__('Informe a solução proposta.', 'ideas'));
        }

        if ($beneficios === '') {
            throw new InvalidArgumentException(__('Informe os benefícios e resultados esperados.', 'ideas'));
        }

        if ($campanhaId <= 0) {
            throw new InvalidArgumentException(__('Selecione uma campanha válida.', 'ideas'));
        }

        if (!PluginIdeasTicket::isCampaign($campanhaId)) {
            throw new InvalidArgumentException(__('A campanha informada é inválida.', 'ideas'));
        }

        // Área impactada é opcional agora
        if ($areaImpactadaId > 0 && !self::isValidImpactArea($areaImpactadaId)) {
            throw new InvalidArgumentException(__('A área impactada selecionada não é válida.', 'ideas'));
        }

        $autores = array_values(array_filter(array_map('intval', (array) ($dados['autores'] ?? [])), static function ($value) {
            return $value > 0;
        }));

        if (empty($autores)) {
            throw new InvalidArgumentException(__('Selecione ao menos um autor para a ideia.', 'ideas'));
        }
    }

    private static function prepareTicketData(array $dados): array {
        $config = PluginIdeasConfig::getConfig();
        $ideaCategory = (int) ($config['idea_category_id'] ?? 153);
        $currentTime = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $campanhaInfo = self::getCampanhaInfo((int) ($dados['campanha_id'] ?? 0));
        $content = self::generateTicketContent($dados, $campanhaInfo);

        $ticketType = defined('Ticket::DEMAND_TYPE') ? Ticket::DEMAND_TYPE : 2;
        $status = Ticket::INCOMING;

        return [
            'name'               => Toolbox::addslashes_deep(trim($dados['titulo'])),
            'content'            => $content,
            'status'             => $status,
            'type'               => $ticketType,
            'priority'           => 3,
            'urgency'            => 3,
            'impact'             => 3,
            'entities_id'        => $_SESSION['glpiactive_entity'],
            'users_id_recipient' => Session::getLoginUserID(),
            'itilcategories_id'  => $ideaCategory,
            'date'               => $currentTime,
            'date_mod'           => $currentTime
        ];
    }

    private static function addRequesterToTicket(int $ticketId): void {
        $ticketUser = new Ticket_User();
        $ticketUser->add([
            'tickets_id' => $ticketId,
            'users_id'   => Session::getLoginUserID(),
            'type'       => CommonITILActor::REQUESTER
        ]);
    }

    private static function processAttachments(int $ticketId, array $files): int {
        if (empty($files) || !isset($files['name'])) {
            return 0;
        }

        $count = 0;
        $names = (array) $files['name'];
        $tmpNames = (array) ($files['tmp_name'] ?? []);
        $sizes = (array) ($files['size'] ?? []);
        $errors = (array) ($files['error'] ?? []);
        $types = (array) ($files['type'] ?? []);

        foreach ($names as $index => $name) {
            if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $file = [
                'name'     => $name,
                'tmp_name' => $tmpNames[$index] ?? null,
                'size'     => $sizes[$index] ?? 0,
                'type'     => $types[$index] ?? ''
            ];

            if (!self::isValidAttachment($file)) {
                continue;
            }

            if (self::storeAttachment($ticketId, $file)) {
                $count++;
            }
        }

        return $count;
    }

    private static function isValidAttachment(array $file): bool {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_ATTACHMENT_SIZE) {
            return false;
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        return true;
    }

    private static function storeAttachment(int $ticketId, array $file): bool {
        try {
            $document = new Document();
            $docId = $document->add([
                'name'                 => Toolbox::addslashes_deep($file['name']),
                'entities_id'          => $_SESSION['glpiactive_entity'],
                'documentcategories_id'=> 0,
                '_filename'            => [$file]
            ]);

            if (!$docId) {
                return false;
            }

            $documentItem = new Document_Item();
            $documentItem->add([
                'documents_id' => $docId,
                'itemtype'     => 'Ticket',
                'items_id'     => $ticketId
            ]);

            return true;
        } catch (Throwable $throwable) {
            PluginIdeasLogger::error(
                'idea_attachment_failure',
                $throwable->getMessage(),
                [
                    'ticket_id' => $ticketId,
                    'file'      => [
                        'name' => $file['name'] ?? null,
                        'size' => $file['size'] ?? null,
                        'type' => $file['type'] ?? null,
                    ]
                ],
                $throwable
            );
        }

        return false;
    }

    private static function getCampanhaInfo(int $campanhaId): array {
        if ($campanhaId <= 0) {
            return [];
        }

        $ticket = new Ticket();
        if (!$ticket->getFromDB($campanhaId)) {
            return [];
        }

        return $ticket->fields;
    }

    private static function sanitizeRichText(?string $value): string {
        $value = $value ?? '';

        if ($value === '') {
            return '';
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $allowedTags = '<p><br><strong><em><u><ol><ul><li><a><h1><h2><h3><h4><h5><h6><table><thead><tbody><tfoot><tr><th><td><div><span>'; 

        $sanitized = strip_tags($decoded, $allowedTags);

        // Remove event handler attributes like onclick, onmouseover, etc.
        $sanitized = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitized);

        // Bloqueia URLs com javascript: em links
        $sanitized = preg_replace_callback(
            '/href\s*=\s*("([^"]*)"|\'([^\']*)\')/i',
            static function (array $matches): string {
                $quote = $matches[1][0];
                $url = trim($matches[2] ?? $matches[3] ?? '');

                if (stripos($url, 'javascript:') === 0) {
                    return 'href=' . $quote . '#' . $quote;
                }

                return 'href=' . $quote . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $quote;
            },
            $sanitized
        );

        return $sanitized;
    }

    private static function generateTicketContent(array $dados, array $campanhaInfo): string {
        $problema = self::sanitizeRichText($dados['problema_identificado'] ?? '');
        $solucao = self::sanitizeRichText($dados['solucao_proposta'] ?? '');
        $beneficios = self::sanitizeRichText($dados['beneficios_resultados'] ?? '');

        $areaImpactadaId = (int) ($dados['area_impactada'] ?? 0);
        $areaImpactada = $areaImpactadaId > 0
            ? self::formatDisplayValue(self::getImpactAreaName($areaImpactadaId))
            : __('Não informado', 'ideas');

        $autoresIds = array_values(array_filter(array_map('intval', (array) ($dados['autores'] ?? [])), static function ($value) {
            return $value > 0;
        }));
        $autores = [];
        foreach ($autoresIds as $autorId) {
            $displayName = self::getUserDisplayName($autorId);
            if ($displayName !== null) {
                $autores[] = $displayName;
            }
        }

        $campanhaNome = $campanhaInfo['name'] ?? __('Campanha não encontrada', 'ideas');
        $campanhaPrazo = $campanhaInfo['time_to_resolve'] ?? null;
        if (empty($campanhaPrazo) || $campanhaPrazo === '0000-00-00 00:00:00') {
            $campanhaPrazo = null;
        }
        $campanhaPrazo = $campanhaPrazo ? Html::convDateTime($campanhaPrazo) : __('Não informado', 'ideas');

        ob_start();
        ?>
        <h2><?php echo __('Nova Ideia', 'ideas'); ?></h2>

        <div class="idea-summary">
            <p><strong><?php echo __('Campanha', 'ideas'); ?>:</strong> #<?php echo (int) ($campanhaInfo['id'] ?? 0); ?> - <?php echo Html::clean($campanhaNome); ?></p>
            <p><strong><?php echo __('Área impactada', 'ideas'); ?>:</strong> <?php echo $areaImpactada; ?></p>
            <p><strong><?php echo __('Prazo da campanha', 'ideas'); ?>:</strong> <?php echo Html::clean($campanhaPrazo); ?></p>
        </div>

        <div class="idea-section">
            <h3><?php echo __('Problema identificado', 'ideas'); ?></h3>
            <div class="idea-section__content">
                <?php echo $problema !== '' ? $problema : '<em>' . __('Não informado', 'ideas') . '</em>'; ?>
            </div>
        </div>

        <div class="idea-section">
            <h3><?php echo __('Solução proposta', 'ideas'); ?></h3>
            <div class="idea-section__content">
                <?php echo $solucao !== '' ? $solucao : '<em>' . __('Não informado', 'ideas') . '</em>'; ?>
            </div>
        </div>

        <div class="idea-section">
            <h3><?php echo __('Benefícios e resultados esperados', 'ideas'); ?></h3>
            <div class="idea-section__content">
                <?php echo $beneficios !== '' ? $beneficios : '<em>' . __('Não informado', 'ideas') . '</em>'; ?>
            </div>
        </div>

        <div class="idea-section">
            <h3><?php echo __('Autores da ideia', 'ideas'); ?></h3>
            <div class="idea-section__content">
                <?php echo Html::clean(empty($autores) ? __('Nenhum autor informado.', 'ideas') : implode(', ', $autores)); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function formatDisplayValue(?string $value, bool $applyClean = true): string {
        $value = trim((string) $value);

        if ($value === '') {
            return $applyClean ? __('Não informado', 'ideas') : '';
        }

        return $applyClean ? Html::clean($value) : $value;
    }

    private static function isValidImpactArea(int $groupId): bool {
        global $DB;

        if ($groupId <= 0) {
            return false;
        }

        $parentGroupId = PluginIdeasConfig::getParentGroupId();
        $where = [
            'id'         => $groupId,
            'is_deleted' => 0
        ];

        if ($parentGroupId > 0) {
            $where['groups_id'] = $parentGroupId;
        }

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_groups',
            'WHERE'  => $where,
            'LIMIT'  => 1
        ]);

        return count($iterator) > 0;
    }

    private static function getImpactAreaName(int $groupId): string {
        if ($groupId <= 0) {
            return '';
        }

        $group = new Group();
        if ($group->getFromDB($groupId)) {
            $name = trim((string) ($group->fields['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }

            $complete = trim((string) ($group->fields['completename'] ?? ''));
            if ($complete !== '') {
                return $complete;
            }
        }

        return '';
    }

    private static function getUserDisplayName(int $userId): ?string {
        if ($userId <= 0) {
            return null;
        }

        $user = new User();
        if (!$user->getFromDB($userId)) {
            return null;
        }

        $firstname = trim((string) ($user->fields['firstname'] ?? ''));
        $lastname  = trim((string) ($user->fields['realname'] ?? ''));
        $login     = trim((string) ($user->fields['name'] ?? ''));

        if ($firstname !== '' || $lastname !== '') {
            return trim($firstname . ' ' . $lastname);
        }

        return $login !== '' ? $login : null;
    }

    private static function addAuthors(int $ticketId, array $authorIds): void {
        if ($ticketId <= 0 || empty($authorIds)) {
            return;
        }

        $currentUserId = Session::getLoginUserID();
        $filtered = array_values(array_unique(array_filter(array_map('intval', $authorIds), static function ($id) use ($currentUserId) {
            return $id > 0 && $id !== $currentUserId;
        })));

        if (empty($filtered)) {
            return;
        }

        global $DB;

        $existing = [];
        $iterator = $DB->request([
            'SELECT' => 'users_id',
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
                'tickets_id' => $ticketId,
                'type'       => CommonITILActor::OBSERVER
            ]
        ]);
        foreach ($iterator as $row) {
            $existing[] = (int) $row['users_id'];
        }

        $ticketUser = new Ticket_User();
        foreach ($filtered as $userId) {
            if (in_array($userId, $existing, true)) {
                continue;
            }

            $ticketUser->add([
                'tickets_id' => $ticketId,
                'users_id'   => $userId,
                'type'       => CommonITILActor::OBSERVER
            ]);
        }
    }

    private static function linkToCampaign(int $ideaId, int $campanhaId): void {
        if ($ideaId <= 0 || $campanhaId <= 0) {
            return;
        }

        // Usar a classe nativa Ticket_Ticket do GLPI para criar a relação pai-filho
        $ticketTicket = new Ticket_Ticket();

        $linkData = [
            'tickets_id_1' => $campanhaId,  // Ticket pai (campanha)
            'tickets_id_2' => $ideaId,       // Ticket filho (ideia)
            'link'         => Ticket_Ticket::LINK_TO  // Tipo de relação: "linked to"
        ];

        $linkId = $ticketTicket->add($linkData);

        if ($linkId) {
            PluginIdeasLogger::info(
                'idea_campaign_linked',
                sprintf('Ideia #%d vinculada à campanha #%d', $ideaId, $campanhaId),
                [
                    'idea_id'     => $ideaId,
                    'campaign_id' => $campanhaId,
                    'link_id'     => $linkId
                ]
            );
        } else {
            PluginIdeasLogger::error(
                'idea_campaign_link_failed',
                sprintf('Falha ao vincular ideia #%d à campanha #%d', $ideaId, $campanhaId),
                [
                    'idea_id'     => $ideaId,
                    'campaign_id' => $campanhaId
                ]
            );
        }
    }

}
