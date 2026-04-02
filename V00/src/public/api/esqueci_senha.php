<?php

/*
   API - Esqueci a Senha
   Envia email com link de recuperação
 */

include_once '../../config/security.php';
security_start_session();
security_set_headers();
security_regenerate_session(15);

// Evita que warnings/notices gerem output HTML e quebrem o JSON da API.
error_reporting(E_ALL);
ini_set('display_errors', 0);
if (ob_get_level() === 0) {
    ob_start();
}

include_once '../../config/database.php';
include_once '../../config/email_helper.php';
include_once '../../config/password_reset_helper.php';

// Throttle basico para evitar spam (30s por sessão + rate limit por IP)
$lastReset = (int)($_SESSION['last_password_reset_request'] ?? 0);
if ($lastReset > 0 && (time() - $lastReset) < 30) {
    responderJson([
        'success' => false,
        'message' => 'Aguarde alguns segundos antes de solicitar novamente.'
    ]);
}
if (!security_rate_limit('password_reset', 5, 600)) {
    security_log_event('password_reset_rate_limited', ['endpoint' => 'api/esqueci_senha']);
    responderJson([
        'success' => false,
        'message' => 'Muitas solicitações. Aguarde alguns minutos e tente novamente.'
    ]);
}
$_SESSION['last_password_reset_request'] = time();

header('Content-Type: application/json');

function responderJson(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        responderJson(["success" => false, "message" => "Por favor, insira seu email."]);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderJson(["success" => false, "message" => "Email inválido."]);
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        responderJson(["success" => false, "message" => "Erro de conexão ao banco de dados."]);
    }

    try {
        password_reset_ensure_table($db);

        $stmt = $db->prepare("SELECT id, nome FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            responderJson(["success" => true, "message" => "Se o email estiver cadastrado, você receberá um link de recuperação."]);
        }

        $resetData = password_reset_create_token($db, $email, '+1 hour', (int)$usuario['id']);
        $link = password_reset_build_link($resetData['token']);

        $subject = "Recuperação de Senha - Sistema RestaurantESA";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .btn { display: inline-block; padding: 12px 24px; background: #6c5ce7; color: white; text-decoration: none; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Olá, {$usuario['nome']}!</h2>
                <p>Recebemos uma solicitação para redefinir a senha da sua conta.</p>
                <p>Clique no botão abaixo para criar uma nova senha:</p>
                <p style='text-align: center;'>
                    <a href='{$link}' class='btn'>Redefinir Senha</a>
                </p>
                <p>Ou copie e cole este link no seu navegador:</p>
                <p>{$link}</p>
                <p><strong>Este link expira em 1 hora.</strong></p>
                <p>Se você não solicitou esta recuperação, ignore este email.</p>
                <div class='footer'>
                    <p>Este é um email automático do Sistema RestaurantESA. Por favor, não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        saas_enviar_email($email, $subject, $body);

        responderJson(["success" => true, "message" => "Se o email estiver cadastrado, você receberá um link de recuperação."]);
    } catch (Throwable $e) {
        error_log('Esqueci senha API Error: ' . $e->getMessage());
        responderJson(["success" => false, "message" => "Erro interno no servidor."], 500);
    }
} else {
    responderJson(["success" => false, "message" => "Método não permitido."], 405);
}

