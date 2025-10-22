<?php

// Desabilitar validação automática de CSRF - vamos fazer manualmente
define('GLPI_USE_CSRF_CHECK', 0);
$AJAX_INCLUDE = 1;

include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../inc/logger.php';
require_once __DIR__ . '/../inc/ideia.creator.php';
require_once __DIR__ . '/../inc/idea_campaign.class.php';
require_once __DIR__ . '/../inc/userpoints.class.php';
require_once __DIR__ . '/../inc/pointhistory.class.php';
require_once __DIR__ . '/../inc/log.class.php';

$next_csrf_token = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException(__('Método não suportado.', 'ideas'));
    }

    // Validação manual de CSRF
    Session::checkCSRF($_POST);
    $next_csrf_token = Session::getNewCSRFToken();

    $acao = 'enviar';

    $dados = [
        'acao'                   => $acao,
        'titulo'                 => trim($_POST['titulo'] ?? ''),
        'campanha_id'            => (int) ($_POST['campanha_id'] ?? 0),
        'area_impactada'         => (int) ($_POST['area_impactada'] ?? 0),
        'problema_identificado'  => $_POST['problema_identificado'] ?? '',
        'solucao_proposta'       => $_POST['solucao_proposta'] ?? '',
        'beneficios_resultados'  => $_POST['beneficios_resultados'] ?? '',
        'autores'                => array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['autores'] ?? [])), static function ($id) {
            return $id > 0;
        }))), 0, 3)
    ];

    $resultado = PluginIdeasIdeiaCreator::createIdeiaTicket($dados, $_FILES['anexos'] ?? []);

    if (!$resultado['success']) {
        http_response_code(400);
        PluginIdeasLogger::error(
            'idea_process_failed',
            $resultado['message'] ?? 'Erro desconhecido ao criar ideia',
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
        'idea_process_exception',
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
