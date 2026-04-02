<?php

/*
   API - Redefinir Senha com Token
*/

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (ob_get_level() === 0) {
    ob_start();
}

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/password_reset_helper.php';
include_once __DIR__ . '/../../Model/Auth.php';

header('Content-Type: application/json');

function responderJsonRedefinicao(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJsonRedefinicao(["success" => false, "message" => "Método não permitido."], 405);
}

$token = isset($_POST['token']) ? trim((string)$_POST['token']) : '';
$nova_senha = isset($_POST['nova_senha']) ? (string)$_POST['nova_senha'] : '';
$confirmar_senha = isset($_POST['confirmar_senha']) ? (string)$_POST['confirmar_senha'] : '';

if ($token === '') {
    responderJsonRedefinicao(["success" => false, "message" => "Link de recuperação inválido ou expirado."]);
}

if (strlen($nova_senha) < 6) {
    responderJsonRedefinicao(["success" => false, "message" => "A senha deve ter pelo menos 6 caracteres."]);
}

if ($nova_senha !== $confirmar_senha) {
    responderJsonRedefinicao(["success" => false, "message" => "As senhas não conferem."]);
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    responderJsonRedefinicao(["success" => false, "message" => "Erro de conexão ao banco de dados."], 500);
}

try {
    $db->beginTransaction();

    $reset = password_reset_find_valid_token($db, $token, true);
    if (!$reset) {
        $db->rollBack();
        responderJsonRedefinicao(["success" => false, "message" => "Link de recuperação inválido ou expirado."]);
    }


    $userIdToken = isset($reset['user_id']) ? (int)$reset['user_id'] : 0;
    if ($userIdToken > 0) {
        $stmt = $db->prepare("SELECT id, super_admin, restaurante_id FROM usuarios WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$userIdToken]);
    } else {
        $stmt = $db->prepare("SELECT id, super_admin, restaurante_id FROM usuarios WHERE email = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$reset['email']]);
    }
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $db->rollBack();
        responderJsonRedefinicao(["success" => false, "message" => "Link de recuperação inválido ou expirado."]);
    }

    // Bloquear redefinição de senha para super admin
    if (isset($usuario['super_admin']) && intval($usuario['super_admin']) === 1) {
        $db->rollBack();
        responderJsonRedefinicao(["success" => false, "message" => "Não é permitido redefinir a senha do super admin por este método."]);
    }

    $auth = new Auth($db);
    if (!$auth->atualizarSenha((int)$usuario['id'], $nova_senha)) {
        throw new RuntimeException('Falha ao atualizar senha via token.');
    }

    if ((int)($usuario['super_admin'] ?? 0) !== 1) {
        $restauranteId = (int)($usuario['restaurante_id'] ?? 0);

        $stmtAtivarUsuario = $db->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
        $stmtAtivarUsuario->execute([(int)$usuario['id']]);

        if ($restauranteId > 0) {
            $stmtAtivarRestaurante = $db->prepare("UPDATE restaurantes SET status = 'ATIVO' WHERE id = ?");
            $stmtAtivarRestaurante->execute([$restauranteId]);
        }
    }

    password_reset_mark_used($db, (int)$reset['id']);
    password_reset_invalidate_email_tokens($db, (string)$reset['email']);

    $db->commit();

    responderJsonRedefinicao(["success" => true, "message" => "Senha redefinida com sucesso! Agora você pode fazer login."]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Redefinir senha API Error: ' . $e->getMessage());
    responderJsonRedefinicao(["success" => false, "message" => "Não foi possível redefinir a senha. Solicite um novo link."], 500);
}
