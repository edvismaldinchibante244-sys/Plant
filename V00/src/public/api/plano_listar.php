<?php

/**
   API - Listar Compras de Planos
   Lista as compras de planos do restaurante
 */

session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (!isset($_SESSION['restaurante_id']) || $_SESSION['perfil'] !== 'ADMIN') {
        echo json_encode(["success" => false, "message" => "Sem permissão"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    $colunasCompra = [];
    $stmtCols = $db->query('SHOW COLUMNS FROM compras_planos');
    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
        $colunasCompra[] = $col['Field'];
    }

    $colData = in_array('criado_em', $colunasCompra, true) ? 'criado_em' : 'created_at';

    // Buscar compras do restaurante
    $stmt = $db->prepare("SELECT *, " . $colData . " AS data_compra FROM compras_planos WHERE restaurante_id = ? ORDER BY " . $colData . " DESC");
    $stmt->execute([$_SESSION['restaurante_id']]);
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $compras
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
