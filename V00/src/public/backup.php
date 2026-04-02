<?php

/*
 
   PÁGINA DE BACKUP - GESTÃO DE BACKUP AUTOMÁTICO
   Disponivel para perfis ADMIN com funcionalidades de backup
   
 */

include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/plano_check.php';
include_once __DIR__ . '/../config/csrf.php';
include_once __DIR__ . '/../config/backup_helper.php';

// Verificar se é admin
$perfil = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
if ($perfil !== 'ADMIN') {
    header("Location: dashboard.php?erro=acesso_negado");
    exit;
}

$restaurante_id = session_restaurante_contexto_id();
$restaurante_plan_id = session_restaurante_capability_id();
$database = new Database();
$db = $database->getConnection();

// Buscar dados do restaurante
$stmt = $db->prepare("SELECT plano FROM restaurantes WHERE id = ? LIMIT 1");
$stmt->execute([$restaurante_plan_id]);
$restaurante = $stmt->fetch(PDO::FETCH_ASSOC);
$plano = $restaurante['plano'] ?? 'BASICO';

$tem_backup = $restaurante_plan_id > 0 && (
    plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_automatico')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_diario')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_manual')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'download_banco')
);

// Se não tem nenhuma funcionalidade de backup, redirecionar
if (!$tem_backup) {
    header("Location: configuracoes.php?erro=plano_sem_backup");
    exit;
}

$mensagem = trim((string)($_GET['msg'] ?? ''));
$tipo_msg = strtolower(trim((string)($_GET['tipo'] ?? '')));
if (!in_array($tipo_msg, ['success', 'danger', 'warning', 'info'], true)) {
    $tipo_msg = '';
}
if ($mensagem !== '' && $tipo_msg === '') {
    $tipo_msg = 'info';
}

backup_ensure_tables_exist($db);

$permiteBackupManual = plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_manual')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'download_banco');
$permiteBackupAutomatico = plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_automatico')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_diario')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_hora');
$permiteBackupDiario = plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_diario');
$permiteBackupHora = plano_tem_funcionalidade_db($restaurante_plan_id, 'backup_hora');
$permiteBackupConfig = $permiteBackupAutomatico;
$csrfToken = csrf_get_token();

