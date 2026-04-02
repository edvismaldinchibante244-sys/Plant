<?php

/*
 
   API - LISTAR TODOS OS RESTAURANTES
  Retorna lista de restaurantes do sistema
 
 */

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/super_admin_check.php';
include_once __DIR__ . '/../../config/restaurante_status_helper.php';

header('Content-Type: application/json; charset=utf-8');

requireSuperAdminPermission('manage_restaurants');

$database = new Database();
$db = $database->getConnection();

try {
    $statusSql = restaurante_status_sql_campos($db, 'r');

    // Buscar todos os restaurantes
    $query = "SELECT 
                r.id,
                r.nome,
                r.email,
                r.telefone,
                r.endereco,
                r.cidade,
                r.nuit,
                r.plano,
                r.status,
                {$statusSql['status_exibicao']},
                {$statusSql['possui_compra_pendente']},
                r.data_inicio,
                r.data_fim,
                r.criado_em,
                (SELECT COUNT(*) FROM usuarios WHERE restaurante_id = r.id) as total_usuarios,
                (SELECT COUNT(*) FROM produtos WHERE restaurante_id = r.id) as total_produtos,
                (SELECT COUNT(*) FROM mesas WHERE restaurante_id = r.id) as total_mesas
              FROM restaurantes r 
              ORDER BY r.id DESC";

    $stmt = $db->query($query);
    $restaurantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $restaurantes,
        'total' => count($restaurantes)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao listar restaurantes: ' . $e->getMessage()
    ]);
}
