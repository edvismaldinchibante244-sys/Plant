<?php

/*
   RELATÓRIOS DO SISTEMA - Estrutura Premium Unificada
*/
session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: index.php");
    exit;
}

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/restaurante_context.php';

$database = new Database();
$db = $database->getConnection();

$restaurante_id = $_SESSION['restaurante_id'] ?? 0;
$restaurante_plan_id = session_restaurante_capability_id();
$restaurante_nome_atual = 'Restaurante';

try {
    $stmt_restaurante_nome = $db->prepare("SELECT nome FROM restaurantes WHERE id = ? LIMIT 1");
    $stmt_restaurante_nome->execute([$restaurante_id]);
    $restaurante_nome_atual = trim((string)($stmt_restaurante_nome->fetchColumn() ?: 'Restaurante'));
} catch (Throwable $e) {
    $restaurante_nome_atual = 'Restaurante';
}

if ($restaurante_id <= 0) {
    header("Location: index.php?erro=sem_restaurante");
    exit;
}

$tipos = [
    'vendas'        => 'Vendas',
    'produtos'      => 'Produtos Mais Vendidos',
    'caixa'         => 'Movimentação de Caixa',
    'financeiro'    => 'Resumo Financeiro',
    'pedidos_qr'    => 'Pedidos QR (Mesa/Hora)',
    'horarios_pico' => 'Horários de Pico',
    'tempo_preparo' => 'Tempo de Preparo',
];

$relatorios_validar_data = static function ($valor): ?string {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $valor);
    if (!$dt || $dt->format('Y-m-d') !== $valor) {
        return null;
    }

    return $valor;
};

$relatorios_escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$relatorios_formatar_duracao = static function ($segundos): string {
    $segundos = max(0, (int)round((float)$segundos));

    if ($segundos < 60) {
        return $segundos . ' s';
    }

    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);

    if ($horas > 0) {
        if ($minutos === 0) {
            return $horas . ' h';
        }

        return $horas . 'h ' . $minutos . 'min';
    }

    return $minutos . ' min';
};

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'vendas';
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30';
$data_inicio_custom = $relatorios_validar_data($_GET['data_inicio'] ?? '');
$data_fim_custom = $relatorios_validar_data($_GET['data_fim'] ?? '');
$export = strtolower(trim((string)($_GET['export'] ?? '')));

include_once __DIR__ . '/../config/plano_check.php';
$tem_relatorio_diario = plano_tem_funcionalidade_db($restaurante_plan_id, 'relatorio_diario');
$tem_relatorios_avancados = plano_tem_funcionalidade_db($restaurante_plan_id, 'relatorios_avancados');
$dados_plano = plano_get_dados($restaurante_id);
$plano_atual = plano_normalizar_nome($dados_plano['plano_nome'] ?? 'BASICO');
$tem_relatorios_avancados = $tem_relatorios_avancados || in_array($plano_atual, ['PROFISSIONAL', 'EMPRESARIAL'], true);
$tem_exportacao_relatorios = plano_tem_funcionalidade_db($restaurante_plan_id, 'exportacao_relatorios')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'export_excel')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'export_csv')
    || plano_tem_funcionalidade_db($restaurante_plan_id, 'export_pdf')
    || in_array($plano_atual, ['PROFISSIONAL', 'EMPRESARIAL'], true);
$pode_exportar = $tem_relatorios_avancados && $tem_exportacao_relatorios;
$pode_imprimir = $tem_relatorios_avancados;
$pode_exportar_pdf = $plano_atual === 'EMPRESARIAL';
$table_class = $tem_relatorios_avancados ? 'table table-hover mb-0 table-advanced' : 'table table-hover mb-0';
$tipos_visiveis = $tem_relatorios_avancados ? $tipos : [
    'vendas' => 'Relatório Diário',
];

$tipo = array_key_exists($tipo, $tipos_visiveis) ? $tipo : 'vendas';

switch ($periodo) {
    case '7':
        $data_inicio = date('Y-m-d', strtotime('-7 days'));
        $data_fim = date('Y-m-d');
        break;
    case '30':
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        $data_fim = date('Y-m-d');
        break;
    case '90':
        $data_inicio = date('Y-m-d', strtotime('-90 days'));
        $data_fim = date('Y-m-d');
        break;
    case 'all':
        $data_inicio = '2020-01-01';
        $data_fim = date('Y-m-d');
        break;
    case 'custom':
        $data_inicio = $data_inicio_custom ?: date('Y-m-d', strtotime('-30 days'));
        $data_fim = $data_fim_custom ?: date('Y-m-d');
        break;
    default:
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        $data_fim = date('Y-m-d');
}

if (strtotime($data_inicio) > strtotime($data_fim)) {
    [$data_inicio, $data_fim] = [$data_fim, $data_inicio];
}

$pedidos_qr_mesa = [];
$pedidos_qr_hora = [];
$resumo_qr = [
    'total_pedidos' => 0,
    'total_receita' => 0,
    'mesas_ativas' => 0,
    'hora_pico' => '--'
];
$por_pagamento = [];
$grand_total = 0;

// Verificação de acesso ao relatório diário/avançado
if ($tipo === 'vendas') {
    if (!$tem_relatorio_diario && !$tem_relatorios_avancados) {
        header("Location: dashboard.php?aviso=plano_sem_relatorio_diario");
        exit;
    }
} elseif (!$tem_relatorios_avancados) {
    $tipo = 'vendas';
}

