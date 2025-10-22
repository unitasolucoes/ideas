<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/logger.php';

class PluginIdeasCampanhaCreator {

    private const MAX_ATTACHMENT_SIZE = 104857600; // 100 MB

    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
    ];

    public static function createCampanhaTicket(array $dados, array $files = []): array {
        global $CFG_GLPI;

        try {
            self::validateBasic($dados);

            $ticketData = self::prepareTicketData($dados);
            $ticket = new Ticket();
            $ticketId = $ticket->add($ticketData);

            if (!$ticketId) {
                $errorMessage = 'Falha ao criar ticket da campanha';
                if (isset($_SESSION['MESSAGE_AFTER_REDIRECT']) && !empty($_SESSION['MESSAGE_AFTER_REDIRECT'])) {
                    $messages = $_SESSION['MESSAGE_AFTER_REDIRECT'];
                    if (is_array($messages)) {
                        $errorDetails = [];
                        foreach ($messages as $msgType => $msgArray) {
                            if (is_array($msgArray)) {
                                foreach ($msgArray as $msg) {
                                    $errorDetails[] = strip_tags($msg);
                                }
                            }
                        }
                        if (!empty($errorDetails)) {
                            $errorMessage .= ': ' . implode('; ', $errorDetails);
                        }
                    }
                }

                PluginIdeasLogger::error('campaign_ticket_add_failed', $errorMessage, [
                    'ticket_data' => $ticketData
                ]);

                throw new RuntimeException(__('Não foi possível criar o ticket da campanha.', 'ideas'));
            }

            self::addRequesterToTicket($ticketId);
            $attachments = self::processAttachments($ticketId, $files);

            if (class_exists('PluginIdeasLog')) {
                PluginIdeasLog::logAction('campaign_created', Session::getLoginUserID(), [
                    'ticket_id' => $ticketId
                ]);
            }

            PluginIdeasLogger::info(
                'campaign_creation_success',
                sprintf('Campanha criada (ticket #%d)', $ticketId),
                [
                    'ticket_id' => $ticketId,
                    'payload'   => PluginIdeasLogger::sanitizeArray([
                        'titulo'          => $dados['titulo'] ?? null,
                        'campanha_pai_id' => $dados['campanha_pai_id'] ?? null,
                        'prazo_estimado'  => $dados['prazo_estimado'] ?? null
                    ]),
                    'attachments' => ['count' => $attachments]
                ]
            );

            return [
                'success'       => true,
                'ticket_id'     => $ticketId,
                'ticket_link'   => $CFG_GLPI['url_base'] . Plugin::getWebDir('ideas') . '/front/campaign.php?id=' . $ticketId,
                'anexos_count'  => $attachments,
                'message'       => __('Campanha criada com sucesso!', 'ideas')
            ];
        } catch (Throwable $exception) {
            PluginIdeasLogger::error(
                'campaign_creation_exception',
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

    private static function validateBasic(array $dados): void {
        Session::checkLoginUser();
    }

    private static function prepareTicketData(array $dados): array {
        $config = PluginIdeasConfig::getConfig();
        $campaignCategory = (int) ($config['campaign_category_id'] ?? 0);
        $currentTime = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $title = self::resolveTicketTitle($dados, $currentTime);
        $content = self::generateTicketContent($dados);

        $ticketType = defined('Ticket::DEMAND_TYPE') ? Ticket::DEMAND_TYPE : 2;

        $ticketData = [
            'name'               => Toolbox::addslashes_deep($title),
            'content'            => Toolbox::addslashes_deep($content),
            'status'             => Ticket::INCOMING,
            'type'               => $ticketType,
            'priority'           => 3,
            'urgency'            => 3,
            'impact'             => 3,
            'entities_id'        => $_SESSION['glpiactive_entity'] ?? 0,
            'users_id_recipient' => Session::getLoginUserID(),
            'itilcategories_id'  => $campaignCategory,
            'date'               => $currentTime,
            'date_mod'           => $currentTime
        ];

        return $ticketData;
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
                'campaign_attachment_failure',
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

    public static function resolveTicketTitle(array $dados, string $currentTime): string {
        $title = trim($dados['titulo'] ?? '');

        if ($title !== '') {
            return $title;
        }

        $timestamp = strtotime($currentTime);
        if ($timestamp === false) {
            $timestamp = time();
        }

        return sprintf(
            '%s %s',
            __('Campanha automática', 'ideas'),
            date('d/m/Y H:i', $timestamp)
        );
    }

    private static function generateTicketContent(array $dados): string {
        $descricao = trim($dados['descricao'] ?? '');

        if ($descricao !== '') {
            // Decodificar HTML entities que vêm do POST
            $descricao = html_entity_decode($descricao, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $descricao;
        }

        return __('Campanha criada automaticamente pelo portal de ideias.', 'ideas');
    }
}
