<?php
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '../../../');
}

$AJAX_INCLUDE = 1;
include (GLPI_ROOT . "inc/includes.php");

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../inc/logger.php';
require_once __DIR__ . '/../inc/campanha.creator.php';

error_log("PROCESSAR_CAMPANHA: Iniciando - Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'NONE'));

$next_csrf_token = null;

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("PROCESSAR_CAMPANHA: Método não é POST");
        throw new RuntimeException(__('Método não suportado.', 'ideas'));
    }

    error_log("PROCESSAR_CAMPANHA: POST OK, verificando CSRF");

    if (method_exists('Session', 'checkCSRF')) {
        Session::checkCSRF($_POST);

        if (method_exists('Session', 'getNewCSRFToken')) {
            $next_csrf_token = Session::getNewCSRFToken();
        }
    }

    $dados = [
        'titulo'          => trim($_POST['titulo'] ?? ''),
        'campanha_pai_id' => (int) ($_POST['campanha_pai_id'] ?? 0),
        'descricao'       => $_POST['descricao'] ?? '',
        'beneficios'      => $_POST['beneficios'] ?? '',
        'prazo_estimado'  => trim($_POST['prazo_estimado'] ?? '')
    ];

    error_log("PROCESSAR_CAMPANHA: Dados validados, criando campanha: " . json_encode($dados));

    $resultado = PluginIdeasCampanhaCreator::createCampanhaTicket($dados, $_FILES['anexos'] ?? []);

    error_log("PROCESSAR_CAMPANHA: Resultado: " . json_encode($resultado));

    if (!$resultado['success']) {
        error_log("PROCESSAR_CAMPANHA: Falha - " . ($resultado['message'] ?? 'sem mensagem'));
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

    error_log("PROCESSAR_CAMPANHA: Enviando resposta JSON");
    echo json_encode($resultado);
    error_log("PROCESSAR_CAMPANHA: Resposta enviada com sucesso");
} catch (Throwable $exception) {
    error_log("PROCESSAR_CAMPANHA: EXCEPTION - " . $exception->getMessage());
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

    if ($next_csrf_token === null && method_exists('Session', 'getNewCSRFToken')) {
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
