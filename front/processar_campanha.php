<?php

// Desabilitar validação automática de CSRF - vamos fazer manualmente
define('GLPI_USE_CSRF_CHECK', 0);
$AJAX_INCLUDE = 1;

include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../inc/logger.php';
require_once __DIR__ . '/../inc/campanha.creator.php';

$next_csrf_token = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException(__('Método não suportado.', 'ideas'));
    }

    // Validação manual de CSRF
    Session::checkCSRF($_POST);
    $next_csrf_token = Session::getNewCSRFToken();

    $dados = [
        'titulo'          => trim($_POST['titulo'] ?? ''),
        'campanha_pai_id' => (int) ($_POST['campanha_pai_id'] ?? 0),
        'descricao'       => $_POST['descricao'] ?? '',
        'beneficios'      => $_POST['beneficios'] ?? '',
        'prazo_estimado'  => trim($_POST['prazo_estimado'] ?? '')
    ];

    $resultado = PluginIdeasCampanhaCreator::createCampanhaTicket($dados, $_FILES['anexos'] ?? []);

    if (!$resultado['success']) {
        http_response_code(400);
        PluginIdeasLogger::error(
            'campaign_process_failed',
            $resultado['message'] ?? 'Erro desconhecido ao criar campanha',
            [
                'request' => PluginIdeasLogger::sanitizeArray($dados),
                'files'   => isset($_FILES['anexos']['name']) ? (array) $_FILES['anexos']['name'] : []
            ]
        );
    }

    if ($next_csrf_token) {
        $resultado['csrf_token'] = $next_csrf_token;
    }

    echo json_encode($resultado);

} catch (Throwable $exception) {
    PluginIdeasLogger::error(
        'campaign_process_exception',
        $exception->getMessage(),
        [
            'raw_post'  => PluginIdeasLogger::sanitizeArray($_POST ?? []),
            'files'     => isset($_FILES['anexos']['name']) ? (array) $_FILES['anexos']['name'] : [],
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'http_code' => http_response_code()
        ],
        $exception
    );

    if ($next_csrf_token === null) {
        $next_csrf_token = Session::getNewCSRFToken();
    }

    $payload = [
        'success' => false,
        'message' => $exception->getMessage()
    ];

    if ($next_csrf_token !== null) {
        $payload['csrf_token'] = $next_csrf_token;
    }

    echo json_encode($payload);
}
