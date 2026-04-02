<?php

/**
   API - Super Admin Estatísticas
   Retorna estatísticas gerais do sistema
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

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    try {
        // Total de restaurantes
        $stmt = $db->query("SELECT COUNT(*) as total FROM restaurantes");
        $totalRestaurantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total até o fim do mês anterior (base para variação temporal)
        $stmt = $db->query("SELECT COUNT(*) as total FROM restaurantes WHERE criado_em < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $totalRestaurantesMesAnterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Restaurantes ativos
        $stmt = $db->query("SELECT COUNT(*) as total FROM restaurantes WHERE status = 'ATIVO'");
        $restaurantesAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM restaurantes WHERE status = 'ATIVO' AND criado_em < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $restaurantesAtivosMesAnterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Restaurantes suspensos/bloqueados
        $stmt = $db->query("SELECT COUNT(*) as total FROM restaurantes WHERE status = 'BLOQUEADO' OR status = 'CANCELADO'");
        $restaurantesSuspensos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Assinaturas expirando (próximos 7 dias)
        $stmt = $db->query("SELECT COUNT(*) as total FROM restaurantes WHERE status = 'ATIVO' AND data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $assinaturasExpirando = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM restaurantes WHERE status = 'ATIVO' AND data_fim BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 7 DAY)");
        $assinaturasExpirandoMesAnterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Receita mensal estimada (baseado nos planos ativos)
        $stmt = $db->query("
            SELECT 
                SUM(CASE 
                    WHEN plano = 'PROFISSIONAL' THEN 1500 
                       WHEN plano IN ('ENTERPRISE', 'EMPRESARIAL') THEN 3000 
                    ELSE 0 
                END) as receita_mensal
            FROM restaurantes 
            WHERE status = 'ATIVO'
        ");
        $receitaMensal = $stmt->fetch(PDO::FETCH_ASSOC)['receita_mensal'] ?? 0;

        $stmt = $db->query(" 
            SELECT 
                SUM(CASE 
                    WHEN plano_novo = 'PROFISSIONAL' THEN 1500 
                       WHEN plano_novo IN ('ENTERPRISE', 'EMPRESARIAL') THEN 3000 
                    ELSE 0 
                END) as receita_mensal
            FROM compras_planos 
            WHERE status = 'APROVADO'
              AND criado_em >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
              AND criado_em < DATE_FORMAT(CURDATE(), '%Y-%m-01')
        ");
        $receitaMensalMesAnterior = $stmt->fetch(PDO::FETCH_ASSOC)['receita_mensal'] ?? 0;

        $calcDelta = function ($current, $previous) {
            $current = floatval($current);
            $previous = floatval($previous);
            if ($previous <= 0) {
                return $current > 0 ? 100.0 : 0.0;
            }
            return (($current - $previous) / $previous) * 100.0;
        };

        echo json_encode([
            "success" => true,
            "data" => [
                "total_restaurantes" => intval($totalRestaurantes),
                "restaurantes_ativos" => intval($restaurantesAtivos),
                "restaurantes_suspensos" => intval($restaurantesSuspensos),
                "assinaturas_expirando" => intval($assinaturasExpirando),
                "receita_mensal" => floatval($receitaMensal),
                "comparativos" => [
                    "total_restaurantes" => [
                        "atual" => intval($totalRestaurantes),
                        "anterior" => intval($totalRestaurantesMesAnterior),
                        "variacao_percentual" => round($calcDelta($totalRestaurantes, $totalRestaurantesMesAnterior), 2)
                    ],
                    "restaurantes_ativos" => [
                        "atual" => intval($restaurantesAtivos),
                        "anterior" => intval($restaurantesAtivosMesAnterior),
                        "variacao_percentual" => round($calcDelta($restaurantesAtivos, $restaurantesAtivosMesAnterior), 2)
                    ],
                    "receita_mensal" => [
                        "atual" => floatval($receitaMensal),
                        "anterior" => floatval($receitaMensalMesAnterior),
                        "variacao_percentual" => round($calcDelta($receitaMensal, $receitaMensalMesAnterior), 2)
                    ],
                    "assinaturas_expirando" => [
                        "atual" => intval($assinaturasExpirando),
                        "anterior" => intval($assinaturasExpirandoMesAnterior),
                        "variacao_percentual" => round($calcDelta($assinaturasExpirando, $assinaturasExpirandoMesAnterior), 2)
                    ]
                ]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Erro: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
