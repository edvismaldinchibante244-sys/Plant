<?php

/*
   Caixa – Vista de Mesas com Contas Abertas
   Interface simplificada para fechar contas rapidamente.
   Garante uma ação por mesa (máximo 2 cliques para pagar).
 */
include_once __DIR__ . '/../config/auth_check.php';

$perfil = strtoupper(trim($_SESSION['perfil'] ?? ''));
if (!in_array($perfil, ['CAIXA', 'ADMIN'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Caixa – Contas Abertas</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #f8fafc;
            --card: #fff;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
            --primary: #FF6B35;
            --success: #10b981;
            --warn: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
        }

        /* TOPBAR */
        .topbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 200;
        }

        .topbar h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar h1 i {
            color: var(--primary);
        }

        /* SUMMARY BAR */
        .summary-bar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .sum-item {
            display: flex;
            flex-direction: column;
        }

        .sum-item .label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sum-item .value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
        }

        .sum-item .value.green {
            color: var(--success);
        }

        /* GRID DE MESAS OCUPADAS */
        .mesas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 18px;
            padding: 24px;
        }

        .mesa-card {
            background: var(--card);
            border-radius: 20px;
            border: 2px solid var(--border);
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .mesa-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .mesa-card.urgente {
            border-color: var(--danger);
        }

        .mesa-card.warn {
            border-color: var(--warn);
        }

        .card-header-mesa {
            padding: 16px 20px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }

        .mesa-num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            font-weight: 700;
        }

        .mesa-num span {
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
        }

        .time-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .time-badge.normal {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .time-badge.warn {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warn);
        }

        .time-badge.urgente {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .card-body-mesa {
            padding: 16px 20px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-row .nome {
            flex: 1;
            color: var(--text);
        }

        .item-row .qtd {
            color: var(--muted);
            margin: 0 12px;
        }

        .item-row .preco {
            font-weight: 600;
        }

        .card-total {
            padding: 14px 20px;
            border-top: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 18px;
        }

        .card-total .garcom {
            font-size: 12px;
            color: var(--muted);
            font-weight: 400;
        }

        .card-actions {
            padding: 0 20px 18px;
            display: flex;
            gap: 10px;
        }

        .btn-pagar {
            flex: 1;
            padding: 12px 0;
            border-radius: 12px;
            background: var(--success);
            color: white;
            border: none;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-pagar:hover {
            opacity: 0.9;
        }

        .btn-detalhe {
            padding: 12px 16px;
            border-radius: 12px;
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--muted);
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
            color: #cbd5e1;
        }

        /* MODAL PAGAMENTO */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header-pay {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 22px 26px;
        }

        .pay-option-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 16px 0;
        }

        .pay-option {
            padding: 14px;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.18s;
        }

        .pay-option:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .pay-option.selected {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.08);
            color: var(--success);
        }

        .pay-option i {
            display: block;
            font-size: 22px;
            margin-bottom: 6px;
        }

        /* STATUS FOOTER */
        #footer-status {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid var(--border);
            padding: 8px 20px;
            font-size: 12px;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            z-index: 100;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.show {
            display: flex;
        }

        @media (max-width: 600px) {
            .mesas-grid {
                grid-template-columns: 1fr;
                padding: 16px;
            }
        }
    </style>
</head>


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

