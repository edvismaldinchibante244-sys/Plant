<?php
// ✅ Executa migração de schema para sistema de planos - segura e idempotente
echo "🔄 Executando migração de schema para correção do erro de aprovação de planos...\n\n";

require_once __DIR__ . '/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Falha na conexão com banco');
    }

    // 1. Verificar colunas atuais
    echo "📋 Verificando colunas da tabela 'restaurantes':\n";
    $stmt = $db->query("SHOW COLUMNS FROM restaurantes LIKE 'plano%' OR SHOW COLUMNS FROM restaurantes LIKE 'data_fim%' OR SHOW COLUMNS FROM restaurantes LIKE 'status%'");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "- Colunas encontradas: " . implode(', ', $cols) . "\n\n";

    // 2. Executar migração SQL
    $sql = file_get_contents(__DIR__ . '/schema_planos_restaurantes.sql');
    if ($sql === false) {
        throw new Exception("Arquivo schema_planos_restaurantes.sql não encontrado");
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $i => $statement) {
        if (!empty($statement)) {
            echo "📝 Executando statement " . ($i + 1) . "... ";
            $db->exec($statement);
            echo "✓ OK\n";
        }
    }

    // 3. Verificação final
    echo "\n✅ Verificação final:\n";
    $stmtFinal = $db->query("DESCRIBE restaurantes");
    $finalCols = [];
    while ($row = $stmtFinal->fetch(PDO::FETCH_ASSOC)) {
        if (strpos($row['Field'], 'plano') !== false || strpos($row['Field'], 'data_fim') !== false || strpos($row['Field'], 'status_plano') !== false) {
            $finalCols[] = $row['Field'];
        }
    }
    echo "- Colunas de plano: " . (empty($finalCols) ? 'Nenhuma (usando tabelas novas)' : implode(', ', $finalCols)) . "\n";

    echo "\n🎉 Migração concluída! O erro 500 ao aprovar planos deve estar corrigido.\n";
    echo "Próximo: Teste a aprovação de plano no admin.\n";
} catch (Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    echo "Execute manualmente se necessário.\n";
}
