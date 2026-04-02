<?php

/*
  API - Login Simples
   Mantida por compatibilidade, mas usando o mesmo fluxo seguro do login principal.
 */

include_once __DIR__ . '/../../config/security.php';
security_start_session();
security_set_headers();
security_regenerate_session(15);

if (ob_get_level() === 0) {
    ob_start();
}

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
        exit;
    }

    if (!security_rate_limit('login_simples', 10, 300)) {
        if (ob_get_length()) {
            ob_clean();
        }
        security_log_event('login_rate_limited', ['endpoint' => 'api/login_simples']);
        echo json_encode([
            'success' => false,
            'message' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'
        ]);
        exit;
    }

    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $senha = isset($_POST['senha']) ? (string)$_POST['senha'] : '';

    if ($email === '' || $senha === '') {
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
        exit;
    }

    require_once __DIR__ . '/../../Controller/AuthController.php';
    require_once __DIR__ . '/../../config/super_admin_permissions.php';

    if (!class_exists('AuthController')) {
        throw new RuntimeException('Classe AuthController nao encontrada.');
    }

    $controller = new AuthController();
    $resultado = $controller->login($email, $senha);

    if (!is_array($resultado) || !array_key_exists('success', $resultado)) {
        throw new RuntimeException('Resposta invalida do AuthController.');
    }

    if (ob_get_length()) {
        ob_clean();
    }

    if (!empty($resultado['success'])) {
        $user = $resultado['data'] ?? $resultado['usuario'] ?? null;
        $ehSuperAdmin = !empty($user['super_admin']);

        if (!is_array($user) || empty($user['id']) || (!$ehSuperAdmin && empty($user['restaurante_id']))) {
            throw new RuntimeException('Dados do usuario ausentes na resposta de login.');
        }

        $restauranteIdSessao = !empty($user['restaurante_id']) ? (int)$user['restaurante_id'] : 0;
        $perfilSessao = strtoupper(trim((string)($user['perfil'] ?? 'USER')));
        if ($perfilSessao === 'GARÇOM') {
            $perfilSessao = 'GARCOM';
        }

        session_regenerate_id(true);
        unset($_SESSION['login_restaurante_temp'], $_SESSION['last_presence_ping']);

        $_SESSION['logado'] = true;
        $_SESSION['usuario_id'] = (int)$user['id'];
        $_SESSION['restaurante_id'] = $restauranteIdSessao;
        $_SESSION['nome'] = (string)($user['nome'] ?? 'Usuario');
        $_SESSION['email'] = (string)($user['email'] ?? $email);
        $_SESSION['perfil'] = $perfilSessao;
        $_SESSION['plano'] = (string)($user['plano'] ?? 'BASICO');
        $_SESSION['foto'] = (string)($user['foto'] ?? '');
        $_SESSION['super_admin'] = $ehSuperAdmin ? 1 : 0;
        $_SESSION['super_admin_permissions'] = $ehSuperAdmin ? super_admin_default_permissions() : [];

        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            unset($_SESSION['csrf_token']);
        }

        if ($_SESSION['super_admin'] === 1) {
            $redirect = 'admin.php';
        } elseif (!empty($user['login_restaurante'])) {
            $_SESSION['login_restaurante_temp'] = true;
            $redirect = 'criar_primeiro_admin.php';
        } else {
            $redirect = 'dashboard.php';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login realizado com sucesso!',
            'redirect' => $redirect
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Email ou senha inválidos'
    ]);
} catch (Throwable $e) {
    error_log('Login simples API Error: ' . $e->getMessage());

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erro interno no servidor.'
    ]);
}

exit;
