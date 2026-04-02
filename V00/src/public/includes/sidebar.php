<?php

/**
 * Sidebar Unificado por Perfil
 * Roles: ADMIN (full), CAIXA (caixa+vendas+pedidos+dashboard), GARCOM (pedidos+novo_pedido+mesas+dashboard), COZINHA (cozinha+pedidos+dashboard), BAR (bar+pedidos+dashboard)
 */
include_once __DIR__ . '/../../config/plano_check.php';
include_once __DIR__ . '/../../config/restaurante_context.php';
include_once __DIR__ . '/../../config/csrf.php';
include_once __DIR__ . '/../../config/database.php';

$perfil = strtoupper(trim($_SESSION['perfil'] ?? 'USER'));
$perfil = str_replace('GARÇOM', 'GARCOM', $perfil);
if ($perfil === 'COZINHEIRO' || $perfil === 'CHEF') {
    $perfil = 'COZINHA';
}
if ($perfil === 'BARMAN' || $perfil === 'BARTENDER') {
    $perfil = 'BAR';
}
$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$restauranteId = session_restaurante_contexto_id();
$restauranteCapabilityId = session_restaurante_capability_id();
$restauranteFeatureId = $restauranteCapabilityId > 0 ? $restauranteCapabilityId : $restauranteId;
$contextoFilialAtivo = session_contexto_filial_ativo();
$filialNomeSessao = trim((string)($_SESSION['filial_nome'] ?? ''));
$csrfTokenSidebar = $contextoFilialAtivo ? csrf_get_token() : '';
$temMultiFilial = $perfil === 'ADMIN' && $restauranteFeatureId > 0 && plano_tem_funcionalidade_db($restauranteFeatureId, 'multi_filial');
$temPedidosOnline = $restauranteFeatureId > 0 && plano_tem_funcionalidade_db($restauranteFeatureId, 'pedidos_online');
$temBackup = $perfil === 'ADMIN' && $restauranteFeatureId > 0 && (
    plano_tem_funcionalidade_db($restauranteFeatureId, 'backup_automatico')
    || plano_tem_funcionalidade_db($restauranteFeatureId, 'backup_diario')
    || plano_tem_funcionalidade_db($restauranteFeatureId, 'backup_manual')
    || plano_tem_funcionalidade_db($restauranteFeatureId, 'download_banco')
);
$restauranteBrandNome = 'Restaurante';
$restauranteBrandLogo = '';

if ($restauranteId > 0) {
    try {
        $databaseSidebar = new Database();
        $dbSidebar = $databaseSidebar->getConnection();
        if ($dbSidebar instanceof PDO) {
            $stmtBrand = $dbSidebar->prepare('SELECT nome, logo FROM restaurantes WHERE id = :id LIMIT 1');
            $stmtBrand->bindValue(':id', $restauranteId, PDO::PARAM_INT);
            $stmtBrand->execute();
            $brandRow = $stmtBrand->fetch(PDO::FETCH_ASSOC) ?: [];
            $restauranteBrandNome = trim((string)($brandRow['nome'] ?? '')) ?: $restauranteBrandNome;
            $restauranteBrandLogo = trim((string)($brandRow['logo'] ?? ''));

            if ($restauranteBrandLogo !== '') {
                $restauranteBrandLogo = str_replace('\\', '/', $restauranteBrandLogo);
                $restauranteBrandLogo = preg_replace('#^src/public/#i', '', $restauranteBrandLogo);
                $restauranteBrandLogo = ltrim($restauranteBrandLogo, '/');
            }
        }
    } catch (Throwable $e) {
        // Mantém o fallback visual.
    }
}

$restauranteBrandInicial = strtoupper(substr(preg_replace('/\s+/', '', $restauranteBrandNome), 0, 2) ?: 'R');

$allItems = [
    'dashboard.php' => ['icon' => 'fa-chart-line', 'label' => 'Dashboard'],
    'pedidos.php' => ['icon' => 'fa-clipboard-check', 'label' => 'Pedidos'],
    'novo_pedido.php' => ['icon' => 'fa-clipboard-list', 'label' => 'Novo Pedido'],
    'caixa_mesas.php' => ['icon' => 'fa-receipt', 'label' => 'Contas Abertas'],
    'vendas.php' => ['icon' => 'fa-cash-register', 'label' => 'Vendas'],
    'caixa.php' => ['icon' => 'fa-money-bill-wave', 'label' => 'Caixa'],
    'mesas.php' => ['icon' => 'fa-chair', 'label' => 'Mesas'],
    'garcom_mesas.php' => ['icon' => 'fa-table-cells', 'label' => 'Mapa de Mesas'],
    'produtos.php' => ['icon' => 'fa-pizza-slice', 'label' => 'Produtos'],
    'funcionarios_turnos.php' => ['icon' => 'fa-clock', 'label' => 'Funcionários e Turnos'],
    'relatorios.php' => ['icon' => 'fa-chart-bar', 'label' => 'Relatórios'],
    'cozinha.php' => ['icon' => 'fa-fire-burner', 'label' => 'Cozinha'],
    'bar.php' => ['icon' => 'fa-martini-glass-citrus', 'label' => 'Bar'],
    'usuarios.php' => ['icon' => 'fa-users', 'label' => 'Usuários'],
    'filiais.php' => ['icon' => 'fa-building', 'label' => 'Filiais'],
    'configuracoes.php' => ['icon' => 'fa-cog', 'label' => 'Configurações'],
    'backup.php' => ['icon' => 'fa-database', 'label' => 'Backup'],
];

