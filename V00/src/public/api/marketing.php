<?php

/*
   API: Marketing (Cupons, Pontos, Campanhas)
   Base: /api/marketing/
 */

session_start();
require_once '../../config/auth_check.php';
require_once '../../config/database.php';

spl_autoload_register(function($class) {
    $base = __DIR__ . '/../../';
    $file = $base . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');

$db = (new Database)->getConnection();

if (empty($_SESSION['restaurante_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$restaurante_id = $_SESSION['restaurante_id'];
$request_method = $_SERVER['REQUEST_METHOD'];
$request_path = trim($_GET['route'] ?? '', '/');

$modelMarketing = new \App\Model\CampanhaMarketing($db);
$serviceMarketing = new \App\Service\MarketingService($db, $modelMarketing);

try {
    // POST /api/marketing/validar-cupom - Validar cupom
    if ($request_method === 'POST' && $request_path === 'validar-cupom') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $codigo = $dados['codigo'] ?? '';
        $valor_venda = floatval($dados['valor_venda'] ?? 0);

        $resultado = $serviceMarketing->processarCupom($restaurante_id, $codigo, $valor_venda);

        echo json_encode($resultado);
    }

    // POST /api/marketing/usar-pontos - Usar pontos para desconto
    elseif ($request_method === 'POST' && $request_path === 'usar-pontos') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $cliente_id = intval($dados['cliente_id'] ?? 0);
        $pontos = intval($dados['pontos'] ?? 0);

        if ($cliente_id <= 0 || $pontos <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        $desconto = $serviceMarketing->converterPontosEmDesconto($pontos);
        $usado = $modelMarketing->usarPontos($cliente_id, $restaurante_id, $pontos, 'Resgatado no POS');

        echo json_encode([
            'success' => $usado,
            'desconto_value' => $desconto,
            'message' => $usado ? "MZN {$desconto} em desconto" : 'Saldo insuficiente'
        ]);
    }

    // GET /api/marketing/pontos/{cliente_id} - Obter saldo de pontos
    elseif ($request_method === 'GET' && preg_match('/^pontos\/(\d+)$/', $request_path, $m)) {
        $cliente_id = intval($m[1]);
        $saldo = $modelMarketing->obterSaldoPontos($cliente_id, $restaurante_id);

        echo json_encode([
            'success' => (bool)$saldo,
            'data' => $saldo ?? ['saldo_pontos' => 0, 'nivel_cliente' => 'bronze']
        ]);
    }

    // POST /api/marketing/gerar-cupom - Gerar cupom personalizado
    elseif ($request_method === 'POST' && $request_path === 'gerar-cupom') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $cliente_id = intval($dados['cliente_id'] ?? 0);
        $tipo = $dados['tipo'] ?? 'promocao';

        $codigo = $serviceMarketing->gerarCupomPersonalizado($restaurante_id, $cliente_id, $tipo);

        echo json_encode([
            'success' => true,
            'codigo_cupom' => $codigo,
            'tipo' => $tipo
        ]);
    }

    // GET /api/marketing/campanhas-ativas - Listar campanhas ativas
    elseif ($request_method === 'GET' && $request_path === 'campanhas-ativas') {
        $campanhas = $modelMarketing->obterCampanhasAtivas($restaurante_id);

        echo json_encode([
            'success' => true,
            'data' => $campanhas,
            'total' => count($campanhas)
        ]);
    }

    // POST /api/marketing/nova-campanha - Criar campaign
    elseif ($request_method === 'POST' && $request_path === 'nova-campanha') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $dados['restaurante_id'] = $restaurante_id;

        $criado = $modelMarketing->criarCampanha($dados);

        if ($criado) {
            echo json_encode(['success' => true, 'message' => 'Campanha criada com sucesso']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao criar campanha']);
        }
    }

    else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