// Carregar dados
try {
    if ($tipo === 'vendas' || $tipo === 'financeiro'):
        $stmt = $db->prepare("SELECT v.*, u.nome as usuario_nome, COALESCE(m.numero, 'Sem mesa') as mesa_numero FROM vendas v LEFT JOIN usuarios u ON v.usuario_id = u.id LEFT JOIN mesas m ON v.mesa_id = m.id WHERE v.restaurante_id = ? AND DATE(v.criado_em) BETWEEN ? AND ? ORDER BY v.criado_em DESC");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_vendas = count($vendas);
        $valor_total = 0;
        foreach ($vendas as $v) {
            $valor_total += floatval($v['valor_total'] ?? $v['total'] ?? $v['total_final'] ?? 0);
        }
        $ticket_medio = $total_vendas > 0 ? $valor_total / $total_vendas : 0;
    endif;

    if ($tipo === 'produtos'):
        $stmt = $db->prepare("SELECT
                                p.nome,
                                p.preco,
                                COALESCE(NULLIF(TRIM(c.nome), ''), NULLIF(TRIM(p.categoria), ''), '-') AS categoria,
                                COALESCE(SUM(pv.quantidade), 0) AS total_vendido,
                                COALESCE(SUM(pv.quantidade * COALESCE(pv.preco_unitario, p.preco)), 0) AS receita
                              FROM produtos p
                              LEFT JOIN categorias c ON c.id = p.categoria_id
                              LEFT JOIN itens_pedido pv ON p.id = pv.produto_id
                              LEFT JOIN pedidos ped ON pv.pedido_id = ped.id
                              WHERE p.restaurante_id = ?
                              GROUP BY p.id, p.nome, p.preco, c.nome, p.categoria
                              ORDER BY total_vendido DESC
                              LIMIT 20");
        $stmt->execute([$restaurante_id]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular totais para resumo
        $total_vendas = 0;
        $valor_total = 0;
        foreach ($produtos as $p) {
            $total_vendas += floatval($p['total_vendido'] ?? 0);
            $valor_total += floatval($p['receita'] ?? 0);
        }
        $ticket_medio = $total_vendas > 0 ? $valor_total / $total_vendas : 0;
    endif;

    if ($tipo === 'caixa'):
        $stmt = $db->prepare("SELECT * FROM caixas WHERE restaurante_id = ? AND DATE(data_abertura) BETWEEN ? AND ? ORDER BY data_abertura DESC");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $movimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular totais para resumo (com base em entradas/saídas)
        $total_vendas = count($movimentos);
        $valor_total = 0;
        foreach ($movimentos as $m) {
            $valor_total += floatval($m['entrada'] ?? 0) - floatval($m['saida'] ?? 0);
        }
        $ticket_medio = $total_vendas > 0 ? $valor_total / $total_vendas : 0;
    endif;

    if ($tipo === 'pedidos_qr'):
        $stmt = $db->prepare("SELECT
                                COALESCE(m.numero, 'Sem mesa') AS mesa_numero,
                                COUNT(*) AS total_pedidos,
                                COALESCE(SUM(p.total), 0) AS valor_total,
                                COALESCE(AVG(p.total), 0) AS ticket_medio
                              FROM pedidos p
                              LEFT JOIN mesas m ON p.mesa_id = m.id
                              WHERE p.restaurante_id = ?
                              AND DATE(p.criado_em) BETWEEN ? AND ?
                              GROUP BY COALESCE(m.numero, 'Sem mesa')
                              ORDER BY total_pedidos DESC");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $pedidos_qr_mesa = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT
                                DATE_FORMAT(p.criado_em, '%H:00') AS hora,
                                COUNT(*) AS total_pedidos,
                                COALESCE(SUM(p.total), 0) AS valor_total
                              FROM pedidos p
                              WHERE p.restaurante_id = ?
                              AND DATE(p.criado_em) BETWEEN ? AND ?
                              GROUP BY DATE_FORMAT(p.criado_em, '%H')
                              ORDER BY DATE_FORMAT(p.criado_em, '%H') ASC");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $pedidos_qr_hora = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT COUNT(*) AS total_pedidos,
                                     COALESCE(SUM(total), 0) AS total_receita,
                                     COUNT(DISTINCT mesa_id) AS mesas_ativas
                              FROM pedidos
                              WHERE restaurante_id = ?
                              AND DATE(criado_em) BETWEEN ? AND ?");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $resumo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $resumo_qr['total_pedidos'] = (int)($resumo['total_pedidos'] ?? 0);
        $resumo_qr['total_receita'] = (float)($resumo['total_receita'] ?? 0);
        $resumo_qr['mesas_ativas'] = (int)($resumo['mesas_ativas'] ?? 0);

        $hora_pico = '--';
        $max_pedidos_hora = -1;
        foreach ($pedidos_qr_hora as $item_hora) {
            $count_hora = (int)($item_hora['total_pedidos'] ?? 0);
            if ($count_hora > $max_pedidos_hora) {
                $max_pedidos_hora = $count_hora;
                $hora_pico = $item_hora['hora'] ?? '--';
            }
        }
        $resumo_qr['hora_pico'] = $hora_pico;
    endif;

    if ($tipo === 'horarios_pico'):
        $stmt = $db->prepare("
            SELECT HOUR(criado_em) AS hora,
                   COUNT(*) AS qtd_pedidos,
                   COALESCE(SUM(total), 0) AS receita
            FROM pedidos
            WHERE restaurante_id = ?
              AND DATE(criado_em) BETWEEN ? AND ?
              AND status NOT IN ('CANCELADO')
            GROUP BY HOUR(criado_em)
            ORDER BY HOUR(criado_em) ASC");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $pico_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build full 0-23 hour array
        $pico_horas = array_fill(0, 24, ['hora' => 0, 'qtd_pedidos' => 0, 'receita' => 0]);
        foreach ($pico_data as $row) {
            $h = (int)$row['hora'];
            $pico_horas[$h] = ['hora' => $h, 'qtd_pedidos' => (int)$row['qtd_pedidos'], 'receita' => (float)$row['receita']];
        }
        $pico_max = max(array_column($pico_horas, 'qtd_pedidos')) ?: 1;
        $pico_best = array_search(max(array_column($pico_horas, 'qtd_pedidos')), array_column($pico_horas, 'qtd_pedidos'));
        $total_vendas = array_sum(array_column($pico_horas, 'qtd_pedidos'));
        $valor_total  = array_sum(array_column($pico_horas, 'receita'));
        $ticket_medio = $total_vendas > 0 ? $valor_total / $total_vendas : 0;
    endif;

    if ($tipo === 'tempo_preparo'):
        // Average wait time (order placed → started prep) and prep time (started → done)
        $stmt = $db->prepare("
            SELECT
                AVG(TIMESTAMPDIFF(SECOND, criado_em, iniciado_preparo_em))       AS avg_espera_seg,
                AVG(TIMESTAMPDIFF(SECOND, iniciado_preparo_em, atualizado_em))   AS avg_preparo_seg,
                MIN(TIMESTAMPDIFF(SECOND, iniciado_preparo_em, atualizado_em))   AS min_preparo_seg,
                MAX(TIMESTAMPDIFF(SECOND, iniciado_preparo_em, atualizado_em))   AS max_preparo_seg,
                COUNT(*)                                                         AS total_pedidos
            FROM pedidos
            WHERE restaurante_id = ?
              AND DATE(criado_em) BETWEEN ? AND ?
              AND status IN ('PRONTO','ENTREGUE','PAGO')
              AND iniciado_preparo_em IS NOT NULL
              AND atualizado_em > iniciado_preparo_em");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $prep_resumo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Breakdown by hour of day
        $stmt = $db->prepare("
            SELECT HOUR(criado_em) AS hora,
                   COUNT(*) AS qtd,
                   AVG(TIMESTAMPDIFF(SECOND, iniciado_preparo_em, atualizado_em)) AS avg_seg
            FROM pedidos
            WHERE restaurante_id = ?
              AND DATE(criado_em) BETWEEN ? AND ?
              AND status IN ('PRONTO','ENTREGUE','PAGO')
              AND iniciado_preparo_em IS NOT NULL
              AND atualizado_em > iniciado_preparo_em
            GROUP BY HOUR(criado_em)
            ORDER BY HOUR(criado_em) ASC");
        $stmt->execute([$restaurante_id, $data_inicio, $data_fim]);
        $prep_por_hora = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_vendas  = (int)($prep_resumo['total_pedidos'] ?? 0);
        $valor_total   = 0;
        $ticket_medio  = round(((float)($prep_resumo['avg_preparo_seg'] ?? 0) / 60), 1);
    endif;

    if ($tipo === 'financeiro') {
        $por_pagamento = [];
        foreach ($vendas ?? [] as $v) {
            $forma = strtoupper((string)($v['forma_pagamento'] ?? 'DINHEIRO'));
            if (!isset($por_pagamento[$forma])) {
                $por_pagamento[$forma] = ['qtd' => 0, 'total' => 0];
            }
            $por_pagamento[$forma]['qtd']++;
            $por_pagamento[$forma]['total'] += floatval($v['valor_total'] ?? $v['total'] ?? $v['total_final'] ?? 0);
        }
        arsort($por_pagamento);
        $grand_total = array_sum(array_column($por_pagamento, 'total'));
    }
} catch (Exception $e) {
    $erro_dados = $e->getMessage();
}

if ($export === 'excel' && !isset($erro_dados)) {
    if (!$pode_exportar) {
        $erro_dados = 'Seu plano atual não permite exportação de relatórios.';
    } else {
    if (ob_get_length()) {
        ob_clean();
    }

    $export_titulo = $tipos[$tipo] ?? ucfirst($tipo);
    $export_periodo = date('d/m/Y', strtotime($data_inicio)) . ' - ' . date('d/m/Y', strtotime($data_fim));
    $export_gerado_em = date('d/m/Y H:i:s');
    $export_resumo = [];
    $export_secoes = [];

    if ($tipo === 'pedidos_qr') {
        $export_resumo = [
            ['label' => 'Total de Pedidos QR', 'valor' => (string)((int)($resumo_qr['total_pedidos'] ?? 0))],
            ['label' => 'Receita QR', 'valor' => 'MZN ' . number_format((float)($resumo_qr['total_receita'] ?? 0), 2, ',', '.')],
            ['label' => 'Mesas Ativas', 'valor' => (string)((int)($resumo_qr['mesas_ativas'] ?? 0))],
            ['label' => 'Hora Pico', 'valor' => (string)($resumo_qr['hora_pico'] ?? '--')],
        ];
    } else {
        $export_resumo = [
            ['label' => 'Total de Registos', 'valor' => (string)($total_vendas ?? 0)],
            ['label' => 'Faturamento', 'valor' => 'MZN ' . number_format((float)($valor_total ?? 0), 2, ',', '.')],
            ['label' => 'Ticket Medio', 'valor' => 'MZN ' . number_format((float)($ticket_medio ?? 0), 2, ',', '.')],
            ['label' => 'Periodo', 'valor' => $export_periodo],
        ];
    }

    if ($tipo === 'vendas') {
        $linhas = [];
        foreach ($vendas ?? [] as $v) {
            $linhas[] = [
                '#' . str_pad((string)$v['id'], 4, '0', STR_PAD_LEFT),
                !empty($v['criado_em']) ? date('d/m/Y H:i', strtotime($v['criado_em'])) : '-',
                $v['cliente_nome'] ?? 'Consumidor',
                (isset($v['mesa_numero']) && $v['mesa_numero'] !== 'Sem mesa') ? 'Mesa ' . $v['mesa_numero'] : ($v['mesa_numero'] ?? '-'),
                number_format((float)($v['valor_total'] ?? $v['total'] ?? $v['total_final'] ?? 0), 2, ',', '.'),
                ucfirst((string)($v['forma_pagamento'] ?? 'Dinheiro')),
                $v['usuario_nome'] ?? '-',
                ucfirst((string)($v['status'] ?? 'Pendente')),
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Detalhe de Vendas',
            'cabecalhos' => ['ID', 'Data/Hora', 'Cliente', 'Mesa', 'Valor (MZN)', 'Forma Pagamento', 'Atendido Por', 'Status'],
            'linhas' => $linhas,
        ];

        $linhas_pagamento = [];
        foreach ($por_pagamento as $forma => $dados) {
            $linhas_pagamento[] = [
                $forma,
                (int)$dados['qtd'],
                number_format((float)$dados['total'], 2, ',', '.'),
                $grand_total > 0 ? number_format(((float)$dados['total'] / $grand_total) * 100, 1, ',', '.') . '%' : '0,0%',
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Resumo por Forma de Pagamento',
            'cabecalhos' => ['Forma', 'Qtd. Vendas', 'Total (MZN)', '% do Faturamento'],
            'linhas' => $linhas_pagamento,
        ];
    } elseif ($tipo === 'produtos') {
        $linhas = [];
        foreach ($produtos ?? [] as $p) {
            $linhas[] = [
                $p['nome'] ?? '',
                $p['categoria'] ?? '-',
                number_format((float)($p['preco'] ?? 0), 2, ',', '.'),
                (int)($p['total_vendido'] ?? 0),
                number_format((float)($p['receita'] ?? 0), 2, ',', '.'),
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Produtos Mais Vendidos',
            'cabecalhos' => ['Produto', 'Categoria', 'Preco (MZN)', 'Quant. Vendida', 'Receita (MZN)'],
            'linhas' => $linhas,
        ];
    } elseif ($tipo === 'caixa') {
        $linhas = [];
        foreach ($movimentos ?? [] as $m) {
            $linhas[] = [
                !empty($m['data_abertura']) ? date('d/m/Y H:i', strtotime($m['data_abertura'])) : '-',
                !empty($m['data_fechamento']) ? date('d/m/Y H:i', strtotime($m['data_fechamento'])) : '-',
                number_format((float)($m['valor_abertura'] ?? 0), 2, ',', '.'),
                number_format((float)($m['valor_fechamento'] ?? 0), 2, ',', '.'),
                ucfirst((string)($m['status'] ?? 'Aberto')),
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Movimentacao de Caixa',
            'cabecalhos' => ['Data Abertura', 'Data Fechamento', 'Valor Abertura (MZN)', 'Valor Fechamento (MZN)', 'Status'],
            'linhas' => $linhas,
        ];
    } elseif ($tipo === 'financeiro') {
        $linhas = [];
        foreach ($por_pagamento as $forma => $dados) {
            $linhas[] = [
                $forma,
                (int)$dados['qtd'],
                number_format((float)$dados['total'], 2, ',', '.'),
                $grand_total > 0 ? number_format(((float)$dados['total'] / $grand_total) * 100, 1, ',', '.') . '%' : '0,0%',
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Resumo Financeiro',
            'cabecalhos' => ['Forma de Pagamento', 'Qtd. Vendas', 'Total (MZN)', '% do Faturamento'],
            'linhas' => $linhas,
        ];
    } elseif ($tipo === 'pedidos_qr') {
        $linhas_mesa = [];
        foreach ($pedidos_qr_mesa as $qm) {
            $linhas_mesa[] = [
                (string)($qm['mesa_numero'] ?? ''),
                (int)($qm['total_pedidos'] ?? 0),
                number_format((float)($qm['ticket_medio'] ?? 0), 2, ',', '.'),
                number_format((float)($qm['valor_total'] ?? 0), 2, ',', '.'),
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Pedidos por Mesa',
            'cabecalhos' => ['Mesa', 'Qtd. Pedidos', 'Ticket Medio (MZN)', 'Receita (MZN)'],
            'linhas' => $linhas_mesa,
        ];

        $linhas_hora = [];
        foreach ($pedidos_qr_hora as $qh) {
            $linhas_hora[] = [
                (string)($qh['hora'] ?? ''),
                (int)($qh['total_pedidos'] ?? 0),
                number_format((float)($qh['valor_total'] ?? 0), 2, ',', '.'),
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Pedidos por Hora',
            'cabecalhos' => ['Hora', 'Qtd. Pedidos', 'Receita (MZN)'],
            'linhas' => $linhas_hora,
        ];
    } elseif ($tipo === 'horarios_pico') {
        $linhas = [];
        foreach ($pico_horas as $hd) {
            if ((int)($hd['qtd_pedidos'] ?? 0) <= 0) {
                continue;
            }
            $linhas[] = [
                sprintf('%02d:00 - %02d:59', (int)$hd['hora'], (int)$hd['hora']),
                (int)$hd['qtd_pedidos'],
                number_format((float)$hd['receita'], 2, ',', '.'),
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Horarios de Pico',
            'cabecalhos' => ['Hora', 'Pedidos', 'Receita (MZN)'],
            'linhas' => $linhas,
        ];
    } elseif ($tipo === 'tempo_preparo') {
        $linhas = [];
        foreach ($prep_por_hora as $ph) {
            $linhas[] = [
                sprintf('%02d:00 - %02d:59', (int)$ph['hora'], (int)$ph['hora']),
                (int)$ph['qtd'],
                $relatorios_formatar_duracao($ph['avg_seg'] ?? 0),
            ];
        }
        $export_secoes[] = [
            'titulo' => 'Tempo de Preparo por Hora',
            'cabecalhos' => ['Hora', 'Pedidos', 'Tempo Medio'],
            'linhas' => $linhas,
        ];
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_' . $tipo . '_' . date('Ymd_His') . '.xls"');

    echo "<html><head><meta charset=\"UTF-8\">";
    echo "<style>
        body{font-family:Segoe UI,Arial,sans-serif;color:#0f172a;}
        .header{margin-bottom:24px;}
        .title{font-size:24px;font-weight:700;color:#1e293b;margin-bottom:6px;}
        .subtitle{font-size:14px;color:#475569;margin-bottom:2px;}
        .summary{border-collapse:collapse;margin:16px 0 24px 0;width:100%;}
        .summary td{border:1px solid #dbe2ea;padding:10px 12px;}
        .summary .label{background:#f8fafc;font-weight:700;width:220px;}
        .section-title{font-size:18px;font-weight:700;color:#1e293b;margin:24px 0 10px 0;}
        table.report{border-collapse:collapse;width:100%;margin-bottom:24px;}
        table.report th{background:#1d4ed8;color:#fff;font-weight:700;text-align:left;padding:10px;border:1px solid #cbd5e1;}
        table.report td{padding:10px;border:1px solid #dbe2ea;}
        .muted{color:#64748b;}
    </style></head><body>";
    echo '<div class="header">';
    echo '<div class="title">' . $relatorios_escape($restaurante_nome_atual) . '</div>';
    echo '<div class="subtitle">Relatório: ' . $relatorios_escape($export_titulo) . '</div>';
    echo '<div class="subtitle">Período: ' . $relatorios_escape($export_periodo) . '</div>';
    echo '<div class="subtitle">Gerado em: ' . $relatorios_escape($export_gerado_em) . '</div>';
    echo '</div>';

    echo '<table class="summary">';
    foreach ($export_resumo as $item_resumo) {
        echo '<tr>';
        echo '<td class="label">' . $relatorios_escape($item_resumo['label']) . '</td>';
        echo '<td>' . $relatorios_escape($item_resumo['valor']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    foreach ($export_secoes as $secao_export) {
        echo '<div class="section-title">' . $relatorios_escape($secao_export['titulo']) . '</div>';
        echo '<table class="report"><thead><tr>';
        foreach ($secao_export['cabecalhos'] as $cabecalho_export) {
            echo '<th>' . $relatorios_escape($cabecalho_export) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if (empty($secao_export['linhas'])) {
            echo '<tr><td colspan="' . count($secao_export['cabecalhos']) . '" class="muted">Nenhum dado encontrado para o período selecionado.</td></tr>';
        } else {
            foreach ($secao_export['linhas'] as $linha_export) {
                echo '<tr>';
                foreach ($linha_export as $celula_export) {
                    echo '<td>' . $relatorios_escape((string)$celula_export) . '</td>';
                }
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    echo '</body></html>';
    exit;
    }
}

if ($export === 'csv' && !isset($erro_dados)) {
    if (!$pode_exportar) {
        $erro_dados = 'Seu plano atual não permite exportação de relatórios.';
    } else {
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_' . $tipo . '_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Restaurante', $restaurante_nome_atual], ';');
    fputcsv($out, ['Relatorio', $tipos[$tipo] ?? ucfirst($tipo)], ';');
    fputcsv($out, ['Periodo', date('d/m/Y', strtotime($data_inicio)) . ' - ' . date('d/m/Y', strtotime($data_fim))], ';');
    fputcsv($out, ['Gerado em', date('d/m/Y H:i:s')], ';');
    fputcsv($out, ['Total de Registos', (string)($total_vendas ?? 0)], ';');
    fputcsv($out, ['Faturamento', 'MZN ' . number_format((float)($valor_total ?? 0), 2, ',', '.')], ';');
    fputcsv($out, ['Ticket Medio', 'MZN ' . number_format((float)($ticket_medio ?? 0), 2, ',', '.')], ';');
    fputcsv($out, [], ';');

    if ($tipo === 'vendas') {
        fputcsv($out, ['ID', 'Data/Hora', 'Cliente', 'Mesa', 'Valor (MZN)', 'Forma Pagamento', 'Atendido Por', 'Status'], ';');
        foreach ($vendas ?? [] as $v) {
            fputcsv($out, [
                '#' . str_pad((string)$v['id'], 4, '0', STR_PAD_LEFT),
                !empty($v['criado_em']) ? date('d/m/Y H:i', strtotime($v['criado_em'])) : '-',
                $v['cliente_nome'] ?? 'Consumidor',
                (isset($v['mesa_numero']) && $v['mesa_numero'] !== 'Sem mesa') ? 'Mesa ' . $v['mesa_numero'] : ($v['mesa_numero'] ?? '-'),
                number_format((float)($v['valor_total'] ?? $v['total'] ?? $v['total_final'] ?? 0), 2, ',', '.'),
                ucfirst((string)($v['forma_pagamento'] ?? 'Dinheiro')),
                $v['usuario_nome'] ?? '-',
                ucfirst((string)($v['status'] ?? 'Pendente')),
            ], ';');
        }
        fputcsv($out, [], ';');
        fputcsv($out, ['Resumo por Forma de Pagamento'], ';');
        fputcsv($out, ['Forma', 'Qtd. Vendas', 'Total (MZN)', '% do Faturamento'], ';');
        foreach ($por_pagamento as $forma => $dados) {
            fputcsv($out, [
                $forma,
                (int)$dados['qtd'],
                number_format((float)$dados['total'], 2, ',', '.'),
                $grand_total > 0 ? number_format(((float)$dados['total'] / $grand_total) * 100, 1, ',', '.') . '%' : '0,0%',
            ], ';');
        }
    } elseif ($tipo === 'produtos') {
        fputcsv($out, ['Produto', 'Categoria', 'Preço (MZN)', 'Quant. Vendida', 'Receita (MZN)'], ';');
        foreach ($produtos ?? [] as $p) {
            fputcsv($out, [
                $p['nome'] ?? '',
                $p['categoria'] ?? '-',
                number_format((float)($p['preco'] ?? 0), 2, ',', '.'),
                (int)($p['total_vendido'] ?? 0),
                number_format((float)($p['receita'] ?? 0), 2, ',', '.'),
            ], ';');
        }
    } elseif ($tipo === 'caixa') {
        fputcsv($out, ['Data Abertura', 'Data Fechamento', 'Valor Abertura (MZN)', 'Valor Fechamento (MZN)', 'Status'], ';');
        foreach ($movimentos ?? [] as $m) {
            fputcsv($out, [
                !empty($m['data_abertura']) ? date('d/m/Y H:i', strtotime($m['data_abertura'])) : '-',
                !empty($m['data_fechamento']) ? date('d/m/Y H:i', strtotime($m['data_fechamento'])) : '-',
                number_format((float)($m['valor_abertura'] ?? 0), 2, ',', '.'),
                number_format((float)($m['valor_fechamento'] ?? 0), 2, ',', '.'),
                ucfirst((string)($m['status'] ?? 'aberto')),
            ], ';');
        }
    } elseif ($tipo === 'financeiro') {
        fputcsv($out, ['Forma de Pagamento', 'Qtd. Vendas', 'Total (MZN)', '% do Faturamento'], ';');
        foreach ($por_pagamento as $forma => $dados) {
            fputcsv($out, [
                $forma,
                (int)$dados['qtd'],
                number_format((float)$dados['total'], 2, ',', '.'),
                $grand_total > 0 ? number_format(((float)$dados['total'] / $grand_total) * 100, 1, ',', '.') . '%' : '0,0%',
            ], ';');
        }
    } elseif ($tipo === 'pedidos_qr') {
        fputcsv($out, ['Pedidos por Mesa'], ';');
        fputcsv($out, ['Mesa', 'Qtd. Pedidos', 'Ticket Médio', 'Receita'], ';');
        foreach ($pedidos_qr_mesa as $qm) {
            fputcsv($out, [
                (string)($qm['mesa_numero'] ?? ''),
                (int)($qm['total_pedidos'] ?? 0),
                number_format((float)($qm['ticket_medio'] ?? 0), 2, ',', '.'),
                number_format((float)($qm['valor_total'] ?? 0), 2, ',', '.'),
            ], ';');
        }

        fputcsv($out, [], ';');
        fputcsv($out, ['Pedidos por Hora'], ';');
        fputcsv($out, ['Hora', 'Qtd. Pedidos', 'Receita'], ';');
        foreach ($pedidos_qr_hora as $qh) {
            fputcsv($out, [
                (string)($qh['hora'] ?? ''),
                (int)($qh['total_pedidos'] ?? 0),
                number_format((float)($qh['valor_total'] ?? 0), 2, ',', '.'),
            ], ';');
        }
    } elseif ($tipo === 'horarios_pico') {
        fputcsv($out, ['Hora', 'Pedidos', 'Receita'], ';');
        foreach ($pico_horas as $hd) {
            if ((int)($hd['qtd_pedidos'] ?? 0) <= 0) {
                continue;
            }
            fputcsv($out, [
                sprintf('%02d:00 - %02d:59', (int)$hd['hora'], (int)$hd['hora']),
                (int)$hd['qtd_pedidos'],
                number_format((float)$hd['receita'], 2, ',', '.'),
            ], ';');
        }
    } elseif ($tipo === 'tempo_preparo') {
        fputcsv($out, ['Hora', 'Pedidos', 'Tempo Medio'], ';');
        foreach ($prep_por_hora as $ph) {
            fputcsv($out, [
                sprintf('%02d:00 - %02d:59', (int)$ph['hora'], (int)$ph['hora']),
                (int)$ph['qtd'],
                $relatorios_formatar_duracao($ph['avg_seg'] ?? 0),
            ], ';');
        }
    }

    fclose($out);
    exit;
    }
}

// Preparar dados do usuário para exibição
$public_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$resolve_foto_url = function ($path) use ($public_base) {
    if (empty($path)) return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/') === 0) return $path;
    return $public_base . '/' . ltrim($path, '/');
};
$top_foto_url = $resolve_foto_url($_SESSION['foto'] ?? '');
$top_nome_usuario = $_SESSION['nome'] ?? 'Usuário';
$top_nome_partes = preg_split('/\s+/', trim($top_nome_usuario));
$top_iniciais = strtoupper(substr($top_nome_partes[0] ?? 'U', 0, 1) . substr($top_nome_partes[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B35;
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

        html {
            overflow-x: hidden;
        }

        .container-fluid {
            padding-left: 0;
            padding-right: 0;
            max-width: 100%;
        }

        .container-fluid>.row {
            margin-left: 0;
            margin-right: 0;
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
            width: calc(100vw - 280px);
            max-width: calc(100vw - 280px);
            padding: 0;
            min-height: 100vh;
            background: var(--bg);
            overflow-x: hidden;
        }

        .top-bar {
            background: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: relative;
            z-index: 100;
        }

        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .page-title i {
            color: var(--primary);
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .top-bar-date {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            color: #334155;
            font-weight: 700;
            white-space: nowrap;
            padding: 7px 12px;
            border-radius: 999px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }

        .top-bar-user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 5px 14px rgba(15, 23, 42, 0.06);
        }

        .top-bar-user-chip img,
        .top-bar-user-chip .chip-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .top-bar-user-chip .chip-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #c2410c, #b45309);
            color: #fff;
            font-weight: 700;
            font-size: 12px;
        }

        .top-bar-user-chip .chip-name {
            font-size: 15px;
            color: var(--text);
            font-weight: 700;
            line-height: 1;
        }

        .top-bar-user-chip .chip-role {
            font-size: 11px;
            color: #1d4ed8;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(29, 78, 216, 0.12);
        }

        .top-bar-user-chip .chip-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .content-area {
            padding: 24px;
        }

        .report-stat-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
            min-height: 126px;
        }

        .relatorios-filter-actions {
            align-items: stretch;
        }

        .report-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .report-stat-icon.tone-primary {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary);
        }

        .report-stat-icon.tone-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .report-stat-icon.tone-purple {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }

        .report-stat-icon.tone-teal {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }

        .report-stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.1;
            margin-bottom: 6px;
        }

        .report-stat-label {
            color: #475569;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            margin-bottom: 4px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: none;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .card-header {
            background: white;
            padding: 24px 28px;
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
            margin-bottom: 0;
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
            padding: 16px 24px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
            border: none;
        }

        .table tbody td {
            padding: 18px 24px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .table tbody tr:hover {
            background: rgba(255, 107, 53, 0.02);
        }

        .table-advanced thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: 0 1px 0 var(--border);
        }

        .table-advanced th:first-child,
        .table-advanced td:first-child {
            position: sticky;
            left: 0;
            z-index: 1;
            background: #fff;
        }

        .table-advanced thead th:first-child {
            z-index: 3;
            background: var(--bg);
        }

        .table-advanced tbody tr:nth-child(even) {
            background: rgba(15, 23, 42, 0.02);
        }

        .table-advanced tbody tr:hover {
            background: rgba(59, 130, 246, 0.08);
        }

        .table-advanced td,
        .table-advanced th {
            border-color: rgba(226, 232, 240, 0.85);
        }

        .pro-badge {
            margin-left: 10px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #7c2d12;
            background: linear-gradient(135deg, #fcd34d, #fbbf24);
        }

        .table-summary {
            display: flex;
            justify-content: flex-end;
            padding: 12px 18px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--text-light);
            background: #fafafa;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }

        .btn-success {
            background: var(--success);
            border: none;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-info {
            background: var(--info);
            border: none;
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
        }

        .form-select,
        .form-control {
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid var(--border);
            font-size: 14px;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
            }

            .content-area {
                padding: 20px;
            }

            .page-title {
                font-size: 18px;
            }

            #filtroRelatorio .col-md-3 {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                padding: 12px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .page-title {
                font-size: 17px;
                gap: 8px;
            }

            .top-bar-right {
                width: 100%;
                justify-content: space-between;
                gap: 8px;
                flex-wrap: wrap;
            }

            .top-bar-date {
                width: 100%;
                justify-content: flex-start;
                white-space: normal;
            }

            .top-bar-user-chip {
                width: 100%;
                padding: 7px 10px;
            }

            .top-bar-user-chip .chip-name {
                max-width: none;
                font-size: 13px;
            }

            .top-bar-user-chip .chip-role {
                font-size: 10px;
            }

            .content-area {
                padding: 12px;
            }

            .report-stat-card {
                padding: 14px;
                min-height: auto;
            }

            .report-stat-value {
                font-size: 24px;
            }

            .report-stat-label {
                font-size: 11px;
            }

            .relatorios-filter-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px !important;
            }

            .relatorios-filter-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .card-header {
                padding: 16px 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .card-title {
                font-size: 16px;
            }

            .card-body {
                padding: 0;
            }

            .form-select,
            .form-control {
                padding: 11px 14px;
                font-size: 13px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 14px;
                font-size: 12px;
                white-space: normal;
            }

            .badge {
                white-space: normal;
            }

            .alert {
                padding: 14px 16px;
            }
        }
    </style>
</head>

<body class="premium-ui <?php echo $tem_relatorios_avancados ? 'plan-advanced' : 'plan-basic'; ?>">
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
            <main class="main-content">
                <div class="top-bar">
                    <h1 class="page-title"><i class="fas fa-chart-bar"></i> Relatórios</h1>
                    <div class="top-bar-right">
                        <span class="top-bar-date"><i class="far fa-clock"></i> <?php echo date('d/m/Y H:i'); ?></span>
                        <div class="top-bar-user-chip">
                            <?php if (!empty($top_foto_url)): ?>
                                <img src="<?php echo htmlspecialchars($top_foto_url); ?>" alt="<?php echo htmlspecialchars($top_nome_usuario); ?>">
                            <?php else: ?>
                                <span class="chip-avatar"><?php echo htmlspecialchars($top_iniciais); ?></span>
                            <?php endif; ?>
                            <span class="chip-info">
                                <span class="chip-name"><?php echo htmlspecialchars($top_nome_usuario); ?></span>
                                <span class="chip-role"><?php echo $relatorios_escape($_SESSION['perfil'] ?? 'USER'); ?></span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="content-area">
                    <?php if (isset($erro_dados)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> Erro ao carregar dados: <?php echo htmlspecialchars($erro_dados); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3" id="filtroRelatorio">
                                <div class="col-md-3">
                                    <label class="form-label">Tipo de Relatório</label>
                                    <select name="tipo" class="form-select">
                                        <?php foreach ($tipos_visiveis as $key => $value): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $tipo === $key ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Período</label>
                                    <select name="periodo" class="form-select">
                                        <option value="7" <?php echo $periodo === '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                                        <option value="30" <?php echo $periodo === '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                        <option value="90" <?php echo $periodo === '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                                        <option value="all" <?php echo $periodo === 'all' ? 'selected' : ''; ?>>Todo o período</option>
                                        <option value="custom" <?php echo $periodo === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data inicial</label>
                                    <input type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($data_inicio); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data final</label>
                                    <input type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($data_fim); ?>">
                                </div>
                                <div class="col-12 d-flex gap-3 flex-wrap relatorios-filter-actions">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                                    <button type="button" class="btn btn-success" onclick="imprimirRelatorio()" <?php echo $pode_imprimir ? '' : 'disabled title="Disponível nos planos Profissional/Empresarial"'; ?>>
                                        <i class="fas fa-print"></i> Imprimir
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="exportarRelatorio()" <?php echo $pode_exportar ? '' : 'disabled title="Disponível nos planos Profissional/Empresarial"'; ?>>
                                        <i class="fas fa-file-export"></i> Exportar
                                    </button>
                                    <?php if ($pode_exportar_pdf): ?>
                                        <button type="button" class="btn btn-dark" onclick="exportarPDF()">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-dark" disabled title="PDF disponível apenas no plano Empresarial">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!$pode_imprimir || !$pode_exportar): ?>
                                        <span style="font-size:12px;color:var(--text-light);align-self:center">
                                            Exportação/impressão disponíveis no Profissional e Empresarial.
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- RESUMO UNIFICADO: Aparece para todos os tipos de relatório -->
                    <?php if ($tipo !== 'pedidos_qr'): ?>
                        <div class="row mb-4" id="relatorioResumoCards">
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Total de Venda</p>
                                            <h3 class="report-stat-value mb-0"><?php echo $total_vendas ?? 0; ?></h3>
                                        </div>
                                        <div class="report-stat-icon tone-success"><i class="fas fa-shopping-cart"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Faturamento</p>
                                            <h3 class="report-stat-value mb-0">MZN <?php echo number_format($valor_total ?? 0, 2, ',', '.'); ?></h3>
                                        </div>
                                        <div class="report-stat-icon tone-primary"><i class="fas fa-money-bill-wave"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Ticket Médio</p>
                                            <h3 class="report-stat-value mb-0">MZN <?php echo number_format($ticket_medio ?? 0, 2, ',', '.'); ?></h3>
                                        </div>
                                        <div class="report-stat-icon tone-purple"><i class="fas fa-chart-line"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Período</p>
                                            <h6 class="mb-0 report-stat-label"><?php echo date('d/m/Y', strtotime($data_inicio)); ?> - <?php echo date('d/m/Y', strtotime($data_fim)); ?></h6>
                                        </div>
                                        <div class="report-stat-icon tone-teal"><i class="fas fa-calendar"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($tipo === 'pedidos_qr'): ?>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Pedidos QR</p>
                                            <h3 class="report-stat-value mb-0"><?php echo (int)$resumo_qr['total_pedidos']; ?></h3>
                                        </div>
                                        <div class="report-stat-icon tone-success"><i class="fas fa-qrcode"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Receita QR</p>
                                            <h3 class="report-stat-value mb-0">MZN <?php echo number_format($resumo_qr['total_receita'], 2, ',', '.'); ?></h3>
                                        </div>
                                        <div class="report-stat-icon tone-primary"><i class="fas fa-money-bill-wave"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Mesas Ativas</p>
                                            <h3 class="report-stat-value mb-0"><?php echo (int)$resumo_qr['mesas_ativas']; ?></h3>
                                        </div>
                                        <div class="report-stat-icon tone-teal"><i class="fas fa-chair"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="report-stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="report-stat-label">Hora Pico</p>
                                            <h3 class="report-stat-value mb-0"><?php echo htmlspecialchars($resumo_qr['hora_pico']); ?></h3>
                                        </div>
                                        <div class="report-stat-icon tone-purple"><i class="fas fa-clock"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card" id="relatorioConteudoCard">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-list me-2"></i><?php echo $relatorios_escape($tipos_visiveis[$tipo] ?? $tipos[$tipo] ?? ucfirst($tipo)); ?>
                                <?php if ($tem_relatorios_avancados): ?>
                                    <span class="pro-badge">PRO</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($tipo === 'vendas'): ?>
                                <div class="table-responsive">
                                    <table class="<?php echo $table_class; ?>" id="tabelaRelatorio">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Data/Hora</th>
                                                <th>Cliente</th>
                                                <th>Mesa</th>
                                                <th>Valor</th>
                                                <th>Forma Pagamento</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vendas ?? [] as $v): ?>
                                                <tr>
                                                    <td>#<?php echo str_pad($v['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($v['criado_em'])); ?></td>
                                                    <td><?php echo $relatorios_escape($v['cliente_nome'] ?? 'Consumidor'); ?></td>
                                                    <td><?php echo $relatorios_escape((isset($v['mesa_numero']) && $v['mesa_numero'] !== 'Sem mesa') ? 'Mesa ' . $v['mesa_numero'] : ($v['mesa_numero'] ?? '-')); ?></td>
                                                    <td>MZN <?php echo number_format($v['valor_total'] ?? $v['total'] ?? $v['total_final'] ?? 0, 2, ',', '.'); ?></td>
                                                    <td><?php echo $relatorios_escape(ucfirst((string)($v['forma_pagamento'] ?? 'Dinheiro'))); ?></td>
                                                    <td><span class="badge bg-<?php echo ($v['status'] ?? 'PENDENTE') === 'PAGO' ? 'success' : 'warning'; ?>"><?php echo $relatorios_escape(ucfirst((string)($v['status'] ?? 'Pendente'))); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($vendas ?? [])): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">Nenhuma venda encontrada neste período</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($tipo === 'produtos'): ?>
                                <div class="table-responsive">
                                    <table class="<?php echo $table_class; ?>" id="tabelaRelatorio">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Categoria</th>
                                                <th>Preço</th>
                                                <th>Quant. Vendida</th>
                                                <th>Receita</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($produtos ?? [] as $p): ?>
                                                <tr>
                                                    <td><?php echo $relatorios_escape($p['nome'] ?? ''); ?></td>
                                                    <td><?php echo $relatorios_escape($p['categoria'] ?? '-'); ?></td>
                                                    <td>MZN <?php echo number_format($p['preco'], 2, ',', '.'); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $p['total_vendido']; ?></span></td>
                                                    <td>MZN <?php echo number_format($p['receita'], 2, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($produtos ?? [])): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4">Nenhum produto encontrado</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($tipo === 'caixa'): ?>
                                <div class="table-responsive">
                                    <table class="<?php echo $table_class; ?>" id="tabelaRelatorio">
                                        <thead>
                                            <tr>
                                                <th>Data Abertura</th>
                                                <th>Data Fechamento</th>
                                                <th>Valor Abertura</th>
                                                <th>Valor Fechamento</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movimentos ?? [] as $m): ?>
                                                <tr>
                                                    <td><?php echo !empty($m['data_abertura']) ? date('d/m/Y H:i', strtotime($m['data_abertura'])) : '-'; ?></td>
                                                    <td><?php echo !empty($m['data_fechamento']) ? date('d/m/Y H:i', strtotime($m['data_fechamento'])) : '-'; ?></td>
                                                    <td>MZN <?php echo number_format($m['valor_abertura'] ?? 0, 2, ',', '.'); ?></td>
                                                    <td>MZN <?php echo number_format($m['valor_fechamento'] ?? 0, 2, ',', '.'); ?></td>
                                                    <td><span class="badge bg-<?php echo ($m['status'] ?? 'aberto') === 'fechado' ? 'secondary' : 'success'; ?>"><?php echo $relatorios_escape(ucfirst((string)($m['status'] ?? 'aberto'))); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($movimentos ?? [])): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4">Nenhum caixa encontrado</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($tipo === 'financeiro'): ?>
                                <?php
                                $por_pagamento = [];
                                foreach ($vendas ?? [] as $v) {
                                    $forma = strtoupper($v['forma_pagamento'] ?? 'DINHEIRO');
                                    if (!isset($por_pagamento[$forma])) $por_pagamento[$forma] = ['qtd' => 0, 'total' => 0];
                                    $por_pagamento[$forma]['qtd']++;
                                    $por_pagamento[$forma]['total'] += floatval($v['valor_total'] ?? $v['total'] ?? $v['total_final'] ?? 0);
                                }
                                arsort($por_pagamento);
                                $grand_total = array_sum(array_column($por_pagamento, 'total'));
                                ?>
                                <div class="row g-3 p-3 mb-2">
                                    <?php foreach ($por_pagamento as $forma => $dados): ?>
                                        <div class="col-md-3">
                                            <div class="report-stat-card">
                                                <div class="report-stat-label"><?php echo htmlspecialchars($forma); ?></div>
                                                <div class="report-stat-value">MZN <?php echo number_format($dados['total'], 2, ',', '.'); ?></div>
                                                <small class="text-muted"><?php echo (int)$dados['qtd']; ?> venda(s) &mdash; <?php echo $grand_total > 0 ? number_format($dados['total'] / $grand_total * 100, 1, ',', '.') : '0,0'; ?>%</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="table-responsive">
                                    <table class="<?php echo $table_class; ?>" id="tabelaRelatorio">
                                        <thead>
                                            <tr>
                                                <th>Forma de Pagamento</th>
                                                <th>Qtd. Vendas</th>
                                                <th>Total</th>
                                                <th>% do Faturamento</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($por_pagamento as $forma => $dados): ?>
                                                <tr>
                                                    <td><span class="badge bg-primary"><?php echo $relatorios_escape($forma); ?></span></td>
                                                    <td><?php echo (int)$dados['qtd']; ?></td>
                                                    <td>MZN <?php echo number_format($dados['total'], 2, ',', '.'); ?></td>
                                                    <td><?php echo $grand_total > 0 ? number_format($dados['total'] / $grand_total * 100, 1, ',', '.') : '0,0'; ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($por_pagamento)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">Nenhuma venda encontrada neste período</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($tipo === 'pedidos_qr'): ?>
                                <div class="p-3">
                                    <h6 class="mb-3"><i class="fas fa-chair me-2"></i>Pedidos por Mesa</h6>
                                    <div class="table-responsive mb-4">
                                        <table class="<?php echo $table_class; ?>" id="tabelaRelatorio">
                                            <thead>
                                                <tr>
                                                    <th>Mesa</th>
                                                    <th>Qtd. Pedidos</th>
                                                    <th>Ticket Médio</th>
                                                    <th>Receita</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pedidos_qr_mesa as $qm): ?>
                                                    <tr>
                                                        <td><?php echo $relatorios_escape((string)$qm['mesa_numero']); ?></td>
                                                        <td><span class="badge bg-primary"><?php echo (int)$qm['total_pedidos']; ?></span></td>
                                                        <td>MZN <?php echo number_format((float)$qm['ticket_medio'], 2, ',', '.'); ?></td>
                                                        <td>MZN <?php echo number_format((float)$qm['valor_total'], 2, ',', '.'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($pedidos_qr_mesa)): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-4">Nenhum pedido QR encontrado neste período</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Pedidos por Hora</h6>
                                    <div class="table-responsive">
                                        <table class="<?php echo $table_class; ?>">
                                            <thead>
                                                <tr>
                                                    <th>Hora</th>
                                                    <th>Qtd. Pedidos</th>
                                                    <th>Receita</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pedidos_qr_hora as $qh): ?>
                                                    <tr>
                                                        <td><?php echo $relatorios_escape((string)$qh['hora']); ?></td>
                                                        <td><span class="badge bg-info"><?php echo (int)$qh['total_pedidos']; ?></span></td>
                                                        <td>MZN <?php echo number_format((float)$qh['valor_total'], 2, ',', '.'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($pedidos_qr_hora)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-4">Nenhum dado por hora no período selecionado</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            <?php elseif ($tipo === 'horarios_pico'): ?>
                                <div class="p-4">
                                    <h6 class="mb-4"><i class="fas fa-clock me-2 text-warning"></i>Volume de Pedidos por Hora do Dia</h6>
                                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;height:180px;padding-bottom:8px;">
                                        <?php foreach ($pico_horas as $hd): ?>
                                            <?php
                                            $pct = $pico_max > 0 ? round($hd['qtd_pedidos'] / $pico_max * 100) : 0;
                                            $barColor = $pct >= 80 ? '#ef4444' : ($pct >= 50 ? '#f59e0b' : '#3b82f6');
                                            ?>
                                            <div style="flex:1;min-width:22px;display:flex;flex-direction:column;align-items:center;gap:4px" title="<?php echo $relatorios_escape($hd['hora']); ?>h – <?php echo (int)$hd['qtd_pedidos']; ?> pedidos">
                                                <?php if ($hd['qtd_pedidos'] > 0): ?>
                                                    <span style="font-size:10px;font-weight:700;color:#475569"><?php echo (int)$hd['qtd_pedidos']; ?></span>
                                                <?php else: ?>
                                                    <span style="font-size:10px">&nbsp;</span>
                                                <?php endif; ?>
                                                <div style="width:100%;background:<?php echo $barColor; ?>;border-radius:6px 6px 0 0;height:<?php echo max(4, $pct); ?>%;transition:height 0.3s"></div>
                                                <span style="font-size:9px;color:#94a3b8;white-space:nowrap"><?php echo $relatorios_escape(sprintf('%02d', $hd['hora'])); ?>h</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="mt-3" style="font-size:13px;color:#475569">
                                        <i class="fas fa-fire text-danger me-1"></i>
                                        Horário de pico: <strong><?php echo $relatorios_escape(sprintf('%02d', $pico_best)); ?>h – <?php echo $relatorios_escape(sprintf('%02d', $pico_best + 1)); ?>h</strong>
                                        com <strong><?php echo (int)$pico_horas[$pico_best]['qtd_pedidos']; ?></strong> pedidos
                                    </p>
                                    <div class="table-responsive mt-4">
                                        <table class="<?php echo $table_class; ?>" id="tabelaRelatorio">
                                            <thead>
                                                <tr>
                                                    <th>Hora</th>
                                                    <th>Pedidos</th>
                                                    <th>Receita</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pico_horas as $hd): if ($hd['qtd_pedidos'] <= 0) continue; ?>
                                                    <tr>
                                                        <td><?php echo $relatorios_escape(sprintf('%02d:00 – %02d:59', $hd['hora'], $hd['hora'])); ?></td>
                                                        <td><span class="badge bg-primary"><?php echo (int)$hd['qtd_pedidos']; ?></span></td>
                                                        <td>MZN <?php echo number_format($hd['receita'], 2, ',', '.'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (array_sum(array_column($pico_horas, 'qtd_pedidos')) === 0): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-4">Nenhum pedido no período</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            <?php elseif ($tipo === 'tempo_preparo'): ?>
                                <div class="p-4">
                                    <?php if ($total_vendas === 0): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                            <p>Nenhum dado de tempo de preparo disponível.<br>
                                                <small class="text-muted">Execute o SQL em <code>schema_melhorias2.sql</code> para adicionar a coluna e os pedidos passarão a registar o início do preparo.</small>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row g-3 mb-4">
                                            <div class="col-md-3">
                                                <div class="report-stat-card">
                                                    <div class="report-stat-icon tone-primary mb-2"><i class="fas fa-hourglass-start"></i></div>
                                                    <div class="report-stat-label">Espera Média (fila)</div>
                                                    <div class="report-stat-value"><?php echo $relatorios_escape($relatorios_formatar_duracao($prep_resumo['avg_espera_seg'] ?? 0)); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="report-stat-card">
                                                    <div class="report-stat-icon tone-success mb-2"><i class="fas fa-fire"></i></div>
                                                    <div class="report-stat-label">Preparo Médio</div>
                                                    <div class="report-stat-value"><?php echo $relatorios_escape($relatorios_formatar_duracao($prep_resumo['avg_preparo_seg'] ?? 0)); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="report-stat-card">
                                                    <div class="report-stat-icon tone-teal mb-2"><i class="fas fa-bolt"></i></div>
                                                    <div class="report-stat-label">Preparo Mínimo</div>
                                                    <div class="report-stat-value"><?php echo $relatorios_escape($relatorios_formatar_duracao($prep_resumo['min_preparo_seg'] ?? 0)); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="report-stat-card">
                                                    <div class="report-stat-icon tone-purple mb-2"><i class="fas fa-exclamation-circle"></i></div>
                                                    <div class="report-stat-label">Preparo Máximo</div>
                                                    <div class="report-stat-value"><?php echo $relatorios_escape($relatorios_formatar_duracao($prep_resumo['max_preparo_seg'] ?? 0)); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="mb-3"><i class="fas fa-chart-line me-2 text-info"></i>Tempo de Preparo por Hora do Dia</h6>
                                        <div class="table-responsive">
                                            <table class="<?php echo $table_class; ?>" id="tabelaRelatorio">
                                                <thead>
                                                    <tr>
                                                        <th>Hora</th>
                                                        <th>Pedidos</th>
                                                        <th>Tempo Médio</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($prep_por_hora as $ph): ?>
                                                        <?php $avgSeg = (float)($ph['avg_seg'] ?? 0);
                                                        $avgM = $avgSeg / 60;
                                                        $cls = $avgM >= 20 ? 'danger' : ($avgM >= 12 ? 'warning' : 'success'); ?>
                                                        <tr>
                                                            <td><?php echo $relatorios_escape(sprintf('%02d:00 – %02d:59', (int)$ph['hora'], (int)$ph['hora'])); ?></td>
                                                            <td><?php echo (int)$ph['qtd']; ?></td>
                                                            <td><span class="badge bg-<?php echo $cls; ?>"><?php echo $relatorios_escape($relatorios_formatar_duracao($avgSeg)); ?></span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-chart-pie fa-4x text-muted mb-3"></i>
                                    <p class="text-muted">Selecione um tipo de relatório acima</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function construirUrlRelatorio(extraParams) {
            var form = document.getElementById('filtroRelatorio');
            var params = new URLSearchParams(new FormData(form));

            Object.keys(extraParams || {}).forEach(function(key) {
                params.set(key, extraParams[key]);
            });

            return 'relatorios.php?' + params.toString();
        }

        const canExportRelatorio = <?php echo $pode_exportar ? 'true' : 'false'; ?>;
        const canPrintRelatorio = <?php echo $pode_imprimir ? 'true' : 'false'; ?>;
        const canExportPdf = <?php echo $pode_exportar_pdf ? 'true' : 'false'; ?>;

        function exportarRelatorio() {
            if (!canExportRelatorio) {
                alert('Seu plano atual não permite exportação de relatórios.');
                return;
            }
            window.location.href = construirUrlRelatorio({
                export: 'excel'
            });
        }

        function imprimirRelatorio() {
            if (!canPrintRelatorio) {
                alert('Seu plano atual não permite impressão de relatórios.');
                return;
            }
            var tabela = document.getElementById('tabelaRelatorio');
            var card = document.getElementById('relatorioConteudoCard');
            var resumo = document.getElementById('relatorioResumoCards');
            var printWindow = window.open('', '_blank', 'width=1200,height=900');

            if (!printWindow || !tabela || !card) {
                window.print();
                return;
            }

            var titulo = <?php echo json_encode($tipos_visiveis[$tipo] ?? $tipos[$tipo] ?? ucfirst($tipo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var restaurante = <?php echo json_encode($restaurante_nome_atual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var periodo = <?php echo json_encode(date('d/m/Y', strtotime($data_inicio)) . ' - ' . date('d/m/Y', strtotime($data_fim)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var resumoHtml = resumo ? resumo.innerHTML : '';
            var tabelaHtml = card.innerHTML;

            printWindow.document.write('<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>' + titulo + '</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon"><style>' +
                'body{font-family:Segoe UI,Arial,sans-serif;padding:32px;color:#0f172a;background:#fff;}' +
                '.header{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-bottom:24px;padding-bottom:18px;border-bottom:2px solid #e2e8f0;}' +
                '.brand{max-width:70%;}.brand h1{margin:0 0 6px;font-size:28px;color:#0f172a;}.brand p{margin:0;color:#475569;font-size:14px;}' +
                '.meta{text-align:right;font-size:13px;color:#475569;}' +
                '.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:24px 0;}' +
                '.summary .col-md-3{width:auto;flex:none;}' +
                '.report-stat-card{border:1px solid #e2e8f0;border-radius:18px;padding:16px;background:#fff;box-shadow:none;break-inside:avoid;}' +
                '.report-stat-label{font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b;margin:0 0 8px;}' +
                '.report-stat-value{font-size:28px;font-weight:800;color:#0f172a;margin:0;line-height:1.1;}' +
                '.report-stat-icon{display:none;}' +
                '.card{border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:none;}' +
                '.card-header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;}' +
                '.card-title{margin:0;font-size:22px;color:#0f172a;}' +
                '.card-body{padding:0;}' +
                'table{width:100%;border-collapse:collapse;}' +
                'th,td{padding:12px 14px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:13px;}' +
                'th{background:#f8fafc;color:#334155;font-weight:800;text-transform:uppercase;}' +
                '.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;}' +
                '.bg-success{background:#dcfce7;color:#166534;}.bg-warning{background:#fef3c7;color:#92400e;}.bg-danger{background:#fee2e2;color:#991b1b;}.bg-info,.bg-primary{background:#dbeafe;color:#1d4ed8;}' +
                '.table-responsive{overflow:visible;}' +
                '@media print{body{padding:18px;}.summary{grid-template-columns:repeat(2,minmax(0,1fr));}}' +
                '</style></head><body>' +
                '<div class="header"><div class="brand"><h1>' + restaurante + '</h1><p>' + titulo + '</p><p>Período: ' + periodo + '</p></div><div class="meta"><div>Gerado em</div><strong>' + new Date().toLocaleString('pt-BR') + '</strong></div></div>' +
                (resumoHtml ? '<div class="summary">' + resumoHtml + '</div>' : '') +
                '<div class="report-content">' + tabelaHtml + '</div>' +
                '</body></html>');

            printWindow.document.close();
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
            }, 250);
        }

        function exportarPDF() {
            if (!canExportPdf) {
                alert('Seu plano atual não permite exportação em PDF.');
                return;
            }
            imprimirRelatorio();
        }

        function atualizarResumoTabela() {
            const table = document.getElementById('tabelaRelatorio');
            const card = document.getElementById('relatorioConteudoCard');
            if (!table || !card || !table.classList.contains('table-advanced')) return;
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            const total = tbody.querySelectorAll('tr').length;
            let footer = card.querySelector('.table-summary');
            if (!footer) {
                footer = document.createElement('div');
                footer.className = 'table-summary';
                card.appendChild(footer);
            }
            footer.textContent = 'Total de registros: ' + total;
        }

        document.addEventListener('DOMContentLoaded', atualizarResumoTabela);
    </script>
</body>

</html>