$primaryActionByPerfil = [
    'ADMIN' => 'novo_pedido.php',
    'GARCOM' => 'novo_pedido.php',
    'CAIXA' => 'caixa_mesas.php',
    'COZINHA' => 'cozinha.php',
    'BAR' => 'bar.php',
];
$primaryAction = $primaryActionByPerfil[$perfil] ?? null;
$collapseOperationalMenus = in_array($perfil, ['GARCOM', 'CAIXA', 'COZINHA', 'BAR'], true);

$menuSections = [];

if ($perfil === 'ADMIN') {
    $menuSections = [
        'Operação' => [
            'dashboard.php',
            'pedidos.php',
            'novo_pedido.php',
            'caixa_mesas.php',
            'vendas.php',
            'caixa.php',
            'mesas.php',
            'garcom_mesas.php',
            'cozinha.php',
            'bar.php',
        ],
        'Gestão' => [
            'produtos.php',
            'relatorios.php',
            'funcionarios_turnos.php',
        ],
        'Sistema' => [
            'usuarios.php',
            'configuracoes.php',
        ],
    ];

    if ($temMultiFilial) {
        $menuSections['Gestão'][] = 'filiais.php';
    }

    if ($temBackup) {
        $menuSections['Sistema'][] = 'backup.php';
    }
} elseif ($perfil === 'CAIXA') {
    $menuSections = [
        'Operação' => [
            'dashboard.php',
            'caixa_mesas.php',
            'vendas.php',
            'caixa.php',
        ],
        'Gestão' => [
            'relatorios.php',
        ],
    ];
} elseif ($perfil === 'GARCOM') {
    $menuSections = [
        'Operação' => [
            'dashboard.php',
            'pedidos.php',
            'novo_pedido.php',
            'mesas.php',
            'garcom_mesas.php',
        ],
    ];
} elseif ($perfil === 'COZINHA') {
    $menuSections = [
        'Operação' => [
            'cozinha.php',
            'pedidos.php',
        ],
    ];
} elseif ($perfil === 'BAR') {
    $menuSections = [
        'Operação' => [
            'bar.php',
            'pedidos.php',
        ],
    ];
}

