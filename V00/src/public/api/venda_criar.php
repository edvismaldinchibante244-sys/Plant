<?php
/*
   API - Criar Venda
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

try {
    include_once '../../config/database.php';
    include_once '../../config/restaurante_context.php';
    include_once '../../config/turno_helpers.php';
    include_once '../../Model/Venda.php';
    include_once '../../Model/Caixa.php';
    include_once '../../Model/Produto.php';
    include_once '../../Model/Mesa.php';
    include_once '../../Service/TurnoService.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "message" => "Erro ao carregar arquivos: " . $e->getMessage()
    ]);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restauranteId = session_restaurante_contexto_id();

    // Verificar autenticação
    if ($restauranteId <= 0) {
        echo json_encode(array("success" => false, "message" => "Não autenticado"));
        exit;
    }

    // Obter dados JSON
    $dados = json_decode(file_get_contents("php://input"), true);

    // Debug: log dos dados recebidos
    error_log("Dados recebidos: " . json_encode($dados));

    if (empty($dados['itens'])) {
        echo json_encode(array("success" => false, "message" => "Dados incompletos: faltam itens"));
        exit;
    }

    // Verificar se os itens têm produto_id
    foreach ($dados['itens'] as $idx => $item) {
        if (!isset($item['id']) && !isset($item['Id'])) {
            error_log("Item $idx sem ID: " . json_encode($item));
            echo json_encode(array("success" => false, "message" => "Item sem ID: " . json_encode($item)));
            exit;
        }
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(array("success" => false, "message" => "Erro de conexão com a base de dados"));
        exit;
    }

    try {
        $perfil = turno_normalizar_perfil($_SESSION['perfil'] ?? '');
        if (turno_usuario_exige_turno_ativo($perfil)) {
            $turnoService = new TurnoService($database);
            $turnoAtivo = $turnoService->obterTurnoAtivoUsuario((int)$_SESSION['usuario_id'], $restauranteId);
            if (!$turnoAtivo) {
                throw new Exception("Não há turno ativo para este usuário. Inicie o turno antes de lançar vendas.");
            }
        }

        // Iniciar transação
        $db->beginTransaction();

        // Verificar se há caixa aberto
        $caixa = new Caixa($db);
        $caixa_aberto = $caixa->buscarAberto($restauranteId);

        if (!$caixa_aberto) {
            throw new Exception("Não há caixa aberto. Por favor, abra o caixa primeiro.");
        }

        // Criar venda
        $venda = new Venda($db);
        $venda->restaurante_id = $restauranteId;
        $venda->usuario_id = $_SESSION['usuario_id'];
        $venda->caixa_id = $caixa_aberto['id'];
        $venda->mesa_id = $dados['mesa_id'] ?? null;
        $venda->total = $dados['total'];
        $venda->desconto = $dados['desconto'] ?? 0;
        $venda->total_final = $dados['total_final'];
        $venda->forma_pagamento = $dados['forma_pagamento'] ?? null;
        // Fluxo correto: venda criada como PENDENTE, só pode ser paga após produção
        $venda->status = 'PENDENTE';
        $venda->numero_fatura = $venda->gerarNumeroFatura($restauranteId);

        $venda_id = $venda->criar();

        if (!$venda_id) {
            throw new Exception("Erro ao criar venda no banco de dados");
        }

        // Adicionar itens
        $produto = new Produto($db);

        foreach ($dados['itens'] as $item) {
            // Adicionar item à venda
            if (!$venda->adicionarItem($venda_id, $item['id'], $item['quantidade'], $item['preco'])) {
                throw new Exception("Erro ao adicionar item: " . $item['nome']);
            }

            // Dar baixa no estoque
            if (!$produto->atualizarEstoque($item['id'], $restauranteId, $item['quantidade'], 'SAIDA')) {
                throw new Exception("Erro ao atualizar estoque do produto: " . $item['nome']);
            }
        }

        // Se tem mesa, marcar como ocupada
        if ($dados['mesa_id']) {
            $mesa = new Mesa($db);
            $mesa->atualizarStatus($dados['mesa_id'], $restauranteId, 'OCUPADA');
        }

        // Confirmar transação
        $db->commit();

        echo json_encode(array(
            "success" => true,
            "message" => "Venda realizada com sucesso!",
            "venda_id" => $venda_id,
            "numero_fatura" => $venda->numero_fatura
        ));
    } catch (Exception $e) {
        // Reverter transação
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Erro venda: " . $e->getMessage());
        echo json_encode(array("success" => false, "message" => $e->getMessage()));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
