<?php

/*
  API - Atualizar status de item do pedido
  Fluxo: pendente -> em_preparo -> pronto -> entregue
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';

function log_debug($msg)
{
    $logDir = sys_get_temp_dir();
    if (!$logDir || !is_dir($logDir) || !is_writable($logDir)) {
        $logDir = __DIR__ . '/../../../';
    }
    $log_file = rtrim($logDir, "/\\") . DIRECTORY_SEPARATOR . 'pedido_status_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $data = "[{$timestamp}] {$msg}\n";
    @file_put_contents($log_file, $data, FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json; charset=utf-8');

log_debug("REQUEST HIT: method=" . $_SERVER['REQUEST_METHOD'] . ", POST=" . json_encode($_POST));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$perfil = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
if ($perfil === 'GARÇOM') {
    $perfil = 'GARCOM';
}
if (!in_array($perfil, ['ADMIN', 'COZINHA', 'BAR'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$restaurante_id = (int)($_SESSION['restaurante_id'] ?? 0);
$item_id = (int)($_POST['item_id'] ?? 0);
$novoStatus = strtolower(trim((string)($_POST['status'] ?? '')));

log_debug("PARAMS: restaurante_id={$restaurante_id}, item_id={$item_id}, status='{$novoStatus}', POST=" . json_encode($_POST) . ", SESSION perfil='{$perfil}' rid=" . ($_SESSION['restaurante_id'] ?? 'MISSING'));

$permitidos = ['pendente', 'em_preparo', 'pronto', 'entregue'];
if ($restaurante_id <= 0 || $item_id <= 0 || !in_array($novoStatus, $permitidos, true)) {
    log_debug("VALIDATION FAILED: restaurante_id={$restaurante_id}, item_id={$item_id}, status='{$novoStatus}'");
    echo json_encode(['success' => false, 'message' => 'Dados inválidos (ver log)']);
    exit;
}

$db = (new Database())->getConnection();
$db->beginTransaction();

try {
    $stmtItem = $db->prepare(
        "SELECT ip.id, ip.pedido_id,
                COALESCE(ip.status, 'pendente') AS status_atual,
                COALESCE(ip.destino, 'cozinha') AS destino
         FROM itens_pedido ip
         INNER JOIN pedidos p ON p.id = ip.pedido_id
         WHERE ip.id = :item_id AND p.restaurante_id = :rid
         LIMIT 1"
    );
    $stmtItem->execute(['item_id' => $item_id, 'rid' => $restaurante_id]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item não encontrado');
    }

    $destinoItem = strtolower((string)($item['destino'] ?? 'cozinha'));
    if ($perfil === 'COZINHA' && $destinoItem !== 'cozinha') {
        throw new Exception('Sem permissão para atualizar item do bar');
    }
    if ($perfil === 'BAR' && $destinoItem !== 'bar') {
        throw new Exception('Sem permissão para atualizar item da cozinha');
    }

    $statusAtual = strtolower((string)$item['status_atual']);
    $transicoes = [
        'pendente' => ['em_preparo'],
        'em_preparo' => ['pronto'],
        'pronto' => ['entregue'],
        'entregue' => [],
    ];

    // Admin pode ajustar livremente; demais seguem fluxo.
    if ($perfil !== 'ADMIN') {
        $next = $transicoes[$statusAtual] ?? [];
        if (!in_array($novoStatus, $next, true)) {
            throw new Exception('Transição de status inválida');
        }
    }

    $sets = ["status = :status"];
    if ($novoStatus === 'em_preparo') {
        $sets[] = "iniciado_preparo_em = COALESCE(iniciado_preparo_em, NOW())";
    }
    if ($novoStatus === 'pronto') {
        $sets[] = "pronto_em = COALESCE(pronto_em, NOW())";
    }
    if ($novoStatus === 'entregue') {
        $sets[] = "entregue_em = COALESCE(entregue_em, NOW())";
    }

    $sqlUpdate = "UPDATE itens_pedido SET " . implode(', ', $sets) . " WHERE id = :item_id";
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->execute(['status' => $novoStatus, 'item_id' => $item_id]);

    $pedidoId = (int)$item['pedido_id'];

    // Recalcular status geral do pedido para manter telas legadas compatíveis.
    $stmtResumo = $db->prepare(
        "SELECT
            SUM(CASE WHEN COALESCE(status,'pendente') = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN COALESCE(status,'pendente') = 'em_preparo' THEN 1 ELSE 0 END) AS preparo,
            SUM(CASE WHEN COALESCE(status,'pendente') = 'pronto' THEN 1 ELSE 0 END) AS prontos,
            SUM(CASE WHEN COALESCE(status,'pendente') = 'entregue' THEN 1 ELSE 0 END) AS entregues,
            COUNT(*) AS total
         FROM itens_pedido
         WHERE pedido_id = :pedido_id"
    );
    $stmtResumo->execute(['pedido_id' => $pedidoId]);
    $r = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: ['pendentes' => 0, 'preparo' => 0, 'prontos' => 0, 'entregues' => 0, 'total' => 0];

    $pedidoStatus = 'NOVO';
    if ((int)$r['entregues'] === (int)$r['total'] && (int)$r['total'] > 0) {
        $pedidoStatus = 'ENTREGUE';
    } elseif ((int)$r['preparo'] > 0 || (int)$r['prontos'] > 0) {
        $pedidoStatus = 'PREPARANDO';
        if ((int)$r['pendentes'] === 0 && (int)$r['preparo'] === 0 && (int)$r['prontos'] > 0) {
            $pedidoStatus = 'PRONTO';
        }
    }

    $stmtPedido = $db->prepare(
        "UPDATE pedidos
         SET status = :status,
             atualizado_em = NOW(),
             iniciado_preparo_em = CASE WHEN :marcar_preparo = 1 THEN COALESCE(iniciado_preparo_em, NOW()) ELSE iniciado_preparo_em END
         WHERE id = :pedido_id AND restaurante_id = :rid"
    );
    $stmtPedido->execute([
        'status' => $pedidoStatus,
        'marcar_preparo' => $pedidoStatus === 'PREPARANDO' ? 1 : 0,
        'pedido_id' => $pedidoId,
        'rid' => $restaurante_id,
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Status do item atualizado',
        'pedido_status' => $pedidoStatus,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