if (!$temPedidosOnline) {
    foreach ($menuSections as $sectionTitle => $sectionItems) {
        $menuSections[$sectionTitle] = array_values(array_filter($sectionItems, static function ($url) {
            return $url !== 'pedidos.php';
        }));
    }

    $menuSections = array_filter($menuSections, static function ($sectionItems) {
        return !empty($sectionItems);
    });
}
?>
<style>
    /* Oculta o botão de toggle quando o menu está aberto no mobile */
    body.sidebar-mobile-open .sidebar-mobile-toggle {
        display: none !important;
    }

    /* Oculta o botão de toggle ao clicar em Funcionários e Turnos (durante a navegação) */
    body.funcionarios-turnos-navegando .sidebar-mobile-toggle {
        display: none !important;
    }
    /* =============================================
       CSS BASE DO SIDEBAR — fonte única de verdade
       Todas as páginas que incluem este arquivo
       usarão este estilo idêntico ao dashboard.
       ============================================= */
    :root {
        --primary: #FF6B35;
        --secondary: #F7931E;
        --dark: #0f0f23;
        --dark-2: #1a1a2e;
        --dark-3: #16213e;
        --text-muted: #94a3b8;
    }

    .sidebar {
        width: 280px;
        min-height: 100vh;
        background: linear-gradient(180deg, var(--dark) 0%, var(--dark-2) 50%, var(--dark-3) 100%);
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        padding: 0;
        display: flex;
        flex-direction: column;
    }

    .sidebar-brand {
        padding: 22px 18px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        background:
            radial-gradient(circle at top left, rgba(255, 107, 53, 0.22), transparent 42%),
            linear-gradient(145deg, rgba(255, 107, 53, 0.14), rgba(247, 147, 30, 0.05) 52%, rgba(255, 255, 255, 0.02));
        position: relative;
        overflow: hidden;
    }

    .sidebar-brand::after {
        content: "";
        position: absolute;
        right: -28px;
        bottom: -42px;
        width: 118px;
        height: 118px;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(247, 147, 30, 0.18), transparent 68%);
        pointer-events: none;
    }

    .sidebar-brand-inner {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        min-width: 0;
        position: relative;
        z-index: 1;
    }

    .sidebar-brand-media {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 107, 53, 0.14));
        border: 1px solid rgba(255, 255, 255, 0.16);
        box-shadow:
            0 16px 30px rgba(15, 15, 35, 0.28),
            inset 0 1px 0 rgba(255, 255, 255, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
        color: #fff;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 19px;
        font-weight: 700;
        backdrop-filter: blur(12px);
    }

    .sidebar-brand-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .sidebar-brand-copy {
        min-width: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-top: 2px;
    }

    .sidebar-brand-kicker {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.72);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        line-height: 1;
    }

    .sidebar-brand-title {
        color: white;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 17px;
        font-weight: 700;
        margin: 0;
        line-height: 1.16;
        letter-spacing: -0.02em;
        word-break: break-word;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .sidebar-brand-subtitle {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        width: fit-content;
        color: #ffd8bd;
        font-size: 11px;
        margin-top: 0;
        letter-spacing: 0.03em;
        font-weight: 600;
    }

    .sidebar-brand-subtitle::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: linear-gradient(135deg, #ff9a5f, #f7c15c);
        box-shadow: 0 0 0 4px rgba(255, 154, 95, 0.16);
    }

    .sidebar-context {
        margin: 0 6px 18px;
        padding: 12px 14px;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.2), rgba(15, 15, 35, 0.78));
        border: 1px solid rgba(255, 107, 53, 0.35);
        display: flex;
        flex-direction: column;
        gap: 6px;
        position: relative;
        z-index: 1;
    }

    .sidebar-context strong {
        display: block;
        color: #fff;
        font-size: 12px;
        margin: 0;
        line-height: 1.35;
    }

    .sidebar-context small {
        display: block;
        color: rgba(255, 255, 255, 0.72);
        line-height: 1.45;
        margin: 0;
        font-size: 11px;
    }

    .sidebar-context form {
        margin: 0;
    }

    .sidebar-context .btn-context {
        width: 100%;
        border: 0;
        border-radius: 10px;
        padding: 10px 12px;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        transition: background 0.2s ease;
        margin-top: 4px;
    }

    .sidebar-context .btn-context:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        flex-shrink: 0;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
    }

    .user-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }

    .user-details {
        flex: 1;
    }

    .user-name {
        color: white;
        font-weight: 600;
        font-size: 14px;
    }

    .user-role {
        color: var(--text-muted);
        font-size: 12px;
    }

    .menu-title {
        color: var(--text-muted);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        padding: 10px 16px 8px;
        font-weight: 600;
    }

    .menu-section {
        margin-bottom: 10px;
        padding-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .menu-section:first-child {
        border-top: none;
        padding-top: 0;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 18px;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        border-radius: 12px;
        margin-bottom: 4px;
        transition: all 0.3s;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        opacity: 0;
        transform: translateX(-8px);
        animation: sidebarItemIn 0.28s ease-out forwards;
        animation-delay: calc(var(--item-index, 0) * 0.03s);
    }

    .menu-item:hover {
        background: rgba(255, 255, 255, 0.08);
        color: white;
        transform: translateX(4px);
    }

    .menu-item.active {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
    }

    .menu-item i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .menu-item-primary {
        margin-top: 4px;
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.22), rgba(247, 147, 30, 0.16));
        border: 1px solid rgba(255, 107, 53, 0.45);
        color: #fff;
        box-shadow: 0 8px 18px rgba(255, 107, 53, 0.2);
    }

    .menu-item-primary:hover {
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.34), rgba(247, 147, 30, 0.24));
        transform: translateX(4px) translateY(-1px);
    }

    .menu-item-primary.active {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-color: transparent;
    }

    .menu-item-badge {
        margin-left: auto;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
    }

    .menu-counter-badge {
        margin-left: auto;
        min-width: 22px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: rgba(59, 130, 246, 0.95);
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);
    }

    .menu-counter-badge.menu-counter-alert {
        background: rgba(239, 68, 68, 0.95);
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25);
    }

    .menu-counter-badge.is-empty {
        display: none;
    }

    .sidebar-toast {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 3000;
        min-width: 300px;
        max-width: 420px;
        padding: 14px 16px;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(15, 15, 35, 0.96), rgba(255, 107, 53, 0.96));
        color: #fff;
        box-shadow: 0 16px 40px rgba(15, 15, 35, 0.28);
        border: 1px solid rgba(255, 255, 255, 0.14);
        display: none;
    }

    .sidebar-toast strong {
        display: block;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .sidebar-toast small {
        color: rgba(255, 255, 255, 0.78);
    }

    .menu-more {
        margin-top: 6px;
    }

    .menu-more summary {
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: rgba(255, 255, 255, 0.68);
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.25px;
        padding: 8px 12px;
        border-radius: 10px;
        cursor: pointer;
        border: 1px dashed rgba(148, 163, 184, 0.35);
        background: rgba(255, 255, 255, 0.03);
        transition: all 0.2s ease;
    }

    .menu-more summary:hover {
        color: #fff;
        border-color: rgba(255, 107, 53, 0.55);
        background: rgba(255, 255, 255, 0.08);
    }

    .menu-more summary::-webkit-details-marker {
        display: none;
    }

    .menu-more-icon {
        font-size: 11px;
        transition: transform 0.2s ease;
    }

    .menu-more[open] .menu-more-icon {
        transform: rotate(180deg);
    }

    @keyframes sidebarItemIn {
        from {
            opacity: 0;
            transform: translateX(-8px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Sidebar layout fix (grid) */
    .sidebar.sidebar-unified {
        display: grid !important;
        grid-template-rows: auto minmax(0, 1fr) auto;
        height: 100vh !important;
        overflow: hidden !important;
    }

    .sidebar.sidebar-unified .sidebar-menu {
        min-height: 0;
        height: auto !important;
        max-height: none !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        padding-bottom: 14px !important;
        scrollbar-gutter: stable;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 107, 53, 0.85) rgba(255, 255, 255, 0.08);
    }

    .sidebar.sidebar-unified .sidebar-menu::-webkit-scrollbar {
        width: 8px;
    }

    .sidebar.sidebar-unified .sidebar-menu::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 8px;
    }

    .sidebar.sidebar-unified .sidebar-menu::-webkit-scrollbar-thumb {
        background: rgba(255, 107, 53, 0.85);
        border-radius: 8px;
        border: 2px solid rgba(255, 255, 255, 0.08);
    }

    .sidebar.sidebar-unified .sidebar-menu::-webkit-scrollbar-thumb:hover {
        background: #ff6b35;
    }

    .sidebar.sidebar-unified .sidebar-footer {
        flex-shrink: 0;
    }

    .sidebar-mobile-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        backdrop-filter: blur(4px);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.24s ease;
        z-index: 1090;
    }

    body.sidebar-mobile-open {
        overflow: hidden;
    }

    body.sidebar-mobile-open .sidebar-mobile-backdrop {
        opacity: 1;
        pointer-events: auto;
    }

    html,
    body {
        overflow-x: hidden;
    }

    @media (max-width: 1200px) {

        .sidebar-mobile-toggle,
        #sidebarToggleBtn,
        #menuToggleBtn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 46px !important;
            height: 46px !important;
            position: static !important;
            inset: auto !important;
            top: auto !important;
            left: auto !important;
            border-radius: 16px !important;
            border: none !important;
            background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
            color: #fff !important;
            box-shadow: 0 14px 28px rgba(255, 107, 53, 0.22) !important;
            margin: 0 !important;
            z-index: 1150 !important;
            flex-shrink: 0 !important;
        }

        .sidebar.sidebar-unified.d-md-block {
            display: grid !important;
        }

        .sidebar.sidebar-unified {
            width: min(320px, calc(100vw - 24px)) !important;
            max-width: calc(100vw - 24px) !important;
            position: fixed !important;
            left: 12px !important;
            top: 12px !important;
            min-height: calc(100dvh - 24px) !important;
            height: calc(100dvh - 24px) !important;
            border-radius: 28px !important;
            overflow: hidden !important;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.38) !important;
            transform: translateX(-120%) !important;
            transition: transform 0.28s ease !important;
            z-index: 1100 !important;
        }

        .sidebar.sidebar-unified.hide-mobile,
        .sidebar.sidebar-unified.sidebar-hidden {
            transform: translateX(-120%) !important;
        }

        body.sidebar-mobile-open .sidebar.sidebar-unified {
            transform: translateX(0) !important;
        }

        .sidebar.sidebar-unified .sidebar-menu {
            max-height: none !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding-bottom: 12px !important;
        }

        .sidebar.sidebar-unified .sidebar-footer {
            padding-top: 16px;
        }

        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .top-bar {
            padding: 14px 16px !important;
            gap: 12px !important;
        }

        .sidebar-topbar-main {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            min-width: 0 !important;
            flex-wrap: nowrap !important;
        }

        .top-bar-right {
            width: 100% !important;
            justify-content: flex-start !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
        }

        .top-bar-date,
        .top-bar-user-chip {
            width: 100% !important;
        }

        .top-bar-user-chip {
            justify-content: flex-start !important;
        }

        .content-area {
            padding: 16px !important;
        }

        .stats-grid,
        .dashboard-cards,
        .stat-grid,
        .nav-grid,
        .overview-grid,
        .metric-list {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }

        .table-toolbar-controls {
            justify-content: stretch !important;
        }

        .table-toolbar-controls .toolbar-search,
        .table-toolbar-controls .form-select,
        .table-toolbar-controls .btn {
            width: 100% !important;
        }
    }

    @media (max-width: 768px) {
        .top-bar {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 12px !important;
        }

        .sidebar-topbar-main {
            width: 100% !important;
        }

        .top-bar-right {
            justify-content: flex-start !important;
        }

        .top-bar-date {
            width: 100% !important;
        }

        .top-bar-user-chip {
            width: 100% !important;
            justify-content: flex-start !important;
        }

        .top-bar-user-chip .chip-name,
        .top-bar-user-chip .chip-role {
            max-width: none !important;
        }

        .section-panel,
        .hero-card,
        .content-card,
        .table-card,
        .chart-card,
        .card,
        .stats-card,
        .overview-box,
        .branch-block,
        .metric-item {
            border-radius: 18px !important;
        }

        .stats-grid,
        .dashboard-cards,
        .stat-grid,
        .nav-grid,
        .overview-grid,
        .metric-list {
            grid-template-columns: 1fr !important;
        }

        .table-toolbar-controls {
            justify-content: stretch !important;
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

        .table-identity {
            min-width: 0 !important;
        }

        .table-id {
            white-space: normal !important;
        }

        .sidebar-toast {
            left: 12px;
            right: 12px;
            top: 12px;
            min-width: 0;
            max-width: none;
        }
    }

    @media (max-width: 576px) {
        .sidebar.sidebar-unified {
            left: 8px !important;
            top: 8px !important;
            width: calc(100vw - 16px) !important;
            max-width: calc(100vw - 16px) !important;
            min-height: calc(100dvh - 16px) !important;
            height: calc(100dvh - 16px) !important;
            border-radius: 24px !important;
        }

        .sidebar-mobile-toggle,
        #sidebarToggleBtn,
        #menuToggleBtn {
            width: 42px !important;
            height: 42px !important;
            border-radius: 14px !important;
        }

        .sidebar-brand {
            padding: 18px 16px;
        }

        .sidebar-brand-inner {
            gap: 12px;
        }

        .sidebar-brand-media {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            font-size: 16px;
        }

        .sidebar-brand-kicker {
            font-size: 9px;
            padding: 4px 8px;
        }

        .sidebar-brand-title {
            font-size: 16px;
        }

        .sidebar-brand-subtitle {
            font-size: 10px;
        }

        .menu-title {
            padding: 8px 12px 6px;
            font-size: 10px;
        }

        .menu-item {
            padding: 12px 14px !important;
            font-size: 13px !important;
            gap: 10px;
        }

        .menu-item i {
            width: 18px;
            font-size: 15px;
        }

        .menu-item-badge {
            font-size: 9px;
            padding: 2px 6px;
        }

        .menu-counter-badge {
            min-width: 20px;
            height: 18px;
            font-size: 10px;
        }

        .main-content {
            padding: 12px !important;
        }

        .content-area {
            padding: 12px !important;
        }

        .top-bar {
            padding: 12px !important;
        }

        .page-title {
            font-size: 17px !important;
            gap: 8px !important;
        }

        .top-bar-user-chip {
            padding: 6px 10px !important;
        }

        .top-bar-user-chip .chip-info {
            gap: 2px !important;
        }

        .dashboard-user-card {
            padding: 14px !important;
        }

        .dashboard-user-card .user-name-welcome {
            font-size: 20px !important;
        }

        .dashboard-user-card img,
        .dashboard-user-card .user-avatar-fallback {
            width: 56px !important;
            height: 56px !important;
        }

        .dashboard-reservas-banner {
            padding: 14px !important;
            gap: 12px !important;
        }

        .dashboard-reservas-left {
            gap: 12px !important;
        }

        .dashboard-reservas-icon {
            width: 44px !important;
            height: 44px !important;
            font-size: 18px !important;
        }

        .dashboard-reservas-title {
            font-size: 16px !important;
        }

        .dashboard-reservas-subtitle {
            font-size: 12px !important;
        }

        .dashboard-reservas-meta {
            width: 100% !important;
            align-items: stretch !important;
        }

        .dashboard-reservas-badge {
            width: 100% !important;
            min-width: 0 !important;
            padding: 9px 12px !important;
            font-size: 13px !important;
        }

        .stat-card {
            padding: 14px !important;
        }

        .stat-value {
            font-size: 24px !important;
        }

        .stat-label {
            font-size: 11px !important;
        }

        .table-responsive {
            margin-bottom: 0;
        }

        .table th,
        .table td {
            white-space: normal !important;
        }

        .modal-content {
            margin: 8px !important;
        }
    }
