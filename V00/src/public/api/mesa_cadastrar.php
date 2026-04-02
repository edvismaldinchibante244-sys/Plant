<?php

/*
  API - Cadastrar Nova Mesa
  Arquitetura N-Tier
 */

session_start();
include_once '../../config/restaurante_context.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (session_restaurante_contexto_id() <= 0) {
        echo json_encode(array("success" => false, "message" => "Não autenticado"));
        exit;
    }

    // Apenas ADMIN pode cadastrar mesas
    if ($_SESSION['perfil'] !== 'ADMIN') {
        echo json_encode(array("success" => false, "message" => "Sem permissão"));
        exit;
    }

    $tipo = strtolower(trim((string)($_POST['tipo'] ?? 'mesa')));
    $numero = trim((string)($_POST['numero'] ?? ''));
    $capacidade = (int)($_POST['capacidade'] ?? 0);

    if ($tipo === 'balcao') {
        $numero = 'Balcão';
        if ($capacidade <= 0) {
            $capacidade = 1;
        }
    }

    if ($numero === '' || $capacidade <= 0) {
        echo json_encode(array("success" => false, "message" => "Preencha todos os campos"));
        exit;
    }

    require_once __DIR__ . '/../../Controller/MesaController.php';

    $controller = new MesaController();

    $dados = array(
        'numero' => $numero,
        'capacidade' => $capacidade,
        'tipo' => $tipo,
    );

    $resultado = $controller->cadastrar($dados, session_restaurante_contexto_id(), session_restaurante_capability_id());
    echo json_encode($resultado);
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
