<?php
/*
   API - Atualizar Turno Funcionário
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

if (!checkPermission(['ADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ADMIN apenas']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = intval($data['id'] ?? 0);
$data['responsavel_id'] = (int)($_SESSION['usuario_id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    $turnoService = new TurnoService(new Database());
    $resultado = $turnoService->atualizarTurno($id, $data, session_restaurante_contexto_id());
    echo json_encode($resultado);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao atualizar turno.']);
}
