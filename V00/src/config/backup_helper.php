<?php

/*
  Helpers compartilhados para backup manual, automatico, historico e cron.
*/

require_once __DIR__ . '/database.php';

if (!function_exists('backup_tabelas_principais')) {
    function backup_tabelas_principais(): array
    {
        return [
            'usuarios',
            'categorias',
            'clientes',
            'produtos',
            'mesas',
            'reservas',
            'pedidos',
            'vendas',
            'caixas',
        ];
    }
}

if (!function_exists('backup_tabelas_filhas')) {
    function backup_tabelas_filhas(): array
    {
        return [
            'itens_pedido' => [
                'parent' => 'pedidos',
                'parent_key' => 'pedido_id',
            ],
            'itens_venda' => [
                'parent' => 'vendas',
                'parent_key' => 'venda_id',
            ],
        ];
    }
}

if (!function_exists('backup_storage_dir')) {
    function backup_storage_dir(int $restauranteId): string
    {
        $baseDir = __DIR__ . '/../../storage/backups/restaurante_' . $restauranteId;
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }
        return $baseDir;
    }
}

if (!function_exists('backup_tabela_existe')) {
    function backup_tabela_existe(PDO $db, string $table): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table
        ");
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('backup_coluna_existe')) {
    function backup_coluna_existe(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND column_name = :column
        ");
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('backup_formatar_valor_sql')) {
    function backup_formatar_valor_sql($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof DateTimeInterface) {
            return "'" . $value->format('Y-m-d H:i:s') . "'";
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        $value = (string)$value;
        $value = str_replace(["\\", "\r", "\n", "'"], ["\\\\", "\\r", "\\n", "\\'"], $value);

        return "'" . $value . "'";
    }
}

if (!function_exists('backup_ensure_tables_exist')) {
    function backup_ensure_tables_exist(?PDO $db = null): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        try {
            if (!$db instanceof PDO) {
                $database = new Database();
                $db = $database->getConnection();
            }

            if (!$db instanceof PDO) {
                return;
            }

            $db->exec("
                CREATE TABLE IF NOT EXISTS backup_configuracoes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    restaurante_id INT NOT NULL,
                    automatico TINYINT(1) NOT NULL DEFAULT 0,
                    frequencia VARCHAR(20) NOT NULL DEFAULT 'DIARIO',
                    hora_execucao TIME NOT NULL DEFAULT '00:00:00',
                    retencao_dias INT NOT NULL DEFAULT 30,
                    notificar_email TINYINT(1) NOT NULL DEFAULT 0,
                    ultimo_backup_em DATETIME NULL,
                    proximo_backup_em DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_backup_config_restaurante (restaurante_id),
                    INDEX idx_backup_config_proximo (proximo_backup_em),
                    CONSTRAINT fk_backup_config_restaurante
                        FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS backup_historico (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    restaurante_id INT NOT NULL,
                    tipo VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
                    status VARCHAR(20) NOT NULL DEFAULT 'SUCESSO',
                    arquivo_nome VARCHAR(255) NOT NULL,
                    arquivo_caminho VARCHAR(500) NOT NULL,
                    tamanho_bytes INT NOT NULL DEFAULT 0,
                    hash_arquivo CHAR(32) NULL,
                    total_tabelas INT NOT NULL DEFAULT 0,
                    total_registros INT NOT NULL DEFAULT 0,
                    origem VARCHAR(50) NOT NULL DEFAULT 'web',
                    mensagem TEXT NULL,
                    executado_em DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_backup_hist_restaurante (restaurante_id),
                    INDEX idx_backup_hist_executado (executado_em),
                    CONSTRAINT fk_backup_hist_restaurante
                        FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
            error_log('[BACKUP][INIT] ' . $e->getMessage());
        }
    }
}

if (!function_exists('backup_normalizar_config')) {
    function backup_normalizar_config(array $config): array
    {
        $automatico = !empty($config['automatico']) ? 1 : 0;
        $frequencia = strtoupper(trim((string)($config['frequencia'] ?? 'DIARIO')));
        if (!in_array($frequencia, ['DIARIO', 'HORARIO'], true)) {
            $frequencia = 'DIARIO';
        }

        $horaExecucao = trim((string)($config['hora_execucao'] ?? '00:00:00'));
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaExecucao)) {
            $horaExecucao = '00:00:00';
        } elseif (strlen($horaExecucao) === 5) {
            $horaExecucao .= ':00';
        }

        $retencaoDias = max(1, (int)($config['retencao_dias'] ?? 30));
        $notificarEmail = !empty($config['notificar_email']) ? 1 : 0;

        return [
            'automatico' => $automatico,
            'frequencia' => $frequencia,
            'hora_execucao' => $horaExecucao,
            'retencao_dias' => $retencaoDias,
            'notificar_email' => $notificarEmail,
            'ultimo_backup_em' => $config['ultimo_backup_em'] ?? null,
            'proximo_backup_em' => $config['proximo_backup_em'] ?? null,
        ];
    }
}