<body>
    <!-- TOPBAR -->
    <div class="topbar">
        <h1><i class="fas fa-cash-register"></i> Contas Abertas</h1>
        <div style="display:flex;gap:10px;align-items:center">
            <span id="clock" style="color:var(--muted);font-size:14px"></span>
            <a href="caixa.php" class="btn btn-sm" style="background:var(--bg);border:1px solid var(--border);border-radius:10px;color:var(--text)">
                <i class="fas fa-money-bill-wave me-1"></i>Caixa
            </a>
            <a href="dashboard.php" class="btn btn-sm" style="background:var(--primary);color:white;border:none;border-radius:10px">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>

    <!-- SUMMARY BAR -->
    <div class="summary-bar">
        <div class="sum-item">
            <span class="label">Mesas Abertas</span>
            <span class="value" id="s-abertas">–</span>
        </div>
        <div class="sum-item">
            <span class="label">Total em Aberto</span>
            <span class="value green" id="s-total">–</span>
        </div>
        <div class="sum-item">
            <span class="label">Atualizado</span>
            <span style="font-size:13px;color:var(--muted);margin-top:4px" id="s-hora">–</span>
        </div>
    </div>

    <!-- GRID -->
    <div class="mesas-grid" id="mesas-grid">
        <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--muted)">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-3">Carregando contas…</p>
        </div>
    </div>

    <!-- FOOTER -->
    <div id="footer-status">
        <span id="footer-msg">–</span>
        <span><i class="fas fa-sync-alt me-1" id="refresh-spin" style="display:none"></i>Atualiza a cada 10s</span>
    </div>

    <!-- LOADING OVERLAY -->
    <div class="loading-overlay" id="loading-overlay">
        <div style="background:white;border-radius:16px;padding:32px 40px;text-align:center">
            <i class="fas fa-spinner fa-spin fa-3x" style="color:var(--success)"></i>
            <p class="mt-3" style="font-weight:600">Processando pagamento…</p>
        </div>
    </div>

    <!-- MODAL PAGAMENTO -->
    <div class="modal fade" id="modalPagamento" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header-pay">
                    <h5 style="margin:0;font-weight:700"><i class="fas fa-check-circle me-2"></i>Fechar Conta</h5>
                    <p style="margin:6px 0 0;opacity:0.85;font-size:14px" id="pay-sub">Mesa – · Total: –</p>
                </div>
                <div class="modal-body" style="padding:22px">
                    <p style="font-weight:600;margin-bottom:12px">Forma de Pagamento</p>
                    <div class="pay-option-grid" id="pay-options">
                        <div class="pay-option" data-pagamento="DINHEIRO" onclick="selectPay(this)">
                            <i class="fas fa-money-bill-wave"></i>Dinheiro
                        </div>
                        <div class="pay-option" data-pagamento="MPESA" onclick="selectPay(this)">
                            <i class="fas fa-mobile-alt"></i>M-Pesa
                        </div>
                        <div class="pay-option" data-pagamento="CARTAO" onclick="selectPay(this)">
                            <i class="fas fa-credit-card"></i>Cartão
                        </div>
                        <div class="pay-option" data-pagamento="TRANSFERENCIA" onclick="selectPay(this)">
                            <i class="fas fa-exchange-alt"></i>Transferência
                        </div>
                    </div>
                    <div style="margin-top:12px">
                        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Desconto (%)</label>
                        <input type="number" id="pay-desconto" min="0" max="100" step="0.01" value="0" placeholder="Ex.: 10 para 10%"
                            style="width:100%;padding:10px 14px;border-radius:10px;border:2px solid var(--border);font-size:14px">
                    </div>
                </div>
                <div class="modal-footer" style="gap:10px;padding:16px 22px">
                    <button class="btn" style="border:1px solid var(--border);border-radius:10px;flex:1"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn" id="btn-confirmar-pag"
                        onclick="confirmarPagamento()"
                        style="background:var(--success);color:white;border:none;border-radius:10px;flex:2;font-weight:700">
                        <i class="fas fa-check me-1"></i>Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ─── Utilitários ──────────────────────────────────────────────────────────────
        function esc(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function fmt(v) {
            return parseFloat(v || 0).toLocaleString('pt-MZ', {
                minimumFractionDigits: 2
            });
        }

        function timerClass(mins) {
            return mins >= 30 ? 'urgente' : mins >= 15 ? 'warn' : 'normal';
        }

        function timerText(mins) {
            if (mins < 1) return 'Agora';
            if (mins < 60) return mins + ' min';
            return Math.floor(mins / 60) + 'h ' + (mins % 60) + 'min';
        }

        async function lerJsonSeguro(resp) {
            const raw = await resp.text();
            try {
                return JSON.parse(raw);
            } catch (e) {
                const preview = raw.slice(0, 160).replace(/\s+/g, ' ').trim();
                throw new Error('Resposta inválida da API: ' + (preview || '[vazia]'));
            }
        }

        function updateClock() {
            document.getElementById('clock').textContent =
                new Date().toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ─── Render ───────────────────────────────────────────────────────────────────
        function renderMesa(m) {
            const tc = timerClass(m.minutos_aberto);
            const tt = timerText(m.minutos_aberto);
            const dataStr = JSON.stringify(m).replace(/"/g, '&quot;');

            let itensHtml = m.itens.map(it => `
        <div class="item-row">
            <span class="nome">${esc(it.produto)}</span>
            <span class="qtd">${esc(it.quantidade)}×</span>
            <span class="preco">MZN ${fmt(parseFloat(it.preco_unitario)*parseFloat(it.quantidade))}</span>
        </div>`).join('');

            const statusBadge = (() => {
                const s = m.pedido_status;
                if (s === 'PRONTO') return '<span style="color:#3b82f6"><i class="fas fa-check-circle me-1"></i>Pronto</span>';
                if (s === 'PREPARANDO') return '<span style="color:#f59e0b"><i class="fas fa-fire me-1"></i>Preparando</span>';
                if (s === 'ENTREGUE') return '<span style="color:#8b5cf6"><i class="fas fa-hand-holding me-1"></i>Entregue</span>';
                return '<span style="color:#94a3b8"><i class="fas fa-clock me-1"></i>Novo</span>';
            })();

            return `
    <div class="mesa-card ${tc}">
        <div class="card-header-mesa">
            <div class="mesa-num">Mesa ${esc(m.mesa_numero)} <br><span>${statusBadge}</span></div>
            <span class="time-badge ${tc}"><i class="fas fa-clock me-1"></i>${tt}</span>
        </div>
        <div class="card-body-mesa">${itensHtml}</div>
        <div class="card-total">
            <div>
                <div>Total</div>
                ${m.garcom_nome ? `<div class="garcom"><i class="fas fa-user me-1"></i>${esc(m.garcom_nome)}</div>` : ''}
            </div>
            <div>MZN ${fmt(m.pedido_total)}</div>
        </div>
        <div class="card-actions">
            <button class="btn-pagar" onclick="abrirPagamento(${m.pedido_id},${m.mesa_numero},${m.pedido_total})">
                <i class="fas fa-check-circle me-2"></i>Receber Pagamento
            </button>
        </div>
    </div>`;
        }

        // ─── Carregar mesas abertas ───────────────────────────────────────────────────
        async function carregarMesas() {
            document.getElementById('refresh-spin').style.display = 'inline-block';
            try {
                const resp = await fetch('api/caixa_mesas_abertas.php');
                const data = await lerJsonSeguro(resp);
                if (!data.success) throw new Error(data.message || 'Erro');

                const grid = document.getElementById('mesas-grid');
                if (data.mesas.length === 0) {
                    grid.innerHTML = `<div class="empty-state">
                <i class="fas fa-coffee"></i>
                <h5>Nenhuma conta aberta</h5>
                <p>Todas as mesas estão livres ou pagas.</p>
            </div>`;
                } else {
                    grid.innerHTML = data.mesas.map(renderMesa).join('');
                }

                document.getElementById('s-abertas').textContent = data.qtd_abertas;
                document.getElementById('s-total').textContent = 'MZN ' + fmt(data.total_aberto);
                document.getElementById('s-hora').textContent = new Date().toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                document.getElementById('footer-msg').textContent = 'Última actualização: ' + new Date().toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } catch (e) {
                document.getElementById('footer-msg').textContent = 'Erro: ' + e.message;
            } finally {
                document.getElementById('refresh-spin').style.display = 'none';
            }
        }

        carregarMesas();
        setInterval(carregarMesas, 10000);

        // ─── Pagamento ────────────────────────────────────────────────────────────────
        let payPedidoId = null;
        let payForma = 'DINHEIRO';

        function abrirPagamento(pedidoId, mesaNum, total) {
            payPedidoId = pedidoId;
            payForma = 'DINHEIRO';
            document.getElementById('pay-sub').textContent = 'Mesa ' + mesaNum + ' · Total: MZN ' + fmt(total);
            // Reset seleção
            document.querySelectorAll('.pay-option').forEach(el => el.classList.remove('selected'));
            document.querySelector('[data-pagamento="DINHEIRO"]').classList.add('selected');
            document.getElementById('pay-desconto').value = '0';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPagamento')).show();
        }

        function selectPay(el) {
            document.querySelectorAll('.pay-option').forEach(x => x.classList.remove('selected'));
            el.classList.add('selected');
            payForma = el.dataset.pagamento;
        }

        async function confirmarPagamento() {
            if (!payPedidoId) return;
            const descontoPercent = parseFloat(document.getElementById('pay-desconto').value || 0);
            if (Number.isNaN(descontoPercent) || descontoPercent < 0 || descontoPercent > 100) {
                alert('Desconto deve estar entre 0% e 100%.');
                return;
            }

            // Evita bloqueio do navegador: popup precisa abrir no clique do usuário.
            const reciboPopup = window.open('', '_blank');

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPagamento')).hide();
            document.getElementById('loading-overlay').classList.add('show');

            const form = new FormData();
            form.append('id', payPedidoId);
            form.append('forma_pagamento', payForma);
            form.append('desconto_percent', descontoPercent);

            try {
                const resp = await fetch('api/pedido_pagar.php', {
                    method: 'POST',
                    body: form
                });
                const data = await lerJsonSeguro(resp);
                if (data.success) {
                    carregarMesas();
                    if (data.venda_id) {
                        const reciboUrl = 'comprovante.php?id=' + encodeURIComponent(data.venda_id) + '&auto_print=1';
                        if (reciboPopup && !reciboPopup.closed) {
                            reciboPopup.location.href = reciboUrl;
                            reciboPopup.focus();
                        } else {
                            const popup = window.open(reciboUrl, '_blank');
                            if (!popup) {
                                window.location.href = reciboUrl;
                            }
                        }
                    } else if (reciboPopup && !reciboPopup.closed) {
                        reciboPopup.close();
                    }
                } else {
                    if (reciboPopup && !reciboPopup.closed) {
                        reciboPopup.close();
                    }
                    alert('Erro: ' + (data.message || 'Falha ao processar'));
                }
            } catch (e) {
                if (reciboPopup && !reciboPopup.closed) {
                    reciboPopup.close();
                }
                alert('Erro de rede: ' + e.message);
            } finally {
                document.getElementById('loading-overlay').classList.remove('show');
            }
        }

        function verPedido(id) {
            window.location.href = 'pedidos.php?pedido_id=' + id;
        }
    </script>
</body>

</html>

