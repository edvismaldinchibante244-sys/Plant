<?php
session_start();

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
    if (!$db instanceof PDO || !presenca_marcar_usuario_offline($db, $usuarioId, $restauranteId)) {
        throw new RuntimeException('Falha ao marcar usuario como offline.');
    }

    unset($_SESSION['last_presence_ping']);

    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha ao atualizar presenca offline.']);
}
