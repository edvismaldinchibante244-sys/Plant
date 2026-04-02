<?php

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$perfil = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
$perfil = str_replace('GARÇOM', 'GARCOM', $perfil);
if (!in_array($perfil, ['ADMIN', 'GARCOM'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$restauranteId = (int)($_SESSION['restaurante_id'] ?? 0);
if ($restauranteId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Restaurante nao identificado.']);
    exit;
}

try {
    $db = (new Database())->getConnection();

    $stmtResumo = $db->prepare("
        SELECT
            COUNT(*) AS total_ativas,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) AS confirmadas
        FROM reservas
        WHERE restaurante_id = :restaurante_id
          AND data_reserva >= CURDATE()
          AND status IN ('pendente', 'confirmado')
    ");
    $stmtResumo->execute([':restaurante_id' => $restauranteId]);
    $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtUltima = $db->prepare("
        SELECT
            r.id,
            r.nome_cliente,
            r.data_reserva,
            r.hora_reserva,
            r.status,
            r.mesa_atribuida,
            m.numero AS mesa_numero,
            r.criado_em
        FROM reservas r
        LEFT JOIN mesas m ON m.id = r.mesa_atribuida
        WHERE r.restaurante_id = :restaurante_id
          AND r.data_reserva >= CURDATE()
          AND r.status = 'pendente'
        ORDER BY r.id DESC
        LIMIT 1
    ");
    $stmtUltima->execute([':restaurante_id' => $restauranteId]);
    $ultima = $stmtUltima->fetch(PDO::FETCH_ASSOC) ?: null;

    echo json_encode([
        'success' => true,
        'restaurante_id' => $restauranteId,
        'total_ativas' => (int)($resumo['total_ativas'] ?? 0),
        'pendentes' => (int)($resumo['pendentes'] ?? 0),
        'confirmadas' => (int)($resumo['confirmadas'] ?? 0),
        'ultima_pendente' => $ultima ? [
            'id' => (int)($ultima['id'] ?? 0),
            'nome_cliente' => (string)($ultima['nome_cliente'] ?? ''),
            'data_reserva' => (string)($ultima['data_reserva'] ?? ''),
            'hora_reserva' => (string)($ultima['hora_reserva'] ?? ''),
            'status' => (string)($ultima['status'] ?? ''),
            'mesa_atribuida' => (int)($ultima['mesa_atribuida'] ?? 0),
            'mesa_numero' => $ultima['mesa_numero'] ?? null,
            'criado_em' => (string)($ultima['criado_em'] ?? ''),
        ] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[RESERVAS_ALERTAS][ERROR] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao consultar alertas de reservas.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
