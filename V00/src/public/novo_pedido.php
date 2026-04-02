<?php

/*
   Tela Garçom – Novo Pedido (Fluxo sem QR Code)
  Garçom seleciona mesa, adiciona itens e envia para a cozinha.
*/
include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/plano_check.php';
include_once __DIR__ . '/../config/restaurante_context.php';
include_once __DIR__ . '/../Model/Mesa.php';

requirePermissionOrRedirect(['ADMIN', 'GARCOM']);

$database  = new Database();
$db        = $database->getConnection();
$mesa_obj  = new Mesa($db);
$rid       = session_restaurante_contexto_id();
$restauranteFeatureId = session_restaurante_capability_id();
$restauranteFeatureId = $restauranteFeatureId > 0 ? $restauranteFeatureId : $rid;
$temPedidosOnline = $restauranteFeatureId > 0 && plano_tem_funcionalidade_db($restauranteFeatureId, 'pedidos_online');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443)
    ? 'https://'
    : 'http://';
$base_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '\\/') . '/';

if ($rid <= 0) {
    header('Location: index.php');
    exit;
}

$stmt_mesas = $mesa_obj->listar($rid);
$mesas      = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

$stmt_produtos = $db->prepare(
    "SELECT p.id, p.nome, p.preco, p.imagem, c.nome AS categoria
     FROM produtos p
     LEFT JOIN categorias c ON p.categoria_id = c.id
     WHERE p.restaurante_id = :rid AND p.ativo = 1
     ORDER BY c.nome, p.nome"
);
$stmt_produtos->execute(['rid' => $rid]);
$produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por categoria
$por_categoria = [];
foreach ($produtos as $prod) {
    $cat = $prod['categoria'] ?? 'Geral';
    $por_categoria[$cat][] = $prod;
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Pedido – Garçom</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #FF6B35;
            --primary-dark: #e55a2b;
            --bg: #f8fafc;
            --text: #1e293b;
            --border: #e2e8f0;
            --card-bg: #ffffff;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .topbar {
            background: #fff;
            padding: 14px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow);
        }

        .topbar h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar h1 i {
            color: var(--primary);
        }

        .content {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 20px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        @media(max-width:900px) {
            .content {
                grid-template-columns: 1fr;
            }
        }

        /* Produtos */
        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin: 16px 0 8px;
            padding: 0 4px;
        }

        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
        }

        .produto-btn {
            background: var(--card-bg);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 10px 10px 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .produto-btn:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.12);
            transform: translateY(-1px);
        }

        .produto-btn .p-img-wrap {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 10px;
            overflow: hidden;
            background: #f1f5f9;
            margin-bottom: 8px;
        }

        .produto-btn .p-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .produto-btn .p-nome {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .produto-btn .p-preco {
            color: var(--primary);
            font-weight: 700;
            font-size: 13px;
        }

        /* Carrinho */
        .cart-panel {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 80px;
            max-height: calc(100vh - 100px);
        }

        .cart-panel .cart-header {
            padding: 18px 20px 12px;
            border-bottom: 1px solid var(--border);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            font-weight: 700;
        }

        .cart-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px 16px;
        }

        .mesa-select {
            margin-bottom: 14px;
        }

        .cart-empty {
            text-align: center;
            color: #94a3b8;
            padding: 30px 10px;
            font-size: 13px;
        }

        .cart-empty i {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item .ci-nome {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }

        .cart-item .ci-preco {
            font-size: 13px;
            color: #64748b;
        }

        .qty-ctrl {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .qty-btn {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 1.5px solid var(--border);
            background: #f1f5f9;
            cursor: pointer;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }

        .qty-btn:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .qty-num {
            font-weight: 700;
            font-size: 14px;
            min-width: 20px;
            text-align: center;
        }

        .cart-footer {
            padding: 14px 16px;
            border-top: 1px solid var(--border);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 17px;
            margin-bottom: 12px;
        }

        .total-row span:last-child {
            color: var(--primary);
        }

        .obs-field {
            margin-bottom: 12px;
        }

        .obs-field label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .obs-field textarea {
            font-size: 13px;
            resize: none;
            border-radius: 10px;
        }

        .btn-enviar {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-enviar:hover:not(:disabled) {
            background: var(--primary-dark);
        }

        .btn-enviar:disabled {
            opacity: 0.6;
            cursor: default;
        }

        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* Responsividade para possível menu lateral futuro */
        .sidebar.sidebar-hidden {
            left: -100vw !important;
            transition: left 0.3s cubic-bezier(.4, 0, .2, 1);
        }

        @media (max-width: 768px) {
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
        }
    </style>
</head>

<body>
    <!-- Botão de alternância do menu lateral para mobile (padrão visual) -->

    <div class="topbar">
        <h1><i class="fas fa-clipboard-list"></i> Novo Pedido</h1>
        <div class="d-flex gap-2">
            <a href="cozinha.php" class="btn btn-sm btn-outline-warning"><i class="fas fa-fire me-1"></i> Cozinha</a>
            <?php if (strtoupper(trim((string)($_SESSION['perfil'] ?? ''))) === 'ADMIN'): ?>
                <a href="bar.php" class="btn btn-sm btn-outline-info"><i class="fas fa-martini-glass-citrus me-1"></i> Bar</a>
            <?php endif; ?>
            <?php if ($temPedidosOnline): ?>
                <a href="pedidos.php" class="btn btn-sm btn-outline-warning"><i class="fas fa-list me-1"></i> Pedidos</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home"></i></a>
        </div>
    </div>

    <!-- Filtros rápidos de status (UX) -->
    <?php if ($temPedidosOnline): ?>
        <div class="container mt-3 mb-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-bold me-2">Filtrar pedidos:</span>
                <button class="btn btn-sm btn-outline-primary" onclick="filtrarPedidos('todos')">Todos</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="filtrarPedidos('NOVO')">Novos</button>
                <button class="btn btn-sm btn-outline-warning" onclick="filtrarPedidos('PREPARANDO')">Preparando</button>
                <button class="btn btn-sm btn-outline-success" onclick="filtrarPedidos('PRONTO')">Prontos</button>
                <button class="btn btn-sm btn-outline-dark" onclick="filtrarPedidos('ENTREGUE')">Entregues</button>
            </div>
        </div>
    <?php endif; ?>
    <!--
        =============================
        SUGESTÕES DE MELHORIA (UX)
        =============================
        - Cardápio QR Code:
            * Exibir modal de sucesso detalhado após pedido, com número do pedido e instruções.
            * Adicionar animação/confete para reforçar experiência positiva.
            * Permitir acompanhamento do status do pedido (polling/SSE).
            * Melhorar acessibilidade: botões maiores, contraste, textos alternativos.
        - Painel do Garçom:
            * Filtros rápidos por status (já implementado aqui).
            * Atualização automática dos pedidos (WebSocket/polling).
            * Alertas visuais/sonoros para novos pedidos.
            * Visualização detalhada do pedido em modal, com opção de marcar como entregue/pronto.
            * Melhorar responsividade para tablets/celulares.
        - Comentários em português para facilitar manutenção.
        - Para implementar, siga os exemplos de estrutura já deixados neste arquivo.
    -->
    <!-- Bootstrap 5 JS (necessário para Modal funcionar) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Melhoria UX: Filtro de pedidos por status (estrutura inicial, depende do painel de pedidos) ---
        function filtrarPedidos(status) {
            <?php if (!$temPedidosOnline): ?>
            return;
            <?php endif; ?>
            // Exemplo: redireciona para pedidos.php com filtro (ajuste conforme integração real)
            window.location.href = 'pedidos.php' + (status && status !== 'todos' ? ('?status=' + encodeURIComponent(status)) : '');
        }

        // --- Melhoria UX: Atualização automática dos pedidos (estrutura para painel de pedidos) ---
        // Comentário: No painel de pedidos, implemente polling ou WebSocket para atualizar a lista em tempo real.
        // Exemplo de polling (AJAX):
        /*
        setInterval(() => {
            fetch('api/pedidos_snapshot.php')
                .then(r => r.json())
                .then(data => {
                    // Atualize a UI dos pedidos conforme necessário
                    // Exemplo: highlightPedidosNovos(data.novos);
                });
        }, 5000);
        */

        // --- Melhoria UX: Alerta visual para novos pedidos (estrutura) ---
        // Exemplo: adicionar badge ou animação quando houver novos pedidos
        function highlightPedidosNovos(qtd) {
            <?php if (!$temPedidosOnline): ?>
            return;
            <?php endif; ?>
            if (qtd > 0) {
                // Exemplo: adicionar badge na aba de pedidos
                const btn = document.querySelector('a[href="pedidos.php"]');
                if (btn && !btn.querySelector('.badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-danger ms-1';
                    badge.textContent = qtd;
                    btn.appendChild(badge);
                }
            }
        }
    </script>

    <div class="content">
        <!-- Lista de Produtos -->
        <div id="produtos-area">
            <?php if (empty($produtos)): ?>
                <div class="alert alert-warning mt-3">Nenhum produto cadastrado ou ativo.</div>
            <?php else: ?>
                <?php foreach ($por_categoria as $cat => $prods): ?>
                    <div class="section-title"><?php echo htmlspecialchars($cat, ENT_QUOTES); ?></div>
                    <div class="produtos-grid">
                        <?php foreach ($prods as $p): ?>
                            <?php
                            $caminho_imagem = trim((string)($p['imagem'] ?? ''));
                            if ($caminho_imagem !== '') {
                                $caminho_imagem = str_replace('src/public/', '', $caminho_imagem);
                            }

                            if ($caminho_imagem !== '' && preg_match('/^https?:\/\//i', $caminho_imagem)) {
                                $imgSrc = $caminho_imagem;
                            } elseif ($caminho_imagem !== '') {
                                $caminho_rel = ltrim($caminho_imagem, '/');
                                if (file_exists(__DIR__ . '/' . $caminho_rel)) {
                                    $imgSrc = $base_url . $caminho_rel;
                                } else {
                                    $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($p['nome']) . '&background=FF6B35&color=ffffff&size=256&bold=true';
                                }
                            } else {
                                $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($p['nome']) . '&background=FF6B35&color=ffffff&size=256&bold=true';
                            }
                            ?>
                            <div class="produto-btn" onclick="addItem(<?php echo (int)$p['id']; ?>, <?php echo htmlspecialchars(json_encode($p['nome']), ENT_QUOTES); ?>, <?php echo (float)$p['preco']; ?>)">
                                <div class="p-img-wrap">
                                    <img class="p-img" src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($p['nome']); ?>&background=FF6B35&color=ffffff&size=256&bold=true';">
                                </div>
                                <div class="p-nome"><?php echo htmlspecialchars($p['nome'], ENT_QUOTES); ?></div>
                                <div class="p-preco">MZN <?php echo number_format((float)$p['preco'], 2, ',', '.'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Painel do Carrinho -->
        <div class="cart-panel">
            <div class="cart-header">
                <i class="fas fa-shopping-cart me-2" style="color:var(--primary)"></i>Pedido
            </div>
            <div class="cart-body">
                <!-- Seletor de Mesa -->
                <div class="mesa-select">
                    <label class="form-label fw-bold" style="font-size:13px">Mesa</label>
                    <select id="sel-mesa" class="form-select form-select-sm" required>
                        <option value="">Selecione a mesa…</option>
                        <?php foreach ($mesas as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"
                                <?php echo ($m['status'] === 'OCUPADA') ? 'style="color:#f59e0b"' : ''; ?>>
                                Mesa <?php echo htmlspecialchars($m['numero'], ENT_QUOTES); ?>
                                <?php echo ($m['status'] === 'OCUPADA') ? ' ⚠ Ocupada' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Itens -->
                <div id="cart-items">
                    <div class="cart-empty">
                        <i class="fas fa-utensils"></i>
                        Clique nos produtos para adicionar
                    </div>
                </div>
            </div>
            <div class="cart-footer">
                <div class="total-row">
                    <span>Total</span>
                    <span id="cart-total">MZN 0,00</span>
                </div>
                <div class="obs-field">
                    <label for="obs-pedido">Observação (opcional)</label>
                    <textarea id="obs-pedido" class="form-control mt-1" rows="2" maxlength="300"
                        placeholder="Ex: sem cebola, ponto da carne…"></textarea>
                </div>
                <button class="btn-enviar" id="btn-enviar" onclick="enviarPedido()" disabled>
                    <i class="fas fa-paper-plane me-2"></i> Enviar Pedido
                </button>
            </div>
        </div>
    </div>



    <!-- Modal de Sucesso do Pedido -->
    <div class="modal fade" id="modalPedidoSucesso" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4 position-relative">
                <!-- Confete animado -->
                <div id="confetti-container" style="pointer-events:none;position:absolute;top:0;left:0;width:100%;height:100%;z-index:10;"></div>
                <div class="mb-3" style="z-index:20;position:relative;">
                    <i class="fas fa-check-circle fa-3x text-success"></i>
                </div>
                <h3 class="mb-2">Pedido enviado!</h3>
                <div id="pedidoNumeroSucesso" class="fw-bold fs-4 mb-2"></div>
                <p class="text-muted mb-3" id="pedidoDestinoSucesso">Seu pedido foi enviado para a cozinha.<br>Em instantes ele aparecerá no painel de pedidos.</p>
                <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toasts"></div>

    <script>
        const BOTAO_ENVIAR_PEDIDO_HTML = '<i class="fas fa-paper-plane me-2"></i> Enviar Pedido';

        const cart = {}; // {id: {id, nome, preco, qtd}}

        function montarMensagemDestino(destinoResumo) {
            if (destinoResumo === 'bar') {
                return 'Seu pedido foi enviado para o bar.<br>Em instantes ele aparecerá no painel do bar.';
            }

            if (destinoResumo === 'cozinha_bar') {
                return 'Seu pedido foi enviado para a cozinha e ao bar.<br>Em instantes ele aparecerá nos painéis de produção.';
            }

            return 'Seu pedido foi enviado para a cozinha.<br>Em instantes ele aparecerá no painel de pedidos.';
        }

        function addItem(id, nome, preco) {
            if (!cart[id]) {
                cart[id] = {
                    id,
                    nome,
                    preco,
                    qtd: 0
                };
            }
            cart[id].qtd++;
            renderCart();
        }

        function changeQty(id, delta) {
            if (!cart[id]) return;
            cart[id].qtd += delta;
            if (cart[id].qtd <= 0) delete cart[id];
            renderCart();
        }

        function renderCart() {
            const el = document.getElementById('cart-items');
            const ids = Object.keys(cart);
            if (!ids.length) {
                el.innerHTML = '<div class="cart-empty"><i class="fas fa-utensils"></i>Clique nos produtos para adicionar</div>';
                document.getElementById('cart-total').textContent = 'R$ 0,00';
                document.getElementById('btn-enviar').disabled = true;
                return;
            }

            let html = '',
                total = 0;
            ids.forEach(id => {
                const it = cart[id];
                const sub = it.preco * it.qtd;
                total += sub;
                html += `<div class="cart-item">
            <div class="qty-ctrl">
                <button class="qty-btn" onclick="changeQty(${id},-1)">−</button>
                <span class="qty-num">${it.qtd}</span>
                <button class="qty-btn" onclick="changeQty(${id},1)">+</button>
            </div>
            <div class="ci-nome">${escHtml(it.nome)}</div>
            <div class="ci-preco">R$ ${fmt(sub)}</div>
        </div>`;
            });

            el.innerHTML = html;
            document.getElementById('cart-total').textContent = 'R$ ' + fmt(total);

            const mesaOk = document.getElementById('sel-mesa').value !== '';
            document.getElementById('btn-enviar').disabled = !mesaOk;
        }

        document.getElementById('sel-mesa').addEventListener('change', () => {
            const hasItems = Object.keys(cart).length > 0;
            document.getElementById('btn-enviar').disabled = !hasItems || !document.getElementById('sel-mesa').value;
        });

        // Preload mesa_id from URL
        const urlParams = new URLSearchParams(window.location.search);
        const mesaId = urlParams.get('mesa_id');
        if (mesaId) {
            document.getElementById('sel-mesa').value = mesaId;
            renderCart(); // Re-evaluate button state
        }

        function fmt(n) {
            return Number(n).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function escHtml(str) {
            return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }


        // Função simples de confete (efeito visual ao mostrar modal de sucesso)
        function confetti() {
            const colors = ['#FF6B35', '#F7931E', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'];
            const container = document.getElementById('confetti-container');
            if (!container) return;
            container.innerHTML = '';
            for (let i = 0; i < 32; i++) {
                const el = document.createElement('div');
                el.style.position = 'absolute';
                el.style.left = Math.random() * 100 + '%';
                el.style.top = '-20px';
                el.style.width = '12px';
                el.style.height = '12px';
                el.style.borderRadius = '50%';
                el.style.background = colors[Math.floor(Math.random() * colors.length)];
                el.style.opacity = 0.85;
                el.style.transform = `scale(${0.7 + Math.random()*0.7})`;
                el.style.transition = 'top 1.2s cubic-bezier(.4,1.4,.6,1), opacity 1.2s';
                container.appendChild(el);
                setTimeout(() => {
                    el.style.top = (60 + Math.random() * 30) + '%';
                    el.style.opacity = 0;
                }, 30 + Math.random() * 100);
            }
            setTimeout(() => {
                container.innerHTML = '';
            }, 1400);
        }

        async function enviarPedido() {
            // Garantir que mesaId seja inteiro (evita erro de 'Mesa inválida' no backend)
            const mesaIdRaw = document.getElementById('sel-mesa').value;
            const mesaId = parseInt(mesaIdRaw, 10) || 0;
            if (!mesaId) {
                showToast('Selecione uma mesa!', 'warning');
                return;
            }
            const ids = Object.keys(cart);
            if (!ids.length) {
                showToast('Carrinho vazio!', 'warning');
                return;
            }

            const itens = ids.map(id => ({
                id: cart[id].id,
                nome: cart[id].nome,
                preco: cart[id].preco,
                qtd: cart[id].qtd
            }));
            const obs = document.getElementById('obs-pedido').value.trim();

            const btn = document.getElementById('btn-enviar');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Enviando…';

            try {
                const fd = new FormData();
                fd.append('mesa_id', mesaId); // sempre inteiro
                fd.append('itens', JSON.stringify(itens));
                if (obs) fd.append('observacao', obs);

                const resp = await fetch('api/pedido_novo.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await resp.json();

                if (data.success) {
                    // Modal de sucesso profissional
                    document.getElementById('pedidoNumeroSucesso').textContent = `#${data.numero_pedido}`;
                    document.getElementById('pedidoDestinoSucesso').innerHTML = montarMensagemDestino(String(data.destino_resumo || 'cozinha'));
                    var modal = new bootstrap.Modal(document.getElementById('modalPedidoSucesso'));
                    modal.show();
                    confetti(); // Efeito visual
                    // Limpar carrinho e campos
                    Object.keys(cart).forEach(k => delete cart[k]);
                    document.getElementById('obs-pedido').value = '';
                    document.getElementById('sel-mesa').value = '';
                    renderCart();
                } else {
                    showToast(data.message || 'Erro ao enviar pedido.', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = BOTAO_ENVIAR_PEDIDO_HTML;
                }
            } catch (e) {
                showToast('Erro de rede: ' + e.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = BOTAO_ENVIAR_PEDIDO_HTML;
            }
        }

        function showToast(msg, type = 'success') {
            const id = 'toast_' + Date.now();
            const colors = {
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444'
            };
            const div = document.createElement('div');
            div.id = id;
            div.style.cssText = `background:${colors[type]||'#333'};color:#fff;padding:12px 18px;border-radius:12px;
        margin-top:8px;font-weight:600;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.2);
        animation:fadeIn 0.3s ease;`;
            div.innerHTML = `<i class="fas fa-${type==='success'?'check':'exclamation-triangle'} me-2"></i>${msg}`;
            document.getElementById('toasts').appendChild(div);
            setTimeout(() => div.remove(), 4000);
        }
    </script>
</body>

</html>

