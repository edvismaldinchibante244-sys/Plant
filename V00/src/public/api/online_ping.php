<?php
session_start();

include_once '../../config/auth_check.php';
include_once '../../config/database.php';
include_once '../../config/presenca_online.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $usuarioId = intval($_SESSION['usuario_id'] ?? 0);
    $restauranteId = intval($_SESSION['restaurante_id'] ?? 0);

    if ($usuarioId <= 0 || $restauranteId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessao invalida.']);
        exit;
    }

    $db = (new Database())->getConnection();
    if (!$db instanceof PDO || !presenca_ping_usuario($db, $usuarioId, $restauranteId)) {
        throw new RuntimeException('Falha ao registrar presenca.');
    }

    $_SESSION['last_presence_ping'] = time();

    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha ao atualizar presenca.']);
}
