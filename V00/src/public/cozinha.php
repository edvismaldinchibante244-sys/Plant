<?php
require_once '../config/database.php'; // ajuste para seu include de conexão
$db = (new Database())->getConnection(); // Instancia a conexão PDO

// Busca itens destinados à cozinha, status em_preparo ou pronto
$sql = "
    SELECT i.id, i.pedido_id, i.produto_id,
           COALESCE(i.status, 'em_preparo') AS status,
           CASE
               WHEN COALESCE(i.status, 'em_preparo') = 'em_preparo' THEN COALESCE(i.iniciado_preparo_em, ped.criado_em)
               WHEN i.status = 'pronto' THEN i.pronto_em
               ELSE NULL
           END AS data_status,
           p.nome AS produto_nome,
           i.quantidade,
           m.numero AS mesa_numero
    FROM itens_pedido i
    JOIN produtos p ON i.produto_id = p.id
    JOIN pedidos ped ON i.pedido_id = ped.id
    LEFT JOIN mesas m ON ped.mesa_id = m.id
    WHERE i.destino = 'cozinha'
      AND COALESCE(i.status, 'em_preparo') IN ('em_preparo', 'pronto')
    ORDER BY FIELD(COALESCE(i.status, 'em_preparo'), 'em_preparo', 'pronto'), data_status ASC
