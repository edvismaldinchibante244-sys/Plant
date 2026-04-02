<?php

/*
   API - Super Admin Inativar Usuário
  Inativa um usuário de um restaurante
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/csrf.php';
include_once '../../config/super_admin_permissions.php';

header('Content-Type: application/json');

// Verificar se é super admin
if (!isset($_SESSION['super_admin']) || $_SESSION['super_admin'] != 1) {
    echo json_encode(["success" => false, "message" => "Acesso negado"]);
    exit;
}

super_admin_require_permission_json('manage_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_json();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $usuario_id = intval($input['usuario_id'] ?? 0);

    if ($usuario_id <= 0) {
        echo json_encode(["success" => false, "message" => "ID do usuário inválido"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    // Verificar se usuário existe
    $stmt = $db->prepare("SELECT id, nome, perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(["success" => false, "message" => "Usuário não encontrado"]);
        exit;
    }

    // Não permitir inativar o próprio super admin
    if ($usuario['perfil'] === 'ADMIN' && $usuario_id == $_SESSION['usuario_id']) {
        echo json_encode(["success" => false, "message" => "Não é possível inativar seu próprio usuário"]);
        exit;
    }

    // Inativar usuário
    try {
        $stmt = $db->prepare("
            UPDATE usuarios
            SET ativo = 0,
                tentativas_login = 0,
                bloqueado_ate = NULL
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id]);

        echo json_encode([
            "success" => true,
            "message" => "Usuário inativado com sucesso!"
        ]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Erro ao inativar: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
