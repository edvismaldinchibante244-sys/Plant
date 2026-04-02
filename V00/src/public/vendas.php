<?php

//Tela principal do PDV
// Este arquivo exibe o fluxo de vendas, pedidos abertos e histórico do dia.
// Comentários focados em manutenção e entendimento do fluxo.

// Proteção de acesso: só usuários autenticados
include_once __DIR__ . '/../config/auth_check.php';
// Conexão com banco de dados
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/plano_check.php';
include_once __DIR__ . '/../config/restaurante_context.php';
// Modelos principais
include_once __DIR__ . '/../Model/Caixa.php';
include_once __DIR__ . '/../Model/Produto.php';
include_once __DIR__ . '/../Model/Mesa.php';

// Instancia conexão
$database = new Database();
$db = $database->getConnection();

// Instancia modelos
$caixa   = new Caixa($db);
$produto = new Produto($db);
$mesa    = new Mesa($db);
$restauranteFeatureId = session_restaurante_capability_id();
$restauranteFeatureId = $restauranteFeatureId > 0 ? $restauranteFeatureId : session_restaurante_contexto_id();
$temPedidosOnline = $restauranteFeatureId > 0 && plano_tem_funcionalidade_db($restauranteFeatureId, 'pedidos_online');

// Verifica se há caixa aberto para o restaurante atual
$caixa_aberto = $caixa->buscarAberto($_SESSION['restaurante_id']);

// Busca todos os produtos ativos para exibir no PDV
$stmt_produtos  = $produto->listar($_SESSION['restaurante_id']);
$todos_produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);
$lista_produtos = [];
foreach ($todos_produtos as $_p) {
    if ($_p['ativo'] == 1) {
        $lista_produtos[] = $_p;
    }
}

// Busca categorias de produtos
include_once __DIR__ . '/../Model/Categoria.php';
$categoria = new Categoria($db);
$stmt_cat = $categoria->listar($_SESSION['restaurante_id']);
$lista_categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Monta base URL para imagens dos produtos
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/');
$base_url = $base_url . '/';

// Busca mesas disponíveis para seleção no carrinho
$stmt_mesas = $mesa->mesasLivres($_SESSION['restaurante_id']);
$lista_mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// INTEGRAÇÃO COM PEDIDOS
// ===============================
// Busca pedidos abertos aguardando pagamento
// Exibir apenas pedidos PRONTO/ENTREGUE e sem itens pendentes (todos itens prontos ou entregues)

$stmt_abertos = $db->prepare(
    "SELECT p.id, p.numero_pedido, p.total, p.mesa_id,
                        m.numero AS mesa_numero, p.cliente_nome, p.criado_em, p.status
         FROM pedidos p
         LEFT JOIN mesas m ON p.mesa_id = m.id
         WHERE p.restaurante_id = :rid
             AND p.status IN ('PRONTO', 'ENTREGUE')
             AND NOT EXISTS (
                 SELECT 1 FROM itens_pedido ip
                 WHERE ip.pedido_id = p.id AND (ip.status = 'pendente' OR ip.status = 'em_preparo')
             )
         ORDER BY p.criado_em ASC"
);
$stmt_abertos->execute([':rid' => $_SESSION['restaurante_id']]);
$pedidos_aguardando = $stmt_abertos->fetchAll(PDO::FETCH_ASSOC);

