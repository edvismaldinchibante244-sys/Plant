<?php
// Proteção da página - apenas ADMIN
include_once __DIR__ . '/../config/auth_check.php';
checkPermission(['ADMIN']);

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/restaurante_context.php';
include_once __DIR__ . '/../Model/Auth.php';

// Conectar ao banco
$database = new Database();
$db = $database->getConnection();

// calcular base URL (sem barra no final)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');


$restauranteId = session_restaurante_contexto_id();

// Buscar usuários do restaurante
$query = "SELECT id, nome, email, perfil, ativo, foto, criado_em FROM usuarios WHERE restaurante_id = :rid ORDER BY nome ASC";
$stmt = $db->prepare($query);
$stmt->bindValue(':rid', $restauranteId, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Sistema de Restaurante</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">

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
            overflow-x: hidden;
            min-height: 0;
            padding-bottom: 12px;
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

        .avatar-lg {
            width: 100px;
            height: 100px;
            border-radius: 16px;
            border: 3px solid white;
            box-shadow: var(--shadow);
        }

        .avatar-sm {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 28px;
            overflow: hidden;
        }

        .avatar-sm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .badge-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-light);
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

        .btn-warning {
            background: var(--warning);
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            color: white;
        }

        .btn-sm {
            padding: 8px 14px;
            border-radius: 10px;
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

        .form-control,
        .form-select {
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid var(--border);
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }

        .foto-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border);
            margin: 0 auto;
            display: block;
        }

        .foto-upload-wrapper {
            text-align: center;
            margin-bottom: 20px;
        }

        @media (max-width: 991px) {
            .sidebar {
                width: 80vw;
                max-width: 340px;
                min-width: 220px;
                position: fixed;
                left: 0;
                top: 0;
                z-index: 2000;
                height: 100vh;
                transition: left 0.3s cubic-bezier(.4, 0, .2, 1);
                box-shadow: 2px 0 16px rgba(0, 0, 0, 0.08);
                display: flex;
            }

            .sidebar.sidebar-hidden {
                left: -100vw !important;
                transition: left 0.3s cubic-bezier(.4, 0, .2, 1);
            }

            .main-content {
                margin-left: 0 !important;
            }

            .top-bar {
                margin: 0 0 20px 0;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 12px;
            }

            .top-bar {
                padding: 12px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                margin: 0 0 16px 0;
            }

            .page-title {
                font-size: 18px;
                gap: 8px;
            }

            .card-body.d-flex.flex-wrap.gap-2.justify-content-between.align-items-center {
                flex-direction: column;
                align-items: stretch !important;
            }

            .card-body.d-flex.flex-wrap.gap-2.justify-content-between.align-items-center .d-flex.align-items-center.gap-2 {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            #filtroPerfil {
                min-width: 0 !important;
                width: 100% !important;
            }

            .table-card .table th,
            .table-card .table td {
                padding: 10px 12px;
                font-size: 12px;
                white-space: normal;
            }

            .avatar-lg {
                width: 42px;
                height: 42px;
            }

            .avatar-sm {
                width: 34px;
                height: 34px;
            }

            .btn-sm {
                width: 100%;
            }

            .table-card .card-body.p-0 {
                overflow-x: auto;
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

            <!-- CONTEÚDO PRINCIPAL -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10">

                <!-- TOP BAR -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0"><i class="fas fa-users text-primary me-2"></i>Gestão de Usuários</h4>
                        <p class="text-muted mb-0" style="font-size: 14px;">Gerencie os acessos ao sistema</p>
                    </div>
                    <?php
                    $foto_atual = !empty($_SESSION['foto']) ? $_SESSION['foto'] : '';
                    $img_url = !empty($foto_atual) ? $foto_atual : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['nome']) . '&background=FF6B35&color=fff&size=50';
                    ?>
                    <?php if (!empty($foto_atual)): ?>
                        <img src="<?php echo htmlspecialchars($foto_atual); ?>" class="avatar-lg" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['nome']); ?>&background=FF6B35&color=fff&size=50'">
                    <?php else: ?>
                        <img src="<?php echo $img_url; ?>" class="avatar-lg">
                    <?php endif; ?>
                </div>

                <!-- AÇÕES -->
                <div class="card mb-4">
                    <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <button class="btn btn-primary" onclick="abrirModal()"><i class="fas fa-plus me-2"></i>Novo Usuário</button>
                        <div class="d-flex align-items-center gap-2">
                            <label for="filtroPerfil" class="mb-0 text-muted" style="font-size: 13px;">Filtrar perfil:</label>
                            <select id="filtroPerfil" class="form-select form-select-sm" style="min-width: 170px;">
                                <option value="">Todos</option>
                                <option value="ADMIN">Administrador</option>
                                <option value="CAIXA">Caixa</option>
                                <option value="GARCOM">Garçom</option>
                                <option value="COZINHA">Cozinha</option>
                                <option value="BAR">Bar</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TABELA -->
                <div class="card table-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Usuário</th>
                                        <th>Email</th>
                                        <th>Perfil</th>
                                        <th>Status</th>
                                        <th>Cadastro</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                        <?php
                                        // montar URL da foto se houver
                                        $fotoUrl = '';
                                        if (!empty($u['foto'])) {
                                            $fotoUrl = (strpos($u['foto'], 'http') === 0)
                                                ? $u['foto']
                                                : $baseUrl . '/' . ltrim($u['foto'], '/');
                                        }
                                        ?>
                                        <tr class="usuario-row" data-perfil="<?php echo htmlspecialchars(strtoupper((string)$u['perfil'])); ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($fotoUrl): ?>
                                                        <a href="<?php echo htmlspecialchars($fotoUrl); ?>" target="_blank" class="me-3" title="Abrir foto em nova aba">
                                                            <div class="avatar-sm" style="background: transparent;">
                                                                <img src="<?php echo htmlspecialchars($fotoUrl); ?>" onerror="this.style.display='none'; this.parentElement.style.background='<?php echo $u['perfil'] == 'ADMIN' ? '#dc3545' : ($u['perfil'] == 'CAIXA' ? '#17a2b8' : ($u['perfil'] == 'COZINHA' ? '#6c757d' : ($u['perfil'] == 'BAR' ? '#3b82f6' : '#ffc107'))); ?>';">
                                                            </div>
                                                        </a>
                                                    <?php else: ?>
                                                        <div class="avatar-sm me-3" style="background: <?php echo $u['perfil'] == 'ADMIN' ? '#dc3545' : ($u['perfil'] == 'CAIXA' ? '#17a2b8' : ($u['perfil'] == 'COZINHA' ? '#6c757d' : ($u['perfil'] == 'BAR' ? '#3b82f6' : '#ffc107'))); ?>;">
                                                            <?php echo strtoupper(substr($u['nome'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($u['nome']); ?></strong>
                                                        <br>
                                                        <span class="badge bg-<?php echo $u['perfil'] == 'ADMIN' ? 'danger' : ($u['perfil'] == 'CAIXA' ? 'info' : ($u['perfil'] == 'COZINHA' ? 'secondary' : ($u['perfil'] == 'BAR' ? 'primary' : 'warning'))); ?>">
                                                            <?php echo $u['perfil']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['perfil'] == 'ADMIN' ? 'danger' : ($u['perfil'] == 'CAIXA' ? 'info' : ($u['perfil'] == 'COZINHA' ? 'secondary' : ($u['perfil'] == 'BAR' ? 'primary' : 'warning'))); ?>">
                                                    <?php echo $u['perfil']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($u['ativo']): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($u['criado_em'])); ?></td>
                                            <td>
                                                <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                                                    <?php if (isset($u['id']) && is_numeric($u['id']) && $u['id'] > 0): ?>
                                                        <button class="btn btn-sm btn-info" onclick="editarUsuario(<?php echo $u['id']; ?>)"><i class="fas fa-edit"></i></button>
                                                        <button class="btn btn-sm btn-warning" onclick="alterarSenha(<?php echo $u['id']; ?>)"><i class="fas fa-key"></i></button>
                                                        <button class="btn btn-sm btn-danger" onclick="deletarUsuario(<?php echo $u['id']; ?>)"><i class="fas fa-trash"></i></button>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">ID inválido</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Você</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($usuarios)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                                Nenhum usuário encontrado
                                            </td>
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

    <!-- MODAL CADASTRO/EDIÇÃO -->
    <div class="modal fade" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModal"><i class="fas fa-plus me-2"></i>Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="alertModal" style="display: none;"></div>
                    <form id="formUsuario" enctype="multipart/form-data">
                        <input type="hidden" id="usuario_id" name="usuario_id">

                        <!-- Foto do usuário (apenas PNG) -->
                        <div class="foto-upload-wrapper">
                            <input type="file" id="foto" name="foto" accept="image/png,image/jpeg" style="display: none;" onchange="previewFoto(this)">
                            <img id="fotoPreview" class="foto-preview" src="https://ui-avatars.com/api/?name=?&background=FF6B35&color=fff&size=100" alt="Foto do usuário">
                            <p class="text-muted small mb-2">Apenas imagens PNG ou JPEG</p>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="document.getElementById('foto').click()">
                                <i class="fas fa-camera me-1"></i> Escolher Foto
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm mt-1" onclick="removerFoto()">
                                <i class="fas fa-times me-1"></i> Remover
                            </button>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" id="nome" name="nome" class="form-control" required placeholder="Ex: João Silva">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="email@exemplo.com">
                        </div>

                        <div class="mb-3" id="senhaGroup">
                            <label class="form-label">Senha *</label>
                            <input type="password" id="senha" name="senha" class="form-control" placeholder="Mínimo 6 caracteres">
                            <small class="text-muted" id="senhaHint">Deixe em branco para manter a senha atual (ao editar)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Perfil *</label>
                            <select id="perfil" name="perfil" class="form-select" required>
                                <option value="ADMIN">👑 Administrador</option>
                                <option value="CAIXA">💵 Caixa</option>
                                <option value="GARCOM">🍽️ Garçom</option>
                                <option value="COZINHA">🔥 Cozinha</option>
                                <option value="BAR">🍹 Bar</option>
                            </select>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" id="ativo" name="ativo" class="form-check-input" checked>
                            <label class="form-check-label">Usuário Ativo</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formUsuario" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ALTERAR SENHA -->
    <div class="modal fade" id="modalSenha" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Alterar Senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="alertSenha" style="display: none;"></div>
                    <form id="formSenha">
                        <input type="hidden" id="senha_usuario_id" name="usuario_id">

                        <div class="mb-3">
                            <label class="form-label">Nova Senha *</label>
                            <input type="password" id="nova_senha" name="nova_senha" class="form-control" required placeholder="Mínimo 6 caracteres">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar Senha *</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" required placeholder="Repita a senha">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formSenha" class="btn btn-warning"><i class="fas fa-key me-2"></i>Alterar Senha</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
            handleResize();
        });
        // make baseUrl available to javascript
        const BASE_URL = '<?php echo $baseUrl; ?>';
    </script>
    <script src="js/usuarios.js"></script>
    <script>
        // Preview da foto
        function previewFoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('fotoPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Remover foto
        function removerFoto() {
            document.getElementById('foto').value = '';
            document.getElementById('fotoPreview').src = 'https://ui-avatars.com/api/?name=?&background=FF6B35&color=fff&size=100';
        }
    </script>
</body>

</html>