if (!function_exists('backup_obter_configuracao')) {
    function backup_obter_configuracao(PDO $db, int $restauranteId): array
    {
        backup_ensure_tables_exist($db);

        $defaults = [
            'id' => null,
            'restaurante_id' => $restauranteId,
            'automatico' => 0,
            'frequencia' => 'DIARIO',
            'hora_execucao' => '00:00:00',
            'retencao_dias' => 30,
            'notificar_email' => 0,
            'ultimo_backup_em' => null,
            'proximo_backup_em' => null,
        ];

        $stmt = $db->prepare("SELECT * FROM backup_configuracoes WHERE restaurante_id = :restaurante_id LIMIT 1");
        $stmt->execute(['restaurante_id' => $restauranteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $defaults;
        }

        return array_merge($defaults, [
            'id' => (int)$row['id'],
            'restaurante_id' => (int)$row['restaurante_id'],
            'automatico' => (int)$row['automatico'],
            'frequencia' => strtoupper((string)($row['frequencia'] ?? 'DIARIO')),
            'hora_execucao' => (string)($row['hora_execucao'] ?? '00:00:00'),
            'retencao_dias' => (int)($row['retencao_dias'] ?? 30),
            'notificar_email' => (int)$row['notificar_email'],
            'ultimo_backup_em' => $row['ultimo_backup_em'] ?? null,
            'proximo_backup_em' => $row['proximo_backup_em'] ?? null,
        ]);
    }
}

if (!function_exists('backup_calcular_proxima_execucao')) {
    function backup_calcular_proxima_execucao(array $config, ?DateTimeImmutable $base = null): ?DateTimeImmutable
    {
        if (empty($config['automatico'])) {
            return null;
        }

        $base = $base ?? new DateTimeImmutable('now');
        $frequencia = strtoupper(trim((string)($config['frequencia'] ?? 'DIARIO')));

        if ($frequencia === 'HORARIO') {
            return $base->modify('+1 hour');
        }

        $hora = trim((string)($config['hora_execucao'] ?? '00:00:00'));
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
            $hora = '00:00:00';
        }

        [$h, $m, $s] = array_map('intval', explode(':', $hora));
        $agendada = $base->setTime($h, $m, $s);

        if ($agendada <= $base) {
            $agendada = $agendada->modify('+1 day');
        }

        return $agendada;
    }
}

