<?php
// Proteção da página
include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/csrf.php';
include_once __DIR__ . '/../config/plano_check.php';
include_once __DIR__ . '/../config/restaurante_context.php';
include_once __DIR__ . '/../Model/Mesa.php';

// Conectar ao banco
$database = new Database();
$db = $database->getConnection();

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$restauranteId = session_restaurante_contexto_id();
$restauranteFeatureId = session_restaurante_capability_id();
$restauranteFeatureId = $restauranteFeatureId > 0 ? $restauranteFeatureId : $restauranteId;
$temPedidosOnline = $restauranteFeatureId > 0 && plano_tem_funcionalidade_db($restauranteFeatureId, 'pedidos_online');
$mesa = new Mesa($db);
$mesas = $mesa->listar($restauranteId);
$csrfToken = csrf_get_token();
$dataHoje = date('Y-m-d');

$stmtReservasHoje = $db->prepare("
    SELECT
        r.id,
        r.nome_cliente,
        r.telefone_cliente,
        r.data_reserva,
        r.hora_reserva,
        r.quantidade_pessoas,
        r.status,
        r.mesa_atribuida,
        r.observacoes,
        m.numero AS mesa_numero
    FROM reservas r
    LEFT JOIN mesas m ON m.id = r.mesa_atribuida
    WHERE r.restaurante_id = :restaurante_id
      AND r.data_reserva >= :data_hoje
      AND r.status IN ('pendente', 'confirmado')
    ORDER BY r.data_reserva ASC, r.hora_reserva ASC
    LIMIT 12
");
$stmtReservasHoje->execute([
    ':restaurante_id' => $restauranteId,
    ':data_hoje' => $dataHoje,
]);
$proximasReservas = $stmtReservasHoje->fetchAll(PDO::FETCH_ASSOC);

$stmtResumoReservas = $db->prepare("
    SELECT
        COUNT(*) AS total_hoje,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes_hoje,
        SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) AS confirmadas_hoje
    FROM reservas
    WHERE restaurante_id = :restaurante_id
      AND data_reserva = :data_hoje
      AND status IN ('pendente', 'confirmado')
");
$stmtResumoReservas->execute([
    ':restaurante_id' => $restauranteId,
    ':data_hoje' => $dataHoje,
]);
$resumoReservasHoje = $stmtResumoReservas->fetch(PDO::FETCH_ASSOC) ?: [
    'total_hoje' => 0,
    'pendentes_hoje' => 0,
    'confirmadas_hoje' => 0,
];
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
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

        .content-area {
            padding: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card.success::before {
            background: var(--success);
        }

        .stat-card.warning::before {
            background: var(--warning);
        }

        .stat-card.danger::before {
            background: var(--danger);
        }

        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 36px;
            font-weight: 700;
            color: var(--text);
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
        }

        .mesa-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            border: 3px solid transparent;
        }

        .mesa-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .mesa-card.livre {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
        }

        .mesa-card.ocupada {
            border-color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
        }

        .mesa-card.reservada {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.05);
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

        .btn-sm {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
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

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 991px) {
            .main-content {
                margin-left: 0 !important;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-area {
                padding: 20px;
            }
        }
    </style>
</head>

<body class="premium-ui">
    <div class="container-fluid">
        <div class="row">
            <!-- Botão toggle do menu para mobile -->
            <style>
                @media (max-width: 991px) {
                    .sidebar-toggle-btn {
                        display: block !important;
                    }

                    .sidebar {
                        position: fixed !important;
                        top: 0;
                        left: 0;
                        height: 100vh;
                        z-index: 2000;
                        transition: left 0.3s;
                    }

                    .sidebar.sidebar-hidden {
                        left: -100vw !important;
                    }

                    .main-content-blur {
                        filter: blur(2px) grayscale(0.1);
                        pointer-events: none;
                    }
                }
            </style>
            <!-- SIDEBAR -->
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

            <!-- MAIN CONTENT -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <div class="content-area">
                <?php
                $total = 0;
                $livres = 0;
                $ocupadas = 0;
                $reservadas = 0;
                $todas_mesas = [];
                while ($m = $mesas->fetch(PDO::FETCH_ASSOC)) {
                    $todas_mesas[] = $m;
                    $total++;
                    if ($m['status'] == 'LIVRE') $livres++;
                    if ($m['status'] == 'OCUPADA') $ocupadas++;
                    if ($m['status'] == 'RESERVADA') $reservadas++;
                }
                ?>

                <!-- STATS -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total; ?></div>
                        <div class="stat-label">Total de Mesas</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value" style="color: var(--success);"><?php echo $livres; ?></div>
                        <div class="stat-label"><i class="fas fa-circle me-1" style="font-size: 10px; color: var(--success);"></i>Mesas Livres</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value" style="color: var(--danger);"><?php echo $ocupadas; ?></div>
                        <div class="stat-label"><i class="fas fa-circle me-1" style="font-size: 10px; color: var(--danger);"></i>Mesas Ocupadas</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value" style="color: var(--warning);"><?php echo $reservadas; ?></div>
                        <div class="stat-label"><i class="fas fa-circle me-1" style="font-size: 10px; color: var(--warning);"></i>Mesas Reservadas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: var(--info);"><?php echo (int)($resumoReservasHoje['total_hoje'] ?? 0); ?></div>
                        <div class="stat-label"><i class="fas fa-calendar-check me-1" style="font-size: 10px; color: var(--info);"></i>Reservas de Hoje</div>
                    </div>
                </div>

                <!-- LEGENDA E BOTÃO -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div class="d-flex gap-3">
                        <span class="badge-custom badge-success"><i class="fas fa-circle me-1"></i>Livre</span>
                        <span class="badge-custom badge-danger"><i class="fas fa-circle me-1"></i>Ocupada</span>
                        <span class="badge-custom badge-warning"><i class="fas fa-circle me-1"></i>Reservada</span>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-warning" onclick="abrirModalReserva()"><i class="fas fa-calendar-plus me-2"></i>Nova Reserva</button>
                        <?php if ($_SESSION['perfil'] == 'ADMIN'): ?>
                            <button class="btn btn-primary" onclick="abrirModalNovaMesa('mesa')"><i class="fas fa-plus me-2"></i>Adicionar Mesa</button>
                            <button class="btn btn-outline-primary" onclick="abrirModalNovaMesa('balcao')"><i class="fas fa-store me-2"></i>Adicionar Balcão</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-calendar-check"></i> Reservas Programadas</div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge-custom badge-warning"><?php echo (int)($resumoReservasHoje['pendentes_hoje'] ?? 0); ?> pendentes hoje</span>
                            <span class="badge-custom badge-success"><?php echo (int)($resumoReservasHoje['confirmadas_hoje'] ?? 0); ?> confirmadas hoje</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($proximasReservas)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-calendar-xmark fa-2x mb-3 d-block"></i>
                                Nenhuma reserva programada a partir de hoje.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Data / Hora</th>
                                            <th>Cliente</th>
                                            <th>Mesa</th>
                                            <th>Pessoas</th>
                                            <th>Status</th>
                                            <th class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($proximasReservas as $reserva): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($reserva['data_reserva']); ?></strong><br>
                                                    <span class="text-muted"><?php echo htmlspecialchars(substr((string)$reserva['hora_reserva'], 0, 5)); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($reserva['nome_cliente']); ?></strong><br>
                                                    <span class="text-muted"><?php echo htmlspecialchars($reserva['telefone_cliente'] ?: 'Sem telefone'); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($reserva['mesa_numero'] ? ('Mesa ' . $reserva['mesa_numero']) : 'Por atribuir'); ?></td>
                                                <td><?php echo (int)$reserva['quantidade_pessoas']; ?></td>
                                                <td>
                                                    <span class="badge-custom <?php echo $reserva['status'] === 'confirmado' ? 'badge-success' : 'badge-warning'; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($reserva['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                        <?php if ($reserva['status'] === 'pendente'): ?>
                                                            <button class="btn btn-success btn-action btn-sm" onclick="confirmarReserva(<?php echo (int)$reserva['id']; ?>)" title="Confirmar reserva">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button
                                                            class="btn btn-info btn-action btn-sm"
                                                            onclick="fazerCheckinReserva(<?php echo (int)$reserva['id']; ?>, <?php echo (int)($reserva['mesa_atribuida'] ?? 0); ?>)"
                                                            title="Fazer check-in">
                                                            <i class="fas fa-right-to-bracket"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-action btn-sm" onclick="cancelarReserva(<?php echo (int)$reserva['id']; ?>)" title="Cancelar reserva">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- GRID DE MESAS -->
                <div class="row g-4">
                    <?php foreach ($todas_mesas as $m): ?>
                        <?php
                        $status_lower = strtolower($m['status']);
                        // Oculta QR code para a mesa Balcão
                        if (strtolower($m['numero']) === 'balcao' || strtolower($m['numero']) === 'balcão') continue;
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="mesa-card <?php echo $status_lower; ?>">
                                <div style="font-size: 48px; margin-bottom: 12px;">
                                    <?php echo $m['status'] == 'LIVRE' ? '🪑' : ($m['status'] == 'OCUPADA' ? '👥' : '📋'); ?>
                                </div>
                                <div style="font-size: 22px; font-weight: 700;">Mesa <?php echo $m['numero']; ?></div>
                                <div class="text-muted small mb-2">👤 <?php echo $m['capacidade'] ?? 4; ?> pessoas</div>
                                <?php if ($temPedidosOnline): ?>
                                    <?php $link = $baseUrl . '/cardapio.php?rid=' . $restauranteId . '&mesa_id=' . $m['id']; ?>
                                    <div class="mt-2 mb-3">
                                        <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" title="Abrir cardápio">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo urlencode($link); ?>" alt="QR Code" />
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-2 mb-3 text-muted small">
                                        <i class="fas fa-lock me-1"></i>QR Code indisponível no plano atual
                                    </div>
                                <?php endif; ?>
                                <span class="badge-custom badge-<?php echo $status_lower == 'livre' ? 'success' : ($status_lower == 'ocupada' ? 'danger' : 'warning'); ?>">
                                    <?php echo $m['status']; ?>
                                </span>
                                <div class="mt-3 d-flex gap-2 justify-content-center">
                                    <?php if ($m['status'] != 'LIVRE'): ?>
                                        <button class="btn btn-success btn-sm" onclick="atualizarMesa(<?php echo $m['id']; ?>, 'LIVRE')" title="Liberar"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                    <?php if ($m['status'] != 'OCUPADA'): ?>
                                        <button class="btn btn-danger btn-sm" onclick="atualizarMesa(<?php echo $m['id']; ?>, 'OCUPADA')" title="Ocupar"><i class="fas fa-user"></i></button>
                                    <?php endif; ?>
                                    <?php if ($m['status'] != 'RESERVADA'): ?>
                                        <button class="btn btn-warning btn-sm" onclick="abrirModalReserva(<?php echo (int)$m['id']; ?>, '<?php echo htmlspecialchars((string)$m['numero'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)($m['capacidade'] ?? 4); ?>)" title="Reservar"><i class="fas fa-calendar"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($todas_mesas)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-chair fa-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted">Nenhuma mesa cadastrada</p>
                            <button class="btn btn-primary" onclick="abrirModalNovaMesa('mesa')"><i class="fas fa-plus me-2"></i>Adicionar Mesa</button>
                            <button class="btn btn-outline-primary mt-2" onclick="abrirModalNovaMesa('balcao')"><i class="fas fa-store me-2"></i>Adicionar Balcão</button>
                        </div>
                    <?php endif; ?>
                </div>
                </div>
            </main>
        </div>
    </div>

    <!-- MODAL NOVA MESA -->
    <div class="modal fade" id="modalNovaMesa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNovaMesaTitulo"><i class="fas fa-plus me-2"></i>Nova Mesa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="alertMesa" style="display: none;"></div>
                    <form id="formNovaMesa">
                        <input type="hidden" id="mesa_tipo" name="tipo" value="mesa">
                        <div class="mb-3">
                            <label class="form-label" id="mesa_numero_label">Número da Mesa *</label>
                            <input type="text" id="mesa_numero" name="numero" class="form-control" required placeholder="Ex: 9">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" id="mesa_capacidade_label">Capacidade (pessoas) *</label>
                            <input type="number" id="mesa_capacidade" name="capacidade" class="form-control" min="1" value="4" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNovaMesa" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalReservaMesa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Nova Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="alertReserva" style="display: none;"></div>
                    <form id="formReservaMesa">
                        <input type="hidden" id="reserva_mesa_id" name="mesa_atribuida">
                        <div class="mb-3">
                            <label class="form-label">Mesa selecionada</label>
                            <input type="text" id="reserva_mesa_label" class="form-control" readonly placeholder="Atribuição automática">
                            <div class="form-text text-muted" id="reserva_disponibilidade">Selecione data, hora e quantidade para ver a disponibilidade.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cliente *</label>
                            <input type="text" id="reserva_nome_cliente" name="nome_cliente" class="form-control" required placeholder="Nome do cliente">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" id="reserva_telefone_cliente" name="telefone_cliente" class="form-control" placeholder="+258...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" id="reserva_email_cliente" name="email_cliente" class="form-control" placeholder="cliente@email.com">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label">Data *</label>
                                <input type="date" id="reserva_data" name="data_reserva" class="form-control" value="<?php echo htmlspecialchars($dataHoje); ?>" min="<?php echo htmlspecialchars($dataHoje); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Hora *</label>
                                <input type="time" id="reserva_hora" name="hora_reserva" class="form-control" value="19:00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pessoas *</label>
                                <input type="number" id="reserva_quantidade" name="quantidade_pessoas" class="form-control" min="1" value="2" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Observações</label>
                            <textarea id="reserva_observacoes" name="observacoes" class="form-control" rows="3" placeholder="Preferências, aniversários, restrições, etc."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnSalvarReserva" form="formReservaMesa" class="btn btn-warning"><i class="fas fa-save me-2"></i>Salvar Reserva</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/mesas.js"></script>
    <script>
        window.RESERVAS_CONFIG = {
            token: <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            endpoint: 'api/reservas.php',
            hoje: <?php echo json_encode($dataHoje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        };

        setInterval(function() {
            fetch('api/online_ping.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        }, 60000);
    </script>
</body>

</html>

