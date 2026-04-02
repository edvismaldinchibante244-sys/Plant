<?php
/**
 * Arquivo legado.
 * Mantém compatibilidade redirecionando para a tela oficial em src/public.
 */
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';

header('Location: /src/public/configuracoes.php' . $query);
exit;

// Proteção da página - apenas ADMIN
include_once __DIR__ . '/../config/auth_check.php';

// Verificar restaurante_id
$restaurante_id = $_SESSION['restaurante_id'] ?? 0;
if ($restaurante_id <= 0) {
    header("Location: index.php?erro=sem_restaurante");
    exit;
}

// Verificar se é admin
if (($_SESSION['perfil'] ?? '') !== 'ADMIN') {
    header("Location: dashboard.php");
    exit;
}

include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM restaurantes WHERE id = :id LIMIT 1";
$stmt  = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['restaurante_id']);
$stmt->execute();
$restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

// Garantir que $restaurante é um array válido para evitar warnings
if (!$restaurante || !is_array($restaurante)) {
    $restaurante = [
        'nome' => '',
        'telefone' => '',
        'endereco' => '',
        'cidade' => '',
        'nuit' => '',
        'plano' => 'BASICO',
        'status' => 'INATIVO',
        'data_fim' => date('Y-m-d', strtotime('+30 days'))
    ];
}

$mensagem = '';
$tipo_msg = '';

// Verificar se há uma seção específica para mostrar
$secao = $_GET['secao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade   = trim($_POST['cidade'] ?? '');
    $nuit     = trim($_POST['nuit'] ?? '');

    if (empty($nome)) {
        $mensagem = 'O nome do restaurante é obrigatório.';
        $tipo_msg = 'danger';
    } else {
        $query_upd = "UPDATE restaurantes SET nome = :nome, telefone = :telefone, endereco = :endereco, cidade = :cidade, nuit = :nuit WHERE id = :id";
        $stmt_upd  = $db->prepare($query_upd);
        $stmt_upd->bindParam(':nome',     $nome);
        $stmt_upd->bindParam(':telefone', $telefone);
        $stmt_upd->bindParam(':endereco', $endereco);
        $stmt_upd->bindParam(':cidade',   $cidade);
        $stmt_upd->bindParam(':nuit',     $nuit);
        $stmt_upd->bindParam(':id',       $_SESSION['restaurante_id']);

        if ($stmt_upd->execute()) {
            $mensagem = 'Configurações salvas com sucesso!';
            $tipo_msg = 'success';
            $stmt->execute();
            $restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

            // Garantir que $restaurante continua sendo um array válido
            if (!$restaurante || !is_array($restaurante)) {
                $restaurante = [
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'endereco' => $endereco,
                    'cidade' => $cidade,
                    'nuit' => $nuit,
                    'plano' => 'BASICO',
                    'status' => 'INATIVO',
                    'data_fim' => date('Y-m-d', strtotime('+30 days'))
                ];
            }
        } else {
            $mensagem = 'Erro ao salvar configurações.';
            $tipo_msg = 'danger';
        }
    }
}

// Valores padrão seguros - buscar do banco de dados
$nome_restaurante = $restaurante['nome'] ?? '';
$telefone_restaurante = $restaurante['telefone'] ?? '';
$endereco_restaurante = $restaurante['endereco'] ?? '';
$cidade_restaurante = $restaurante['cidade'] ?? '';
$nuit_restaurante = $restaurante['nuit'] ?? '';

