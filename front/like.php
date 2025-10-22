<?php
define('GLPI_ROOT', dirname(__DIR__, 3));
include GLPI_ROOT . '/inc/includes.php';

Session::checkLoginUser();

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
$user_id   = Session::getLoginUserID();

if ($ticket_id <= 0) {
    Session::addMessageAfterRedirect('Ticket inválido', false, ERROR);
    Html::back();
}

global $DB;

// Verificar se já curtiu
$check = $DB->request([
    'FROM'  => 'glpi_plugin_ideas_likes',
    'WHERE' => [
        'tickets_id' => $ticket_id,
        'users_id'   => $user_id
    ]
]);

$has_liked = count($check) > 0;

if ($has_liked) {
    // DESCURTIR
    $DB->delete('glpi_plugin_ideas_likes', [
        'tickets_id' => $ticket_id,
        'users_id'   => $user_id
    ]);
    
    // Remover pontos
    $ticket = new Ticket();
    if ($ticket->getFromDB($ticket_id)) {
        $recipient_id = (int)($ticket->fields['users_id_recipient'] ?? 0);
        if ($recipient_id > 0) {
            PluginIdeasUserPoints::removePoints($recipient_id, 'like', $ticket_id);
        }
    }
    
    Session::addMessageAfterRedirect('Curtida removida', false, INFO);
} else {
    // CURTIR
    $DB->insert('glpi_plugin_ideas_likes', [
        'tickets_id'    => $ticket_id,
        'users_id'      => $user_id,
        'date_creation' => $_SESSION['glpi_currenttime']
    ]);
    
    // Adicionar pontos
    $ticket = new Ticket();
    if ($ticket->getFromDB($ticket_id)) {
        $recipient_id = (int)($ticket->fields['users_id_recipient'] ?? 0);
        if ($recipient_id > 0) {
            PluginIdeasUserPoints::addPoints($recipient_id, 'like', $ticket_id, true);
        }
    }
    
    Session::addMessageAfterRedirect('Curtida registrada', false, INFO);
}

// Redirecionar de volta
Html::back();