$configBackup = backup_obter_configuracao($db, $restaurante_id);
if (empty($configBackup['id']) && $permiteBackupAutomatico) {
    $configBackup = backup_salvar_configuracao($db, $restaurante_id, [
        'automatico' => 1,
        'frequencia' => $permiteBackupHora ? 'HORARIO' : 'DIARIO',
        'hora_execucao' => '00:00:00',
        'retencao_dias' => 30,
        'notificar_email' => 0,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['acao'] ?? '') === 'salvar_config') {
    if (!csrf_is_valid()) {
        header('Location: backup.php?tipo=danger&msg=' . urlencode('Sessao expirada. Recarregue a pagina e tente novamente.'));
        exit;
    }

    $automatico = $permiteBackupConfig && isset($_POST['backup_automatico']) ? 1 : 0;
    $frequencia = strtoupper(trim((string)($_POST['frequencia'] ?? 'DIARIO')));
    if (!$permiteBackupHora || !in_array($frequencia, ['DIARIO', 'HORARIO'], true)) {
        $frequencia = 'DIARIO';
    }
    if (!$permiteBackupDiario && $frequencia === 'DIARIO' && $permiteBackupHora) {
        $frequencia = 'HORARIO';
    }
    $horaExecucao = trim((string)($_POST['hora_execucao'] ?? '00:00'));
    if (!preg_match('/^\d{2}:\d{2}$/', $horaExecucao)) {
        $horaExecucao = '00:00';
    }
    $retencaoDias = max(1, min(3650, (int)($_POST['retencao_dias'] ?? 30)));
    $notificarEmail = isset($_POST['notificar_email']) ? 1 : 0;

    if (!$permiteBackupConfig) {
        $automatico = 0;
    }

    $configBackup = backup_salvar_configuracao($db, $restaurante_id, [
        'automatico' => $automatico,
        'frequencia' => $frequencia,
        'hora_execucao' => $horaExecucao,
        'retencao_dias' => $retencaoDias,
        'notificar_email' => $notificarEmail,
    ]);

    header('Location: backup.php?tipo=success&msg=' . urlencode('Configuracao de backup atualizada com sucesso.'));
    exit;
}

if (isset($_GET['acao']) && $_GET['acao'] === 'download_historico') {
    $historicoId = (int)($_GET['id'] ?? 0);
    $stmtHistorico = $db->prepare("SELECT * FROM backup_historico WHERE id = :id AND restaurante_id = :rid LIMIT 1");
    $stmtHistorico->execute([
        'id' => $historicoId,
        'rid' => $restaurante_id,
    ]);
    $backupHistorico = $stmtHistorico->fetch(PDO::FETCH_ASSOC);
    if (!$backupHistorico || empty($backupHistorico['arquivo_caminho']) || !is_file($backupHistorico['arquivo_caminho'])) {
        header('Location: backup.php?tipo=danger&msg=' . urlencode('Arquivo de backup nao encontrado.'));
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename((string)$backupHistorico['arquivo_nome']) . '"');
    header('Content-Length: ' . filesize((string)$backupHistorico['arquivo_caminho']));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: public');
    header('X-Content-Type-Options: nosniff');
    readfile((string)$backupHistorico['arquivo_caminho']);
    exit;
}

if (isset($_GET['acao']) && $_GET['acao'] === 'download') {
    $resultado = backup_executar_geracao($db, $restaurante_id, $configBackup, 'MANUAL', 'web');
    if (empty($resultado['success'])) {
        $mensagem = (string)($resultado['message'] ?? 'Erro ao gerar backup.');
        $tipo_msg = 'danger';
    } else {
        $historicoId = (int)($resultado['historico_id'] ?? 0);
        if ($historicoId > 0) {
            header('Location: backup.php?acao=download_historico&id=' . $historicoId);
            exit;
        }

        $mensagem = 'Backup gerado, mas nao foi possivel localizar o arquivo para download.';
        $tipo_msg = 'warning';
    }
}

$historico = backup_listar_historico($db, $restaurante_id, 20);
$backupStatusAtivo = $permiteBackupAutomatico && !empty($configBackup['automatico']);
$backupStatusTexto = $backupStatusAtivo ? 'Ativo' : ($permiteBackupAutomatico ? 'Manual' : 'Somente manual');
$backupStatusClasse = $backupStatusAtivo ? 'ativo' : ($permiteBackupAutomatico ? 'manual' : 'inativo');
$backupFrequenciaTexto = $backupStatusAtivo
    ? (strtoupper((string)($configBackup['frequencia'] ?? 'DIARIO')) === 'HORARIO' ? 'A cada hora' : 'Diario')
    : 'Manual';
$backupUltimoTexto = !empty($configBackup['ultimo_backup_em'])
    ? date('d/m/Y H:i', strtotime((string)$configBackup['ultimo_backup_em']))
    : '--';
$backupProximoTexto = !empty($configBackup['proximo_backup_em'])
    ? date('d/m/Y H:i', strtotime((string)$configBackup['proximo_backup_em']))
    : '--';
$backupRetencaoTexto = max(1, (int)($configBackup['retencao_dias'] ?? 30)) . ' dias';
$backupHoraTexto = substr((string)($configBackup['hora_execucao'] ?? '00:00:00'), 0, 5);
$backupCronCommand = 'php "' . str_replace('\\', '/', __DIR__ . '/cron/backup_automatico.php') . '"';
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
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
            padding: 32px;
            min-height: 100vh;
            background: var(--bg);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .backup-topbar-main {
            min-width: 0;
        }

        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .page-title i {
            color: var(--primary);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: var(--text);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            margin-bottom: 24px;
        }

        .back-btn:hover {
            transform: translateX(-4px);
            box-shadow: var(--shadow-lg);
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
            border-bottom: 1px solid var(--border);
            padding: 20px 24px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 18px;
        }

        .card-body {
            padding: 24px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .status-badge.ativo {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-badge.inativo {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-badge.manual {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
        }

        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            box-shadow: var(--shadow);
            height: 100%;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
        }

        .feature-card h5 {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
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
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-success:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
            color: #10b981;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .alert {
            border-radius: 12px;
            padding: 16px 20px;
            border: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
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
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 991px) {
            .card-body .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }

            .status-badge {
                align-self: flex-start;
            }

            .row.g-3.mb-4>[class*="col-md-4"] {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .row.g-4.mb-4>[class*="col-md-4"] {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .card-header {
                padding: 18px 20px;
            }

            .stat-card {
                padding: 18px;
            }

            .feature-card {
                min-height: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 12px;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
            }

            .top-bar-right {
                width: 100%;
            }

            .card-body {
                padding: 16px;
            }

            .card-body .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }

            .status-badge {
                width: 100%;
                justify-content: center;
            }

            .stat-card {
                padding: 16px;
                gap: 12px;
            }

            .stat-value {
                font-size: 20px;
                line-height: 1.15;
            }

            .stat-label {
                font-size: 11px;
            }

            .feature-card {
                padding: 16px;
                border-radius: 18px;
            }

            .feature-icon {
                width: 54px;
                height: 54px;
                font-size: 22px;
            }

            .card-header {
                padding: 14px 16px;
            }

            .card-header code {
                display: block;
                margin-top: 8px;
                white-space: normal;
                word-break: break-word;
            }

            .table-responsive {
                border-radius: 16px;
            }

            .table thead th,
            .table tbody td {
                padding: 10px 12px;
                font-size: 12px;
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

            .row.g-3.mb-4>[class*="col-md-4"],
            .row.g-4.mb-4>[class*="col-md-4"] {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>
</head>

<body class="premium-ui">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

            <!-- MAIN CONTENT -->
            <main class="main-content" id="mainContent">
                <div class="top-bar">
                    <div class="sidebar-topbar-main backup-topbar-main">
                        <h1 class="page-title mb-0"><i class="fas fa-database"></i> Backup e Recuperação</h1>
                    </div>
                    <div class="top-bar-right">
                        <a href="dashboard.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                        </a>
                    </div>
                </div>

                <!-- Mensagens -->
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> fade-in mb-4">
                        <i class="fas fa-<?php echo $tipo_msg == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($mensagem); ?>
                    </div>
                <?php endif; ?>

                <!-- Status do Backup -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="mb-1"><i class="fas fa-shield-alt me-2 text-success"></i>Backup e Recuperação</h4>
                                <p class="text-muted mb-0">
                                    <?php if ($permiteBackupAutomatico): ?>
                                        O sistema pode executar backups automáticos conforme a configuração salva.
                                    <?php else: ?>
                                        Seu plano permite backup manual e recuperação do banco.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="status-badge <?php echo $backupStatusClasse; ?>">
                                <i class="fas fa-<?php echo $backupStatusAtivo ? 'check-circle' : 'circle'; ?>"></i> <?php echo htmlspecialchars($backupStatusTexto); ?>
                            </div>
                        </div>

                        <!-- Stats Row -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo htmlspecialchars($backupFrequenciaTexto); ?></div>
                                        <div class="stat-label">Frequência</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                        <i class="fas fa-cloud-download-alt"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo $permiteBackupAutomatico ? 'Automático' : 'Manual'; ?></div>
                                        <div class="stat-label">Modo</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: rgba(255, 107, 53, 0.1); color: #FF6B35;">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo htmlspecialchars($backupRetencaoTexto); ?></div>
                                        <div class="stat-label">Retenção</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo htmlspecialchars($backupUltimoTexto); ?></div>
                                        <div class="stat-label">Ultimo backup</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo htmlspecialchars($backupProximoTexto); ?></div>
                                        <div class="stat-label">Proximo agendamento</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: rgba(255, 107, 53, 0.1); color: #FF6B35;">
                                        <i class="fas fa-file-archive"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo (int)count($historico); ?></div>
                                        <div class="stat-label">Backups no historico</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-3 flex-wrap">
                            <a href="?acao=download" class="btn btn-primary">
                                <i class="fas fa-download"></i> Baixar Backup Agora
                            </a>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recoveryModal">
                                <i class="fas fa-undo"></i> Recuperar Backup
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($permiteBackupConfig): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-sliders-h me-2"></i>Configuração do Backup Automático
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="acao" value="salvar_config">

                                <div class="col-md-4">
                                    <label class="form-label">Backup automático</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="backup_automatico" name="backup_automatico" <?php echo $backupStatusAtivo ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="backup_automatico">Ativar execução agendada</label>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Ao desativar, o sistema continua permitindo backup manual e restauração.
                                    </small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Frequência</label>
                                    <select name="frequencia" class="form-select">
                                        <option value="DIARIO" <?php echo strtoupper((string)$configBackup['frequencia']) === 'DIARIO' ? 'selected' : ''; ?>>Diário</option>
                                        <?php if ($permiteBackupHora): ?>
                                            <option value="HORARIO" <?php echo strtoupper((string)$configBackup['frequencia']) === 'HORARIO' ? 'selected' : ''; ?>>A cada hora</option>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted d-block mt-2">
                                        <?php if ($permiteBackupHora): ?>
                                            O plano empresarial pode usar backups horários.
                                        <?php else: ?>
                                            O seu plano usa backup diário.
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Hora de execução</label>
                                    <input type="time" name="hora_execucao" class="form-control" value="<?php echo htmlspecialchars($backupHoraTexto); ?>">
                                    <small class="text-muted d-block mt-2">
                                        Para backups diários, esta é a hora preferida de execução.
                                    </small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Retenção</label>
                                    <input type="number" name="retencao_dias" class="form-control" min="1" max="3650" value="<?php echo (int)($configBackup['retencao_dias'] ?? 30); ?>">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Notificação por email</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="notificar_email" name="notificar_email" <?php echo !empty($configBackup['notificar_email']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notificar_email">Avisar quando o backup terminar</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-terminal me-2"></i>
                                        Cron sugerido:
                                        <code><?php echo htmlspecialchars($backupCronCommand); ?></code>
                                    </div>
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Salvar Configuração
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                O seu plano atual permite backup manual. Ative um plano com automação para configurar execucao agendada.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recursos do Backup -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <h5><?php echo $permiteBackupAutomatico ? 'Backup Automático' : 'Backup Manual'; ?></h5>
                            <p class="text-muted">
                                <?php if ($permiteBackupAutomatico): ?>
                                    Os backups sao agendados conforme a configuracao salva no painel.
                                <?php else: ?>
                                    Gere o arquivo SQL quando precisar e mantenha uma copia segura.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white;">
                                <i class="fas fa-download"></i>
                            </div>
                            <h5>Download do Banco</h5>
                            <p class="text-muted">Baixe quando quiser. O arquivo fica salvo no historico para recuperacao posterior.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon" style="background: linear-gradient(135deg, #FF6B35, #F7931E); color: white;">
                                <i class="fas fa-undo"></i>
                            </div>
                            <h5>Recuperação Segura</h5>
                            <p class="text-muted">Restaure um arquivo .sql sem mexer nas tabelas do sistema fora do restaurante atual.</p>
                        </div>
                    </div>
                </div>

                <!-- Histórico de Backups -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i>Histórico de Backups
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Executado em</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Tamanho</th>
                                        <th>Origem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historico as $h): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($h['executado_em'])); ?></td>
                                            <td><?php echo htmlspecialchars($h['tipo']); ?></td>
                                            <td>
                                                <span class="badge-custom <?php echo ($h['status'] === 'SUCESSO') ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo htmlspecialchars($h['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($h['tamanho_formatado']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst((string)$h['origem'])); ?></td>
                                            <td>
                                                <?php if (!empty($h['arquivo_caminho']) && is_file((string)$h['arquivo_caminho'])): ?>
                                                    <a href="?acao=download_historico&id=<?php echo (int)$h['id']; ?>" class="btn btn-sm btn-primary" title="Baixar backup">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Indisponível</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($historico)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">Nenhum backup registrado ainda.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Recuperação de Backup -->
    <div class="modal fade" id="recoveryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Recuperar Backup</h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> A recuperação de backup substituirá todos os dados atuais. Esta ação não pode ser desfeita.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Selecione o arquivo de backup (.sql)</label>
                        <input type="file" class="form-control" id="backupFile" accept=".sql">
                    </div>

                    <div id="recoveryStatus" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="restaurarBackup()">
                        <i class="fas fa-undo me-1"></i> Restaurar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function restaurarBackup() {
            var fileInput = document.getElementById('backupFile');
            var statusDiv = document.getElementById('recoveryStatus');
            var btnRestaurar = document.querySelector('#recoveryModal .btn-success');

            if (!fileInput.files.length) {
                statusDiv.className = 'alert alert-danger';
                statusDiv.textContent = 'Por favor, selecione um arquivo de backup!';
                statusDiv.style.display = 'block';
                return;
            }

            var formData = new FormData();
            formData.append('backup_file', fileInput.files[0]);

            // Bloquear botão para evitar duplo envio
            btnRestaurar.disabled = true;
            btnRestaurar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>A restaurar...';

            statusDiv.className = 'alert alert-info';
            statusDiv.textContent = 'A restaurar backup, aguarde...';
            statusDiv.style.display = 'block';

            // Timeout de segurança: re-activar botão após 60s caso algo falhe silenciosamente
            var timeoutGuard = setTimeout(function() {
                btnRestaurar.disabled = false;
                btnRestaurar.innerHTML = '<i class="fas fa-undo me-1"></i> Restaurar';
                statusDiv.className = 'alert alert-danger';
                statusDiv.textContent = 'Tempo esgotado. Tente novamente.';
                statusDiv.style.display = 'block';
            }, 60000);

            fetch('api/backup_restaurar.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Servidor retornou erro ' + response.status);
                    }
                    return response.text();
                })
                .then(function(text) {
                    clearTimeout(timeoutGuard);
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        // Resposta não é JSON (ex: PHP mostrando erro ou redirecionamento)
                        throw new Error('Resposta inválida do servidor. Verifique o log de erros PHP.');
                    }
                    if (data.success) {
                        statusDiv.textContent = data.message || 'Backup restaurado com sucesso!';
                        statusDiv.className = 'alert alert-success';
                        statusDiv.style.display = 'block';

                        setTimeout(function() {
                            var modal = bootstrap.Modal.getInstance(document.getElementById('recoveryModal'));
                            if (modal) modal.hide();
                            window.location.reload();
                        }, 2000);
                    } else {
                        statusDiv.className = 'alert alert-danger';
                        statusDiv.textContent = data.message || 'Erro ao restaurar backup!';
                        statusDiv.style.display = 'block';
                        btnRestaurar.disabled = false;
                        btnRestaurar.innerHTML = '<i class="fas fa-undo me-1"></i> Restaurar';
                    }
                })
                .catch(function(err) {
                    clearTimeout(timeoutGuard);
                    statusDiv.className = 'alert alert-danger';
                    statusDiv.textContent = 'Erro: ' + err.message;
                    statusDiv.style.display = 'block';
                    btnRestaurar.disabled = false;
                    btnRestaurar.innerHTML = '<i class="fas fa-undo me-1"></i> Restaurar';
                });
        }
    </script>
</body>

</html>

