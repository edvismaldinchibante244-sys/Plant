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

$filialId = (int)($_POST['filial_id'] ?? 0);

try {
    $db = (new Database())->getConnection();
    $contexto = filiais_obter_contexto(
        $db,
        (int)($_SESSION['restaurante_id'] ?? 0),
        (int)($_SESSION['matriz_id'] ?? 0)
    );

    if (empty($contexto['tem_multi_filial'])) {
        header('Location: ../configuracoes.php?erro=plano_sem_multi_filial');
        exit;
    }

    $resultado = filiais_assumir_contexto($db, (int)$contexto['matriz_id'], $filialId);
    if (!$resultado['success']) {
        header('Location: ../filiais.php?tipo=error&msg=' . urlencode($resultado['message']));
        exit;
    }

    header('Location: ../dashboard.php?msg=filial_selecionada');
    exit;
} catch (Throwable $e) {
    error_log('[FILIAIS][SELECIONAR][ERROR] ' . $e->getMessage());
    header('Location: ../filiais.php?tipo=error&msg=' . urlencode('Nao foi possivel acessar a filial agora.'));
    exit;
}
