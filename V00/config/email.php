<?php

/**
 * Configurações de email (SMTP) - Gmail
 * Ajuste via variáveis de ambiente em `V00/.env` quando for publicar.
 */
return [
    // Habilitar envio via SMTP (PHPMailer). Se false, usa mail() do PHP.
    'use_smtp' => true,

    // Configurações SMTP - Gmail
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'edvismaldinchibante244@gmail.com',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'smtp_secure' => 'tls',

    // Endereço remetente
    'smtp_from' => 'Sistema RestaurantESA <edvismaldinchibante244@gmail.com>',
];
