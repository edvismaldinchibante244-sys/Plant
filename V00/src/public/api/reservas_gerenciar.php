<?php

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../Model/Reserva.php';
require_once __DIR__ . '/../../Service/ReservaService.php';
require_once __DIR__ . '/../../api/reserva_notificacoes.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido.']);
    exit;
}

if (!csrf_is_valid()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sessao expirada ou token invalido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados invalidos.']);
    exit;
}

$restauranteId = (int)($_SESSION['restaurante_id'] ?? 0);
$reservaId = (int)($data['reserva_id'] ?? 0);
$acaoEntrada = strtolower(trim((string)($data['acao'] ?? $data['status'] ?? '')));
$mesaId = (int)($data['mesa_id'] ?? $data['mesa_atribuida'] ?? 0);

$acao = match ($acaoEntrada) {
    'confirmada', 'confirmado', 'confirmar' => 'confirmar',
    'cancelada', 'cancelado', 'cancelar' => 'cancelar',
    'no-show', 'no_show', 'noshow' => 'no-show',
    'checkin', 'check-in' => 'checkin',
    default => '',
};

if ($restauranteId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autorizado.']);
    exit;
}

if ($reservaId <= 0 || $acao === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Reserva ou acao invalida.']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $modelReserva = new \App\Model\Reserva($db);
    $serviceReserva = new \App\Service\ReservaService($db, $modelReserva);

    $resultado = match ($acao) {
        'confirmar' => $serviceReserva->confirmarReserva($reservaId, $restauranteId),
        'cancelar' => $serviceReserva->cancelarReserva($reservaId, $restauranteId),
        'no-show' => $serviceReserva->marcarNoShow($reservaId, $restauranteId),
        'checkin' => $mesaId > 0
            ? $serviceReserva->fazerCheckIn($reservaId, $mesaId, $restauranteId)
            : ['success' => false, 'message' => 'Mesa invalida para check-in.'],
        default => ['success' => false, 'message' => 'Acao nao suportada.'],
    };

    if ($resultado['success'] && !empty($data['origem']) && in_array($data['origem'], ['app', 'telefone', 'presencial'], true)) {
        $stmtOrigem = $db->prepare("
            UPDATE reservas
            SET origem = :origem, atualizado_em = NOW()
            WHERE id = :id
              AND restaurante_id = :restaurante_id
        ");
        $stmtOrigem->execute([
            ':origem' => $data['origem'],
            ':id' => $reservaId,
            ':restaurante_id' => $restauranteId,
        ]);
    }

    if (!empty($resultado['success']) && in_array($acao, ['confirmar', 'cancelar'], true)) {
        $statusNotificacao = $acao === 'confirmar' ? 'confirmado' : 'cancelado';
        if (!reserva_notificacoes_enviar_status_cliente($db, $reservaId, $statusNotificacao, $restauranteId)) {
            error_log('[RESERVAS][GERENCIAR][NOTIFICACAO] Falha ao notificar cliente da reserva ' . $reservaId . '.');
        }
    }

    if (!$resultado['success']) {
        http_response_code(422);
    }

    echo json_encode($resultado);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[RESERVAS][GERENCIAR][ERROR] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao atualizar a reserva.']);
}
