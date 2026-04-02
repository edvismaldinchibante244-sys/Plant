<?php

/*
   Configurações de email (SMTP) - fallback/template local.
   A configuração ativa é carregada primeiro de `V00/config/email.php`.
 */
return [
    // Habilitar envio via SMTP (PHPMailer). Se false, usa mail() do PHP.
    'use_smtp' => true,

    // Configurações SMTP
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_timeout' => 5,
    'smtp_username' => 'edvismaldinchibante244@gmail.com',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'smtp_secure' => 'tls', // 'tls' ou 'ssl'

    // Endereço remetente
    'smtp_from' => 'Sistema RestaurantESA <edvismaldinchibante244@gmail.com>',
];
