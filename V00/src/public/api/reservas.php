<?php

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../Model/Reserva.php';
require_once __DIR__ . '/../../Service/ReservaService.php';
require_once __DIR__ . '/../../api/reserva_notificacoes.php';

header('Content-Type: application/json; charset=utf-8');

$restauranteId = (int)($_SESSION['restaurante_id'] ?? 0);
if ($restauranteId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autorizado.']);
    exit;
}

$db = (new Database())->getConnection();
$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestPath = trim((string)($_GET['route'] ?? ''), '/');

$modelReserva = new \App\Model\Reserva($db);
$serviceReserva = new \App\Service\ReservaService($db, $modelReserva);

function reservas_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function reservas_require_csrf_if_needed(string $requestMethod): void
{
    if ($requestMethod === 'GET') {
        return;
    }

    if (csrf_is_valid()) {
        return;
    }

    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Sessao expirada ou token invalido. Recarregue a pagina.',
    ]);
    exit;
}

function reservas_notificar_cliente_status(PDO $db, int $reservaId, int $restauranteId, string $status): void
{
    if (!reserva_notificacoes_enviar_status_cliente($db, $reservaId, $status, $restauranteId)) {
        error_log('[RESERVAS][API][NOTIFICACAO] Falha ao notificar cliente da reserva ' . $reservaId . '.');
    }
}

reservas_require_csrf_if_needed($requestMethod);

try {
    if ($requestMethod === 'GET' && $requestPath === '') {
        $data = trim((string)($_GET['data'] ?? date('Y-m-d')));
        $reservas = $modelReserva->obterPorData($restauranteId, $data);

        echo json_encode([
            'success' => true,
            'data' => $reservas,
            'total' => count($reservas),
            'ocupacao' => $serviceReserva->calcularOcupacao($restauranteId, $data),
        ]);
        exit;
    }

    if ($requestMethod === 'GET' && $requestPath === 'disponibilidade') {
        $data = trim((string)($_GET['data'] ?? date('Y-m-d')));
        $hora = trim((string)($_GET['hora'] ?? '19:00'));
        $quantidade = (int)($_GET['quantidade'] ?? 2);

        $mesasDisponiveis = $serviceReserva->validarDisponibilidade(
            $restauranteId,
            $data,
            $hora,
            $quantidade
        );

        echo json_encode([
            'success' => true,
            'mesas_disponiveis' => $mesasDisponiveis,
            'total' => count($mesasDisponiveis),
        ]);
        exit;
    }

    if ($requestMethod === 'GET' && is_numeric($requestPath)) {
        $reserva = $modelReserva->obter((int)$requestPath, $restauranteId);
        if (!$reserva) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Reserva nao encontrada.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $reserva]);
        exit;
    }

    if ($requestMethod === 'POST' && $requestPath === '') {
        $dados = reservas_read_json_body();
        if (empty($dados)) {
            $dados = $_POST;
        }
        $dados['restaurante_id'] = $restauranteId;

        $resultado = $serviceReserva->criarReserva($dados);
        if (!$resultado['success']) {
            http_response_code(($resultado['code'] ?? '') === 'SEM_DISPONIBILIDADE' ? 409 : 422);
        }

        echo json_encode($resultado);
        exit;
    }

    if (preg_match('/^(\d+)\/confirmar$/', $requestPath, $m) && in_array($requestMethod, ['PUT', 'POST'], true)) {
        $resultado = $serviceReserva->confirmarReserva((int)$m[1], $restauranteId);
        if (!empty($resultado['success'])) {
            reservas_notificar_cliente_status($db, (int)$m[1], $restauranteId, 'confirmado');
        }
        if (!$resultado['success']) {
            http_response_code(422);
        }

        echo json_encode($resultado);
        exit;
    }

    if (preg_match('/^(\d+)\/checkin$/', $requestPath, $m) && in_array($requestMethod, ['PUT', 'POST'], true)) {
        $dados = reservas_read_json_body();
        if (empty($dados)) {
            $dados = $_POST;
        }
        $mesaId = (int)($dados['mesa_id'] ?? 0);

        if ($mesaId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Mesa invalida.']);
            exit;
        }

        $resultado = $serviceReserva->fazerCheckIn((int)$m[1], $mesaId, $restauranteId);
        if (!$resultado['success']) {
            http_response_code(422);
        }

        echo json_encode($resultado);
        exit;
    }

    if (preg_match('/^(\d+)\/no-show$/', $requestPath, $m) && in_array($requestMethod, ['PUT', 'POST'], true)) {
        $resultado = $serviceReserva->marcarNoShow((int)$m[1], $restauranteId);
        if (!$resultado['success']) {
            http_response_code(422);
        }

        echo json_encode($resultado);
        exit;
    }

    if (is_numeric($requestPath) && in_array($requestMethod, ['DELETE', 'POST'], true)) {
        $resultado = $serviceReserva->cancelarReserva((int)$requestPath, $restauranteId);
        if (!empty($resultado['success'])) {
            reservas_notificar_cliente_status($db, (int)$requestPath, $restauranteId, 'cancelado');
        }
        if (!$resultado['success']) {
            http_response_code(422);
        }

        echo json_encode($resultado);
        exit;
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Endpoint de reservas nao encontrado.']);
} catch (\InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[RESERVAS][API][ERROR] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao processar a reserva.',
    ]);
}
