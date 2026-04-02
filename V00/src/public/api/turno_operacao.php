<?php

include_once '../../config/auth_check.php';
include_once '../../config/database.php';
include_once '../../config/restaurante_context.php';
include_once '../../config/turno_helpers.php';
include_once '../../Service/TurnoService.php';

header('Content-Type: application/json; charset=utf-8');

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
$restauranteId = session_restaurante_contexto_id();
$contexto = [
    'usuario_id' => (int)($_SESSION['usuario_id'] ?? 0),
    'restaurante_id' => $restauranteId,
    'perfil' => $_SESSION['perfil'] ?? '',
];

try {
    $turnoService = new TurnoService(new Database());

    switch ($acao) {
        case 'iniciar':
            $resultado = $turnoService->iniciarTurno(
                $contexto['usuario_id'],
                $restauranteId,
                $data['turno'] ?? null
            );
            break;

        case 'encerrar':
            $resultado = $turnoService->encerrarTurno(
                $contexto['usuario_id'],
                $restauranteId
            );
            break;

        case 'abrir_manual':
            $funcionarioId = (int)($data['funcionario_id'] ?? 0);
            $motivo = trim((string)($data['motivo'] ?? ''));
            $resultado = $turnoService->abrirTurnoManual($contexto, $funcionarioId, $motivo, $data['turno'] ?? null);
            break;

        case 'fechar_manual':
            $funcionarioId = (int)($data['funcionario_id'] ?? 0);
            $motivo = trim((string)($data['motivo'] ?? ''));
            $resultado = $turnoService->fecharTurnoManual($contexto, $funcionarioId, $motivo);
            break;

        case 'status':
            $turno = $turnoService->obterTurnoAtivoUsuario($contexto['usuario_id'], $restauranteId);
            $resultado = [
                'success' => true,
                'turno_ativo' => $turno,
            ];
            break;

        default:
            http_response_code(400);
            $resultado = ['success' => false, 'message' => 'Ação inválida.'];
            break;
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar operação de turno.',
    ], JSON_UNESCAPED_UNICODE);
}
