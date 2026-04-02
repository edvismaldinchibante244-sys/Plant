 <?php
    // Proteção da página
    include_once __DIR__ . '/../config/auth_check.php';
    include_once __DIR__ . '/../config/database.php';
    include_once __DIR__ . '/../config/plano_check.php';
    include_once __DIR__ . '/../config/presenca_online.php';
    include_once __DIR__ . '/../config/restaurante_context.php';
    include_once __DIR__ . '/../Model/Produto.php';
    include_once __DIR__ . '/../Model/Venda.php';
    include_once __DIR__ . '/../Model/Caixa.php';
    include_once __DIR__ . '/../Model/Mesa.php';

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    // Instanciar classes
    $venda   = new Venda($db);
    $produto = new Produto($db);
    $caixa   = new Caixa($db);
    $mesa    = new Mesa($db);

    // Verificar se é Super Admin
    $isSuperAdmin = isset($_SESSION['super_admin']) && $_SESSION['super_admin'] == 1;

    // Se for Super Admin, verificar se selecionou um restaurante
    if ($isSuperAdmin) {
        if (isset($_POST['selecionar_restaurante']) && isset($_POST['restaurante_id'])) {
            $_SESSION['restaurante_selecionado'] = intval($_POST['restaurante_id']);
            $_SESSION['restaurante_id'] = $_SESSION['restaurante_selecionado'];
            $_SESSION['restaurante_base_id'] = $_SESSION['restaurante_selecionado'];
            unset($_SESSION['matriz_id'], $_SESSION['filial_id']);
            header("Location: dashboard.php");
            exit;
        }

        if (isset($_SESSION['restaurante_selecionado']) && $_SESSION['restaurante_selecionado'] > 0) {
            $_SESSION['restaurante_id'] = $_SESSION['restaurante_selecionado'];
            $_SESSION['restaurante_base_id'] = $_SESSION['restaurante_selecionado'];
        }
    }

    // Se for Super Admin, verificar se selecionou um restaurante primeiro
    if ($isSuperAdmin) {
        if (!isset($_SESSION['restaurante_selecionado']) || $_SESSION['restaurante_selecionado'] == 0) {
            header("Location: admin.php");
            exit;
        }
        // Usar o restaurante selecionado
        $_SESSION['restaurante_id'] = $_SESSION['restaurante_selecionado'];
        $_SESSION['restaurante_base_id'] = $_SESSION['restaurante_selecionado'];
    }

    $rid = session_restaurante_contexto_id();
    $planoRid = session_restaurante_capability_id();
    $authRid = session_restaurante_auth_id();
    $featureRid = $planoRid > 0 ? $planoRid : $rid;
    $temPedidosOnline = $featureRid > 0 && plano_tem_funcionalidade_db($featureRid, 'pedidos_online');

    // Se não tem restaurante_id válido (para usuários normais), redirecionar
    if ($rid <= 0) {
        header("Location: index.php?erro=sem_restaurante");
        exit;
    }

    $perfil = strtoupper($_SESSION['perfil'] ?? 'USER');
    if ($perfil === 'GARÇOM') $perfil = 'GARCOM';

    // Sincronizar foto do usuário logado para garantir exibição correta no dashboard
    if (!empty($_SESSION['usuario_id'])) {
        $stmt_user_foto = $db->prepare("SELECT foto, nome, perfil FROM usuarios WHERE id = ? AND restaurante_id = ? LIMIT 1");
        $stmt_user_foto->execute([intval($_SESSION['usuario_id']), $authRid]);
        $dados_usuario_sessao = $stmt_user_foto->fetch(PDO::FETCH_ASSOC);
        if ($dados_usuario_sessao) {
            $_SESSION['foto'] = $dados_usuario_sessao['foto'] ?? '';
            $_SESSION['nome'] = $dados_usuario_sessao['nome'] ?? ($_SESSION['nome'] ?? 'Usuário');
            $_SESSION['perfil'] = $dados_usuario_sessao['perfil'] ?? ($_SESSION['perfil'] ?? 'USER');
            $perfil = strtoupper($_SESSION['perfil'] ?? 'USER');
            if ($perfil === 'GARÇOM') $perfil = 'GARCOM';
        }
    }

    $perfil_lower = strtolower($perfil);
    $usuario_logado_id = intval($_SESSION['usuario_id'] ?? 0);

    // --- Dados do dashboard ---
    $total_hoje = $venda->vendasHoje($rid);
    $qtd_hoje   = $venda->contarVendasHoje($rid);
    $caixa_aberto = $caixa->buscarAberto($rid);
    $total_produtos = $produto->contarAtivos($rid);

    $stmt_mesas = $mesa->listar($rid);
    $todas_mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);
    $mesas_ocupadas = 0;
    $total_mesas    = count($todas_mesas);
    foreach ($todas_mesas as $m) {
        if ($m['status'] == 'OCUPADA') $mesas_ocupadas++;
    }

    $stmt_estoque = $produto->estoqueBaixo($rid);
    $estoque_baixo = $stmt_estoque->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ultimas = $venda->ultimasVendas($rid, 8);
    $ultimas_vendas = $stmt_ultimas->fetchAll(PDO::FETCH_ASSOC);

    $stmt_semana = $venda->vendasPorDia($rid, 7);
    $vendas_semana = $stmt_semana->fetchAll(PDO::FETCH_ASSOC);

    $dados_plano = plano_get_dados($planoRid);
    $plano_atual = plano_normalizar_nome($dados_plano['plano_nome'] ?? 'BASICO');
    $data_fim_plano = $dados_plano['data_fim'] ?? date('Y-m-d');
    $dias_restantes = floor((strtotime($data_fim_plano) - time()) / (60 * 60 * 24));
    $recursos_plano_atual = plano_get_resumo_recursos($plano_atual);

    $isAdminDashboard = !in_array($perfil, ['GARCOM', 'CAIXA'], true);
    $grafico_min_pontos = $isAdminDashboard ? 14 : 7;
    $dados_grafico = $vendas_semana;
    $garcom_dashboard = [
        'total_pedidos' => 0,
        'mesas_total' => 0,
        'mesas_ocupadas' => 0,
        'valor_pedidos' => 0.0,
        'pendentes' => 0,
        'preparados' => 0,
        'entregues' => 0,
    ];
    $caixa_dashboard = [
        'contas_abertas' => 0,
        'total_aberto' => 0.0,
        'caixa_aberto' => (bool)$caixa_aberto,
        'total_turno' => 0.0,
    ];
    $dashboard_reservas = [
        'total_ativas' => 0,
        'pendentes' => 0,
        'confirmadas' => 0,
        'ultima_pendente' => null,
    ];

    try {
        $stmt_dashboard_reservas = $db->prepare("
            SELECT
                COUNT(*) AS total_ativas,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
                SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) AS confirmadas
            FROM reservas
            WHERE restaurante_id = :restaurante_id
              AND data_reserva >= CURDATE()
              AND status IN ('pendente', 'confirmado')
        ");
        $stmt_dashboard_reservas->execute([':restaurante_id' => $rid]);
        $resumo_dashboard_reservas = $stmt_dashboard_reservas->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt_dashboard_reservas_ultima = $db->prepare("
            SELECT
                r.id,
                r.nome_cliente,
                r.data_reserva,
                r.hora_reserva,
                r.mesa_atribuida,
                m.numero AS mesa_numero,
                r.criado_em
            FROM reservas r
            LEFT JOIN mesas m ON m.id = r.mesa_atribuida
            WHERE r.restaurante_id = :restaurante_id
              AND r.data_reserva >= CURDATE()
              AND r.status = 'pendente'
            ORDER BY r.id DESC
            LIMIT 1
        ");
        $stmt_dashboard_reservas_ultima->execute([':restaurante_id' => $rid]);
        $ultimaReservaDashboard = $stmt_dashboard_reservas_ultima->fetch(PDO::FETCH_ASSOC) ?: null;

        $dashboard_reservas = [
            'total_ativas' => (int)($resumo_dashboard_reservas['total_ativas'] ?? 0),
            'pendentes' => (int)($resumo_dashboard_reservas['pendentes'] ?? 0),
            'confirmadas' => (int)($resumo_dashboard_reservas['confirmadas'] ?? 0),
            'ultima_pendente' => $ultimaReservaDashboard ? [
                'id' => (int)($ultimaReservaDashboard['id'] ?? 0),
                'nome_cliente' => (string)($ultimaReservaDashboard['nome_cliente'] ?? ''),
                'data_reserva' => (string)($ultimaReservaDashboard['data_reserva'] ?? ''),
                'hora_reserva' => (string)($ultimaReservaDashboard['hora_reserva'] ?? ''),
                'mesa_atribuida' => (int)($ultimaReservaDashboard['mesa_atribuida'] ?? 0),
                'mesa_numero' => $ultimaReservaDashboard['mesa_numero'] ?? null,
                'criado_em' => (string)($ultimaReservaDashboard['criado_em'] ?? ''),
            ] : null,
        ];
    } catch (Throwable $e) {
        error_log('[DASHBOARD][RESERVAS] ' . $e->getMessage());
    }

    if (!$isAdminDashboard && $usuario_logado_id > 0) {
        if ($perfil === 'GARCOM') {
            $stmt_pedidos_usuario_hoje = $db->prepare("SELECT COUNT(*) AS qtd_pedidos, COALESCE(SUM(total), 0) AS total_pedidos FROM pedidos WHERE restaurante_id = :rid AND garcom_id = :uid AND DATE(criado_em) = CURDATE() AND status <> 'CANCELADO'");
            $stmt_pedidos_usuario_hoje->execute([':rid' => $rid, ':uid' => $usuario_logado_id]);
            $pedidos_usuario_hoje = $stmt_pedidos_usuario_hoje->fetch(PDO::FETCH_ASSOC) ?: [];

            $total_hoje = floatval($pedidos_usuario_hoje['total_pedidos'] ?? 0);
            $qtd_hoje = intval($pedidos_usuario_hoje['qtd_pedidos'] ?? 0);

            $stmt_ultimas = $db->prepare("SELECT p.numero_pedido AS numero_fatura,
                                                 p.criado_em,
                                                 m.numero AS mesa_numero,
                                                 p.total AS total_final,
                                                 'PEDIDO' AS forma_pagamento,
                                                 p.status
                                          FROM pedidos p
                                          LEFT JOIN mesas m ON p.mesa_id = m.id
                                          WHERE p.restaurante_id = :rid AND p.garcom_id = :uid
                                          ORDER BY p.criado_em DESC
                                          LIMIT 8");
            $stmt_ultimas->bindValue(':rid', $rid, PDO::PARAM_INT);
            $stmt_ultimas->bindValue(':uid', $usuario_logado_id, PDO::PARAM_INT);
            $stmt_ultimas->execute();
            $ultimas_vendas = $stmt_ultimas->fetchAll(PDO::FETCH_ASSOC);

            $stmt_semana = $db->prepare("SELECT DATE(criado_em) AS data, COALESCE(SUM(total), 0) AS total
                                         FROM pedidos
                                         WHERE restaurante_id = :rid
                                         AND garcom_id = :uid
                                         AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                         AND status <> 'CANCELADO'
                                         GROUP BY DATE(criado_em)
                                         ORDER BY data ASC");
            $stmt_semana->bindValue(':rid', $rid, PDO::PARAM_INT);
            $stmt_semana->bindValue(':uid', $usuario_logado_id, PDO::PARAM_INT);
            $stmt_semana->execute();
            $vendas_semana = $stmt_semana->fetchAll(PDO::FETCH_ASSOC);
            $dados_grafico = $vendas_semana;

                        $stmt_garcom_resumo = $db->prepare("SELECT COUNT(*) AS total_pedidos,
                                                                                                             COALESCE(SUM(total), 0) AS valor_pedidos,
                                                                                                             SUM(CASE WHEN status IN ('NOVO','PENDENTE') THEN 1 ELSE 0 END) AS pendentes,
                                                                                                             SUM(CASE WHEN status IN ('PREPARANDO','PRONTO') THEN 1 ELSE 0 END) AS preparados,
                                                                                                             SUM(CASE WHEN status = 'ENTREGUE' THEN 1 ELSE 0 END) AS entregues
                                                                                                FROM pedidos
                                                                                                WHERE restaurante_id = :rid
                                                                                                    AND garcom_id = :uid
                                                                                                    AND DATE(criado_em) = CURDATE()
                                                                                                    AND status <> 'CANCELADO'");
                        $stmt_garcom_resumo->execute([':rid' => $rid, ':uid' => $usuario_logado_id]);
                        $garcom_resumo = $stmt_garcom_resumo->fetch(PDO::FETCH_ASSOC) ?: [];

                        $stmt_garcom_mesas = $db->prepare("SELECT COUNT(*) AS total,
                                                                                                            SUM(CASE WHEN status = 'OCUPADA' THEN 1 ELSE 0 END) AS ocupadas
                                                                                             FROM mesas
                                                                                             WHERE restaurante_id = :rid
                                                                                                 AND (garcom_id = :uid OR garcom_id IS NULL)");
                        $stmt_garcom_mesas->execute([':rid' => $rid, ':uid' => $usuario_logado_id]);
                        $garcom_mesas_resumo = $stmt_garcom_mesas->fetch(PDO::FETCH_ASSOC) ?: [];

                        $garcom_dashboard = [
                                'total_pedidos' => intval($garcom_resumo['total_pedidos'] ?? 0),
                                'mesas_total' => intval($garcom_mesas_resumo['total'] ?? 0),
                                'mesas_ocupadas' => intval($garcom_mesas_resumo['ocupadas'] ?? 0),
                                'valor_pedidos' => floatval($garcom_resumo['valor_pedidos'] ?? 0),
                                'pendentes' => intval($garcom_resumo['pendentes'] ?? 0),
                                'preparados' => intval($garcom_resumo['preparados'] ?? 0),
                                'entregues' => intval($garcom_resumo['entregues'] ?? 0),
                        ];
        } else {
            $stmt_vendas_usuario_hoje = $db->prepare("SELECT COUNT(*) AS qtd_vendas, COALESCE(SUM(total_final), 0) AS total_vendas FROM vendas WHERE restaurante_id = :rid AND usuario_id = :uid AND DATE(criado_em) = CURDATE() AND status = 'PAGO'");
            $stmt_vendas_usuario_hoje->execute([':rid' => $rid, ':uid' => $usuario_logado_id]);
            $vendas_usuario_hoje = $stmt_vendas_usuario_hoje->fetch(PDO::FETCH_ASSOC) ?: [];

            $total_hoje = floatval($vendas_usuario_hoje['total_vendas'] ?? 0);
            $qtd_hoje = intval($vendas_usuario_hoje['qtd_vendas'] ?? 0);

            $stmt_ultimas = $db->prepare("SELECT v.*, u.nome as usuario_nome, m.numero as mesa_numero
                                          FROM vendas v
                                          LEFT JOIN usuarios u ON v.usuario_id = u.id
                                          LEFT JOIN mesas m ON v.mesa_id = m.id
                                          WHERE v.restaurante_id = :rid AND v.usuario_id = :uid
                                          ORDER BY v.criado_em DESC
                                          LIMIT 8");
            $stmt_ultimas->bindValue(':rid', $rid, PDO::PARAM_INT);
            $stmt_ultimas->bindValue(':uid', $usuario_logado_id, PDO::PARAM_INT);
            $stmt_ultimas->execute();
            $ultimas_vendas = $stmt_ultimas->fetchAll(PDO::FETCH_ASSOC);

            $stmt_semana = $db->prepare("SELECT DATE(criado_em) AS data, COALESCE(SUM(total_final), 0) AS total
                                         FROM vendas
                                         WHERE restaurante_id = :rid
                                         AND usuario_id = :uid
                                         AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                         AND status = 'PAGO'
                                         GROUP BY DATE(criado_em)
                                         ORDER BY data ASC");
            $stmt_semana->bindValue(':rid', $rid, PDO::PARAM_INT);
            $stmt_semana->bindValue(':uid', $usuario_logado_id, PDO::PARAM_INT);
            $stmt_semana->execute();
            $vendas_semana = $stmt_semana->fetchAll(PDO::FETCH_ASSOC);
            $dados_grafico = $vendas_semana;

            $stmt_caixa_abertas = $db->prepare("SELECT COUNT(*) AS contas_abertas, COALESCE(SUM(total), 0) AS total_aberto
                                                FROM pedidos
                                                WHERE restaurante_id = :rid
                                                  AND status NOT IN ('PAGO','CANCELADO')
                                                  AND DATE(criado_em) = CURDATE()");
            $stmt_caixa_abertas->execute([':rid' => $rid]);
            $caixa_abertas_resumo = $stmt_caixa_abertas->fetch(PDO::FETCH_ASSOC) ?: [];

            $total_turno_dashboard = 0.0;
            if (!empty($caixa_aberto['id'])) {
                $stmt_total_turno = $db->prepare("SELECT COALESCE(SUM(total_final), 0) AS total FROM vendas WHERE caixa_id = :caixa_id AND status = 'PAGO'");
                $stmt_total_turno->execute([':caixa_id' => intval($caixa_aberto['id'])]);
                $total_turno_dashboard = floatval($stmt_total_turno->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            }

            $caixa_dashboard = [
                'contas_abertas' => intval($caixa_abertas_resumo['contas_abertas'] ?? 0),
                'total_aberto' => floatval($caixa_abertas_resumo['total_aberto'] ?? 0),
                'caixa_aberto' => (bool)$caixa_aberto,
                'total_turno' => $total_turno_dashboard,
            ];
        }
    }

    $receita_mes_atual = 0.0;
    $receita_mes_anterior = 0.0;
    $crescimento_mensal_pct = 0.0;
    $top_garcom = null;
    $top_caixa = null;
    $pedidos_resumo = [
        'pendentes' => 0,
        'preparando' => 0,
        'prontos' => 0,
        'entregues' => 0,
    ];
    $estoque_critico = 0;
    $estoque_alerta = 0;
    $sugestao_upgrade = [
        'mostrar' => false,
        'plano' => '',
        'mensagem' => 'Seu plano atual atende bem a operação de hoje.',
    ];
    $team_online = [
        'online' => 0,
        'total' => 0,
        'equipa' => [],
    ];

    foreach ($estoque_baixo as $itemEstoque) {
        $estoqueAtual = intval($itemEstoque['estoque'] ?? 0);
        $estoqueMinimo = intval($itemEstoque['estoque_minimo'] ?? 0);
        if ($estoqueAtual <= 0) {
            $estoque_critico++;
        }
        if ($estoqueAtual <= $estoqueMinimo) {
            $estoque_alerta++;
        }
    }

    if ($isAdminDashboard) {
        $stmt_mes = $db->prepare("SELECT COALESCE(SUM(total_final), 0) AS total FROM vendas WHERE restaurante_id = :rid AND status = 'PAGO' AND YEAR(criado_em) = YEAR(CURDATE()) AND MONTH(criado_em) = MONTH(CURDATE())");
        $stmt_mes->execute([':rid' => $rid]);
        $receita_mes_atual = floatval(($stmt_mes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));

        $stmt_mes_anterior = $db->prepare("SELECT COALESCE(SUM(total_final), 0) AS total FROM vendas WHERE restaurante_id = :rid AND status = 'PAGO' AND YEAR(criado_em) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(criado_em) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
        $stmt_mes_anterior->execute([':rid' => $rid]);
        $receita_mes_anterior = floatval(($stmt_mes_anterior->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));

        if ($receita_mes_anterior > 0) {
            $crescimento_mensal_pct = (($receita_mes_atual - $receita_mes_anterior) / $receita_mes_anterior) * 100;
        } elseif ($receita_mes_atual > 0) {
            $crescimento_mensal_pct = 100;
        }

        $stmt_tendencia = $db->prepare("SELECT DATE(criado_em) AS data, COALESCE(SUM(total_final), 0) AS total FROM vendas WHERE restaurante_id = :rid AND status = 'PAGO' AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(criado_em) ORDER BY data ASC");
        $stmt_tendencia->execute([':rid' => $rid]);
        $dados_grafico = $stmt_tendencia->fetchAll(PDO::FETCH_ASSOC);

        $stmt_top_garcom = $db->prepare("SELECT
                                            u.nome,
                                            COUNT(p.id) AS total_pedidos,
                                            COALESCE(SUM(p.total), 0) AS valor_pedidos
                                         FROM pedidos p
                                         INNER JOIN usuarios u ON u.id = p.garcom_id
                                         WHERE p.restaurante_id = :rid
                                           AND p.status <> 'CANCELADO'
                                           AND DATE(p.criado_em) = CURDATE()
                                           AND UPPER(REPLACE(u.perfil, 'Ç', 'C')) = 'GARCOM'
                                         GROUP BY u.id, u.nome
                                         ORDER BY valor_pedidos DESC, total_pedidos DESC
                                         LIMIT 1");
        $stmt_top_garcom->execute([':rid' => $rid]);
        $top_garcom = $stmt_top_garcom->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt_top_caixa = $db->prepare("SELECT u.nome, COUNT(v.id) AS total_vendas, COALESCE(SUM(v.total_final), 0) AS receita FROM vendas v INNER JOIN usuarios u ON u.id = v.usuario_id WHERE v.restaurante_id = :rid AND v.status = 'PAGO' AND DATE(v.criado_em) = CURDATE() AND UPPER(REPLACE(u.perfil, 'Ç', 'C')) = 'CAIXA' GROUP BY u.id, u.nome ORDER BY receita DESC, total_vendas DESC LIMIT 1");
        $stmt_top_caixa->execute([':rid' => $rid]);
        $top_caixa = $stmt_top_caixa->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt_pedidos_resumo = $db->prepare("SELECT SUM(CASE WHEN status IN ('NOVO','PENDENTE') THEN 1 ELSE 0 END) AS pendentes, SUM(CASE WHEN status = 'PREPARANDO' THEN 1 ELSE 0 END) AS preparando, SUM(CASE WHEN status = 'PRONTO' THEN 1 ELSE 0 END) AS prontos, SUM(CASE WHEN status = 'ENTREGUE' THEN 1 ELSE 0 END) AS entregues FROM pedidos WHERE restaurante_id = :rid");
        $stmt_pedidos_resumo->execute([':rid' => $rid]);
        $pedidos_resumo_db = $stmt_pedidos_resumo->fetch(PDO::FETCH_ASSOC) ?: [];
        $pedidos_resumo = [
            'pendentes' => intval($pedidos_resumo_db['pendentes'] ?? 0),
            'preparando' => intval($pedidos_resumo_db['preparando'] ?? 0),
            'prontos' => intval($pedidos_resumo_db['prontos'] ?? 0),
            'entregues' => intval($pedidos_resumo_db['entregues'] ?? 0),
        ];

        if ($plano_atual === 'BASICO' && ($qtd_hoje >= 25 || $total_produtos >= 80 || $pedidos_resumo['pendentes'] >= 10)) {
            $sugestao_upgrade = [
                'mostrar' => true,
                'plano' => 'PROFISSIONAL',
                'mensagem' => 'Seu volume operacional esta crescendo. O plano PROFISSIONAL libera mais automacoes e capacidade.',
            ];
        } elseif ($plano_atual === 'PROFISSIONAL' && ($qtd_hoje >= 80 || $total_hoje >= 25000 || $pedidos_resumo['pendentes'] >= 20)) {
            $sugestao_upgrade = [
                'mostrar' => true,
                'plano' => 'EMPRESARIAL',
                'mensagem' => 'Sua operacao esta em escala alta. O plano EMPRESARIAL ajuda no controle multi-equipe e expansao.',
            ];
        }

        try {
            $team_online = presenca_buscar_equipa_online($db, intval($rid), 12);
        } catch (Throwable $e) {
            $team_online = [
                'online' => 0,
                'total' => 0,
                'equipa' => [],
            ];
        }
    }
    ?>
 <!DOCTYPE html>
 <html lang="pt">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Dashboard - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
     <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
     <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

         .dashboard-user-avatar {
             width: 60px;
             height: 60px;
             border-radius: 14px;
             border: 3px solid var(--primary);
             box-shadow: var(--shadow);
             object-fit: cover;
         }

         .dashboard-user-info {
             display: flex;
             align-items: center;
             gap: 12px;
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

         .top-bar-date i {
             color: #475569;
         }

         .top-bar-user-chip {
             display: inline-flex;
             align-items: center;
             gap: 10px;
             padding: 8px 14px;
             border: 1px solid var(--border);
             border-radius: 999px;
             background: #fff;
             max-width: 100%;
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
             letter-spacing: 0.3px;
         }

         .top-bar-user-chip .chip-name {
             font-size: 15px;
             color: var(--text);
             font-weight: 700;
             line-height: 1;
             white-space: nowrap;
             overflow: hidden;
             text-overflow: ellipsis;
             max-width: 230px;
         }

         .top-bar-user-chip .chip-role {
             display: inline-flex;
             align-items: center;
             width: fit-content;
             font-size: 11px;
             color: #1d4ed8;
             font-weight: 800;
             text-transform: uppercase;
             letter-spacing: 0.35px;
             line-height: 1;
             padding: 4px 8px;
             border-radius: 999px;
             background: rgba(29, 78, 216, 0.12);
         }

         .top-bar-user-chip .chip-info {
             display: flex;
             flex-direction: column;
             gap: 3px;
             min-width: 0;
         }

         .content-area {
             padding: 24px;
         }

         .dashboard-user-card {
             background: linear-gradient(135deg, rgba(194, 65, 12, 0.12), rgba(247, 147, 30, 0.08));
             border: 1px solid rgba(194, 65, 12, 0.18);
             border-radius: 18px;
             padding: 18px 20px;
             margin-bottom: 18px;
             box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
         }

         .dashboard-user-toolbar {
             display: flex;
             align-items: center;
             justify-content: space-between;
             flex-wrap: wrap;
             gap: 16px;
         }

         .dashboard-user-card img,
         .dashboard-user-card .user-avatar-fallback {
             width: 74px;
             height: 74px;
             border-radius: 14px;
             border: 3px solid #fff;
             box-shadow: 0 8px 18px rgba(194, 65, 12, 0.22);
             object-fit: cover;
             flex-shrink: 0;
         }

         .dashboard-user-card .user-avatar-fallback {
             background: linear-gradient(135deg, #c2410c, #b45309);
             font-size: 30px;
             font-weight: 800;
         }

         .dashboard-user-card .user-name-welcome {
             font-family: 'Space Grotesk', sans-serif;
             font-size: 28px;
             font-weight: 700;
             color: #0f172a;
             line-height: 1.1;
             margin-bottom: 8px;
         }

         .dashboard-user-card .user-plan-info {
             display: flex;
             align-items: center;
             flex-wrap: wrap;
             gap: 8px;
             font-size: 15px;
             color: #334155;
             font-weight: 600;
         }

         .dashboard-user-card .plan-pill {
             display: inline-flex;
             align-items: center;
             padding: 5px 10px;
             border-radius: 999px;
             background: rgba(29, 78, 216, 0.12);
             color: #1d4ed8;
             font-size: 12px;
             font-weight: 800;
             letter-spacing: 0.4px;
             text-transform: uppercase;
         }

         .dashboard-user-card .expiry-pill {
             display: inline-flex;
             align-items: center;
             padding: 5px 10px;
             border-radius: 999px;
             background: rgba(4, 120, 87, 0.12);
             color: #047857;
             font-size: 12px;
             font-weight: 800;
             letter-spacing: 0.2px;
         }

         .stats-grid {
             display: grid;
             grid-template-columns: repeat(4, 1fr);
             gap: 16px;
             margin-bottom: 20px;
         }

         .stat-card {
             background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
             border-radius: 18px;
             padding: 20px;
             box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
             border: 1px solid #e2e8f0;
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
             transform: translateY(-3px);
             box-shadow: 0 14px 26px rgba(15, 23, 42, 0.1);
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

         .stat-card.info::before {
             background: var(--info);
         }

         .stat-icon {
             width: 50px;
             height: 50px;
             border-radius: 12px;
             display: flex;
             align-items: center;
             justify-content: center;
             font-size: 20px;
             box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
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

         .stat-icon.info {
             background: rgba(59, 130, 246, 0.1);
             color: var(--info);
         }

         .stat-value {
             font-family: 'Space Grotesk', sans-serif;
             font-size: 38px;
             font-weight: 800;
             color: var(--text);
             line-height: 1;
             margin-bottom: 8px;
         }

         .stat-label {
             color: #475569;
             font-size: 13px;
             font-weight: 700;
             text-transform: uppercase;
             letter-spacing: 0.35px;
         }

         .stat-card .text-muted {
             color: #64748b !important;
             font-size: 12px !important;
             font-weight: 600;
         }

         .dashboard-reservas-banner {
             background: linear-gradient(135deg, rgba(255, 107, 53, 0.10), rgba(245, 158, 11, 0.08));
             border: 1px solid rgba(255, 107, 53, 0.18);
             border-radius: 20px;
             padding: 18px 20px;
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 16px;
             box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
         }

         .dashboard-reservas-banner.has-pending {
             background: linear-gradient(135deg, rgba(255, 107, 53, 0.16), rgba(245, 158, 11, 0.10));
             border-color: rgba(255, 107, 53, 0.28);
         }

         .dashboard-reservas-left {
             display: flex;
             align-items: center;
             gap: 16px;
             min-width: 0;
         }

         .dashboard-reservas-icon {
             width: 52px;
             height: 52px;
             border-radius: 16px;
             display: flex;
             align-items: center;
             justify-content: center;
             background: rgba(255, 107, 53, 0.12);
             color: var(--primary);
             font-size: 22px;
             flex-shrink: 0;
         }

         .dashboard-reservas-title {
             font-family: 'Space Grotesk', sans-serif;
             font-weight: 800;
             font-size: 18px;
             color: var(--text);
             margin-bottom: 4px;
         }

         .dashboard-reservas-subtitle {
             color: #475569;
             font-size: 13px;
             line-height: 1.45;
         }

         .dashboard-reservas-meta {
             display: flex;
             flex-direction: column;
             align-items: flex-end;
             gap: 8px;
             flex-shrink: 0;
         }

         .dashboard-reservas-badge {
             min-width: 104px;
             padding: 10px 16px;
             border-radius: 999px;
             font-size: 14px;
             font-weight: 800;
             text-align: center;
             letter-spacing: 0.2px;
         }

         .dashboard-reservas-badge.pending {
             background: rgba(245, 158, 11, 0.16);
             color: var(--warning);
         }

         .dashboard-reservas-badge.clear {
             background: rgba(16, 185, 129, 0.12);
             color: var(--success);
         }

         .dashboard-reservas-link {
             color: var(--primary);
             text-decoration: none;
             font-size: 12px;
             font-weight: 700;
         }

         .dashboard-reservas-link:hover {
             text-decoration: underline;
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

         .badge-custom {
             padding: 6px 14px;
             border-radius: 20px;
             font-size: 11px;
             font-weight: 700;
             text-transform: uppercase;
             letter-spacing: 0.5px;
         }

         .badge-primary {
             background: rgba(255, 107, 53, 0.1);
             color: var(--primary);
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

         .btn-sm {
             padding: 8px 14px;
             border-radius: 8px;
             font-size: 12px;
         }

         .quick-action {
             display: flex;
             flex-direction: column;
             align-items: center;
             justify-content: center;
             padding: 20px;
             border-radius: 20px;
             text-decoration: none;
             transition: all 0.3s;
             color: white;
         }

         .quick-action:hover {
             transform: translateY(-5px);
             box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
         }

         .plan-card {
             border: 2px solid var(--border);
             border-radius: 20px;
             text-align: center;
             padding: 24px;
             transition: all 0.3s;
         }

         .plan-card:hover {
             border-color: var(--primary);
             transform: scale(1.02);
         }

         .plan-card.current {
             border-color: var(--primary);
             background: linear-gradient(135deg, rgba(255, 107, 53, 0.1), rgba(247, 147, 30, 0.1));
         }

         .plan-icon {
             width: 60px;
             height: 60px;
             border-radius: 50%;
             display: inline-flex;
             align-items: center;
             justify-content: center;
             font-size: 28px;
             margin-bottom: 12px;
         }

         .alert-box {
             border-radius: 16px;
             padding: 16px 20px;
             display: flex;
             align-items: center;
             gap: 15px;
         }

         .product-img {
             width: 45px;
             height: 45px;
             border-radius: 10px;
             object-fit: cover;
         }

         .executive-card {
             background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
             border-radius: 18px;
             border: 1px solid #e2e8f0;
             box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
             padding: 18px;
             height: 100%;
         }

         .executive-title {
             font-size: 13px;
             font-weight: 800;
             letter-spacing: 0.3px;
             text-transform: uppercase;
             color: #64748b;
             margin-bottom: 10px;
             display: flex;
             align-items: center;
             gap: 8px;
         }

         .executive-main {
             font-family: 'Space Grotesk', sans-serif;
             font-size: 28px;
             font-weight: 800;
             color: #0f172a;
             line-height: 1.15;
         }

         .kpi-row {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 10px;
             margin-top: 8px;
             font-size: 13px;
             color: #475569;
             font-weight: 600;
         }

         .trend-chip {
             display: inline-flex;
             align-items: center;
             gap: 6px;
             padding: 6px 10px;
             border-radius: 999px;
             font-size: 12px;
             font-weight: 800;
         }

         .trend-positive {
             background: rgba(16, 185, 129, 0.15);
             color: #047857;
         }

         .trend-negative {
             background: rgba(239, 68, 68, 0.13);
             color: #b91c1c;
         }

         .insight-list {
             list-style: none;
             padding: 0;
             margin: 12px 0 0;
         }

         .insight-list li {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 10px;
             font-size: 13px;
             color: #334155;
             padding: 8px 0;
             border-bottom: 1px dashed #e2e8f0;
         }

         .insight-list li:last-child {
             border-bottom: none;
             padding-bottom: 0;
         }

         .team-online-list {
             list-style: none;
             margin: 12px 0 0;
             padding: 0;
         }

         .team-online-item {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 10px;
             padding: 9px 0;
             border-bottom: 1px dashed #e2e8f0;
         }

         .team-online-item:last-child {
             border-bottom: none;
             padding-bottom: 0;
         }

         .team-member-name {
             font-size: 13px;
             font-weight: 700;
             color: #0f172a;
         }

         .team-member-role {
             font-size: 11px;
             color: #64748b;
             font-weight: 700;
             text-transform: uppercase;
             letter-spacing: 0.3px;
         }

         .team-status-dot {
             width: 10px;
             height: 10px;
             border-radius: 50%;
             display: inline-block;
             margin-right: 6px;
         }

         .team-status-online {
             background: #10b981;
             box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
         }

         .team-status-offline {
             background: #cbd5e1;
         }

         .skeleton {
             background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%) !important;
             background-size: 200% 100%;
             animation: loading 1.5s infinite;
         }

         @keyframes loading {
             0% {
                 background-position: 200% 0;
             }

             100% {
                 background-position: -200% 0;
             }
         }

         [data-stat] {
             font-size: 38px !important;
             font-weight: 800 !important;
         }

         @media (max-width: 1200px) {
             .stats-grid {
                 grid-template-columns: repeat(2, 1fr);
             }
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
                 transition: left 0.3s cubic-bezier(.4,0,.2,1);
                 box-shadow: 2px 0 16px rgba(0,0,0,0.08);
                 display: flex;
             }
             .sidebar.sidebar-hidden {
                 left: -100vw !important;
                 transition: left 0.3s cubic-bezier(.4,0,.2,1);
             }
             .main-content {
                 margin-left: 0 !important;
                 width: 100%;
                 max-width: 100%;
             }

             .stats-grid {
                 grid-template-columns: 1fr;
             }

             .dashboard-reservas-banner {
                 flex-direction: column;
                 align-items: flex-start;
             }

             .dashboard-reservas-meta {
                 width: 100%;
                 align-items: flex-start;
             }

             .dashboard-reservas-badge {
                 min-width: 0;
                 width: 100%;
             }

             .content-area {
                 padding: 20px;
             }

             .top-bar {
                 padding: 10px 16px;
                 position: relative;
             }

             .page-title {
                 font-size: 18px;
             }

             .top-bar-right {
                 gap: 8px;
             }

             .top-bar-date {
                 display: none;
             }

             .top-bar-user-chip {
                 padding: 5px 8px;
             }

             .top-bar-user-chip .chip-name {
                 display: none;
             }

             .top-bar-user-chip .chip-role {
                 display: none;
             }

             .dashboard-user-card {
                 padding: 14px 14px;
             }

             .dashboard-user-toolbar {
                 align-items: flex-start;
             }

             .dashboard-user-card .user-name-welcome {
                 font-size: 22px;
             }

             .dashboard-user-card img,
             .dashboard-user-card .user-avatar-fallback {
                 width: 62px;
                 height: 62px;
             }

             .stat-card {
                 padding: 16px;
             }

             .stat-value {
                 font-size: 30px;
             }

             .stat-label {
                 font-size: 12px;
             }
         }

         @media (max-width: 576px) {
             .content-area {
                 padding: 16px 12px;
             }

             .top-bar {
                 padding: 12px 14px;
                 gap: 10px;
             }

             .page-title {
                 font-size: 16px;
                 gap: 8px;
             }

             .top-bar-right {
                 width: 100%;
                 justify-content: space-between;
                 gap: 8px;
                 flex-wrap: wrap;
             }

             .top-bar-user-chip {
                 width: 100%;
                 padding: 6px 10px;
             }

             .dashboard-user-toolbar {
                 gap: 14px;
             }

             .dashboard-user-toolbar > .btn {
                 width: 100%;
                 justify-content: center;
             }

             .dashboard-user-card {
                 padding: 14px;
             }

             .dashboard-user-card .user-name-welcome {
                 font-size: 20px;
             }

             .dashboard-user-card img,
             .dashboard-user-card .user-avatar-fallback {
                 width: 56px;
                 height: 56px;
             }

             .dashboard-user-card .user-plan-info {
                 font-size: 13px;
             }

             .stat-card {
                 padding: 14px;
             }

             .stat-value {
                 font-size: 24px;
             }

             .stat-label {
                 font-size: 11px;
             }

             .dashboard-reservas-banner {
                 padding: 14px;
                 gap: 12px;
             }

             .dashboard-reservas-left {
                 gap: 12px;
             }

             .dashboard-reservas-icon {
                 width: 44px;
                 height: 44px;
                 font-size: 18px;
             }

             .dashboard-reservas-title {
                 font-size: 16px;
             }

             .dashboard-reservas-subtitle {
                 font-size: 12px;
             }

             .dashboard-reservas-meta {
                 width: 100%;
                 align-items: stretch;
             }

             .dashboard-reservas-badge {
                 width: 100%;
                 min-width: 0;
                 padding: 9px 12px;
                 font-size: 13px;
             }

             .dashboard-reservas-link {
                 align-self: flex-start;
             }
         }
     </style>
 </head>

 <body class="premium-ui">
     <?php
        $top_nome_usuario = $_SESSION['nome'] ?? 'Usuário';
        $top_nome_curto = explode(' ', trim($top_nome_usuario))[0] ?: 'Usuário';
        $top_perfil_usuario = $_SESSION['perfil'] ?? 'USER';
        $top_nome_partes = preg_split('/\s+/', trim($top_nome_usuario));
        $top_iniciais = strtoupper(substr($top_nome_partes[0] ?? 'U', 0, 1) . substr($top_nome_partes[1] ?? '', 0, 1));
        $top_foto_usuario = $_SESSION['foto'] ?? '';
        $public_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $resolve_foto_url = function ($path) use ($public_base) {
            if (empty($path)) {
                return '';
            }
            if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/') === 0) {
                return $path;
            }
            return $public_base . '/' . ltrim($path, '/');
        };
        $top_foto_url = $resolve_foto_url($top_foto_usuario);
        $perfil_menu = strtoupper(trim((string)($_SESSION['perfil'] ?? 'USER')));
        if ($perfil_menu === 'GARÇOM') {
            $perfil_menu = 'GARCOM';
        }
        ?>
     <div class="container-fluid">
         <div class="row">
             <!-- SIDEBAR -->
             <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

             <!-- MAIN CONTENT -->
             <main class="main-content">
                 <div class="top-bar">
                     <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                     <div class="top-bar-right">
                         <span class="top-bar-date"><i class="far fa-clock"></i><span id="topBarDateTime"><?php echo date('d/m/Y H:i'); ?></span></span>
                         <div class="top-bar-user-chip">
                             <?php if (!empty($top_foto_url)): ?>
                                 <img src="<?php echo htmlspecialchars($top_foto_url); ?>" alt="<?php echo htmlspecialchars($top_nome_usuario); ?>">
                             <?php else: ?>
                                 <span class="chip-avatar"><?php echo htmlspecialchars($top_iniciais); ?></span>
                             <?php endif; ?>
                             <span class="chip-info">
                                 <span class="chip-name"><?php echo htmlspecialchars($top_nome_usuario); ?></span>
                                 <span class="chip-role"><?php echo htmlspecialchars($top_perfil_usuario); ?></span>
                             </span>
                         </div>
                     </div>
                 </div>

                 <div class="content-area">
                     <!-- CARD USUÁRIO PROFISSIONAL -->
                     <?php
                        $nome_usuario_completo = $_SESSION['nome'] ?? 'Usuário';
                        $perfil_usuario_card = $_SESSION['perfil'] ?? 'USER';
                        $usuario_id = $_SESSION['usuario_id'] ?? 0;
                        $foto_usuario = $_SESSION['foto'] ?? '';
                        $foto_usuario_url = $resolve_foto_url($foto_usuario);
                        $data_cadastro = $_SESSION['criado_em'] ?? date('Y-m-d');
                        ?>
                     <div class="dashboard-user-card">
                         <div class="dashboard-user-toolbar">
                             <div class="d-flex align-items-center gap-3">
                                 <?php if (!empty($foto_usuario_url)): ?>
                                     <img src="<?php echo htmlspecialchars($foto_usuario_url); ?>" alt="<?php echo htmlspecialchars($nome_usuario_completo); ?>">
                                 <?php else: ?>
                                     <div class="user-avatar-fallback d-flex align-items-center justify-content-center text-white">
                                         <?php echo strtoupper(substr($nome_usuario_completo, 0, 1)); ?>
                                     </div>
                                 <?php endif; ?>
                                 <div>
                                     <div class="user-name-welcome">Bem-vindo, <?php echo htmlspecialchars(explode(' ', $nome_usuario_completo)[0]); ?>!</div>
                                     <div class="user-plan-info">
                                         <span>Você está no plano</span>
                                         <span class="plan-pill"><?php echo htmlspecialchars($plano_atual); ?></span>
                                         <?php if ($dias_restantes > 0): ?>
                                             <span class="expiry-pill"><i class="fas fa-calendar-check me-1"></i>Expira em <?php echo $dias_restantes; ?> dias</span>
                                         <?php else: ?>
                                             <span class="expiry-pill"><i class="fas fa-check-circle me-1"></i>Ativo</span>
                                         <?php endif; ?>
                                     </div>
                                 </div>
                             </div>
                             <a href="usuarios.php?edit_me=1" class="btn btn-primary btn-sm" style="border-radius: 10px; padding: 9px 16px; font-weight: 600;">
                                 <i class="fas fa-camera me-1"></i> Editar Perfil / Upload Imagem
                             </a>
                         </div>
                     </div>

                     <!-- AVISO CAIXA FECHADO -->
                     <?php if (!$caixa_aberto): ?>
                         <div class="alert alert-warning d-flex align-items-center mb-4" style="border-radius: 16px;">
                             <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                             <div class="flex-grow-1"><strong>Atenção!</strong> O caixa ainda não foi aberto hoje.</div>
                             <a href="caixa.php" class="btn btn-warning btn-sm">Abrir Caixa</a>
                         </div>
                     <?php endif; ?>

                     <?php if (in_array($perfil, ['ADMIN', 'GARCOM'], true)): ?>
                         <?php
                         $reservaUltima = $dashboard_reservas['ultima_pendente'];
                         $reservaCount = (int)($dashboard_reservas['pendentes'] ?? 0);
                         $reservaLabel = $reservaCount === 1 ? 'pendente' : 'pendentes';
                         $reservaResumoTexto = $reservaUltima
                             ? trim(
                                 ($reservaUltima['nome_cliente'] ?? 'Cliente')
                                 . ' - '
                                 . ($reservaUltima['data_reserva'] ?? '')
                                 . ' às '
                                 . substr((string)($reservaUltima['hora_reserva'] ?? ''), 0, 5)
                                 . (
                                     !empty($reservaUltima['mesa_numero'])
                                         ? ' | Mesa ' . $reservaUltima['mesa_numero']
                                         : ' | Mesa por atribuir'
                                 )
                             )
                             : 'Nenhuma reserva pendente no momento.';
                         ?>
                         <div
                             id="dashboardReservasBanner"
                             class="dashboard-reservas-banner mb-4 <?php echo $reservaCount > 0 ? 'has-pending' : ''; ?>"
                         >
                             <div class="dashboard-reservas-left">
                                 <div class="dashboard-reservas-icon">
                                     <i class="fas fa-bell"></i>
                                 </div>
                                 <div class="min-w-0">
                                     <div class="dashboard-reservas-title">Reservas do restaurante</div>
                                     <div class="dashboard-reservas-subtitle" data-dashboard-reservas-ultima>
                                         <?php echo htmlspecialchars($reservaResumoTexto); ?>
                                     </div>
                                 </div>
                             </div>
                             <div class="dashboard-reservas-meta">
                                 <span
                                    class="badge-custom dashboard-reservas-badge <?php echo $reservaCount > 0 ? 'pending' : 'clear'; ?>"
                                    data-dashboard-reservas-count
                                >
                                     <?php echo $reservaCount; ?> <?php echo $reservaLabel; ?>
                                 </span>
                                 <a href="mesas.php" class="dashboard-reservas-link">Abrir mesas e reservas</a>
                             </div>
                         </div>
                     <?php endif; ?>

                    <?php if ($perfil === 'GARCOM'): ?>
                        <!-- GARÇOM STATS -->
                        <div class="stats-grid" id="garcom-stats-loading">
                            <div class="stat-card">
                                <div class="stat-icon primary"><i class="fas fa-receipt"></i></div>
                                <div class="stat-value" data-stat="pedidos-total"><?php echo $garcom_dashboard['total_pedidos']; ?></div>
                                <div class="stat-label">Pedidos Hoje</div>
                            </div>
                            <div class="stat-card warning">
                                <div class="stat-icon warning"><i class="fas fa-chair"></i></div>
                                <div class="stat-value" data-stat="mesas-total"><?php echo $garcom_dashboard['mesas_ocupadas']; ?>/<?php echo $garcom_dashboard['mesas_total']; ?></div>
                                <div class="stat-label">Mesas (Ocup./Total)</div>
                            </div>
                            <div class="stat-card success">
                                <div class="stat-icon success"><i class="fas fa-shekel-sign"></i></div>
                                <div class="stat-value" data-stat="vendas-total"><?php echo number_format($garcom_dashboard['valor_pedidos'], 2, ',', '.'); ?> MZN</div>
                                <div class="stat-label">Valor em Pedidos</div>
                            </div>
                            <div class="stat-card info">
                                <div class="stat-icon info"><i class="fas fa-list-check"></i></div>
                                <div class="stat-value" data-stat="status-pedidos">
                                    <div class="d-flex justify-content-around gap-2 mt-2">
                                        <span class="badge-custom badge-warning"><?php echo $garcom_dashboard['pendentes']; ?> Pend.</span>
                                        <span class="badge-custom badge-info"><?php echo $garcom_dashboard['preparados']; ?> Prep.</span>
                                        <span class="badge-custom badge-success"><?php echo $garcom_dashboard['entregues']; ?> Ent.</span>
                                    </div>
                                </div>
                                <div class="stat-label">Status dos Pedidos</div>
                            </div>
                        </div>
                     <?php elseif ($perfil === 'CAIXA'): ?>
                         <!-- CAIXA STATS -->
                         <div class="stats-grid" id="caixa-stats-loading">
                             <div class="stat-card">
                                 <div class="stat-icon success"><i class="fas fa-receipt"></i></div>
                                 <div class="stat-value" data-caixa="contas-abertas"><?php echo $caixa_dashboard['contas_abertas']; ?></div>
                                 <div class="stat-label">Contas Abertas</div>
                             </div>
                             <div class="stat-card">
                                 <div class="stat-icon info"><i class="fas fa-receipt"></i></div>
                                 <div class="stat-value" data-caixa="total-aberto"><?php echo number_format($caixa_dashboard['total_aberto'], 2, ',', '.'); ?></div>
                                 <div class="stat-label">Total em Aberto</div>
                             </div>
                             <div class="stat-card warning">
                                 <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
                                 <span class="caixa-status badge <?php echo $caixa_dashboard['caixa_aberto'] ? 'badge-success' : 'badge-warning'; ?>"><?php echo $caixa_dashboard['caixa_aberto'] ? 'ABERTO' : 'FECHADO'; ?></span>
                                 <div class="stat-label mt-2">Status Caixa</div>
                             </div>
                             <div class="stat-card success">
                                 <div class="stat-icon success"><i class="fas fa-money-bill-wave"></i></div>
                                 <div class="stat-value" data-caixa="total-turno"><?php echo number_format($caixa_dashboard['total_turno'], 2, ',', '.'); ?></div>
                                 <div class="stat-label">Total Turno</div>
                             </div>
                         </div>
                     <?php else: ?>
                         <!-- GENERIC ADMIN STATS -->
                         <div class="stats-grid">
                             <div class="stat-card success">
                                 <div class="d-flex justify-content-between align-items-start mb-3">
                                     <div class="stat-icon success"><i class="fas fa-shekel-sign"></i></div>
                                 </div>
                                 <div class="stat-value" style="color: var(--success);"><?php echo number_format($total_hoje, 2, ',', '.'); ?></div>
                                 <div class="stat-label">Vendas Hoje (MZN)</div>
                                 <div class="mt-2 text-muted" style="font-size: 13px;"><i class="fas fa-shopping-cart me-1"></i> <?php echo $qtd_hoje; ?> vendas</div>
                             </div>
                             <div class="stat-card">
                                 <div class="d-flex justify-content-between align-items-start mb-3">
                                     <div class="stat-icon primary"><i class="fas fa-pizza-slice"></i></div>
                                 </div>
                                 <div class="stat-value" style="color: var(--primary);"><?php echo $total_produtos; ?></div>
                                 <div class="stat-label">Produtos Ativos</div>
                                 <div class="mt-2 text-muted" style="font-size: 13px;"><i class="fas fa-box me-1"></i> No cardápio</div>
                             </div>
                             <div class="stat-card warning">
                                 <div class="d-flex justify-content-between align-items-start mb-3">
                                     <div class="stat-icon warning"><i class="fas fa-chair"></i></div>
                                 </div>
                                 <div class="stat-value" style="color: var(--warning);"><?php echo $mesas_ocupadas; ?>/<?php echo $total_mesas; ?></div>
                                 <div class="stat-label">Mesas Ocupadas</div>
                                 <div class="mt-2 text-muted" style="font-size: 13px;"><i class="fas fa-check-circle me-1"></i> <?php echo $total_mesas - $mesas_ocupadas; ?> livres</div>
                             </div>
                             <div class="stat-card info">
                                 <div class="d-flex justify-content-between align-items-start mb-3">
                                     <div class="stat-icon info"><i class="fas fa-money-bill-wave"></i></div>
                                 </div>
                                 <div class="stat-value" style="color: <?php echo $caixa_aberto ? 'var(--success)' : 'var(--danger)'; ?>;"><?php echo $caixa_aberto ? 'ABERTO' : 'FECHADO'; ?></div>
                                 <div class="stat-label">Status do Caixa</div>
                                 <div class="mt-2 text-muted" style="font-size: 13px;"><i class="fas fa-<?php echo $caixa_aberto ? 'lock-open' : 'lock'; ?> me-1"></i> <?php echo $caixa_aberto ? 'Operacional' : 'Bloqueado'; ?></div>
                             </div>
                         </div>
                     <?php endif; ?>

                     <?php if ($isAdminDashboard): ?>
                         <div class="row g-3 mb-3">
                             <div class="col-lg-4">
                                 <div class="executive-card">
                                     <div class="executive-title"><i class="fas fa-chart-line text-success"></i> Visão Geral de Receita</div>
                                     <div class="executive-main">MZN <?php echo number_format($receita_mes_atual, 2, ',', '.'); ?></div>
                                     <?php $crescimentoPositivo = $crescimento_mensal_pct >= 0; ?>
                                     <div class="kpi-row">
                                         <span>Vs. mês anterior</span>
                                         <span class="trend-chip <?php echo $crescimentoPositivo ? 'trend-positive' : 'trend-negative'; ?>">
                                             <i class="fas fa-<?php echo $crescimentoPositivo ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                             <?php echo number_format(abs($crescimento_mensal_pct), 1, ',', '.'); ?>%
                                         </span>
                                     </div>
                                     <div class="kpi-row">
                                         <span>Mês anterior</span>
                                         <strong>MZN <?php echo number_format($receita_mes_anterior, 2, ',', '.'); ?></strong>
                                     </div>
                                 </div>
                             </div>

                             <div class="col-lg-4">
                                 <div class="executive-card">
                                     <div class="executive-title"><i class="fas fa-users text-primary"></i> Desempenho da Equipe</div>
                                     <ul class="insight-list">
                                         <li>
                                             <span>Top Garçom</span>
                                             <span>
                                                 <?php if ($top_garcom): ?>
                                                     <strong><?php echo htmlspecialchars($top_garcom['nome']); ?></strong>
                                                 <?php else: ?>
                                                     <span class="text-muted">Sem pedidos hoje</span>
                                                 <?php endif; ?>
                                             </span>
                                         </li>
                                         <li>
                                             <span>Pedidos Garçom</span>
                                             <span><?php echo $top_garcom ? intval($top_garcom['total_pedidos']) . ' pedido(s)' : '0 pedido(s)'; ?></span>
                                         </li>
                                         <li>
                                             <span>Top Caixa</span>
                                             <span>
                                                 <?php if ($top_caixa): ?>
                                                     <strong><?php echo htmlspecialchars($top_caixa['nome']); ?></strong>
                                                 <?php else: ?>
                                                     <span class="text-muted">Sem vendas hoje</span>
                                                 <?php endif; ?>
                                             </span>
                                         </li>
                                         <li>
                                             <span>Receita Caixa</span>
                                             <span><?php echo $top_caixa ? 'MZN ' . number_format(floatval($top_caixa['receita']), 2, ',', '.') : 'MZN 0,00'; ?></span>
                                         </li>
                                     </ul>
                                 </div>
                             </div>

                             <div class="col-lg-4">
                                 <div class="executive-card">
                                     <div class="executive-title"><i class="fas fa-hourglass-half text-warning"></i> Pedidos Pendentes</div>
                                     <div class="executive-main"><?php echo $pedidos_resumo['pendentes']; ?></div>
                                     <div class="kpi-row"><span>Em preparo</span><strong><?php echo $pedidos_resumo['preparando']; ?></strong></div>
                                     <div class="kpi-row"><span>Prontos para entrega</span><strong><?php echo $pedidos_resumo['prontos']; ?></strong></div>
                                     <div class="kpi-row"><span>Entregues</span><strong><?php echo $pedidos_resumo['entregues']; ?></strong></div>
                                 </div>
                             </div>
                         </div>

                         <div class="row g-3 mb-3">
                             <div class="col-lg-12">
                                 <div class="executive-card">
                                     <div class="executive-title"><i class="fas fa-signal text-success"></i> Equipe Online</div>
                                     <div class="executive-main" id="teamOnlineCount"><?php echo intval($team_online['online']); ?>/<?php echo intval($team_online['total']); ?></div>
                                     <ul class="team-online-list" id="teamOnlineList">
                                         <?php foreach ($team_online['equipa'] as $membro): ?>
                                             <?php $isOnline = !empty($membro['online']); ?>
                                             <li class="team-online-item">
                                                 <div>
                                                     <div class="team-member-name"><?php echo htmlspecialchars($membro['nome'] ?? ''); ?></div>
                                                     <div class="team-member-role"><?php echo htmlspecialchars($membro['perfil'] ?? ''); ?></div>
                                                 </div>
                                                 <div class="small fw-semibold" style="color: #475569;">
                                                     <span class="team-status-dot <?php echo $isOnline ? 'team-status-online' : 'team-status-offline'; ?>"></span>
                                                     <?php echo $isOnline ? 'Online' : 'Offline'; ?>
                                                 </div>
                                             </li>
                                         <?php endforeach; ?>
                                         <?php if (empty($team_online['equipa'])): ?>
                                             <li class="text-muted small">Sem dados de presença da equipe no momento.</li>
                                         <?php endif; ?>
                                     </ul>
                                 </div>
                             </div>
                         </div>
                     <?php endif; ?>

                     <!-- GRÁFICO E PLANOS -->
                     <div class="row g-3 mb-3">
                         <div class="col-lg-8">
                             <div class="card">
                                 <div class="card-header">
                                     <h3 class="card-title"><i class="fas fa-chart-line"></i> <?php echo $isAdminDashboard ? 'Receita e Tendência (14 dias)' : ($perfil === 'GARCOM' ? 'Pedidos da Semana' : 'Vendas da Semana'); ?></h3>
                                 </div>
                                 <div class="card-body">
                                     <canvas id="salesChart" height="100"></canvas>
                                 </div>
                             </div>
                         </div>
                         <div class="col-lg-4">
                             <div class="card">
                                 <div class="card-header">
                                     <h3 class="card-title"><i class="fas fa-crown"></i> Seu Plano</h3>
                                     <?php if ($plano_atual != 'EMPRESARIAL'): ?>
                                         <a href="configuracoes.php?secao=plano" class="btn btn-primary btn-sm">Upgrade</a>
                                     <?php else: ?>
                                         <a href="configuracoes.php?secao=plano" class="btn btn-outline-secondary btn-sm"><i class="fas fa-history me-1"></i>Histórico</a>
                                     <?php endif; ?>
                                 </div>
                                 <div class="card-body">
                                     <div class="plan-card <?php echo $plano_atual == 'EMPRESARIAL' ? 'current' : ''; ?> mb-3">
                                         <div class="plan-icon" style="background: linear-gradient(135deg, #FF6B35, #F7931E); color: white;">
                                             <i class="fas fa-<?php echo $plano_atual == 'EMPRESARIAL' ? 'crown' : ($plano_atual == 'PROFISSIONAL' ? 'star' : 'user'); ?>"></i>
                                         </div>
                                         <h5 class="mb-1">Plano <?php echo $plano_atual; ?></h5>
                                         <p class="text-muted mb-2" style="font-size: 13px;">
                                             <?php if ($dias_restantes > 0): ?>Expira em <?php echo $dias_restantes; ?> dias
                                             <?php else: ?>Assinatura ativa<?php endif; ?>
                                         </p>
                                         <?php if ($plano_atual == 'EMPRESARIAL'): ?><span class="badge-custom badge-success">Ativo</span><?php endif; ?>
                                     </div>
                                     <h6 class="mb-3" style="font-size: 13px; color: var(--text-light);">Recursos do Plano</h6>
                                     <ul class="list-unstyled" style="font-size: 13px;">
                                         <?php foreach ($recursos_plano_atual as $recurso): ?>
                                             <li class="mb-2"><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($recurso); ?></li>
                                         <?php endforeach; ?>
                                     </ul>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <!-- TABELAS -->
                     <div class="row g-3 mb-3">
                         <div class="col-lg-8">
                             <div class="card">
                                 <div class="card-header">
                                     <h3 class="card-title"><i class="fas fa-shopping-cart"></i> <?php echo $perfil === 'GARCOM' ? 'Últimos Pedidos' : 'Últimas Vendas'; ?></h3>
                                     <?php if ($perfil !== 'GARCOM' || $temPedidosOnline): ?>
                                         <a href="<?php echo $perfil === 'GARCOM' ? 'pedidos.php' : 'vendas.php'; ?>" class="btn btn-outline-primary btn-sm">Ver Todas</a>
                                     <?php endif; ?>
                                 </div>
                                 <div class="card-body">
                                     <div class="table-responsive">
                                         <table class="table">
                                             <thead>
                                                 <tr>
                                                     <th>Fatura</th>
                                                     <th>Horário</th>
                                                     <th>Mesa</th>
                                                     <th>Total</th>
                                                     <th>Pagamento</th>
                                                     <th>Status</th>
                                                 </tr>
                                             </thead>
                                             <tbody>
                                                 <?php foreach ($ultimas_vendas as $v): ?>
                                                     <tr>
                                                         <td><strong><?php echo $v['numero_fatura']; ?></strong></td>
                                                         <td><?php echo date('H:i', strtotime($v['criado_em'])); ?></td>
                                                         <!-- Destaca pedidos do Balcão no dashboard -->
                                                         <td class="<?php echo empty($v['mesa_numero']) ? 'balcao-label' : ''; ?>">
                                                             <?php echo $v['mesa_numero'] ? 'Mesa ' . $v['mesa_numero'] : 'Balcão'; ?>
                                                         </td>
                                                         <td><strong><?php echo number_format($v['total_final'], 2, ',', '.'); ?></strong></td>
                                                         <td><span class="badge-custom badge-<?php echo $v['forma_pagamento'] == 'DINHEIRO' ? 'success' : ($v['forma_pagamento'] == 'CARTAO' ? 'info' : 'secondary'); ?>"><?php echo $v['forma_pagamento']; ?></span></td>
                                                         <td><span class="badge-custom badge-<?php echo $v['status'] == 'PAGO' ? 'success' : ($v['status'] == 'CANCELADO' ? 'danger' : 'warning'); ?>"><?php echo $v['status']; ?></span></td>
                                                     </tr>
                                                 <?php endforeach; ?>
                                                 <?php if (empty($ultimas_vendas)): ?>
                                                     <tr>
                                                         <td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Nenhuma venda hoje</td>
                                                     </tr>
                                                 <?php endif; ?>
                                             </tbody>
                                         </table>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <div class="col-lg-4">
                             <div class="card">
                                 <div class="card-header">
                                     <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Estoque Baixo</h3>
                                     <a href="produtos.php" class="btn btn-outline-warning btn-sm">Ver Produtos</a>
                                 </div>
                                 <div class="card-body p-0">
                                     <?php if (!empty($estoque_baixo)): ?>
                                         <div class="list-group list-group-flush">
                                             <?php foreach ($estoque_baixo as $ep): ?>
                                                 <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                     <div class="d-flex align-items-center">
                                                         <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($ep['nome']); ?>&background=ffe5d0&color=FF6B35&size=40" alt="<?php echo $ep['nome']; ?>" class="product-img me-3">
                                                         <div>
                                                             <div class="fw-semibold"><?php echo htmlspecialchars($ep['nome']); ?></div>
                                                             <small class="text-muted">Mín: <?php echo $ep['estoque_minimo']; ?></small>
                                                         </div>
                                                     </div>
                                                     <span class="badge-custom badge-danger"><?php echo $ep['estoque']; ?></span>
                                                 </div>
                                             <?php endforeach; ?>
                                         </div>
                                     <?php else: ?>
                                         <div class="text-center py-5 text-muted"><i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                             <p class="mb-0">Estoque em dia!</p>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <!-- ATALHOS RÁPIDOS -->
                     <div class="row g-3">
                         <div class="col-6 col-md-3">
                             <a href="vendas.php" class="quick-action" style="background: linear-gradient(135deg, #FF6B35, #F7931E);">
                                 <i class="fas fa-plus-circle fa-2x mb-2"></i><span>Nova Venda</span>
                             </a>
                         </div>
                         <div class="col-6 col-md-3">
                             <a href="produtos.php" class="quick-action" style="background: linear-gradient(135deg, #10b981, #20c997);">
                                 <i class="fas fa-box fa-2x mb-2"></i><span>Produtos</span>
                             </a>
                         </div>
                         <div class="col-6 col-md-3">
                             <a href="mesas.php" class="quick-action" style="background: linear-gradient(135deg, #3b82f6, #6f42c1);">
                                 <i class="fas fa-chair fa-2x mb-2"></i><span>Mesas</span>
                             </a>
                         </div>
                         <?php if ($plano_atual === 'EMPRESARIAL'): ?>
                         <div class="col-6 col-md-3">
                             <a href="admin.php" class="quick-action" style="background: linear-gradient(135deg, #FF6B35, #F7931E);">
                                 <i class="fas fa-user-shield fa-2x mb-2"></i><span>Administração</span>
                             </a>
                         </div>
                         <div class="col-6 col-md-3">
                             <a href="relatorios.php" class="quick-action" style="background: linear-gradient(135deg, #64748b, #343a40);">
                                 <i class="fas fa-chart-bar fa-2x mb-2"></i><span>Relatórios</span>
                             </a>
                         </div>
                         <?php else: ?>
                         <div class="col-6 col-md-3">
                             <a href="relatorios.php" class="quick-action" style="background: linear-gradient(135deg, #64748b, #343a40);">
                                 <i class="fas fa-chart-bar fa-2x mb-2"></i><span>Relatórios</span>
                             </a>
                         </div>
                         <?php endif; ?>
                     </div>
                 </div>
             </main>
         </div>
     </div>

     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
     <script>
         const dashboardMeta = {
             restauranteId: <?php echo intval($rid); ?>,
             perfil: <?php echo json_encode($perfil); ?>,
             usuarioId: <?php echo intval($usuario_logado_id); ?>
         };

         function formatDashboardNumber(num, withDecimals = true) {
             return new Intl.NumberFormat('pt-PT', {
                 minimumFractionDigits: withDecimals ? 2 : 0,
                 maximumFractionDigits: withDecimals ? 2 : 0
             }).format(Number(num || 0));
         }

         const ctx = document.getElementById('salesChart').getContext('2d');
         const labels = <?php echo json_encode(array_map(function ($d) {
                            return date('d/m', strtotime($d['data']));
                        }, $dados_grafico)); ?>;
         const data = <?php echo json_encode(array_map(function ($d) {
                            return floatval($d['total']);
                        }, $dados_grafico)); ?>;
         while (labels.length < <?php echo intval($grafico_min_pontos); ?>) {
             labels.unshift('-');
             data.unshift(0);
         }
         new Chart(ctx, {
             type: 'line',
             data: {
                 labels: labels,
                 datasets: [{
                     label: 'Vendas (MZN)',
                     data: data,
                     borderColor: '#FF6B35',
                     backgroundColor: 'rgba(255, 107, 53, 0.1)',
                     borderWidth: 3,
                     fill: true,
                     tension: 0.4,
                     pointBackgroundColor: '#FF6B35',
                     pointBorderColor: '#fff',
                     pointBorderWidth: 2,
                     pointRadius: 5
                 }]
             },
             options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 plugins: {
                     legend: {
                         display: false
                     }
                 },
                 scales: {
                     y: {
                         beginAtZero: true,
                         grid: {
                             color: 'rgba(0, 0, 0, 0.05)'
                         }
                     },
                     x: {
                         grid: {
                             display: false
                         }
                     }
                 }
             }
         });

         const reservasBannerEl = document.getElementById('dashboardReservasBanner');
         const reservasCountEl = document.querySelector('[data-dashboard-reservas-count]');
         const reservasUltimaEl = document.querySelector('[data-dashboard-reservas-ultima]');

         const refreshDashboardReservas = async () => {
             if (!reservasBannerEl || !reservasCountEl || !reservasUltimaEl) {
                 return;
             }

             try {
                 const res = await fetch('api/reservas_alertas.php', {
                     credentials: 'same-origin',
                     headers: {
                         'X-Requested-With': 'XMLHttpRequest'
                     }
                 });
                 const json = await res.json();
                 if (!json.success) {
                     return;
                 }

                 const pendentes = Number(json.pendentes || 0);
                 const ultima = json.ultima_pendente || null;
                 const pendentesLabel = pendentes === 1 ? 'pendente' : 'pendentes';

                 reservasBannerEl.classList.toggle('has-pending', pendentes > 0);
                 reservasCountEl.textContent = pendentes + ' ' + pendentesLabel;
                 reservasCountEl.classList.toggle('pending', pendentes > 0);
                 reservasCountEl.classList.toggle('clear', pendentes <= 0);

                 if (ultima) {
                     const nomeCliente = ultima.nome_cliente || 'Cliente';
                     const dataReserva = ultima.data_reserva || '';
                     const horaReserva = String(ultima.hora_reserva || '').slice(0, 5);
                     const mesaLabel = ultima.mesa_numero ? ('Mesa ' + ultima.mesa_numero) : 'Mesa por atribuir';
                     reservasUltimaEl.textContent = `${nomeCliente} - ${dataReserva} às ${horaReserva} | ${mesaLabel}`;
                 } else {
                     reservasUltimaEl.textContent = 'Nenhuma reserva pendente no momento.';
                 }
             } catch (error) {
                 console.error('Falha ao atualizar reservas do dashboard:', error);
             }
         };

         refreshDashboardReservas();
         setInterval(refreshDashboardReservas, 25000);

         // Relógio em tempo real no topo do dashboard
         // Garçom Dashboard
         if (document.getElementById('garcom-stats-loading')) {
             const loadGarcomStats = async () => {
                 try {
                     const [pedidosRes, mesasRes, vendasRes] = await Promise.all([
                                fetch('api/garcom_pedidos.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                                fetch('api/garcom_mesas.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                                fetch('api/garcom_vendas.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                     ]);

                     const pedidos = await pedidosRes.json();
                     const mesas = await mesasRes.json();
                     const vendas = await vendasRes.json();

                     if (pedidos.success && mesas.success && vendas.success) {
                        const pedidosEl = document.querySelector('[data-stat="pedidos-total"]');
                        const mesasEl = document.querySelector('[data-stat="mesas-total"]');
                        const vendasEl = document.querySelector('[data-stat="vendas-total"]');

                        if (pedidosEl) pedidosEl.textContent = String(pedidos.total_pedidos ?? 0);
                        if (mesasEl) mesasEl.textContent = `${mesas.ocupadas ?? 0}/${mesas.total ?? 0}`;
                        if (vendasEl) vendasEl.textContent = formatDashboardNumber(vendas.total_vendas_hoje ?? 0) + ' MZN';

                         // Status breakdown
                         const statusHtml = `
                             <div class="d-flex justify-content-around gap-2 mt-2">
                                 <span class="badge-custom badge-warning">${pedidos.pendentes} Pend.</span>
                                 <span class="badge-custom badge-info">${pedidos.preparados} Prep.</span>
                                 <span class="badge-custom badge-success">${pedidos.entregues} Ent.</span>
                             </div>
                         `;
                        const statusPedidosEl = document.querySelector('[data-stat="status-pedidos"]');
                        if (statusPedidosEl) {
                            statusPedidosEl.innerHTML = statusHtml;
                        }

                         document.getElementById('garcom-stats-loading').id = 'garcom-stats-loaded';
                     }
                 } catch (e) {
                     console.error('Erro loading garcom stats:', e);
                 }
             };

             loadGarcomStats();
             setInterval(loadGarcomStats, 30000); // Refresh every 30s
         }

         if (document.getElementById('caixa-stats-loading')) {
             const loadCaixaStats = async () => {
                 try {
                     const [vendasRes, turnoRes, abertasRes] = await Promise.all([
                         fetch('api/caixa_vendas.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                         fetch('api/caixa_turno.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                         fetch('api/caixa_mesas_abertas.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                     ]);

                     const vendas = await vendasRes.json();
                     const turno = await turnoRes.json();
                     const abertas = await abertasRes.json();

                     if ((vendas.success ?? true) && (turno.success ?? true) && (abertas.success ?? true)) {
                         const contasAbertasEl = document.querySelector('[data-caixa="contas-abertas"]');
                         const totalAbertoEl = document.querySelector('[data-caixa="total-aberto"]');
                         const totalTurnoEl = document.querySelector('[data-caixa="total-turno"]');
                         const caixaStatusEl = document.querySelector('.caixa-status');

                         if (contasAbertasEl) contasAbertasEl.textContent = String(abertas.qtd_abertas ?? 0);
                         if (totalAbertoEl) totalAbertoEl.textContent = formatDashboardNumber(abertas.total_aberto ?? 0);
                         if (totalTurnoEl) totalTurnoEl.textContent = formatDashboardNumber(turno.total_turno ?? 0);

                         if (caixaStatusEl) {
                             caixaStatusEl.textContent = turno.aberto ? 'ABERTO' : 'FECHADO';
                             caixaStatusEl.className = `caixa-status badge ${turno.aberto ? 'badge-success' : 'badge-warning'}`;
                         }

                         document.getElementById('caixa-stats-loading').id = 'caixa-stats-loaded';
                     }
                 } catch (e) {
                     console.error('Erro loading caixa stats:', e);
                 }
             };

             loadCaixaStats();
             setInterval(loadCaixaStats, 30000);
         }

         const teamOnlineCountEl = document.getElementById('teamOnlineCount');
         const teamOnlineListEl = document.getElementById('teamOnlineList');

         const refreshTeamOnline = async () => {
             if (!teamOnlineCountEl || !teamOnlineListEl) return;
             try {
                 const res = await fetch('api/team_online_status.php', {
                     credentials: 'same-origin',
                     headers: {
                         'X-Requested-With': 'XMLHttpRequest'
                     }
                 });
                 const json = await res.json();
                 if (!json.success) return;

                 teamOnlineCountEl.textContent = `${json.online}/${json.total}`;
                 if (!Array.isArray(json.equipa) || json.equipa.length === 0) {
                     teamOnlineListEl.innerHTML = '<li class="text-muted small">Sem dados de presença da equipe no momento.</li>';
                     return;
                 }

                 teamOnlineListEl.innerHTML = json.equipa.map((membro) => {
                     const isOnline = Boolean(membro.online);
                     const nome = membro.nome || '';
                     const perfil = membro.perfil || '';
                     return `
                         <li class="team-online-item">
                             <div>
                                 <div class="team-member-name">${nome}</div>
                                 <div class="team-member-role">${perfil}</div>
                             </div>
                             <div class="small fw-semibold" style="color: #475569;">
                                 <span class="team-status-dot ${isOnline ? 'team-status-online' : 'team-status-offline'}"></span>
                                 ${isOnline ? 'Online' : 'Offline'}
                             </div>
                         </li>
                     `;
                 }).join('');
             } catch (error) {
                 console.error('Falha ao atualizar equipe online:', error);
             }
         };

         const heartbeatPresence = async () => {
             try {
                 await fetch('api/online_ping.php', {
                     method: 'POST',
                     credentials: 'same-origin',
                     headers: {
                         'X-Requested-With': 'XMLHttpRequest'
                     }
                 });
             } catch (error) {
                 console.error('Falha no heartbeat de presença:', error);
             }
         };

         if (teamOnlineCountEl) {
             heartbeatPresence();
             refreshTeamOnline();
             setInterval(heartbeatPresence, 15000);
             setInterval(refreshTeamOnline, 10000);
         }

        function enableRealtimeDashboardRefresh() {
            let refreshTimer = null;
            const triggerRefresh = () => {
                if (refreshTimer) clearTimeout(refreshTimer);
                refreshTimer = setTimeout(() => window.location.reload(), 700);
            };

            if (typeof io !== 'function') {
                return;
            }

            try {
                const socketBaseUrl = `${window.location.protocol}//${window.location.hostname}:3001`;
                const socket = io(socketBaseUrl, {
                    transports: ['websocket', 'polling'],
                    reconnection: true,
                    reconnectionAttempts: 5
                });

                 socket.on('connect', () => {
                     socket.emit('join-room', `restaurante_${dashboardMeta.restauranteId}`);
                 });

                 socket.on('novo_pedido', (payload) => {
                     const pedido = payload && payload.pedido ? payload.pedido : payload;
                     if (!pedido) return;
                     if (Number(pedido.restaurante_id || 0) !== Number(dashboardMeta.restauranteId || 0)) return;

                     if (dashboardMeta.perfil === 'GARCOM') {
                         if (Number(pedido.garcom_id || 0) !== Number(dashboardMeta.usuarioId || 0)) return;
                     }

                     triggerRefresh();
                 });
             } catch (error) {
                 console.error('Falha ao conectar no realtime dashboard:', error);
             }
         }

        (function loadRealtimeDashboardScript() {
            const script = document.createElement('script');
            script.src = `${window.location.protocol}//${window.location.hostname}:3001/socket.io/socket.io.js`;
            script.async = true;
            script.onload = enableRealtimeDashboardRefresh;
            script.onerror = () => {
                // Realtime é opcional; sem Socket.IO o dashboard continua funcionando via refresh/intervalos.
            };
            document.head.appendChild(script);
        })();

         (function startTopBarClock() {
             const el = document.getElementById('topBarDateTime');
             if (!el) return;

             const format = (n) => String(n).padStart(2, '0');
             const updateClock = () => {
                 const now = new Date();
                 const text = `${format(now.getDate())}/${format(now.getMonth() + 1)}/${now.getFullYear()} ${format(now.getHours())}:${format(now.getMinutes())}`;
                 el.textContent = text;
             };

             updateClock();
             setInterval(updateClock, 1000);
         })();
     </script>
 </body>

 </html>

