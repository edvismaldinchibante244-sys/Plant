<?php

/*
   API - Server Sent Events para pedidos
  Envia atualizacoes em tempo real do dashboard sem refresh.
*/

session_start();
include_once '../../config/database.php';
include_once '../../config/pedido_schema.php';

if (!isset($_SESSION['restaurante_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nao autenticado';
    exit;
}

// Evita travar outras requisições da mesma sessão durante o stream SSE.
$restauranteId = (int)$_SESSION['restaurante_id'];
session_write_close();

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

function carregar_payload_pedidos($db, $restauranteId)
{
    $query = "SELECT p.*, m.numero as mesa_numero,
              (SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = p.id) as total_itens
              FROM pedidos p
              LEFT JOIN mesas m ON p.mesa_id = m.id
              WHERE p.restaurante_id = :rid
              AND DATE(p.criado_em) = CURDATE()
              ORDER BY p.criado_em DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':rid', $restauranteId, PDO::PARAM_INT);
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

    return [
        'pedidos' => $pedidos,
        'contadores' => $contadores,
        'updated_at' => date('c')
    ];
}

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', '1');

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

set_time_limit(0);
$database = new Database();
$db = $database->getConnection();
pedido_schema_garantir($db);
$lastHash = '';

// Mantem a conexao por alguns minutos para evitar sessao infinita no backend.
for ($i = 0; $i < 120; $i++) {
    if (connection_aborted()) {
        break;
    }

    try {
        $payload = carregar_payload_pedidos($db, $restauranteId);
        $hash = md5(json_encode($payload, JSON_UNESCAPED_UNICODE));

        if ($hash !== $lastHash) {
            echo "event: pedidos\n";
            echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
            $lastHash = $hash;
        } else {
            echo ": heartbeat\n\n";
        }

        @flush();
    } catch (Exception $e) {
        error_log('[pedido_sse] ' . $e->getMessage());
        echo "event: error\n";
        echo "data: {\"message\":\"erro interno\"}\n\n";
        @flush();
    }

    sleep(3);
}
