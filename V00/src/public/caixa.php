<?php
// Proteção da página
include_once __DIR__ . '/../config/auth_check.php';
requirePermissionOrRedirect(['ADMIN', 'CAIXA']);

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/turno_helpers.php';
include_once __DIR__ . '/../Model/Caixa.php';
include_once __DIR__ . '/../Model/Venda.php';
include_once __DIR__ . '/../Service/TurnoService.php';

$database = new Database();
$db = $database->getConnection();
$turnoService = new TurnoService($database);
$turnoAtivoOperador = $turnoService->obterTurnoAtivoUsuario((int)$_SESSION['usuario_id'], (int)$_SESSION['restaurante_id']);

$caixa = new Caixa($db);
$caixa_aberto = $caixa->buscarAberto($_SESSION['restaurante_id']);

$total_vendas = 0;
if ($caixa_aberto) {
    $total_vendas = $caixa->totalVendas($caixa_aberto['id']);
}

$stmt_historico = $caixa->listar($_SESSION['restaurante_id'], 15);
$historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);

// Preparar dados do usuário para exibição no topo
$public_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$resolve_foto_url = function ($path) use ($public_base) {
    if (empty($path)) return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/') === 0) return $path;
    return $public_base . '/' . ltrim($path, '/');
};
$top_foto_url = $resolve_foto_url($_SESSION['foto'] ?? '');
$top_nome_usuario = $_SESSION['nome'] ?? 'Usuário';
$top_perfil_usuario = $_SESSION['perfil'] ?? 'USER';
$top_nome_partes = preg_split('/\s+/', trim($top_nome_usuario));
$top_iniciais = strtoupper(substr($top_nome_partes[0] ?? 'U', 0, 1) . substr($top_nome_partes[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caixa - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B35;
            --primary-dark: #e55a2b;
            --secondary: #F7931E;
            --dark: #0f0f23;
            --dark-2: #1a1a2e;
            --dark-3: #16213e;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --text: #1e293b;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --bg: #f8fafc;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
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
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.15), rgba(247, 147, 30, 0.05));
        }

        .sidebar-brand h2 {
            color: white;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand h2 i {
            color: var(--primary);
            font-size: 24px;
        }

        .sidebar-brand span {
            display: block;
            color: var(--text-muted);
            font-size: 11px;
            margin-top: 4px;
            margin-left: 36px;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            flex: 1;
            padding: 20px 12px;
            overflow-y: auto;
        }

        .menu-title {
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 12px 16px 8px;
            font-weight: 600;
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

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
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

        .main-content {
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
            background: var(--bg);
        }

        .top-bar {
            background: white;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--primary);
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .top-bar-date {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            color: #334155;
            font-weight: 700;
            white-space: nowrap;
            padding: 7px 12px;
            border-radius: 999px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }

        .top-bar-user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 5px 14px rgba(15, 23, 42, 0.06);
        }

        .top-bar-user-chip img,
        .top-bar-user-chip .chip-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .top-bar-user-chip .chip-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #c2410c, #b45309);
            color: #fff;
            font-weight: 700;
            font-size: 12px;
        }

        .top-bar-user-chip .chip-name {
            font-size: 15px;
            color: var(--text);
            font-weight: 700;
            line-height: 1;
        }

        .top-bar-user-chip .chip-role {
            font-size: 11px;
            color: #1d4ed8;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(29, 78, 216, 0.12);
        }

        .top-bar-user-chip .chip-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .content-area {
            padding: 32px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: none;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            background: white;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary);
        }

        .card-body {
            padding: 0;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--bg);
            padding: 16px 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
            border: none;
        }

        .table tbody td {
            padding: 16px 20px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .table tbody tr:hover {
            background: rgba(255, 107, 53, 0.02);
        }

        .badge-custom {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #20c997);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background: var(--text-light);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
        }

        .modal-content {
            border: none;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
            border-radius: 24px 24px 0 0;
        }

        .modal-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 20px;
        }

        .modal-body {
            padding: 28px;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid var(--border);
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }

        .caixa-status-card,
        .caixa-turno-actions {
            align-items: stretch;
        }

        .caixa-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .top-bar {
                padding: 12px 16px;
                flex-wrap: wrap;
                gap: 10px;
            }

            .page-title {
                font-size: 18px;
            }

            .top-bar-right {
                width: 100%;
                justify-content: space-between;
            }

            .top-bar-date {
                font-size: 12px;
            }

            .top-bar-user-chip {
                padding: 7px 10px;
                gap: 8px;
            }

            .top-bar-user-chip .chip-name {
                font-size: 13px;
            }

            .top-bar-user-chip .chip-role {
                font-size: 10px;
            }

            .content-area {
                padding: 20px;
            }

            .caixa-turno-actions {
                justify-content: stretch !important;
            }

            .caixa-turno-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 12px;
            }

            .top-bar {
                padding: 12px 14px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 16px;
            }

            .page-title {
                font-size: 17px;
                gap: 8px;
            }

            .top-bar-right {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
            }

            .top-bar-date {
                width: 100%;
                justify-content: flex-start;
                white-space: normal;
            }

            .top-bar-user-chip {
                width: 100%;
                padding: 7px 10px;
            }

            .content-area {
                padding: 12px;
            }

            .caixa-status-header,
            .caixa-turno-actions {
                flex-direction: column;
                align-items: stretch !important;
            }

            .caixa-status-header .btn,
            .caixa-turno-actions .btn {
                width: 100%;
            }

            .card {
                border-radius: 18px;
            }

            .card-header {
                padding: 16px 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .card-title {
                font-size: 16px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 24px;
            }

            .stat-label {
                font-size: 12px;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 14px;
                font-size: 12px;
                white-space: normal;
            }

            .badge-custom {
                white-space: normal;
            }

            .modal-dialog {
                margin: 10px;
            }

            .modal-content {
                border-radius: 18px;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding-left: 16px;
                padding-right: 16px;
            }

            .modal-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .modal-footer .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

            <!-- MAIN CONTENT -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <div class="top-bar">
                    <h1 class="page-title"><i class="fas fa-money-bill-wave"></i> Controle de Caixa</h1>
                    <div class="top-bar-right">
                        <span class="top-bar-date"><i class="far fa-clock"></i><span id="topBarDateTime"><?php echo date('d/m/Y H:i'); ?></span></span>
                        <div class="top-bar-user-chip">
                            <?php if (!empty($top_foto_url)): ?>
                                <img src="<?php echo htmlspecialchars($top_foto_url); ?>" alt="<?php echo htmlspecialchars($top_nome_usuario); ?>">
                            <?php else: ?>
                                <span class="chip-avatar"><?php echo htmlspecialchars($top_iniciais); ?></span>
                            <?php endif; ?>
                            <span class="chip-info">
                                <span class="chip-name"><?php echo htmlspecialchars($top_nome_usuario); ?></span>
                                <span class="chip-role"><?php echo htmlspecialchars($top_perfil_usuario); ?></span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="content-area">
                    <!-- CAIXA ABERTO -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-clock"></i> Turno do Operador</h3>
                        </div>
                        <div class="card-body" style="padding:24px;">
                            <div id="turnoOperadorAlert" class="alert" style="display:none;"></div>
                            <div class="row g-3 align-items-center caixa-status-card">
                                <div class="col-md-8">
                                    <?php if ($turnoAtivoOperador): ?>
                                        <div class="d-flex flex-column gap-2">
                                            <div><strong>Status:</strong> <span class="badge-custom badge-success">ATIVO</span></div>
                                            <div><strong>Cargo:</strong> <?php echo htmlspecialchars($turnoAtivoOperador['cargo'] ?? $top_perfil_usuario); ?></div>
                                            <div><strong>Início:</strong> <?php echo htmlspecialchars(($turnoAtivoOperador['data'] ?? '') . ' ' . substr((string)($turnoAtivoOperador['hora_entrada'] ?? ''), 0, 5)); ?></div>
                                            <div><strong>Tempo de turno:</strong> <span id="turnoOperadorDuracao"><?php echo htmlspecialchars((string)($turnoAtivoOperador['duracao_formatada'] ?? '0 min')); ?></span></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted">
                                            Nenhum turno ativo para este operador. Inicie o turno antes de abrir, fechar ou operar o caixa.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 d-flex gap-2 justify-content-md-end caixa-turno-actions">
                                    <button class="btn btn-success" type="button" onclick="operarTurno('iniciar')">
                                        <i class="fas fa-play me-2"></i>Iniciar Turno
                                    </button>
                                    <button class="btn btn-danger" type="button" onclick="operarTurno('encerrar')">
                                        <i class="fas fa-stop me-2"></i>Encerrar Turno
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($caixa_aberto): ?>
                        <div class="card mb-4" style="border-left: 5px solid var(--success);">
                            <div class="card-body">
                                <div class="caixa-status-header mb-4">
                                    <div>
                                        <h4 style="color: var(--success);"><i class="fas fa-check-circle me-2"></i>Caixa Aberto</h4>
                                        <p class="text-muted mb-0">
                                            Aberto em: <?php echo date('d/m/Y H:i', strtotime($caixa_aberto['data_abertura'])); ?>
                                            &bull; Operador: <?php echo htmlspecialchars($caixa_aberto['usuario_nome'] ?? $_SESSION['nome']); ?>
                                        </p>
                                    </div>
                                    <button class="btn btn-danger" onclick="abrirModalFechar()"><i class="fas fa-lock me-2"></i>Fechar Caixa</button>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="stat-card">
                                            <div class="stat-value"><?php echo number_format($caixa_aberto['saldo_inicial'], 2, ',', '.'); ?></div>
                                            <div class="stat-label">Valor de Abertura (MZN)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="stat-card">
                                            <div class="stat-value" style="color: var(--success);"><?php echo number_format($total_vendas, 2, ',', '.'); ?></div>
                                            <div class="stat-label">Total em Vendas (MZN)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="stat-card" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
                                            <div class="stat-value" style="color: white;"><?php echo number_format($caixa_aberto['saldo_inicial'] + $total_vendas, 2, ',', '.'); ?></div>
                                            <div class="stat-label" style="color: rgba(255,255,255,0.8);">Saldo Esperado (MZN)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card mb-4" style="border-left: 5px solid var(--danger);">
                            <div class="card-body">
                                <div class="caixa-status-header">
                                    <div>
                                        <h4 style="color: var(--danger);"><i class="fas fa-lock me-2"></i>Caixa Fechado</h4>
                                        <p class="text-muted mb-0">Abra o caixa para iniciar as vendas do dia.</p>
                                    </div>
                                    <button class="btn btn-success" onclick="abrirModalAbrir()"><i class="fas fa-lock-open me-2"></i>Abrir Caixa</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- HISTÓRICO -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-history"></i> Histórico de Caixas</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Operador</th>
                                            <th>Abertura</th>
                                            <th>Fechamento</th>
                                            <th>Total Vendas</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historico as $h): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($h['data_abertura'])); ?></td>
                                                <td><?php echo htmlspecialchars($h['usuario_nome'] ?? '—'); ?></td>
                                                <td><?php echo number_format($h['saldo_inicial'], 2, ',', '.'); ?> MZN</td>
                                                <td><?php echo $h['saldo_final'] ? number_format($h['saldo_final'], 2, ',', '.') . ' MZN' : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo number_format($caixa->totalVendas($h['id']), 2, ',', '.'); ?> MZN</td>
                                                <td><span class="badge-custom badge-<?php echo $h['status'] == 'ABERTO' ? 'success' : 'danger'; ?>"><?php echo $h['status']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($historico)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Nenhum registro</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- MODAL ABRIR CAIXA -->
    <div class="modal fade" id="modalAbrir" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-lock-open me-2"></i>Abrir Caixa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Informe o valor inicial em dinheiro no caixa.</p>
                    <div class="alert" id="alertAbrir" style="display: none;"></div>
                    <form id="formAbrir">
                        <div class="mb-3">
                            <label class="form-label">Valor de Abertura (MZN) *</label>
                            <input type="number" id="valor_abertura" class="form-control" step="0.01" min="0" required placeholder="0.00">
                            <small class="text-muted">Valor em dinheiro para troco</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formAbrir" class="btn btn-success"><i class="fas fa-check me-2"></i>Abrir Caixa</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FECHAR CAIXA -->
    <div class="modal fade" id="modalFechar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-lock me-2"></i>Fechar Caixa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="alertFechar" style="display: none;"></div>
                    <form id="formFechar">
                        <div class="mb-3">
                            <label class="form-label">Valor de Fechamento (MZN) *</label>
                            <input type="number" id="valor_fechamento" class="form-control" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div style="background: var(--bg); border-radius: 16px; padding: 20px;">
                            <div class="d-flex justify-content-between mb-2"><span>Abertura:</span><strong><?php echo $caixa_aberto ? number_format($caixa_aberto['saldo_inicial'], 2, ',', '.') : '0,00'; ?> MZN</strong></div>
                            <div class="d-flex justify-content-between mb-2"><span>Vendas:</span><strong><?php echo number_format($total_vendas, 2, ',', '.'); ?> MZN</strong></div>
                            <hr>
                            <div class="d-flex justify-content-between"><span>Esperado:</span><strong style="color: var(--success);"><?php $esperado = $caixa_aberto ? ($caixa_aberto['saldo_inicial'] + $total_vendas) : 0;
                                                                                                                                        echo number_format($esperado, 2, ',', '.'); ?> MZN</strong></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formFechar" class="btn btn-danger"><i class="fas fa-lock me-2"></i>Fechar Caixa</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/caixa.js"></script>
</body>

</html>

