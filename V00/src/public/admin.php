<?php
require_once __DIR__ . '/app_base.php';

/*
 
   PÁGINA DE ADMINISTRAÇÃO - SUPER ADMIN
 
 */

include_once APP_BASE . '/src/config/super_admin_check.php';
include_once APP_BASE . '/src/config/database.php';
include_once APP_BASE . '/src/config/csrf.php';
include_once APP_BASE . '/src/config/restaurante_status_helper.php';

$database = new Database();
$db = $database->getConnection();

// Buscar dados do super admin
$super_admin_nome = $_SESSION['nome'] ?? 'Super Admin';
$csrf_token = csrf_get_token();
$super_admin_permissions = super_admin_get_permissions();
$restaurante_status_suportados = restaurante_status_suportados($db);
if (empty($restaurante_status_suportados)) {
    $restaurante_status_suportados = ['ATIVO', 'BLOQUEADO', 'CANCELADO'];
}

$restaurante_status_labels = [
    'PENDENTE' => 'Aguardando Aprovação',
    'AGUARDANDO_APROVACAO' => 'Aguardando Aprovação',
    'ATIVO' => 'Ativo',
    'BLOQUEADO' => 'Bloqueado',
    'CANCELADO' => 'Cancelado',
    'SEM_STATUS' => 'Sem status',
];

$can_manage_restaurants = super_admin_has_permission('manage_restaurants');
$can_manage_users = super_admin_has_permission('manage_users');
$can_view_finance = super_admin_has_permission('view_finance');
$can_approve_plans = super_admin_has_permission('approve_plans');
$can_view_dashboard = super_admin_has_permission('view_dashboard');
$can_manage_security = super_admin_has_permission('manage_security');
$can_access_plans = $can_view_finance || $can_approve_plans;

