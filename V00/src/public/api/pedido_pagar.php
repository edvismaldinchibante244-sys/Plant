<?php

/*
  API - Receber Pagamento de Pedido
  Converte pedido ENTREGUE em venda PAGA no caixa e libera a mesa.
*/

session_start();

include_once '../../config/database.php';
include_once '../../config/turno_helpers.php';
include_once '../../Model/Caixa.php';
include_once '../../Model/Venda.php';
include_once '../../Model/Produto.php';
include_once '../../Model/Mesa.php';
include_once '../../Service/TurnoService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

if (!isset($_SESSION['restaurante_id'], $_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nao autenticado']);
    exit;
}

$perfil_raw = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
$perfil = turno_normalizar_perfil($perfil_raw === 'OPERADOR' ? 'GARCOM' : $perfil_raw);
if (!in_array($perfil, ['ADMIN', 'CAIXA'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissao para receber pagamento']);
    exit;
}

$pedido_id = (int)($_POST['id'] ?? 0);
$forma_pagamento = strtoupper(trim((string)($_POST['forma_pagamento'] ?? 'DINHEIRO')));
$desconto_percent = (float)($_POST['desconto_percent'] ?? ($_POST['desconto'] ?? 0));
$observacao = trim((string)($_POST['observacao'] ?? ''));

$formas_validas = ['DINHEIRO', 'MPESA', 'CARTAO', 'TRANSFERENCIA'];
if ($pedido_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Pedido invalido']);
    exit;
}
if (!in_array($forma_pagamento, $formas_validas, true)) {
    echo json_encode(['success' => false, 'message' => 'Forma de pagamento invalida']);
    exit;
}
if ($desconto_percent < 0 || $desconto_percent > 100) {
    echo json_encode(['success' => false, 'message' => 'Desconto percentual invalido (0 a 100)']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (turno_usuario_exige_turno_ativo($perfil)) {
    $turnoService = new TurnoService($database);
    $turnoAtivo = $turnoService->obterTurnoAtivoUsuario((int)$_SESSION['usuario_id'], (int)$_SESSION['restaurante_id']);
    if (!$turnoAtivo) {
        echo json_encode(['success' => false, 'message' => 'É necessário ter turno ativo para receber pagamentos'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $db->beginTransaction();

    $restaurante_id = (int)$_SESSION['restaurante_id'];

    $stmt_pedido = $db->prepare(
        "SELECT id, restaurante_id, mesa_id, numero_pedido, status, total
         FROM pedidos
         WHERE id = :id AND restaurante_id = :rid
         LIMIT 1"
    );
    $stmt_pedido->execute([
        'id' => $pedido_id,
        'rid' => $restaurante_id,
    ]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('Pedido nao encontrado');
    }

    $status = strtoupper((string)($pedido['status'] ?? ''));
    if ($status === 'PAGO') {
        throw new Exception('Este pedido ja foi pago');
    }

    // Fluxo novo por item: aceita pagamento quando nao ha itens pendentes/em preparo.
    $stmt_status_itens = $db->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN LOWER(COALESCE(status,'pendente')) = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN LOWER(COALESCE(status,'pendente')) = 'em_preparo' THEN 1 ELSE 0 END) AS em_preparo,
            SUM(CASE WHEN LOWER(COALESCE(status,'pendente')) = 'pronto' THEN 1 ELSE 0 END) AS prontos,
            SUM(CASE WHEN LOWER(COALESCE(status,'pendente')) = 'entregue' THEN 1 ELSE 0 END) AS entregues
         FROM itens_pedido
         WHERE pedido_id = :pedido_id"
    );
    $stmt_status_itens->execute(['pedido_id' => $pedido_id]);
    $resumo_itens = $stmt_status_itens->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0,
        'pendentes' => 0,
        'em_preparo' => 0,
        'prontos' => 0,
        'entregues' => 0,
    ];

    $total_itens = (int)$resumo_itens['total'];
    $pendentes = (int)$resumo_itens['pendentes'];
    $em_preparo = (int)$resumo_itens['em_preparo'];
    $prontos = (int)$resumo_itens['prontos'];
    $entregues = (int)$resumo_itens['entregues'];

    $pedidoElegivelPagamento =
        in_array($status, ['PRONTO', 'ENTREGUE'], true)
        || ($total_itens > 0 && $pendentes === 0 && $em_preparo === 0 && ($prontos + $entregues) === $total_itens);

    if (!$pedidoElegivelPagamento) {
        throw new Exception(
            'Somente pedidos PRONTO/ENTREGUE (sem itens pendentes) podem ser pagos. '
                . 'Status pedido=' . $status
                . ' | itens: pendentes=' . $pendentes
                . ', em_preparo=' . $em_preparo
                . ', prontos=' . $prontos
                . ', entregues=' . $entregues
                . ', total=' . $total_itens
        );
    }

    $caixa = new Caixa($db);
    $caixa_aberto = $caixa->buscarAberto($restaurante_id);
    if (!$caixa_aberto) {
        throw new Exception('Nao ha caixa aberto. Abra o caixa para receber pagamentos.');
    }

    $stmt_itens = $db->prepare(
        "SELECT ip.produto_id, ip.quantidade, ip.preco_unitario, ip.subtotal
         FROM itens_pedido ip
         WHERE ip.pedido_id = :pedido_id"
    );
    $stmt_itens->execute(['pedido_id' => $pedido_id]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        throw new Exception('Pedido sem itens para pagamento');
    }

    $total_bruto = 0.0;
    foreach ($itens as $item) {
        $subtotal_item = isset($item['subtotal']) ? (float)$item['subtotal'] : ((float)$item['quantidade'] * (float)$item['preco_unitario']);
        $total_bruto += $subtotal_item;
    }

    $desconto_valor = round($total_bruto * ($desconto_percent / 100), 2);
    $total_final = max(0, $total_bruto - $desconto_valor);

    $venda = new Venda($db);
    $venda->restaurante_id = $restaurante_id;
    $venda->usuario_id = (int)$_SESSION['usuario_id'];
    $venda->caixa_id = (int)$caixa_aberto['id'];
    $venda->mesa_id = !empty($pedido['mesa_id']) ? (int)$pedido['mesa_id'] : null;
    $venda->total = $total_bruto;
    $venda->desconto = $desconto_valor;
    $venda->total_final = $total_final;
    $venda->forma_pagamento = $forma_pagamento;
    // Só aqui a venda é marcada como paga
    $venda->status = 'PAGO';
    $venda->numero_fatura = $venda->gerarNumeroFatura($restaurante_id);

    $venda_id = $venda->criar();
    if (!$venda_id) {
        throw new Exception('Erro ao criar venda');
    }

    $produto = new Produto($db);
    foreach ($itens as $item) {
        $produto_id = (int)$item['produto_id'];
        $qtd = (int)$item['quantidade'];
        $preco = (float)$item['preco_unitario'];

        if (!$venda->adicionarItem($venda_id, $produto_id, $qtd, $preco)) {
            throw new Exception('Erro ao adicionar item da venda');
        }

        if (!$produto->atualizarEstoque($produto_id, $restaurante_id, $qtd, 'SAIDA')) {
            throw new Exception('Erro ao atualizar estoque de produto');
        }
    }

    $stmt_upd_pedido = $db->prepare(
        "UPDATE pedidos
         SET status = 'PAGO',
             total = :total,
             atualizado_em = NOW(),
             observacao = CASE
                 WHEN :obs_empty = '' THEN observacao
                 WHEN observacao IS NULL OR observacao = '' THEN :obs_value
                 ELSE CONCAT(observacao, ' | ', :obs_concat)
             END
         WHERE id = :id AND restaurante_id = :rid"
    );
    $stmt_upd_pedido->execute([
        'total' => $total_bruto,
        'obs_empty' => $observacao,
        'obs_value' => $observacao,
        'obs_concat' => $observacao,
        'id' => $pedido_id,
        'rid' => $restaurante_id,
    ]);

    if (!empty($pedido['mesa_id'])) {
        $stmt_mesa_aberta = $db->prepare(
            "SELECT COUNT(*)
             FROM pedidos
             WHERE restaurante_id = :rid
               AND mesa_id = :mesa_id
               AND id <> :pedido_id
               AND status IN ('PENDENTE','NOVO','CONFIRMADO','PREPARANDO','PRONTO','ENTREGUE')"
        );
        $stmt_mesa_aberta->execute([
            'rid' => $restaurante_id,
            'mesa_id' => (int)$pedido['mesa_id'],
            'pedido_id' => $pedido_id,
        ]);

        if ((int)$stmt_mesa_aberta->fetchColumn() === 0) {
            $mesa = new Mesa($db);
            $mesa->atualizarStatus((int)$pedido['mesa_id'], $restaurante_id, 'LIVRE');
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pagamento registrado com sucesso',
        'venda_id' => (int)$venda_id,
        'numero_fatura' => $venda->numero_fatura,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[pedido_pagar] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
