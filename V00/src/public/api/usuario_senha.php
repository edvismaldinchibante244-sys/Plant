<?php

/*
   API - Alterar Senha do Usuário
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/restaurante_context.php';
include_once '../../Model/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restauranteId = session_restaurante_contexto_id();

    if ($restauranteId <= 0 || $_SESSION['perfil'] !== 'ADMIN') {
        echo json_encode(array("success" => false, "message" => "Sem permissão"));
        exit;
    }

    if (empty($_POST['usuario_id']) || empty($_POST['nova_senha'])) {
        echo json_encode(array("success" => false, "message" => "Dados incompletos"));
        exit;
    }

    if (strlen($_POST['nova_senha']) < 6) {
        echo json_encode(array("success" => false, "message" => "A senha deve ter pelo menos 6 caracteres"));
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $id = intval($_POST['usuario_id']);

    // Verificar se o usuário pertence ao restaurante
    $query_check = "SELECT id FROM usuarios WHERE id = :id AND restaurante_id = :rid LIMIT 1";
    $stmt_check  = $db->prepare($query_check);
    $stmt_check->bindParam(':id',  $id, PDO::PARAM_INT);
    $stmt_check->bindValue(':rid', $restauranteId, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo json_encode(array("success" => false, "message" => "Usuário não encontrado"));
        exit;
    }

    $auth = new Auth($db);

    if ($auth->atualizarSenha($id, $_POST['nova_senha'])) {
        echo json_encode(array("success" => true, "message" => "Senha alterada com sucesso!"));
    } else {
        echo json_encode(array("success" => false, "message" => "Erro ao alterar senha"));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
