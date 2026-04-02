<?php
// Proteção da página
include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/plano_check.php';
include_once __DIR__ . '/../config/pedido_schema.php';
$restaurante_id = $_SESSION['restaurante_id'] ?? 0;
if ($restaurante_id <= 0) {
    header("Location: index.php?erro=sem_restaurante");
    exit;
}

requirePermissionOrRedirect(['ADMIN', 'CAIXA', 'GARCOM', 'COZINHA']);

plano_verificar_funcionalidade($restaurante_id, 'pedidos_online');
include_once __DIR__ . '/../config/database.php';

// Verificar restaurante_id
$restaurante_id = $_SESSION['restaurante_id'] ?? 0;
if ($restaurante_id <= 0) {
    header("Location: index.php?erro=sem_restaurante");
    exit;
}
include_once __DIR__ . '/../Model/Mesa.php';

function normalizar_status_pedido($status)
{
    $status = strtoupper(trim((string)$status));
    $map = [
        'PENDENTE' => 'NOVO',
        'CONFIRMADO' => 'PREPARANDO',
        'NOVO' => 'NOVO',
        'PREPARANDO' => 'PREPARANDO',
        'PRONTO' => 'PRONTO',
        'ENTREGUE' => 'ENTREGUE',
        'PAGO' => 'PAGO',
        'CANCELADO' => 'CANCELADO'
    ];
    return $map[$status] ?? 'NOVO';
}

// Conectar ao banco
$database = new Database();
$db = $database->getConnection();
pedido_schema_garantir($db);

// URL base para QR Code
$protocol     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host         = $_SERVER['HTTP_HOST'];
$base_url     = $protocol . '://' . $host;
$script_dir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$cardapio_url = $base_url . $script_dir . '/cardapio.php?rid=' . $_SESSION['restaurante_id'];

// Buscar mesas
$mesa_obj    = new Mesa($db);
$stmt_mesas  = $mesa_obj->listar($_SESSION['restaurante_id']);
$todas_mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

// Buscar pedidos do dia
$query = "SELECT p.*, m.numero as mesa_numero,
          (SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = p.id) as total_itens
          FROM pedidos p
          LEFT JOIN mesas m ON p.mesa_id = m.id
          WHERE p.restaurante_id = :rid
          AND DATE(p.criado_em) = CURDATE()
          ORDER BY p.criado_em DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':rid', $_SESSION['restaurante_id']);
$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pedidos_ui = [];
foreach ($pedidos as $pedido) {
    $pedido['status'] = normalizar_status_pedido($pedido['status'] ?? 'NOVO');
    $pedido['origem'] = pedido_normalizar_origem($pedido['origem'] ?? 'BALCAO');
    $pedidos_ui[] = $pedido;
}
$pedidos = $pedidos_ui;

// Contadores por status
$contadores = ['NOVO' => 0, 'PREPARANDO' => 0, 'PRONTO' => 0, 'ENTREGUE' => 0, 'PAGO' => 0, 'CANCELADO' => 0];
foreach ($pedidos as $p) {
    if (isset($contadores[$p['status']])) {
        $contadores[$p['status']]++;
    }
}

