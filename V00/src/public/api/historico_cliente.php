<?php
// Histórico de reservas por cliente
// Endpoint: /api/historico_cliente.php

include_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

$cliente_id = (int)($_GET['cliente_id'] ?? 0);
if ($cliente_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'cliente_id obrigatório.']);
    exit;
}

$db = (new Database())->getConnection();

// Total de reservas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM reservas WHERE cliente_id = :cliente_id");
$stmt->execute([':cliente_id' => $cliente_id]);
$total = $stmt->fetchColumn();

// Frequência (reservas por mês)
$stmt = $db->prepare("SELECT DATE_FORMAT(data_reserva, '%Y-%m') as mes, COUNT(*) as qtd FROM reservas WHERE cliente_id = :cliente_id GROUP BY mes ORDER BY mes DESC");
$stmt->execute([':cliente_id' => $cliente_id]);
$frequencia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Restaurantes mais visitados
$stmt = $db->prepare("SELECT restaurante_id, COUNT(*) as qtd FROM reservas WHERE cliente_id = :cliente_id GROUP BY restaurante_id ORDER BY qtd DESC LIMIT 5");
$stmt->execute([':cliente_id' => $cliente_id]);
$restaurantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total_reservas' => (int)$total,
    'frequencia' => $frequencia,
    'restaurantes_mais_visitados' => $restaurantes
]);
