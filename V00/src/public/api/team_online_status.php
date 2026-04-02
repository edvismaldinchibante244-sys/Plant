<?php
session_start();

include_once '../../config/auth_check.php';
include_once '../../config/database.php';
include_once '../../config/presenca_online.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $restauranteId = intval($_SESSION['restaurante_id'] ?? 0);
    $isSuperAdmin = intval($_SESSION['super_admin'] ?? 0) === 1;

    if ($restauranteId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessao invalida.']);
        exit;
    }

    if (!$isSuperAdmin && !checkPermission(['ADMIN'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $db = (new Database())->getConnection();
    $teamOnline = presenca_buscar_equipa_online($db, $restauranteId, 12);

    echo json_encode([
        'success' => true,
        'online' => $teamOnline['online'],
        'total' => $teamOnline['total'],
        'equipa' => $teamOnline['equipa'],
        'updated_at' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha ao carregar equipe online.']);
}
