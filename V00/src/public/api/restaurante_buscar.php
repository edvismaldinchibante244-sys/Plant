<?php

/*
 
  API - BUSCAR RESTAURANTE POR ID
  Retorna os dados de um restaurante específico
 
 */

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/super_admin_check.php';
include_once __DIR__ . '/../../config/restaurante_status_helper.php';

header('Content-Type: application/json; charset=utf-8');

requireSuperAdminPermission('manage_restaurants');

$database = new Database();
$db = $database->getConnection();

$id = intval($_GET['id'] ?? 0);

// Validar ID
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do restaurante inválido'
    ]);
    exit;
}

try {
    $statusSql = restaurante_status_sql_campos($db, 'r');

    // Buscar restaurante
    $query = "SELECT 
                r.id,
                r.nome,
                r.email,
                r.telefone,
                r.endereco,
                r.cidade,
                r.nuit,
                r.logo,
                r.plano,
                r.status,
                {$statusSql['status_exibicao']},
                {$statusSql['possui_compra_pendente']},
                r.data_inicio,
                r.data_fim,
                r.criado_em,
                r.atualizado_em,
                (SELECT COUNT(*) FROM usuarios WHERE restaurante_id = r.id) as total_usuarios,
                (SELECT COUNT(*) FROM produtos WHERE restaurante_id = r.id) as total_produtos,
                (SELECT COUNT(*) FROM mesas WHERE restaurante_id = r.id) as total_mesas,
                (SELECT COUNT(*) FROM vendas WHERE restaurante_id = r.id) as total_vendas
              FROM restaurantes r 
              WHERE r.id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    $restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($restaurante) {
        echo json_encode([
            'success' => true,
            'data' => $restaurante
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Restaurante não encontrado'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar restaurante: ' . $e->getMessage()
    ]);
}
