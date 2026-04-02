
<?php

/*

   Verificação de Planos - Funções Auxiliares
   Usa o banco de dados para verificar limites e funcionalidades
 
*/

require_once __DIR__ . '/database.php';

if (!function_exists('plano_remover_view_compatibilidade')) {
    function plano_remover_view_compatibilidade(PDO $db): void
    {
        static $executado = false;

        if ($executado) {
            return;
        }
        $executado = true;

        try {
            $currentUser = (string)$db->query('SELECT CURRENT_USER()')->fetchColumn();
            $ehLocal = stripos($currentUser, 'root@localhost') !== false
                || stripos($currentUser, 'root@127.0.0.1') !== false
                || stripos($currentUser, 'root@%') !== false;

            if (!$ehLocal) {
                return;
            }

            $db->exec('DROP VIEW IF EXISTS `v_plano_usage_resumo`');
        } catch (Throwable $e) {
            error_log('Erro ao remover view de compatibilidade: ' . $e->getMessage());
        }
    }
}

/**
 * Inicializar tabelas de planos se não existirem
 * Executado silenciosamente na primeira requisição
 */
function plano_ensure_tables_exist()
{
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    try {
        $database = new Database();
        $db = $database->getConnection();

        // 1. Criar tabela planos_disponiveis se não existir
        $sql1 = "CREATE TABLE IF NOT EXISTS planos_disponiveis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(50) NOT NULL UNIQUE,
            nome_display VARCHAR(100) NOT NULL,
            descricao TEXT,
            cor VARCHAR(20) DEFAULT '#6c757d',
            icone VARCHAR(50) DEFAULT 'fa-star',
            preco_mensal DECIMAL(10,2) DEFAULT 0,
            preco_trimestral DECIMAL(10,2) DEFAULT 0,
            preco_anual DECIMAL(10,2) DEFAULT 0,
            limite_produtos INT DEFAULT -1,
            limite_usuarios INT DEFAULT -1,
            limite_mesas INT DEFAULT -1,
            limite_filiais INT DEFAULT 0,
            ordem INT DEFAULT 0,
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($sql1);

        // Inserir planos padrão se não existirem
        $db->exec("INSERT INTO planos_disponiveis (nome, nome_display, descricao, cor, icone, preco_mensal, preco_trimestral, preco_anual, limite_produtos, limite_usuarios, limite_mesas, limite_filiais, ordem) VALUES
        ('BASICO', 'Básico', 'Ideal para lanchonetes.', '#6c757d', 'fa-user', 1500, 4000, 15000, 100, 4, 20, 0, 1),
        ('PROFISSIONAL', 'Profissional', 'Ideal para restaurantes médios.', '#17a2b8', 'fa-star', 3000, 8000, 30000, 500, 10, 50, 0, 2),
        ('EMPRESARIAL', 'Empresarial', 'Para grandes restaurantes.', '#6f42c1', 'fa-crown', 6000, 16000, 60000, -1, -1, -1, 5, 3)
        ON DUPLICATE KEY UPDATE
            nome_display = VALUES(nome_display),
            descricao = VALUES(descricao),
            cor = VALUES(cor),
            icone = VALUES(icone),
            preco_mensal = VALUES(preco_mensal),
            preco_trimestral = VALUES(preco_trimestral),
            preco_anual = VALUES(preco_anual),
            limite_produtos = VALUES(limite_produtos),
            limite_usuarios = VALUES(limite_usuarios),
            limite_mesas = VALUES(limite_mesas),
            limite_filiais = VALUES(limite_filiais),
            ordem = VALUES(ordem)");

        // 2. Criar tabela restaurante_planos se não existir
        $sql2 = "CREATE TABLE IF NOT EXISTS restaurante_planos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurante_id INT NOT NULL,
            plano_id INT NOT NULL,
            data_inicio DATE NOT NULL,
            data_fim DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'ATIVO',
            tipo_plano VARCHAR(20) DEFAULT 'PAGO',
            valor_pago DECIMAL(10,2) DEFAULT 0,
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_restaurante_plano (restaurante_id, status),
            FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
            FOREIGN KEY (plano_id) REFERENCES planos_disponiveis(id) ON DELETE RESTRICT,
            INDEX idx_status (status),
            INDEX idx_data_fim (data_fim)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($sql2);

        // 3. Criar tabela plano_funcionalidades se não existir
        $sql3 = "CREATE TABLE IF NOT EXISTS plano_funcionalidades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plano_id INT NOT NULL,
            funcionalidade VARCHAR(100) NOT NULL,
            permitido TINYINT(1) DEFAULT 1,
            descricao TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_plano_func (plano_id, funcionalidade),
            FOREIGN KEY (plano_id) REFERENCES planos_disponiveis(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($sql3);

        // 4. Criar tabela plano_logs se não existir
        $sql4 = "CREATE TABLE IF NOT EXISTS plano_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            restaurante_id INT NOT NULL,
            funcionalidade VARCHAR(100),
            recurso VARCHAR(50),
            acao VARCHAR(50),
            quantidade INT DEFAULT 1,
            limite_anterior INT,
            limite_restante INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
            INDEX idx_restaurante (restaurante_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($sql4);

        // 5. Remover a view antiga para manter o banco compatível com hospedagens
        // que não permitem CREATE VIEW, como a InfinityFree.
        plano_remover_view_compatibilidade($db);
    } catch (Exception $e) {
        // Silencioso - não interrompe a aplicação se falhar
        error_log("Erro ao criar tabelas de planos: " . $e->getMessage());
    }
}

// Chamar automaticamente na primeira vez
plano_ensure_tables_exist();

function plano_normalizar_nome($plano)
{
    $plano = strtoupper(trim((string) $plano));
    if ($plano === 'EMPRESARIAL') {
        return 'EMPRESARIAL';
    }

    return $plano !== '' ? $plano : 'BASICO';
}

function plano_get_configuracao($plano)
{
    $planos = require __DIR__ . '/planos.php';
    $plano = plano_normalizar_nome($plano);

    return $planos[$plano] ?? $planos['BASICO'];
}

function plano_get_resumo_recursos($plano)
{
    $planos = require __DIR__ . '/planos.php';

    if (function_exists('plano_get_recursos_catalogo')) {
        return plano_get_recursos_catalogo($plano);
    }

    $config = $planos[plano_normalizar_nome($plano)] ?? $planos['BASICO'];
    $limites = $config['limites'] ?? [];
    $funcionalidades = $config['funcionalidades'] ?? [];
    $recursos = [];
    $recursos[] = (($limites['produtos'] ?? 0) == -1) ? 'Produtos ilimitados' : 'Até ' . ($limites['produtos'] ?? 0) . ' produtos';
    $recursos[] = (($limites['usuarios'] ?? 0) == -1) ? 'Usuários ilimitados' : 'Até ' . ($limites['usuarios'] ?? 0) . ' usuários';
    $recursos[] = (($limites['mesas'] ?? 0) == -1) ? 'Mesas ilimitadas' : 'Até ' . ($limites['mesas'] ?? 0) . ' mesas';

    if (!empty($funcionalidades['pedidos_online'])) {
        $recursos[] = 'Pedidos online (QR Code)';
    }

    if (!empty($funcionalidades['relatorios_avancados'])) {
        $recursos[] = 'Relatórios avançados';
    } elseif (!empty($funcionalidades['relatorio_diario'])) {
        $recursos[] = 'Relatório diário';
    }

    if (!empty($funcionalidades['multi_filial'])) {
        $recursos[] = 'Multi-filial';
    }

    if (!empty($funcionalidades['suporte_24h'])) {
        $recursos[] = 'Suporte 24/7';
    } elseif (!empty($funcionalidades['suporte_whatsapp'])) {
        $recursos[] = 'Suporte via WhatsApp';
    } elseif (!empty($funcionalidades['suporte_email'])) {
        $recursos[] = 'Suporte por email';
    }

    return array_values(array_unique($recursos));
}

function plano_sincronizar_restaurante_plano($restaurante_id, $plano, $dataFim = null, $tipoPlano = 'PAGO', $observacoes = null, ?PDO $db = null)
{
    if (!$db instanceof PDO) {
        $database = new Database();
        $db = $database->getConnection();
    }

    if (!$db instanceof PDO) {
        error_log('[PLANO_SYNC][ERROR] conexao PDO indisponivel');
        return false;
    }

    $traceId = 'sync_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    $log = static function ($nivel, $mensagem, array $ctx = []) use ($traceId) {
        $ctx['trace_id'] = $traceId;
        error_log('[PLANO_SYNC][' . $nivel . '] ' . $mensagem . ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE));
    };

    $restaurante_id = (int)$restaurante_id;
    $plano = plano_normalizar_nome($plano);
    $tipoPlano = strtoupper(trim((string)$tipoPlano)) ?: 'PAGO';

    if ($restaurante_id <= 0) {
        $log('ERROR', 'restaurante_id invalido', ['restaurante_id' => $restaurante_id]);
        return false;
    }

    $hoje = new DateTimeImmutable('today');
    try {
        $dataFimObj = $dataFim ? new DateTimeImmutable((string)$dataFim) : $hoje->modify('+30 days');
    } catch (Throwable $e) {
        $log('WARN', 'data_fim invalida, aplicando fallback +30 dias', ['data_fim_recebida' => $dataFim]);
        $dataFimObj = $hoje->modify('+30 days');
    }

    if ($dataFimObj < $hoje) {
        $log('WARN', 'data_fim menor que hoje, ajustando para +30 dias', ['data_fim_recebida' => $dataFimObj->format('Y-m-d')]);
        $dataFimObj = $hoje->modify('+30 days');
    }

    $dataInicio = $hoje->format('Y-m-d');
    $dataFimFmt = $dataFimObj->format('Y-m-d');

    $planoId = 0;
    try {
        $stmtPlano = $db->prepare("SELECT id FROM planos_disponiveis WHERE nome = :nome LIMIT 1");
        $stmtPlano->bindValue(':nome', $plano);
        $stmtPlano->execute();
        $planoId = (int)($stmtPlano->fetchColumn() ?: 0);
        if ($planoId <= 0) {
            $log('WARN', 'plano nao encontrado em planos_disponiveis', ['plano' => $plano]);
        }
    } catch (Throwable $e) {
        $log('WARN', 'falha ao buscar plano em planos_disponiveis', ['erro' => $e->getMessage(), 'plano' => $plano]);
    }

    $startedTransaction = false;

    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmtExiste = $db->prepare("SELECT id FROM restaurantes WHERE id = :restaurante_id LIMIT 1");
        $stmtExiste->bindValue(':restaurante_id', $restaurante_id, PDO::PARAM_INT);
        $stmtExiste->execute();
        if (!$stmtExiste->fetchColumn()) {
            throw new Exception('Restaurante nao encontrado para sincronizacao');
        }

        $cols = [];
        $stmtCols = $db->query("SHOW COLUMNS FROM restaurantes");
        while ($c = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $c['Field'];
        }

        $firstExistingColumn = static function (array $columns, array $candidates) {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    return $candidate;
                }
            }
            return null;
        };

        $colPlanoNome = $firstExistingColumn($cols, ['plano']);
        $colPlanoId = $firstExistingColumn($cols, ['plano_id']);
        $colDataInicio = $firstExistingColumn($cols, ['data_inicio', 'data_inicio_plano']);
        $colDataFim = $firstExistingColumn($cols, ['data_fim', 'data_fim_plano']);
        $colStatus = $firstExistingColumn($cols, ['status_plano', 'status']);

        $set = [];
        $params = [':restaurante_id' => $restaurante_id];

        if ($colPlanoNome !== null) {
            $set[] = $colPlanoNome . ' = :plano_nome';
            $params[':plano_nome'] = $plano;
        }

        if ($colPlanoId !== null) {
            if ($planoId <= 0) {
                throw new Exception('coluna plano_id existe, mas plano_id nao foi encontrado para o plano informado');
            }
            $set[] = $colPlanoId . ' = :plano_id';
            $params[':plano_id'] = $planoId;
        }

        if ($colDataInicio !== null) {
            $set[] = $colDataInicio . ' = :data_inicio';
            $params[':data_inicio'] = $dataInicio;
        }

        if ($colDataFim !== null) {
            $set[] = $colDataFim . ' = :data_fim';
            $params[':data_fim'] = $dataFimFmt;
        }

        if ($colStatus !== null) {
            $set[] = $colStatus . " = 'ATIVO'";
        }

        if (!empty($set)) {
            $sqlUpdate = "UPDATE restaurantes SET " . implode(', ', $set) . " WHERE id = :restaurante_id";
            $stmtRestaurante = $db->prepare($sqlUpdate);
            $stmtRestaurante->execute($params);
            $log('INFO', 'tabela restaurantes sincronizada', [
                'restaurante_id' => $restaurante_id,
                'plano' => $plano,
                'plano_id' => $planoId,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFimFmt,
                'colunas_atualizadas' => $set,
            ]);
        } else {
            $log('WARN', 'nenhuma coluna legada encontrada em restaurantes para sincronizacao', ['restaurante_id' => $restaurante_id]);
        }

        $sincronizouTabelaNova = false;
        try {
            $stmtFechar = $db->prepare("UPDATE restaurante_planos SET status = 'EXPIRADO', updated_at = CURRENT_TIMESTAMP WHERE restaurante_id = :restaurante_id AND status = 'ATIVO'");
            $stmtFechar->bindValue(':restaurante_id', $restaurante_id, PDO::PARAM_INT);
            $stmtFechar->execute();

            if ($planoId > 0) {
                $stmtInsert = $db->prepare("INSERT INTO restaurante_planos (restaurante_id, plano_id, data_inicio, data_fim, status, tipo_plano, valor_pago, observacoes)
                    VALUES (:restaurante_id, :plano_id, :data_inicio, :data_fim, 'ATIVO', :tipo_plano, 0, :observacoes)");
                $stmtInsert->bindValue(':restaurante_id', $restaurante_id, PDO::PARAM_INT);
                $stmtInsert->bindValue(':plano_id', $planoId, PDO::PARAM_INT);
                $stmtInsert->bindValue(':data_inicio', $dataInicio);
                $stmtInsert->bindValue(':data_fim', $dataFimFmt);
                $stmtInsert->bindValue(':tipo_plano', $tipoPlano);
                $stmtInsert->bindValue(':observacoes', $observacoes ?: ('Sincronizado para plano ' . $plano));
                $stmtInsert->execute();
                $sincronizouTabelaNova = true;
            }
        } catch (Throwable $e) {
            $log('WARN', 'falha ao sincronizar restaurante_planos', ['restaurante_id' => $restaurante_id, 'erro' => $e->getMessage()]);
        }

        if (empty($set) && !$sincronizouTabelaNova) {
            throw new Exception('sincronizacao nao aplicada: sem colunas legadas e sem insercao em restaurante_planos');
        }

        if ($startedTransaction) {
            $db->commit();
        }

        $log('INFO', 'sincronizacao concluida com sucesso', [
            'restaurante_id' => $restaurante_id,
            'plano' => $plano,
            'plano_id' => $planoId,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFimFmt,
            'tabela_nova' => $sincronizouTabelaNova,
        ]);

        return true;
    } catch (Throwable $e) {
        if ($startedTransaction && $db->inTransaction()) {
            $db->rollBack();
        }

        $log('ERROR', 'falha na sincronizacao de plano', [
            'restaurante_id' => $restaurante_id,
            'plano' => $plano,
            'data_fim' => $dataFimFmt,
            'erro' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Obter dados do plano atual do restaurante
 */
function plano_get_dados($restaurante_id)
{
    $database = new Database();
    $db = $database->getConnection();

    // Primeiro tenta buscar da nova tabela
    try {
        $query = "SELECT rp.*, pd.nome as plano_nome, pd.nome_display, pd.cor, pd.icone,
                         pd.limite_produtos, pd.limite_usuarios, pd.limite_mesas, pd.limite_filiais,
                         pd.preco_mensal, pd.preco_trimestral, pd.preco_anual
                  FROM restaurante_planos rp
                  JOIN planos_disponiveis pd ON rp.plano_id = pd.id
                  WHERE rp.restaurante_id = :restaurante_id AND rp.status = 'ATIVO'
                  AND rp.data_fim >= CURDATE()
                  ORDER BY rp.id DESC LIMIT 1";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurante_id', $restaurante_id);
        $stmt->execute();

        $plano = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se encontrar na nova estrutura, retorna
        if ($plano) {
            return $plano;
        }
    } catch (PDOException $e) {
        // Se tabela não existir, usa fallback
    }

    // Fallback: buscar da tabela restaurantes (antigo)
    try {
        $query_old = "SELECT plano, data_inicio, data_fim FROM restaurantes WHERE id = :id";
        $stmt_old = $db->prepare($query_old);
        $stmt_old->bindParam(':id', $restaurante_id);
        $stmt_old->execute();
        $restaurante = $stmt_old->fetch(PDO::FETCH_ASSOC);

        if ($restaurante && $restaurante['plano']) {
            $planos_antigos = require __DIR__ . '/planos.php';
            $plano_nome = plano_normalizar_nome($restaurante['plano']);

            if (isset($planos_antigos[$plano_nome])) {
                $p = $planos_antigos[$plano_nome];
                return [
                    'plano_nome' => $plano_nome,
                    'nome_display' => $p['nome'] ?? $plano_nome,
                    'cor' => $p['cor'] ?? '#6c757d',
                    'icone' => $p['icone'] ?? 'fa-star',
                    'limite_produtos' => $p['limites']['produtos'] ?? 100,
                    'limite_usuarios' => $p['limites']['usuarios'] ?? 4,
                    'limite_mesas' => $p['limites']['mesas'] ?? 20,
                    'limite_filiais' => 0,
                    'data_inicio' => $restaurante['data_inicio'],
                    'data_fim' => $restaurante['data_fim'],
                    'status' => 'ATIVO',
                    'tipo_fallback' => true
                ];
            }
        }
    } catch (PDOException $e) {
        // Fallback para dados em memória
    }

    // Plano padrão se não encontrar nada
    return [
        'plano_nome' => 'BASICO',
        'nome_display' => 'Básico',
        'cor' => '#6c757d',
        'icone' => 'fa-user',
        'limite_produtos' => 100,
        'limite_usuarios' => 4,
        'limite_mesas' => 20,
        'limite_filiais' => 0,
        'status' => 'ATIVO'
    ];
}

/**
 * Verificar se uma funcionalidade está disponível no plano
 */
function plano_tem_funcionalidade_db($restaurante_id, $funcionalidade)
{
    $database = new Database();
    $db = $database->getConnection();

    // Tentar buscar da nova estrutura
    try {
        $query = "SELECT pf.permitido 
                  FROM plano_funcionalidades pf
                  JOIN restaurante_planos rp ON pf.plano_id = rp.plano_id
                  WHERE rp.restaurante_id = :restaurante_id 
                  AND rp.status = 'ATIVO'
                  AND rp.data_fim >= CURDATE()
                  AND pf.funcionalidade = :funcionalidade
                  LIMIT 1";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurante_id', $restaurante_id);
        $stmt->bindParam(':funcionalidade', $funcionalidade);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado) {
            return (bool) $resultado['permitido'];
        }
    } catch (PDOException $e) {
        // Se tabela não existir, usa fallback
    }

    // Fallback: usar configuração antiga
    $planos = require __DIR__ . '/planos.php';
    $dados = plano_get_dados($restaurante_id);
    $plano_nome = plano_normalizar_nome($dados['plano_nome'] ?? 'BASICO');

    if (isset($planos[$plano_nome]['funcionalidades'][$funcionalidade])) {
        return $planos[$plano_nome]['funcionalidades'][$funcionalidade];
    }

    return false;
}

/**
 * Verificar limite de um recurso (produtos, usuarios, mesas)
 */
function plano_verificar_limite_db($restaurante_id, $recurso, $qtd_atual = 0)
{
    $dados = plano_get_dados($restaurante_id);

    $mapeamento = [
        'produtos' => 'limite_produtos',
        'usuarios' => 'limite_usuarios',
        'mesas' => 'limite_mesas',
        'filiais' => 'limite_filiais'
    ];

    $campo_limite = $mapeamento[$recurso] ?? 'limite_produtos';
    $limite = $dados[$campo_limite] ?? -1;

    if ($limite == -1) {
        return [
            'permitido' => true,
            'limite' => -1,
            'atual' => $qtd_atual,
            'restante' => -1,
            'plano' => $dados['nome_display'] ?? 'Básico'
        ];
    }

    $restante = $limite - $qtd_atual;

    return [
        'permitido' => $restante > 0,
        'limite' => $limite,
        'atual' => $qtd_atual,
        'restante' => max(0, $restante),
        'plano' => $dados['nome_display'] ?? 'Básico'
    ];
}

/**
 * Registrar log de uso de plano
 */
function plano_log($restaurante_id, $funcionalidade, $recurso, $acao, $quantidade = 1, $limite_anterior = null, $limite_restante = null)
{
    // Silencioso - não lança erros se tabela não existir
    return true;
}

/**
 * Verificar e bloquear se exceder limite
 */
function plano_verificar_e_bloquear($restaurante_id, $recurso, $qtd_atual, $retornar_json = true)
{
    $verificacao = plano_verificar_limite_db($restaurante_id, $recurso, $qtd_atual);

    if (!$verificacao['permitido']) {
        if ($retornar_json) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Limite do plano atingido! Seu plano {$verificacao['plano']} permite apenas {$verificacao['limite']} {$recurso}. Atualize para continuar.",
                'code' => 'LIMITE_EXCEDIDO',
                'plano' => $verificacao['plano'],
                'limite' => $verificacao['limite'],
                'atual' => $verificacao['atual']
            ]);
            exit;
        }
        return $verificacao;
    }

    return $verificacao;
}

