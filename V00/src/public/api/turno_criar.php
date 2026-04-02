<?php
/*
  API - Criar Turno Funcionário
 */
include_once '../../config/auth_check.php';
header('Content-Type: application/json');
include_once '../../config/database.php';
include_once '../../config/restaurante_context.php';
include_once '../../Service/TurnoService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST apenas']);
    exit;
}

if (!checkPermission(['ADMIN', 'CAIXA'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ADMIN ou CAIXA apenas']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$data['restaurante_id'] = session_restaurante_contexto_id();
$data['responsavel_id'] = (int)($_SESSION['usuario_id'] ?? 0);
$data['manual'] = 1;

try {
    $turnoService = new TurnoService(new Database());
    $resultado = $turnoService->criarTurno($data);
    echo json_encode($resultado);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao criar turno.']);
}
