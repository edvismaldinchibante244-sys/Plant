<?php
session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$rid = $_SESSION['restaurante_id'] ?? 0;

if ($rid <= 0 || !checkPermission(['ADMIN'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Admin only']));
}

// Revenue trend - 30 days
$stmt_trend = $db->prepare("
    SELECT DATE(criado_em) as dia, SUM(total_final) as total
    FROM vendas 
    WHERE restaurante_id = ? AND status = 'PAGO' AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY dia 
    ORDER BY dia DESC
    LIMIT 30
");
$stmt_trend->execute([$rid]);
$trend = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);

$total_mes = $db->prepare("SELECT SUM(total_final) as total_mes FROM vendas WHERE restaurante_id = ? AND MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE()) AND status = 'PAGO'");
$total_mes->execute([$rid]);
$total_mes_val = $total_mes->fetch(PDO::FETCH_ASSOC)['total_mes'] ?? 0;

$pedidos_pendentes = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE restaurante_id = ? AND status IN ('NOVO', 'PENDENTE')");
$pedidos_pendentes->execute([$rid]);
$pendentes = $pedidos_pendentes->fetch(PDO::FETCH_ASSOC)['total'];

echo json_encode([
    'trend' => $trend,
    'total_mes' => number_format($total_mes_val, 2),
    'pedidos_pendentes' => (int)$pendentes
]);
?>

