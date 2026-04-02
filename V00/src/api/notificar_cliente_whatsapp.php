<?php

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use Twilio\Rest\Client;

function notificar_cliente_whatsapp($telefone, $mensagem): bool
{
    if (!class_exists(Client::class)) {
        error_log('[WHATSAPP] Biblioteca Twilio indisponivel.');
        return false;
    }

    $sid = getenv('TWILIO_ACCOUNT_SID') ?: getenv('TWILIO_SID');
    $token = getenv('TWILIO_AUTH_TOKEN') ?: getenv('TWILIO_TOKEN');
    $twilioNumber = getenv('TWILIO_WHATSAPP_FROM') ?: 'whatsapp:+14155238886';
    $destino = notificar_cliente_whatsapp_normalizar_destino($telefone);
    $mensagem = trim((string)$mensagem);

    if ($destino === null || $mensagem === '') {
        return false;
    }

    if ($sid === false || $sid === '' || $token === false || $token === '') {
        error_log('[WHATSAPP] Credenciais Twilio nao configuradas.');
        return false;
    }

    try {
        $client = new Client($sid, $token);
        $client->messages->create(
            'whatsapp:' . $destino,
            [
                'from' => $twilioNumber,
                'body' => $mensagem,
            ]
        );

        return true;
    } catch (\Throwable $e) {
        error_log('[WHATSAPP][ERROR] ' . $e->getMessage());
        return false;
    }
}

function notificar_cliente_whatsapp_normalizar_destino($telefone): ?string
{
    $telefone = trim((string)$telefone);
    if ($telefone === '') {
        return null;
    }

    $prefixo = str_starts_with($telefone, '+') ? '+' : '';
    $digitos = preg_replace('/\D+/', '', $telefone);
    if ($digitos === '' || strlen($digitos) < 8) {
        return null;
    }

    return $prefixo . $digitos;
}