if (!function_exists('backup_salvar_configuracao')) {
    function backup_salvar_configuracao(PDO $db, int $restauranteId, array $dados): array
    {
        backup_ensure_tables_exist($db);

        $atual = backup_obter_configuracao($db, $restauranteId);
        $config = backup_normalizar_config(array_merge($atual, $dados, ['restaurante_id' => $restauranteId]));

        if (empty($config['automatico'])) {
            $config['proximo_backup_em'] = null;
        } else {
            $proxima = backup_calcular_proxima_execucao($config, new DateTimeImmutable('now'));
            $config['proximo_backup_em'] = $proxima ? $proxima->format('Y-m-d H:i:s') : null;
        }

        $sql = "
            INSERT INTO backup_configuracoes
                (restaurante_id, automatico, frequencia, hora_execucao, retencao_dias, notificar_email, ultimo_backup_em, proximo_backup_em)
            VALUES
                (:restaurante_id, :automatico, :frequencia, :hora_execucao, :retencao_dias, :notificar_email, :ultimo_backup_em, :proximo_backup_em)
            ON DUPLICATE KEY UPDATE
                automatico = VALUES(automatico),
                frequencia = VALUES(frequencia),
                hora_execucao = VALUES(hora_execucao),
                retencao_dias = VALUES(retencao_dias),
                notificar_email = VALUES(notificar_email),
                ultimo_backup_em = VALUES(ultimo_backup_em),
                proximo_backup_em = VALUES(proximo_backup_em)
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'restaurante_id' => $restauranteId,
            'automatico' => $config['automatico'],
            'frequencia' => $config['frequencia'],
            'hora_execucao' => $config['hora_execucao'],
            'retencao_dias' => $config['retencao_dias'],
            'notificar_email' => $config['notificar_email'],
            'ultimo_backup_em' => $config['ultimo_backup_em'] ?: null,
            'proximo_backup_em' => $config['proximo_backup_em'] ?: null,
        ]);

        return backup_obter_configuracao($db, $restauranteId);
    }
}

if (!function_exists('backup_formatar_tamanho')) {
    function backup_formatar_tamanho(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $valor = (float)$bytes;
        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return number_format($valor, $i === 0 ? 0 : 2, ',', '.') . ' ' . $unidades[$i];
    }
}

if (!function_exists('backup_listar_historico')) {
    function backup_listar_historico(PDO $db, int $restauranteId, int $limite = 20): array
    {
        backup_ensure_tables_exist($db);

        $stmt = $db->prepare("
            SELECT *
            FROM backup_historico
            WHERE restaurante_id = :restaurante_id
            ORDER BY executado_em DESC, id DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':restaurante_id', $restauranteId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        $historico = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $historico[] = [
                'id' => (int)$row['id'],
                'restaurante_id' => (int)$row['restaurante_id'],
                'tipo' => strtoupper((string)$row['tipo']),
                'status' => strtoupper((string)$row['status']),
                'arquivo_nome' => (string)$row['arquivo_nome'],
                'arquivo_caminho' => (string)$row['arquivo_caminho'],
                'tamanho_bytes' => (int)$row['tamanho_bytes'],
                'tamanho_formatado' => backup_formatar_tamanho((int)$row['tamanho_bytes']),
                'hash_arquivo' => (string)($row['hash_arquivo'] ?? ''),
                'total_tabelas' => (int)$row['total_tabelas'],
                'total_registros' => (int)$row['total_registros'],
                'origem' => (string)$row['origem'],
                'mensagem' => (string)($row['mensagem'] ?? ''),
                'executado_em' => (string)$row['executado_em'],
            ];
        }

        return $historico;
    }
}

if (!function_exists('backup_limpar_antigos')) {
    function backup_limpar_antigos(PDO $db, int $restauranteId, int $retencaoDias): int
    {
        $retencaoDias = max(1, $retencaoDias);
        $limite = (new DateTimeImmutable('now'))->modify('-' . $retencaoDias . ' days')->format('Y-m-d H:i:s');

        $stmt = $db->prepare("
            SELECT id, arquivo_caminho
            FROM backup_historico
            WHERE restaurante_id = :restaurante_id
              AND executado_em < :limite
        ");
        $stmt->execute([
            'restaurante_id' => $restauranteId,
            'limite' => $limite,
        ]);

        $ids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (int)$row['id'];
            $arquivo = (string)($row['arquivo_caminho'] ?? '');
            if ($arquivo !== '' && is_file($arquivo)) {
                @unlink($arquivo);
            }
        }

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $delete = $db->prepare("DELETE FROM backup_historico WHERE id IN ($placeholders)");
        $delete->execute($ids);

        return count($ids);
    }
}