// Usar o plano do banco de dados diretamente
$plano_atual = $restaurante['plano'] ?? 'BASICO';
$status_atual = $restaurante['status'] ?? 'INATIVO';
$data_fim = $restaurante['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
$dias_restantes = ceil((strtotime($data_fim) - time()) / 86400);

// Atualizar sessão com o plano do banco
$_SESSION['plano'] = $plano_atual;

// Carregar configuração dos planos
$planos_config = require __DIR__ . '/../config/planos.php';
$plano_nome = $planos_config[$plano_atual]['nome'] ?? $plano_atual;
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - RestauranteSaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Fallback para quando Font Awesome não carregar */
        .fas,
        .far,
        .fal,
        .fab {
            font-family: 'Font Awesome 6 Free', 'Font Awesome 6 Pro', 'FontAwesome' !important;
            font-weight: 900;
            display: inline-block;
            width: 1.25em;
            text-align: center;
        }

        /* Garante que ícones apareçam mesmo com cache antigo */
        .menu-item i::before,
        .sidebar-brand i::before {
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }
    </style>
    <link href="css/premium-unified.css" rel="stylesheet">
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

        .plan-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            padding: 24px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        }

        .plan-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }

        .plan-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .plan-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .plan-details {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .plan-detail {
            display: flex;
            flex-direction: column;
        }

        .plan-detail-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-detail-value {
            font-size: 18px;
            font-weight: 700;
        }

        .plan-days {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 42px;
            font-weight: 700;
        }

        .plan-days-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: none;
            overflow: hidden;
            transition: all 0.3s;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 20px 24px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 18px;
        }

        .card-body {
            padding: 24px;
        }

        .plan-option {
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .plan-option:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .plan-option.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.08), rgba(247, 147, 30, 0.08));
        }

        .plan-option.current {
            border-color: var(--success);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(16, 185, 129, 0.03));
        }

        .plan-option-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 16px;
        }

        .plan-option-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .plan-option-price {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }

        .plan-option-price span {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .plan-option-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            flex: 1;
            text-align: left;
        }

        .plan-option-features li {
            padding: 8px 0;
            font-size: 13px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .plan-option-features li i {
            color: var(--success);
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

        .badge-primary {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary);
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

        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }

        .btn-outline-secondary {
            border: 2px solid var(--border);
            color: var(--text-light);
            background: transparent;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline-secondary:hover {
            border-color: var(--text-light);
            color: var(--text);
            background: var(--bg);
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
            font-family: 'Outfit', sans-serif;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }

        .alert {
            border-radius: 12px;
            padding: 16px 20px;
            border: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--bg);
            border: none;
            padding: 14px 16px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .table tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--bg);
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

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-light);
            font-size: 14px;
        }

        .info-value {
            font-weight: 600;
            font-size: 14px;
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

            .content-area {
                padding: 20px;
            }
        }
    </style>
</head>