</style>
<nav class="sidebar sidebar-unified col-md-3 col-lg-2 d-md-block">
    <div class="sidebar-brand">
        <div class="sidebar-brand-inner">
            <div class="sidebar-brand-media">
                <?php if ($restauranteBrandLogo !== ''): ?>
                    <img src="<?php echo htmlspecialchars($restauranteBrandLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($restauranteBrandNome, ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <?php echo htmlspecialchars($restauranteBrandInicial, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-brand-copy">
                <span class="sidebar-brand-kicker">Restaurante</span>
                <h2 class="sidebar-brand-title"><?php echo htmlspecialchars($restauranteBrandNome, ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="sidebar-brand-subtitle">Gestão Premium</span>
            </div>
        </div>
    </div>
    <div class="sidebar-menu">
        <?php if ($contextoFilialAtivo): ?>
            <div class="sidebar-context">
                <strong>Filial ativa</strong>
                <small><?php echo htmlspecialchars($filialNomeSessao !== '' ? $filialNomeSessao : ('Filial #' . $restauranteId)); ?></small>
                <small>Você está operando em uma unidade. O plano continua vinculado à matriz.</small>
                <form method="post" action="api/filial_voltar_matriz.php">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfTokenSidebar, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn-context">Voltar para matriz</button>
                </form>
            </div>
        <?php endif; ?>
        <?php $menuItemIndex = 0; ?>
        <?php foreach ($menuSections as $sectionTitle => $sectionItems): ?>
            <div class="menu-section">
                <div class="menu-title"><?php echo htmlspecialchars($sectionTitle); ?></div>

                <?php
                $directItems = $sectionItems;
                $extraItems = [];
                if ($collapseOperationalMenus && $sectionTitle === 'Operação' && count($sectionItems) > 4) {
                    $directItems = array_slice($sectionItems, 0, 4);
                    $extraItems = array_slice($sectionItems, 4);
                }
                ?>

                <?php foreach ($directItems as $url):
                    if (!isset($allItems[$url])) {
                        continue;
                    }
                    $item = $allItems[$url];
                    $isActive = $currentPage === $url;
                    $isPrimary = $primaryAction === $url;
                    $menuItemIndex++;
                    $counterType = '';
                    if ($url === 'pedidos.php' && in_array($perfil, ['GARCOM'], true)) {
                        $counterType = 'pedidos';
                    }
                    if ($url === 'caixa_mesas.php' && in_array($perfil, ['CAIXA'], true)) {
                        $counterType = 'contas-abertas';
                    }
                    if ($url === 'mesas.php' && in_array($perfil, ['ADMIN', 'GARCOM'], true)) {
                        $counterType = 'reservas';
                    }
                ?>
                    <a class="menu-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $isPrimary ? 'menu-item-primary' : ''; ?>" href="<?php echo htmlspecialchars($url); ?>" style="--item-index: <?php echo $menuItemIndex; ?>;" <?php echo $counterType !== '' ? 'data-counter-type="' . htmlspecialchars($counterType) . '"' : ''; ?>>
                        <i class="fas <?php echo $item['icon']; ?>"></i> <?php echo htmlspecialchars($item['label']); ?>
                        <?php if ($isPrimary): ?><span class="menu-item-badge">Principal</span><?php endif; ?>
                        <?php if ($counterType !== ''): ?>
                            <span class="menu-counter-badge is-empty<?php echo $counterType === 'reservas' ? ' menu-counter-alert' : ''; ?>" data-counter-badge="<?php echo htmlspecialchars($counterType); ?>">0</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!empty($extraItems)): ?>
                    <?php $detailsId = 'menu-more-' . strtolower($perfil) . '-' . substr(md5($sectionTitle), 0, 8); ?>
                    <details class="menu-more" id="<?php echo htmlspecialchars($detailsId); ?>" data-menu-more>
                        <summary>
                            <span>Mais opções</span>
                            <i class="fas fa-chevron-down menu-more-icon"></i>
                        </summary>
                        <?php foreach ($extraItems as $url):
                            if (!isset($allItems[$url])) {
                                continue;
                            }
                            $item = $allItems[$url];
                            $isActive = $currentPage === $url;
                            $isPrimary = $primaryAction === $url;
                            $menuItemIndex++;
                            $counterType = '';
                            if ($url === 'pedidos.php' && in_array($perfil, ['GARCOM'], true)) {
                                $counterType = 'pedidos';
                            }
                            if ($url === 'caixa_mesas.php' && in_array($perfil, ['CAIXA'], true)) {
                                $counterType = 'contas-abertas';
                            }
                            if ($url === 'mesas.php' && in_array($perfil, ['ADMIN', 'GARCOM'], true)) {
                                $counterType = 'reservas';
                            }
                        ?>
                            <a class="menu-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $isPrimary ? 'menu-item-primary' : ''; ?>" href="<?php echo htmlspecialchars($url); ?>" style="--item-index: <?php echo $menuItemIndex; ?>; margin-top: 6px;" <?php echo $counterType !== '' ? 'data-counter-type="' . htmlspecialchars($counterType) . '"' : ''; ?>>
                                <i class="fas <?php echo $item['icon']; ?>"></i> <?php echo htmlspecialchars($item['label']); ?>
                                <?php if ($isPrimary): ?><span class="menu-item-badge">Principal</span><?php endif; ?>
                                <?php if ($counterType !== ''): ?>
                                    <span class="menu-counter-badge is-empty<?php echo $counterType === 'reservas' ? ' menu-counter-alert' : ''; ?>" data-counter-badge="<?php echo htmlspecialchars($counterType); ?>">0</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo substr($_SESSION['nome'] ?? 'U', 0, 2); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['nome'] ?? 'Usuário'); ?></div>
                <div class="user-role"><?php echo $perfil; ?></div>
            </div>
        </div>
        <a class="menu-item" href="logout.php" style="margin-top: 12px;"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</nav>
