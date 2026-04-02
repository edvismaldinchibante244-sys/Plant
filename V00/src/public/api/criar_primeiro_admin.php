<?php

/*
  API - Criar Primeiro Usuário Admin do Restaurante
  Usado após login temporário com senha do restaurante
*/

session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

// Verificar se está em sessão temporária de restaurante
if (!isset($_SESSION['logado']) || !isset($_SESSION['login_restaurante_temp']) || $_SESSION['login_restaurante_temp'] != true) {
    echo json_encode(["success" => false, "message" => "Acesso não autorizado"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // Validações
    if (empty($nome) || empty($email) || empty($senha)) {
        echo json_encode(["success" => false, "message" => "Preencha todos os campos"]);
        exit;
    }

    if (strlen($senha) < 6) {
        echo json_encode(["success" => false, "message" => "A senha deve ter pelo menos 6 caracteres"]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Email inválido"]);
        exit;
    }

    // Verificar se o email corresponde ao do restaurante logado
    if ($email !== $_SESSION['email']) {
        echo json_encode(["success" => false, "message" => "Email não corresponde ao restaurante"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Verificar se já existe um usuário admin para este restaurante
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE restaurante_id = ? AND perfil = 'ADMIN' LIMIT 1");
        $stmt->execute([$_SESSION['restaurante_id']]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Já existe um usuário admin para este restaurante"]);
            exit;
        }

        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

        // Criar o usuário admin
        $stmt = $db->prepare("INSERT INTO usuarios (restaurante_id, nome, email, senha, perfil, ativo, criado_em)
                             VALUES (?, ?, ?, ?, 'ADMIN', 1, NOW())");
        $stmt->execute([$_SESSION['restaurante_id'], $nome, $email, $senha_hash]);

        $usuario_id = $db->lastInsertId();

        // Limpar a senha temporária do restaurante (já não é mais necessária)
        $stmt = $db->prepare("UPDATE restaurantes SET senha_admin = NULL, senha_criada_em = NULL, senha_pode_alterar = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['restaurante_id']]);

        $db->commit();

        // Atualizar sessão para o novo usuário admin
        $_SESSION['usuario_id'] = $usuario_id;
        $_SESSION['nome'] = $nome;
        $_SESSION['perfil'] = 'ADMIN';
        $_SESSION['foto'] = '';
        unset($_SESSION['login_restaurante_temp']); // Remover flag temporário

        echo json_encode([
            "success" => true,
            "message" => "Usuário admin criado com sucesso! Redirecionando..."
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Erro ao criar usuário: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
