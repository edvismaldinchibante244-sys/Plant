<?php

/*
   API - Snapshot de Pedidos do Dia
   Retorna payload JSON para atualização incremental no painel.
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/pedido_schema.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['restaurante_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nao autenticado']);
    exit;
}

function normalizar_status_pedido($status)
{
    $status = strtoupper(trim((string)$status));
    $map = [
        'PENDENTE' => 'NOVO',
        'CONFIRMADO' => 'PREPARANDO',
        'NOVO' => 'NOVO',
        'PREPARANDO' => 'PREPARANDO',
        'PRONTO' => 'PRONTO',
        'ENTREGUE' => 'ENTREGUE',
        'PAGO' => 'PAGO',
        'CANCELADO' => 'CANCELADO'
    ];
    return $map[$status] ?? 'NOVO';
}

try {
    $database = new Database();
    $db = $database->getConnection();
    pedido_schema_garantir($db);

    $query = "SELECT p.*, m.numero as mesa_numero,
              (SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = p.id) as total_itens
              FROM pedidos p
              LEFT JOIN mesas m ON p.mesa_id = m.id
              WHERE p.restaurante_id = :rid
              AND DATE(p.criado_em) = CURDATE()
              ORDER BY p.criado_em DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':rid', $_SESSION['restaurante_id'], PDO::PARAM_INT);
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $contadores = ['NOVO' => 0, 'PREPARANDO' => 0, 'PRONTO' => 0, 'ENTREGUE' => 0, 'PAGO' => 0, 'CANCELADO' => 0];

    foreach ($pedidos as &$pedido) {
        $pedido['status'] = normalizar_status_pedido($pedido['status'] ?? 'NOVO');
        $pedido['origem'] = pedido_normalizar_origem($pedido['origem'] ?? 'BALCAO');
        if (isset($contadores[$pedido['status']])) {
            $contadores[$pedido['status']]++;
        }
    }
    unset($pedido);

    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos,
        'contadores' => $contadores,
        'updated_at' => date('c')
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[pedidos_snapshot] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar pedidos']);
}
