<?php
/**
 * AJAX - Vincular/Desvincular Ideia a Campanha
 */

define('GLPI_ROOT', dirname(__DIR__, 3));

include GLPI_ROOT . '/inc/includes.php';

Session::checkLoginUser();

global $DB;

$action = $_POST['action'] ?? '';

if ($action === 'get_campaigns') {
    header('Content-Type: application/json; charset=UTF-8');

    $config = PluginIdeasConfig::getConfig();
    $campaign_category_id = (int) ($config['campaign_category_id'] ?? 0);

    if ($campaign_category_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Categoria de campanha não configurada.'
        ]);
        exit;
    }

    $campaigns = PluginIdeasTicket::getCampaigns(['is_active' => true]);
    $campaigns_payload = [];

    foreach ($campaigns as $campaign) {
        $deadline = $campaign['time_to_resolve'] ?? null;
        $deadline_formatted = null;

        if (!empty($deadline)) {
            $timestamp = strtotime($deadline);
            if ($timestamp) {
                $deadline_formatted = date('d/m/Y', $timestamp);
            }
        }

        $campaigns_payload[] = [
            'id'       => (int) $campaign['id'],
            'name'     => $campaign['name'],
            'deadline' => $deadline_formatted,
            'status'   => Ticket::getStatus($campaign['status'])
        ];
    }

    echo json_encode([
        'success'   => true,
        'campaigns' => $campaigns_payload
    ]);
    exit;
}

if (method_exists('Session', 'checkCSRF')) {
    if (!isset($_POST['_glpi_csrf_token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Token CSRF inválido ou ausente'
        ]);
        exit;
    }
    
    try {
        Session::checkCSRF($_POST);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Falha na verificação CSRF: ' . $e->getMessage()
        ]);
        exit;
    }
}

$ticket_id   = (int) ($_POST['ticket_id'] ?? 0);
$campaign_id = (int) ($_POST['campaign_id'] ?? 0);
$user_id     = Session::getLoginUserID();

if ($ticket_id <= 0) {
    Session::addMessageAfterRedirect('Ticket inválido', false, ERROR);
    Html::back();
}

$config = PluginIdeasConfig::getConfig();
$idea_category_id = (int) ($config['idea_category_id'] ?? 0);
$campaign_category_id = (int) ($config['campaign_category_id'] ?? 0);

$idea = new Ticket();
if (!$idea->getFromDB($ticket_id)) {
    Session::addMessageAfterRedirect('Ideia não encontrada', false, ERROR);
    Html::back();
}

if ($idea_category_id > 0 && (int) $idea->fields['itilcategories_id'] !== $idea_category_id) {
    Session::addMessageAfterRedirect('Ticket informado não é uma ideia válida', false, ERROR);
    Html::back();
}

if ($action === 'unlink') {
    $table = PluginIdeasIdeaCampaign::getTable();
    $existing = $DB->request([
        'SELECT' => ['id', 'campaigns_id'],
        'FROM'   => $table,
        'WHERE'  => ['ideas_id' => $ticket_id],
        'LIMIT'  => 1
    ]);

    if (count($existing) === 0) {
        Session::addMessageAfterRedirect('Nenhuma campanha vinculada', false, INFO);
        Html::back();
    }

    $row = $existing->current();
    $link_id = (int) ($row['id'] ?? 0);
    $linked_campaign_id = (int) ($row['campaigns_id'] ?? 0);

    if ($link_id > 0 && $DB->delete($table, ['id' => $link_id])) {
        PluginIdeasLog::logAction('idea_unlinked_campaign', $user_id, [
            'idea_id'     => $ticket_id,
            'campaign_id' => $linked_campaign_id
        ]);

        Session::addMessageAfterRedirect('Ideia desvinculada com sucesso!', false, INFO);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao desvincular ideia da campanha'
        ]);
        exit;
    }
}

// ✅ LINK
if ($campaign_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Campanha inválida'
    ]);
    exit;
}

if ($campaign_id <= 0) {
    Session::addMessageAfterRedirect('Campanha inválida', false, ERROR);
    Html::back();
}

$campaign = new Ticket();
if (!$campaign->getFromDB($campaign_id)) {
    Session::addMessageAfterRedirect('Campanha não encontrada', false, ERROR);
    Html::back();
}

if ($campaign_id <= 0) {
    Session::addMessageAfterRedirect('Campanha inválida', false, ERROR);
    Html::back();
}

$campaign = new Ticket();
if (!$campaign->getFromDB($campaign_id)) {
    Session::addMessageAfterRedirect('Campanha não encontrada', false, ERROR);
    Html::back();
}

if ($campaign_category_id > 0 && (int) $campaign->fields['itilcategories_id'] !== $campaign_category_id) {
    Session::addMessageAfterRedirect('Ticket informado não é uma campanha válida', false, ERROR);
    Html::back();
}

// Inserir ou atualizar diretamente na tabela de vínculos
$table = PluginIdeasIdeaCampaign::getTable();
$timestamp = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

$existing = $DB->request([
    'SELECT' => ['id'],
    'FROM'   => $table,
    'WHERE'  => ['ideas_id' => $ticket_id],
    'LIMIT'  => 1
]);

$data = [
    'campaigns_id'  => $campaign_id,
    'users_id'      => $user_id > 0 ? $user_id : null,
    'date_creation' => $timestamp
];

$linked = false;

if (count($existing) > 0) {
    $row = $existing->current();
    $linked = $DB->update($table, $data, ['id' => (int) ($row['id'] ?? 0)]);
} else {
    $linked = $DB->insert($table, ['ideas_id' => $ticket_id] + $data);
}

if ($linked) {
    PluginIdeasLog::logAction('idea_linked_campaign', $user_id, [
        'idea_id'     => $ticket_id,
        'campaign_id' => $campaign_id
    ]);

    Session::addMessageAfterRedirect('Ideia vinculada com sucesso!', false, INFO);
} else {
    Session::addMessageAfterRedirect('Erro ao vincular ideia à campanha', false, ERROR);
}

Html::back();
