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
    COUNT(*) as total_pedidos,
    SUM(CASE WHEN status = 'NOVO' OR status = 'PENDENTE' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'PREPARANDO' OR status = 'PRONTO' THEN 1 ELSE 0 END) as preparados,
    SUM(CASE WHEN status = 'ENTREGUE' THEN 1 ELSE 0 END) as entregues
FROM pedidos 
WHERE garcom_id = :garcom_id AND restaurante_id = :rid AND DATE(criado_em) = CURDATE()";

$stmt = $db->prepare($query);
$stmt->execute(['garcom_id' => $garcom_id, 'rid' => $restaurante_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total_pedidos' => (int)$stats['total_pedidos'],
    'pendentes' => (int)$stats['pendentes'],
    'preparados' => (int)$stats['preparados'],
    'entregues' => (int)$stats['entregues']
]);
?>

