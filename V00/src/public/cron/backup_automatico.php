<?php

/**
 * Job diario/horario: executar backups automaticos dos restaurantes elegiveis.
 *
 * Execucao sugerida (Windows Task Scheduler):
 * php "C:\caminho\projeto\src\public\cron\backup_automatico.php"
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/plano_check.php';
require_once __DIR__ . '/../../config/backup_helper.php';

set_time_limit(0);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Este job deve ser executado via CLI.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    fwrite(STDERR, "Falha de conexao com banco\n");
    exit(1);
}

backup_ensure_tables_exist($db);

try {
    $stmt = $db->query("
        SELECT r.id, r.nome, r.plano, r.status,
               c.id AS config_id,
               c.automatico,
               c.frequencia,
               c.hora_execucao,
               c.retencao_dias,
               c.ultimo_backup_em,
               c.proximo_backup_em
        FROM restaurantes r
        LEFT JOIN backup_configuracoes c ON c.restaurante_id = r.id
        WHERE r.status = 'ATIVO'
        ORDER BY r.id ASC
    ");
    $restaurantes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $executados = 0;
    $falhas = 0;
    $ignorados = 0;

    foreach ($restaurantes as $restaurante) {
        $restauranteId = (int)($restaurante['id'] ?? 0);
        if ($restauranteId <= 0) {
            continue;
        }

        $temAuto = plano_tem_funcionalidade_db($restauranteId, 'backup_automatico')
            || plano_tem_funcionalidade_db($restauranteId, 'backup_diario')
            || plano_tem_funcionalidade_db($restauranteId, 'backup_hora');

        if (!$temAuto) {
            $ignorados++;
            continue;
        }

        $config = backup_obter_configuracao($db, $restauranteId);
        if (empty($config['id'])) {
            $defaultFreq = plano_tem_funcionalidade_db($restauranteId, 'backup_hora') ? 'HORARIO' : 'DIARIO';
            $config = backup_salvar_configuracao($db, $restauranteId, [
                'automatico' => 1,
                'frequencia' => $defaultFreq,
                'hora_execucao' => '00:00:00',
                'retencao_dias' => 30,
                'notificar_email' => 0,
            ]);
        }

        if (empty($config['automatico'])) {
            $ignorados++;
            continue;
        }

        $agora = new DateTimeImmutable('now');
        if (!empty($config['proximo_backup_em'])) {
            $proxima = new DateTimeImmutable((string)$config['proximo_backup_em']);
            if ($proxima > $agora) {
                $ignorados++;
                continue;
            }
        }

        $resultado = backup_executar_geracao($db, $restauranteId, $config, 'AUTOMATICO', 'cron');
        if (!empty($resultado['success'])) {
            $executados++;
        } else {
            $falhas++;
            error_log('[BACKUP_CRON][ERRO] Restaurante #' . $restauranteId . ': ' . ($resultado['message'] ?? 'Erro desconhecido'));
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Job executado com sucesso.',
        'total_restaurantes' => count($restaurantes),
        'executados' => $executados,
        'falhas' => $falhas,
        'ignorados' => $ignorados,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro no job de backup: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