/**
 * Verificar funcionalidade
 */
function plano_verificar_funcionalidade($restaurante_id, $funcionalidade, $retornar_json = true)
{
    $tem_acesso = plano_tem_funcionalidade_db($restaurante_id, $funcionalidade);

    plano_log(
        $restaurante_id,
        $funcionalidade,
        'funcionalidade',
        $tem_acesso ? 'SUCESSO' : 'BLOQUEADO'
    );

    if (!$tem_acesso) {
        if ($retornar_json) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Esta funcionalidade não está disponível no seu plano atual.",
                'code' => 'FUNCIONALIDADE_BLOQUEADA',
                'funcionalidade' => $funcionalidade
            ]);
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Obter lista de funcionalidades disponíveis no plano
 */
function plano_get_funcionalidades($restaurante_id)
{
    try {
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT pf.funcionalidade, pf.permitido, pf.descricao
                  FROM plano_funcionalidades pf
                  JOIN restaurante_planos rp ON pf.plano_id = rp.plano_id
                  WHERE rp.restaurante_id = :restaurante_id 
                  AND rp.status = 'ATIVO'
                  AND rp.data_fim >= CURDATE()";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurante_id', $restaurante_id);
        $stmt->execute();

        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($resultados)) {
            return $resultados;
        }
    } catch (PDOException $e) {
        // Fallback para dados em memória
    }

    $planos = require __DIR__ . '/planos.php';
    $dados = plano_get_dados($restaurante_id);
    $plano_nome = strtoupper($dados['plano_nome'] ?? 'BASICO');

    if (isset($planos[$plano_nome]['funcionalidades'])) {
        $funcionalidades = [];
        foreach ($planos[$plano_nome]['funcionalidades'] as $func => $permitido) {
            $funcionalidades[] = [
                'funcionalidade' => $func,
                'permitido' => $permitido,
                'descricao' => ucfirst(str_replace('_', ' ', $func))
            ];
        }
        return $funcionalidades;
    }

    return [];
}

/**
 * Função para verificar limite de categorias
 */
function plano_verificar_limite_categorias($restaurante_id, $qtd_atual = 0)
{
    $dados = plano_get_dados($restaurante_id);
    $plano = strtoupper($dados['plano_nome'] ?? 'BASICO');
    $limites = plano_get_limites($plano);

    $limite = $limites['categorias'] ?? 10;

    if ($limite == -1) {
        return [
            'permitido' => true,
            'limite' => -1,
            'atual' => $qtd_atual,
            'restante' => -1,
            'plano' => $dados['nome_display'] ?? 'Básico'
        ];
    }

    $restante = $limite - $qtd_atual;

    return [
        'permitido' => $restante > 0,
        'limite' => $limite,
        'atual' => $qtd_atual,
        'restante' => max(0, $restante),
        'plano' => $dados['nome_display'] ?? 'Básico'
    ];
}
