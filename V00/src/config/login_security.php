<?php

if (!function_exists('login_security_coluna_existe')) {
    function login_security_coluna_existe(PDO $db, string $tabela, string $coluna): bool
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

if (!function_exists('login_security_garantir_schema')) {
    function login_security_garantir_schema(PDO $db): bool
    {
        static $status = null;

        if ($status !== null) {
            return $status;
        }

        $alteracoes = [
            'tentativas_login' => "ALTER TABLE usuarios ADD COLUMN tentativas_login INT NOT NULL DEFAULT 0",
            'bloqueado_ate' => "ALTER TABLE usuarios ADD COLUMN bloqueado_ate DATETIME NULL DEFAULT NULL",
        ];

        foreach ($alteracoes as $coluna => $sql) {
            if (login_security_coluna_existe($db, 'usuarios', $coluna)) {
                continue;
            }

            try {
                $db->exec($sql);
            } catch (Throwable $e) {
                // Ignora concorrencia ou ambientes em que a migracao ja rodou.
            }
        }

        $status = login_security_coluna_existe($db, 'usuarios', 'tentativas_login')
            && login_security_coluna_existe($db, 'usuarios', 'bloqueado_ate');

        return $status;
    }
}
