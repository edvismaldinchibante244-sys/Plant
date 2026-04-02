<?php
session_start();
include_once '../../config/database.php';
include_once '../../config/auth_check.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$rid = $_SESSION['restaurante_id'] ?? 0;
$uid = $_SESSION['usuario_id'] ?? 0;

if ($rid <= 0 || $uid <= 0 || !in_array($_SESSION['perfil'], ['CAIXA', 'ADMIN'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$stmt = $db->prepare("
    SELECT 
        forma_pagamento,
        COUNT(*) as qtd,
        SUM(total_final) as total
    FROM vendas 
    WHERE restaurante_id = ? AND usuario_id = ? AND DATE(criado_em) = CURDATE() AND status = 'PAGO'
    GROUP BY forma_pagamento
");
$stmt->execute([$rid, $uid]);
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dados = [
    'DINHEIRO' => ['qtd' => 0, 'total' => 0],
    'M-PESA' => ['qtd' => 0, 'total' => 0],
    'E-MOLA' => ['qtd' => 0, 'total' => 0],
    'CARTAO' => ['qtd' => 0, 'total' => 0],
    'PIX' => ['qtd' => 0, 'total' => 0]
];

foreach ($pagamentos as $p) {
    $dados[$p['forma_pagamento']] = [
        'qtd' => (int)$p['qtd'],
        'total' => number_format($p['total'], 2)
    ];
}

echo json_encode($dados);
?>

