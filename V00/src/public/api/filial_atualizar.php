<?php

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/csrf.php';
include_once __DIR__ . '/../../config/filiais_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!checkPermission(['ADMIN'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Apenas administradores podem editar filiais.',
    ]);
    exit;
}

csrf_validate_or_json();

$filialId = (int)($_POST['filial_id'] ?? 0);

try {
    $db = (new Database())->getConnection();
    $contexto = filiais_obter_contexto(
        $db,
        (int)($_SESSION['restaurante_id'] ?? 0),
        (int)($_SESSION['matriz_id'] ?? 0)
    );

    if (empty($contexto['tem_multi_filial'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Seu plano atual nao possui acesso a multi-filial.',
        ]);
        exit;
    }

    $resultado = filiais_atualizar($db, (int)$contexto['matriz_id'], $filialId, $_POST);
    echo json_encode($resultado);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[FILIAIS][ATUALIZAR][ERROR] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Nao foi possivel atualizar a filial agora. Tente novamente.',
    ]);
}
