<?php

/**
 * Job diario: notificar vencimento proximo de planos (D-7 e D-1).
 *
 * Execucao sugerida (Windows Task Scheduler):
 * php C:\caminho\projeto\src\public\cron\notificar_vencimento_planos.php
 */

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/plano_notificacoes.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Falha de conexao com banco'
    ]);
    exit;
}

try {
    $colunasRest = [];
    $stmtCols = $db->query('SHOW COLUMNS FROM restaurantes');
    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
        $colunasRest[] = $col['Field'];
    }

    $colunaFim = in_array('data_fim_plano', $colunasRest, true) ? 'r.data_fim_plano' : 'r.data_fim';

    $hasRestaurantePlanos = (bool)$db->query("SHOW TABLES LIKE 'restaurante_planos'")->fetchColumn();
    $joinPlano = '';
    $dataFimExpr = $colunaFim;
    if ($hasRestaurantePlanos) {
        $joinPlano = "
            LEFT JOIN (
                SELECT restaurante_id, MAX(data_fim) AS data_fim
                FROM restaurante_planos
                WHERE status = 'ATIVO'
                GROUP BY restaurante_id
            ) rp ON rp.restaurante_id = r.id
        ";
        $dataFimExpr = "COALESCE(rp.data_fim, {$colunaFim})";
    }

    $sql = "
        SELECT
            r.id,
            r.nome,
            r.email,
            r.telefone,
            r.plano,
            {$dataFimExpr} AS data_fim,
            DATEDIFF(DATE({$dataFimExpr}), CURDATE()) AS dias_restantes,
            (
                SELECT u.email
                FROM usuarios u
                WHERE u.restaurante_id = r.id
                  AND u.perfil = 'ADMIN'
                ORDER BY u.id ASC
                LIMIT 1
            ) AS admin_email
        FROM restaurantes r
        {$joinPlano}
        WHERE {$dataFimExpr} IS NOT NULL
          AND r.status = 'ATIVO'
          AND DATEDIFF(DATE({$dataFimExpr}), CURDATE()) IN (7, 3, 1)
    ";

    $stmt = $db->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $enviadas = 0;
    $falhas = 0;

    foreach ($rows as $r) {
        $emailDestino = !empty($r['admin_email']) ? $r['admin_email'] : ($r['email'] ?? '');
        $ok = plano_notificar_vencimento_proximo(
            $emailDestino,
            $r['telefone'] ?? '',
            $r['nome'] ?? 'Restaurante',
            $r['plano'] ?? 'BASICO',
            $r['data_fim'] ?? date('Y-m-d'),
            (int)($r['dias_restantes'] ?? 0)
        );

        if ($ok) {
            $enviadas++;
        } else {
            $falhas++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Job executado',
        'total_alvos' => count($rows),
        'emails_enviados' => $enviadas,
        'emails_falharam' => $falhas
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no job: ' . $e->getMessage()
    ]);
}
