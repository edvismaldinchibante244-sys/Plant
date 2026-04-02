<?php

/*
   API - Atualizar Estoque
  Arquitetura N-Tier
 */

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/restaurante_context.php';

header('Content-Type: application/json');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (empty($_POST['id']) || !isset($_POST['quantidade'])) {
            echo json_encode(array("success" => false, "message" => "Dados incompletos"));
            exit;
        }

        require_once __DIR__ . '/../../Controller/ProdutoController.php';

        $controller = new ProdutoController();

        // Determinar o tipo de operação (entrada ou saída)
        $quantidade = $_POST['quantidade'];
        $tipo = ($quantidade < 0) ? 'SAIDA' : 'ENTRADA';

        $resultado = $controller->atualizarEstoque($_POST['id'], session_restaurante_contexto_id(), abs($quantidade), $tipo);

        echo json_encode($resultado);
    } else {
        echo json_encode(array("success" => false, "message" => "Método não permitido"));
    }
} catch (Exception $e) {
    echo json_encode(array("success" => false, "message" => "Erro: " . $e->getMessage()));
}
