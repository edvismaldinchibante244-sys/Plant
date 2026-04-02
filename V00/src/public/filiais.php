<?php

include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/plano_check.php';
include_once __DIR__ . '/../config/csrf.php';
include_once __DIR__ . '/../config/filiais_helper.php';

$perfil = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
if ($perfil !== 'ADMIN') {
    header('Location: dashboard.php?erro=acesso_negado');
    exit;
}

$db = (new Database())->getConnection();

try {
    $contexto = filiais_obter_contexto(
        $db,
        (int)($_SESSION['restaurante_id'] ?? 0),
        (int)($_SESSION['matriz_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[FILIAIS][PAGINA][CONTEXTO] ' . $e->getMessage());
    header('Location: dashboard.php?erro=gestao_filiais_indisponivel');
    exit;
}

if (empty($contexto['tem_multi_filial'])) {
    header('Location: configuracoes.php?erro=plano_sem_multi_filial');
    exit;
}

$matrizId = (int)$contexto['matriz_id'];
$matriz = $contexto['matriz'];
$restauranteAtual = $contexto['restaurante_atual'];
$contextoFilialAtivo = !$contexto['restaurante_atual_eh_matriz'];
$filialAtual = $contextoFilialAtivo ? $restauranteAtual : null;
$dadosPlano = $contexto['plano'];
$nomePlano = $dadosPlano['nome_display'] ?? ($dadosPlano['plano_nome'] ?? ($matriz['plano'] ?? 'Plano atual'));
$csrfToken = csrf_get_token();

$secao = $_GET['secao'] ?? 'inicio';
$secoesValidas = ['inicio', 'dashboard', 'estoque', 'funcionarios'];
if (!in_array($secao, $secoesValidas, true)) {
    $secao = 'inicio';
}

$mensagem = '';
$tipoMsg = 'success';
if (isset($_GET['msg'])) {
    $mensagem = htmlspecialchars((string)$_GET['msg'], ENT_QUOTES, 'UTF-8');
    $tipoMsg = strtolower(trim((string)($_GET['tipo'] ?? 'success')));
    if ($tipoMsg === 'error') {
        $tipoMsg = 'danger';
    }
    if (!in_array($tipoMsg, ['success', 'danger', 'warning', 'info'], true)) {
        $tipoMsg = 'success';
    }
}

$stmt = $db->prepare("
    SELECT id, nome, email, telefone, endereco, cidade, status, created_at, atualizado_em AS updated_at
    FROM restaurantes
    WHERE filial_id = ? AND is_matriz = 0
    ORDER BY CASE WHEN status = 'ATIVO' THEN 0 ELSE 1 END, nome ASC
");
$stmt->execute([$matrizId]);
$filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filialIds = array_map('intval', array_column($filiais, 'id'));
$vendasPorFilial = [];
$estoquePorFilial = [];
$funcionariosPorFilial = [];

if (!empty($filialIds)) {
    $placeholders = implode(', ', array_fill(0, count($filialIds), '?'));

    $stmtVendas = $db->prepare("
        SELECT restaurante_id, COALESCE(SUM(total_final), 0) AS total_vendas, COUNT(*) AS num_vendas
        FROM vendas
        WHERE restaurante_id IN ($placeholders)
          AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY restaurante_id
    ");
    $stmtVendas->execute($filialIds);
    while ($row = $stmtVendas->fetch(PDO::FETCH_ASSOC)) {
        $vendasPorFilial[(int)$row['restaurante_id']] = [
            'total' => (float)$row['total_vendas'],
            'num_vendas' => (int)$row['num_vendas'],
        ];
    }

    $stmtEstoque = $db->prepare("
        SELECT restaurante_id, nome, estoque, estoque_minimo
        FROM produtos
        WHERE restaurante_id IN ($placeholders)
          AND ativo = 1
          AND estoque <= estoque_minimo
        ORDER BY restaurante_id ASC, estoque ASC, nome ASC
    ");
    $stmtEstoque->execute($filialIds);
    while ($row = $stmtEstoque->fetch(PDO::FETCH_ASSOC)) {
        $restauranteId = (int)$row['restaurante_id'];
        if (!isset($estoquePorFilial[$restauranteId])) {
            $estoquePorFilial[$restauranteId] = ['total_baixo' => 0, 'itens' => []];
        }

        $estoquePorFilial[$restauranteId]['total_baixo']++;
        if (count($estoquePorFilial[$restauranteId]['itens']) < 10) {
            $estoquePorFilial[$restauranteId]['itens'][] = [
                'nome' => $row['nome'],
                'estoque' => (float)$row['estoque'],
                'estoque_minimo' => (float)$row['estoque_minimo'],
            ];
        }
    }

    $stmtFuncionarios = $db->prepare("
        SELECT
            u.id,
            u.restaurante_id,
            u.nome,
            u.email,
            u.perfil,
            u.ativo,
            COALESCE(v.total_vendas, 0) AS vendas_total
        FROM usuarios u
        LEFT JOIN (
            SELECT usuario_id, COUNT(*) AS total_vendas
            FROM vendas
            GROUP BY usuario_id
        ) v ON v.usuario_id = u.id
        WHERE u.restaurante_id IN ($placeholders)
        ORDER BY u.restaurante_id ASC, u.nome ASC
    ");
    $stmtFuncionarios->execute($filialIds);
    while ($row = $stmtFuncionarios->fetch(PDO::FETCH_ASSOC)) {
        $restauranteId = (int)$row['restaurante_id'];
        if (!isset($funcionariosPorFilial[$restauranteId])) {
            $funcionariosPorFilial[$restauranteId] = ['total' => 0, 'funcionarios' => []];
        }

        $funcionariosPorFilial[$restauranteId]['total']++;
        $funcionariosPorFilial[$restauranteId]['funcionarios'][] = [
            'id' => (int)$row['id'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'perfil' => $row['perfil'],
            'ativo' => (int)$row['ativo'] === 1,
            'vendas_total' => (int)$row['vendas_total'],
        ];
    }
}

$totalFiliais = count($filiais);
$filiaisAtivas = 0;
$totalFuncionarios = 0;
$totalVendas30Dias = 0.0;
$totalAlertasEstoque = 0;
$cidades = [];

foreach ($filiais as &$filial) {
    $filialId = (int)$filial['id'];
    $filial['vendas_30d'] = (float)($vendasPorFilial[$filialId]['total'] ?? 0);
    $filial['num_vendas_30d'] = (int)($vendasPorFilial[$filialId]['num_vendas'] ?? 0);
    $filial['estoque_critico_total'] = (int)($estoquePorFilial[$filialId]['total_baixo'] ?? 0);
    $filial['estoque_critico_itens'] = $estoquePorFilial[$filialId]['itens'] ?? [];
    $filial['funcionarios_total'] = (int)($funcionariosPorFilial[$filialId]['total'] ?? 0);
    $filial['funcionarios_lista'] = $funcionariosPorFilial[$filialId]['funcionarios'] ?? [];
    $filial['status_normalizado'] = filiais_normalizar_status($filial['status'] ?? 'ATIVO');

    if ($filial['status_normalizado'] === 'ATIVO') {
        $filiaisAtivas++;
    }

    if (!empty($filial['cidade'])) {
        $cidades[strtolower((string)$filial['cidade'])] = $filial['cidade'];
    }

    $totalFuncionarios += $filial['funcionarios_total'];
    $totalVendas30Dias += $filial['vendas_30d'];
    $totalAlertasEstoque += $filial['estoque_critico_total'];
}
unset($filial);

$limiteFiliais = plano_verificar_limite_db($matrizId, 'filiais', $totalFiliais);
$podeCriarFilial = ((int)$limiteFiliais['limite'] === -1) || ((int)$limiteFiliais['restante'] > 0);
$limiteTexto = ((int)$limiteFiliais['limite'] === -1) ? 'Ilimitadas' : ($totalFiliais . '/' . (int)$limiteFiliais['limite']);
$restantesTexto = ((int)$limiteFiliais['limite'] === -1) ? 'Ilimitado' : (string)(int)$limiteFiliais['restante'];
$cidadeCobertura = count($cidades);
$configJs = [
    'csrfToken' => $csrfToken,
    'createUrl' => 'api/filial_criar.php',
    'updateUrl' => 'api/filial_atualizar.php',
];

function filiais_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function filiais_moeda($valor): string
{
    return number_format((float)$valor, 2, ',', '.') . ' MZN';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filiais - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/filiais.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="premium-ui">
    <div class="container-fluid">
        <div class="row">
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

            <main class="main-content filiais-page">
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Voltar ao Dashboard</a>

                <?php if ($contextoFilialAtivo && $filialAtual): ?>
                    <section class="context-banner">
                        <div>
                            <h2><i class="fas fa-location-dot me-2"></i>Contexto atual: <?php echo filiais_h($filialAtual['nome']); ?></h2>
                            <p>Voce esta dentro de uma filial. Restaure a matriz para gerir todas as unidades novamente.</p>
                        </div>
                        <form method="post" action="api/filial_voltar_matriz.php">
                            <input type="hidden" name="_csrf" value="<?php echo filiais_h($csrfToken); ?>">
                            <button type="submit" class="btn-brand"><i class="fas fa-rotate-left"></i> Voltar para a Matriz</button>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="hero-card">
                    <div class="hero-main">
                        <div>
                            <h1>Gestao de Filiais</h1>
                            <p>Centralize a operacao da sua rede, acompanhe desempenho e corrija desvios sem perder o contexto da matriz.</p>
                            <div class="hero-meta">
                                <span class="hero-chip"><i class="fas fa-crown"></i><?php echo filiais_h($matriz['nome'] ?? 'Matriz'); ?></span>
                                <span class="hero-chip"><i class="fas fa-layer-group"></i><?php echo filiais_h($nomePlano); ?></span>
                                <span class="hero-chip"><i class="fas fa-building"></i><?php echo filiais_h($limiteTexto); ?> filiais</span>
                                <span class="hero-chip"><i class="fas fa-globe"></i><?php echo (int)$cidadeCobertura; ?> cidades</span>
                            </div>
                        </div>
                        <div class="hero-actions">
                            <button type="button" class="btn-brand" data-bs-toggle="modal" data-bs-target="#modalAdicionarFilial" <?php echo $podeCriarFilial ? '' : 'disabled'; ?>>
                                <i class="fas fa-plus"></i> Nova Filial
                            </button>
                            <a href="filiais.php?secao=<?php echo filiais_h($secao); ?>" class="btn-ghost"><i class="fas fa-rotate"></i> Atualizar</a>
                        </div>
                    </div>
                </section>

                <?php if ($mensagem !== ''): ?>
                    <div class="alert alert-<?php echo filiais_h($tipoMsg); ?> mb-4"><?php echo $mensagem; ?></div>
                <?php endif; ?>

                <section class="stat-grid">
                    <article class="stats-card"><span class="stats-label">Filiais ativas</span><strong><?php echo (int)$filiaisAtivas; ?>/<?php echo (int)$totalFiliais; ?></strong><p><?php echo max(0, $totalFiliais - $filiaisAtivas); ?> inativas no momento</p></article>
                    <article class="stats-card"><span class="stats-label">Equipe total</span><strong><?php echo (int)$totalFuncionarios; ?></strong><p>Colaboradores somados nas unidades</p></article>
                    <article class="stats-card"><span class="stats-label">Vendas em 30 dias</span><strong><?php echo filiais_h(filiais_moeda($totalVendas30Dias)); ?></strong><p>Receita agregada das filiais</p></article>
                    <article class="stats-card"><span class="stats-label">Espaco no plano</span><strong><?php echo filiais_h($restantesTexto); ?></strong><p>Vagas restantes para novas filiais</p></article>
                </section>

                <section class="nav-grid">
                    <a href="filiais.php?secao=inicio" class="nav-card <?php echo $secao === 'inicio' ? 'active' : ''; ?>"><span>Minhas Filiais</span><small><?php echo (int)$totalFiliais; ?> unidades</small></a>
                    <a href="filiais.php?secao=dashboard" class="nav-card <?php echo $secao === 'dashboard' ? 'active' : ''; ?>"><span>Dashboard</span><small>Visao executiva</small></a>
                    <a href="filiais.php?secao=estoque" class="nav-card <?php echo $secao === 'estoque' ? 'active' : ''; ?>"><span>Estoque</span><small><?php echo (int)$totalAlertasEstoque; ?> alertas</small></a>
                    <a href="filiais.php?secao=funcionarios" class="nav-card <?php echo $secao === 'funcionarios' ? 'active' : ''; ?>"><span>Funcionarios</span><small>Equipe por unidade</small></a>
                </section>

                <?php include __DIR__ . '/includes/filiais_sections.php'; ?>
            </main>
        </div>
    </div>

    <div class="modal fade" id="modalAdicionarFilial" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2 text-primary"></i>Nova Filial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div id="alertFilialCriacao" class="alert d-none mx-3 mt-3 mb-0"></div>
                <form id="formNovaFilial">
                    <input type="hidden" name="_csrf" value="<?php echo filiais_h($csrfToken); ?>">
                    <div class="modal-body">
                        <label class="form-label" for="nome_filial">Nome da Filial *</label>
                        <input type="text" id="nome_filial" name="nome_filial" class="form-control mb-3" required>
                        <label class="form-label" for="email_filial">Email</label>
                        <input type="email" id="email_filial" name="email_filial" class="form-control mb-3">
                        <label class="form-label" for="telefone_filial">Telefone</label>
                        <input type="text" id="telefone_filial" name="telefone_filial" class="form-control mb-3">
                        <label class="form-label" for="endereco_filial">Endereco</label>
                        <input type="text" id="endereco_filial" name="endereco_filial" class="form-control mb-3">
                        <label class="form-label" for="cidade_filial">Cidade</label>
                        <input type="text" id="cidade_filial" name="cidade_filial" class="form-control">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" id="btnCriarFilial" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Criar Filial</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditarFilial" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-pen me-2 text-primary"></i>Editar Filial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div id="alertFilialEdicao" class="alert d-none mx-3 mt-3 mb-0"></div>
                <form id="formEditarFilial">
                    <input type="hidden" name="_csrf" value="<?php echo filiais_h($csrfToken); ?>">
                    <input type="hidden" id="edit_filial_id" name="filial_id" value="">
                    <div class="modal-body">
                        <label class="form-label" for="edit_nome_filial">Nome da Filial *</label>
                        <input type="text" id="edit_nome_filial" name="nome_filial" class="form-control mb-3" required>
                        <label class="form-label" for="edit_email_filial">Email</label>
                        <input type="email" id="edit_email_filial" name="email_filial" class="form-control mb-3">
                        <label class="form-label" for="edit_telefone_filial">Telefone</label>
                        <input type="text" id="edit_telefone_filial" name="telefone_filial" class="form-control mb-3">
                        <label class="form-label" for="edit_endereco_filial">Endereco</label>
                        <input type="text" id="edit_endereco_filial" name="endereco_filial" class="form-control mb-3">
                        <label class="form-label" for="edit_cidade_filial">Cidade</label>
                        <input type="text" id="edit_cidade_filial" name="cidade_filial" class="form-control mb-3">
                        <label class="form-label" for="edit_status_filial">Status</label>
                        <select id="edit_status_filial" name="status" class="form-select">
                            <option value="ATIVO">Ativo</option>
                            <option value="INATIVO">Inativo</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" id="btnSalvarFilial" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Alteracoes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>window.FILIAIS_CONFIG = <?php echo json_encode($configJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/filiais.js"></script>
</body>
</html>

