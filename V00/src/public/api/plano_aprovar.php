<?php

/*
   API LEGADA - Aprovar Compra de Plano
   A aprovação de restaurantes/planos é exclusiva do fundador SaaS (Super Admin).
   Use: /api/super_admin_plano_aprovar.php
 */

session_start();
header('Content-Type: application/json');

http_response_code(403);
echo json_encode([
    "success" => false,
    "message" => "Aprovacao restrita ao Super Admin fundador. Use o painel AdminPro.",
    "code" => "SUPER_ADMIN_REQUIRED",
    "endpoint" => "api/super_admin_plano_aprovar.php"
]);
exit;
