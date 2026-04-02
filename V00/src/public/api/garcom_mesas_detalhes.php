<?php

/*
   API – Mesas com dados do pedido ativo
   Retorna todas as mesas do restaurante com informações do
   pedido em aberto (NOVO, PREPARANDO, PRONTO ou ENTREGUE)
   para a tela principal do garçom.
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$perfil = strtoupper(trim($_SESSION['perfil'] ?? ''));
if ($perfil === 'GARÇOM') $perfil = 'GARCOM';

if (!in_array($perfil, ['GARCOM', 'ADMIN', 'CAIXA'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado']));
}

$restaurante_id = (int)$_SESSION['restaurante_id'];
$db = (new Database())->getConnection();

// Busca todas as mesas com o pedido ativo mais recente (não pago/cancelado)
$sql = "
    SELECT
        m.id,
        m.numero,
        m.capacidade,
        m.status,
        p.id            AS pedido_id,
        p.numero_pedido,
        p.status        AS pedido_status,
        p.total         AS pedido_total,
        p.criado_em     AS pedido_criado_em,
        p.garcom_id,
        u.nome          AS garcom_nome,
        (SELECT COUNT(*) FROM itens_pedido ip WHERE ip.pedido_id = p.id) AS pedido_itens,
        (SELECT COUNT(*) FROM itens_pedido ip WHERE ip.pedido_id = p.id AND COALESCE(ip.status,'pendente') = 'pronto' AND (ip.destino = 'cozinha' OR ip.destino IS NULL)) AS itens_prontos_cozinha,
        (SELECT COUNT(*) FROM itens_pedido ip WHERE ip.pedido_id = p.id AND COALESCE(ip.status,'pendente') = 'pronto' AND ip.destino = 'bar') AS itens_prontos_bar,
        TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS pedido_minutos
    FROM mesas m
    LEFT JOIN pedidos p
        ON p.mesa_id = m.id
        AND p.restaurante_id = :rid
        AND p.status NOT IN ('PAGO','CANCELADO','ENTREGUE')
        AND DATE(p.criado_em) = CURDATE()
        AND p.id = (
            SELECT id FROM pedidos
            WHERE mesa_id = m.id
              AND restaurante_id = :rid2
              AND status NOT IN ('PAGO','CANCELADO','ENTREGUE')
              AND DATE(criado_em) = CURDATE()
            ORDER BY criado_em DESC
            LIMIT 1
        )
    LEFT JOIN usuarios u ON u.id = p.garcom_id
    WHERE m.restaurante_id = :rid3
    ORDER BY m.numero ASC
";

$stmt = $db->prepare($sql);
$stmt->bindValue(':rid',  $restaurante_id, PDO::PARAM_INT);
$stmt->bindValue(':rid2', $restaurante_id, PDO::PARAM_INT);
$stmt->bindValue(':rid3', $restaurante_id, PDO::PARAM_INT);
$stmt->execute();
$mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalizar tipos e valores
foreach ($mesas as &$m) {
    $m['id']            = (int)$m['id'];
    $m['numero']        = (int)$m['numero'];
    $m['capacidade']    = (int)($m['capacidade'] ?? 4);
    $m['pedido_id']     = $m['pedido_id'] ? (int)$m['pedido_id'] : null;
    $m['pedido_total']  = $m['pedido_total'] !== null ? (float)$m['pedido_total'] : null;
    $m['pedido_itens']  = (int)$m['pedido_itens'];
    $m['itens_prontos_cozinha'] = (int)($m['itens_prontos_cozinha'] ?? 0);
    $m['itens_prontos_bar'] = (int)($m['itens_prontos_bar'] ?? 0);
    $m['pedido_minutos'] = $m['pedido_minutos'] !== null ? (int)$m['pedido_minutos'] : null;
}
unset($m);

// Estatísticas rápidas
$total    = count($mesas);
$livres   = count(array_filter($mesas, fn($x) => $x['status'] === 'LIVRE'));
$ocupadas = count(array_filter($mesas, fn($x) => $x['status'] === 'OCUPADA'));
$atrasadas = count(array_filter($mesas, fn($x) => $x['pedido_minutos'] !== null && $x['pedido_minutos'] >= 15));

echo json_encode([
    'success'   => true,
    'mesas'     => $mesas,
    'stats'     => compact('total', 'livres', 'ocupadas', 'atrasadas'),
    'updated_at' => date('c'),
], JSON_UNESCAPED_UNICODE);
