<?php
// Proteção da página
include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/restaurante_context.php';
// Verificar restaurante_id
$restaurante_id = session_restaurante_contexto_id();
if ($restaurante_id <= 0) {
    header("Location: index.php?erro=sem_restaurante");
    exit;
}

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../Model/Produto.php';
include_once __DIR__ . '/../Model/Categoria.php';

// Conectar ao banco
$database = new Database();
$db = $database->getConnection();

// Instanciar classes
$produto   = new Produto($db);
$categoria = new Categoria($db);

// Buscar produtos (array)
$stmt_produtos = $produto->listar($restaurante_id);
$lista_produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para o filtro/modal
$stmt_cat = $categoria->listar($restaurante_id);
$lista_categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$total_produtos = count($lista_produtos);
$produtos_ativos = count(array_filter($lista_produtos, fn($p) => $p['ativo'] == 1));
$estoque_baixo = count(array_filter($lista_produtos, fn($p) => $p['estoque'] <= $p['estoque_minimo']));

// Base URL - adjusted to point to src/public where the API actually is
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/');
// Don't replace src/public with public - the API is in src/public/api/
$base_url = $base_url . '/';
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
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
                transition: left 0.3s cubic-bezier(.4, 0, .2, 1);
                box-shadow: 2px 0 16px rgba(0, 0, 0, 0.08);
            }

            .sidebar.sidebar-hidden {
                left: -100vw !important;
            }

            .main-content-blur {
                filter: blur(2px) grayscale(0.1);
                pointer-events: none;
            }
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
            grid-template-columns: repeat(3, 1fr);
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

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.primary {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary);
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .stat-icon.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
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
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
        }

        .btn-sm {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
        }

        .btn-success {
            background: var(--success);
            border: none;
        }

        .btn-warning {
            background: var(--warning);
            border: none;
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            border: none;
        }

        .btn-info {
            background: var(--info);
            border: none;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin: 0 3px;
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

        .product-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 3px solid var(--border);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background-color: #f8f9fa;
        }

        .produtos-toolbar-card,
        .produtos-categoria-card {
            overflow: visible;
        }

        .produtos-toolbar-actions {
            align-items: stretch;
        }

        .produto-status-stack {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .product-info {
            display: flex;
            flex-direction: column;
        }

        .product-name {
            font-weight: 700;
            font-size: 16px;
            color: var(--text);
        }

        .product-desc {
            font-size: 13px;
            color: var(--text-muted);
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-area {
                padding: 20px;
            }

            .produtos-toolbar-actions > [class*="col-"] {
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
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

            .content-area {
                padding: 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 14px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 16px;
                border-radius: 18px;
            }

            #botoesCategoria {
                flex-direction: column;
            }

            #botoesCategoria .btn {
                width: 100%;
            }

            .card .row.g-3>[class*="col-"] {
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
            }

            .card {
                border-radius: 18px;
            }

            .card-body {
                padding: 16px;
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

            .product-img {
                width: 56px !important;
                height: 56px !important;
            }

            .product-name {
                font-size: 13px;
            }

            .product-desc {
                font-size: 11px;
            }

            .btn-action {
                width: 38px;
                height: 38px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .table td.text-center {
                white-space: nowrap;
            }

            .modal-dialog.modal-lg {
                max-width: calc(100% - 20px);
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

<body class="premium-ui">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

            <!-- MAIN CONTENT -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <div class="top-bar">
                    <h1 class="page-title"><i class="fas fa-pizza-slice"></i> Gestão de Produtos</h1>
                </div>

                <div class="content-area">
                    <!-- STATS -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon primary"><i class="fas fa-box"></i></div>
                            </div>
                            <div class="stat-value" style="color: var(--primary);"><?php echo $total_produtos; ?></div>
                            <div class="stat-label">Total de Produtos</div>
                        </div>
                        <div class="stat-card success">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                            </div>
                            <div class="stat-value" style="color: var(--success);"><?php echo $produtos_ativos; ?></div>
                            <div class="stat-label">Produtos Ativos</div>
                        </div>
                        <div class="stat-card warning">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
                            </div>
                            <div class="stat-value" style="color: var(--warning);"><?php echo $estoque_baixo; ?></div>
                            <div class="stat-label">Estoque Baixo</div>
                        </div>
                    </div>

                    <!-- BOTÕES DE CATEGORIA -->
                    <div class="card mb-4 produtos-categoria-card">
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2" id="botoesCategoria">
                                <button class="btn btn-outline-primary categoria-btn active" data-categoria-id="">
                                    <i class="fas fa-list me-1"></i>Todas
                                </button>
                                <?php foreach ($lista_categorias as $cat): ?>
                                    <button class="btn btn-outline-primary categoria-btn"
                                        data-categoria-id="<?php echo $cat['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>">
                                        <?php echo htmlspecialchars($cat['nome']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- AÇÕES -->
                    <div class="card produtos-toolbar-card">
                        <div class="card-body">
                            <div class="row g-3 produtos-toolbar-actions">
                                <div class="col-md-5">
                                    <input type="text" id="buscar" class="form-control" placeholder="🔍 Buscar produto...">
                                </div>
                                <div class="col-md-4">
                                    <select id="filtroCategoria" class="form-select">
                                        <option value="">Todas as categorias</option>
                                        <?php foreach ($lista_categorias as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary w-100" onclick="abrirModal()"><i class="fas fa-plus me-2"></i>Novo Produto</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TABELA -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Imagem</th>
                                            <th>Produto</th>
                                            <th>Categoria</th>
                                            <th>Preço</th>
                                            <th>Estoque</th>
                                            <th>Status</th>
                                            <th class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabelaProdutos">
                                        <?php foreach ($lista_produtos as $p): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    // Mostrar imagem do produto ou avatar automático
                                                    // alguns registros antigos podem ter salvo o prefixo errado
                                                    $caminho_imagem = $p['imagem'];
                                                    if ($caminho_imagem) {
                                                        $caminho_imagem = str_replace('src/public/', '', $caminho_imagem);
                                                    }

                                                    if (!empty($caminho_imagem) && file_exists(__DIR__ . '/' . $caminho_imagem)) {
                                                        // Usar imagem salva
                                                        $imgSrc = $base_url . $caminho_imagem;
                                                    } else {
                                                        // Gerar avatar automático baseado no nome do produto
                                                        // Cores para restaurantes
                                                        $cores = ['FF6B35', 'F7931E', '10b981', '3b82f6', '8b5cf6', 'ec4899'];
                                                        $cor = $cores[array_rand($cores)];
                                                        $imgSrc = "https://ui-avatars.com/api/?name=" . urlencode($p['nome']) . "&background=" . $cor . "&color=ffffff&size=128&bold=true";
                                                    }
                                                    ?>
                                                    <img src="<?php echo $imgSrc; ?>" class="product-img" alt="<?php echo htmlspecialchars($p['nome']); ?>" style="width: 80px; height: 80px;" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($p['nome']); ?>&background=FF6B35&color=ffffff&size=80'">
                                                </td>
                                                <td>
                                                    <div class="product-info">
                                                        <span class="product-name"><?php echo htmlspecialchars($p['nome']); ?></span>
                                                        <?php if (!empty($p['descricao'])): ?>
                                                            <span class="product-desc"><?php echo htmlspecialchars($p['descricao']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td style="cursor:pointer; color:#FF6B35; text-decoration:underline;" title="Clique para filtrar por esta categoria" data-categoria-id="<?php echo htmlspecialchars($p['categoria_id']); ?>"><?php echo htmlspecialchars($p['categoria_nome'] ?? '—'); ?></td>
                                                <td><strong><?php echo number_format($p['preco'], 2, ',', '.'); ?> MZN</strong></td>
                                                <td>
                                                    <span class="produto-status-stack">
                                                        <?php if ($p['estoque'] <= $p['estoque_minimo']): ?>
                                                            <span class="badge-custom badge-warning">⚠️ <?php echo $p['estoque']; ?></span>
                                                        <?php else: ?>
                                                            <span class="badge-custom badge-success"><?php echo $p['estoque']; ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="produto-status-stack">
                                                        <?php if ($p['ativo']): ?>
                                                            <span class="badge-custom badge-success">Ativo</span>
                                                        <?php else: ?>
                                                            <span class="badge-custom badge-danger">Inativo</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-info btn-action btn-sm" onclick="editarProduto(<?php echo $p['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                                    <button class="btn btn-warning btn-action btn-sm" onclick="atualizarEstoque(<?php echo $p['id']; ?>)" title="Estoque"><i class="fas fa-box"></i></button>
                                                    <button class="btn btn-danger btn-action btn-sm" onclick="deletarProduto(<?php echo $p['id']; ?>)" title="Excluir"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($lista_produtos)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <i class="fas fa-pizza-slice fa-3x text-muted mb-3 d-block"></i>
                                                    <p class="text-muted mb-3">Nenhum produto cadastrado</p>
                                                    <button class="btn btn-primary" onclick="abrirModal()"><i class="fas fa-plus me-2"></i>Cadastrar primeiro produto</button>
                                                </td>
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

    <!-- MODAL PRODUTO -->
    <div class="modal fade" id="modalProduto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloModal"><i class="fas fa-plus me-2"></i>Novo Produto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="alertModal" style="display: none;"></div>
                    <form id="formProduto">
                        <input type="hidden" id="produto_id" name="produto_id">

                        <!-- Foto Centralizada -->
                        <div class="text-center mb-4">
                            <div class="position-relative d-inline-block">
                                <img id="imagemPreview" src="" class="img-fluid rounded-circle"
                                    style="width:120px;height:120px;object-fit:cover;border:4px solid #FF6B35;display:none;">
                                <div id="imagemPlaceholder" class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                    style="width:120px;height:120px;border:4px solid #FF6B35;">
                                    <i class="fas fa-utensils fa-3x text-muted"></i>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle"
                                    style="width:36px;height:36px;" onclick="document.getElementById('imagem').click()">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <input type="file" id="imagem" name="imagem" accept="image/*" style="display:none;" onchange="previewImagem(this)">
                            <input type="hidden" id="imagem_existing" name="imagem_existing">
                            <p class="text-muted small mt-2">Clique na câmera para alterar a foto</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label"><i class="fas fa-utensils me-2"></i>Nome do Produto *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                    <input type="text" id="nome" name="nome" class="form-control" required placeholder="Ex: Frango Grelhado">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-money-bill me-2"></i>Preço (MZN) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">MZN</span>
                                    <input type="number" id="preco" name="preco" class="form-control" step="0.01" min="0" required placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <label class="form-label"><i class="fas fa-list me-2"></i>Categoria</label>
                                <div class="input-group mb-2">
                                    <input type="text" id="novaCategoria" class="form-control" placeholder="Nova categoria..." autocomplete="off">
                                    <button class="btn btn-success" type="button" onclick="adicionarNovaCategoria()"><i class="fas fa-plus-circle"></i></button>
                                </div>
                                <select id="categoria_id" name="categoria_id" class="form-select">
                                    <option value="">— Selecione uma categoria —</option>
                                    <?php foreach ($lista_categorias as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <label class="form-label"><i class="fas fa-align-left me-2"></i>Descrição</label>
                                <textarea id="descricao" name="descricao" class="form-control" rows="2" placeholder="Descrição do produto..."></textarea>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-coins me-2"></i>Custo (MZN)</label>
                                <div class="input-group">
                                    <span class="input-group-text">MZN</span>
                                    <input type="number" id="custo" name="custo" class="form-control" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-boxes me-2"></i>Estoque</label>
                                <input type="number" id="estoque" name="estoque" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-exclamation-triangle me-2"></i>Estoque Mín.</label>
                                <input type="number" id="estoque_minimo" name="estoque_minimo" class="form-control" value="5" min="0">
                            </div>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input type="checkbox" id="ativo" name="ativo" class="form-check-input" checked>
                            <label class="form-check-label">Produto Ativo</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formProduto" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
        const RESTAURANTE_ID = <?php echo (int)$restaurante_id; ?>;
    </script>
    <script src="js/produtos.js"></script>
</body>

</html>