$pode_receber_pagamento = checkPermission(['ADMIN', 'CAIXA']);
$pode_gerir_fluxo_pedido = checkPermission(['ADMIN']);
$pode_cancelar_pedido = checkPermission(['ADMIN']);
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Online - Sistema de Restaurante</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
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
            padding: 32px;
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
            margin: -32px -32px 32px -32px;
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

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
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
            padding: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
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

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .badge-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-light);
        }

        .pedido-origin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .pedido-origin-badge.qr {
            background: rgba(59, 130, 246, 0.12);
            color: #1d4ed8;
        }

        .pedido-origin-badge.garcom {
            background: rgba(245, 158, 11, 0.14);
            color: #b45309;
        }

        .pedido-origin-badge.balcao {
            background: rgba(16, 185, 129, 0.12);
            color: #047857;
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

        .btn-info {
            background: var(--info);
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
        }

        .btn-sm {
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
        }

        .kanban {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .kanban-col {
            background: var(--bg);
            border-radius: 16px;
            padding: 16px;
            min-height: 300px;
        }

        .kanban-header {
            font-weight: 700;
            font-size: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pedido-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: var(--shadow);
            border-left: 4px solid #dee2e6;
            transition: all 0.2s;
        }

        .pedido-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .status-NOVO {
            border-left-color: #ffc107;
        }

        .status-PREPARANDO {
            border-left-color: #FF6B35;
        }

        .status-PRONTO {
            border-left-color: #28a745;
        }

        .status-ENTREGUE {
            border-left-color: #6c757d;
        }

        .status-PAGO {
            border-left-color: #10b981;
        }

        .status-CANCELADO {
            border-left-color: #dc3545;
        }

        .bg-NOVO {
            background: rgba(255, 193, 7, 0.15) !important;
            color: #856404 !important;
        }

        .bg-PREPARANDO {
            background: rgba(255, 107, 53, 0.15) !important;
            color: #c0392b !important;
        }

        .bg-PRONTO {
            background: rgba(40, 167, 69, 0.15) !important;
            color: #155724 !important;
        }

        .bg-ENTREGUE {
            background: rgba(108, 117, 125, 0.15) !important;
            color: #383d41 !important;
        }

        .bg-PAGO {
            background: rgba(16, 185, 129, 0.15) !important;
            color: #065f46 !important;
        }

        .bg-CANCELADO {
            background: rgba(220, 53, 69, 0.15) !important;
            color: #dc3545 !important;
        }

        .avatar-lg {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            border: 3px solid white;
            box-shadow: var(--shadow);
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

        @media (max-width: 991px) {
            .sidebar {
                width: 100%;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }

            .top-bar {
                margin: 0 0 20px 0;
            }

            .pedidos-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .pedidos-header-actions {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 12px;
            }

            .pedidos-header {
                gap: 12px;
                margin-bottom: 16px;
            }

            .pedidos-header > div:first-child {
                width: 100%;
            }

            .pedidos-header-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
                gap: 8px;
            }

            .avatar-lg {
                width: 42px;
                height: 42px;
            }

            .pedidos-qr-card .card-body {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 14px !important;
            }

            .pedidos-qr-card .card-body>div:first-child {
                width: 100% !important;
                text-align: center;
            }

            #qrCodeCanvas {
                width: 100% !important;
                max-width: 220px;
                height: auto !important;
            }

            .pedidos-qr-actions {
                width: 100%;
                flex-direction: column;
            }

            .pedidos-qr-actions .btn,
            .pedidos-header-actions .btn,
            .pedidos-header-actions #rtBadge {
                width: 100%;
                text-align: center;
            }

            .row.g-3.mb-4>[class*="col-"] {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .row.g-3.mb-4 .card-body {
                padding: 14px;
            }

            .row.g-3.mb-4 .fw-bold {
                font-size: 22px !important;
            }

            .kanban {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .kanban-col {
                min-height: auto;
                padding: 14px;
            }

            .kanban-header {
                padding: 10px 12px;
            }

            .pedido-card {
                padding: 14px;
            }

            .modal-dialog {
                margin: 10px;
            }

            .modal-content {
                border-radius: 18px;
            }

            .modal-body,
            .modal-footer {
                padding: 16px;
            }

            .modal-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .modal-footer .btn {
                width: 100%;
            }
        }

        .sound-banner {
            position: fixed;
            left: 50%;
            bottom: 20px;
            transform: translateX(-50%);
            z-index: 5000;
            display: none;
            align-items: center;
            gap: 12px;
            background: rgba(15, 23, 42, 0.96);
            color: #fff;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 14px;
            padding: 12px 16px;
            box-shadow: 0 18px 38px rgba(0, 0, 0, 0.28);
            max-width: calc(100vw - 24px);
        }

        .sound-banner.show {
            display: flex;
        }

        .sound-banner button {
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-weight: 700;
            background: #ffb020;
            color: #111;
        }

        .pedidos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .pedidos-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pedidos-qr-card .card-body {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .pedidos-qr-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body class="premium-ui">

    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

            <!-- CONTEÚDO PRINCIPAL -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <div id="sound-banner" class="sound-banner">
                    <span><i class="fas fa-volume-up me-2"></i>Toque para ativar alertas sonoros dos pedidos</span>
                    <button type="button" id="sound-enable-btn">Ativar som</button>
                </div>

                <!-- TOP BAR -->
                <div class="pedidos-header">
                    <div>
                        <h4 class="mb-0"><i class="fas fa-mobile-alt text-primary me-2"></i>Pedidos</h4>
                        <p class="text-muted mb-0" style="font-size: 14px;">Na Cozinha → Em Preparo → Pronto → Entregue → Pago</p>
                    </div>
                    <div class="pedidos-header-actions">
                        <span id="rtBadge" class="badge bg-success">Tempo real</span>
                        <button id="btnAtualizarPedidos" class="btn btn-warning btn-sm text-dark" onclick="forcarAtualizacaoPedidos(this)"><i class="fas fa-sync-alt me-1"></i> Atualizar</button>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['nome']); ?>&background=FF6B35&color=fff&size=50" class="avatar-lg">
                    </div>
                </div>

                <!-- QR CODE SECTION -->
                <div class="card mb-4 pedidos-qr-card">
                    <div class="card-body">
                        <div class="text-center" style="width: 120px; flex-shrink: 0;">
                            <canvas id="qrCodeCanvas" class="img-fluid rounded"></canvas>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-2"><i class="fas fa-qrcode me-2"></i>QR Code do Cardápio</h5>
                            <p class="text-muted mb-2" style="font-size: 14px;">Clientes escaneiam para fazer pedidos pelo celular.</p>
                            <p class="text-muted mb-3" style="font-size: 13px;">URL: <strong><?php echo htmlspecialchars($cardapio_url); ?></strong></p>
                            <div class="pedidos-qr-actions">
                                <button class="btn btn-success btn-sm" onclick="baixarQRCode()"><i class="fas fa-download me-1"></i> Baixar</button>
                                <button class="btn btn-info btn-sm" onclick="imprimirQRCodes()"><i class="fas fa-print me-1"></i> Imprimir</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STATS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-2">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div id="countNovo" class="fw-bold text-warning" style="font-size: 28px;"><?php echo $contadores['NOVO']; ?></div>
                                <div class="text-muted small">Novos</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div id="countPreparando" class="fw-bold" style="color: #FF6B35; font-size: 28px;"><?php echo $contadores['PREPARANDO']; ?></div>
                                <div class="text-muted small">Preparando</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div id="countPronto" class="fw-bold text-success" style="font-size: 28px;"><?php echo $contadores['PRONTO']; ?></div>
                                <div class="text-muted small">Prontos</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div id="countEntregue" class="fw-bold text-muted" style="font-size: 28px;"><?php echo $contadores['ENTREGUE']; ?></div>
                                <div class="text-muted small">Entregues</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div id="countPago" class="fw-bold" style="color: #10b981; font-size: 28px;"><?php echo $contadores['PAGO']; ?></div>
                                <div class="text-muted small">Pagos</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KANBAN -->
                <div id="pedidosArea"></div>

            </main>
        </div>
    </div>

    <!-- MODAL ITENS -->
    <div class="modal fade" id="modalItens" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-list me-2"></i>Itens do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="listaItens"></div>
            </div>
        </div>
    </div>

    <!-- MODAL PAGAMENTO -->
    <div class="modal fade" id="modalPagamento" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Receber Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Estado: formulário -->
                    <div id="pagFormBody">
                        <div class="mb-2 text-muted small" id="pagPedidoInfo">Pedido</div>
                        <div class="mb-3">
                            <label class="form-label">Forma de pagamento</label>
                            <select id="pagForma" class="form-select">
                                <option value="DINHEIRO">DINHEIRO</option>
                                <option value="MPESA">MPESA</option>
                                <option value="CARTAO">CARTAO</option>
                                <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Desconto (MZN)</label>
                            <input type="number" id="pagDesconto" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observação</label>
                            <textarea id="pagObs" class="form-control" rows="2" maxlength="300" placeholder="Opcional"></textarea>
                        </div>
                        <div class="alert alert-light border mb-0">
                            Total do pedido: <strong id="pagTotalLabel">0,00 MZN</strong>
                        </div>
                    </div>
                    <!-- Estado: sucesso -->
                    <div id="pagSuccessBody" style="display:none;">
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle text-success" style="font-size:56px;"></i>
                            <h5 class="mt-3 mb-1 text-success">Pagamento Registrado!</h5>
                            <p class="text-muted small" id="pagSuccessInfo"></p>
                            <a id="btnComprovante" href="#" target="_blank" class="btn btn-primary mt-2">
                                <i class="fas fa-receipt me-1"></i> Abrir Comprovante
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="pagFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnConfirmarPagamento" onclick="confirmarPagamentoPedido()">
                        <i class="fas fa-check me-1"></i>Confirmar Pagamento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- QR Code -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>

    <script>
        var cardapioUrl = '<?php echo addslashes($cardapio_url); ?>';
        var baseUrl = '<?php echo addslashes($base_url); ?>';
        var scriptDir = '<?php echo addslashes($script_dir); ?>';
        var restauranteId = <?php echo (int)$_SESSION['restaurante_id']; ?>;
        var todasMesas = <?php echo json_encode($todas_mesas); ?>;
        var initialPedidosData = <?php echo json_encode($pedidos, JSON_UNESCAPED_UNICODE); ?>;
        var initialContadores = <?php echo json_encode($contadores, JSON_UNESCAPED_UNICODE); ?>;
        var sse = null;
        var pollingTimer = null;
        var currentPedidos = Array.isArray(initialPedidosData) ? initialPedidosData : [];
        var pedidoSelecionadoId = null;
        var canReceivePayment = <?php echo $pode_receber_pagamento ? 'true' : 'false'; ?>;
        var canManageOrderFlow = <?php echo $pode_gerir_fluxo_pedido ? 'true' : 'false'; ?>;
        var canCancelOrder = <?php echo $pode_cancelar_pedido ? 'true' : 'false'; ?>;

        function gerarQRDataURL(url, size) {
            var qr = qrcode(0, 'M');
            qr.addData(url);
            qr.make();
            var mc = qr.getModuleCount();
            var cell = Math.ceil(size / mc);
            var cv = document.createElement('canvas');
            cv.width = cell * mc;
            cv.height = cell * mc;
            var ctx = cv.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, cv.width, cv.height);
            ctx.fillStyle = '#000000';
            for (var r = 0; r < mc; r++) {
                for (var c = 0; c < mc; c++) {
                    if (qr.isDark(r, c)) {
                        ctx.fillRect(c * cell, r * cell, cell, cell);
                    }
                }
            }
            return cv.toDataURL('image/png');
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>'"]/g, function(char) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                })[char];
            });
        }

        function formatMoney(value) {
            return parseFloat(value || 0).toFixed(2).replace('.', ',') + ' MZN';
        }

        let audioCtx = null;
        let soundEnabled = false;
        let previousReadyIds = new Set();

        function getAudioCtx() {
            if (!audioCtx) audioCtx = new(window.AudioContext || window.webkitAudioContext)();
            return audioCtx;
        }

        function playBeep(freq, dur, volume = 0.5) {
            if (!soundEnabled) return;
            try {
                var ctx = getAudioCtx();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, ctx.currentTime);
                gain.gain.setValueAtTime(volume, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + dur);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + dur);
            } catch (e) {}
        }

        function playReadySound() {
            playBeep(1046, 0.18, 0.62);
            setTimeout(function() { playBeep(1175, 0.18, 0.65); }, 180);
            setTimeout(function() { playBeep(1318, 0.25, 0.7); }, 360);
            setTimeout(function() { playBeep(1568, 0.3, 0.75); }, 620);
        }

        function updateSoundBanner() {
            var banner = document.getElementById('sound-banner');
            if (banner) banner.classList.toggle('show', !soundEnabled);
        }

        async function enableSoundAlerts() {
            try {
                var ctx = getAudioCtx();
                if (ctx.state === 'suspended') await ctx.resume();
                soundEnabled = true;
                localStorage.setItem('edvis_sound_alerts', '1');
                updateSoundBanner();
                playBeep(880, 0.12, 0.4);
            } catch (e) {}
        }

        function normalizarStatus(status) {
            var s = String(status || '').toUpperCase();
            if (s === 'PENDENTE') return 'NOVO';
            if (s === 'CONFIRMADO') return 'PREPARANDO';
            if (['NOVO', 'PREPARANDO', 'PRONTO', 'ENTREGUE', 'PAGO', 'CANCELADO'].indexOf(s) >= 0) return s;
            return 'NOVO';
        }

        function normalizarOrigemPedido(origem) {
            var valor = String(origem || 'BALCAO').trim().toUpperCase();
            if (['QR', 'GARCOM', 'BALCAO'].indexOf(valor) === -1) {
                return 'BALCAO';
            }
            return valor;
        }

        function getOrigemPedidoMeta(origem) {
            var valor = normalizarOrigemPedido(origem);
            if (valor === 'QR') {
                return {
                    label: 'QR',
                    icon: 'fa-qrcode',
                    className: 'qr'
                };
            }

            if (valor === 'GARCOM') {
                return {
                    label: 'Garçom',
                    icon: 'fa-user-tie',
                    className: 'garcom'
                };
            }

            return {
                label: 'Balcão',
                icon: 'fa-store',
                className: 'balcao'
            };
        }

        function getStatusColumns() {
            return {
                NOVO: {
                    titulo: '📋 Na Cozinha',
                    subtitulo: 'Enviar para preparo',
                    proximo: 'PREPARANDO',
                    cor: 'NOVO'
                },
                PREPARANDO: {
                    titulo: '🔥 Em Preparo',
                    subtitulo: 'Cozinha preparando',
                    proximo: 'PRONTO',
                    cor: 'PREPARANDO'
                },
                PRONTO: {
                    titulo: '✅ Pronto',
                    subtitulo: 'Entregar ao cliente',
                    proximo: 'ENTREGUE',
                    cor: 'PRONTO'
                },
                ENTREGUE: {
                    titulo: '🛎 Entregue',
                    subtitulo: 'Ag. pagamento',
                    proximo: 'PAGO',
                    cor: 'ENTREGUE'
                },
                PAGO: {
                    titulo: '💰 Pago',
                    subtitulo: 'Mesa fechada',
                    proximo: null,
                    cor: 'PAGO'
                }
            };
        }

        function updateCounters(contadores) {
            document.getElementById('countNovo').textContent = contadores.NOVO || 0;
            document.getElementById('countPreparando').textContent = contadores.PREPARANDO || 0;
            document.getElementById('countPronto').textContent = contadores.PRONTO || 0;
            document.getElementById('countEntregue').textContent = contadores.ENTREGUE || 0;
            document.getElementById('countPago').textContent = contadores.PAGO || 0;
        }

        function detectReadyAlerts(pedidos) {
            var readyIds = new Set((pedidos || []).filter(function(p) {
                return normalizarStatus(p.status) === 'PRONTO';
            }).map(function(p) {
                return String(p.id);
            }));

            if (previousReadyIds.size > 0) {
                readyIds.forEach(function(id) {
                    if (!previousReadyIds.has(id)) {
                        playReadySound();
                    }
                });
            }

            previousReadyIds = readyIds;
        }

        function buildKanbanHtml(pedidos) {
            if (!pedidos || pedidos.length === 0) {
                return '<div class="card text-center py-5">' +
                    '<i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>' +
                    '<h5>Nenhum pedido hoje</h5>' +
                    '<p class="text-muted">Pedidos via QR Code aparecerão aqui.</p>' +
                    '</div>';
            }

            var columns = getStatusColumns();
            var html = '<div class="kanban">';

            Object.keys(columns).forEach(function(status) {
                var info = columns[status];
                var pedidosColuna = pedidos.filter(function(p) {
                    return normalizarStatus(p.status) === status;
                });

                html += '<div class="kanban-col">';
                html += '<div class="kanban-header bg-' + info.cor + '">';
                html += '<div>';
                html += '<div>' + info.titulo + '</div>';
                if (info.subtitulo) html += '<div style="font-size:11px;opacity:0.75;font-weight:400;margin-top:2px;">' + info.subtitulo + '</div>';
                html += '</div>';
                html += '</div>';

                if (pedidosColuna.length === 0) {
                    html += '<p class="text-muted text-center small py-4">Nenhum</p>';
                }

                pedidosColuna.forEach(function(ped) {
                    var statusAtual = normalizarStatus(ped.status);
                    var mesaLabel = ped.mesa_numero ? ('Mesa ' + ped.mesa_numero) : 'Sem mesa';
                    var origemMeta = getOrigemPedidoMeta(ped.origem);
                    var hora = '';
                    if (ped.criado_em && ped.criado_em.length >= 16) {
                        hora = ped.criado_em.substring(11, 16);
                    }

                    html += '<div class="pedido-card status-' + statusAtual + '">';
                    html += '<div class="d-flex justify-content-between align-items-start mb-2">';
                    html += '<strong>' + escapeHtml(ped.numero_pedido) + '</strong>';
                    html += '<small class="text-muted">' + escapeHtml(hora) + '</small>';
                    html += '</div>';
                    html += '<div class="mb-2"><span class="pedido-origin-badge ' + origemMeta.className + '">';
                    html += '<i class="fas ' + origemMeta.icon + '"></i> ' + escapeHtml(origemMeta.label) + '</span></div>';
                    html += '<div class="small text-muted mb-2">' + escapeHtml(mesaLabel) + ' &bull; ' + parseInt(ped.total_itens || 0, 10) + ' item(s)</div>';
                    html += '<div class="fw-bold text-primary mb-2">' + formatMoney(ped.total) + '</div>';
                    html += '<div class="d-flex gap-2 flex-wrap">';

                    if (info.proximo) {
                        if (statusAtual === 'ENTREGUE') {
                            if (canReceivePayment) {
                                html += '<button class="btn btn-sm btn-success" onclick="abrirPagamentoPedido(' + parseInt(ped.id, 10) + ')">';
                                html += '<i class="fas fa-money-bill-wave me-1"></i> Receber Pagamento</button>';
                            } else {
                                html += '<button class="btn btn-sm btn-secondary" disabled title="Apenas perfil CAIXA ou ADMIN">';
                                html += '<i class="fas fa-lock me-1"></i> Aguardando Caixa</button>';
                            }
                        } else {
                            if (canManageOrderFlow) {
                                if (statusAtual === 'NOVO') {
                                    html += '<button class="btn btn-sm btn-warning text-dark" onclick="avancarPedido(this, ' + parseInt(ped.id, 10) + ', \'' + info.proximo + '\')">';
                                    html += '<i class="fas fa-fire me-1"></i> Enviar p/ Preparo</button>';
                                } else if (statusAtual === 'PREPARANDO') {
                                    html += '<button class="btn btn-sm btn-success" onclick="avancarPedido(this, ' + parseInt(ped.id, 10) + ', \'' + info.proximo + '\')">';
                                    html += '<i class="fas fa-check me-1"></i> Marcar Pronto</button>';
                                } else if (statusAtual === 'PRONTO') {
                                    html += '<button class="btn btn-sm btn-primary" onclick="avancarPedido(this, ' + parseInt(ped.id, 10) + ', \'' + info.proximo + '\')">';
                                    html += '<i class="fas fa-hand-holding me-1"></i> Entregar ao Cliente</button>';
                                } else {
                                    html += '<button class="btn btn-sm btn-success" onclick="avancarPedido(this, ' + parseInt(ped.id, 10) + ', \'' + info.proximo + '\')">';
                                    html += '<i class="fas fa-arrow-right me-1"></i> Avançar</button>';
                                }
                            } else {
                                html += '<button class="btn btn-sm btn-secondary" disabled title="Acompanhamento apenas">';
                                html += '<i class="fas fa-eye me-1"></i> Acompanhando</button>';
                            }
                        }
                    }

                    html += '<button class="btn btn-sm btn-info" onclick="verItensPedido(' + parseInt(ped.id, 10) + ')"><i class="fas fa-eye me-1"></i> Itens</button>';

                    if (canCancelOrder && statusAtual !== 'ENTREGUE' && statusAtual !== 'PAGO') {
                        html += '<button class="btn btn-sm btn-danger" onclick="cancelarPedido(this, ' + parseInt(ped.id, 10) + ')"><i class="fas fa-times me-1"></i> Cancelar</button>';
                    }

                    html += '</div></div>';
                });

                html += '</div>';
            });

            html += '</div>';
            return html;
        }

        function renderPedidos(payload) {
            if (!payload) return;
            var pedidos = Array.isArray(payload.pedidos) ? payload.pedidos : [];
            detectReadyAlerts(pedidos);
            currentPedidos = pedidos;
            var contadores = payload.contadores || {
                NOVO: 0,
                PREPARANDO: 0,
                PRONTO: 0,
                ENTREGUE: 0,
                PAGO: 0,
                CANCELADO: 0
            };
            updateCounters(contadores);
            document.getElementById('pedidosArea').innerHTML = buildKanbanHtml(pedidos);
        }

        function refreshPedidosData() {
            return fetch('api/pedidos_snapshot.php?ts=' + Date.now())
                .then(function(r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function(data) {
                    if (data && data.success) {
                        renderPedidos(data);
                        return true;
                    }
                    throw new Error((data && data.message) ? data.message : 'Falha ao atualizar pedidos');
                })
                .catch(function(err) {
                    console.warn('[pedidos] refresh falhou:', err);
                    return false;
                });
        }

        function startPollingFallback() {
            if (pollingTimer) return;
            pollingTimer = setInterval(function() {
                refreshPedidosData();
            }, 3000);
        }

        function reconnectRealtime() {
            if (sse) return;
            setTimeout(function() {
                if (!sse) {
                    setupRealtime();
                }
            }, 3000);
        }

        function pauseRealtime() {
            if (sse) {
                sse.close();
                sse = null;
                return true;
            }
            return false;
        }

        function resumeRealtime(wasPaused) {
            if (wasPaused) {
                reconnectRealtime();
            }
        }

        function setupRealtime() {
            if (!window.EventSource) {
                startPollingFallback();
                return;
            }

            sse = new EventSource('api/pedido_sse.php');

            sse.onopen = function() {
                var badge = document.getElementById('rtBadge');
                if (badge) {
                    badge.textContent = 'Tempo real';
                    badge.className = 'badge bg-success';
                }
            };

            sse.addEventListener('pedidos', function(event) {
                try {
                    var data = JSON.parse(event.data);
                    renderPedidos(data);
                } catch (e) {}
            });

            sse.onerror = function() {
                var badge = document.getElementById('rtBadge');
                if (badge) {
                    badge.textContent = 'Reconectando';
                    badge.className = 'badge bg-warning text-dark';
                }
                if (sse) {
                    sse.close();
                    sse = null;
                }
                startPollingFallback();
                reconnectRealtime();
            };
        }

        window.addEventListener('beforeunload', function() {
            if (sse) {
                sse.close();
                sse = null;
            }
            if (pollingTimer) {
                clearInterval(pollingTimer);
                pollingTimer = null;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            soundEnabled = localStorage.getItem('edvis_sound_alerts') === '1';
            updateSoundBanner();
            document.getElementById('sound-enable-btn')?.addEventListener('click', enableSoundAlerts);

            try {
                var qr = qrcode(0, 'M');
                qr.addData(cardapioUrl);
                qr.make();
                var mc = qr.getModuleCount();
                var cell = Math.floor(116 / mc);
                var canvas = document.getElementById('qrCodeCanvas');
                canvas.width = cell * mc;
                canvas.height = cell * mc;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#000000';
                for (var r = 0; r < mc; r++) {
                    for (var c = 0; c < mc; c++) {
                        if (qr.isDark(r, c)) {
                            ctx.fillRect(c * cell, r * cell, cell, cell);
                        }
                    }
                }
            } catch (e) {}

            renderPedidos({
                pedidos: initialPedidosData,
                contadores: initialContadores
            });
            detectReadyAlerts(initialPedidosData || []);

            // Garante atualização automática mesmo quando SSE falha silenciosamente.
            startPollingFallback();
            refreshPedidosData();
            setupRealtime();
        });

        function forcarAtualizacaoPedidos(btn) {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Atualizando';
            }

            refreshPedidosData().finally(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Atualizar';
                }
            });
        }

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshPedidosData();
            }
        });

        function baixarQRCode() {
            var dataUrl = gerarQRDataURL(cardapioUrl, 400);
            var link = document.createElement('a');
            link.download = 'qrcode-cardapio.png';
            link.href = dataUrl;
            link.click();
        }

        function imprimirQRCodes() {
            if (todasMesas.length === 0) {
                alert('Nenhuma mesa cadastrada.');
                return;
            }
            var h = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>QR Codes</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">';
            h += '<style>body{font-family:Arial;padding:20px;}h1{text-align:center;}.grid{display:flex;flex-wrap:wrap;gap:20px;justify-content:center;}.qi{text-align:center;padding:15px;border:2px solid #333;border-radius:10px;width:180px;}.qi img{display:block;margin:0 auto;}</style></head><body>';
            h += '<h1>QR Codes do Restaurante</h1><div class="grid">';
            h += '<div class="qi"><img src="' + gerarQRDataURL(cardapioUrl, 200) + '" width="150"><h3>Geral</h3></div>';
            for (var i = 0; i < todasMesas.length; i++) {
                var m = todasMesas[i];
                var mu = baseUrl + scriptDir + '/cardapio.php?rid=' + restauranteId + '&mesa=' + m.id;
                h += '<div class="qi"><img src="' + gerarQRDataURL(mu, 200) + '" width="150"><h3>Mesa ' + m.numero + '</h3></div>';
            }
            h += '</div></body></html>';
            var win = window.open('', '_blank');
            win.document.write(h);
            win.document.close();
        }

        function avancarPedido(btnEl, id, status) {
            if (!confirm('Avançar para ' + status + '?')) return;

            // Show loading
            const btn = btnEl;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            var fd = new FormData();
            fd.append('id', id);
            fd.append('status', status);
            fetch('api/pedido_status.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    if (data.success) {
                        refreshPedidosData();
                    } else {
                        alert('Erro: ' + (data.message || 'Falha desconhecida'));
                    }
                })
                .catch(err => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    alert('Erro de conexão: ' + err.message);
                });
        }

        function cancelarPedido(btnEl, id) {
            if (!confirm('Cancelar este pedido?')) return;

            // Show loading
            const btn = btnEl;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            var fd = new FormData();
            fd.append('id', id);
            fd.append('status', 'CANCELADO');
            fetch('api/pedido_status.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    if (data.success) {
                        refreshPedidosData();
                    } else {
                        alert('Erro: ' + (data.message || 'Falha desconhecida'));
                    }
                })
                .catch(err => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    alert('Erro de conexão: ' + err.message);
                });
        }

        function verItensPedido(id) {
            fetch('api/pedido_itens.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        var html = '';
                        data.itens.forEach(item => {
                            html += '<div class="d-flex justify-content-between py-2 border-bottom">';
                            html += '<span><strong>' + escapeHtml(item.produto_nome) + '</strong><br><small class="text-muted">' + escapeHtml(item.quantidade) + 'x ' + parseFloat(item.preco_unitario).toFixed(2).replace('.', ',') + ' MZN</small></span>';
                            html += '<strong class="text-primary">' + parseFloat(item.subtotal).toFixed(2).replace('.', ',') + ' MZN</strong></div>';
                        });
                        document.getElementById('listaItens').innerHTML = html || '<p class="text-muted">Sem itens</p>';
                        new bootstrap.Modal(document.getElementById('modalItens')).show();
                    }
                });
        }

        var _pagModal = null;

        function getPagModal() {
            if (!_pagModal) _pagModal = new bootstrap.Modal(document.getElementById('modalPagamento'));
            return _pagModal;
        }

        function abrirPagamentoPedido(id) {
            if (!canReceivePayment) {
                alert('Apenas o perfil CAIXA ou ADMIN pode receber pagamento.');
                return;
            }

            var pedido = currentPedidos.find(function(p) {
                return parseInt(p.id, 10) === parseInt(id, 10);
            });

            if (!pedido) {
                alert('Pedido não encontrado na lista atual.');
                return;
            }

            pedidoSelecionadoId = parseInt(id, 10);
            document.getElementById('pagForma').value = 'DINHEIRO';
            document.getElementById('pagDesconto').value = '0';
            document.getElementById('pagObs').value = '';

            var mesaTxt = pedido.mesa_numero ? ('Mesa ' + pedido.mesa_numero) : 'Sem mesa';
            document.getElementById('pagPedidoInfo').textContent = 'Pedido #' + (pedido.numero_pedido || pedido.id) + ' • ' + mesaTxt;
            document.getElementById('pagTotalLabel').textContent = formatMoney(pedido.total || 0);

            // Resetar para estado de formulário
            document.getElementById('pagFormBody').style.display = '';
            document.getElementById('pagSuccessBody').style.display = 'none';
            document.getElementById('pagFooter').style.display = '';

            getPagModal().show();
        }

        function confirmarPagamentoPedido() {
            if (!canReceivePayment) {
                alert('Apenas o perfil CAIXA ou ADMIN pode finalizar venda.');
                return;
            }

            if (!pedidoSelecionadoId) {
                alert('Nenhum pedido selecionado.');
                return;
            }

            // Evita bloqueio de popup após fetch assíncrono.
            var reciboPopup = window.open('', '_blank');

            var btn = document.getElementById('btnConfirmarPagamento');
            var original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processando…';

            var fd = new FormData();
            fd.append('id', pedidoSelecionadoId);
            fd.append('forma_pagamento', document.getElementById('pagForma').value);
            fd.append('desconto', document.getElementById('pagDesconto').value || '0');
            fd.append('observacao', document.getElementById('pagObs').value || '');

            // Em servidor local (php -S), pausar SSE evita travamento de requisição.
            var realtimePaused = pauseRealtime();

            var controller = new AbortController();
            var timeoutId = setTimeout(function() {
                controller.abort();
            }, 15000);

            fetch('api/pedido_pagar.php', {
                    method: 'POST',
                    body: fd,
                    signal: controller.signal
                })
                .then(function(r) {
                    return r.text();
                })
                .then(function(raw) {
                    var data;
                    try {
                        data = JSON.parse(raw);
                    } catch (e) {
                        btn.disabled = false;
                        btn.innerHTML = original;
                        alert('Resposta inválida do servidor:\n' + raw.substring(0, 300));
                        return;
                    }

                    if (!data.success) {
                        if (reciboPopup && !reciboPopup.closed) {
                            reciboPopup.close();
                        }
                        btn.disabled = false;
                        btn.innerHTML = original;
                        alert('Erro: ' + (data.message || 'Falha ao receber pedido'));
                        return;
                    }

                    // Sucesso: mostrar estado de confirmação com link clicável
                    document.getElementById('pagFormBody').style.display = 'none';
                    document.getElementById('pagFooter').style.display = 'none';

                    var infoEl = document.getElementById('pagSuccessInfo');
                    infoEl.textContent = 'Fatura ' + (data.numero_fatura || '') + ' registrada com sucesso.';

                    var linkEl = document.getElementById('btnComprovante');
                    var reciboUrl = 'comprovante.php?id=' + data.venda_id + '&auto_print=1';
                    linkEl.href = reciboUrl;

                    if (reciboPopup && !reciboPopup.closed) {
                        reciboPopup.location.href = reciboUrl;
                        reciboPopup.focus();
                    } else {
                        var popup = window.open(reciboUrl, '_blank');
                        if (!popup) {
                            window.location.href = reciboUrl;
                        }
                    }

                    document.getElementById('pagSuccessBody').style.display = '';

                    btn.disabled = false;
                    btn.innerHTML = original;

                    pedidoSelecionadoId = null;
                    refreshPedidosData();
                })
                .catch(function(err) {
                    if (reciboPopup && !reciboPopup.closed) {
                        reciboPopup.close();
                    }
                    btn.disabled = false;
                    btn.innerHTML = original;
                    if (err && err.name === 'AbortError') {
                        alert('Tempo limite ao finalizar venda. Tente novamente.');
                    } else {
                        alert('Erro de conexão: ' + err.message);
                    }
                })
                .finally(function() {
                    clearTimeout(timeoutId);
                    resumeRealtime(realtimePaused);
                });
        }

        // Realtime via SSE + fallback polling substitui reload automático.
    </script>
</body>

</html>