";
$stmt = $db->prepare($sql);
$stmt->execute();
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grupos = [
    'em_preparo' => [],
    'pronto' => [],
];
foreach ($itens as $item) {
    $status = strtolower((string)($item['status'] ?? 'em_preparo'));
    if (!isset($grupos[$status])) {
        $status = 'em_preparo';
    }
    $grupos[$status][] = $item;
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cozinha - Itens do Dia</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --kds-bg: #0f1115;
            --kds-panel: #171a21;
            --kds-panel-2: #1c202a;
            --kds-border: #2a303b;
            --kds-text: #e5e7eb;
            --kds-muted: #9aa3af;
            --kds-blue: #3b82f6;
            --kds-amber: #f59e0b;
            --kds-green: #10b981;
            --kds-danger: #ef4444;
        }

        body {
            background: var(--kds-bg);
            color: var(--kds-text);
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        .kds-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid var(--kds-border);
            background: #0b0d12;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .kds-title {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .kds-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--kds-muted);
        }

        .kds-actions a {
            text-decoration: none;
            color: var(--kds-text);
            border: 1px solid var(--kds-border);
            padding: 6px 12px;
            border-radius: 8px;
            background: #0f131b;
        }

        .kds-actions a.btn-primary {
            background: #fbbf24;
            border-color: #fbbf24;
            color: #111827;
            font-weight: 700;
        }

        .board {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            padding: 18px;
        }

        .column {
            background: var(--kds-panel);
            border: 1px solid var(--kds-border);
            border-radius: 14px;
            overflow: hidden;
            min-height: 200px;
        }

        .column-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-size: 12px;
        }

        .column-header .count {
            background: rgba(255, 255, 255, 0.12);
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
        }

        .column-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .column-novo .column-header { background: rgba(59, 130, 246, 0.2); color: #bfdbfe; }
        .column-preparo .column-header { background: rgba(245, 158, 11, 0.2); color: #fde68a; }
        .column-pronto .column-header { background: rgba(16, 185, 129, 0.2); color: #bbf7d0; }

        .card-item {
            background: var(--kds-panel-2);
            border: 1px solid var(--kds-border);
            border-radius: 14px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
        }

        .card-item.antigo { border-color: var(--kds-danger); }
        .card-item.critico {
            border-color: var(--kds-danger);
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.35), 0 10px 24px rgba(239, 68, 68, 0.25);
        }
        .card-item.critico .tempo { color: #fecaca; }

        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 700;
        }

        .card-sub {
            font-size: 12px;
            color: var(--kds-muted);
        }

        .badge-qtd {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #111827;
            border: 1px solid var(--kds-border);
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 12px;
            color: var(--kds-text);
        }

        .tempo {
            font-size: 12px;
            color: #f87171;
            font-weight: 600;
        }

        .btn-status {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-start { background: #f59e0b; color: #111827; }
        .btn-ready { background: #10b981; color: #0b1220; }
        .btn-delivered { background: #3b82f6; color: #0b1220; }
        .btn-status[disabled] { background: #374151; color: #9ca3af; cursor: not-allowed; }

        .status-pronto {
            color: var(--kds-green);
            font-weight: 700;
        }

        .empty-state {
            padding: 22px 14px;
            text-align: center;
            color: var(--kds-muted);
            border: 1px dashed var(--kds-border);
            border-radius: 12px;
        }

        @media (max-width: 980px) {
            .board { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <div class="kds-topbar">
        <div class="kds-title">Cozinha - Itens do Dia</div>
        <div class="kds-actions">
            <span id="kds-clock"></span>
            <a class="btn-primary" href="novo_pedido.php">+ Novo Pedido</a>
            <a href="dashboard.php">Voltar</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="board">
        <?php
        $colunas = [
            'em_preparo' => ['label' => 'EM PREPARO', 'class' => 'column-preparo'],
            'pronto' => ['label' => 'PRONTO', 'class' => 'column-pronto'],
        ];
        foreach ($colunas as $statusKey => $info):
            $lista = $grupos[$statusKey] ?? [];
        ?>
            <div class="column <?= $info['class'] ?>">
                <div class="column-header">
                    <span><?= $info['label'] ?></span>
                    <span class="count"><?= count($lista) ?></span>
                </div>
                <div class="column-body">
                    <?php if (empty($lista)): ?>
                        <div class="empty-state">Nenhum pedido</div>
                    <?php else: ?>
                        <?php foreach ($lista as $item):
                            $criado = strtotime($item['data_status']);
                            $minutos = $criado ? floor((time() - $criado) / 60) : 0;
                            $critico = $minutos > 20 ? 'critico' : '';
                            $antigo = $minutos > 10 ? 'antigo' : '';
                            $tempoTexto = $minutos <= 0 ? 'agora' : ($minutos . ' min atrás');
                            $mesaTexto = $item['mesa_numero'] ? 'Mesa ' . (int)$item['mesa_numero'] : 'Balcão';
                            $qtd = (int)($item['quantidade'] ?? 1);
                        ?>
                            <div class="card-item <?= $antigo ?> <?= $critico ?>" data-id="<?= (int)$item['id'] ?>" data-status="<?= htmlspecialchars($item['status']) ?>">
                                <div class="card-top">
                                    <span><?= htmlspecialchars($mesaTexto) ?></span>
                                </div>
                                <div class="card-sub">Pedido #<?= (int)$item['pedido_id'] ?> · Item #<?= (int)$item['id'] ?></div>
                                <div>
                                    <span class="badge-qtd"><?= $qtd ?>x</span>
                                    <strong><?= htmlspecialchars($item['produto_nome']) ?></strong>
                                </div>
                                <div class="tempo"><?= $tempoTexto ?></div>
                                <?php if ($statusKey === 'em_preparo'): ?>
                                    <button class="btn-status btn-ready" type="button" data-next="pronto">Marcar Pronto</button>
                                <?php else: ?>
                                    <button class="btn-status btn-delivered" type="button" data-next="entregue">Marcar Entregue</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <audio id="audio-novo" src="data:audio/wav;base64,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" preload="auto"></audio>
    <audio id="audio-pronto" src="data:audio/wav;base64,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" preload="auto"></audio>
    <script>
        // Relógio
        const clockEl = document.getElementById('kds-clock');
        const tick = () => {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString('pt-BR', { hour12: false });
        };
        tick();
        setInterval(tick, 1000);

        // Beep fallback (caso áudio não carregue)
        let audioCtx = null;
        function beep(freq, dur, volume = 0.5) {
            try {
                if (!audioCtx) audioCtx = new(window.AudioContext || window.webkitAudioContext)();
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
        function beepNovo() {
            beep(880, 0.1, 0.25);
            setTimeout(() => beep(1046, 0.1, 0.25), 120);
        }
        function beepPronto() {
            beep(1318, 0.14, 0.4);
            setTimeout(() => beep(1568, 0.16, 0.45), 160);
        }

        // Atualizar status do item
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-status');
            if (!btn) return;
            const card = btn.closest('.card-item');
            const itemId = card?.dataset.id;
            const next = btn.dataset.next;
            if (!itemId || !next) return;

            btn.disabled = true;
            fetch('api/pedido_item_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'item_id=' + encodeURIComponent(itemId) + '&status=' + encodeURIComponent(next)
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        btn.disabled = false;
                        alert(data.message || 'Erro ao atualizar status!');
                        return;
                    }
                    setTimeout(() => location.reload(), 400);
                });
        });

        // Alerta sonoro inteligente: toca se entrou item novo EM PREPARO ou mudou para PRONTO
        const audioNovo = document.getElementById('audio-novo');
        const audioPronto = document.getElementById('audio-pronto');
        const storageKey = 'kds_cozinha_counts';
        const countByStatus = () => {
            const cards = document.querySelectorAll('.card-item');
            let preparo = 0;
            let pronto = 0;
            cards.forEach(c => {
                const s = (c.dataset.status || '').toLowerCase();
                if (s === 'em_preparo') preparo++;
                if (s === 'pronto') pronto++;
            });
            return { preparo, pronto };
        };
        const prevRaw = localStorage.getItem(storageKey);
        const prev = prevRaw ? JSON.parse(prevRaw) : { preparo: 0, pronto: 0 };
        const current = countByStatus();
        if (prev.preparo !== 0 || prev.pronto !== 0) {
            if (current.preparo > prev.preparo) {
                audioNovo?.play().catch(() => beepNovo());
            }
            if (current.pronto > prev.pronto) {
                setTimeout(() => audioPronto?.play().catch(() => beepPronto()), current.preparo > prev.preparo ? 350 : 0);
            }
        }
        localStorage.setItem(storageKey, JSON.stringify(current));

        // Atualização automática dos cards (pode ser AJAX para atualizar só a lista)
        setInterval(() => location.reload(), 4000);

        // Som ao novo pedido (exemplo simples, pode ser melhorado)
        let lastCount = document.querySelectorAll('.card-item').length;
        setInterval(() => {
            fetch(window.location.href)
                .then(r => r.text())
                .then(html => {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const newCount = temp.querySelectorAll('.card-item').length;
                    if (newCount > lastCount) {
                        document.getElementById('audio-novo').play();
                    }
                    lastCount = newCount;
                });
        }, 5000);
    </script>
</body>

</html>
