<?php
/*
   API - Listar Turnos
 */
header('Content-Type: application/json');
include_once '../../config/auth_check.php';
include_once '../../config/database.php';
include_once '../../config/restaurante_context.php';
include_once '../../Service/TurnoService.php';

if (session_restaurante_contexto_id() <= 0) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!checkPermission(['ADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ADMIN apenas']);
    exit;
}

try {
    $turnoService = new TurnoService(new Database());
    $filtroData = trim((string)($_GET['data'] ?? ''));
    $data = $filtroData === '' ? null : $filtroData;

    // Mapear filtros amigáveis para datas válidas
    if ($data === 'hoje') {
        $data = date('Y-m-d');
    } elseif ($data === 'amanha') {
        $data = date('Y-m-d', strtotime('+1 day'));
    } elseif ($data === 'semana') {
        // Para semana, retorna todos e o front filtra pela data do período.
        $data = null;
    }

    $restauranteId = session_restaurante_contexto_id();
    $result = $turnoService->listarArray($restauranteId, $data);
    $ativosHoje = $turnoService->ativosHojeArray($restauranteId);
    $metricas = $turnoService->obterMetricasDashboard($restauranteId);
    $auditoria = $turnoService->listarAuditoria($restauranteId, 20);

    echo json_encode([
        'success' => true,
        'turnos' => $result,
        'ativos_hoje' => $ativosHoje,
        'metricas' => $metricas,
        'auditoria' => $auditoria
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar turnos'
    ]);
}
