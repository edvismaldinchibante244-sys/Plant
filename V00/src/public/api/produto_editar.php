<?php

/*
   API - Editar Produto
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

        if (empty($_POST['produto_id']) || empty($_POST['nome']) || empty($_POST['preco'])) {
            echo json_encode(array("success" => false, "message" => "Preencha todos os campos obrigatórios"));
            exit;
        }

        require_once __DIR__ . '/../../Controller/ProdutoController.php';

        $controller = new ProdutoController();

        $dados = array(
            'id' => $_POST['produto_id'],
            'restaurante_id' => session_restaurante_contexto_id(),
            'categoria_id' => !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null,
            'nome' => $_POST['nome'],
            'descricao' => $_POST['descricao'] ?? '',
            'preco' => $_POST['preco'],
            'custo' => $_POST['custo'] ?? 0,
            'estoque' => $_POST['estoque'] ?? 0,
            'estoque_minimo' => $_POST['estoque_minimo'] ?? 5,
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
            'imagem' => !empty($_POST['imagem_existing']) ? $_POST['imagem_existing'] : null
        );

        $resultado = $controller->editar($dados);
        echo json_encode($resultado);
    } else {
        echo json_encode(array("success" => false, "message" => "Método não permitido"));
    }
} catch (Exception $e) {
    echo json_encode(array("success" => false, "message" => "Erro: " . $e->getMessage()));
}
