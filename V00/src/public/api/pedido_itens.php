<?php

/*
   API - Buscar Itens do Pedido
*/

session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['restaurante_id'])) {
    echo json_encode(array("success" => false, "message" => "Não autenticado"));
    exit;
}

if (empty($_GET['id'])) {
    echo json_encode(array("success" => false, "message" => "ID não fornecido"));
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar se pedido pertence ao restaurante
$query_ped = "SELECT id, observacao FROM pedidos WHERE id = :id AND restaurante_id = :rid LIMIT 1";
$stmt_ped  = $db->prepare($query_ped);
$stmt_ped->bindValue(':id',  (int)$_GET['id'], PDO::PARAM_INT);
$stmt_ped->bindValue(':rid', (int)$_SESSION['restaurante_id'], PDO::PARAM_INT);
$stmt_ped->execute();
$pedido = $stmt_ped->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    echo json_encode(array("success" => false, "message" => "Pedido não encontrado"));
    exit;
}

// Buscar itens
$query = "SELECT ip.*, COALESCE(p.nome, CONCAT('Produto #', ip.produto_id)) as produto_nome,
                 COALESCE(ip.destino, 'cozinha') as destino,
                 COALESCE(ip.status, 'pendente') as status
          FROM itens_pedido ip
          LEFT JOIN produtos p ON ip.produto_id = p.id
          WHERE ip.pedido_id = :pedido_id
          ORDER BY COALESCE(p.nome, CONCAT('Produto #', ip.produto_id)) ASC";
$stmt  = $db->prepare($query);
$stmt->bindParam(':pedido_id', $pedido['id'], PDO::PARAM_INT);
$stmt->execute();
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(array(
    "success"    => true,
    "itens"      => $itens,
    "observacao" => $pedido['observacao']
));