// Busca resumo e histórico das vendas do dia
$stmt_vh = $db->prepare(
    "SELECT v.id, v.numero_fatura, v.total_final, v.forma_pagamento, v.criado_em,
            u.nome AS usuario_nome, m.numero AS mesa_numero
     FROM vendas v
     LEFT JOIN usuarios u ON v.usuario_id = u.id
     LEFT JOIN mesas m ON v.mesa_id = m.id
     WHERE v.restaurante_id = :rid AND DATE(v.criado_em) = CURDATE()
     ORDER BY v.criado_em DESC
     LIMIT 25"
);
$stmt_vh->execute([':rid' => $_SESSION['restaurante_id']]);
$vendas_hoje = $stmt_vh->fetchAll(PDO::FETCH_ASSOC);
$total_hoje   = array_sum(array_column($vendas_hoje, 'total_final'));
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDV - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .menu-toggle-btn {
            display: none;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            border: none;
            border-radius: 16px;
            width: 46px;
            height: 46px;
            font-size: 18px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(255, 107, 53, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .menu-toggle-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(255, 107, 53, 0.3);
        }

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

        body.sidebar-mobile-open {
            overflow: hidden;
        }

        .container-fluid,
        .container-fluid > .row {
            min-height: 100vh;
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
            z-index: 1090;
        }

        body.sidebar-mobile-open .sidebar-backdrop {
            opacity: 1;
            pointer-events: auto;
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
            padding: 0;
            min-height: 100vh;
            background: var(--bg);
        }

        .top-bar {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(14px);
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar-main {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .top-bar-status {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 12px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
        }

        .status-badge.success {
            background: linear-gradient(135deg, var(--success), #20c997);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.18);
        }

        .status-badge.danger {
            background: linear-gradient(135deg, var(--danger), #f87171);
            box-shadow: 0 12px 24px rgba(239, 68, 68, 0.16);
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

        .card {
            border: 1px solid rgba(226, 232, 240, 0.86);
            border-radius: 24px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .search-panel.card {
            overflow: visible;
        }

        .table-responsive {
            scrollbar-width: thin;
        }

        .table th,
        .table td {
            vertical-align: middle;
        }

        .produto-btn {
            background: white;
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 20px 15px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .produto-btn:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 107, 53, 0.2);
        }

        .produto-btn .icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 10px;
            border-radius: 12px;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            overflow: hidden;
        }

        .produto-btn .icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .produto-btn .nome {
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            margin-bottom: 4px;
        }

        .produto-btn .preco {
            color: var(--primary);
            font-weight: 700;
            font-size: 16px;
        }

        .carrinho-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 24px;
            position: sticky;
            top: 100px;
        }

        .total-box {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
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
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: var(--danger);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
        }

        .btn-outline-danger {
            border: 2px solid var(--danger);
            color: var(--danger);
            background: transparent;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            color: white;
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

        @media (max-width: 992px) {
            .menu-toggle-btn {
                display: inline-flex;
            }

            .sidebar {
                width: min(320px, calc(100vw - 24px)) !important;
                max-width: calc(100vw - 24px);
                height: 100dvh !important;
                min-height: 100dvh !important;
                left: 12px !important;
                top: 12px !important;
                transform: translateX(-120%);
                transition: transform 0.28s ease;
                z-index: 1100 !important;
                border-radius: 28px;
                overflow: hidden;
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.38);
            }

            body.sidebar-mobile-open .sidebar {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
            }

            .top-bar {
                padding: 16px 20px;
                gap: 14px;
                flex-wrap: wrap;
            }

            .top-bar-status {
                width: 100%;
                justify-content: flex-start;
            }

            .content-area {
                padding: 20px;
            }

            .produto-btn {
                padding: 16px 12px;
            }

            .produto-btn .icon {
                width: 54px;
                height: 54px;
                font-size: 24px;
            }

            .produto-btn .nome {
                font-size: 13px;
            }

            .produto-btn .preco {
                font-size: 15px;
            }

            .carrinho-card {
                position: static;
                top: auto;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
            }

            .content-area {
                padding: 16px;
            }

            .top-bar {
                padding: 14px 16px;
                gap: 10px;
            }

            .top-bar-main {
                width: 100%;
                align-items: center;
            }

            .top-bar-status {
                width: 100%;
            }

            .page-title {
                font-size: 18px;
            }

            .row.g-4 > [class*="col-"] {
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
            }

            .carrinho-card {
                padding: 20px;
                position: static;
            }

            .carrinho-card h4 {
                font-size: 18px;
            }

            .produto-btn {
                padding: 15px 10px;
                border-radius: 14px;
            }

            .produto-btn .icon {
                width: 50px;
                height: 50px;
                font-size: 22px;
            }

            .produto-btn .preco {
                font-size: 14px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .card-header .btn,
            .card-header span[style] {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                padding: 12px 14px;
            }

            .menu-toggle-btn {
                width: 42px;
                height: 42px;
                border-radius: 14px;
            }

            .page-title {
                font-size: 17px;
                gap: 8px;
            }

            .content-area {
                padding: 12px;
            }

            .content-area .card {
                border-radius: 16px;
            }

            .card.p-4 {
                padding: 16px !important;
            }

            .produto-item {
                padding-left: 4px;
                padding-right: 4px;
            }

            .produto-btn {
                padding: 12px 10px;
            }

            .produto-btn .icon {
                width: 46px;
                height: 46px;
                font-size: 20px;
            }

            .produto-btn .nome {
                font-size: 12px;
            }

            .produto-btn .preco {
                font-size: 13px;
            }

            .carrinho-card {
                padding: 16px;
                border-radius: 16px;
            }

            .total-box {
                padding: 16px;
            }

            #total {
                font-size: 24px !important;
            }

            #carrinhoItens {
                max-height: 240px !important;
            }

            .carrinho-card .d-flex.justify-content-between.align-items-center.mb-3 {
                flex-direction: column;
                align-items: stretch !important;
                gap: 8px;
            }

            .carrinho-card #desconto {
                width: 100% !important;
            }

            .card-header h5 {
                font-size: 16px;
            }

            .card-header .btn {
                width: 100%;
            }

            .status-badge {
                width: 100%;
                white-space: normal;
                border-radius: 16px;
                justify-content: center;
                text-align: center;
            }

            .sidebar {
                left: 8px !important;
                top: 8px !important;
                width: calc(100vw - 16px) !important;
                max-width: calc(100vw - 16px);
                border-radius: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>
            <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <div class="top-bar">
                    <div class="top-bar-main">
                        <h1 class="page-title mb-0"><i class="fas fa-cash-register"></i> PDV - Ponto de Venda</h1>
                    </div>
                    <div class="top-bar-status">
                        <?php if ($caixa_aberto): ?>
                            <span class="status-badge success"><i class="fas fa-circle-check"></i> Caixa aberto desde <?php echo date('H:i', strtotime($caixa_aberto['data_abertura'])); ?></span>
                        <?php else: ?>
                            <span class="status-badge danger"><i class="fas fa-lock"></i> Caixa fechado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-area">
                    <?php if (!$caixa_aberto): ?>
                        <div class="alert d-flex align-items-center mb-4" style="background: var(--warning); color: white; border-radius: 16px; padding: 20px;">
                            <i class="fas fa-exclamation-triangle fs-2 me-3"></i>
                            <div class="flex-grow-1"><strong>Atenção!</strong> Para realizar vendas, é necessário abrir o caixa primeiro.</div>
                            <a href="caixa.php" class="btn" style="background: white; color: var(--warning);">Abrir Caixa</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($caixa_aberto): ?>
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="card search-panel p-4">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-8">
                                            <input type="text" id="buscarProduto" class="form-control" placeholder="🔍 Buscar produto...">
                                        </div>
                                        <div class="col-md-4">
                                            <select id="filtroCategoria" class="form-select">
                                                <option value="">Todas as categorias</option>
                                                <?php foreach ($lista_categorias as $cat): ?>
                                                    <option value="<?php echo htmlspecialchars($cat['nome']); ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <?php foreach ($lista_produtos as $p): ?>
                                            <div class="col-6 col-md-4 col-lg-3 produto-item" data-categoria="<?php echo htmlspecialchars($p['categoria_nome'] ?? ''); ?>" data-id="<?php echo $p['id']; ?>" data-nome="<?php echo htmlspecialchars($p['nome']); ?>" data-preco="<?php echo $p['preco']; ?>" data-estoque="<?php echo $p['estoque']; ?>">
                                                <div class="produto-btn" onclick="adicionarProdutoCarrinho(this)">
                                                    <?php if (!empty($p['imagem'])): ?>
                                                        <div class="icon"><img src="<?php echo $base_url . htmlspecialchars($p['imagem']); ?>" alt="<?php echo htmlspecialchars($p['nome']); ?>" onerror="this.parentElement.innerHTML='🍽️'"></div>
                                                    <?php else: ?>
                                                        <div class="icon">🍽️</div>
                                                    <?php endif; ?>
                                                    <div class="nome"><?php echo htmlspecialchars($p['nome']); ?></div>
                                                    <div class="preco"><?php echo number_format($p['preco'], 2, ',', '.'); ?> MZN</div>
                                                    <?php if ($p['estoque'] <= 0): ?>
                                                        <div class="text-danger small mt-1">Sem estoque</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($lista_produtos)): ?>
                                            <div class="col-12 text-center py-5 text-muted">
                                                <i class="fas fa-pizza-slice fa-3x mb-3 d-block"></i>
                                                <p>Nenhum produto ativo</p>
                                                <a href="produtos.php" class="btn btn-primary">Cadastrar produtos</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="carrinho-card">
                                    <h4 class="mb-4"><i class="fas fa-shopping-cart me-2"></i>Carrinho</h4>
                                    <div class="mb-4">
                                        <label class="form-label">Mesa (opcional)</label>
                                        <select id="mesa_id" class="form-select">
                                            <option value="">Balcão / Sem mesa</option>
                                            <?php foreach ($lista_mesas as $m): ?>
                                                <option value="<?php echo $m['id']; ?>">Mesa <?php echo $m['numero']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div id="carrinhoItens" style="min-height: 150px; max-height: 300px; overflow-y: auto;">
                                        <div class="text-center text-muted py-5">
                                            <i class="fas fa-shopping-cart fa-2x mb-2 d-block"></i>
                                            Carrinho vazio
                                        </div>
                                    </div>
                                    <div class="border-top pt-3 mt-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal:</span>
                                            <span id="subtotal" class="fw-bold">0,00 MZN</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span>Desconto:</span>
                                            <input type="number" id="desconto" class="form-control" style="width: 100px;" value="0" min="0" step="0.01" onchange="calcularTotal()">
                                        </div>
                                    </div>
                                    <div class="total-box mb-4">
                                        <div class="small opacity-75">TOTAL A PAGAR</div>
                                        <div id="total" class="fw-bold" style="font-size: 28px;">0,00 MZN</div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Forma de Pagamento</label>
                                        <select id="forma_pagamento" class="form-select">
                                            <option value="DINHEIRO">💵 Dinheiro</option>
                                            <option value="MPESA">📱 M-Pesa</option>
                                            <option value="CARTAO">💳 Cartão</option>
                                            <option value="TRANSFERENCIA">🏦 Transferência</option>
                                        </select>
                                    </div>
                                    <!-- Botão envia o pedido para produção, texto 'Lançar Pedido' -->
                                    <button class="btn btn-success w-100 mb-3 py-3" onclick="finalizarVenda()">
                                        <i class="fas fa-check me-2"></i>Lançar Pedido
                                    </button>
                                    <button class="btn btn-outline-danger w-100" onclick="limparCarrinho()">
                                        <i class="fas fa-trash me-2"></i>Limpar Carrinho
                                    </button>
                                    <div id="alertVenda" class="alert mt-3" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ===== PEDIDOS AGUARDANDO PAGAMENTO ===== -->
                    <?php if ($caixa_aberto): ?>
                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, var(--warning), #d97706); color: white; border-radius: 16px 16px 0 0; padding: 18px 24px;">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pedidos Aguardando Pagamento
                                    <span class="badge ms-2" style="background: rgba(255,255,255,0.25);"><?php echo count($pedidos_aguardando); ?></span>
                                </h5>
                                <?php if ($temPedidosOnline): ?>
                                    <a href="pedidos.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4);">Ver todos os pedidos</a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($pedidos_aguardando)): ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="fas fa-check-circle fa-2x mb-2 d-block" style="color: var(--success);"></i>
                                        Nenhum pedido aguardando pagamento
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table mb-0">
                                            <thead style="background: #fef9c3;">
                                                <tr>
                                                    <th class="ps-4">Pedido</th>
                                                    <th>Mesa</th>
                                                    <th>Cliente</th>
                                                    <th>Total</th>
                                                    <th>Horário</th>
                                                    <th style="min-width:300px;">Receber</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pedidos_aguardando as $pa): ?>
                                                    <tr id="pedido_row_<?php echo $pa['id']; ?>">
                                                        <td class="ps-4 fw-bold">#<?php echo htmlspecialchars($pa['numero_pedido'] ?? $pa['id']); ?></td>
                                                        <!-- Se mesa_numero estiver vazio, é pedido do Balcão. Adiciona classe para destaque visual. -->
                                                        <td class="<?php echo empty($pa['mesa_numero']) ? 'balcao-label' : ''; ?>">
                                                            <?php echo $pa['mesa_numero'] ? 'Mesa ' . htmlspecialchars($pa['mesa_numero']) : '<span class="text-muted">Balcão</span>'; ?>
                                                        </td>
                                                        <td><?php echo $pa['cliente_nome'] ? htmlspecialchars($pa['cliente_nome']) : '<span class="text-muted">—</span>'; ?></td>
                                                        <td class="fw-bold" style="color: var(--primary);"><?php echo number_format($pa['total'], 2, ',', '.'); ?> MZN</td>
                                                        <td class="text-muted small"><?php echo date('H:i', strtotime($pa['criado_em'])); ?></td>
                                                        <td>
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <select id="forma_pag_<?php echo $pa['id']; ?>" class="form-select form-select-sm" style="width:150px;">
                                                                    <option value="DINHEIRO">💵 Dinheiro</option>
                                                                    <option value="MPESA">📱 M-Pesa</option>
                                                                    <option value="CARTAO">💳 Cartão</option>
                                                                    <option value="TRANSFERENCIA">🏦 Transferência</option>
                                                                </select>
                                                                <button id="btn_pagar_<?php echo $pa['id']; ?>"
                                                                    class="btn btn-success btn-sm"
                                                                    onclick="receberPagamentoPedido(<?php echo $pa['id']; ?>)">
                                                                    <i class="fas fa-check me-1"></i>Receber
                                                                </button>
                                                                <a href="comprovante.php?pedido_id=<?php echo $pa['id']; ?>" class="btn btn-outline-secondary btn-sm" target="_blank" title="Ver itens">
                                                                    <i class="fas fa-list"></i>
                                                                </a>
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
                    <?php endif; ?>

                    <!-- ===== HISTÓRICO DE VENDAS DE HOJE ===== -->
                    <div class="card mt-4 mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, var(--dark), var(--dark-2)); color: white; border-radius: 16px 16px 0 0; padding: 18px 24px;">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Vendas de Hoje
                                <span class="badge ms-2" style="background: rgba(255,255,255,0.15);"><?php echo count($vendas_hoje); ?></span>
                            </h5>
                            <span style="font-size: 18px; font-weight: 700; color: #10b981;">
                                Total: <?php echo number_format($total_hoje, 2, ',', '.'); ?> MZN
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($vendas_hoje)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-2x mb-2 d-block"></i>
                                    Nenhuma venda registrada hoje
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead style="background: #f8fafc;">
                                            <tr>
                                                <th class="ps-4">Fatura</th>
                                                <th>Mesa</th>
                                                <th>Operador</th>
                                                <th>Pagamento</th>
                                                <th>Total</th>
                                                <th>Hora</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $forma_icons = ['DINHEIRO' => '💵', 'MPESA' => '📱', 'CARTAO' => '💳', 'TRANSFERENCIA' => '🏦'];
                                            foreach ($vendas_hoje as $vh):
                                                $icone = $forma_icons[$vh['forma_pagamento']] ?? '💰';
                                            ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($vh['numero_fatura']); ?></td>
                                                    <!-- Destaca pedidos do Balcão no histórico -->
                                                    <td class="<?php echo empty($vh['mesa_numero']) ? 'balcao-label' : ''; ?>">
                                                        <?php echo $vh['mesa_numero'] ? 'Mesa ' . htmlspecialchars($vh['mesa_numero']) : '<span class="text-muted">Balcão</span>'; ?>
                                                    </td>
                                                    <td class="text-muted small"><?php echo htmlspecialchars($vh['usuario_nome'] ?? '—'); ?></td>
                                                    <td><?php echo $icone . ' ' . htmlspecialchars($vh['forma_pagamento']); ?></td>
                                                    <td class="fw-bold" style="color: var(--success);"><?php echo number_format($vh['total_final'], 2, ',', '.'); ?> MZN</td>
                                                    <td class="text-muted small"><?php echo date('H:i', strtotime($vh['criado_em'])); ?></td>
                                                    <td>
                                                        <a href="comprovante.php?id=<?php echo $vh['id']; ?>" class="btn btn-outline-secondary btn-sm" target="_blank" title="Comprovante">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot style="background: #f0fdf4;">
                                            <tr>
                                                <td colspan="4" class="ps-4 fw-bold">Total do dia</td>
                                                <td class="fw-bold" style="color: var(--success); font-size: 16px;"><?php echo number_format($total_hoje, 2, ',', '.'); ?> MZN</td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/pdv.js"></script>
</body>

</html>

