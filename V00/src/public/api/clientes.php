<?php

/*
   API: Clientes (CRM)
   Endpoints para gerenciamento de clientes
   Base: /api/clientes/
 */

session_start();
require_once '../../config/auth_check.php';
require_once '../../config/database.php';

// Autoload
spl_autoload_register(function ($class) {
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

$modelCliente = new \App\Model\Cliente($db);
$serviceCliente = new \App\Service\ClienteService($db, $modelCliente);

try {
    // GET /api/clientes/ - Listar clientes
    if ($request_method === 'GET' && $request_path === '') {
        $tipo = $_GET['tipo'] ?? 'todos'; // comum, vip, corporativo, todos
        $pagina = intval($_GET['pagina'] ?? 1);
        $limite = intval($_GET['limite'] ?? 10);
        $offset = ($pagina - 1) * $limite;

        $query = "SELECT * FROM clientes WHERE restaurante_id = :restaurante_id ";

        if ($tipo !== 'todos') {
            $query .= "AND tipo_cliente = :tipo ";
        }

        $query .= "AND ativo = TRUE ORDER BY data_ultima_visita DESC LIMIT :limite OFFSET :offset";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurante_id', $restaurante_id);
        if ($tipo !== 'todos') {
            $stmt->bindParam(':tipo', $tipo);
        }
        $stmt->bindParam(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $clientes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $clientes,
            'pagina' => $pagina,
            'total' => count($clientes)
        ]);
    }

    // GET /api/clientes/vips - Listar VIPs
    elseif ($request_method === 'GET' && $request_path === 'vips') {
        $vips = $modelCliente->obterVips($restaurante_id, 20);
        echo json_encode(['success' => true, 'data' => $vips]);
    }

    // GET /api/clientes/{id} - Obter cliente + histórico
    elseif ($request_method === 'GET' && is_numeric($request_path)) {
        $cliente_id = intval($request_path);
        $cliente = $modelCliente->obter($cliente_id);

        if (!$cliente || $cliente['restaurante_id'] != $restaurante_id) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
            exit;
        }

        $stats = $modelCliente->calcularEstatisticas($cliente_id);
        $score = $serviceCliente->calcularScoreCliente($cliente_id);

        // Histórico de compras
        $query_historico = "SELECT * FROM cliente_historico_compras 
                           WHERE cliente_id = :cliente_id 
                           ORDER BY data_compra DESC LIMIT 10";
        $stmt_hist = $db->prepare($query_historico);
        $stmt_hist->execute([':cliente_id' => $cliente_id]);
        $historico = $stmt_hist->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'cliente' => $cliente,
            'estatisticas' => $stats,
            'score_cliente' => $score,
            'historico_compras' => $historico
        ]);
    }

    // POST /api/clientes/ - Criar cliente
    elseif ($request_method === 'POST' && $request_path === '') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $dados['restaurante_id'] = $restaurante_id;

        $criado = $modelCliente->criar($dados);

        if ($criado) {
            $cliente_id = $db->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Cliente criado com sucesso',
                'cliente_id' => $cliente_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao criar cliente']);
        }
    }

    // PUT /api/clientes/{id} - Atualizar cliente
    elseif ($request_method === 'PUT' && is_numeric($request_path)) {
        $cliente_id = intval($request_path);
        $dados = json_decode(file_get_contents('php://input'), true);

        $atualizado = $modelCliente->atualizar($cliente_id, $dados);

        echo json_encode([
            'success' => $atualizado,
            'message' => $atualizado ? 'Cliente atualizado' : 'Erro ao atualizar'
        ]);
    }

    // PUT /api/clientes/{id}/bloquear - Bloquear cliente
    elseif ($request_method === 'PUT' && preg_match('/^(\d+)\/bloquear$/', $request_path, $m)) {
        $cliente_id = intval($m[1]);
        $dados = json_decode(file_get_contents('php://input'), true);
        $motivo = $dados['motivo'] ?? 'Bloqueado por administrador';

        $bloqueado = $modelCliente->bloquear($cliente_id, $motivo);

        echo json_encode([
            'success' => $bloqueado,
            'message' => $bloqueado ? 'Cliente bloqueado' : 'Erro ao bloquear'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
