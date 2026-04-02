<?php

/*
 
   PROTEÇÃO DE ROTAS - SUPER ADMIN
   Incluir esse arquivo em páginas exclusivas do Super Admin
 
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once __DIR__ . '/super_admin_permissions.php';

// Verificar se usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.php");
    exit;
}

// Verificar se é super admin
if (!isset($_SESSION['super_admin']) || intval($_SESSION['super_admin']) !== 1) {
    header("HTTP/1.0 403 Forbidden");
    echo "Acesso negado! Apenas Super Admin.";
    exit;
}

// Função para verificar se é super admin
function isSuperAdmin()
{
    return isset($_SESSION['super_admin']) && intval($_SESSION['super_admin']) === 1;
}

if (!function_exists('requireSuperAdminPermission')) {
    function requireSuperAdminPermission($permission)
    {
        if (!super_admin_has_permission($permission)) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Acesso negado! Sem permissão para esta área.';
            exit;
        }
    }
}
