<?php
session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$rid = $_SESSION['restaurante_id'] ?? 0;

if ($rid <= 0 || !in_array($_SESSION['perfil'], ['CAIXA', 'ADMIN'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$stmt = $db->prepare("
    SELECT v.numero_fatura, v.total_final, v.forma_pagamento, v.status, v.criado_em, m.numero as mesa_numero
    FROM vendas v 
    LEFT JOIN mesas m ON v.mesa_id = m.id
    WHERE v.restaurante_id = ? AND v.status = 'PAGO'
    ORDER BY v.criado_em DESC 
    LIMIT 10
");
$stmt->execute([$rid]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pedidos as &$p) {
    $p['criado_em'] = date('H:i', strtotime($p['criado_em']));
}

echo json_encode($pedidos);
?>

