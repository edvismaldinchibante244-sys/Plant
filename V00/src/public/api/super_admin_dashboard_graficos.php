<?php

/*
  API - Super Admin Dashboard Gráficos
  Retorna dados para gráficos do dashboard
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/super_admin_permissions.php';

header('Content-Type: application/json');

// Verificar se é super admin
if (!isset($_SESSION['super_admin']) || $_SESSION['super_admin'] != 1) {
    echo json_encode(["success" => false, "message" => "Acesso negado"]);
    exit;
}

super_admin_require_permission_json('view_dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    // 1. Restaurantes por plano (gráfico pizza)
    $stmt = $db->query("
        SELECT plano, COUNT(*) as total 
        FROM restaurantes 
        GROUP BY plano
    ");
    $restaurantesPorPlano = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Restaurantes por status (gráfico pizza)
    $stmt = $db->query("
        SELECT status, COUNT(*) as total 
        FROM restaurantes 
        GROUP BY status
    ");
    $restaurantesPorStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Crescimento de restaurantes por mês (gráfico linha)
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(criado_em, '%Y-%m') as mes,
            COUNT(*) as total
        FROM restaurantes
        WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
        ORDER BY mes
    ");
    $crescimentoRestaurantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Receita mensal por plano (gráfico barra)
    $stmt = $db->query("
        SELECT 
            plano,
            SUM(CASE 
                    WHEN plano IN ('ENTERPRISE', 'EMPRESARIAL') THEN 3000
                WHEN plano = 'PROFISSIONAL' THEN 1500
                ELSE 0
            END) as receita
        FROM restaurantes
        WHERE status = 'ATIVO'
        GROUP BY plano
    ");
    $receitaPorPlano = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Top 5 restaurantes por usuários
    $stmt = $db->query("
        SELECT r.nome, COUNT(u.id) as total_usuarios
        FROM restaurantes r
        LEFT JOIN usuarios u ON r.id = u.restaurante_id
        GROUP BY r.id, r.nome
        ORDER BY total_usuarios DESC
        LIMIT 5
    ");
    $topRestaurantesUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Compras de planos por mês
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(criado_em, '%Y-%m') as mes,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'APROVADO' THEN 1 ELSE 0 END) as aprovados
        FROM compras_planos
        WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
        ORDER BY mes
    ");
    $comprasPorMes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => [
            "restaurantes_por_plano" => $restaurantesPorPlano,
            "restaurantes_por_status" => $restaurantesPorStatus,
            "crescimento_restaurantes" => $crescimentoRestaurantes,
            "receita_por_plano" => $receitaPorPlano,
            "top_restaurantes_usuarios" => $topRestaurantesUsuarios,
            "compras_por_mes" => $comprasPorMes
        ]
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
