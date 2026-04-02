<?php

/*
   API: Restaurar Backup
   Recebe um arquivo .sql via POST, extrai apenas os INSERTs das tabelas do
   restaurante logado e reinsere os dados (sem DDL, sem risco de quebrar FKs).
 */

// Buffer de saída: evita que notices/warnings corrompam o JSON
ob_start();

header('Content-Type: application/json');

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/plano_check.php';
include_once __DIR__ . '/../../config/backup_helper.php';
include_once __DIR__ . '/../../config/restaurante_context.php';

// Apenas ADMIN
$perfil = $_SESSION['perfil'] ?? '';
if ($perfil !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$restaurante_id = session_restaurante_contexto_id();
$restaurante_plan_id = session_restaurante_capability_id();
if ($restaurante_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Restaurante não identificado.']);
    exit;
}

// Verificar se o plano permite backup (mesmos critérios que backup.php)
$tem_backup = plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_automatico')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_diario')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_manual')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'download_banco');

if (!$tem_backup) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Seu plano não inclui recuperação de backup.']);
    exit;
}

// ── Upload ────────────────────────────────────────────────────────────────────
if (empty($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErros = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o tamanho máximo permitido pelo servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o tamanho máximo do formulário.',
        UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo temporário.',
    ];
    $codigo = $_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    ob_clean();
    echo json_encode(['success' => false, 'message' => $uploadErros[$codigo] ?? 'Erro no upload.']);
    exit;
}

$extension = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
if ($extension !== 'sql') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Apenas arquivos .sql são aceitos.']);
    exit;
}

if ($_FILES['backup_file']['size'] > 50 * 1024 * 1024) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo: 50 MB.']);
    exit;
}

$sql_content = file_get_contents($_FILES['backup_file']['tmp_name']);
if ($sql_content === false || trim($sql_content) === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Arquivo de backup vazio ou ilegível.']);
    exit;
}

// ── Tabelas restauráveis ─────────────────────────────────────────────────────
// Misturamos tabelas principais e filhas dos pedidos/vendas para preservar o
// histórico completo no backup.
$tabelas_principais = backup_tabelas_principais();
$tabelas_filhas = backup_tabelas_filhas();
$ordem_delete = array_merge(array_reverse(array_keys($tabelas_filhas)), array_reverse($tabelas_principais));
$ordem_insert = array_merge($tabelas_principais, array_keys($tabelas_filhas));
$tabelas_validas = array_flip($ordem_insert);
$tabelas_principais_lookup = array_flip($tabelas_principais);

// ── Parsear linhas do backup: extrair apenas INSERTs válidos ─────────────────
/**
 * Divide a string de valores de um INSERT em tokens individuais,
 * respeitando strings entre aspas simples com escape por barra invertida.
 */
function parseInsertValues(string $str): array
{
    $tokens  = [];
    $current = '';
    $inQuote = false;
    $len     = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $c = $str[$i];
        if ($inQuote) {
            if ($c === '\\' && $i + 1 < $len) {
                $current .= $c . $str[++$i];
            } elseif ($c === "'") {
                $inQuote  = false;
                $current .= $c;
            } else {
                $current .= $c;
            }
        } else {
            if ($c === "'") {
                $inQuote  = true;
                $current .= $c;
            } elseif ($c === ',') {
                $tokens[] = trim($current);
                $current  = '';
            } else {
                $current .= $c;
            }
        }
    }
    if ($current !== '') {
        $tokens[] = trim($current);
    }
    return $tokens;
}

$inserts_por_tabela = [];

foreach (explode("\n", $sql_content) as $linha) {
    $linha = trim($linha);

    // Ignorar comentários, linhas vazias e tudo que não seja INSERT
    if ($linha === '' || $linha[0] === '-' || $linha[0] === '/' || $linha[0] === '#') {
        continue;
    }
    if (stripos($linha, 'INSERT INTO') !== 0) {
        continue; // pula DROP TABLE, CREATE TABLE, SET, etc.
    }

    // Extrair nome da tabela
    if (!preg_match('/^INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s+VALUES\s*\((.+)\);?\s*$/i', $linha, $m)) {
        continue;
    }

    $tabela = strtolower($m[1]);
    if (!isset($tabelas_validas[$tabela])) {
        continue; // ignora 'restaurantes' e outras tabelas globais
    }

    if (isset($tabelas_principais_lookup[$tabela])) {
        // Encontrar posição da coluna restaurante_id
        $colunas = array_map('trim', explode(',', $m[2]));
        $rid_pos = array_search('restaurante_id', $colunas);
        if ($rid_pos === false) {
            continue; // tabela principal sem restaurante_id – pular por segurança
        }

        // Extrair o valor de restaurante_id da linha
        $valores = parseInsertValues($m[3]);
        if (!isset($valores[$rid_pos])) {
            continue;
        }

        $rid_val = intval(trim($valores[$rid_pos], "' \""));
        if ($rid_val !== $restaurante_id) {
            continue; // dados de outro restaurante – ignorar
        }
    }

    $inserts_por_tabela[$tabela][] = $linha;
}

if (empty($inserts_por_tabela)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Nenhum dado válido encontrado no backup para este restaurante. Verifique se o arquivo é correto.']);
    exit;
}

// ── Executar restauração ─────────────────────────────────────────────────────
try {
    $database = new Database();
    $db       = $database->getConnection();

    // Desabilitar checks de FK para lidar com dependências sem erros
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    $db->beginTransaction();

    // 1. Apagar dados actuais do restaurante (ordem: dependentes primeiro)
    foreach ($ordem_delete as $tabela) {
        if (isset($inserts_por_tabela[$tabela])) {
            if (isset($tabelas_principais_lookup[$tabela])) {
                $db->prepare("DELETE FROM `$tabela` WHERE restaurante_id = ?")->execute([$restaurante_id]);
            } elseif ($tabela === 'itens_pedido') {
                $db->prepare("
                    DELETE ip
                    FROM itens_pedido ip
                    INNER JOIN pedidos p ON p.id = ip.pedido_id
                    WHERE p.restaurante_id = ?
                ")->execute([$restaurante_id]);
            } elseif ($tabela === 'itens_venda') {
                $db->prepare("
                    DELETE iv
                    FROM itens_venda iv
                    INNER JOIN vendas v ON v.id = iv.venda_id
                    WHERE v.restaurante_id = ?
                ")->execute([$restaurante_id]);
            }
        }
    }

    // 2. Inserir dados do backup (ordem: pais primeiro)
    $total = 0;
    foreach ($ordem_insert as $tabela) {
        if (!isset($inserts_por_tabela[$tabela])) {
            continue;
        }
        foreach ($inserts_por_tabela[$tabela] as $insert_sql) {
            $db->exec($insert_sql);
            $total++;
        }
    }

    $db->commit();
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => "Backup restaurado com sucesso! ($total registos recuperados)"
    ]);
} catch (Throwable $e) {
    if (isset($db)) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        try {
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $_) {
        }
    }
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao restaurar: ' . $e->getMessage()
    ]);
}
