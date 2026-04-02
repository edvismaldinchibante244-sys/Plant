<?php
// api/pedido_cancelar_pagamento.php
// Cancela o pagamento de um pedido: volta status do pedido para 'ENTREGUE' e remove venda associada
// Comentários em português para manutenção

session_start();
include_once '../../config/database.php';
include_once '../../Model/Venda.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_SESSION['restaurante_id'], $_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$perfil = strtoupper(trim($_SESSION['perfil'] ?? ''));
if (!in_array($perfil, ['ADMIN', 'CAIXA'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para cancelar pagamento']);
    exit;
}

$pedido_id = (int)($_POST['id'] ?? 0);
if ($pedido_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido inválido']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Busca o pedido
    $stmt_pedido = $db->prepare('SELECT id, restaurante_id, status FROM pedidos WHERE id = :id AND restaurante_id = :rid LIMIT 1');
    $stmt_pedido->execute(['id' => $pedido_id, 'rid' => $_SESSION['restaurante_id']]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
    if ($pedido['status'] !== 'PAGO') {
        throw new Exception('Só é possível cancelar pagamento de pedidos pagos');
    }

    // Busca venda associada
    $stmt_venda = $db->prepare('SELECT id FROM vendas WHERE pedido_id = :pedido_id LIMIT 1');
    $stmt_venda->execute(['pedido_id' => $pedido_id]);
    $venda = $stmt_venda->fetch(PDO::FETCH_ASSOC);

    // Remove venda e itens_venda
    if ($venda) {
        $db->prepare('DELETE FROM itens_venda WHERE venda_id = :venda_id')->execute(['venda_id' => $venda['id']]);
        $db->prepare('DELETE FROM vendas WHERE id = :id')->execute(['id' => $venda['id']]);
    }

    // Atualiza status do pedido para ENTREGUE
    $db->prepare('UPDATE pedidos SET status = "ENTREGUE", atualizado_em = NOW() WHERE id = :id')->execute(['id' => $pedido_id]);

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
