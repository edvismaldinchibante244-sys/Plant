<?php
session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';

header('Content-Type: application/json');

$perfil = strtoupper(trim($_SESSION['perfil'] ?? ''));
if ($perfil === 'GARÇOM') {
    $perfil = 'GARCOM';
}
if (!in_array($perfil, ['GARCOM', 'ADMIN'])) {
    http_response_code(403);
    exit(json_encode(['success' => false]));
}

$restaurante_id = (int)$_SESSION['restaurante_id'];
$garcom_id = (int)$_SESSION['usuario_id'];

$db = (new Database())->getConnection();

$stmt = $db->prepare("SELECT COUNT(*) as total, 
    SUM(CASE WHEN status = 'OCUPADA' THEN 1 ELSE 0 END) as ocupadas,
    SUM(CASE WHEN status = 'LIVRE' THEN 1 ELSE 0 END) as livres
FROM mesas 
WHERE restaurante_id = :rid AND (garcom_id = :garcom_id OR garcom_id IS NULL)");

$stmt->execute(['garcom_id' => $garcom_id, 'rid' => $restaurante_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total' => (int)$stats['total'],
    'ocupadas' => (int)$stats['ocupadas'],
    'livres' => (int)$stats['livres']
]);
?>

