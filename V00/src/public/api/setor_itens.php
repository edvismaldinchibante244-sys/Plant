<?php

/*
   API - Itens por setor (cozinha/bar)
   Retorna itens de pedidos ativos com status independente.
*/

session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

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
$destino = strtolower(trim((string)($_GET['destino'] ?? 'cozinha')));
if (!in_array($destino, ['cozinha', 'bar'], true)) {
    $destino = 'cozinha';
}

// Perfis operacionais só podem ver seu proprio setor.
if ($perfil === 'COZINHA') {
    $destino = 'cozinha';
}
if ($perfil === 'BAR') {
    $destino = 'bar';
}

if ($restaurante_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$db = (new Database())->getConnection();

$query = "SELECT
            ip.id,
            ip.pedido_id,
            ip.produto_id,
            COALESCE(p.nome, CONCAT('Produto #', ip.produto_id)) AS produto_nome,
            ip.quantidade,
            COALESCE(ip.destino, 'cozinha') AS destino,
            COALESCE(ip.status, 'pendente') AS status,
            ip.iniciado_preparo_em,
            ip.pronto_em,
            ip.entregue_em,
            ped.numero_pedido,
            ped.observacao,
            ped.criado_em,
            m.numero AS mesa_numero
          FROM itens_pedido ip
          INNER JOIN pedidos ped ON ped.id = ip.pedido_id
          LEFT JOIN produtos p ON p.id = ip.produto_id
          LEFT JOIN mesas m ON m.id = ped.mesa_id
          WHERE ped.restaurante_id = :rid
            AND ped.status NOT IN ('PAGO','CANCELADO')
            AND COALESCE(ip.destino, 'cozinha') = :destino
            AND COALESCE(ip.status, 'pendente') IN ('pendente','em_preparo','pronto')
            AND DATE(ped.criado_em) = CURDATE()
          ORDER BY
            FIELD(COALESCE(ip.status, 'pendente'), 'pendente','em_preparo','pronto'),
            ped.criado_em ASC,
            ip.id ASC";

$stmt = $db->prepare($query);
$stmt->execute([
    'rid' => $restaurante_id,
    'destino' => $destino,
]);

$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($itens as &$it) {
    $it['id'] = (int)$it['id'];
    $it['pedido_id'] = (int)$it['pedido_id'];
    $it['produto_id'] = (int)$it['produto_id'];
    $it['quantidade'] = (int)$it['quantidade'];
}
unset($it);

echo json_encode([
    'success' => true,
    'destino' => $destino,
    'itens' => $itens,
    'updated_at' => date('c'),
], JSON_UNESCAPED_UNICODE);
