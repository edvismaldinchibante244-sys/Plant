<?php

/*
   API - Atualizar Status da Mesa
   Arquitetura N-Tier
 */

session_start();
include_once '../../config/restaurante_context.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $restauranteId = session_restaurante_contexto_id();
    if ($restauranteId <= 0) {
        echo json_encode(array("success" => false, "message" => "Não autenticado"));
        exit;
    }

    if (empty($_POST['id']) || empty($_POST['status'])) {
        echo json_encode(array("success" => false, "message" => "Dados incompletos"));
        exit;
    }

    $status_validos = ['LIVRE', 'OCUPADA', 'RESERVADA'];
    if (!in_array($_POST['status'], $status_validos)) {
        echo json_encode(array("success" => false, "message" => "Status inválido"));
        exit;
    }

    require_once __DIR__ . '/../../Controller/MesaController.php';

    $controller = new MesaController();
    $resultado = $controller->atualizarStatus(intval($_POST['id']), $restauranteId, $_POST['status']);

    if ($resultado['success']) {
        $labels = ['LIVRE' => 'liberada', 'OCUPADA' => 'ocupada', 'RESERVADA' => 'reservada'];
        $resultado['message'] = "Mesa " . ($labels[$_POST['status']] ?? $_POST['status']) . " com sucesso!";
    }

    echo json_encode($resultado);
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
