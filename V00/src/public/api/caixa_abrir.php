<?php

/*
   API - Abrir Caixa
   Arquitetura N-Tier
 */

session_start();

header('Content-Type: application/json');
include_once '../../config/turno_helpers.php';
include_once '../../config/database.php';
include_once '../../Service/TurnoService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['restaurante_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        http_response_code(401);
        echo json_encode(array("success" => false, "message" => "Não autenticado"));
        exit;
    }

    $perfil = turno_normalizar_perfil($_SESSION['perfil'] ?? '');
    if (!in_array($perfil, ['ADMIN', 'CAIXA'], true)) {
        http_response_code(403);
        echo json_encode(array("success" => false, "message" => "Sem permissão para abrir o caixa"));
        exit;
    }

    if (empty($_POST['abertura'])) {
        echo json_encode(array("success" => false, "message" => "Valor de abertura não informado"));
        exit;
    }

    $database = new Database();
    $turnoService = new TurnoService($database);
    $turnoAtivo = $turnoService->obterTurnoAtivoUsuario((int)$_SESSION['usuario_id'], (int)$_SESSION['restaurante_id']);
    if (!$turnoAtivo) {
        echo json_encode(array("success" => false, "message" => "É necessário iniciar o turno antes de abrir o caixa"));
        exit;
    }

    require_once __DIR__ . '/../../Controller/CaixaController.php';

    $controller = new CaixaController();

    $dados = array(
        'restaurante_id' => $_SESSION['restaurante_id'],
        'usuario_id' => $_SESSION['usuario_id'],
        'abertura' => $_POST['abertura']
    );

    $resultado = $controller->abrir($dados);
    if (!empty($resultado['success']) && !empty($resultado['id'])) {
        $turnoService->vincularCaixaAoTurno(
            (int)$_SESSION['restaurante_id'],
            (int)$resultado['id'],
            (int)$turnoAtivo['id'],
            (int)$_SESSION['usuario_id']
        );
    }
    echo json_encode($resultado);
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