if (!function_exists('backup_gerar_dump_sql')) {
    function backup_gerar_dump_sql(PDO $db, int $restauranteId): array
    {
        $sql = "-- Backup do Banco de Dados - Restaurante SaaS\n";
        $sql .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tabelasExportadas = [];
        $totalRegistros = 0;

        foreach (backup_tabelas_principais() as $table) {
            if (!backup_tabela_existe($db, $table) || !backup_coluna_existe($db, $table, 'restaurante_id')) {
                continue;
            }

            $stmtCreate = $db->query("SHOW CREATE TABLE `$table`");
            $create = $stmtCreate ? $stmtCreate->fetch(PDO::FETCH_ASSOC) : null;
            if (!$create || empty($create['Create Table'])) {
                continue;
            }

            $tabelasExportadas[] = $table;
            $sql .= "\n-- Tabela: $table\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $create['Create Table'] . ";\n\n";

            $stmtData = $db->prepare("SELECT * FROM `$table` WHERE restaurante_id = :restaurante_id");
            $stmtData->execute(['restaurante_id' => $restauranteId]);

            while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_keys($row);
                $quotedColumns = array_map(static function ($column) {
                    return '`' . str_replace('`', '``', $column) . '`';
                }, $columns);
                $values = array_map('backup_formatar_valor_sql', array_values($row));
                $sql .= "INSERT INTO `$table` (" . implode(', ', $quotedColumns) . ") VALUES (" . implode(', ', $values) . ");\n";
                $totalRegistros++;
            }
        }

        foreach (backup_tabelas_filhas() as $table => $meta) {
            $parent = $meta['parent'] ?? '';
            $parentKey = $meta['parent_key'] ?? '';
            if ($table === '' || $parent === '' || $parentKey === '') {
                continue;
            }

            if (!backup_tabela_existe($db, $table) || !backup_tabela_existe($db, $parent)) {
                continue;
            }

            $stmtCreate = $db->query("SHOW CREATE TABLE `$table`");
            $create = $stmtCreate ? $stmtCreate->fetch(PDO::FETCH_ASSOC) : null;
            if (!$create || empty($create['Create Table'])) {
                continue;
            }

            $tabelasExportadas[] = $table;
            $sql .= "\n-- Tabela: $table\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $create['Create Table'] . ";\n\n";

            $stmtData = $db->prepare("
                SELECT child.*
                FROM `$table` child
                INNER JOIN `$parent` parent ON parent.id = child.`$parentKey`
                WHERE parent.restaurante_id = :restaurante_id
            ");
            $stmtData->execute(['restaurante_id' => $restauranteId]);

            while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_keys($row);
                $quotedColumns = array_map(static function ($column) {
                    return '`' . str_replace('`', '``', $column) . '`';
                }, $columns);
                $values = array_map('backup_formatar_valor_sql', array_values($row));
                $sql .= "INSERT INTO `$table` (" . implode(', ', $quotedColumns) . ") VALUES (" . implode(', ', $values) . ");\n";
                $totalRegistros++;
            }
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

        return [
            'sql' => $sql,
            'total_tabelas' => count(array_unique($tabelasExportadas)),
            'total_registros' => $totalRegistros,
            'tabelas' => array_values(array_unique($tabelasExportadas)),
        ];
    }
}

