<?php

/*
   API - Inativar Usuário
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/restaurante_context.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restauranteId = session_restaurante_contexto_id();

    if ($restauranteId <= 0 || $_SESSION['perfil'] !== 'ADMIN') {
        echo json_encode(array("success" => false, "message" => "Sem permissão"));
        exit;
    }

    if (empty($_POST['id'])) {
        echo json_encode(array("success" => false, "message" => "ID não fornecido"));
        exit;
    }

    $id = intval($_POST['id']);

    // Não pode inativar a si mesmo
    if ($id === intval($_SESSION['usuario_id'])) {
        echo json_encode(array("success" => false, "message" => "Você não pode inativar sua própria conta"));
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

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

    $query = "UPDATE usuarios
              SET ativo = 0,
                  tentativas_login = 0,
                  bloqueado_ate = NULL
              WHERE id = :id AND restaurante_id = :rid";
    $stmt  = $db->prepare($query);
    $stmt->bindParam(':id',  $id, PDO::PARAM_INT);
    $stmt->bindValue(':rid', $restauranteId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Usuário inativado com sucesso!"));
    } else {
        echo json_encode(array("success" => false, "message" => "Erro ao inativar usuário"));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
