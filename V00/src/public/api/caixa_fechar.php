<?php

/*
   API - Fechar Caixa
   Arquitetura N-Tier
 */

session_start();

header('Content-Type: application/json');
include_once '../../config/turno_helpers.php';
include_once '../../config/database.php';
include_once '../../Model/Caixa.php';
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
        echo json_encode(array("success" => false, "message" => "Sem permissão para fechar o caixa"));
        exit;
    }

    if (!isset($_POST['fechamento']) || $_POST['fechamento'] === '') {
        echo json_encode(array("success" => false, "message" => "Valor de fechamento não informado"));
        exit;
    }

    $fechamento = floatval($_POST['fechamento']);

    if ($fechamento < 0) {
        echo json_encode(array("success" => false, "message" => "Valor de fechamento inválido"));
        exit;
    }

    $database = new Database();
    $turnoService = new TurnoService($database);
    $turnoAtivo = $turnoService->obterTurnoAtivoUsuario((int)$_SESSION['usuario_id'], (int)$_SESSION['restaurante_id']);
    if (!$turnoAtivo) {
        echo json_encode(array("success" => false, "message" => "É necessário ter turno ativo para fechar o caixa"));
        exit;
    }

    require_once __DIR__ . '/../../Controller/CaixaController.php';

    try {
        $db = $database->getConnection();
        $caixaModel = new Caixa($db);
        $caixaAberto = $caixaModel->buscarAberto((int)$_SESSION['restaurante_id']);
        $controller = new CaixaController();
        $resultado = $controller->fechar($_SESSION['restaurante_id'], $fechamento);
        if (!empty($resultado['success']) && !empty($caixaAberto['id'])) {
            $turnoService->encerrarVinculoCaixa((int)$_SESSION['restaurante_id'], (int)$caixaAberto['id']);
        }
        echo json_encode($resultado);
    } catch (Exception $e) {
        error_log("Erro fechar caixa: " . $e->getMessage());
        echo json_encode(array("success" => false, "message" => "Erro: " . $e->getMessage()));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
