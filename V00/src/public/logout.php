<?php

/*
   PROCESSAMENTO DE LOGOUT

*/

session_start();

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/presenca_online.php';
include_once __DIR__ . '/../config/turno_helpers.php';
include_once __DIR__ . '/../Service/TurnoService.php';

$usuarioId = intval($_SESSION['usuario_id'] ?? 0);
$restauranteId = intval($_SESSION['restaurante_id'] ?? 0);
$perfilSessao = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
if ($perfilSessao === 'GARÇOM') {
    $perfilSessao = 'GARCOM';
}

if ($usuarioId > 0 && $restauranteId > 0) {
    try {
        $db = (new Database())->getConnection();
        if ($db instanceof PDO) {
            presenca_marcar_usuario_offline($db, $usuarioId, $restauranteId);
        }
    } catch (Throwable $e) {
        // Logout nao deve falhar se a atualizacao de presenca falhar.
    }
}

// Encerrar turno automaticamente para perfis operacionais (GARCOM/BAR/COZINHA)
try {
    if (in_array($perfilSessao, ['GARCOM', 'BAR', 'COZINHA'], true) && $usuarioId > 0 && $restauranteId > 0) {
        $turnoService = new TurnoService(new Database());
        $turnoService->encerrarTurno($usuarioId, $restauranteId, ['manual' => false]);
    }
} catch (Throwable $e) {
    // Logout nao deve falhar se encerrar turno falhar.
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir a sessão
session_destroy();

// Redirecionar para login
header("Location: index.php");
exit;
