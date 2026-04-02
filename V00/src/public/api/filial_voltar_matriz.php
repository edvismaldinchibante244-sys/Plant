<?php

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/csrf.php';
include_once __DIR__ . '/../../config/filiais_helper.php';

if (!checkPermission(['ADMIN'])) {
    header('Location: ../dashboard.php?erro=acesso_negado');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
    header('Location: ../filiais.php?tipo=error&msg=' . urlencode('Sessao expirada. Recarregue a pagina e tente novamente.'));
    exit;
}

try {
    $db = (new Database())->getConnection();
    $contexto = filiais_obter_contexto(
        $db,
        (int)($_SESSION['restaurante_id'] ?? 0),
        (int)($_SESSION['matriz_id'] ?? 0)
    );

    $resultado = filiais_retornar_para_matriz((int)$contexto['matriz_id']);
    if (!$resultado['success']) {
        header('Location: ../filiais.php?tipo=error&msg=' . urlencode($resultado['message']));
        exit;
    }

    header('Location: ../filiais.php?tipo=success&msg=' . urlencode($resultado['message']));
    exit;
} catch (Throwable $e) {
    error_log('[FILIAIS][RETORNAR_MATRIZ][ERROR] ' . $e->getMessage());
    header('Location: ../filiais.php?tipo=error&msg=' . urlencode('Nao foi possivel restaurar o contexto da matriz.'));
    exit;
}
