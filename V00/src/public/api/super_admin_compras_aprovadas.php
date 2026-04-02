<?php

/*
   API - Super Admin Listar Compras Aprovadas
   Retorna apenas os restaurantes que têm planos ativos/aprovados
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

super_admin_require_permission_json('view_finance');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    // Buscar apenas compras aprovadas
    $stmt = $db->query("
        SELECT cp.*, r.nome as restaurante_nome, r.email as restaurante_email, r.status as restaurante_status
        FROM compras_planos cp
        INNER JOIN restaurantes r ON cp.restaurante_id = r.id
        WHERE cp.status = 'APROVADO'
        ORDER BY cp.criado_em DESC
    ");
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $compras
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
