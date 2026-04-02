<?php
session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$perfil = strtoupper(trim($_SESSION['perfil'] ?? ''));
if ($perfil === 'GARÇOM') {
    $perfil = 'GARCOM';
}
if (!in_array($perfil, ['GARCOM', 'ADMIN'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado']));
}

$restaurante_id = (int)$_SESSION['restaurante_id'];
$garcom_id = (int)$_SESSION['usuario_id'];

$db = (new Database())->getConnection();

$query = "SELECT 
    COUNT(*) as total_vendas,
    COALESCE(SUM(total), 0) as total_vendas_hoje
FROM pedidos 
WHERE garcom_id = :garcom_id AND restaurante_id = :rid AND DATE(criado_em) = CURDATE() AND status <> 'CANCELADO'";

$stmt = $db->prepare($query);
$stmt->execute(['garcom_id' => $garcom_id, 'rid' => $restaurante_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total_vendas' => (int)$stats['total_vendas'],
    'total_vendas_hoje' => (float)$stats['total_vendas_hoje']
]);
?>

