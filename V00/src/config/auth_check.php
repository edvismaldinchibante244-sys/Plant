<?php

/*
   Auth Check - Verify if user is logged in
 */

include_once __DIR__ . '/security.php';
security_start_session();
security_set_headers();
if (!security_enforce_idle_timeout(45)) {
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'api') !== false)
    ) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Sessao expirada. Faça login novamente.',
            'redirect' => 'index.php'
        ]);
        exit;
    }
    header('Location: index.php?erro=sessao_expirada');
    exit;
}
security_regenerate_session(15);

include_once __DIR__ . '/presenca_online.php';
include_once __DIR__ . '/restaurante_context.php';

// Check if user is logged in
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Return JSON error for API calls
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'api') !== false)
    ) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Acesso não autorizado. Faça login primeiro.',
            'redirect' => 'index.php'
        ]);
        exit;
    }

    // Redirect to login for regular page requests
    header('Location: index.php');
    exit;
}

// Bloqueia imediatamente contas inativas removidas por soft delete.
$userIdOnline = intval($_SESSION['usuario_id'] ?? 0);
$restauranteIdOnline = session_restaurante_auth_id();
$ehSuperAdminSessao = intval($_SESSION['super_admin'] ?? 0) === 1;
$isApiRequest = strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/') !== false;

if (!$ehSuperAdminSessao && $userIdOnline > 0 && $restauranteIdOnline > 0) {
    try {
        if (!class_exists('Database')) {
            include_once __DIR__ . '/database.php';
        }
        if (class_exists('Database')) {
            $dbStatus = (new Database())->getConnection();
            if ($dbStatus instanceof PDO) {
                $stmtStatus = $dbStatus->prepare('SELECT ativo FROM usuarios WHERE id = :id AND restaurante_id = :rid LIMIT 1');
                $stmtStatus->bindValue(':id', $userIdOnline, PDO::PARAM_INT);
                $stmtStatus->bindValue(':rid', $restauranteIdOnline, PDO::PARAM_INT);
                $stmtStatus->execute();
                $usuarioAtivo = $stmtStatus->fetch(PDO::FETCH_ASSOC);

                if (!$usuarioAtivo || intval($usuarioAtivo['ativo'] ?? 0) !== 1) {
                    $_SESSION = [];
                    session_unset();
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_destroy();
                    }

                    if ($isApiRequest || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'message' => 'Sua conta foi inativada. Faça login novamente.',
                            'redirect' => 'index.php'
                        ]);
                        exit;
                    }

                    header('Location: index.php?erro=conta_inativada');
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        // A validacao de status nao deve bloquear a aplicacao em caso de falha temporaria.
    }
}

// Atualiza presença online do usuário logado com throttle para reduzir escrita no banco.
$lastPresencePing = intval($_SESSION['last_presence_ping'] ?? 0);

if ($userIdOnline > 0 && $restauranteIdOnline > 0 && (time() - $lastPresencePing) >= 45) {
    try {
        if (!class_exists('Database')) {
            include_once __DIR__ . '/database.php';
        }
        if (class_exists('Database')) {
            $dbPresence = (new Database())->getConnection();
            if ($dbPresence instanceof PDO) {
                if (presenca_ping_usuario($dbPresence, $userIdOnline, $restauranteIdOnline)) {
                    $_SESSION['last_presence_ping'] = time();
                }
            }
        }
    } catch (Throwable $e) {
        // Falha de presença não deve bloquear autenticação/páginas.
    }
}

// Restrição global de rotas por perfil
$perfilSessao = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
$perfilSessao = str_replace('GARÇOM', 'GARCOM', $perfilSessao);
$scriptAtual = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isApiRequest = strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/') !== false;

if (!$isApiRequest) {
    $rotasPorPerfil = [
        'CAIXA' => [
            'permitidas' => ['dashboard.php', 'vendas.php', 'caixa.php', 'caixa_mesas.php', 'pedidos.php', 'logout.php'],
            'redirect' => 'caixa.php'
        ],
        'GARCOM' => [
            'permitidas' => ['dashboard.php', 'pedidos.php', 'novo_pedido.php', 'mesas.php', 'garcom_mesas.php', 'logout.php'],
            'redirect' => 'pedidos.php'
        ],
        'COZINHA' => [
            'permitidas' => ['cozinha.php', 'logout.php', 'api/setor_itens.php', 'api/pedido_item_status.php', 'api/pedido_sse.php'],
            'redirect' => 'cozinha.php'
        ],
        'BAR' => [
            'permitidas' => ['bar.php', 'logout.php', 'api/setor_itens.php', 'api/pedido_item_status.php', 'api/pedido_sse.php'],
            'redirect' => 'bar.php'
        ],
    ];

    if (isset($rotasPorPerfil[$perfilSessao]) && !in_array($scriptAtual, $rotasPorPerfil[$perfilSessao]['permitidas'], true)) {
        $redirectDestino = $rotasPorPerfil[$perfilSessao]['redirect'];
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Sem permissão para acessar esta página.',
                'redirect' => $redirectDestino
            ]);
            exit;
        }

        header('Location: ' . $redirectDestino . '?erro=sem_permissao');
        exit;
    }
}

// Backward-compatible helper used by legacy pages.
if (!function_exists('checkPermission')) {
    function normalizePerfil($perfil)
    {
        $p = strtoupper(trim((string)$perfil));
        $aliases = [
            'GARÇOM' => 'GARCOM',
            'OPERADOR' => 'GARCOM',
            'COZINHEIRO' => 'COZINHA',
            'CHEF' => 'COZINHA',
            'BARMAN' => 'BAR',
            'BARTENDER' => 'BAR',
            'FUNCIONARIO' => 'GARCOM',
        ];
        return $aliases[$p] ?? $p;
    }

    function checkPermission($allowedPerfis = ['ADMIN'])
    {
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            return false;
        }

        if (!is_array($allowedPerfis)) {
            $allowedPerfis = [$allowedPerfis];
        }

        $perfil = normalizePerfil($_SESSION['perfil'] ?? '');
        $allowed = array_map('normalizePerfil', $allowedPerfis);
        return in_array($perfil, $allowed, true);
    }

    function requirePermissionOrRedirect($allowedPerfis = ['ADMIN'], $redirect = 'dashboard.php')
    {
        if (!checkPermission($allowedPerfis)) {
            header('Location: ' . $redirect . '?erro=sem_permissao');
            exit;
        }
    }
}
