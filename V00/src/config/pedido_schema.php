<?php

if (!function_exists('pedido_schema_coluna_existe')) {
    function pedido_schema_coluna_existe(PDO $db, string $tabela, string $coluna): bool
    {
        $stmt = $db->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabela
              AND COLUMN_NAME = :coluna
            LIMIT 1
        ");
        $stmt->bindValue(':tabela', $tabela, PDO::PARAM_STR);
        $stmt->bindValue(':coluna', $coluna, PDO::PARAM_STR);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('pedido_schema_garantir')) {
    function pedido_schema_garantir(PDO $db): bool
    {
        static $status = null;

        if ($status !== null) {
            return $status;
        }

        if (!pedido_schema_coluna_existe($db, 'pedidos', 'origem')) {
            try {
                $db->exec("ALTER TABLE pedidos ADD COLUMN origem VARCHAR(20) NOT NULL DEFAULT 'BALCAO' AFTER observacao");
            } catch (Throwable $e) {
                // Outra requisição pode ter criado a coluna em paralelo.
            }
        }

        if (pedido_schema_coluna_existe($db, 'pedidos', 'origem')) {
            try {
                $db->exec("
                    UPDATE pedidos
                    SET origem = CASE
                        WHEN origem IS NULL OR origem = '' THEN 'BALCAO'
                        ELSE UPPER(origem)
                    END
                ");
            } catch (Throwable $e) {
                // Não bloquear a aplicação por falha de normalização legada.
            }
        }

        $status = pedido_schema_coluna_existe($db, 'pedidos', 'origem');
        return $status;
    }
}

if (!function_exists('pedido_normalizar_origem')) {
    function pedido_normalizar_origem($origem): string
    {
        $valor = strtoupper(trim((string)$origem));
        $validas = ['QR', 'GARCOM', 'BALCAO'];
        return in_array($valor, $validas, true) ? $valor : 'BALCAO';
    }
}
