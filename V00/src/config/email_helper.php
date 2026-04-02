<?php

/*
   Helper de envio de email — reutilizável em toda a aplicação.
   Inclua este arquivo onde precisar enviar emails.
*/

if (!function_exists('saas_email_project_root')) {
    function saas_email_project_root(): string
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('saas_email_debug_log')) {
    function saas_email_debug_log(string $mensagem): void
    {
        $logPath = saas_email_project_root() . '/debug_email.log';
        @file_put_contents($logPath, date('Y-m-d H:i:s') . ' | ' . $mensagem . PHP_EOL, FILE_APPEND);
    }
}

if (!function_exists('saas_email_set_last_error')) {
    function saas_email_set_last_error(?string $mensagem): void
    {
        $GLOBALS['saas_email_last_error'] = $mensagem !== null ? trim($mensagem) : null;
    }
}

if (!function_exists('saas_email_get_last_error')) {
    function saas_email_get_last_error(): ?string
    {
        $valor = $GLOBALS['saas_email_last_error'] ?? null;
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}

if (!function_exists('saas_email_env_file_candidates')) {
    function saas_email_env_file_candidates(): array
    {
        $projectRoot = saas_email_project_root();

        return [
            $projectRoot . '/.env',
            $projectRoot . '/.env.local',
            $projectRoot . '/src/.env',
            $projectRoot . '/src/.env.local',
        ];
    }
}

if (!function_exists('saas_email_parse_env_file')) {
    function saas_email_parse_env_file(string $path): array
    {
        $result = [];

        if (!is_file($path) || !is_readable($path)) {
            return $result;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $result;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if ($key === '') {
                continue;
            }

            if ($value !== '') {
                $firstChar = substr($value, 0, 1);
                $lastChar = substr($value, -1);
                if (
                    ($firstChar === '"' && $lastChar === '"')
                    || ($firstChar === "'" && $lastChar === "'")
                ) {
                    $value = substr($value, 1, -1);
                    if ($firstChar === '"') {
                        $value = stripcslashes($value);
                    }
                } else {
                    $commentPos = strpos($value, ' #');
                    if ($commentPos !== false) {
                        $value = rtrim(substr($value, 0, $commentPos));
                    }
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }
}

if (!function_exists('saas_email_load_local_env')) {
    function saas_email_load_local_env(): array
    {
        static $loaded = false;
        static $values = [];

        if ($loaded) {
            return $values;
        }

        $loaded = true;

        foreach (saas_email_env_file_candidates() as $path) {
            if (!is_file($path)) {
                continue;
            }

            $values = array_merge($values, saas_email_parse_env_file($path));
        }

        foreach ($values as $key => $value) {
            // O .env do projeto deve prevalecer sobre variaveis herdadas do processo,
            // especialmente em ambiente local com servidor PHP persistente.
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return $values;
    }
}

if (!function_exists('saas_email_is_placeholder_config')) {
    function saas_email_is_placeholder_config(array $config): bool
    {
        $smtpHost = strtolower(trim((string)($config['smtp_host'] ?? '')));
        $smtpUsername = (string)($config['smtp_username'] ?? '');
        $smtpPassword = (string)($config['smtp_password'] ?? '');
        $smtpFrom = (string)($config['smtp_from'] ?? '');

        return $smtpHost === 'smtp.test'
            || $smtpHost === ''
            || substr($smtpHost, -5) === '.test'
            || strpos($smtpHost, 'example') !== false
            || trim($smtpUsername) === ''
            || strpos($smtpUsername, 'seu-email') !== false
            || trim($smtpPassword) === ''
            || strpos($smtpPassword, 'sua-senha') !== false
            || trim($smtpFrom) === ''
            || strpos($smtpFrom, 'seusite.com') !== false;
    }
}

if (!function_exists('saas_email_env_value')) {
    function saas_email_env_value(string $key): ?string
    {
        saas_email_load_local_env();

        $value = getenv($key);
        if ($value === false && array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        }
        if ($value === false && array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        }

        if ($value === false) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}

if (!function_exists('saas_email_is_gmail_host')) {
    function saas_email_is_gmail_host(string $host): bool
    {
        $host = strtolower(trim($host));
        return $host === 'smtp.gmail.com' || substr($host, -10) === '.gmail.com';
    }
}

if (!function_exists('saas_email_carregar_config')) {
    function saas_email_carregar_config(): array
    {
        saas_email_load_local_env();

        $projectRoot = saas_email_project_root();
        $paths = [
            $projectRoot . '/config/email.php',
            __DIR__ . '/email.php',
        ];
        $fallbackConfig = [];

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $config = require $path;
            if (!is_array($config)) {
                continue;
            }

            if (!$fallbackConfig) {
                $fallbackConfig = $config;
            }

            if (!saas_email_is_placeholder_config($config)) {
                return $config;
            }
        }

        return $fallbackConfig;
    }
}

if (!function_exists('saas_email_autoload_candidates')) {
    function saas_email_autoload_candidates(): array
    {
        $projectRoot = saas_email_project_root();

        return [
            $projectRoot . '/vendor/autoload.php',
            dirname($projectRoot) . '/vendor/autoload.php',
        ];
    }
}

if (!function_exists('saas_email_obter_autoload')) {
    function saas_email_obter_autoload(): ?string
    {
        foreach (saas_email_autoload_candidates() as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}

if (!function_exists('saas_email_phpmailer_candidates')) {
    function saas_email_phpmailer_candidates(): array
    {
        $projectRoot = saas_email_project_root();

        return [
            $projectRoot . '/vendor/phpmailer/phpmailer/src',
            dirname($projectRoot) . '/vendor/phpmailer/phpmailer/src',
        ];
    }
}

if (!function_exists('saas_email_carregar_phpmailer')) {
    function saas_email_carregar_phpmailer(): ?string
    {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
            return 'loaded';
        }

        foreach (saas_email_phpmailer_candidates() as $basePath) {
            $exceptionFile = $basePath . '/Exception.php';
            $phpmailerFile = $basePath . '/PHPMailer.php';
            $smtpFile = $basePath . '/SMTP.php';

            if (!is_file($exceptionFile) || !is_file($phpmailerFile) || !is_file($smtpFile)) {
                continue;
            }

            require_once $exceptionFile;
            require_once $phpmailerFile;
            require_once $smtpFile;

            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
                return $basePath;
            }
        }

        $autoload = saas_email_obter_autoload();
        if ($autoload !== null) {
            require_once $autoload;
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
                return $autoload;
            }
        }

        return null;
    }
}

if (!function_exists('saas_enviar_email')) {
    /**
     * Envia um email HTML.
     *
     * @param string $para       Endereço de destino
     * @param string $assunto    Assunto do email
     * @param string $mensagem   Corpo HTML
     * @return bool              true se enviado com sucesso
     */
    function saas_enviar_email(string $para, string $assunto, string $mensagem): bool
    {
        saas_email_set_last_error(null);
        $emailConfig = saas_email_carregar_config();

        $envHost = saas_email_env_value('SMTP_HOST');
        if ($envHost !== null && saas_email_is_placeholder_config(['smtp_host' => $envHost])) {
            $envHost = null;
        }

        $smtp_host = $envHost ?? ($emailConfig['smtp_host'] ?? 'localhost');
        $smtp_port = saas_email_env_value('SMTP_PORT') ?? ($emailConfig['smtp_port'] ?? 587);
        $smtp_username = saas_email_env_value('SMTP_USERNAME') ?? ($emailConfig['smtp_username'] ?? '');
        $smtp_password = saas_email_env_value('SMTP_PASSWORD') ?? ($emailConfig['smtp_password'] ?? '');
        $smtp_from = saas_email_env_value('SMTP_FROM') ?? ($emailConfig['smtp_from'] ?? 'noreply@localhost');
        $smtp_secure = saas_email_env_value('SMTP_SECURE') ?? ($emailConfig['smtp_secure'] ?? 'tls');
        $smtp_timeout = (int)(saas_email_env_value('SMTP_TIMEOUT') ?? ($emailConfig['smtp_timeout'] ?? 5));
        $smtp_debug = (int)(saas_email_env_value('SMTP_DEBUG') ?? ($emailConfig['smtp_debug'] ?? 0));
        $useSmtpEnv = getenv('USE_SMTP');
        $use_smtp = $useSmtpEnv !== false
            ? filter_var($useSmtpEnv, FILTER_VALIDATE_BOOLEAN)
            : (bool)($emailConfig['use_smtp'] ?? false);

        $effectiveConfig = [
            'smtp_host' => $smtp_host,
            'smtp_username' => $smtp_username,
            'smtp_password' => $smtp_password,
            'smtp_from' => $smtp_from,
        ];
        $hasPlaceholderConfig = saas_email_is_placeholder_config($effectiveConfig);

        if (saas_email_is_gmail_host($smtp_host)) {
            $smtp_password = preg_replace('/\s+/', '', $smtp_password);
        }

        if ($use_smtp && $hasPlaceholderConfig) {
            saas_email_debug_log('AVISO: Configuracao SMTP ainda esta com placeholders. Usando fallback mail().');
            error_log('[saas_enviar_email] configuracao SMTP padrao/placeholders ainda nao foi ajustada; usando fallback mail().');
            saas_email_set_last_error('Configuracao SMTP ainda esta com placeholders.');
            $use_smtp = false;
        }

        $phpMailerLoaded = false;
        $phpMailerSource = null;

        // Tentar PHPMailer primeiro quando SMTP estiver habilitado.
        if ($use_smtp) {
            $phpMailerSource = saas_email_carregar_phpmailer();
            $phpMailerLoaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer', false);

            if ($phpMailerLoaded) {
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host     = $smtp_host;
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtp_username;
                    $mail->Password = $smtp_password;
                    if (defined('PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS')) {
                        $mail->SMTPSecure = $smtp_secure === 'ssl'
                            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    } else {
                        $mail->SMTPSecure = $smtp_secure;
                    }
                    $mail->Port = (int) $smtp_port;
                    $mail->Timeout = $smtp_timeout > 0 ? $smtp_timeout : 5;
                    $mail->SMTPKeepAlive = false;
                    if ($smtp_debug > 0) {
                        $mail->SMTPDebug = $smtp_debug;
                        $mail->Debugoutput = static function ($str): void {
                            saas_email_debug_log('SMTP: ' . trim((string)$str));
                        };
                    }

                    // Remetente
                    if (strpos($smtp_from, '<') !== false) {
                        [$fromName, $fromAddr] = explode('<', $smtp_from, 2);
                        $mail->setFrom(trim($fromAddr, '> '), trim($fromName));
                    } else {
                        $mail->setFrom($smtp_from);
                    }

                    $mail->addAddress($para);
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = $assunto;
                    $mail->Body    = $mensagem;
                    $mail->send();
                    saas_email_set_last_error(null);
                    saas_email_debug_log('SUCESSO: Email enviado para ' . $para . ' via PHPMailer.');
                    return true;
                } catch (Throwable $e) {
                    saas_email_set_last_error($e->getMessage());
                    saas_email_debug_log('PHPMailer error: ' . $e->getMessage());
                    if (saas_email_is_gmail_host($smtp_host) && stripos($e->getMessage(), 'authenticate') !== false) {
                        saas_email_debug_log('DICA: O Gmail rejeitou a autenticacao. Verifique se SMTP_USERNAME esta correto e se SMTP_PASSWORD e uma senha de app valida de 16 caracteres.');
                    }
                    error_log('[saas_enviar_email] PHPMailer: ' . $e->getMessage());
                }
            } else {
                saas_email_debug_log(
                    'AVISO: PHPMailer nao encontrado. Caminhos testados: ' .
                        implode(' | ', saas_email_phpmailer_candidates()) .
                        ' | autoload: ' . implode(' | ', saas_email_autoload_candidates()) .
                        '. Tentando fallback mail().'
                );
                error_log('[saas_enviar_email] PHPMailer nao carregado; usando fallback mail().');
                saas_email_set_last_error('PHPMailer nao encontrado para envio SMTP.');
            }
        }

        // Fallback: função mail() nativa do PHP
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$smtp_from}\r\n";
        $headers .= "Reply-To: {$smtp_from}\r\n";
        $enviado = @mail($para, $assunto, $mensagem, $headers);
        saas_email_debug_log('Resultado mail(): ' . ($enviado ? 'OK' : 'FALHOU'));
        if ($enviado) {
            saas_email_set_last_error(null);
            saas_email_debug_log('SUCESSO: Email enviado para ' . $para . ' via mail().');
        } else {
            $erroAtual = saas_email_get_last_error();
            saas_email_set_last_error($erroAtual ?: 'mail() falhou no servidor local.');
        }
        return $enviado;
    }
}