<body class="premium-ui">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <nav class="sidebar col-md-3 col-lg-2 d-md-block">
                <div class="sidebar-brand">
                    <h2><i class="fas fa-utensils"></i> Restaurante</h2>
                    <span>Gestão Premium</span>
                </div>
                <div class="sidebar-menu">
                    <div class="menu-title">Menu</div>
                    <a class="menu-item" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <a class="menu-item" href="produtos.php"><i class="fas fa-pizza-slice"></i> Produtos</a>
                    <a class="menu-item" href="vendas.php"><i class="fas fa-cash-register"></i> Vendas</a>
                    <a class="menu-item" href="caixa.php"><i class="fas fa-money-bill-wave"></i> Caixa</a>
                    <a class="menu-item" href="pedidos.php"><i class="fas fa-mobile-alt"></i> Pedidos</a>
                    <a class="menu-item" href="mesas.php"><i class="fas fa-chair"></i> Mesas</a>
                    <a class="menu-item" href="relatorios.php"><i class="fas fa-chart-bar"></i> Relatórios</a>
                    <?php if ($_SESSION['perfil'] == 'ADMIN'): ?>
                        <div class="menu-title" style="margin-top: 20px;">Administração</div>
                        <a class="menu-item" href="usuarios.php"><i class="fas fa-users"></i> Usuários</a>
                        <?php
                        // Verificar se o plano tem Multi-Filial (apenas para EMPRESARIAL)
                        $current_plan_upper = strtoupper($plano_atual);
                        $tem_multi_filial = (stripos($current_plan_upper, 'EMPRES') !== false || stripos($current_plan_upper, 'ENTERP') !== false);
                        // Verificar se o plano tem Backup Automático
                        $tem_backup = (stripos($current_plan_upper, 'EMPRES') !== false || stripos($current_plan_upper, 'ENTERP') !== false);

                        if ($tem_multi_filial): ?>
                            <a class="menu-item" href="filiais.php"><i class="fas fa-building"></i> Filiais</a>
                        <?php endif; ?>

                        <?php if ($tem_backup): ?>
                            <a class="menu-item" href="backup.php"><i class="fas fa-database"></i> Backup</a>
                        <?php endif; ?>

                        <a class="menu-item active" href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                    <?php endif; ?>
                    <div class="menu-title" style="margin-top: 20px;">Sistema</div>
                    <a class="menu-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </div>
                <div class="sidebar-footer">
                    <div class="user-info">
                        <div class="user-avatar"><?php echo substr($_SESSION['nome'], 0, 2); ?></div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['nome']); ?></div>
                            <div class="user-role"><?php echo $_SESSION['perfil']; ?></div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- MAIN CONTENT -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <div class="top-bar">
                    <h1 class="page-title"><i class="fas fa-cog"></i> Configurações</h1>
                </div>

                <div class="content-area">
                    <?php if ($mensagem): ?>
                        <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $tipo_msg == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                            <?php echo $mensagem; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- PLANO ATUAL -->
                    <?php if ($secao !== 'plano'): ?>
                        <div class="plan-card mb-4">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                <div>
                                    <div class="plan-icon">👑</div>
                                    <div class="plan-name">Plano <?php echo htmlspecialchars($plano_nome); ?></div>
                                    <div class="plan-status">
                                        <i class="fas fa-<?php echo $status_atual == 'ATIVO' ? 'check-circle' : 'clock'; ?>"></i>
                                        <?php echo htmlspecialchars($status_atual); ?>
                                    </div>
                                    <div class="plan-details mt-3">
                                        <div class="plan-detail">
                                            <span class="plan-detail-label">Validade</span>
                                            <span class="plan-detail-value"><?php echo date('d/m/Y', strtotime($data_fim)); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-md-end">
                                    <div class="plan-days"><?php echo max(0, $dias_restantes); ?></div>
                                    <div class="plan-days-label">dias restantes</div>
                                    <?php if ($plano_atual !== 'EMPRESARIAL'): ?>
                                        <a href="configuracoes.php?secao=plano" class="btn btn-light mt-3">
                                            <i class="fas fa-arrow-up me-2"></i>Upgrade
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- SEÇÃO PLANO (quando acessar via upgrade) -->
                    <?php if ($secao === 'plano'): ?>
                        <div class="card mb-4" id="plano">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div><i class="fas fa-crown me-2 text-warning"></i>Planos de Assinatura</div>
                                <a href="configuracoes.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-1"></i> Fechar
                                </a>
                            </div>
                            <div class="card-body">
                                <!-- Tabela Comparativa de Planos (Admin) -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Funcionalidade</th>
                                                        <th style="text-align: center; background: linear-gradient(135deg, #6c757d, #495057); color: white;">Básico</th>
                                                        <th style="text-align: center; background: linear-gradient(135deg, #17a2b8, #0dcaf0); color: white;">Profissional</th>
                                                        <th style="text-align: center; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">Empresarial</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><strong>Produtos</strong></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i> 100</td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i> 500</td>
                                                        <td style="text-align: center;"><i class="fas fa-infinity text-primary"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Usuários</strong></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i> 3</td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i> 10</td>
                                                        <td style="text-align: center;"><i class="fas fa-infinity text-primary"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Mesas</strong></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i> 20</td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i> 50</td>
                                                        <td style="text-align: center;"><i class="fas fa-infinity text-primary"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Caixa Diário</td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Pedidos QR Code</td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Relatórios Avançados</td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Multi-Filial</td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Backup Automático</td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Suporte 24h</td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-times text-muted"></i></td>
                                                        <td style="text-align: center;"><i class="fas fa-check text-success"></i></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                // Dados dos planos para cards dinâmicos
                                $planos_data = $planos_config;
                                $plan_keys = ['BASICO', 'PROFISSIONAL', 'EMPRESARIAL'];
                                ?>
                                <div class="row g-4 plans-grid align-items-stretch">
                                    <?php foreach ($plan_keys as $plano_key): 
                                        $plano = $planos_data[$plano_key];
                                        $is_current = strtoupper($plano_atual) === $plano_key;
                                    ?>
                                    <div class="col-lg-4 col-md-4 plan-col">
                                        <div class="plan-option <?php echo $is_current ? 'current selected' : ''; ?>" style="border-color: <?php echo $plano['cor']; ?>;">
                                            <div class="plan-option-icon" style="background: linear-gradient(135deg, <?php echo $plano['cor']; ?>, darken(<?php echo $plano['cor']; ?>, 20%)); color: white;">
                                                <i class="<?php echo $plano['icone']; ?>"></i>
                                            </div>
                                            <div class="plan-option-name"><?php echo $plano['nome']; ?></div>
                                            <div class="plan-option-price"><?php echo number_format($plano['precos']['mensal']); ?><span> MZN/mês</span></div>
                                            <ul class="plan-option-features">
                                                <li class="text-success"><i class="fas fa-check"></i> <?php echo $plano['limites']['produtos'] == -1 ? 'Ilimitado' : $plano['limites']['produtos']; ?> produtos</li>
                                                <li class="text-success"><i class="fas fa-check"></i> <?php echo $plano['limites']['usuarios'] == -1 ? 'Ilimitado' : $plano['limites']['usuarios']; ?> usuários</li>
                                                <li class="text-success"><i class="fas fa-check"></i> <?php echo $plano['limites']['mesas'] == -1 ? 'Ilimitado' : $plano['limites']['mesas']; ?> mesas</li>
                                                <?php if($plano['funcionalidades']['caixa']): ?>
                                                <li class="text-success"><i class="fas fa-check"></i> Caixa diário</li>
                                                <?php endif; ?>
                                                <?php if($plano['funcionalidades']['pedidos_online']): ?>
                                                <li class="text-success"><i class="fas fa-check"></i> Pedidos QR Code</li>
                                                <?php endif; ?>
                                                <?php if($plano['funcionalidades']['relatorios_avancados']): ?>
                                                <li class="text-success"><i class="fas fa-check"></i> Relatórios avançados</li>
                                                <?php endif; ?>
                                                <?php if($plano['funcionalidades']['multi_filial']): ?>
                                                <li class="text-success"><i class="fas fa-check"></i> Multi-filial</li>
                                                <?php endif; ?>
                                            </ul>
                                            <?php if ($is_current): ?>
                                                <span class="badge-custom badge-success mt-auto">Plano Atual</span>
                                            <?php elseif (in_array(strtoupper($plano_atual), ['PROFISSIONAL', 'EMPRESARIAL'])): ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" disabled>
                                                    <i class="fas fa-arrow-down me-1"></i> Plano Inferior
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCompraPlano" onclick="selecionarPlano('<?php echo $plano_key; ?>', <?php echo $plano['precos']['mensal']; ?>)">
                                                    <i class="fas fa-shopping-cart me-1"></i> Comprar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- HISTÓRICO DE COMPRAS -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="fas fa-history me-2"></i>Histórico de Compras
                            </div>
                            <div class="card-body p-0">
                                <div id="historicoCompras">
                                    <p class="text-center text-muted py-4">Carregando...</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4 mt-2">
                        <!-- DADOS DO RESTAURANTE -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <i class="fas fa-store me-2"></i>Dados do Restaurante
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="configuracoes.php">
                                        <div class="mb-3">
                                            <label class="form-label">Nome do Restaurante *</label>
                                            <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($nome_restaurante); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telefone</label>
                                            <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($telefone_restaurante); ?>" placeholder="+258 84 000 0000">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Endereço</label>
                                            <input type="text" name="endereco" class="form-control" value="<?php echo htmlspecialchars($endereco_restaurante); ?>" placeholder="Av. Eduardo Mondlane, 123">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Cidade</label>
                                            <input type="text" name="cidade" class="form-control" value="<?php echo htmlspecialchars($cidade_restaurante); ?>" placeholder="Maputo">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">NUIT</label>
                                            <input type="text" name="nuit" class="form-control" value="<?php echo htmlspecialchars($nuit_restaurante); ?>" placeholder="400000000">
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-save me-2"></i>Salvar Alterações
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MINHA CONTA -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-user me-2"></i>Minha Conta
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="user-avatar me-3" style="width: 60px; height: 60px; font-size: 22px;">
                                            <?php echo substr($_SESSION['nome'], 0, 2); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="font-size: 18px;"><?php echo htmlspecialchars($_SESSION['nome']); ?></div>
                                            <span class="badge-custom <?php echo $_SESSION['perfil'] == 'ADMIN' ? 'badge-danger' : 'badge-primary'; ?>">
                                                <?php echo $_SESSION['perfil']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Restaurante ID</span>
                                        <span class="info-value">#<?php echo $_SESSION['restaurante_id']; ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Plano Atual</span>
                                        <span class="info-value"><?php echo htmlspecialchars($plano_nome); ?></span>
                                    </div>
                                    <a href="usuarios.php" class="btn btn-outline-primary w-100 mt-4">
                                        <i class="fas fa-users me-2"></i>Gerenciar Usuários
                                    </a>
                                    <?php if ($tem_backup): ?>
                                        <a href="backup.php" class="btn btn-primary w-100 mt-2">
                                            <i class="fas fa-database me-2"></i>Backup Automático
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-info-circle me-2"></i>Informações do Sistema
                                </div>
                                <div class="card-body p-0">
                                    <div class="info-row">
                                        <span class="info-label">Versão</span>
                                        <span class="info-value">1.0.0</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">PHP</span>
                                        <span class="info-value"><?php echo phpversion(); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Suporte</span>
                                        <span class="info-value">suporte@sabormoz.co.mz</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Compra de Plano -->
    <div class="modal fade" id="modalCompraPlano" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-shopping-cart me-2"></i>Confirmar Compra de Plano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="alertModalPlano" class="alert" style="display: none;"></div>

                    <div class="text-center mb-4">
                        <div class="plan-option-icon d-inline-flex" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; width: 80px; height: 80px; font-size: 36px;">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h5 class="mt-3" id="planoSelecionadoNome"></h5>
                        <div class="plan-option-price" id="planoSelecionadoValor"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Método de Pagamento *</label>
                        <select class="form-select" id="metodoPagamento">
                            <option value="">Selecione...</option>
                            <option value="DINHEIRO">💵 Dinheiro</option>
                            <option value="MPESA">📱 M-Pesa</option>
                            <option value="CARTAO">💳 Cartão</option>
                            <option value="TRANSFERENCIA">🏦 Transferência</option>
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Após confirmar, aguarde a verificação do pagamento. O plano será ativado após aprovação do administrador.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarCompraPlano()">
                        <i class="fas fa-check me-1"></i> Confirmar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var planoAtual = '';
        var valorAtual = 0;

        // Selecionar plano
        function selecionarPlano(plano, valor) {
            planoAtual = plano;
            valorAtual = valor;
            document.getElementById('planoSelecionadoNome').textContent = plano;
            if (valor === 0) {
                document.getElementById('planoSelecionadoValor').innerHTML = 'Grátis<span>/mês</span>';
            } else {
                document.getElementById('planoSelecionadoValor').innerHTML = valor + '<span> MZN/mês</span>';
            }
            document.getElementById('metodoPagamento').value = '';
            document.getElementById('alertModalPlano').style.display = 'none';
        }

        // Confirmar compra
        function confirmarCompraPlano() {
            var metodo = document.getElementById('metodoPagamento').value;
            var alertDiv = document.getElementById('alertModalPlano');

            if (!metodo) {
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'Selecione o método de pagamento!';
                alertDiv.style.display = 'block';
                return;
            }

            // Criar form data
            var formData = new FormData();
            formData.append('plano', planoAtual);
            formData.append('metodo', metodo);

            // Chamar API
            fetch('api/plano_comprar.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = data.message;
                        alertDiv.style.display = 'block';

                        // Fechar modal e recarregar após 2 segundos
                        setTimeout(function() {
                            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCompraPlano'));
                            modal.hide();
                            window.location.reload();
                        }, 2000);
                    } else {
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = data.message;
                        alertDiv.style.display = 'block';
                    }
                })
                .catch(function(err) {
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'Erro: ' + err.message;
                    alertDiv.style.display = 'block';
                });
        }

        // Carregar histórico de compras
        <?php if ($secao === 'plano'): ?>
            fetch('api/plano_listar.php', {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    var container = document.getElementById('historicoCompras');
                    if (data.success && data.data && data.data.length > 0) {
                        var html = '<table class="table"><thead><tr><th>ID</th><th>De</th><th>Para</th><th>Valor</th><th>Método</th><th>Status</th><th>Data</th></tr></thead><tbody>';
                        data.data.forEach(function(c) {
                            var statusClass = c.status === 'APROVADO' ? 'success' : (c.status === 'PENDENTE' ? 'warning' : 'danger');
                            html += '<tr><td>#' + c.id + '</td><td>' + c.plano_atual + '</td><td>' + c.plano_novo + '</td><td>' + parseFloat(c.valor).toFixed(2) + ' MZN</td><td>' + c.metodo_pagamento + '</td><td><span class="badge-custom badge-' + statusClass + '">' + c.status + '</span></td><td>' + new Date(c.criado_em).toLocaleDateString('pt-BR') + '</td></tr>';
                        });
                        html += '</tbody></table>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p class="text-center text-muted py-4">Nenhuma compra encontrada</p>';
                    }
                })
                .catch(function(err) {
                    console.error('Erro ao carregar compras:', err);
                    document.getElementById('historicoCompras').innerHTML = '<p class="text-center text-danger py-4">Erro ao carregar histórico</p>';
                });
        <?php endif; ?>
    </script>
</body>

</html>