if (!function_exists('backup_executar_geracao')) {
    function backup_executar_geracao(PDO $db, int $restauranteId, array $config = [], string $tipo = 'MANUAL', string $origem = 'web'): array
    {
        backup_ensure_tables_exist($db);

        $configAtual = empty($config) ? backup_obter_configuracao($db, $restauranteId) : backup_normalizar_config($config);
        $dump = backup_gerar_dump_sql($db, $restauranteId);

        if (empty($dump['sql'])) {
            return [
                'success' => false,
                'message' => 'Nao foi possivel gerar o backup.',
            ];
        }

        $storageDir = backup_storage_dir($restauranteId);
        $timestamp = date('Ymd_His');
        $arquivoNome = sprintf('backup_restaurante_%d_%s_%s.sql', $restauranteId, strtoupper($tipo), $timestamp);
        $arquivoPath = $storageDir . DIRECTORY_SEPARATOR . $arquivoNome;

        if (file_put_contents($arquivoPath, $dump['sql']) === false) {
            return [
                'success' => false,
                'message' => 'Falha ao gravar o arquivo de backup.',
            ];
        }

        $tamanhoBytes = (int)@filesize($arquivoPath);
        $hashArquivo = is_file($arquivoPath) ? (string)@md5_file($arquivoPath) : '';
        $agora = date('Y-m-d H:i:s');
        $mensagem = sprintf('%d tabelas, %d registros exportados.', (int)$dump['total_tabelas'], (int)$dump['total_registros']);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO backup_historico
                    (restaurante_id, tipo, status, arquivo_nome, arquivo_caminho, tamanho_bytes, hash_arquivo, total_tabelas, total_registros, origem, mensagem, executado_em)
                VALUES
                    (:restaurante_id, :tipo, :status, :arquivo_nome, :arquivo_caminho, :tamanho_bytes, :hash_arquivo, :total_tabelas, :total_registros, :origem, :mensagem, :executado_em)
            ");
            $stmt->execute([
                'restaurante_id' => $restauranteId,
                'tipo' => strtoupper($tipo) ?: 'MANUAL',
                'status' => 'SUCESSO',
                'arquivo_nome' => $arquivoNome,
                'arquivo_caminho' => $arquivoPath,
                'tamanho_bytes' => $tamanhoBytes,
                'hash_arquivo' => $hashArquivo ?: null,
                'total_tabelas' => (int)$dump['total_tabelas'],
                'total_registros' => (int)$dump['total_registros'],
                'origem' => $origem ?: 'web',
                'mensagem' => $mensagem,
                'executado_em' => $agora,
            ]);
            $historicoId = (int)$db->lastInsertId();

            $configAtual['automatico'] = !empty($configAtual['automatico']) ? 1 : 0;
            $configAtual['ultimo_backup_em'] = $agora;
            if ($configAtual['automatico']) {
                $proxima = backup_calcular_proxima_execucao($configAtual, new DateTimeImmutable($agora));
                $configAtual['proximo_backup_em'] = $proxima ? $proxima->format('Y-m-d H:i:s') : null;
            }
            backup_salvar_configuracao($db, $restauranteId, $configAtual);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (is_file($arquivoPath)) {
                @unlink($arquivoPath);
            }
            error_log('[BACKUP][EXEC] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao registrar o backup: ' . $e->getMessage(),
            ];
        }

        $retencaoDias = (int)($configAtual['retencao_dias'] ?? 30);
        if ($retencaoDias > 0) {
            backup_limpar_antigos($db, $restauranteId, $retencaoDias);
        }

        $totalTabelas = (int)$dump['total_tabelas'];
        $totalRegistros = (int)$dump['total_registros'];
        unset($dump);

        return [
            'success' => true,
            'message' => $mensagem,
            'arquivo_nome' => $arquivoNome,
            'arquivo_path' => $arquivoPath,
            'arquivo_url_name' => $arquivoNome,
            'historico_id' => $historicoId,
            'tamanho_bytes' => $tamanhoBytes,
            'total_tabelas' => $totalTabelas,
            'total_registros' => $totalRegistros,
        ];
    }
}

backup_ensure_tables_exist();
