<?php

/**
 * Endpoint para obter um novo CSRF token
 * Útil quando o token do formulário expira
 */

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');

try {
    Session::checkLoginUser();

    $token = Session::getNewCSRFToken();

    echo json_encode([
        'success' => true,
        'token' => $token
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
