<?php

/*
  Tela Principal do Garçom – Grid de Mesas
  UI focada em eficiência: 1 clique para abrir/criar pedido.
  Auto-atualiza a cada 8s via AJAX.
 */
include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/plano_check.php';
include_once __DIR__ . '/../config/restaurante_context.php';

$perfil_raw = strtoupper(trim($_SESSION['perfil'] ?? ''));
if ($perfil_raw === 'GARÇOM') $perfil_raw = 'GARCOM';
if (!in_array($perfil_raw, ['GARCOM', 'ADMIN'])) {
    header('Location: dashboard.php');
    exit;
}

$restaurante_id = session_restaurante_contexto_id();
$restauranteFeatureId = session_restaurante_capability_id();
$restauranteFeatureId = $restauranteFeatureId > 0 ? $restauranteFeatureId : $restaurante_id;
$temPedidosOnline = $restauranteFeatureId > 0 && plano_tem_funcionalidade_db($restauranteFeatureId, 'pedidos_online');
$usuario_nome   = $_SESSION['nome'] ?? 'Garçom';
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Mesas – Garçom</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #0f1117;
            --card: #1c1e27;
            --border: #2a2d3a;
            --livre: #10b981;
            --ocupada: #f59e0b;
            --atrasada: #ef4444;
            --pronto: #3b82f6;
            --destaque: #fbbf24;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --primary: #FF6B35;
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
            min-height: 100vh;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: #111318;
            border-bottom: 1px solid var(--border);
            padding: 14px 20px;
            position: sticky;
            top: 0;
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-left h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }

        .topbar-left h1 i {
            color: var(--primary);
        }

        #clock {
            font-size: 15px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .topbar-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* ── STATS BAR ── */
        .stats-bar {
            display: flex;
            gap: 10px;
            padding: 14px 20px;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--border);
        }

        .stat-chip {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .stat-chip .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-livre {
            background: var(--livre);
        }

        .dot-ocupada {
            background: var(--ocupada);
        }

        .dot-atrasada {
            background: var(--atrasada);
        }

        /* ── GRID DE MESAS ── */
        .mesas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            padding: 20px;
        }

        /* ── CARD DE MESA ── */
        .mesa-card {
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: 18px;
            padding: 20px 16px 16px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.18s, box-shadow 0.18s, border-color 0.2s;
            position: relative;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .mesa-card:active {
            transform: scale(0.95);
        }

        .mesa-card.livre {
            border-color: var(--livre);
        }

        .mesa-card.ocupada {
            border-color: var(--ocupada);
        }

        .mesa-card.atrasada {
            border-color: var(--atrasada);
            animation: pulse-red 1.4s ease-in-out infinite;
        }

        .mesa-card.pronto {
            border-color: var(--pronto);
            animation: pulse-blue 1.4s ease-in-out infinite;
        }

        .mesa-card.minha-mesa {
            border-color: var(--destaque);
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
        }

        @keyframes pulse-red {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
            }
        }

        @keyframes pulse-blue {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(59, 130, 246, 0);
            }
        }

        .mesa-numero {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .mesa-status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .badge-livre {
            background: rgba(16, 185, 129, 0.15);
            color: var(--livre);
        }

        .badge-ocupada {
            background: rgba(245, 158, 11, 0.15);
            color: var(--ocupada);
        }

        .badge-reservada {
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
        }

        .badge-pronto {
            background: rgba(59, 130, 246, 0.15);
            color: var(--pronto);
        }

        .mesa-info {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
        }

        .badge-minha {
            display: inline-block;
            margin-top: 8px;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 10px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            background: rgba(251, 191, 36, 0.15);
            color: var(--destaque);
            font-weight: 700;
        }

        .mesa-info .total {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-top: 4px;
        }

        .mesa-info .timer {
            font-size: 11px;
            margin-top: 2px;
        }

        .mesa-info .timer.warn {
            color: var(--ocupada);
            font-weight: 700;
        }

        .mesa-info .timer.urgent {
            color: var(--atrasada);
            font-weight: 700;
        }

        .mesa-cap {
            position: absolute;
            top: 10px;
            right: 12px;
            font-size: 11px;
            color: var(--muted);
        }

        /* ── FAB ── */
        .fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: var(--primary);
            border: none;
            color: white;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 24px rgba(255, 107, 53, 0.5);
            cursor: pointer;
            z-index: 300;
            transition: transform 0.2s;
        }

        .fab:active {
            transform: scale(0.9);
        }

        /* ── STATUS FOOTER ── */
        #footer-status {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #111318;
            border-top: 1px solid var(--border);
            padding: 7px 20px;
            font-size: 11px;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        #refresh-spinner {
            display: none;
        }

        /* ── MODAL MESA OCUPADA ── */
        .modal-mesa {
            border-radius: 20px;
        }

        .modal-header-ocupada {
            background: linear-gradient(135deg, #1e2130, #252836);
            border-bottom: 1px solid var(--border);
        }

        .item-pedido-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 14px;
        }

        .item-pedido-row:last-child {
            border-bottom: none;
        }

        .item-pedido-row .nome {
            flex: 1;
        }

        .item-pedido-row .qtd {
            color: var(--muted);
            margin: 0 12px;
        }

        .item-pedido-row .preco {
            font-weight: 600;
        }

        .item-meta {
            display: flex;
            gap: 6px;
            margin-top: 4px;
        }

        .item-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .badge-dest-cozinha {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .badge-dest-bar {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
        }

        .badge-st-pendente {
            background: rgba(148, 163, 184, 0.2);
            color: #cbd5e1;
        }

        .badge-st-preparo {
            background: rgba(249, 115, 22, 0.2);
            color: #fb923c;
        }

        .badge-st-pronto {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .badge-st-entregue {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        /* ── NOTIFICAÇÃO TOAST ── */
        #toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast-item {
            background: #1c1e27;
            border-left: 4px solid var(--primary);
            border-radius: 12px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            animation: slide-in 0.3s ease;
            max-width: 320px;
        }

        .toast-item.pronto {
            border-color: var(--pronto);
        }

        .sound-banner {
            position: fixed;
            left: 50%;
            bottom: 68px;
            transform: translateX(-50%);
            z-index: 5000;
            display: none;
            align-items: center;
            gap: 12px;
            background: rgba(15, 23, 42, 0.96);
            color: var(--text);
            border: 1px solid var(--border);
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
            background: var(--primary);
            color: #fff;
        }

        .toast-item.urgente {
            border-color: var(--atrasada);
        }

        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateX(40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (max-width: 500px) {
            .mesas-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 12px;
                padding: 14px;
            }

            .mesa-numero {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <a href="dashboard.php" style="color:var(--muted);font-size:20px;text-decoration:none">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1><i class="fas fa-chair me-2"></i>Mesas</h1>
            <span id="clock"></span>
        </div>
        <div class="topbar-right">
            <?php if ($temPedidosOnline): ?>
                <a href="pedidos.php" class="btn btn-sm" style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:10px">
                    <i class="fas fa-list me-1"></i>Pedidos
                </a>
            <?php endif; ?>
            <a href="novo_pedido.php" class="btn btn-sm" style="background:var(--primary);color:white;border:none;border-radius:10px">
                <i class="fas fa-plus me-1"></i>Novo
            </a>
        </div>
    </div>

    <!-- STATS BAR -->
    <div class="stats-bar" id="stats-bar">
        <div class="stat-chip"><span class="dot dot-livre"></span><span id="s-livres">–</span> Livres</div>
        <div class="stat-chip"><span class="dot dot-ocupada"></span><span id="s-ocupadas">–</span> Ocupadas</div>
        <div class="stat-chip"><span class="dot dot-atrasada"></span><span id="s-atrasadas">–</span> Atrasadas</div>
    </div>

    <!-- GRID DE MESAS -->
    <div class="mesas-grid" id="mesas-grid">
        <div class="text-center" style="grid-column:1/-1;padding:60px 0;color:var(--muted)">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-3">Carregando mesas…</p>
        </div>
    </div>

    <!-- FAB – novo pedido rápido -->
    <button class="fab" onclick="location.href='novo_pedido.php'" title="Novo pedido">
        <i class="fas fa-plus"></i>
    </button>

    <!-- TOAST CONTAINER -->
    <div id="toast-container"></div>
    <div id="sound-banner" class="sound-banner">
        <span><i class="fas fa-volume-up me-2"></i>Toque para ativar alertas sonoros no tablet</span>
        <button type="button" id="sound-enable-btn">Ativar som</button>
    </div>
    <audio id="audio-pronto-garcom" src="data:audio/wav;base64,UklGRoQJAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YWAJAAAAAHkYtyWmISIOI/SV3+jZt+WX/ZEWMSW+IlkQdPbw4K7ZA+Qw+5MUhCSzI38Sz/hq4prZauLP+H8SsyOEJJMUMPsD5K7Z8OB09lkQviIxJZEWl/235ejZld8j9CIOpiG3JXkYAACH50naWt7e8d0LayAYJkkaaQJv6c/aQt2n74wJEB9SJv0b0ARt63zbTdyB7TEHlh1mJpYdMQeB7U3cfNtt69AE/RtSJhAfjAmn70Ldz9pv6WkCSRoYJmsg3Qve8VreSdqH5wAAeRi3JaYhIg4j9JXf6Nm35Zf9kRYxJb4iWRB09vDgrtkD5DD7kxSEJLMjfxLP+Grimtlq4s/4fxKzI4QkkxQw+wPkrtnw4HT2WRC+IjElkRaX/bfl6NmV3yP0Ig6mIbcleRgAAIfnSdpa3t7x3QtrIBgmSRppAm/pz9pC3afvjAkQH1Im/RvQBG3rfNtN3IHtMQeWHWYmlh0xB4HtTdx8223r0AT9G1ImEB+MCafvQt3P2m/paQJJGhgmayDdC97xWt5J2ofnAAB5GLclpiEiDiP0ld/o2bfll/2RFjElviJZEHT28OCu2QPkMPuTFIQksyN/Es/4auKa2Wriz/h/ErMjhCSTFDD7A+Su2fDgdPZZEL4iMSWRFpf9t+Xo2ZXfI/QiDqYhtyV5GAAAh+dJ2lre3vHdC2sgGCZJGmkCb+nP2kLdp++MCRAfUib9G9AEbet8203cge0xB5YdZiaWHTEHge1N3HzbbevQBP0bUiYQH4wJp+9C3c/ab+lpAkkaGCZrIN0L3vFa3knah+cAAHkYtyWmISIOI/SV3+jZt+WX/ZEWMSW+IlkQdPbw4K7ZA+Qw+5MUhCSzI38Sz/hq4prZauLP+H8SsyOEJJMUMPsD5K7Z8OB09lkQviIxJZEWl/235ejZld8j9CIOpiG3JXkYAACH50naWt7e8d0LayAYJkkaaQJv6c/aQt2n74wJEB9SJv0b0ARt63zbTdyB7TEHlh1mJpYdMQeB7U3cfNtt69AE/RtSJhAfjAmn70Ldz9pv6WkCSRoYJmsg3Qve8VreSdqH5wAAeRi3JaYhIg4j9JXf6Nm35Zf9kRYxJb4iWRB09vDgrtkD5DD7kxSEJLMjfxLP+Grimtlq4s/4fxKzI4QkkxQw+wPkrtnw4HT2WRC+IjElkRaX/bfl6NmV3yP0Ig6mIbcleRgAAIfnSdpa3t7x3QtrIBgmSRppAm/pz9pC3afvjAkQH1Im/RvQBG3rfNtN3IHtMQeWHWYmlh0xB4HtTdx8223r0AT9G1ImEB+MCafvQt3P2m/paQJJGhgmayDdC97xWt5J2ofnAAB5GLclpiEiDiP0ld/o2bfll/2RFjElviJZEHT28OCu2QPkMPuTFIQksyN/Es/4auKa2Wriz/h/ErMjhCSTFDD7A+Su2fDgdPZZEL4iMSWRFpf9t+Xo2ZXfI/QiDqYhtyV5GAAAh+dJ2lre3vHdC2sgGCZJGmkCb+nP2kLdp++MCRAfUib9G9AEbet8203cge0xB5YdZiaWHTEHge1N3HzbbevQBP0bUiYQH4wJp+9C3c/ab+lpAkkaGCZrIN0L3vFa3knah+cAAHkYtyWmISIOI/SV3+jZt+WX/ZEWMSW+IlkQdPbw4K7ZA+Qw+5MUhCSzI38Sz/hq4prZauLP+H8SsyOEJJMUMPsD5K7Z8OB09lkQviIxJZEWl/235ejZld8j9CIOpiG3JXkYAACH50naWt7e8d0LayAYJkkaaQJv6c/aQt2n74wJEB9SJv0b0ARt63zbTdyB7TEHlh1mJpYdMQeB7U3cfNtt69AE/RtSJhAfjAmn70Ldz9pv6WkCSRoYJmsg3Qve8VreSdqH5wAAeRi3JaYhIg4j9JXf6Nm35Zf9kRYxJb4iWRB09vDgrtkD5DD7kxSEJLMjfxLP+Grimtlq4s/4fxKzI4QkkxQw+wPkrtnw4HT2WRC+IjElkRaX/bfl6NmV3yP0Ig6mIbcleRgAAIfnSdpa3t7x3QtrIBgmSRppAm/pz9pC3afvjAkQH1Im/RvQBG3rfNtN3IHtMQeWHWYmlh0xB4HtTdx8223r0AT9G1ImEB+MCafvQt3P2m/paQJJGhgmayDdC97xWt5J2ofnAAB5GLclpiEiDiP0ld/o2bfll/2RFjElviJZEHT28OCu2QPkMPuTFIQksyN/Es/4auKa2Wriz/h/ErMjhCSTFDD7A+Su2fDgdPZZEL4iMSWRFpf9t+Xo2ZXfI/QiDqYhtyV5GAAAh+dJ2lre3vHdC2sgGCZJGmkCb+nP2kLdp++MCRAfUib9G9AEbet8203cge0xB5YdZiaWHTEHge1N3HzbbevQBP0bUiYQH4wJp+9C3c/ab+lpAkkaGCZrIN0L3vFa3knah+c=" preload="auto"></audio>

    <!-- STATUS FOOTER -->
    <div id="footer-status">
        <span id="last-update">–</span>
        <span><i class="fas fa-sync-alt me-1" id="refresh-spinner"></i>Atualiza a cada 8s</span>
    </div>

    <!-- MODAL: Mesa Ocupada -->
    <div class="modal fade" id="modalMesaOcupada" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:#1c1e27;color:var(--text);border:1px solid var(--border);border-radius:20px">
                <div class="modal-header modal-header-ocupada">
                    <h5 class="modal-title" id="modal-titulo">
                        <i class="fas fa-chair me-2" style="color:var(--primary)"></i>
                        <span id="modal-mesa-label">Mesa –</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modal-body" style="padding:22px">
                    <!-- Preenchido via JS -->
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);gap:10px">
                    <button class="btn btn-sm" style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:10px"
                        data-bs-dismiss="modal">Fechar</button>
                    <button class="btn btn-sm" id="btn-modal-add"
                        style="background:var(--primary);color:white;border:none;border-radius:10px">
                        <i class="fas fa-plus me-1"></i>Adicionar Itens
                    </button>
                    <button class="btn btn-sm" id="btn-modal-pagar"
                        style="background:var(--livre);color:black;border:none;border-radius:10px;font-weight:700">
                        <i class="fas fa-check me-1"></i>Fechar Conta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentUserId = <?php echo (int)($_SESSION['usuario_id'] ?? 0); ?>;
        const currentPerfil = "<?php echo htmlspecialchars($perfil_raw, ENT_QUOTES); ?>";
        const isMesaDoGarcom = (m) => {
            if (currentPerfil !== 'GARCOM') return true;
            return Number(m.garcom_id || 0) === currentUserId;
        };
        // ─── Utilitários ──────────────────────────────────────────────────────────────
        function esc(str) {
            return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function fmt(v) {
            return parseFloat(v || 0).toLocaleString('pt-MZ', {
                minimumFractionDigits: 2
            });
        }

        function timerText(mins) {
            if (mins === null || mins === undefined) return '';
            if (mins < 1) return 'Agora';
            if (mins < 60) return mins + ' min';
            return Math.floor(mins / 60) + 'h ' + (mins % 60) + 'min';
        }

        // ─── Relógio ──────────────────────────────────────────────────────────────────
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

        // ─── Notificações ─────────────────────────────────────────────────────────────
        let audioCtx = null;
        let soundEnabled = false;
        let audioUnlocked = false;

        function beep(freq, dur, volume = 0.35) {
            if (!soundEnabled || !audioUnlocked || !audioCtx) return;
            try {
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.connect(g);
                g.connect(audioCtx.destination);
                o.frequency.value = freq;
                g.gain.setValueAtTime(volume, audioCtx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + dur);
                o.start();
                o.stop(audioCtx.currentTime + dur);
            } catch (e) {}
        }

        function playBeepPronto() {
            beep(880, 0.16, 0.85);
            setTimeout(() => beep(1046, 0.16, 0.9), 150);
            setTimeout(() => beep(1318, 0.2, 0.95), 300);
            setTimeout(() => beep(1568, 0.24, 1.0), 520);
            if (navigator.vibrate) {
                navigator.vibrate([180, 100, 220, 100, 260]);
            }
        }

        function playPronto() {
            if (!soundEnabled || !audioUnlocked) return;
            const audio = document.getElementById('audio-pronto-garcom');
            if (audio) {
                const playOnce = (delayMs) => {
                    setTimeout(() => {
                        audio.currentTime = 0;
                        audio.volume = 1.0;
                        audio.play().catch(() => playBeepPronto());
                    }, delayMs);
                };
                playOnce(0);
                playOnce(900);
                playOnce(1800);
                return;
            }
            playBeepPronto();
        }

        function updateSoundBanner() {
            const banner = document.getElementById('sound-banner');
            if (banner) banner.classList.toggle('show', !soundEnabled);
        }

        async function enableSoundAlerts() {
            try {
                if (!audioCtx) audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') await audioCtx.resume();
                soundEnabled = true;
                audioUnlocked = true;
                localStorage.setItem('edvis_sound_alerts', '1');
                updateSoundBanner();
                beep(880, 0.12, 0.45);
            } catch (e) {}
        }

        function showToast(msg, type = '') {
            const div = document.createElement('div');
            div.className = 'toast-item ' + type;
            div.innerHTML = '<i class="fas fa-bell"></i>' + esc(msg);
            const tc = document.getElementById('toast-container');
            tc.appendChild(div);
            setTimeout(() => div.remove(), 5000);
        }

        // ─── Renderizar mesa ──────────────────────────────────────────────────────────
        function classifyCard(m) {
            if (m.status === 'LIVRE') return 'livre';
            if (m.status === 'RESERVADA') return 'reservada';
            if (m.pedido_status === 'PRONTO') return 'pronto';
            if (m.pedido_minutos !== null && m.pedido_minutos >= 15) return 'atrasada';
            return 'ocupada';
        }

        function renderMesa(m) {
            const cls = classifyCard(m);
            let badgeCls = 'badge-ocupada',
                badgeTxt = 'Ocupada';
            if (cls === 'livre') {
                badgeCls = 'badge-livre';
                badgeTxt = 'Livre';
            }
            if (cls === 'reservada') {
                badgeCls = 'badge-reservada';
                badgeTxt = 'Reservada';
            }
            if (cls === 'pronto') {
                badgeCls = 'badge-pronto';
                badgeTxt = 'Pronto ✓';
            }
            if (cls === 'atrasada') {
                badgeTxt = 'Atrasada ⚠';
            }

            let infoHtml = '';
            if (m.pedido_id) {
                const mins = m.pedido_minutos;
                const tClass = mins >= 15 ? 'urgent' : mins >= 10 ? 'warn' : '';
                const tTxt = timerText(mins);
                const minhaMesa = isMesaDoGarcom(m) && m.pedido_status === 'PRONTO';

                infoHtml = `
            <div class="mesa-info">
                <div>${esc(m.pedido_itens)} ${m.pedido_itens === 1 ? 'item' : 'itens'}</div>
                <div class="total">MZN ${fmt(m.pedido_total)}</div>
                <div class="timer ${tClass}"><i class="fas fa-clock fa-xs me-1"></i>${tTxt}</div>
                ${minhaMesa ? '<div class="badge-minha">minha mesa</div>' : ''}
            </div>`;
            } else {
                infoHtml = `<div class="mesa-info" style="color:var(--muted)">
            <i class="fas fa-check-circle" style="font-size:20px;color:var(--livre)"></i>
            <div class="mt-1">Disponível</div>
        </div>`;
            }

            const minhaMesa = m.pedido_id ? (isMesaDoGarcom(m) && m.pedido_status === 'PRONTO') : false;
            return `
    <div class="mesa-card ${cls} ${minhaMesa ? 'minha-mesa' : ''}"
         onclick="onMesaClick(${m.id}, ${JSON.stringify(m).replace(/"/g,'&quot;')})"
         data-mesa-id="${m.id}">
        <span class="mesa-cap"><i class="fas fa-user-friends"></i> ${m.capacidade}</span>
        <div class="mesa-numero">${m.numero}</div>
        <div class="mesa-status-badge ${badgeCls}">${badgeTxt}</div>
        ${infoHtml}
    </div>`;
        }

        // ─── Click na mesa ────────────────────────────────────────────────────────────
        function onMesaClick(id, m) {
            if (m.status === 'LIVRE' || !m.pedido_id) {
                // Mesa livre → criar pedido direto
                location.href = 'novo_pedido.php?mesa_id=' + id;
                return;
            }
            // Mesa ocupada → abrir modal de detalhes
            abrirModalMesa(m);
        }

        let currentPedidoId = null;
        let currentMesaId = null;

        function abrirModalMesa(m) {
            currentPedidoId = m.pedido_id;
            currentMesaId = m.id;

            document.getElementById('modal-mesa-label').textContent = 'Mesa ' + m.numero;

            const mins = m.pedido_minutos;
            const tClass = mins >= 15 ? 'color:var(--atrasada)' : mins >= 10 ? 'color:var(--ocupada)' : 'color:var(--muted)';
            const tTxt = timerText(mins);

            let statusIcon = '';
            if (m.pedido_status === 'PRONTO') statusIcon = '<span style="color:var(--pronto)"><i class="fas fa-check-circle me-1"></i>Pronto para entrega</span>';
            else if (m.pedido_status === 'PREPARANDO') statusIcon = '<span style="color:var(--ocupada)"><i class="fas fa-fire me-1"></i>Em preparo</span>';
            else statusIcon = '<span style="color:var(--muted)"><i class="fas fa-clock me-1"></i>Aguardando</span>';

            document.getElementById('modal-body').innerHTML = `
        <div style="display:flex;justify-content:space-between;margin-bottom:14px;font-size:13px">
            <span>${statusIcon}</span>
            <span style="${tClass}"><i class="fas fa-stopwatch me-1"></i>${tTxt}</span>
        </div>
        <div id="modal-itens-lista">
            <div style="text-align:center;padding:20px;color:var(--muted)"><i class="fas fa-spinner fa-spin"></i> Carregando itens…</div>
        </div>
        <hr style="border-color:var(--border);margin:14px 0">
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700">
            <span>Total</span>
            <span>MZN ${fmt(m.pedido_total)}</span>
        </div>
        ${m.garcom_nome ? '<div style="font-size:12px;color:var(--muted);margin-top:6px"><i class="fas fa-user me-1"></i>' + esc(m.garcom_nome) + '</div>' : ''}
    `;

            // Botões de ação
            document.getElementById('btn-modal-add').onclick = () => {
                location.href = 'novo_pedido.php?mesa_id=' + currentMesaId + '&pedido_id=' + currentPedidoId;
            };
            document.getElementById('btn-modal-pagar').onclick = () => {
                location.href = 'caixa.php?pedido_id=' + currentPedidoId;
            };

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMesaOcupada')).show();

            // Carregar itens detalhados
            fetch('api/pedido_itens.php?id=' + currentPedidoId)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    let html = '';
                    (data.itens || []).forEach(it => {
                        const destino = String(it.destino || 'cozinha').toLowerCase();
                        const status = String(it.status || 'pendente').toLowerCase();
                        const destinoClass = destino === 'bar' ? 'badge-dest-bar' : 'badge-dest-cozinha';
                        const statusMap = {
                            'pendente': ['badge-st-pendente', 'Pendente'],
                            'em_preparo': ['badge-st-preparo', 'Em preparo'],
                            'pronto': ['badge-st-pronto', 'Pronto'],
                            'entregue': ['badge-st-entregue', 'Entregue']
                        };
                        const st = statusMap[status] || ['badge-st-pendente', status];
                        html += `<div class="item-pedido-row">
                    <span class="nome">${esc(it.produto_nome)}
                        <div class="item-meta">
                            <span class="item-badge ${destinoClass}">${esc(destino)}</span>
                            <span class="item-badge ${st[0]}">${esc(st[1])}</span>
                        </div>
                    </span>
                    <span class="qtd">${esc(it.quantidade)}×</span>
                    <span class="preco">MZN ${fmt(parseFloat(it.preco_unitario) * parseFloat(it.quantidade))}</span>
                </div>`;
                    });
                    if (data.observacao) {
                        html += `<div style="font-size:12px;color:#fcd34d;background:rgba(245,158,11,0.1);border-radius:8px;padding:8px;margin-top:10px">
                    <i class="fas fa-comment-dots me-1"></i>${esc(data.observacao)}</div>`;
                    }
                    document.getElementById('modal-itens-lista').innerHTML = html || '<p style="color:var(--muted);text-align:center">Sem itens</p>';
                })
                .catch(() => {});
        }

        // ─── Carregar mesas (polling) ─────────────────────────────────────────────────
        let prevProntos = new Set();
        let prevReadyByPedido = {};

        async function carregarMesas() {
            document.getElementById('refresh-spinner').style.display = 'inline-block';
            try {
                const resp = await fetch('api/garcom_mesas_detalhes.php');
                const data = await resp.json();
                if (!data.success) throw new Error(data.message || 'Erro');

                // Detectar pedidos que ficaram prontos
                const newProntos = new Set(
                    data.mesas.filter(m => m.pedido_status === 'PRONTO').map(m => m.pedido_id)
                );
                newProntos.forEach(id => {
                    if (!prevProntos.has(id)) {
                        const m = data.mesas.find(x => x.pedido_id === id);
                        if (m && isMesaDoGarcom(m)) {
                            playPronto();
                            showToast('Mesa ' + m.numero + ' — pedido pronto! 🍽️', 'pronto');
                        }
                    }
                });
                prevProntos = newProntos;

                // Notificacoes granulares por setor (bar/cozinha)
                (data.mesas || []).forEach(m => {
                    if (!m.pedido_id) return;
                    const pid = String(m.pedido_id);
                    const prev = prevReadyByPedido[pid] || {
                        bar: 0,
                        cozinha: 0
                    };
                    const nowBar = Number(m.itens_prontos_bar || 0);
                    const nowCoz = Number(m.itens_prontos_cozinha || 0);

                    if (nowBar > prev.bar && isMesaDoGarcom(m)) {
                        playPronto();
                        showToast('Mesa ' + m.numero + ' — bebida pronta 🍹', 'pronto');
                    }
                    if (nowCoz > prev.cozinha && isMesaDoGarcom(m)) {
                        playPronto();
                        showToast('Mesa ' + m.numero + ' — comida pronta 🍽️', 'pronto');
                    }

                    prevReadyByPedido[pid] = {
                        bar: nowBar,
                        cozinha: nowCoz
                    };
                });

                // Renderizar grid
                const grid = document.getElementById('mesas-grid');
                if (data.mesas.length === 0) {
                    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--muted)"><i class="fas fa-chair fa-2x mb-3"></i><p>Nenhuma mesa cadastrada.<br><a href="mesas.php" style="color:var(--primary)">Cadastrar mesas</a></p></div>';
                } else {
                    grid.innerHTML = data.mesas.map(renderMesa).join('');
                }

                // Atualizar stats
                const s = data.stats || {};
                document.getElementById('s-livres').textContent = s.livres ?? '–';
                document.getElementById('s-ocupadas').textContent = s.ocupadas ?? '–';
                document.getElementById('s-atrasadas').textContent = s.atrasadas ?? '–';

                document.getElementById('last-update').textContent =
                    'Atualizado às ' + new Date().toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });

            } catch (e) {
                document.getElementById('last-update').textContent = 'Erro ao carregar – ' + e.message;
            } finally {
                document.getElementById('refresh-spinner').style.display = 'none';
            }
        }

        document.addEventListener('click', () => {
            try {
                if (!audioCtx) audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                audioUnlocked = true;
            } catch (e) {}
        }, { once: true });

        document.addEventListener('touchstart', () => {
            try {
                if (!audioCtx) audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                audioUnlocked = true;
            } catch (e) {}
        }, { once: true, passive: true });

        soundEnabled = localStorage.getItem('edvis_sound_alerts') === '1';
        updateSoundBanner();
        document.getElementById('sound-enable-btn')?.addEventListener('click', enableSoundAlerts);
        setTimeout(updateSoundBanner, 150);

        // Carregar imediatamente e a cada 5s
        carregarMesas();
        setInterval(carregarMesas, 5000);
    </script>
</body>

</html>

