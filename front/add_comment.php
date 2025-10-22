<?php
define('GLPI_ROOT', dirname(__DIR__, 3));
include GLPI_ROOT . '/inc/includes.php';

// Limpar tudo
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');

// Verificar se está logado
if (!Session::getLoginUserID()) {
    echo json_encode(['success' => false, 'message' => 'Não logado']);
    exit;
}

// Pegar dados
$ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$user_id = Session::getLoginUserID();

// Validações básicas
if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ticket inválido']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Comentário vazio']);
    exit;
}

// Criar followup
$followup = new ITILFollowup();
$followup_id = $followup->add([
    'itemtype'   => 'Ticket',
    'items_id'   => $ticket_id,
    'users_id'   => $user_id,
    'content'    => $content,
    'is_private' => 0
]);

if (!$followup_id) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar followup']);
    exit;
}

// Registrar pontuação e log para o autor do comentário
PluginIdeasUserPoints::addPoints($user_id, 'comment', $ticket_id);
PluginIdeasLog::logAction('comment_added', $user_id, [
    'tickets_id' => $ticket_id,
    'followup_id' => $followup_id
]);

// Buscar dados do usuário
$user = new User();
$user->getFromDB($user_id);

$user_name = $user->getFriendlyName();

// Gerar iniciais CORRETAS
$realname = trim($user->fields['realname'] ?? '');
$firstname = trim($user->fields['firstname'] ?? '');
$username = trim($user->fields['name'] ?? '');

// Tentar pegar nome completo
$full_name = '';
if (!empty($realname) && !empty($firstname)) {
    $full_name = $firstname . ' ' . $realname;
} elseif (!empty($realname)) {
    $full_name = $realname;
} elseif (!empty($firstname)) {
    $full_name = $firstname;
} else {
    $full_name = $username;
}

// Gerar iniciais
$name_parts = explode(' ', trim($full_name));
if (count($name_parts) >= 2) {
    // Primeira letra do primeiro nome + primeira letra do último nome
    $user_initials = strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
} else {
    // Se tiver só um nome, pegar as 2 primeiras letras
    $user_initials = strtoupper(substr($full_name, 0, 2));
}

// Se ainda estiver vazio
if (empty($user_initials)) {
    $user_initials = '??';
}

// ✅ Retornar EXATAMENTE a estrutura que o JS espera
echo json_encode([
    'success' => true,
    'message' => 'Comentário adicionado com sucesso!',
    'comment' => [
        'id' => $followup_id,
        'user_name' => $user_name,
        'user_initials' => $user_initials, // ✅ CAMPO OBRIGATÓRIO
        'date' => Html::convDateTime($_SESSION['glpi_currenttime']),
        'content' => nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'))
    ]
]);
exit;