<script>
    (function initSidebarEnhancements() {
        const body = document.body;
        const sidebar = document.querySelector('.sidebar.sidebar-unified');
        let backdrop = document.querySelector('.sidebar-mobile-backdrop');
        let sidebarToggleButtons = Array.from(document.querySelectorAll('#sidebarToggleBtn, #menuToggleBtn, .sidebar-mobile-toggle'));
        const mobileQuery = window.matchMedia('(max-width: 1200px)');

        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'sidebar-mobile-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            body.appendChild(backdrop);
        }

        const resetLegacySidebarState = () => {
            if (!sidebar) {
                return;
            }

            sidebar.classList.remove('hide-mobile', 'sidebar-hidden');
            sidebar.style.removeProperty('display');
            sidebar.style.removeProperty('left');
            sidebar.style.removeProperty('top');
            sidebar.style.removeProperty('transform');
        };

        const placeToggleInsideTopBar = () => {
            const topBar = document.querySelector('.top-bar');
            const mainContent = document.querySelector('.main-content');

            if (!sidebarToggleButtons.length) {
                const generatedButton = document.createElement('button');
                generatedButton.type = 'button';
                generatedButton.id = 'sidebarAutoToggleBtn';
                generatedButton.className = 'sidebar-mobile-toggle';
                generatedButton.setAttribute('aria-label', 'Abrir menu');
                generatedButton.setAttribute('title', 'Abrir menu');
                generatedButton.innerHTML = '<i class="fas fa-bars"></i>';
                sidebarToggleButtons = [generatedButton];
                (topBar || mainContent || body).prepend(generatedButton);
                generatedButton.setAttribute('aria-expanded', 'false');
                generatedButton.addEventListener('click', toggleSidebarEvent, true);
            }

            if (!topBar) {
                return;
            }

            const primaryButton = sidebarToggleButtons[0];
            if (!primaryButton) {
                return;
            }

            let targetContainer = topBar.querySelector('.top-bar-main, .sidebar-topbar-main');
            if (!targetContainer) {
                targetContainer = document.createElement('div');
                targetContainer.className = 'sidebar-topbar-main';

                const firstNonToggleChild = Array.from(topBar.children).find((child) => !sidebarToggleButtons.includes(child));
                if (firstNonToggleChild) {
                    topBar.insertBefore(targetContainer, firstNonToggleChild);
                    targetContainer.appendChild(firstNonToggleChild);
                } else {
                    topBar.prepend(targetContainer);
                }
            }

            if (primaryButton.parentElement !== targetContainer) {
                targetContainer.prepend(primaryButton);
            }
        };

        const closeMobileSidebar = () => {
            body.classList.remove('sidebar-mobile-open');
            resetLegacySidebarState();
            sidebarToggleButtons.forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };

        const openMobileSidebar = () => {
            if (!mobileQuery.matches) {
                return;
            }

            body.classList.add('sidebar-mobile-open');
            resetLegacySidebarState();
            sidebarToggleButtons.forEach((button) => button.setAttribute('aria-expanded', 'true'));
        };

        const syncSidebarLayout = () => {
            resetLegacySidebarState();
            if (!mobileQuery.matches) {
                body.classList.remove('sidebar-mobile-open');
            }
        };

        const toggleSidebarEvent = (event) => {
            if (!mobileQuery.matches) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            if (body.classList.contains('sidebar-mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        };

        sidebarToggleButtons.forEach((button) => {
            button.setAttribute('aria-expanded', 'false');
            button.addEventListener('click', toggleSidebarEvent, true);
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeMobileSidebar);
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMobileSidebar();
            }
        });

        document.addEventListener('click', (event) => {
            if (!mobileQuery.matches || !body.classList.contains('sidebar-mobile-open') || !sidebar) {
                return;
            }

            if (sidebar.contains(event.target)) {
                return;
            }

            if (sidebarToggleButtons.some((button) => button.contains(event.target))) {
                return;
            }

            closeMobileSidebar();
        });

        if (sidebar) {
            sidebar.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    closeMobileSidebar();
                });
            });
        }

        placeToggleInsideTopBar();
        syncSidebarLayout();
        window.addEventListener('resize', syncSidebarLayout);
        window.addEventListener('load', syncSidebarLayout);
        window.addEventListener('load', placeToggleInsideTopBar);
        setTimeout(() => {
            placeToggleInsideTopBar();
            syncSidebarLayout();
        }, 120);
        setTimeout(() => {
            placeToggleInsideTopBar();
            syncSidebarLayout();
        }, 420);
        setTimeout(() => {
            placeToggleInsideTopBar();
            syncSidebarLayout();
        }, 900);

        const restauranteId = <?php echo (int)$restauranteId; ?>;
        const perfilAtual = <?php echo json_encode($perfil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const menuMores = document.querySelectorAll('[data-menu-more]');
        menuMores.forEach((detailsEl) => {
            const key = 'sidebar.menuMore.' + (detailsEl.id || 'default');
            try {
                const saved = localStorage.getItem(key);
                if (saved === '1') detailsEl.open = true;
                if (saved === '0') detailsEl.open = false;
            } catch (e) {
                // Ignore storage errors (private mode, blocked storage, etc.)
            }

            detailsEl.addEventListener('toggle', () => {
                try {
                    localStorage.setItem(key, detailsEl.open ? '1' : '0');
                } catch (e) {
                    // Ignore storage errors.
                }
            });
        });

        const updateCounter = (type, value) => {
            const badge = document.querySelector('[data-counter-badge="' + type + '"]');
            if (!badge) return;

            const num = Number(value || 0);
            badge.textContent = String(num);
            badge.classList.toggle('is-empty', num <= 0);
        };

        const reqOpts = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const reservasBadge = document.querySelector('[data-counter-badge="reservas"]');
        const reservasEndpoint = 'api/reservas_alertas.php';
        const reservasStorageKey = 'sidebar.reservas.lastId.' + restauranteId + '.' + perfilAtual;
        let reservasUltimoId = 0;
        let reservasInicializado = false;

        try {
            const saved = localStorage.getItem(reservasStorageKey);
            const parsed = Number(saved || 0);
            if (Number.isFinite(parsed) && parsed > 0) {
                reservasUltimoId = parsed;
            }
        } catch (e) {
            // Ignore storage errors.
        }

        const toastEl = document.createElement('div');
        toastEl.className = 'sidebar-toast';
        toastEl.setAttribute('role', 'status');
        toastEl.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastEl);

        const updateReservaBadge = (value) => {
            updateCounter('reservas', value);
        };

        const persistReservaId = (id) => {
            const num = Number(id || 0);
            if (!(num > 0)) {
                return;
            }

            reservasUltimoId = num;
            try {
                localStorage.setItem(reservasStorageKey, String(num));
            } catch (e) {
                // Ignore storage errors.
            }
        };

        window.sidebarReservasMarcarVisto = persistReservaId;

        const showReservaToast = (titulo, mensagem) => {
            toastEl.innerHTML = '<strong>' + titulo + '</strong><small>' + mensagem + '</small>';
            toastEl.style.display = 'block';

            clearTimeout(toastEl._hideTimer);
            toastEl._hideTimer = setTimeout(() => {
                toastEl.style.display = 'none';
            }, 4500);
        };

        const isMesasPage = () => {
            return window.location.pathname.toLowerCase().endsWith('/mesas.php');
        };

        const refreshReservasAlertas = () => {
            if (!reservasBadge) {
                return;
            }

            fetch(reservasEndpoint, reqOpts)
                .then((r) => r.json())
                .then((data) => {
                    if (!data || !data.success) {
                        return;
                    }

                    updateReservaBadge(data.pendentes || 0);

                    const ultima = data.ultima_pendente || null;
                    const ultimoId = Number(ultima && ultima.id ? ultima.id : 0);

                    if (reservasInicializado && ultimoId > 0 && ultimoId > reservasUltimoId) {
                        const nomeCliente = ultima && ultima.nome_cliente ? ultima.nome_cliente : 'Cliente';
                        const dataReserva = ultima && ultima.data_reserva ? ultima.data_reserva : '';
                        const horaReserva = ultima && ultima.hora_reserva ? String(ultima.hora_reserva).slice(0, 5) : '';
                        const mesaNumero = ultima && ultima.mesa_numero ? ' Mesa ' + ultima.mesa_numero + '.' : '';

                        showReservaToast(
                            'Nova reserva recebida',
                            nomeCliente + ' para ' + dataReserva + ' às ' + horaReserva + '.' + mesaNumero
                        );

                        if (isMesasPage()) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1800);
                        }
                    }

                    if (ultimoId > 0) {
                        persistReservaId(ultimoId);
                    }

                    reservasInicializado = true;
                })
                .catch(() => {
                    // Silencioso: a badge continua como estava até a próxima tentativa.
                });
        };

        if (document.querySelector('[data-counter-type="pedidos"]')) {
            fetch('api/garcom_pedidos.php', reqOpts)
                .then((r) => r.json())
                .then((data) => {
                    if (data && data.success) {
                        updateCounter('pedidos', data.total_pedidos || 0);
                    }
                })
                .catch(() => {
                    // Noop: sidebar should continue rendering normally.
                });
        }

        if (document.querySelector('[data-counter-type="contas-abertas"]')) {
            fetch('api/caixa_mesas_abertas.php', reqOpts)
                .then((r) => r.json())
                .then((data) => {
                    if (data && data.success) {
                        updateCounter('contas-abertas', data.qtd_abertas || 0);
                    }
                })
                .catch(() => {
                    // Noop: sidebar should continue rendering normally.
                });
        }

        refreshReservasAlertas();
        setInterval(refreshReservasAlertas, 25000);
    })();
