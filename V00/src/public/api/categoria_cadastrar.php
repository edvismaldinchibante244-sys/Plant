<?php

/*
   API - Cadastrar Categoria
   Arquitetura N-Tier
*/

include_once __DIR__ . '/../../config/auth_check.php';

header('Content-Type: application/json');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (empty($_POST['nome'])) {
            echo json_encode(array("success" => false, "message" => "Digite o nome da categoria"));
            exit;
        }

        require_once __DIR__ . '/../../Controller/CategoriaController.php';

        $controller = new CategoriaController();

        $dados = array(
            'restaurante_id' => $_SESSION['restaurante_id'],
            'nome' => trim($_POST['nome']),
            'descricao' => $_POST['descricao'] ?? '',
            'ativo' => 1
        );

        $resultado = $controller->cadastrar($dados);
        echo json_encode($resultado);
    } else {
        echo json_encode(array("success" => false, "message" => "Método não permitido"));
    }
} catch (Exception $e) {
    echo json_encode(array("success" => false, "message" => "Erro: " . $e->getMessage()));
}
