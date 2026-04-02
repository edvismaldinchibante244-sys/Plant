<?php

/*
   Security bootstrap helpers (intermediate hardening).
   - Secure session settings
   - Standard security headers
   - Idle timeout + periodic session ID regeneration
*/

if (!function_exists('security_is_https')) {
    function security_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }
}

if (!function_exists('security_start_session')) {
    function security_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secureCookie = security_is_https();
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secureCookie ? '1' : '0');

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secureCookie,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }
}

if (!function_exists('security_set_headers')) {
    function security_set_headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}

if (!function_exists('security_enforce_idle_timeout')) {
    function security_enforce_idle_timeout(int $minutes = 45): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }
        $now = time();
        $last = (int)($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && ($now - $last) > ($minutes * 60)) {
            $_SESSION = [];
            session_unset();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return false;
        }
        $_SESSION['last_activity'] = $now;
        return true;
    }
}

if (!function_exists('security_regenerate_session')) {
    function security_regenerate_session(int $minutes = 15): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $now = time();
        $last = (int)($_SESSION['last_regen'] ?? 0);
        if ($last === 0 || ($now - $last) > ($minutes * 60)) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = $now;
        }
    }
}

if (!function_exists('security_get_client_ip')) {
    function security_get_client_ip(): string
    {
        $candidates = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $raw = (string)$_SERVER[$key];
                $parts = array_map('trim', explode(',', $raw));
                if (!empty($parts[0])) {
                    return $parts[0];
                }
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('security_rate_limit')) {
    function security_rate_limit(string $actionKey, int $maxAttempts, int $windowSeconds): bool
    {
        $ip = security_get_client_ip();
        $keyRaw = $actionKey . '|' . $ip;
        $key = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $keyRaw);
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rate_' . $key . '.json';
        $now = time();
        $data = ['start' => $now, 'count' => 0];

        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
                    $data = $decoded;
                }
            }
        }

        if (($now - (int)$data['start']) > $windowSeconds) {
            $data = ['start' => $now, 'count' => 0];
        }

        $data['count'] = (int)$data['count'] + 1;
        @file_put_contents($path, json_encode($data), LOCK_EX);

        return $data['count'] <= $maxAttempts;
    }
}

if (!function_exists('security_csrf_token')) {
    function security_csrf_token(int $ttlSeconds = 7200): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        $now = time();
        $token = (string)($_SESSION['csrf_token'] ?? '');
        $issuedAt = (int)($_SESSION['csrf_token_time'] ?? 0);
        if ($token === '' || $issuedAt === 0 || ($now - $issuedAt) > $ttlSeconds) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $token;
            $_SESSION['csrf_token_time'] = $now;
        }
        return $token;
    }
}

if (!function_exists('security_get_request_csrf')) {
    function security_get_request_csrf(): ?string
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!empty($header)) {
            return (string)$header;
        }
        if (isset($_POST['csrf_token'])) {
            return (string)$_POST['csrf_token'];
        }
        return null;
    }
}

if (!function_exists('security_validate_csrf')) {
    function security_validate_csrf(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($sessionToken === '' || $token === null || $token === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('security_validate_upload')) {
    function security_validate_upload(array $file, array $allowedExt, array $allowedMime, int $maxBytes, ?string &$error = null): bool
    {
        $error = null;
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload inválido.';
            return false;
        }
        if (!isset($file['size']) || (int)$file['size'] > $maxBytes) {
            $error = 'Arquivo muito grande.';
            return false;
        }
        $name = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            $error = 'Extensão não permitida.';
            return false;
        }
        if (!isset($file['tmp_name']) || !is_file($file['tmp_name'])) {
            $error = 'Arquivo temporário inválido.';
            return false;
        }
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) finfo_close($finfo);
            if ($mime && !in_array($mime, $allowedMime, true)) {
                $error = 'Tipo de arquivo não permitido.';
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('security_log_event')) {
    function security_log_event(string $event, array $context = []): void
    {
        $baseDir = dirname(__DIR__);
        $logDir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $ip = security_get_client_ip();
        $line = [
            'time' => date('c'),
            'event' => $event,
            'ip' => $ip,
            'context' => $context,
        ];
        $path = $logDir . DIRECTORY_SEPARATOR . 'security.log';
        @file_put_contents($path, json_encode($line, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