$default_secao = 'restaurantes';
if (!$can_manage_restaurants && $can_manage_users) {
    $default_secao = 'usuarios';
} elseif (!$can_manage_restaurants && !$can_manage_users && $can_access_plans) {
    $default_secao = 'planos';
} elseif (!$can_manage_restaurants && !$can_manage_users && !$can_access_plans && $can_manage_security) {
    $default_secao = 'seguranca';
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração - Sistema de Restaurantes</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/adminpro-theme.css">

    <style>
        :root {
            --sa-bg-a: #f6f8ff;
            --sa-bg-b: #eef2ff;
            --sa-ink: #0f172a;
            --sa-muted: #64748b;
            --sa-border: rgba(148, 163, 184, 0.26);
            --sa-card: rgba(255, 255, 255, 0.92);
            --sa-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            --sa-primary: #2563eb;
            --sa-accent: #ff7a1a;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background:
                radial-gradient(circle at top right, rgba(162, 155, 254, 0.16), transparent 28%),
                linear-gradient(180deg, #f5f7ff 0%, #eef2fb 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--dark) 0%, #1a1a2e 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 16px 0 40px rgba(17, 24, 39, 0.18);
            transition: transform 0.25s ease;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar.sidebar-hidden {
            transform: translateX(-100%);
        }

        .menu-toggle-btn {
            position: fixed;
            left: 16px;
            top: 16px;
            z-index: 2100;
            width: 56px;
            height: 56px;
            border-radius: 16px;
            border: none;
            background: linear-gradient(180deg, #ff9433 0%, #ff7a1a 100%);
            color: #fff;
            font-size: 22px;
            box-shadow: 0 10px 20px rgba(255, 122, 26, 0.26);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: opacity .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        .menu-toggle-btn:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(255, 122, 26, 0.2), 0 12px 26px rgba(255, 122, 26, 0.34);
        }

        .menu-toggle-btn.is-docked {
            left: 202px;
            top: 14px;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            font-size: 18px;
            box-shadow: 0 6px 14px rgba(255, 122, 26, 0.2);
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.44);
            z-index: 1800;
            display: none;
        }

        .sidebar-backdrop.show {
            display: block;
        }

        .sidebar .brand {
            padding: 22px 20px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .brand h3 {
            color: white;
            font-size: 17px;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
        }

        .sidebar .brand span {
            color: #a5b4fc;
            font-size: 11px;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .sidebar .nav-item a {
            color: rgba(255, 255, 255, 0.7);
            margin: 4px 10px;
            padding: 11px 14px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.25s;
            cursor: pointer;
            border-left: 0;
        }

        .sidebar .nav-item a:hover,
        .sidebar .nav-item a.active {
            background: rgba(99, 102, 241, 0.3);
            color: white;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
            transform: translateX(2px);
        }

        .sidebar .nav-item a i {
            width: 20px;
            text-align: center;
            opacity: 0.9;
        }

        .main-content {
            margin-left: 260px;
            padding: 25px;
            transition: margin-left 0.25s ease;
            width: calc(100% - 260px);
            max-width: calc(100% - 260px);
            flex: 0 0 calc(100% - 260px);
        }

        body.layout-sidebar-collapsed .main-content {
            margin-left: 0;
            width: 100%;
            max-width: 100%;
            flex-basis: 100%;
            padding-left: 92px;
        }

        .section-panel {
            background: var(--sa-card);
            border: 1px solid var(--sa-border);
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: var(--sa-shadow);
            backdrop-filter: blur(14px);
        }

        .section-note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }

        .stat-card {
            background: var(--sa-card);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--sa-shadow);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            border: 1px solid var(--sa-border);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(17, 24, 39, 0.12);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .table-card {
            background: var(--sa-card);
            border-radius: 16px;
            box-shadow: var(--sa-shadow);
            overflow: hidden;
            border: 1px solid var(--sa-border);
        }

        .table-card .card-header {
            border-bottom: 1px solid rgba(108, 92, 231, 0.08);
        }

        .security-card {
            background: var(--sa-card);
            border-radius: 16px;
            box-shadow: var(--sa-shadow);
            overflow: hidden;
            border: 1px solid var(--sa-border);
        }

        .admin-shell-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid var(--sa-border);
            border-radius: 14px;
            padding: 12px 14px 12px 16px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }

        .admin-shell-header .title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            color: var(--sa-ink);
            letter-spacing: 0;
        }

        .admin-shell-header .sub {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--sa-muted);
            font-weight: 600;
        }

        .admin-founder-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            border: 1px solid rgba(37, 99, 235, 0.24);
            padding: 6px 12px;
            background: rgba(37, 99, 235, 0.08);
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .admin-founder-badge i {
            color: #1d4ed8;
        }

        .btn {
            border-radius: 12px;
            font-weight: 700;
        }

        .btn-primary {
            background: linear-gradient(180deg, #2f7cff 0%, #1e63e6 100%);
            border-color: #1e63e6;
        }

        .btn-success {
            background: linear-gradient(180deg, #1dbf84 0%, #109d6b 100%);
            border-color: #109d6b;
        }

        .form-control,
        .form-select {
            border-radius: 12px;
            border-color: #dbe2f2;
            min-height: 44px;
            box-shadow: none;
        }

        .table thead th {
            font-size: 13px;
            color: #334155;
            font-weight: 700;
            background: #f8fafc !important;
            border-bottom-color: #e2e8f0 !important;
        }

        #secao-seguranca {
            padding-left: 6px;
            padding-right: 6px;
            overflow: visible;
        }

        #secao-seguranca .row {
            --bs-gutter-x: 16px;
            --bs-gutter-y: 16px;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        #secao-seguranca [class*="col-"] {
            padding-left: 10px;
            padding-right: 10px;
        }

        #secao-seguranca .card-header h6,
        #secao-seguranca h4,
        #secao-seguranca h6 {
            color: #0f172a;
            font-weight: 700;
            letter-spacing: 0;
            word-break: break-word;
        }

        #secao-seguranca .card-header {
            padding: 14px 16px !important;
        }

        #secao-seguranca .text-muted {
            color: #475569 !important;
        }

        #secao-seguranca .security-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 16px;
            margin-bottom: 10px;
        }

        #secao-seguranca .security-header .controls {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        #secao-seguranca .security-header .controls .form-select {
            min-width: 210px;
            height: 52px;
            font-size: 16px;
            font-weight: 600;
        }

        #secao-seguranca .security-header .controls .btn {
            height: 52px;
            min-width: 170px;
            font-size: 16px;
            font-weight: 600;
        }

        #secao-seguranca .security-title {
            margin: 0;
            line-height: 1.2;
            font-size: 20px;
            color: #0b1220 !important;
            font-weight: 800 !important;
        }

        #secao-seguranca .security-subtitle {
            margin-top: 6px;
            margin-bottom: 0;
            font-size: 15px;
            color: #1e293b !important;
            font-weight: 600;
        }

        #secao-seguranca .security-last-update {
            margin-bottom: 14px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a !important;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #secao-seguranca #securityLastUpdate {
            color: #0b1220;
            font-weight: 800;
        }

        #secao-seguranca .card-header h6 {
            font-size: 18px;
            margin: 0;
        }

        #secao-seguranca table th {
            color: #0f172a;
            font-weight: 700;
            background: #f8fafc;
        }

        #secao-seguranca table td {
            color: #1f2937;
        }

        #secao-seguranca table {
            table-layout: fixed;
            width: 100%;
        }

        #secao-seguranca table th,
        #secao-seguranca table td {
            overflow-wrap: anywhere;
            word-break: break-word;
            vertical-align: middle;
        }

        #secao-seguranca #tabelaSecurityEvents td {
            font-size: 14px;
        }

        .security-kpi-row .stat-card {
            min-height: 118px;
        }

        #secao-seguranca .security-kpi-row .stat-card .text-muted {
            font-size: 15px !important;
            color: #334155 !important;
        }

        #secao-seguranca .security-kpi-row .stat-card .fs-3,
        #secao-seguranca .security-kpi-row .stat-card .fs-4 {
            font-size: 28px !important;
            line-height: 1.1;
        }

        .security-mini-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed rgba(108, 92, 231, 0.15);
            font-size: 14px;
        }

        .security-mini-item:last-child {
            border-bottom: 0;
        }

        .security-chart-wrap {
            min-height: 290px;
        }

        .security-checklist-row .badge {
            font-size: 11px;
            letter-spacing: 0;
        }

        .security-checklist-row td {
            font-size: 13px;
        }

        .security-checklist-summary .badge {
            font-size: 11px;
            padding: 6px 8px;
        }

        .table-card .table {
            margin-bottom: 0;
        }

        .table-card .table th,
        .table-card .table td {
            vertical-align: middle;
        }

        .table-toolbar-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }

        .table-toolbar-controls .toolbar-search {
            flex: 1 1 300px;
            min-width: 240px;
        }

        .action-group {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .action-group .btn {
            width: 38px;
            height: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }

        .table-id {
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
        }

        .table-identity {
            min-width: 220px;
        }

        .table-identity strong {
            display: block;
            line-height: 1.4;
        }

        .table-subtext {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: var(--muted);
        }

        .validity-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 999px;
        }

        .validity-badge.success {
            color: #0f9d58;
            background: rgba(15, 157, 88, 0.12);
        }

        .validity-badge.warning {
            color: #b7791f;
            background: rgba(255, 193, 7, 0.18);
        }

        .validity-badge.danger {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.12);
        }

        .badge-plano {
            font-size: 11px;
            padding: 5px 10px;
        }

        .badge-status {
            font-size: 11px;
            padding: 5px 10px;
        }

        .secao {
            display: none;
        }

        .secao.ativa {
            display: block;
        }

        @media (max-width: 991px) {
            .sidebar {
                width: 82vw;
                max-width: 340px;
                min-width: 230px;
                position: fixed;
                left: 0;
                top: 0;
                z-index: 2000;
                box-shadow: 2px 0 16px rgba(0, 0, 0, 0.08);
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding-top: 98px;
                width: 100%;
                max-width: 100%;
                flex-basis: 100%;
                padding-left: 14px;
            }

            .section-panel {
                padding: 18px;
            }

            .admin-shell-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 12px 14px;
            }

            .admin-shell-header .title {
                font-size: 16px;
            }
        }

        @media (max-width: 767px) {
            .table-toolbar-controls {
                justify-content: stretch;
            }

            .table-toolbar-controls .toolbar-search,
            .table-toolbar-controls .form-select,
            .table-toolbar-controls .btn {
                width: 100%;
            }

            #formSelecionarRestaurante,
            #formSelecionarRestaurante .form-select,
            #restauranteSelecionadoUsuarios,
            #btnAcessarDashboard {
                width: 100% !important;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 86vw !important;
                max-width: 320px !important;
                min-height: 100vh !important;
                display: block !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 14px !important;
                padding-top: 94px !important;
            }

            .menu-toggle-btn {
                width: 50px;
                height: 50px;
                border-radius: 14px;
                font-size: 20px;
                left: 12px;
                top: 12px;
            }

            .section-panel {
                padding: 16px !important;
            }

            .admin-shell-header {
                margin-bottom: 12px;
            }

            .stat-card {
                padding: 16px !important;
            }

            .table-card .card-header {
                padding: 14px 16px !important;
            }

            .table-card .table th,
            .table-card .table td {
                white-space: normal !important;
            }

            .table-identity {
                min-width: 0 !important;
            }

            .table-toolbar-controls .toolbar-search,
            .table-toolbar-controls .form-select,
            .table-toolbar-controls .btn {
                width: 100% !important;
            }

            .action-group {
                width: 100% !important;
                flex-wrap: wrap !important;
            }

            .action-group .btn {
                width: 100% !important;
            }

            .stat-icon {
                width: 44px;
                height: 44px;
                font-size: 20px;
            }

            .security-chart-wrap {
                min-height: 220px;
            }

            #secao-seguranca {
                padding-left: 0;
                padding-right: 0;
            }

            #secao-seguranca [class*="col-"] {
                padding-left: 0;
                padding-right: 0;
            }

            #secao-seguranca .security-header {
                grid-template-columns: 1fr;
            }

            #secao-seguranca .security-header .controls {
                justify-content: stretch;
            }

            #secao-seguranca .security-header .controls .form-select,
            #secao-seguranca .security-header .controls .btn {
                width: 100%;
                min-width: 0;
            }

            #secao-seguranca .security-title {
                font-size: 18px;
            }

            #secao-seguranca .security-subtitle {
                font-size: 14px;
            }

            #secao-seguranca .card-header h6 {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <button type="button" class="menu-toggle-btn" id="btnMenuToggle" aria-label="Abrir menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="container-fluid">
        <div class="row">
            <nav class="sidebar col-md-3 col-lg-2 d-md-block">
                <div class="brand">
                    <h3><i class="fas fa-crown me-2"></i>Super Admin</h3>
                    <span>Gestão de Restaurantes</span>
                </div>
                <ul class="nav flex-column">
                    <?php if ($can_manage_restaurants): ?>
                        <li class="nav-item"><a href="#" class="nav-link <?php echo $default_secao === 'restaurantes' ? 'active' : ''; ?> js-secao-link" data-secao="restaurantes"><i class="fas fa-building"></i> Restaurantes</a></li>
                    <?php endif; ?>
                    <?php if ($can_manage_users): ?>
                        <li class="nav-item"><a href="#" class="nav-link <?php echo $default_secao === 'usuarios' ? 'active' : ''; ?> js-secao-link" data-secao="usuarios"><i class="fas fa-users"></i> Usuários</a></li>
                    <?php endif; ?>
                    <?php if ($can_access_plans): ?>
                        <li class="nav-item"><a href="#" class="nav-link <?php echo $default_secao === 'planos' ? 'active' : ''; ?> js-secao-link" data-secao="planos"><i class="fas fa-credit-card"></i> Planos</a></li>
                    <?php endif; ?>
                    <?php if ($can_manage_security): ?>
                        <li class="nav-item"><a href="#" class="nav-link <?php echo $default_secao === 'seguranca' ? 'active' : ''; ?> js-secao-link" data-secao="seguranca"><i class="fas fa-shield-alt"></i> Segurança</a></li>
                    <?php endif; ?>
                    <?php if ($can_view_dashboard): ?>
                        <li class="nav-item"><a href="admin_dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <?php endif; ?>
                    <?php if ($can_manage_restaurants): ?>
                        <li class="nav-item"><a href="filiais.php" class="nav-link"><i class="fas fa-building"></i> Filiais</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                </ul>
            </nav>

            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <!-- Alertas -->
                <div id="alertContainer"></div>
                <div class="admin-shell-header">
                    <div>
                        <h2 class="title">Painel Executivo Super Admin</h2>
                        <p class="sub">Operação da plataforma, assinaturas, usuários e segurança corporativa.</p>
                    </div>
                    <span class="admin-founder-badge"><i class="fas fa-rocket"></i> Founder Control</span>
                </div>

                <?php if (!$can_manage_restaurants && !$can_manage_users && !$can_access_plans && !$can_manage_security): ?>
                    <div class="alert alert-warning" role="alert">
                        Este usuário não possui permissões de módulo no AdminPro. Contacte o fundador para ajustar os acessos.
                    </div>
                <?php endif; ?>

                <?php if ($can_view_dashboard): ?>
                    <!-- Estatísticas Gerais -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted" style="font-size: 13px;">Total de Restaurantes</div>
                                        <div class="fs-3 fw-bold" id="statTotalRestaurantes">0</div>
                                    </div>
                                    <div class="stat-icon" style="background: rgba(108, 92, 231, 0.1); color: var(--primary);">
                                        <i class="fas fa-building"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted" style="font-size: 13px;">Restaurantes Ativos</div>
                                        <div class="fs-3 fw-bold text-success" id="statRestaurantesAtivos">0</div>
                                    </div>
                                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted" style="font-size: 13px;">Assinaturas Expirando</div>
                                        <div class="fs-3 fw-bold text-warning" id="statAssinaturasExpirando">0</div>
                                    </div>
                                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SEÇÃO: RESTAURANTES -->
                <?php if ($can_manage_restaurants): ?>
                    <div id="secao-restaurantes" class="secao <?php echo $default_secao === 'restaurantes' ? 'ativa' : ''; ?>">
                        <!-- Header com Seletor de Restaurante -->
                        <div class="section-panel mb-4">
                            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
                                <div>
                                    <h4 class="mb-0"><i class="fas fa-building text-primary me-2"></i>Gerenciar Restaurantes</h4>
                                    <p class="text-muted mb-0" style="font-size: 14px;">Cadastre, pesquise e acompanhe os restaurantes da plataforma.</p>
                                    <div class="section-note">Os indicadores abaixo acompanham a lista atualmente filtrada.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-stretch justify-content-xl-end">
                                    <form id="formSelecionarRestaurante" class="d-flex flex-wrap gap-2 align-items-stretch">
                                        <select class="form-select" id="restauranteSelecionado" name="restaurante_id" style="width: 250px;">
                                            <option value="">Selecione um restaurante...</option>
                                        </select>
                                        <?php if ($can_view_dashboard): ?>
                                            <button type="button" class="btn btn-success" id="btnAcessarDashboard">
                                                <i class="fas fa-chart-line me-1"></i> Dashboard
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrar">
                                        <i class="fas fa-plus me-2"></i>Novo Restaurante
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Estatísticas -->
                        <div class="row g-4 mb-4">
                            <div class="col-12 col-sm-6 col-xl">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Na Lista</div>
                                            <div class="fs-3 fw-bold" id="listaTotalRestaurantes">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(108, 92, 231, 0.1); color: var(--primary);">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Ativos</div>
                                            <div class="fs-3 fw-bold text-success" id="listaAtivos">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Expirando em 30 dias</div>
                                            <div class="fs-3 fw-bold text-warning" id="listaExpirando">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="fas fa-hourglass-half"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Bloqueados</div>
                                            <div class="fs-3 fw-bold text-warning" id="listaBloqueados">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="fas fa-pause-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Empresarial</div>
                                            <div class="fs-3 fw-bold text-info" id="listaEnterprise">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabela de Restaurantes -->
                        <div class="table-card">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Restaurantes</h5>
                                    <div class="table-toolbar-controls">
                                        <input type="text" id="filtroRestaurantes" class="form-control form-control-sm toolbar-search" placeholder="Filtrar por nome, email, status, plano">
                                        <select id="pageSizeRestaurantes" class="form-select form-select-sm" style="width: 90px;">
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRestaurantesPrev">Anterior</button>
                                        <span class="text-muted small" id="paginacaoRestaurantesInfo">1/1</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRestaurantesNext">Próxima</button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4"><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="restaurantes" data-sort="id">ID</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="restaurantes" data-sort="nome">Nome</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="restaurantes" data-sort="email">Email</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="restaurantes" data-sort="telefone">Telefone</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="restaurantes" data-sort="plano">Plano</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="restaurantes" data-sort="status_exibicao">Status</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="restaurantes" data-sort="data_fim">Validade</button></th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabelaRestaurantes">
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>Carregando...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SEÇÃO: USUÁRIOS -->
                <?php if ($can_manage_users): ?>
                    <div id="secao-usuarios" class="secao <?php echo $default_secao === 'usuarios' ? 'ativa' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="mb-0"><i class="fas fa-users text-primary me-2"></i>Gerenciar Usuários</h4>
                                <p class="text-muted mb-0" style="font-size: 14px;">Selecione um restaurante para gerenciar seus usuários</p>
                            </div>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="restauranteSelecionadoUsuarios" style="width: 300px;">
                                    <option value="">Selecione um restaurante...</option>
                                </select>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrarUsuario">
                                    <i class="fas fa-plus me-2"></i>Novo Usuário
                                </button>
                            </div>
                        </div>

                        <div class="table-card">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Usuários</h5>
                                    <div class="table-toolbar-controls">
                                        <input type="text" id="filtroUsuarios" class="form-control form-control-sm toolbar-search" placeholder="Filtrar por nome, email, perfil">
                                        <select id="pageSizeUsuarios" class="form-select form-select-sm" style="width: 90px;">
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUsuariosPrev">Anterior</button>
                                        <span class="text-muted small" id="paginacaoUsuariosInfo">1/1</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUsuariosNext">Próxima</button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4"><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="usuarios" data-sort="id">ID</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="usuarios" data-sort="nome">Nome</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="usuarios" data-sort="email">Email</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="usuarios" data-sort="perfil">Perfil</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="usuarios" data-sort="ativo">Status</button></th>
                                                <th>Turno</th>
                                                <th class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabelaUsuarios">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                Selecione um restaurante para ver os usuários
                                            </td>
                                        </tr>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SEÇÃO: PLANOS -->
                <?php if ($can_access_plans): ?>
                    <div id="secao-planos" class="secao <?php echo $default_secao === 'planos' ? 'ativa' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="mb-0"><i class="fas fa-credit-card text-primary me-2"></i>Gerenciar Planos</h4>
                                <p class="text-muted mb-0" style="font-size: 14px;">Acompanhe e aprove os pagamentos de planos dos restaurantes</p>
                            </div>
                            <button class="btn btn-success" id="btnAtualizarCompras">
                                <i class="fas fa-sync me-2"></i>Atualizar
                            </button>
                        </div>

                        <!-- Estatísticas de Planos -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Total Compras</div>
                                            <div class="fs-3 fw-bold" id="totalCompras">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(108, 92, 231, 0.1); color: var(--primary);">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Pendentes</div>
                                            <div class="fs-3 fw-bold text-warning" id="totalPendentes">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Aprovados</div>
                                            <div class="fs-3 fw-bold text-success" id="totalAprovados">0</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">MRR (Receita Mensal)</div>
                                            <div class="fs-4 fw-bold text-info" id="totalMRR">0 MZN</div>
                                            <div class="text-muted" style="font-size: 11px;">ARR: <span id="totalARR">0 MZN</span></div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Taxa de Conversão</div>
                                            <div class="fs-4 fw-bold text-primary" id="totalConversao">0%</div>
                                            <div class="text-muted" style="font-size: 11px;">Aprovados / Solicitações</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                                            <i class="fas fa-percentage"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted" style="font-size: 13px;">Inadimplência (estimada)</div>
                                            <div class="fs-4 fw-bold text-danger" id="totalInadimplencia">0%</div>
                                            <div class="text-muted" style="font-size: 11px;">Pendentes / Solicitações</div>
                                        </div>
                                        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabela de Compras -->
                        <div class="table-card">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Histórico de Compras de Planos</h5>
                                    <div class="table-toolbar-controls">
                                        <input type="text" id="filtroCompras" class="form-control form-control-sm toolbar-search" placeholder="Filtrar por restaurante, plano, status">
                                        <select id="filtroPlanoCompras" class="form-select form-select-sm" style="width: 160px;">
                                            <option value="">Todos planos</option>
                                            <option value="BASICO">Básico</option>
                                            <option value="PROFISSIONAL">Profissional</option>
                                            <option value="EMPRESARIAL">Empresarial</option>
                                        </select>
                                        <select id="filtroCicloCompras" class="form-select form-select-sm" style="width: 130px;">
                                            <option value="">Todos ciclos</option>
                                            <option value="MENSAL">Mensal</option>
                                            <option value="TRIMESTRAL">Trimestral</option>
                                            <option value="ANUAL">Anual</option>
                                        </select>
                                        <select id="filtroStatusCompras" class="form-select form-select-sm" style="width: 140px;">
                                            <option value="">Todos status</option>
                                            <option value="AGUARDANDO_APROVACAO">Aguardando Aprovação</option>
                                            <option value="APROVADO">Aprovado</option>
                                            <option value="REJEITADO">Rejeitado</option>
                                        </select>
                                        <input type="date" id="filtroDataIniCompras" class="form-control form-control-sm" style="width: 155px;" title="Data inicial">
                                        <input type="date" id="filtroDataFimCompras" class="form-control form-control-sm" style="width: 155px;" title="Data final">
                                        <select id="pageSizeCompras" class="form-select form-select-sm" style="width: 90px;">
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnComprasPrev">Anterior</button>
                                        <span class="text-muted small" id="paginacaoComprasInfo">1/1</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnComprasNext">Próxima</button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4"><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="id">ID</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="restaurante_nome">Restaurante</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="plano_atual">Plano Atual</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="plano_novo">Plano Novo</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="valor">Valor</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="metodo_pagamento">Método</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="ciclo">Ciclo</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="hash_arquivo">Hash</button></th>
                                                <th>Comprovativo</th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="status">Status</button></th>
                                                <th><button class="btn btn-link btn-sm p-0 text-decoration-none text-dark fw-semibold js-sort" data-table="compras" data-sort="criado_em">Data</button></th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabelaCompras">
                                            <tr>
                                                <td colspan="12" class="text-center py-4 text-muted">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>Carregando...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($can_manage_security): ?>
                    <div id="secao-seguranca" class="secao <?php echo $default_secao === 'seguranca' ? 'ativa' : ''; ?>">
                        <div class="security-header">
                            <div>
                                <h4 class="security-title"><i class="fas fa-shield-alt text-danger me-2"></i>Centro de Segurança</h4>
                                <p class="security-subtitle">Monitoramento corporativo de ataques, WAF e resposta automática.</p>
                            </div>
                            <div class="controls">
                                <select id="securityWindowHours" class="form-select">
                                    <option value="6">Últimas 6h</option>
                                    <option value="24" selected>Últimas 24h</option>
                                    <option value="72">Últimas 72h</option>
                                </select>
                                <button class="btn btn-dark" id="btnAtualizarSeguranca"><i class="fas fa-sync me-2"></i>Atualizar</button>
                            </div>
                        </div>
                        <div class="section-note security-last-update">
                            <i class="fas fa-clock"></i> Ultima atualizacao: <span id="securityLastUpdate">--:--:--</span>
                            <span class="mx-2">|</span>
                            IP detectado: <span id="securityCurrentIp">--</span>
                        </div>

                        <div class="row g-4 mb-4 security-kpi-row">
                            <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">Eventos Segurança</div><div class="fs-3 fw-bold" id="secTotalEventos">0</div></div></div>
                            <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">Críticos</div><div class="fs-3 fw-bold text-danger" id="secCriticos">0</div></div></div>
                            <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">IPs Bloqueados</div><div class="fs-3 fw-bold text-warning" id="secIpsBloqueados">0</div></div></div>
                            <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">Risco Atual</div><div class="fs-4 fw-bold" id="secRiskLabel">BAIXO</div><div class="text-muted small">Score: <span id="secRiskScore">0</span>/100</div></div></div>
                        </div>

                        <div class="security-card mb-4">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Readiness 9.5+ (WAF / Pentest / DR-IR)</h6>
                                <div class="security-checklist-summary d-flex gap-2">
                                    <span class="badge bg-primary" id="secReadyStatus">PENDENTE</span>
                                    <span class="badge bg-info text-dark">Score: <span id="secReadyScore">0</span>/100</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Controle</th>
                                                <th>Ultima Execucao</th>
                                                <th style="width:120px;">Cadencia</th>
                                                <th style="width:90px;">Dias</th>
                                                <th style="width:110px;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabelaGovernanceCadencia"></tbody>
                                    </table>
                                </div>
                                <div class="small text-muted px-3 py-2">
                                    Owner: <strong id="secGovOwner">--</strong> | Atualizado em: <strong id="secGovUpdatedAt">--</strong>
                                </div>
                            </div>
                        </div>

                        <div class="security-card mb-4">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Checklist Executavel de Producao</h6>
                                <div class="security-checklist-summary d-flex gap-2">
                                    <span class="badge bg-success">OK: <span id="secCheckOk">0</span></span>
                                    <span class="badge bg-warning text-dark">Pendente: <span id="secCheckPendente">0</span></span>
                                    <span class="badge bg-danger">Critico: <span id="secCheckCritico">0</span></span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Controle</th>
                                                <th style="width: 120px;">Status</th>
                                                <th>Detalhe</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabelaSecurityChecklist"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="security-card mb-4">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Fila Offline (dispositivo atual)</h6>
                                <button class="btn btn-sm btn-outline-primary" id="btnSincronizarFilaOffline"><i class="fas fa-rotate me-1"></i>Sincronizar Agora</button>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">Pendentes</div><div class="fs-4 fw-bold text-warning" id="offlinePendingCount">0</div></div></div>
                                    <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">Sincronizadas</div><div class="fs-4 fw-bold text-success" id="offlineDoneCount">0</div></div></div>
                                    <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">Falhas</div><div class="fs-4 fw-bold text-danger" id="offlineFailedCount">0</div></div></div>
                                    <div class="col-md-3"><div class="stat-card"><div class="text-muted" style="font-size:13px;">Tentativas</div><div class="fs-4 fw-bold" id="offlineAttemptsCount">0</div></div></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    Central: restaurantes com fila <strong id="offlineCentralRestaurantes">0</strong> |
                                    dispositivos com fila <strong id="offlineCentralDispositivos">0</strong> |
                                    total de operacoes <strong id="offlineCentralTotal">0</strong>
                                </div>
                                <div class="small text-muted mt-2" id="offlineQueueStatus">Sem leitura da fila offline.</div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-lg-7">
                                <div class="security-card p-3 security-chart-wrap">
                                    <h6 class="mb-3">Ataques por Hora</h6>
                                    <canvas id="securityTimelineChart" height="110"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="security-card p-3">
                                    <h6 class="mb-3">Indicadores de Ataque</h6>
                                    <div class="security-mini-item"><span>SQL Injection</span><strong id="secSqli">0</strong></div>
                                    <div class="security-mini-item"><span>XSS</span><strong id="secXss">0</strong></div>
                                    <div class="security-mini-item"><span>Brute Force</span><strong id="secBrute">0</strong></div>
                                    <div class="security-mini-item"><span>Bots</span><strong id="secBots">0</strong></div>
                                    <div class="security-mini-item"><span>Path Traversal</span><strong id="secTraversal">0</strong></div>
                                    <div class="small text-muted mt-2">SIEM logs: <code>security.log</code> e <code>security_alerts.log</code></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="security-card">
                                    <div class="card-header bg-white py-3"><h6 class="mb-0">IPs Suspeitos e Bloqueio</h6></div>
                                    <div class="card-body">
                                        <div class="input-group mb-3 flex-wrap">
                                            <input id="securityIpManual" type="text" class="form-control" placeholder="IP ex: 192.168.0.10">
                                            <select id="securityTipoBloqueio" class="form-select" style="max-width:170px;">
                                                <option value="TEMPORARIO">Temporário</option>
                                                <option value="PERMANENTE">Permanente</option>
                                            </select>
                                            <button class="btn btn-danger" id="btnBloquearIpManual">Bloquear</button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead><tr><th>IP</th><th>Ataques</th><th></th></tr></thead>
                                                <tbody id="tabelaTopIps"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="security-card">
                                    <div class="card-header bg-white py-3"><h6 class="mb-0">Blacklist Ativa</h6></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead><tr><th>IP</th><th>Tipo</th><th>Origem</th><th></th></tr></thead>
                                                <tbody id="tabelaBlockedIps"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="security-card mt-4">
                            <div class="card-header bg-white py-3"><h6 class="mb-0">Eventos Recentes (tempo real por atualização)</h6></div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light"><tr><th>ID</th><th>Quando</th><th>Severidade</th><th>Tipo</th><th>IP</th><th>Endpoint</th></tr></thead>
                                        <tbody id="tabelaSecurityEvents"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <!-- Modal Aprovar Compra -->
    <div class="modal fade" id="modalAprovarCompra" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Aprovar Pagamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="compra_id_aprovar">
                    <div id="alertAprovarModal" class="alert" style="display:none;"></div>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Você está aprovando</strong> um pagamento de plano. O plano será ativado automaticamente.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observação (opcional)</label>
                        <textarea class="form-control" id="obs_aprovar" rows="3" placeholder="Ex: Pagamento confirmado via M-Pesa, referência #123456"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnConfirmarAprovacao" onclick="confirmarAprovacao()">
                        <i class="fas fa-check me-1"></i> Confirmar Aprovação
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Rejeitar Compra -->
    <div class="modal fade" id="modalRejeitarCompra" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Rejeitar Pagamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="compra_id_rejeitar">
                    <div id="alertRejeitarModal" class="alert" style="display:none;"></div>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Você está rejeitando</strong> uma compra de plano. O restaurante será notificado.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo da rejeição *</label>
                        <textarea class="form-control is-invalid" id="obs_rejeitar" rows="3" placeholder="Ex: Pagamento não confirmado, transação falhou, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarRejeicao" onclick="confirmarRejeicao()">
                        <i class="fas fa-ban me-1"></i> Confirmar Rejeição
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Preview Comprovativo -->
    <div class="modal fade" id="modalComprovativo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Comprovativo de Pagamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalComprovativoBody" style="min-height: 520px;">
                    <div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin me-2"></i>Carregando comprovativo...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cadastrar Restaurante -->
    <div class="modal fade" id="modalCadastrar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Novo Restaurante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCadastrar">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do Restaurante *</label>
                                <input type="text" class="form-control" name="nome" required placeholder="Ex: Restaurante Sabor">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required placeholder="contato@restaurante.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone" placeholder="+258 84 000 0000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" name="cidade" placeholder="Maputo">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" name="endereco" placeholder="Av. Principal, 123">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NUIT</label>
                                <input type="text" class="form-control" name="nuit" placeholder="400000000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Plano *</label>
                                <select class="form-select" name="plano" required>
                                    <option value="BASICO">Básico (Grátis)</option>
                                    <option value="PROFISSIONAL">Profissional (1.999 MZN/mês)</option>
                                    <option value="EMPRESARIAL">Empresarial (3.999 MZN/mês)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Acesso do Admin</label>
                                <div class="alert alert-info mb-0">
                                    O administrador sera criado inativo. A senha sera definida depois por link seguro enviado no momento da ativacao.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nome do Administrador</label>
                                <input type="text" class="form-control" name="nome_admin" value="Administrador" placeholder="Nome do administrador">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnCadastrarRestaurante">
                        <i class="fas fa-save me-1"></i> Cadastrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Restaurante -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Restaurante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditar">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do Restaurante *</label>
                                <input type="text" class="form-control" name="nome" id="edit_nome" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone" id="edit_telefone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" name="cidade" id="edit_cidade">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" name="endereco" id="edit_endereco">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NUIT</label>
                                <input type="text" class="form-control" name="nuit" id="edit_nuit">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Plano *</label>
                                <select class="form-select" name="plano" id="edit_plano" required>
                                    <option value="BASICO">Básico</option>
                                    <option value="PROFISSIONAL">Profissional</option>
                                    <option value="EMPRESARIAL">Empresarial</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" id="edit_status" required>
                                    <?php foreach ($restaurante_status_suportados as $statusOption): ?>
                                        <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($restaurante_status_labels[$statusOption] ?? ucfirst(strtolower($statusOption)), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="edit_status_help"></div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarEdicao">
                        <i class="fas fa-save me-1"></i> Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cadastrar Usuário -->
    <div class="modal fade" id="modalCadastrarUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCadastrarUsuario">
                        <input type="hidden" id="usuario_restaurante_id" name="restaurante_id">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" name="nome" required placeholder="Nome do usuário">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required placeholder="email@exemplo.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="text" class="form-control" name="senha" value="usuario123" placeholder="Senha padrão">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Perfil *</label>
                            <select class="form-select" name="perfil" required>
                                <option value="ADMIN">Administrador</option>
                                <option value="OPERADOR">Operador</option>
                                <option value="COZINHA">Cozinha</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnCadastrarUsuario">
                        <i class="fas fa-save me-1"></i> Cadastrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ver Detalhes -->
    <div class="modal fade" id="modalVer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalhes do Restaurante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalhesRestaurante">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/offline_sync.js"></script>
    <script>
        const CSRF_TOKEN = <?php echo json_encode($csrf_token, JSON_UNESCAPED_UNICODE); ?>;
        const SUPER_ADMIN_PERMISSIONS = <?php echo json_encode($super_admin_permissions, JSON_UNESCAPED_UNICODE); ?>;
        const SUPPORTED_RESTAURANT_STATUSES = <?php echo json_encode(array_values($restaurante_status_suportados), JSON_UNESCAPED_UNICODE); ?>;
        const RESTAURANT_STATUS_LABELS = <?php echo json_encode($restaurante_status_labels, JSON_UNESCAPED_UNICODE); ?>;
        let secaoAtual = <?php echo json_encode($default_secao, JSON_UNESCAPED_UNICODE); ?>;
        let securityAutoRefreshHandle = null;
        const PAGE_SIZE = 10;
        const tableState = {
            restaurantes: {
                all: [],
                filtered: [],
                page: 1,
                pageSize: PAGE_SIZE,
                sortKey: 'id',
                sortDir: 'desc'
            },
            usuarios: {
                all: [],
                filtered: [],
                page: 1,
                pageSize: PAGE_SIZE,
                sortKey: 'id',
                sortDir: 'desc'
            },
            compras: {
                all: [],
                filtered: [],
                page: 1,
                pageSize: PAGE_SIZE,
                sortKey: 'id',
                sortDir: 'desc'
            }
        };
        let securityTimelineChart = null;

        function getRestaurantDisplayStatus(restaurante) {
            const rawDisplay = restaurante && restaurante.status_exibicao !== undefined ?
                restaurante.status_exibicao :
                (restaurante && Number(restaurante.possui_compra_pendente) === 1 ? 'PENDENTE' : (restaurante ? restaurante.status : ''));
            const normalized = String(rawDisplay || '').toUpperCase().trim();
            if (normalized) {
                return normalized;
            }

            const rawStatus = String(restaurante?.status || '').toUpperCase().trim();
            return rawStatus || 'SEM_STATUS';
        }

        function normalizePurchaseStatus(status) {
            const normalized = String(status || '').toUpperCase().trim();
            return normalized === 'PENDENTE' ? 'AGUARDANDO_APROVACAO' : normalized;
        }

        function getPurchaseStatusLabel(status) {
            const key = normalizePurchaseStatus(status);
            if (key === 'AGUARDANDO_APROVACAO') return 'Aguardando Aprovação';
            if (key === 'APROVADO') return 'Aprovado';
            if (key === 'REJEITADO') return 'Rejeitado';
            return key || 'Sem status';
        }

        function getRestaurantRawStatus(restaurante) {
            return String(restaurante?.status || '').toUpperCase().trim();
        }

        function getRestaurantStatusLabel(status) {
            const key = String(status || '').toUpperCase();
            return RESTAURANT_STATUS_LABELS[key] || (key.charAt(0) + key.slice(1).toLowerCase());
        }

        function getRestaurantStatusBadgeClass(status) {
            const key = String(status || '').toUpperCase();
            if (key === 'ATIVO') return 'success';
            if (key === 'PENDENTE') return 'warning';
            if (key === 'BLOQUEADO') return 'warning';
            if (key === 'CANCELADO') return 'danger';
            return 'secondary';
        }

        function getRestaurantPlanCode(restaurante) {
            const plan = String(restaurante?.plano || 'BASICO').toUpperCase();
            return plan === 'ENTERPRISE' ? 'EMPRESARIAL' : plan;
        }

        function paginate(items, page, pageSize = PAGE_SIZE) {
            const safeTotal = Math.max(1, Math.ceil(items.length / pageSize));
            const safePage = Math.min(Math.max(1, page), safeTotal);
            const start = (safePage - 1) * pageSize;
            return {
                page: safePage,
                totalPages: safeTotal,
                items: items.slice(start, start + pageSize)
            };
        }

        function compareValues(a, b) {
            const aNum = Number(a);
            const bNum = Number(b);
            const aIsNum = Number.isFinite(aNum);
            const bIsNum = Number.isFinite(bNum);
            if (aIsNum && bIsNum) {
                return aNum - bNum;
            }

            const aDate = Date.parse(a);
            const bDate = Date.parse(b);
            const aIsDate = Number.isFinite(aDate);
            const bIsDate = Number.isFinite(bDate);
            if (aIsDate && bIsDate) {
                return aDate - bDate;
            }

            return String(a || '').localeCompare(String(b || ''), 'pt', {
                sensitivity: 'base'
            });
        }

        function applySorting(items, state) {
            const list = [...items];
            const dir = state.sortDir === 'asc' ? 1 : -1;
            const key = state.sortKey;
            list.sort((x, y) => compareValues(x?.[key], y?.[key]) * dir);
            return list;
        }

        function updateUrlState() {
            const params = new URLSearchParams();
            params.set('secao', secaoAtual);

            const map = {
                restaurantes: 'r',
                usuarios: 'u',
                compras: 'c',
                seguranca: 's'
            };

            const activeTable = secaoAtual === 'planos' ? 'compras' : secaoAtual;
            const prefix = map[activeTable];
            const state = tableState[activeTable];
            const filterEl = document.getElementById(
                activeTable === 'restaurantes' ? 'filtroRestaurantes' : (activeTable === 'usuarios' ? 'filtroUsuarios' : 'filtroCompras')
            );

            if (activeTable === 'seguranca') {
                params.set('sw', String(document.getElementById('securityWindowHours')?.value || '24'));
            } else if (prefix && state) {
                params.set(prefix + 'p', String(state.page));
                params.set(prefix + 's', String(state.pageSize));
                params.set(prefix + 'k', String(state.sortKey));
                params.set(prefix + 'd', String(state.sortDir));

                const filtroAtual = (filterEl ? filterEl.value : '').trim();
                if (filtroAtual !== '') {
                    params.set(prefix + 'q', filtroAtual);
                }
            }

            const url = `${window.location.pathname}?${params.toString()}`;
            window.history.replaceState(null, '', url);
        }

        function restoreStateFromUrl() {
            const params = new URLSearchParams(window.location.search);
            const secao = params.get('secao');
            if (secao && ['restaurantes', 'usuarios', 'planos', 'seguranca'].includes(secao)) {
                secaoAtual = secao;
            }

            const map = {
                restaurantes: 'r',
                usuarios: 'u',
                compras: 'c'
            };

            Object.keys(map).forEach(key => {
                const prefix = map[key];
                const state = tableState[key];
                state.page = Math.max(1, parseInt(params.get(prefix + 'p') || '1', 10) || 1);
                const restoredPageSize = parseInt(params.get(prefix + 's') || '10', 10);
                state.pageSize = [10, 25, 50].includes(restoredPageSize) ? restoredPageSize : 10;
                state.sortKey = params.get(prefix + 'k') || state.sortKey;
                if (key === 'restaurantes' && state.sortKey === 'status') {
                    state.sortKey = 'status_exibicao';
                }
                state.sortDir = params.get(prefix + 'd') === 'asc' ? 'asc' : 'desc';

                const filterValue = params.get(prefix + 'q') || '';
                const filterEl = document.getElementById(
                    key === 'restaurantes' ? 'filtroRestaurantes' : (key === 'usuarios' ? 'filtroUsuarios' : 'filtroCompras')
                );
                if (filterEl) {
                    filterEl.value = filterValue;
                }

                const sizeEl = document.getElementById(
                    key === 'restaurantes' ? 'pageSizeRestaurantes' : (key === 'usuarios' ? 'pageSizeUsuarios' : 'pageSizeCompras')
                );
                if (sizeEl) {
                    sizeEl.value = String(state.pageSize);
                }
            });

            const sw = params.get('sw');
            if (sw && document.getElementById('securityWindowHours')) {
                document.getElementById('securityWindowHours').value = sw;
            }
        }

        function setSort(table, sortKey) {
            const state = tableState[table];
            if (!state) {
                return;
            }

            if (state.sortKey === sortKey) {
                state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                state.sortKey = sortKey;
                state.sortDir = 'asc';
            }
            state.page = 1;

            if (table === 'restaurantes') {
                renderRestaurantes();
            } else if (table === 'usuarios') {
                renderUsuarios();
            } else if (table === 'compras') {
                renderCompras();
            }
            updateUrlState();
        }

        function normalizeText(value) {
            return String(value || '').toLowerCase();
        }

        function hasPermission(permission) {
            return Boolean(SUPER_ADMIN_PERMISSIONS && SUPER_ADMIN_PERMISSIONS[permission]);
        }

        function canAccessPlansSection() {
            return hasPermission('view_finance') || hasPermission('approve_plans');
        }

        function canAccessSecuritySection() {
            return hasPermission('manage_security');
        }

        function escapeHtml(value) {
            const str = String(value ?? '');
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function apiFetch(url, options = {}) {
            const method = String(options.method || 'GET').toUpperCase();
            const headers = new Headers(options.headers || {});
            if (method !== 'GET') {
                headers.set('X-CSRF-Token', CSRF_TOKEN);
            }

            return fetch(url, {
                ...options,
                headers,
                credentials: 'same-origin'
            });
        }

        async function parseJsonSafe(response) {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                const compact = String(text || '').replace(/\s+/g, ' ').trim();
                throw new Error(compact ? compact.slice(0, 220) : ('Resposta invalida do servidor (HTTP ' + response.status + ')'));
            }
        }

        function buildCsrfFormBody(data) {
            const params = new URLSearchParams();
            Object.entries(data || {}).forEach(([key, value]) => {
                params.append(key, value == null ? '' : String(value));
            });
            params.append('_csrf', CSRF_TOKEN);
            return params.toString();
        }

        // Função para mostrar seção
        function mostrarSecao(secao, triggerElement = null) {
            const secaoPermitida = (secao === 'restaurantes' && hasPermission('manage_restaurants')) ||
                (secao === 'usuarios' && hasPermission('manage_users')) ||
                (secao === 'planos' && canAccessPlansSection()) ||
                (secao === 'seguranca' && canAccessSecuritySection());

            if (!secaoPermitida) {
                showAlert('Sem permissão para acessar esta área.', 'warning');
                return;
            }

            secaoAtual = secao;

            // Atualizar menu
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            if (triggerElement) {
                triggerElement.classList.add('active');
            }

            // Mostrar seção correta
            document.querySelectorAll('.secao').forEach(s => s.classList.remove('ativa'));
            document.getElementById('secao-' + secao).classList.add('ativa');

            // Carregar dados conforme seção
            if (secao === 'restaurantes') {
                carregarRestaurantes();
            } else if (secao === 'usuarios') {
                carregarRestaurantesParaUsuario();
            } else if (secao === 'planos') {
                carregarCompras();
            } else if (secao === 'seguranca') {
                carregarPainelSeguranca();
            }

            controlarAutoRefreshSeguranca();
            updateUrlState();
        }

        // Função para mostrar alerta
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const safeMessage = escapeHtml(message);
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${safeMessage}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        function showAlertHtml(html, type = 'success', timeoutMs = 5000) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${html}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            if (timeoutMs > 0) {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, timeoutMs);
            }
        }

        function isLocalEnvironment() {
            const host = String(window.location.hostname || '').toLowerCase();
            return host === 'localhost' || host === '127.0.0.1';
        }

        function isMobileViewport() {
            return window.matchMedia('(max-width: 991px)').matches;
        }

        function setSidebarOpen(open) {
            const sidebar = document.querySelector('.sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const btn = document.getElementById('btnMenuToggle');
            const icon = btn ? btn.querySelector('i') : null;
            if (!sidebar) return;

            if (isMobileViewport()) {
                sidebar.classList.toggle('sidebar-hidden', !open);
                if (backdrop) backdrop.classList.toggle('show', !!open);
                document.body.classList.remove('layout-sidebar-collapsed');
                if (btn) btn.classList.remove('is-docked');
                if (icon) {
                    icon.classList.remove('fa-bars');
                    icon.classList.remove('fa-times');
                    icon.classList.add(open ? 'fa-times' : 'fa-bars');
                }
            } else {
                sidebar.classList.toggle('sidebar-hidden', !open);
                document.body.classList.toggle('layout-sidebar-collapsed', !open);
                if (backdrop) backdrop.classList.remove('show');
                if (btn) btn.classList.toggle('is-docked', !!open);
                if (icon) {
                    icon.classList.remove('fa-bars');
                    icon.classList.remove('fa-times');
                    icon.classList.add(open ? 'fa-times' : 'fa-bars');
                }
            }
        }

        // ==================== RESTAURANTES ====================

        // Carregar restaurantes ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof OfflineSync !== 'undefined' && OfflineSync.init) {
                OfflineSync.init({
                    csrfToken: CSRF_TOKEN,
                    syncIntervalMs: 15000
                });
            }
            restoreStateFromUrl();

            const secaoPermitida = (secaoAtual === 'restaurantes' && hasPermission('manage_restaurants')) ||
                (secaoAtual === 'usuarios' && hasPermission('manage_users')) ||
                (secaoAtual === 'planos' && canAccessPlansSection()) ||
                (secaoAtual === 'seguranca' && canAccessSecuritySection());
            if (!secaoPermitida) {
                secaoAtual = <?php echo json_encode($default_secao, JSON_UNESCAPED_UNICODE); ?>;
            }

            document.querySelectorAll('.js-secao-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const secao = this.dataset.secao;
                    if (secao) {
                        mostrarSecao(secao, this);
                        if (isMobileViewport()) {
                            setSidebarOpen(false);
                        }
                    }
                });
            });

            const btnMenuToggle = document.getElementById('btnMenuToggle');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const sidebar = document.querySelector('.sidebar');
            const startOpen = !isMobileViewport();
            setSidebarOpen(startOpen);

            btnMenuToggle?.addEventListener('click', () => {
                const isHidden = sidebar?.classList.contains('sidebar-hidden');
                setSidebarOpen(!!isHidden);
            });
            sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));
            window.addEventListener('resize', () => {
                if (isMobileViewport()) {
                    setSidebarOpen(false);
                } else {
                    setSidebarOpen(true);
                }
            });

            document.getElementById('restauranteSelecionadoUsuarios')?.addEventListener('change', carregarUsuarios);
            document.getElementById('btnAcessarDashboard')?.addEventListener('click', acessarDashboard);
            document.getElementById('btnAtualizarCompras')?.addEventListener('click', carregarCompras);
            document.getElementById('btnAtualizarSeguranca')?.addEventListener('click', carregarPainelSeguranca);
            document.getElementById('securityWindowHours')?.addEventListener('change', carregarPainelSeguranca);
            document.getElementById('btnBloquearIpManual')?.addEventListener('click', bloquearIpManual);
            document.getElementById('btnSincronizarFilaOffline')?.addEventListener('click', sincronizarFilaOfflineAgora);
            // Os botoes de aprovar/rejeitar usam onclick inline para evitar bind duplicado.
            document.getElementById('btnCadastrarRestaurante')?.addEventListener('click', cadastrarRestaurante);
            document.getElementById('btnSalvarEdicao')?.addEventListener('click', salvarEdicao);
            document.getElementById('btnCadastrarUsuario')?.addEventListener('click', cadastrarUsuario);
            document.getElementById('filtroRestaurantes')?.addEventListener('input', aplicarFiltroRestaurantes);
            document.getElementById('filtroUsuarios')?.addEventListener('input', aplicarFiltroUsuarios);
            document.getElementById('filtroCompras')?.addEventListener('input', aplicarFiltroCompras);
            document.getElementById('filtroPlanoCompras')?.addEventListener('change', () => aplicarFiltroCompras());
            document.getElementById('filtroCicloCompras')?.addEventListener('change', () => aplicarFiltroCompras());
            document.getElementById('filtroStatusCompras')?.addEventListener('change', () => aplicarFiltroCompras());
            document.getElementById('filtroDataIniCompras')?.addEventListener('change', () => aplicarFiltroCompras());
            document.getElementById('filtroDataFimCompras')?.addEventListener('change', () => aplicarFiltroCompras());
            document.getElementById('btnRestaurantesPrev')?.addEventListener('click', () => mudarPagina('restaurantes', -1));
            document.getElementById('btnRestaurantesNext')?.addEventListener('click', () => mudarPagina('restaurantes', 1));
            document.getElementById('btnUsuariosPrev')?.addEventListener('click', () => mudarPagina('usuarios', -1));
            document.getElementById('btnUsuariosNext')?.addEventListener('click', () => mudarPagina('usuarios', 1));
            document.getElementById('btnComprasPrev')?.addEventListener('click', () => mudarPagina('compras', -1));
            document.getElementById('btnComprasNext')?.addEventListener('click', () => mudarPagina('compras', 1));
            document.getElementById('pageSizeRestaurantes')?.addEventListener('change', (e) => {
                tableState.restaurantes.pageSize = parseInt(e.target.value, 10) || PAGE_SIZE;
                tableState.restaurantes.page = 1;
                renderRestaurantes();
                updateUrlState();
            });
            document.getElementById('pageSizeUsuarios')?.addEventListener('change', (e) => {
                tableState.usuarios.pageSize = parseInt(e.target.value, 10) || PAGE_SIZE;
                tableState.usuarios.page = 1;
                renderUsuarios();
                updateUrlState();
            });
            document.getElementById('pageSizeCompras')?.addEventListener('change', (e) => {
                tableState.compras.pageSize = parseInt(e.target.value, 10) || PAGE_SIZE;
                tableState.compras.page = 1;
                renderCompras();
                updateUrlState();
            });

            document.querySelectorAll('.js-sort').forEach(btn => {
                btn.addEventListener('click', () => {
                    const table = btn.dataset.table;
                    const sort = btn.dataset.sort;
                    if (table && sort) {
                        setSort(table, sort);
                    }
                });
            });

            document.getElementById('tabelaRestaurantes')?.addEventListener('click', function(e) {
                const button = e.target.closest('.btn-restaurante-action');
                if (!button) {
                    return;
                }

                const id = Number(button.dataset.id || 0);
                const action = button.dataset.action || '';

                if (action === 'ver') {
                    verDetalhes(id);
                } else if (action === 'editar') {
                    editarRestaurante(id);
                } else if (action === 'deletar') {
                    deletarRestaurante(id, button.dataset.nome || '');
                }
            });

            document.getElementById('tabelaUsuarios')?.addEventListener('click', function(e) {
                const turnoBtn = e.target.closest('.btn-turno-action');
                if (turnoBtn) {
                    const id = Number(turnoBtn.dataset.id || 0);
                    const nome = turnoBtn.dataset.nome || '';
                    const action = turnoBtn.dataset.action || '';
                    const restauranteId = document.getElementById('restauranteSelecionadoUsuarios')?.value;
                    if (!restauranteId) {
                        showAlert('Selecione um restaurante primeiro!', 'warning');
                        return;
                    }
                    if (!id) return;
                    const motivo = prompt('Motivo da intervenção no turno de ' + nome + ':');
                    if (!motivo) return;
                    const payload = {
                        acao: action === 'abrir_turno' ? 'abrir_manual' : 'fechar_manual',
                        funcionario_id: id,
                        restaurante_id: Number(restauranteId),
                        motivo: motivo
                    };
                    apiFetch('api/super_admin_turno_operacao.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showAlert(data.message || 'Operação de turno realizada.', 'success');
                                carregarUsuarios();
                            } else {
                                showAlert(data.message || 'Falha ao operar turno.', 'danger');
                            }
                        })
                        .catch(() => showAlert('Falha ao operar turno.', 'danger'));
                    return;
                }

                const button = e.target.closest('.btn-usuario-action');
                if (!button) {
                    return;
                }

                const id = Number(button.dataset.id || 0);
                const nome = button.dataset.nome || '';
                if ((button.dataset.action || '') === 'deletar') {
                    deletarUsuario(id, nome);
                }
            });

            document.getElementById('tabelaCompras')?.addEventListener('click', function(e) {
                const button = e.target.closest('.btn-compra-action');
                const buttonComprovativo = e.target.closest('.btn-compra-comprovativo');

                if (buttonComprovativo) {
                    const path = buttonComprovativo.dataset.path || '';
                    abrirComprovativo(path);
                    return;
                }

                if (!button) {
                    return;
                }

                const id = Number(button.dataset.id || 0);
                const action = button.dataset.action || '';
                if (action === 'aprovar') {
                    aprovarCompra(id);
                } else if (action === 'rejeitar') {
                    rejeitarCompra(id);
                }
            });

            document.getElementById('tabelaTopIps')?.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-block-ip');
                if (!btn) return;
                executarAcaoIp('BLOCK', btn.dataset.ip || '', 'TEMPORARIO');
            });

            document.getElementById('tabelaBlockedIps')?.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-unblock-ip');
                if (!btn) return;
                executarAcaoIp('UNBLOCK', btn.dataset.ip || '');
            });

            const secaoLinkInicial = document.querySelector(`.js-secao-link[data-secao="${secaoAtual}"]`);
            if (secaoLinkInicial) {
                mostrarSecao(secaoAtual, secaoLinkInicial);
            }
            carregarPainelFilaOffline();
            carregarEstatisticasGerais();
        });

        function mudarPagina(tipo, delta) {
            const state = tableState[tipo];
            if (!state) {
                return;
            }

            const maxPage = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));
            state.page = Math.min(maxPage, Math.max(1, state.page + delta));
            if (tipo === 'restaurantes') {
                renderRestaurantes();
            } else if (tipo === 'usuarios') {
                renderUsuarios();
            } else if (tipo === 'compras') {
                renderCompras();
            }
            updateUrlState();
        }

        // Carregar estatísticas gerais
        function carregarEstatisticasGerais() {
            if (!hasPermission('view_dashboard')) {
                return;
            }

            fetch('api/super_admin_estatisticas.php', {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('statTotalRestaurantes').textContent = data.data.total_restaurantes;
                        document.getElementById('statRestaurantesAtivos').textContent = data.data.restaurantes_ativos;
                        document.getElementById('statAssinaturasExpirando').textContent = data.data.assinaturas_expirando;
                    }
                })
                .catch(err => console.error('Erro ao carregar estatísticas:', err));
        }

        // Função para acessar dashboard do restaurante selecionado
        function acessarDashboard() {
            if (!hasPermission('view_dashboard')) {
                showAlert('Sem permissão para acessar o dashboard.', 'warning');
                return;
            }

            const select = document.getElementById('restauranteSelecionado');
            const restauranteId = select.value;

            if (!restauranteId) {
                showAlert('Selecione um restaurante primeiro!', 'warning');
                return;
            }

            apiFetch('api/selecionar_restaurante.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'restaurante_id=' + restauranteId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'dashboard.php';
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro: ' + err.message, 'danger');
                });
        }

        // Função para popular seletor de restaurantes
        function popularSeletor(restaurantes) {
            const select = document.getElementById('restauranteSelecionado');
            let html = '<option value="">Selecione um restaurante...</option>';
            restaurantes.forEach(r => {
                html += `<option value="${Number(r.id) || 0}">${escapeHtml(r.nome)}</option>`;
            });
            select.innerHTML = html;
        }

        // Carregar lista de restaurantes
        function carregarRestaurantes() {
            if (!hasPermission('manage_restaurants')) {
                return;
            }

            const basePath = window.location.pathname.replace(/\/[^/]*$/, '/');
            const candidates = [
                'api/restaurante_listar.php',
                basePath + 'api/restaurante_listar.php',
                '/V00/src/public/api/restaurante_listar.php'
            ];

            const tentar = (idx = 0) => {
                if (idx >= candidates.length) {
                    throw new Error('Falha de conexao com API de restaurantes.');
                }
                return apiFetch(candidates[idx])
                    .then(response => parseJsonSafe(response))
                    .catch(err => {
                        if (/Failed to fetch|NetworkError|conexao/i.test(String(err && err.message || ''))) {
                            return tentar(idx + 1);
                        }
                        throw err;
                    });
            };

            Promise.resolve()
                .then(() => tentar(0))
                .then(data => {
                    if (data.success) {
                        tableState.restaurantes.all = Array.isArray(data.data) ? data.data : [];
                        aplicarFiltroRestaurantes(false);
                        atualizarEstatisticas(tableState.restaurantes.filtered);
                        popularSeletor(data.data);
                    } else {
                        showAlert(data.message || 'Falha ao carregar restaurantes.', 'danger');
                    }
                })
                .catch(err => {
                    console.error('Erro:', err);
                    showAlert('Erro ao carregar restaurantes: ' + err.message, 'danger');
                });
        }

        function aplicarFiltroRestaurantes(resetPage = true) {
            const query = normalizeText(document.getElementById('filtroRestaurantes').value);
            tableState.restaurantes.filtered = tableState.restaurantes.all.filter(r => {
                if (!query) {
                    return true;
                }

                const hay = [r.nome, r.email, r.telefone, getRestaurantDisplayStatus(r), r.status, getRestaurantPlanCode(r)].map(normalizeText).join(' ');
                return hay.includes(query);
            });
            if (resetPage) {
                tableState.restaurantes.page = 1;
            }
            atualizarEstatisticas(tableState.restaurantes.filtered);
            renderRestaurantes();
            updateUrlState();
        }

        function renderRestaurantes() {
            const state = tableState.restaurantes;
            const ordered = applySorting(state.filtered, state);
            const paged = paginate(ordered, state.page, state.pageSize);
            state.page = paged.page;
            atualizarTabela(paged.items);
            document.getElementById('paginacaoRestaurantesInfo').textContent = `${paged.page}/${paged.totalPages}`;
            document.getElementById('btnRestaurantesPrev').disabled = paged.page <= 1;
            document.getElementById('btnRestaurantesNext').disabled = paged.page >= paged.totalPages;
        }

        // Atualizar tabela
        function atualizarTabela(restaurantes) {
            const tbody = document.getElementById('tabelaRestaurantes');

            if (restaurantes.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-inbox me-2"></i>Nenhum restaurante cadastrado</td></tr>`;
                return;
            }

            let html = '';
            restaurantes.forEach(r => {
                const id = Number(r.id) || 0;
                const nome = escapeHtml(r.nome);
                const email = escapeHtml(r.email || '-');
                const telefone = escapeHtml(r.telefone || '-');
                const status = getRestaurantDisplayStatus(r);
                const statusAttr = escapeHtml(status);
                const nomeAttr = escapeHtml(String(r.nome || ''));
                const planoAtual = getRestaurantPlanCode(r);

                let badgePlano = planoAtual === 'EMPRESARIAL' ? '<span class="badge bg-warning badge-plano">Empresarial</span>' :
                    planoAtual === 'PROFISSIONAL' ? '<span class="badge bg-info badge-plano">Profissional</span>' :
                    '<span class="badge bg-secondary badge-plano">Básico</span>';

                let badgeStatus = `<span class="badge bg-${getRestaurantStatusBadgeClass(status)} badge-status">${escapeHtml(getRestaurantStatusLabel(status))}</span>`;

                const dataValidade = r.data_fim ? new Date(r.data_fim) : null;
                const hoje = new Date();
                let validadeTexto = '-';
                let validadeClasse = 'warning';

                if (dataValidade instanceof Date && !Number.isNaN(dataValidade.getTime())) {
                    const diasRestantes = Math.ceil((dataValidade - hoje) / (1000 * 60 * 60 * 24));
                    if (diasRestantes < 0) {
                        validadeTexto = 'Expirado';
                        validadeClasse = 'danger';
                    } else if (diasRestantes <= 30) {
                        validadeTexto = diasRestantes + ' dias';
                        validadeClasse = 'warning';
                    } else {
                        validadeTexto = diasRestantes + ' dias';
                        validadeClasse = 'success';
                    }
                }

                html += `<tr>
                    <td class="ps-4"><span class="table-id">#${id}</span></td>
                    <td>
                        <div class="table-identity">
                            <strong>${nome}</strong>
                            <span class="table-subtext">${planoAtual === 'EMPRESARIAL' ? 'Conta premium' : (planoAtual === 'PROFISSIONAL' ? 'Plano profissional' : 'Plano básico')}</span>
                        </div>
                    </td>
                    <td>${email}</td>
                    <td>${telefone}</td>
                    <td>${badgePlano}</td>
                    <td>${badgeStatus}</td>
                    <td><span class="validity-badge ${validadeClasse}"><i class="fas fa-calendar-alt"></i>${escapeHtml(validadeTexto)}</span></td>
                    <td class="text-center">
                        <div class="action-group">
                            <button class="btn btn-sm btn-outline-info btn-restaurante-action" data-action="ver" data-id="${id}" title="Ver detalhes"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-outline-primary btn-restaurante-action" data-action="editar" data-id="${id}" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger btn-restaurante-action" data-action="deletar" data-id="${id}" data-nome="${nomeAttr}" data-status="${statusAttr}" title="Excluir"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            });

            tbody.innerHTML = html;
        }

        // Atualizar estatísticas
        function atualizarEstatisticas(restaurantes) {
            const hoje = new Date();
            const expirando = restaurantes.filter(r => {
                if (!r.data_fim) {
                    return false;
                }

                const dataValidade = new Date(r.data_fim);
                if (Number.isNaN(dataValidade.getTime())) {
                    return false;
                }

                const diasRestantes = Math.ceil((dataValidade - hoje) / (1000 * 60 * 60 * 24));
                return diasRestantes >= 0 && diasRestantes <= 30;
            }).length;

            document.getElementById('listaTotalRestaurantes').textContent = restaurantes.length;
            document.getElementById('listaAtivos').textContent = restaurantes.filter(r => getRestaurantDisplayStatus(r) === 'ATIVO').length;
            document.getElementById('listaExpirando').textContent = expirando;
            document.getElementById('listaBloqueados').textContent = restaurantes.filter(r => getRestaurantDisplayStatus(r) === 'BLOQUEADO').length;
            document.getElementById('listaEnterprise').textContent = restaurantes.filter(r => getRestaurantPlanCode(r) === 'EMPRESARIAL').length;
        }

        // Cadastrar restaurante
        function cadastrarRestaurante() {
            if (!hasPermission('manage_restaurants')) {
                showAlert('Sem permissão para cadastrar restaurantes.', 'warning');
                return;
            }

            const form = document.getElementById('formCadastrar');
            const formData = new FormData(form);

            apiFetch('api/restaurante_cadastrar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalCadastrar')).hide();
                        form.reset();
                        carregarRestaurantes();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro ao cadastrar: ' + err.message, 'danger');
                });
        }

        // Ver detalhes
        function verDetalhes(id) {
            if (!hasPermission('manage_restaurants')) {
                showAlert('Sem permissão para visualizar detalhes.', 'warning');
                return;
            }

            const modal = new bootstrap.Modal(document.getElementById('modalVer'));
            const container = document.getElementById('detalhesRestaurante');

            container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
            modal.show();

            fetch('api/restaurante_buscar.php?id=' + id, {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const r = data.data;
                        const nome = escapeHtml(r.nome);
                        const email = escapeHtml(r.email || '-');
                        const telefone = escapeHtml(r.telefone || '-');
                        const cidade = escapeHtml(r.cidade || '-');
                        const nuit = escapeHtml(r.nuit || '-');
                        const planoAtual = getRestaurantPlanCode(r);
                        const plano = escapeHtml(planoAtual);
                        const status = getRestaurantDisplayStatus(r);
                        const statusClass = getRestaurantStatusBadgeClass(status);
                        const validade = r.data_fim ? new Date(r.data_fim).toLocaleDateString('pt-BR') : '-';
                        const pendingNotice = Number(r.possui_compra_pendente || 0) === 1 ?
                            '<div class="alert alert-warning py-2 mt-3 mb-0"><i class="fas fa-clock me-2"></i>Este restaurante possui uma solicitacao de plano pendente. O status exibido considera essa pendencia.</div>' :
                            '';

                        container.innerHTML = `
                        <div class="text-center mb-3">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 32px; color: white;">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <h5 class="mt-3">${nome}</h5>
                            <span class="badge bg-${statusClass}">${escapeHtml(getRestaurantStatusLabel(status))}</span>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2"><small class="text-muted">Email</small><div>${email}</div></div>
                            <div class="col-6 mb-2"><small class="text-muted">Telefone</small><div>${telefone}</div></div>
                            <div class="col-6 mb-2"><small class="text-muted">Cidade</small><div>${cidade}</div></div>
                            <div class="col-6 mb-2"><small class="text-muted">NUIT</small><div>${nuit}</div></div>
                            <div class="col-6 mb-2"><small class="text-muted">Plano</small><div><span class="badge bg-${planoAtual === 'EMPRESARIAL' ? 'warning' : (planoAtual === 'PROFISSIONAL' ? 'info' : 'secondary')}">${plano}</span></div></div>
                            <div class="col-6 mb-2"><small class="text-muted">Validade</small><div>${escapeHtml(validade)}</div></div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-4"><div class="fs-4 fw-bold text-primary">${r.total_usuarios}</div><small class="text-muted">Usuários</small></div>
                            <div class="col-4"><div class="fs-4 fw-bold text-success">${r.total_produtos}</div><small class="text-muted">Produtos</small></div>
                            <div class="col-4"><div class="fs-4 fw-bold text-info">${r.total_mesas}</div><small class="text-muted">Mesas</small></div>
                        </div>
                        ${pendingNotice}`;
                    } else {
                        container.innerHTML = '<div class="text-danger text-center">Erro ao carregar detalhes</div>';
                    }
                })
                .catch(err => {
                    container.innerHTML = '<div class="text-danger text-center">Erro: ' + err.message + '</div>';
                });
        }

        // Editar restaurante
        function editarRestaurante(id) {
            if (!hasPermission('manage_restaurants')) {
                showAlert('Sem permissão para editar restaurantes.', 'warning');
                return;
            }

            fetch('api/restaurante_buscar.php?id=' + id, {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const r = data.data;
                        const rawStatus = getRestaurantRawStatus(r);
                        const displayStatus = getRestaurantDisplayStatus(r);
                        const statusSelect = document.getElementById('edit_status');
                        const statusHelp = document.getElementById('edit_status_help');

                        document.getElementById('edit_id').value = r.id;
                        document.getElementById('edit_nome').value = r.nome;
                        document.getElementById('edit_email').value = r.email;
                        document.getElementById('edit_telefone').value = r.telefone || '';
                        document.getElementById('edit_cidade').value = r.cidade || '';
                        document.getElementById('edit_endereco').value = r.endereco || '';
                        document.getElementById('edit_nuit').value = r.nuit || '';
                        document.getElementById('edit_plano').value = getRestaurantPlanCode(r);
                        statusSelect.value = SUPPORTED_RESTAURANT_STATUSES.includes(rawStatus) ?
                            rawStatus :
                            (SUPPORTED_RESTAURANT_STATUSES[0] || 'ATIVO');
                        statusHelp.textContent = Number(r.possui_compra_pendente || 0) === 1 ?
                            'Este restaurante aparece como "' + getRestaurantStatusLabel(displayStatus) + '" porque possui uma compra de plano pendente. O campo acima edita apenas o status salvo na tabela restaurantes.' :
                            'Apenas os status suportados pelo banco atual aparecem nesta lista.';
                        new bootstrap.Modal(document.getElementById('modalEditar')).show();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro ao carregar dados: ' + err.message, 'danger');
                });
        }

        // Salvar edição
        function salvarEdicao() {
            if (!hasPermission('manage_restaurants')) {
                showAlert('Sem permissão para editar restaurantes.', 'warning');
                return;
            }

            const form = document.getElementById('formEditar');
            const formData = new FormData(form);

            apiFetch('api/restaurante_editar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalEditar')).hide();
                        carregarRestaurantes();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro ao salvar: ' + err.message, 'danger');
                });
        }

        // Alterar status rapidamente (aprovar/rejeitar)
        function mudarStatus(id, novoStatus) {
            if (!hasPermission('manage_restaurants')) {
                showAlert('Sem permissão para alterar status.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', novoStatus);

            apiFetch('api/restaurante_editar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Status alterado com sucesso', 'success');
                        carregarRestaurantes();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro ao alterar status: ' + err.message, 'danger');
                });
        }

        // Deletar restaurante
        function deletarRestaurante(id, nome) {
            if (!hasPermission('manage_restaurants')) {
                showAlert('Sem permissão para excluir restaurantes.', 'warning');
                return;
            }

            if (!confirm(`Tem certeza que deseja excluir o restaurante "${nome}"? Esta ação não pode ser desfeita.`)) {
                return;
            }

            apiFetch('api/restaurante_deletar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        carregarRestaurantes();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro ao deletar: ' + err.message, 'danger');
                });
        }

        // ==================== USUÁRIOS ====================

        // Carregar restaurantes para seletor de usuários
        function carregarRestaurantesParaUsuario() {
            if (!hasPermission('manage_users')) {
                return;
            }

            fetch('api/restaurante_listar.php', {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('restauranteSelecionadoUsuarios');
                        let html = '<option value="">Selecione um restaurante...</option>';
                        data.data.forEach(r => {
                            html += `<option value="${Number(r.id) || 0}">${escapeHtml(r.nome)}</option>`;
                        });
                        select.innerHTML = html;
                    }
                })
                .catch(err => console.error('Erro:', err));
        }

        // Carregar usuários
        function carregarUsuarios() {
            if (!hasPermission('manage_users')) {
                return;
            }

            const restauranteId = document.getElementById('restauranteSelecionadoUsuarios').value;
            const tbody = document.getElementById('tabelaUsuarios');

            if (!restauranteId) {
                tableState.usuarios.all = [];
                tableState.usuarios.filtered = [];
                tableState.usuarios.page = 1;
                document.getElementById('paginacaoUsuariosInfo').textContent = '1/1';
                document.getElementById('btnUsuariosPrev').disabled = true;
                document.getElementById('btnUsuariosNext').disabled = true;
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Selecione um restaurante para ver os usuários</td></tr>';
                return;
            }

            // Definir ID no form oculto
            document.getElementById('usuario_restaurante_id').value = restauranteId;

            fetch('api/super_admin_usuarios_listar.php?restaurante_id=' + restauranteId, {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        tableState.usuarios.all = Array.isArray(data.data) ? data.data : [];
                        aplicarFiltroUsuarios(false);
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    console.error('Erro:', err);
                    showAlert('Erro ao carregar usuários', 'danger');
                });
        }

        function aplicarFiltroUsuarios(resetPage = true) {
            const query = normalizeText(document.getElementById('filtroUsuarios').value);
            tableState.usuarios.filtered = tableState.usuarios.all.filter(u => {
                if (!query) {
                    return true;
                }

                const hay = [u.nome, u.email, u.perfil, u.ativo ? 'ativo' : 'inativo'].map(normalizeText).join(' ');
                return hay.includes(query);
            });
            if (resetPage) {
                tableState.usuarios.page = 1;
            }
            renderUsuarios();
            updateUrlState();
        }

        function renderUsuarios() {
            const tbody = document.getElementById('tabelaUsuarios');
            const state = tableState.usuarios;
            const ordered = applySorting(state.filtered, state);
            const paged = paginate(ordered, state.page, state.pageSize);
            state.page = paged.page;

            if (paged.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nenhum usuário encontrado</td></tr>';
            } else {
                let html = '';
                paged.items.forEach(u => {
                    const userId = Number(u.id) || 0;
                    const nome = escapeHtml(u.nome);
                    const email = escapeHtml(u.email);
                    const nomeAttr = escapeHtml(String(u.nome || ''));
                    const perfilBadge = u.perfil === 'ADMIN' ? '<span class="badge bg-primary">Admin</span>' :
                        u.perfil === 'COZINHA' ? '<span class="badge bg-warning">Cozinha</span>' :
                        '<span class="badge bg-info">Operador</span>';
                    const statusBadge = u.ativo ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>';
                    const turnoAtivo = Number(u.turno_ativo || 0) === 1;
                    const turnoTipo = escapeHtml((u.turno_tipo || ''));
                    const horaEntrada = escapeHtml((u.turno_hora_entrada || ''));
                    const turnoLabel = turnoAtivo
                        ? `<span class="badge bg-success">Ativo</span>${turnoTipo ? ' <small class="text-muted">(' + turnoTipo + ')</small>' : ''}${horaEntrada ? '<div class="text-muted small">Entrada ' + horaEntrada + '</div>' : ''}`
                        : '<span class="badge bg-secondary">Encerrado</span>';
                    const btnTurno = turnoAtivo
                        ? `<button class="btn btn-sm btn-outline-danger btn-turno-action" data-action="fechar_turno" data-id="${userId}" data-nome="${nomeAttr}" title="Encerrar turno"><i class="fas fa-stop"></i></button>`
                        : `<button class="btn btn-sm btn-outline-success btn-turno-action" data-action="abrir_turno" data-id="${userId}" data-nome="${nomeAttr}" title="Iniciar turno"><i class="fas fa-play"></i></button>`;

                    html += `<tr>
                        <td class="ps-4"><strong>#${userId}</strong></td>
                        <td>${nome}</td>
                        <td>${email}</td>
                        <td>${perfilBadge}</td>
                        <td>${statusBadge}</td>
                        <td>${turnoLabel}</td>
                        <td class="text-center">
                            <div class="action-group">
                                ${btnTurno}
                                <button class="btn btn-sm btn-outline-danger btn-usuario-action" data-action="deletar" data-id="${userId}" data-nome="${nomeAttr}" title="Inativar"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            }

            document.getElementById('paginacaoUsuariosInfo').textContent = `${paged.page}/${paged.totalPages}`;
            document.getElementById('btnUsuariosPrev').disabled = paged.page <= 1;
            document.getElementById('btnUsuariosNext').disabled = paged.page >= paged.totalPages;
        }

        // Cadastrar usuário
        function cadastrarUsuario() {
            if (!hasPermission('manage_users')) {
                showAlert('Sem permissão para cadastrar usuários.', 'warning');
                return;
            }

            const restauranteId = document.getElementById('restauranteSelecionadoUsuarios').value;
            if (!restauranteId) {
                showAlert('Selecione um restaurante primeiro!', 'warning');
                return;
            }

            const form = document.getElementById('formCadastrarUsuario');
            const formData = new FormData(form);
            formData.set('restaurante_id', restauranteId);

            apiFetch('api/super_admin_usuario_cadastrar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalCadastrarUsuario')).hide();
                        form.reset();
                        carregarUsuarios();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro ao cadastrar: ' + err.message, 'danger');
                });
        }

        // Deletar usuário
        function deletarUsuario(id, nome) {
            if (!hasPermission('manage_users')) {
                showAlert('Sem permissão para inativar usuários.', 'warning');
                return;
            }

            if (!confirm(`Tem certeza que deseja inativar o usuário "${nome}"?`)) {
                return;
            }

            apiFetch('api/super_admin_usuario_deletar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        usuario_id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        carregarUsuarios();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    showAlert('Erro ao inativar: ' + err.message, 'danger');
                });
        }

        // ==================== PLANOS ====================

        // Carregar compras de planos
        function carregarCompras() {
            if (!canAccessPlansSection()) {
                return;
            }

            fetch('api/super_admin_compras_listar.php', {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        tableState.compras.all = Array.isArray(data.data) ? data.data : [];
                        aplicarFiltroCompras(false);
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(err => {
                    console.error('Erro:', err);
                    showAlert('Erro ao carregar compras', 'danger');
                });
        }

        function obterCompraPorId(compraId) {
            return fetch('api/super_admin_compras_listar.php', {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !Array.isArray(data.data)) {
                        throw new Error(data.message || 'Falha ao consultar compras');
                    }
                    return data.data.find(item => Number(item.id) === Number(compraId)) || null;
                });
        }

        function reconciliarCompraProcessada(compraId, acao, modalAlertId, modalElementId) {
            const acaoLabel = acao === 'rejeitar' ? 'rejeitada' : 'aprovada';
            return obterCompraPorId(compraId)
                .then(compra => {
                    if (compra && normalizePurchaseStatus(compra.status) !== 'AGUARDANDO_APROVACAO') {
                        bootstrap.Modal.getInstance(document.getElementById(modalElementId))?.hide();
                        carregarCompras();
                        showAlert(`A compra #${compraId} já foi ${acaoLabel} no servidor.`, 'info');
                        return true;
                    }
                    return false;
                })
                .catch(err => {
                    console.error('Falha ao reconciliar status da compra:', err);
                    return false;
                });
        }

        function aplicarFiltroCompras(resetPage = true) {
            const query = normalizeText(document.getElementById('filtroCompras').value);
            const planoSelecionado = (document.getElementById('filtroPlanoCompras')?.value || '').toUpperCase();
            const cicloSelecionado = (document.getElementById('filtroCicloCompras')?.value || '').toUpperCase();
            const statusSelecionado = (document.getElementById('filtroStatusCompras')?.value || '').toUpperCase();
            const dataIni = document.getElementById('filtroDataIniCompras')?.value || '';
            const dataFim = document.getElementById('filtroDataFimCompras')?.value || '';
            tableState.compras.filtered = tableState.compras.all.filter(c => {
                const cicloCompra = String(c.ciclo || (String(c.metodo_pagamento || '').includes('-') ? String(c.metodo_pagamento).split('-').pop().trim() : 'MENSAL')).toUpperCase();
                const statusCompra = normalizePurchaseStatus(c.status);
                const planoCompra = String(c.plano_novo || '').toUpperCase();
                const dataRef = c.data_compra || c.criado_em || c.created_at;
                const dataCompra = dataRef ? new Date(dataRef) : null;

                if (planoSelecionado && planoCompra !== planoSelecionado) {
                    return false;
                }

                if (cicloSelecionado && cicloCompra !== cicloSelecionado) {
                    return false;
                }

                if (statusSelecionado && statusCompra !== statusSelecionado) {
                    return false;
                }

                if (dataIni && dataCompra) {
                    const ini = new Date(dataIni + 'T00:00:00');
                    if (dataCompra < ini) {
                        return false;
                    }
                }

                if (dataFim && dataCompra) {
                    const fim = new Date(dataFim + 'T23:59:59');
                    if (dataCompra > fim) {
                        return false;
                    }
                }

                if ((dataIni || dataFim) && !dataCompra) {
                    return false;
                }

                if (!query) {
                    return true;
                }

            const hay = [c.restaurante_nome, c.plano_atual, c.plano_novo, getPurchaseStatusLabel(c.status), c.metodo_pagamento, c.ciclo, c.hash_arquivo].map(normalizeText).join(' ');
            return hay.includes(query);
        });
            if (resetPage) {
                tableState.compras.page = 1;
            }
            atualizarEstatisticasCompras(tableState.compras.filtered);
            renderCompras();
            updateUrlState();
        }

        function renderCompras() {
            const state = tableState.compras;
            const ordered = applySorting(state.filtered, state);
            const paged = paginate(ordered, state.page, state.pageSize);
            state.page = paged.page;
            atualizarTabelaCompras(paged.items);
            document.getElementById('paginacaoComprasInfo').textContent = `${paged.page}/${paged.totalPages}`;
            document.getElementById('btnComprasPrev').disabled = paged.page <= 1;
            document.getElementById('btnComprasNext').disabled = paged.page >= paged.totalPages;
        }

        // Atualizar tabela de compras
        function atualizarTabelaCompras(compras) {
            const tbody = document.getElementById('tabelaCompras');

            if (compras.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-muted">Nenhuma compra encontrada</td></tr>';
                return;
            }

            let html = '';
            compras.forEach(c => {
                const statusNorm = normalizePurchaseStatus(c.status);
                let statusBadge = statusNorm === 'APROVADO' ? '<span class="badge bg-success">Aprovado</span>' :
                    statusNorm === 'AGUARDANDO_APROVACAO' ? '<span class="badge bg-warning">Aguardando Aprovação</span>' :
                    '<span class="badge bg-danger">Rejeitado</span>';

                const dataRef = c.data_compra || c.criado_em || c.created_at;
                const dataFormatada = dataRef ? new Date(dataRef).toLocaleDateString('pt-BR') : '-';
                const ciclo = c.ciclo ? String(c.ciclo) : (String(c.metodo_pagamento || '').includes('-') ? String(c.metodo_pagamento).split('-').pop().trim() : 'MENSAL');
                const hashRaw = String(c.hash_arquivo || '').trim();
                const hashResumo = hashRaw ? (hashRaw.slice(0, 10) + '...' + hashRaw.slice(-6)) : '-';
                const comprovativoBtn = c.comprovativo_path ?
                    `<button type="button" class="btn btn-sm btn-outline-primary btn-compra-comprovativo" data-path="${encodeURIComponent(String(c.comprovativo_path))}" title="Ver comprovativo"><i class="fas fa-file-invoice"></i></button>` :
                    '<span class="text-muted">-</span>';

                let botoes = '';
                if (statusNorm === 'AGUARDANDO_APROVACAO' && hasPermission('approve_plans')) {
                    const compraId = Number(c.id) || 0;
                    botoes = `<div class="action-group">
                                <button class="btn btn-sm btn-success btn-compra-action" data-action="aprovar" data-id="${compraId}" title="Aprovar"><i class="fas fa-check"></i></button>
                                <button class="btn btn-sm btn-danger btn-compra-action" data-action="rejeitar" data-id="${compraId}" title="Rejeitar"><i class="fas fa-times"></i></button>
                              </div>`;
                } else {
                    botoes = '-';
                }

                html += `<tr>
                    <td class="ps-4"><strong>#${Number(c.id) || 0}</strong></td>
                    <td>${escapeHtml(c.restaurante_nome)}</td>
                    <td>${escapeHtml(c.plano_atual)}</td>
                    <td>${escapeHtml(c.plano_novo)}</td>
                    <td>${parseFloat(c.valor).toFixed(2)} MZN</td>
                    <td>${escapeHtml(c.metodo_pagamento)}</td>
                    <td>${escapeHtml(ciclo)}</td>
                    <td><code title="${escapeHtml(hashRaw || 'Hash indisponivel')}">${escapeHtml(hashResumo)}</code></td>
                    <td class="text-center">${comprovativoBtn}</td>
                    <td>${statusBadge}</td>
                    <td>${escapeHtml(dataFormatada)}</td>
                    <td class="text-center">${botoes}</td>
                </tr>`;
            });

            tbody.innerHTML = html;
        }

        // Atualizar estatísticas de compras
        function atualizarEstatisticasCompras(compras) {
            const total = compras.length;
            const pendentes = compras.filter(c => normalizePurchaseStatus(c.status) === 'AGUARDANDO_APROVACAO').length;
            const aprovados = compras.filter(c => normalizePurchaseStatus(c.status) === 'APROVADO').length;

            document.getElementById('totalCompras').textContent = total;
            document.getElementById('totalPendentes').textContent = pendentes;
            document.getElementById('totalAprovados').textContent = aprovados;

            // MRR: apenas compras aprovadas — normaliza cada valor para equivalente mensal
            const divisoresCiclo = {
                MENSAL: 1,
                TRIMESTRAL: 3,
                ANUAL: 12
            };
            const mrr = compras
                .filter(c => normalizePurchaseStatus(c.status) === 'APROVADO')
                .reduce((sum, c) => {
                    const valor = parseFloat(c.valor) || 0;
                    const divisor = divisoresCiclo[(c.ciclo || 'MENSAL').toUpperCase()] || 1;
                    return sum + (valor / divisor);
                }, 0);
            const arr = mrr * 12;
            document.getElementById('totalMRR').textContent = mrr.toLocaleString('pt-BR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }) + ' MZN';
            document.getElementById('totalARR').textContent = arr.toLocaleString('pt-BR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }) + ' MZN';

            const conversao = total > 0 ? (aprovados / total) * 100 : 0;
            const inadimplencia = total > 0 ? (pendentes / total) * 100 : 0;
            document.getElementById('totalConversao').textContent = conversao.toLocaleString('pt-BR', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }) + '%';
            document.getElementById('totalInadimplencia').textContent = inadimplencia.toLocaleString('pt-BR', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }) + '%';
        }

        function abrirComprovativo(pathEncoded) {
            const path = decodeURIComponent(pathEncoded || '').trim();
            if (!path) {
                showAlert('Comprovativo não disponível.', 'warning');
                return;
            }

            const body = document.getElementById('modalComprovativoBody');
            const lower = path.toLowerCase();
            const fileUrl = path.includes('comprovativo_arquivo.php')
                ? path
                : 'comprovativo_arquivo.php?path=' + encodeURIComponent(path);

            if (lower.endsWith('.pdf')) {
                body.innerHTML = `<iframe src="${escapeHtml(fileUrl)}" style="width:100%;height:70vh;border:0;border-radius:8px;"></iframe>`;
            } else {
                body.innerHTML = `<div class="text-center"><img src="${escapeHtml(fileUrl)}" alt="Comprovativo" style="max-width:100%;max-height:70vh;border-radius:10px;"></div>`;
            }

            new bootstrap.Modal(document.getElementById('modalComprovativo')).show();
        }

        // Aprovar compra - abre modal
        function aprovarCompra(id) {
            if (!hasPermission('approve_plans')) {
                showAlert('Sem permissão para aprovar planos.', 'warning');
                return;
            }

            document.getElementById('compra_id_aprovar').value = id;
            document.getElementById('obs_aprovar').value = '';
            new bootstrap.Modal(document.getElementById('modalAprovarCompra')).show();
        }

        function showModalAlert(id, message, type) {
            const el = document.getElementById(id);
            if (!el) {
                showAlert(message, type || 'danger');
                return;
            }
            el.className = 'alert alert-' + (type || 'danger');
            el.textContent = message;
            el.style.display = 'block';
        }

        function clearModalAlert(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = 'none';
            el.textContent = '';
        }

        // Confirmar aprovação com observação
        function confirmarAprovacao() {
            if (!hasPermission('approve_plans')) {
                showModalAlert('alertAprovarModal', 'Sem permissão para aprovar planos.', 'warning');
                return;
            }

            const compra_id = document.getElementById('compra_id_aprovar').value;
            const observacao = document.getElementById('obs_aprovar').value;

            if (!compra_id || Number(compra_id) <= 0) {
                showModalAlert('alertAprovarModal', 'ID da compra inválido. Feche o modal e tente novamente.', 'danger');
                return;
            }

            clearModalAlert('alertAprovarModal');

            // Desabilitar botão durante envio
            const btnConfirmar = document.getElementById('btnConfirmarAprovacao');
            if (btnConfirmar.disabled) {
                return;
            }
            const btnOriginalText = btnConfirmar.innerHTML;
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processando...';

            const controller = new AbortController();

            // Timeout de 30 segundos para evitar travamento indefinido
            const timeoutId = setTimeout(() => {
                controller.abort();
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = btnOriginalText;
                console.error('Timeout ao aprovar compra (30s)');
                showModalAlert('alertAprovarModal', 'Tempo limite atingido. Verificando status real da compra...', 'warning');

                reconciliarCompraProcessada(compra_id, 'aprovar', 'alertAprovarModal', 'modalAprovarCompra')
                    .then(resolvido => {
                        if (!resolvido) {
                            showModalAlert('alertAprovarModal', 'Timeout: A operação demorou muito. Tente novamente.', 'danger');
                            showAlert('Timeout: A operação demorou muito. Tente novamente.', 'danger');
                        }
                    });
            }, 30000);

            apiFetch('api/super_admin_plano_aprovar.php', {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: buildCsrfFormBody({
                        compra_id,
                        acao: 'aprovar',
                        observacao
                    })
                })
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Erro HTTP ' + response.status + ':', text);
                            try {
                                const data = JSON.parse(text);
                                throw new Error(data.message || 'Erro HTTP ' + response.status);
                            } catch (e) {
                                throw new Error('Erro HTTP ' + response.status + ': ' + (text || response.statusText));
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = btnOriginalText;

                    if (data.success) {
                        const passwordSetupUrl = data.data && data.data.password_setup_url ?
                            String(data.data.password_setup_url) :
                            '';
                        const passwordSetupEmail = data.data && data.data.password_setup_email ?
                            String(data.data.password_setup_email) :
                            '';

                        if (passwordSetupUrl) {
                            const safePasswordSetupUrl = escapeHtml(passwordSetupUrl);
                            const safePasswordSetupEmail = escapeHtml(passwordSetupEmail || 'Nao informado');
                            const supportMessages = [];
                            if (data.warning) {
                                supportMessages.push(String(data.warning));
                            }
                            const supportMessage = supportMessages.length ?
                                supportMessages.join(' ') :
                                'Guarde este link e partilhe com o administrador caso o email nao chegue.';

                            const html = `
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 pe-4">
                                    <div>
                                        <div>${escapeHtml(data.message || 'Plano aprovado com sucesso!')}</div>
                                        <div class="small mt-2"><strong>Email do administrador:</strong> ${safePasswordSetupEmail}</div>
                                        <div class="small mt-2">${escapeHtml(supportMessage)}</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="${safePasswordSetupUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">
                                            Abrir definicao de senha
                                        </a>
                                    </div>
                                </div>
                            `;

                            console.info('Link local de definicao de senha:', passwordSetupUrl);
                            showAlertHtml(html, isLocalEnvironment() ? 'info' : (data.warning ? 'warning' : 'success'), 15000);
                        } else {
                            const detalhesExtras = [];
                            if (data.warning) {
                                detalhesExtras.push(data.warning);
                            }

                            const mensagemFinal = [data.message || 'Plano aprovado com sucesso!'].concat(detalhesExtras).join(' ');
                            showAlert(mensagemFinal, data.warning ? 'warning' : 'success');
                        }
                        bootstrap.Modal.getInstance(document.getElementById('modalAprovarCompra')).hide();
                        carregarCompras();
                    } else {
                        const errorMsg = data.message || 'Falha ao aprovar compra. Tente novamente.';
                        if (errorMsg.toLowerCase().includes('já foi processada')) {
                            reconciliarCompraProcessada(compra_id, 'aprovar', 'alertAprovarModal', 'modalAprovarCompra')
                                .then(resolvido => {
                                    if (!resolvido) {
                                        showModalAlert('alertAprovarModal', errorMsg, 'warning');
                                        showAlert(errorMsg, 'warning');
                                    }
                                });
                        } else {
                            showModalAlert('alertAprovarModal', errorMsg, 'danger');
                            showAlert(errorMsg, 'danger');
                            console.error('Erro na resposta:', data);
                        }
                    }
                })
                .catch(err => {
                    clearTimeout(timeoutId);
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = btnOriginalText;

                    console.error('Erro ao aprovar compra:', err);
                    const errorMsg = err.name === 'AbortError' ?
                        'Timeout: A operação demorou muito. Tente novamente.' :
                        ('Erro ao aprovar: ' + (err.message || 'Erro desconhecido'));
                    showModalAlert('alertAprovarModal', errorMsg, 'danger');
                    showAlert(errorMsg, 'danger');
                });
        }

        // Rejeitar compra - abre modal
        function rejeitarCompra(id) {
            if (!hasPermission('approve_plans')) {
                showAlert('Sem permissão para rejeitar planos.', 'warning');
                return;
            }

            document.getElementById('compra_id_rejeitar').value = id;
            document.getElementById('obs_rejeitar').value = '';
            clearModalAlert('alertRejeitarModal');
            new bootstrap.Modal(document.getElementById('modalRejeitarCompra')).show();
        }

        // Confirmar rejeição com observação
        function confirmarRejeicao() {
            if (!hasPermission('approve_plans')) {
                showModalAlert('alertRejeitarModal', 'Sem permissão para rejeitar planos.', 'warning');
                return;
            }

            const compra_id = document.getElementById('compra_id_rejeitar').value;
            const observacao = document.getElementById('obs_rejeitar').value;

            if (!compra_id || Number(compra_id) <= 0) {
                showModalAlert('alertRejeitarModal', 'ID da compra inválido. Feche o modal e tente novamente.', 'danger');
                return;
            }

            if (!observacao.trim()) {
                showModalAlert('alertRejeitarModal', 'Informe o motivo da rejeição!', 'warning');
                return;
            }

            clearModalAlert('alertRejeitarModal');

            // Desabilitar botão durante envio
            const btnConfirmar = document.getElementById('btnConfirmarRejeicao');
            if (btnConfirmar.disabled) {
                return;
            }
            const btnOriginalText = btnConfirmar.innerHTML;
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processando...';

            const controller = new AbortController();

            // Timeout de 30 segundos para evitar travamento indefinido
            const timeoutId = setTimeout(() => {
                controller.abort();
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = btnOriginalText;
                console.error('Timeout ao rejeitar compra (30s)');
                showModalAlert('alertRejeitarModal', 'Tempo limite atingido. Verificando status real da compra...', 'warning');

                reconciliarCompraProcessada(compra_id, 'rejeitar', 'alertRejeitarModal', 'modalRejeitarCompra')
                    .then(resolvido => {
                        if (!resolvido) {
                            showModalAlert('alertRejeitarModal', 'Timeout: A operação demorou muito. Tente novamente.', 'danger');
                            showAlert('Timeout: A operação demorou muito. Tente novamente.', 'danger');
                        }
                    });
            }, 30000);

            apiFetch('api/super_admin_plano_aprovar.php', {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: buildCsrfFormBody({
                        compra_id,
                        acao: 'rejeitar',
                        observacao
                    })
                })
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Erro HTTP ' + response.status + ':', text);
                            try {
                                const data = JSON.parse(text);
                                throw new Error(data.message || 'Erro HTTP ' + response.status);
                            } catch (e) {
                                throw new Error('Erro HTTP ' + response.status + ': ' + (text || response.statusText));
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = btnOriginalText;

                    if (data.success) {
                        showAlert(data.message || 'Compra rejeitada com sucesso!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalRejeitarCompra')).hide();
                        carregarCompras();
                    } else {
                        const errorMsg = data.message || 'Falha ao rejeitar compra. Tente novamente.';
                        if (errorMsg.toLowerCase().includes('já foi processada')) {
                            reconciliarCompraProcessada(compra_id, 'rejeitar', 'alertRejeitarModal', 'modalRejeitarCompra')
                                .then(resolvido => {
                                    if (!resolvido) {
                                        showModalAlert('alertRejeitarModal', errorMsg, 'warning');
                                        showAlert(errorMsg, 'warning');
                                    }
                                });
                        } else {
                            showModalAlert('alertRejeitarModal', errorMsg, 'danger');
                            showAlert(errorMsg, 'danger');
                            console.error('Erro na resposta:', data);
                        }
                    }
                })
                .catch(err => {
                    clearTimeout(timeoutId);
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = btnOriginalText;

                    console.error('Erro ao rejeitar compra:', err);
                    const errorMsg = err.name === 'AbortError' ?
                        'Timeout: A operação demorou muito. Tente novamente.' :
                        ('Erro ao rejeitar: ' + (err.message || 'Erro desconhecido'));
                    showModalAlert('alertRejeitarModal', errorMsg, 'danger');
                    showAlert(errorMsg, 'danger');
                });
        }

        function renderSecurityTimeline(timeline) {
            const labels = (timeline || []).map(item => String(item.bucket || '').slice(11, 16));
            const values = (timeline || []).map(item => Number(item.total || 0));
            const ctx = document.getElementById('securityTimelineChart');
            if (!ctx) return;

            if (securityTimelineChart) {
                securityTimelineChart.destroy();
            }

            securityTimelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Ataques/eventos',
                        data: values,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220,53,69,0.12)',
                        fill: true,
                        tension: 0.25
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 } } }
                }
            });
        }

        function renderSecurityPanel(data) {
            const stats = data?.stats || {};
            document.getElementById('secTotalEventos').textContent = Number(stats.total_eventos || 0);
            document.getElementById('secCriticos').textContent = Number(stats.criticos || 0);
            document.getElementById('secIpsBloqueados').textContent = Number(stats.ips_bloqueados_ativos || 0);
            document.getElementById('secRiskLabel').textContent = String(stats.risk_level || 'BAIXO');
            document.getElementById('secRiskScore').textContent = Number(stats.risk_score || 0);
            document.getElementById('secSqli').textContent = Number(stats.sqli || 0);
            document.getElementById('secXss').textContent = Number(stats.xss || 0);
            document.getElementById('secBrute').textContent = Number(stats.brute || 0);
            document.getElementById('secBots').textContent = Number(stats.bots || 0);
            document.getElementById('secTraversal').textContent = Number(stats.traversal || 0);
            const checklistSummary = data?.production_checklist_summary || {};
            document.getElementById('secCheckOk').textContent = Number(checklistSummary.ok || 0);
            document.getElementById('secCheckPendente').textContent = Number(checklistSummary.pendente || 0);
            document.getElementById('secCheckCritico').textContent = Number(checklistSummary.critico || 0);
            const offlineSummary = data?.offline_queue_summary || {};
            const centralRestEl = document.getElementById('offlineCentralRestaurantes');
            const centralDevEl = document.getElementById('offlineCentralDispositivos');
            const centralTotalEl = document.getElementById('offlineCentralTotal');
            if (centralRestEl) centralRestEl.textContent = String(Number(offlineSummary.restaurantes_com_fila || 0));
            if (centralDevEl) centralDevEl.textContent = String(Number(offlineSummary.dispositivos_com_fila || 0));
            if (centralTotalEl) centralTotalEl.textContent = String(Number(offlineSummary.total_count || 0));

            const readiness = data?.readiness_95 || {};
            const governance = data?.governance || {};
            const readyScore = Number(readiness.score || 0);
            const readyStatus = String(readiness.status || 'PENDENTE');
            const readyStatusEl = document.getElementById('secReadyStatus');
            const readyScoreEl = document.getElementById('secReadyScore');
            const govOwnerEl = document.getElementById('secGovOwner');
            const govUpdatedEl = document.getElementById('secGovUpdatedAt');
            if (readyScoreEl) readyScoreEl.textContent = String(readyScore);
            if (govOwnerEl) govOwnerEl.textContent = String(governance.owner || '--');
            if (govUpdatedEl) govUpdatedEl.textContent = String(governance.updated_at || '--');
            if (readyStatusEl) {
                readyStatusEl.textContent = readyStatus;
                readyStatusEl.className = 'badge ' + (readyStatus === 'PRONTO_95+' ? 'bg-success' : (readyStatus === 'PARCIAL' ? 'bg-warning text-dark' : 'bg-danger'));
            }

            const govRows = Array.isArray(governance.items) ? governance.items : [];
            const govBody = document.getElementById('tabelaGovernanceCadencia');
            if (govBody) {
                govBody.innerHTML = govRows.map((row) => {
                    const overdue = Boolean(row?.overdue);
                    return `
                        <tr>
                            <td>${escapeHtml(String(row?.name || '-'))}</td>
                            <td>${escapeHtml(String(row?.last_at || '-'))}</td>
                            <td>${Number(row?.cadence_days || 0)}d</td>
                            <td>${row?.days_since == null ? '-' : Number(row.days_since)}</td>
                            <td><span class="badge ${overdue ? 'bg-danger' : 'bg-success'}">${overdue ? 'OVERDUE' : 'OK'}</span></td>
                        </tr>
                    `;
                }).join('') || '<tr><td colspan="5" class="security-table-empty">Sem dados de governanca</td></tr>';
            }

            renderSecurityTimeline(data?.timeline || []);

            const topIpsBody = document.getElementById('tabelaTopIps');
            topIpsBody.innerHTML = (data?.top_ips || []).map(row => `
                <tr>
                    <td>${escapeHtml(row.ip || '-')}</td>
                    <td>${Number(row.ataques || 0)}</td>
                    <td class="text-end"><button class="btn btn-sm btn-outline-danger btn-block-ip" data-ip="${escapeHtml(row.ip || '')}">Bloquear</button></td>
                </tr>
            `).join('') || '<tr><td colspan="3" class="security-table-empty">Sem atividade</td></tr>';

            const blockedBody = document.getElementById('tabelaBlockedIps');
            blockedBody.innerHTML = (data?.blocked_ips || []).map(row => `
                <tr>
                    <td>${escapeHtml(row.ip || '-')}</td>
                    <td>${escapeHtml(row.bloqueio_tipo || '-')}</td>
                    <td>${escapeHtml(row.origem || '-')}</td>
                    <td class="text-end"><button class="btn btn-sm btn-outline-success btn-unblock-ip" data-ip="${escapeHtml(row.ip || '')}">Desbloquear</button></td>
                </tr>
            `).join('') || '<tr><td colspan="4" class="security-table-empty">Nenhum IP bloqueado</td></tr>';

            const eventsBody = document.getElementById('tabelaSecurityEvents');
            eventsBody.innerHTML = (data?.recent_events || []).map(row => `
                <tr>
                    <td>#${Number(row.id || 0)}</td>
                    <td>${escapeHtml(row.created_at || '-')}</td>
                    <td><span class="badge bg-${row.severity === 'CRITICAL' ? 'danger' : (row.severity === 'HIGH' ? 'warning' : 'secondary')}">${escapeHtml(row.severity || '-')}</span></td>
                    <td>${escapeHtml(row.attack_type || '-')}</td>
                    <td>${escapeHtml(row.ip || '-')}</td>
                    <td><small>${escapeHtml(row.endpoint || '-')}</small></td>
                </tr>
            `).join('') || '<tr><td colspan="6" class="security-table-empty">Sem eventos recentes</td></tr>';

            const checklistBody = document.getElementById('tabelaSecurityChecklist');
            const statusBadgeMap = {
                ok: 'bg-success',
                pendente: 'bg-warning text-dark',
                critico: 'bg-danger'
            };
            const statusLabelMap = {
                ok: 'OK',
                pendente: 'PENDENTE',
                critico: 'CRITICO'
            };
            checklistBody.innerHTML = (data?.production_checklist || []).map((row) => {
                const statusKey = String(row.status || 'pendente').toLowerCase();
                const badgeClass = statusBadgeMap[statusKey] || 'bg-secondary';
                const label = statusLabelMap[statusKey] || statusKey.toUpperCase();
                return `
                <tr class="security-checklist-row">
                    <td>${escapeHtml(row.label || '-')}</td>
                    <td><span class="badge ${badgeClass}">${label}</span></td>
                    <td><small>${escapeHtml(row.detail || '-')}</small></td>
                </tr>`;
            }).join('') || '<tr><td colspan="3" class="security-table-empty">Checklist indisponivel</td></tr>';

            const stampEl = document.getElementById('securityLastUpdate');
            if (stampEl) {
                const now = new Date();
                stampEl.textContent = now.toLocaleTimeString('pt-PT', { hour12: false });
            }
            const ipEl = document.getElementById('securityCurrentIp');
            if (ipEl) {
                ipEl.textContent = String(data?.current_client_ip || '--');
            }
        }

        function controlarAutoRefreshSeguranca() {
            if (securityAutoRefreshHandle) {
                clearInterval(securityAutoRefreshHandle);
                securityAutoRefreshHandle = null;
            }

            if (secaoAtual !== 'seguranca' || !canAccessSecuritySection()) {
                return;
            }

            securityAutoRefreshHandle = setInterval(() => {
                if (secaoAtual === 'seguranca') {
                    carregarPainelSeguranca();
                    carregarPainelFilaOffline();
                }
            }, 10000);
        }

        function setOfflineQueueStatus(message) {
            const el = document.getElementById('offlineQueueStatus');
            if (el) el.textContent = String(message || '');
        }

        function preencherOfflineStats(stats = {}) {
            const pending = Number(stats.pending || 0);
            const done = Number(stats.done || 0);
            const failed = Number(stats.failed || 0);
            const attempts = Number(stats.attempts || 0);
            const pendingEl = document.getElementById('offlinePendingCount');
            const doneEl = document.getElementById('offlineDoneCount');
            const failedEl = document.getElementById('offlineFailedCount');
            const attemptsEl = document.getElementById('offlineAttemptsCount');
            if (pendingEl) pendingEl.textContent = String(pending);
            if (doneEl) doneEl.textContent = String(done);
            if (failedEl) failedEl.textContent = String(failed);
            if (attemptsEl) attemptsEl.textContent = String(attempts);
        }

        function carregarPainelFilaOffline() {
            if (!canAccessSecuritySection() || secaoAtual !== 'seguranca') return;
            if (typeof OfflineSync === 'undefined' || !OfflineSync.getQueueStats) {
                setOfflineQueueStatus('Modulo offline indisponivel nesta pagina.');
                return;
            }

            OfflineSync.getQueueStats()
                .then((stats) => {
                    preencherOfflineStats(stats || {});
                    const pend = Number(stats?.pending || 0);
                    setOfflineQueueStatus(pend > 0
                        ? ('Fila offline com ' + pend + ' operacao(oes) pendente(s).')
                        : 'Fila offline vazia.');
                })
                .catch((err) => {
                    setOfflineQueueStatus('Erro ao ler fila offline: ' + (err?.message || 'desconhecido'));
                });
        }

        function sincronizarFilaOfflineAgora() {
            if (typeof OfflineSync === 'undefined' || !OfflineSync.syncQueue) {
                showAlert('Modulo offline nao disponivel.', 'warning');
                return;
            }
            setOfflineQueueStatus('Sincronizando fila offline...');
            OfflineSync.syncQueue()
                .then((res) => {
                    const synced = Number(res?.synced || 0);
                    const pending = Number(res?.pending || 0);
                    showAlert('Sincronizacao concluida. Sincronizadas: ' + synced + ' | Pendentes: ' + pending, 'success');
                    carregarPainelFilaOffline();
                })
                .catch((err) => {
                    showAlert('Erro ao sincronizar fila offline: ' + (err?.message || 'desconhecido'), 'danger');
                    carregarPainelFilaOffline();
                });
        }

        function carregarPainelSeguranca() {
            if (!canAccessSecuritySection()) return;
            const hours = document.getElementById('securityWindowHours')?.value || '24';

            const basePath = window.location.pathname.replace(/\/[^/]*$/, '/');
            const candidates = [
                'api/super_admin_security_dashboard.php?hours=' + encodeURIComponent(hours),
                basePath + 'api/super_admin_security_dashboard.php?hours=' + encodeURIComponent(hours),
                '/V00/src/public/api/super_admin_security_dashboard.php?hours=' + encodeURIComponent(hours)
            ];

            const tentar = (idx = 0) => {
                if (idx >= candidates.length) {
                    throw new Error('Falha de conexao com API de seguranca.');
                }
                return apiFetch(candidates[idx], { method: 'GET' })
                    .then(parseJsonSafe)
                    .catch((err) => {
                        if (/Failed to fetch|NetworkError|conexao/i.test(String(err && err.message || ''))) {
                            return tentar(idx + 1);
                        }
                        throw err;
                    });
            };

            Promise.resolve()
            .then(() => tentar(0))
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.error ? (data.message + ' (' + data.error + ')') : (data.message || 'Falha ao carregar painel de segurança.'));
                }
                renderSecurityPanel(data.data || {});
                carregarPainelFilaOffline();
                updateUrlState();
            })
            .catch((err) => {
                showAlert('Erro no painel de segurança: ' + err.message, 'danger');
            });
        }

        function executarAcaoIp(action, ip, tipo = 'TEMPORARIO') {
            if (!ip) return;
            const payload = JSON.stringify({
                action,
                ip,
                tipo,
                duracao_min: 60,
                motivo: action === 'BLOCK' ? 'Bloqueio manual via painel Super Admin' : 'Desbloqueio manual via painel Super Admin'
            });

            const basePath = window.location.pathname.replace(/\/[^/]*$/, '/');
            const candidates = [
                'api/super_admin_security_ip_action.php',
                basePath + 'api/super_admin_security_ip_action.php',
                '/V00/src/public/api/super_admin_security_ip_action.php'
            ];

            const tentar = (idx = 0) => {
                if (idx >= candidates.length) {
                    throw new Error('Falha de conexao com API de acao de IP.');
                }
                return apiFetch(candidates[idx], {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload
                })
                .then(parseJsonSafe)
                .catch((err) => {
                    if (/Failed to fetch|NetworkError|conexao/i.test(String(err && err.message || ''))) {
                        return tentar(idx + 1);
                    }
                    throw err;
                });
            };

            Promise.resolve()
            .then(() => tentar(0))
            .then((data) => {
                if (!data.success) throw new Error(data.message || 'Ação não executada.');
                showAlert(data.message || 'Ação aplicada com sucesso.', 'success');
                carregarPainelSeguranca();
            })
            .catch((err) => showAlert('Erro ao aplicar ação de IP: ' + err.message, 'danger'));
        }

        function bloquearIpManual() {
            const ip = (document.getElementById('securityIpManual')?.value || '').trim();
            const tipo = document.getElementById('securityTipoBloqueio')?.value || 'TEMPORARIO';
            if (!ip) {
                showAlert('Informe um IP para bloquear.', 'warning');
                return;
            }
            executarAcaoIp('BLOCK', ip, tipo);
        }

        (() => {
            const logoutLinks = document.querySelectorAll('a[href="logout.php"]');
            if (!logoutLinks.length) {
                return;
            }

            const markOffline = () => {
                const url = 'api/online_offline.php';

                try {
                    if (navigator.sendBeacon) {
                        const data = new FormData();
                        data.append('source', 'logout');
                        navigator.sendBeacon(url, data);
                        return;
                    }
                } catch (e) {}

                try {
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                } catch (e) {}
            };

            logoutLinks.forEach((link) => {
                link.addEventListener('click', markOffline, {
                    passive: true
                });
            });
        })();
    </script>
</body>

</html>


