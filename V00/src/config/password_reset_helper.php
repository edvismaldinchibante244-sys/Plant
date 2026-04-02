<?php

if (!function_exists('password_reset_ensure_table')) {
    function password_reset_ensure_table(PDO $db): void
    {
        $stmt = $db->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'password_resets'
            LIMIT 1
        ");
        $stmt->execute();

        $tableExists = (bool)$stmt->fetchColumn();
        if (!$tableExists) {
            // DDL dentro de transação MySQL provoca commit implícito.
            // Se chegamos aqui no fluxo de reset, a tabela já deveria existir.
            if ($db->inTransaction()) {
                throw new RuntimeException('Tabela password_resets ausente durante uma transacao ativa.');
            }

            $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                user_id INT NULL,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                used TINYINT(1) DEFAULT 0,
                INDEX idx_token (token),
                INDEX idx_email (email),
                INDEX idx_user_id (user_id)
            )");
            return;
        }

        $stmtCols = $db->query("SHOW COLUMNS FROM password_resets");
        $columns = [];
        while ($column = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $column['Field'];
        }

        if (!in_array('user_id', $columns, true)) {
            if ($db->inTransaction()) {
                throw new RuntimeException('Coluna user_id ausente em password_resets durante uma transacao ativa.');
            }

            $db->exec("ALTER TABLE password_resets ADD COLUMN user_id INT NULL AFTER email, ADD INDEX idx_user_id (user_id)");
        }
    }
}

if (!function_exists('password_reset_create_token')) {
    function password_reset_create_token(PDO $db, string $email, string $expiresSpec = '+1 hour', ?int $userId = null): array
    {
        $email = trim($email);
        if ($email === '') {
            throw new InvalidArgumentException('Email obrigatorio para criar token de senha.');
        }

        password_reset_ensure_table($db);

        $stmtInvalidate = $db->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
        $stmtInvalidate->execute([$email]);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable('now'))->modify($expiresSpec)->format('Y-m-d H:i:s');

        $stmt = $db->prepare("INSERT INTO password_resets (email, user_id, token, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $userId, $tokenHash, $expiresAt]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }
}

if (!function_exists('password_reset_build_link')) {
    function password_reset_build_link(string $token): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if (substr($basePath, -4) === '/api') {
            $basePath = substr($basePath, 0, -4);
        }

        if ($basePath === '') {
            $basePath = '/';
        }

        return $protocol . $host . rtrim($basePath, '/') . '/nova_senha.php?token=' . urlencode($token);
    }
}

if (!function_exists('password_reset_find_valid_token')) {
    function password_reset_find_valid_token(PDO $db, string $token, bool $forUpdate = false): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        password_reset_ensure_table($db);

        $tokenHash = hash('sha256', $token);
        $sql = "
            SELECT *
            FROM password_resets
            WHERE used = 0
              AND expires_at > NOW()
              AND (token = :token_raw OR token = :token_hash)
            ORDER BY id DESC
            LIMIT 1
        ";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':token_raw', $token, PDO::PARAM_STR);
        $stmt->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
        $stmt->execute();

        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reset ?: null;
    }
}

if (!function_exists('password_reset_mark_used')) {
    function password_reset_mark_used(PDO $db, int $resetId): bool
    {
        if ($resetId <= 0) {
            return false;
        }

        password_reset_ensure_table($db);
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        return $stmt->execute([$resetId]);
    }
}

if (!function_exists('password_reset_invalidate_email_tokens')) {
    function password_reset_invalidate_email_tokens(PDO $db, string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }

        password_reset_ensure_table($db);
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
        return $stmt->execute([$email]);
    }
}
