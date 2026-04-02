<?php

include_once '../../config/auth_check.php';
include_once '../../config/database.php';
include_once '../../config/super_admin_permissions.php';
include_once '../../config/turno_helpers.php';
include_once '../../Service/TurnoService.php';

header('Content-Type: application/json; charset=utf-8');

$isSuperAdmin = isset($_SESSION['logado'], $_SESSION['super_admin'])
    && $_SESSION['logado'] === true
    && intval($_SESSION['super_admin']) === 1;

if (!$isSuperAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

super_admin_require_permission_json('manage_users');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}

$acao = strtolower(trim((string)($data['acao'] ?? '')));
$restauranteId = (int)($data['restaurante_id'] ?? 0);
$funcionarioId = (int)($data['funcionario_id'] ?? 0);
$motivo = trim((string)($data['motivo'] ?? ''));

if ($restauranteId <= 0 || $funcionarioId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$contexto = [
    'usuario_id' => (int)($_SESSION['usuario_id'] ?? 0),
    'restaurante_id' => $restauranteId,
    'perfil' => 'ADMIN',
];

try {
    $turnoService = new TurnoService(new Database());
    switch ($acao) {
        case 'abrir_manual':
            $resultado = $turnoService->abrirTurnoManual($contexto, $funcionarioId, $motivo, $data['turno'] ?? null);
            break;
        case 'fechar_manual':
            $resultado = $turnoService->fecharTurnoManual($contexto, $funcionarioId, $motivo);
            break;
        default:
            http_response_code(400);
            $resultado = ['success' => false, 'message' => 'Ação inválida.'];
            break;
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar operação de turno.'], JSON_UNESCAPED_UNICODE);
}
