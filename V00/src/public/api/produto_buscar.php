<?php

/*
   API - Buscar Produto
   Arquitetura N-Tier
*/

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/restaurante_context.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    if (empty($_GET['id'])) {
        echo json_encode(array("success" => false, "message" => "ID não fornecido"));
        exit;
    }

    require_once __DIR__ . '/../../Controller/ProdutoController.php';

    $controller = new ProdutoController();
    $data = $controller->buscarPorId($_GET['id'], session_restaurante_contexto_id());

    if ($data) {
        echo json_encode(array("success" => true, "data" => $data));
    } else {
        echo json_encode(array("success" => false, "message" => "Produto não encontrado"));
    }
} catch (Exception $e) {
    error_log("produto_buscar.php - Exception: " . $e->getMessage());
    echo json_encode(array("success" => false, "message" => "Erro: " . $e->getMessage()));
}