</script>
<script>
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
            } catch (e) {
                // Fallback para fetch keepalive abaixo.
            }

            try {
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            } catch (e) {
                // Logout segue normalmente mesmo sem confirmar presenca offline.
            }
        };

        logoutLinks.forEach((link) => {
            link.addEventListener('click', markOffline, {
                passive: true
            });
        });
    })();
</script>
<script>
    // Garantia extra: fecha o menu mobile ao clicar em Backup ou Funcionários e Turnos
    document.addEventListener('DOMContentLoaded', function() {
        const body = document.body;
        const mobileQuery = window.matchMedia('(max-width: 1200px)');

        function closeMobileSidebar() {
            body.classList.remove('sidebar-mobile-open');
        }
        // Seleciona links pelo href
        const links = [
            document.querySelector('a.menu-item[href*="backup.php"]'),
            document.querySelector('a.menu-item[href*="funcionarios_turnos.php"]')
        ];
        links.forEach(function(link) {
            if (link) {
                link.addEventListener('click', function() {
                    if (mobileQuery.matches) {
                        closeMobileSidebar();
                        // Oculta o toggle imediatamente ao clicar
                        body.classList.add('funcionarios-turnos-navegando');
                        setTimeout(function() {
                            body.classList.remove('funcionarios-turnos-navegando');
                        }, 1200); // tempo suficiente para navegação
                    }
                });
            }
        });
    });
</script>
