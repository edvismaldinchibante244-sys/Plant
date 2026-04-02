<?php if ($secao === 'inicio'): ?>
    <section class="content-card">
        <div class="content-head">
            <div>
                <h2 class="content-title"><i class="fas fa-crown text-warning"></i>Informacoes da Matriz</h2>
                <p class="content-subtitle">A matriz centraliza o plano, o acesso e o contexto de gestao das unidades.</p>
            </div>
            <span class="pill <?php echo $podeCriarFilial ? 'pill-success' : 'pill-warning'; ?>">
                <?php echo $podeCriarFilial ? 'Pode criar nova filial' : 'Limite do plano atingido'; ?>
            </span>
        </div>

        <div class="overview-grid">
            <div class="overview-box"><span>Nome da matriz</span><strong><?php echo filiais_h($matriz['nome'] ?? '-'); ?></strong></div>
            <div class="overview-box"><span>Email principal</span><strong><?php echo filiais_h($matriz['email'] ?? '-'); ?></strong></div>
            <div class="overview-box"><span>Plano / capacidade</span><strong><?php echo filiais_h($nomePlano . ' • ' . $limiteTexto); ?></strong></div>
        </div>

        <div class="content-head">
            <div>
                <h3 class="content-title"><i class="fas fa-network-wired text-primary"></i>Rede de Filiais</h3>
                <p class="content-subtitle">Acesse rapidamente uma unidade, acompanhe indicadores e ajuste o cadastro sem sair da matriz.</p>
            </div>
        </div>

        <?php if (empty($filiais)): ?>
            <div class="empty-state">
                <i class="fas fa-building-circle-xmark"></i>
                <h4>Nenhuma filial cadastrada</h4>
                <p>Clique em <strong>Nova Filial</strong> para abrir a primeira unidade da sua rede.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-filiais">
                    <thead>
                        <tr>
                            <th>Filial</th>
                            <th>Contato</th>
                            <th>Vendas / Equipe</th>
                            <th>Status</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filiais as $filial): ?>
                            <tr>
                                <td>
                                    <div class="branch-name"><?php echo filiais_h($filial['nome']); ?></div>
                                    <div class="branch-meta">
                                        <span><i class="fas fa-location-dot me-1"></i><?php echo filiais_h($filial['cidade'] ?: 'Sem cidade'); ?></span>
                                        <span><i class="fas fa-box-open me-1"></i><?php echo (int)$filial['estoque_critico_total']; ?> alertas</span>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo filiais_h($filial['email'] ?: '-'); ?></div>
                                    <div class="content-subtitle mb-0"><?php echo filiais_h($filial['telefone'] ?: 'Sem telefone'); ?></div>
                                </td>
                                <td>
                                    <div><strong><?php echo filiais_h(filiais_moeda($filial['vendas_30d'])); ?></strong></div>
                                    <div class="content-subtitle mb-0"><?php echo (int)$filial['num_vendas_30d']; ?> vendas • <?php echo (int)$filial['funcionarios_total']; ?> funcionarios</div>
                                </td>
                                <td>
                                    <span class="status-chip <?php echo $filial['status_normalizado'] === 'ATIVO' ? 'active' : 'inactive'; ?>">
                                        <i class="fas fa-circle"></i><?php echo filiais_h($filial['status_normalizado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-stack">
                                        <form method="post" action="api/filial_selecionar.php">
                                            <input type="hidden" name="_csrf" value="<?php echo filiais_h($csrfToken); ?>">
                                            <input type="hidden" name="filial_id" value="<?php echo (int)$filial['id']; ?>">
                                            <button type="submit" class="btn-table btn-table-primary" <?php echo $filial['status_normalizado'] === 'ATIVO' ? '' : 'disabled'; ?>>
                                                <i class="fas fa-right-to-bracket"></i> Acessar
                                            </button>
                                        </form>
                                        <button
                                            type="button"
                                            class="btn-table"
                                            data-edit-filial
                                            data-filial-id="<?php echo (int)$filial['id']; ?>"
                                            data-filial-nome="<?php echo filiais_h($filial['nome']); ?>"
                                            data-filial-email="<?php echo filiais_h($filial['email'] ?? ''); ?>"
                                            data-filial-telefone="<?php echo filiais_h($filial['telefone'] ?? ''); ?>"
                                            data-filial-endereco="<?php echo filiais_h($filial['endereco'] ?? ''); ?>"
                                            data-filial-cidade="<?php echo filiais_h($filial['cidade'] ?? ''); ?>"
                                            data-filial-status="<?php echo filiais_h($filial['status_normalizado']); ?>"
                                        >
                                            <i class="fas fa-pen"></i> Editar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($secao === 'dashboard'): ?>
    <section class="content-card">
        <div class="content-head">
            <div>
                <h2 class="content-title"><i class="fas fa-chart-line text-primary"></i>Dashboard Executivo</h2>
                <p class="content-subtitle">Comparacao rapida entre as filiais com base nos ultimos 30 dias.</p>
            </div>
            <span class="pill pill-success">Atualizado agora</span>
        </div>

        <?php if (empty($filiais)): ?>
            <div class="empty-state"><i class="fas fa-chart-line"></i><h4>Sem filiais para comparar</h4><p>Cadastre uma unidade para desbloquear os indicadores consolidados da rede.</p></div>
        <?php else: ?>
            <?php foreach ($filiais as $filial): ?>
                <div class="branch-block">
                    <div class="branch-block-header">
                        <div>
                            <h3 class="content-title mb-0"><i class="fas fa-building text-primary"></i><?php echo filiais_h($filial['nome']); ?></h3>
                            <p class="content-subtitle mb-0"><?php echo filiais_h($filial['cidade'] ?: 'Cidade nao informada'); ?></p>
                        </div>
                        <span class="status-chip <?php echo $filial['status_normalizado'] === 'ATIVO' ? 'active' : 'inactive'; ?>"><i class="fas fa-circle"></i><?php echo filiais_h($filial['status_normalizado']); ?></span>
                    </div>
                    <div class="metric-list">
                        <div class="metric-item"><span>Receita em 30 dias</span><strong><?php echo filiais_h(filiais_moeda($filial['vendas_30d'])); ?></strong></div>
                        <div class="metric-item"><span>Numero de vendas</span><strong><?php echo (int)$filial['num_vendas_30d']; ?></strong></div>
                        <div class="metric-item"><span>Equipe cadastrada</span><strong><?php echo (int)$filial['funcionarios_total']; ?></strong></div>
                        <div class="metric-item"><span>Alertas de estoque</span><strong><?php echo (int)$filial['estoque_critico_total']; ?></strong></div>
                        <div class="metric-item"><span>Email operacional</span><strong><?php echo filiais_h($filial['email'] ?: '-'); ?></strong></div>
                        <div class="metric-item"><span>Telefone</span><strong><?php echo filiais_h($filial['telefone'] ?: '-'); ?></strong></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($secao === 'estoque'): ?>
    <section class="content-card">
        <div class="content-head">
            <div>
                <h2 class="content-title"><i class="fas fa-boxes-stacked text-primary"></i>Estoque Critico por Filial</h2>
                <p class="content-subtitle">Lista resumida dos produtos abaixo do estoque minimo em cada unidade.</p>
            </div>
            <span class="pill <?php echo $totalAlertasEstoque > 0 ? 'pill-danger' : 'pill-success'; ?>"><?php echo (int)$totalAlertasEstoque; ?> itens criticos</span>
        </div>

        <?php if (empty($filiais)): ?>
            <div class="empty-state"><i class="fas fa-boxes-stacked"></i><h4>Nenhuma filial cadastrada</h4><p>Cadastre uma filial para centralizar os alertas de estoque.</p></div>
        <?php else: ?>
            <?php foreach ($filiais as $filial): ?>
                <div class="branch-block">
                    <div class="branch-block-header">
                        <div>
                            <h3 class="content-title mb-0"><i class="fas fa-building text-primary"></i><?php echo filiais_h($filial['nome']); ?></h3>
                            <p class="content-subtitle mb-0"><?php echo (int)$filial['estoque_critico_total']; ?> itens abaixo do minimo</p>
                        </div>
                        <span class="pill <?php echo $filial['estoque_critico_total'] > 0 ? 'pill-danger' : 'pill-success'; ?>">
                            <?php echo $filial['estoque_critico_total'] > 0 ? 'Atencao imediata' : 'Estoque saudavel'; ?>
                        </span>
                    </div>

                    <?php if (!empty($filial['estoque_critico_itens'])): ?>
                        <div class="table-wrap">
                            <table class="table-filiais">
                                <thead><tr><th>Produto</th><th>Estoque atual</th><th>Estoque minimo</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($filial['estoque_critico_itens'] as $item): ?>
                                        <tr>
                                            <td class="branch-name"><?php echo filiais_h($item['nome']); ?></td>
                                            <td><?php echo filiais_h($item['estoque']); ?></td>
                                            <td><?php echo filiais_h($item['estoque_minimo']); ?></td>
                                            <td><span class="pill pill-danger">Critico</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="content-subtitle mb-0">Nenhum produto em nivel critico nesta filial.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($secao === 'funcionarios'): ?>
    <section class="content-card">
        <div class="content-head">
            <div>
                <h2 class="content-title"><i class="fas fa-users text-primary"></i>Funcionarios por Filial</h2>
                <p class="content-subtitle">Perfil da equipa em cada unidade com sinalizacao de colaboradores ativos.</p>
            </div>
            <span class="pill pill-success"><?php echo (int)$totalFuncionarios; ?> colaboradores</span>
        </div>

        <?php if (empty($filiais)): ?>
            <div class="empty-state"><i class="fas fa-users"></i><h4>Nenhuma filial cadastrada</h4><p>Cadastre uma filial para acompanhar a distribuicao da equipa.</p></div>
        <?php else: ?>
            <?php foreach ($filiais as $filial): ?>
                <div class="branch-block">
                    <div class="branch-block-header">
                        <div>
                            <h3 class="content-title mb-0"><i class="fas fa-building text-primary"></i><?php echo filiais_h($filial['nome']); ?></h3>
                            <p class="content-subtitle mb-0"><?php echo (int)$filial['funcionarios_total']; ?> funcionarios cadastrados</p>
                        </div>
                        <span class="pill <?php echo $filial['funcionarios_total'] > 0 ? 'pill-success' : 'pill-warning'; ?>">
                            <?php echo $filial['funcionarios_total'] > 0 ? 'Equipe cadastrada' : 'Sem equipe'; ?>
                        </span>
                    </div>

                    <?php if (!empty($filial['funcionarios_lista'])): ?>
                        <div class="table-wrap">
                            <table class="table-filiais">
                                <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Vendas</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($filial['funcionarios_lista'] as $funcionario): ?>
                                        <tr>
                                            <td class="branch-name"><?php echo filiais_h($funcionario['nome']); ?></td>
                                            <td><?php echo filiais_h($funcionario['email'] ?: '-'); ?></td>
                                            <td><?php echo filiais_h($funcionario['perfil']); ?></td>
                                            <td><?php echo (int)$funcionario['vendas_total']; ?></td>
                                            <td><span class="pill <?php echo $funcionario['ativo'] ? 'pill-success' : 'pill-warning'; ?>"><?php echo $funcionario['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="content-subtitle mb-0">Nenhum funcionario encontrado nesta filial.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
