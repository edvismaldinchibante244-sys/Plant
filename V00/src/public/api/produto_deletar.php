<?php

/**
  API - Inativar Produto
  Arquitetura N-Tier
 */

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/restaurante_context.php';

header('Content-Type: application/json');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (empty($_POST['id'])) {
            echo json_encode(array("success" => false, "message" => "ID não fornecido"));
            exit;
        }

        $produto_id = (int) $_POST['id'];
        $restaurante_id = session_restaurante_contexto_id();

        require_once __DIR__ . '/../../Controller/ProdutoController.php';
        $controller = new ProdutoController();

        $produto = $controller->buscarPorId($produto_id, $restaurante_id);
        if (!$produto) {
            echo json_encode(array("success" => false, "message" => "Produto não encontrado"));
            exit;
        }

        $resultado = $controller->deletar($produto_id, $restaurante_id);
        echo json_encode($resultado);
    } else {
        echo json_encode(array("success" => false, "message" => "Método não permitido"));
    }
} catch (Exception $e) {
    echo json_encode(array("success" => false, "message" => "Erro: " . $e->getMessage()));
}
