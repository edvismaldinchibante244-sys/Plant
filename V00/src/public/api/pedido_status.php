<?php

/*
   API - Atualizar Status do Pedido
*/

session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['restaurante_id'])) {
        echo json_encode(array("success" => false, "message" => "Não autenticado"));
        exit;
    }

    if (empty($_POST['id']) || empty($_POST['status'])) {
        echo json_encode(array("success" => false, "message" => "Dados incompletos"));
        exit;
    }

    $status_in = strtoupper(trim((string)($_POST['status'] ?? '')));
    $status_map = [
        'PENDENTE' => 'NOVO',
        'CONFIRMADO' => 'PREPARANDO',
        'NOVO' => 'NOVO',
        'PREPARANDO' => 'PREPARANDO',
        'PRONTO' => 'PRONTO',
        'ENTREGUE' => 'ENTREGUE',
        'PAGO' => 'PAGO',
        'CANCELADO' => 'CANCELADO'
    ];

    if (!isset($status_map[$status_in])) {
        echo json_encode(array("success" => false, "message" => "Status inválido"));
        exit;
    }

    $status_final = $status_map[$status_in];

    $database = new Database();
    $db = $database->getConnection();
    $pedido_id = (int)$_POST['id'];

    // Record when preparation starts (only on first transition to PREPARANDO)
    $preparo_sql = $status_final === 'PREPARANDO'
        ? ", iniciado_preparo_em = COALESCE(iniciado_preparo_em, NOW())"
        : "";

    $query = "UPDATE pedidos SET status = :status, atualizado_em = NOW(){$preparo_sql}
              WHERE id = :id AND restaurante_id = :rid";
    $stmt  = $db->prepare($query);
    $stmt->bindParam(':status', $status_final);
    $stmt->bindParam(':id',     $pedido_id, PDO::PARAM_INT);
    $stmt->bindParam(':rid',    $_SESSION['restaurante_id'], PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Pedido atualizado!"));
    } else {
        echo json_encode(array("success" => false, "message" => "Erro ao atualizar pedido"));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
