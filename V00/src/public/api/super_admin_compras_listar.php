<?php

/*
   API - Super Admin Listar Todas as Compras de Planos
   Lista as compras de planos de todos os restaurantes
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/super_admin_permissions.php';

header('Content-Type: application/json');

// Verificar se é super admin (fundador SaaS)
$isSuperAdmin = isset($_SESSION['logado'], $_SESSION['super_admin'])
    && $_SESSION['logado'] === true
    && intval($_SESSION['super_admin']) === 1;

if (!$isSuperAdmin) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Acesso negado"]);
    exit;
}

super_admin_require_any_permission_json(['view_finance', 'approve_plans']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    $colunasCompra = [];
    $stmtCols = $db->query('SHOW COLUMNS FROM compras_planos');
    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
        $colunasCompra[] = $col['Field'];
    }

    $colData = in_array('criado_em', $colunasCompra, true) ? 'criado_em' : 'created_at';

    // Buscar todas as compras com dados do restaurante
    $stmt = $db->query(" 
        SELECT cp.*, cp." . $colData . " AS data_compra, r.nome as restaurante_nome, r.email as restaurante_email
        FROM compras_planos cp
        INNER JOIN restaurantes r ON cp.restaurante_id = r.id
        ORDER BY cp." . $colData . " DESC
    ");
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $compras
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
