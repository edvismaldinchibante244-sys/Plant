<?php

/**
   API - Super Admin Cadastrar Usuário
  Cadastra um novo usuário para um restaurante específico
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

    $restaurante_id = intval($_POST['restaurante_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? 'usuario123';
    $perfil = strtoupper(trim((string)($_POST['perfil'] ?? 'GARCOM')));
    $perfil_alias = [
        'OPERADOR' => 'GARCOM',
        'COZINHA' => 'GARCOM',
        'GARÇOM' => 'GARCOM',
        'FUNCIONARIO' => 'GARCOM'
    ];
    $perfil = $perfil_alias[$perfil] ?? $perfil;

    // Validações
    if ($restaurante_id <= 0) {
        echo json_encode(["success" => false, "message" => "ID do restaurante inválido"]);
        exit;
    }

    if (empty($nome)) {
        echo json_encode(["success" => false, "message" => "Nome é obrigatório"]);
        exit;
    }

    if (empty($email)) {
        echo json_encode(["success" => false, "message" => "Email é obrigatório"]);
        exit;
    }

    if (!in_array($perfil, ['ADMIN', 'CAIXA', 'GARCOM'], true)) {
        echo json_encode(["success" => false, "message" => "Perfil inválido"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    // Verificar se email já existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND restaurante_id = ?");
    $stmt->execute([$email, $restaurante_id]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Email já está em uso neste restaurante"]);
        exit;
    }

    // Verificar se restaurante existe
    $stmt = $db->prepare("SELECT id, nome FROM restaurantes WHERE id = ?");
    $stmt->execute([$restaurante_id]);
    $restaurante = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$restaurante) {
        echo json_encode(["success" => false, "message" => "Restaurante não encontrado"]);
        exit;
    }

    // Cadastrar usuário
    try {
        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO usuarios (restaurante_id, nome, email, senha, perfil, ativo, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$restaurante_id, $nome, $email, $senha_hash, $perfil]);

        echo json_encode([
            "success" => true,
            "message" => "Usuário cadastrado com sucesso!"
        ]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Erro ao cadastrar: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
