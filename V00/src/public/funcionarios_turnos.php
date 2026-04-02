<?php

/*
 Gestão de Horários - Funcionários Turnos
 ADMIN apenas - PREMIUM UI
 */
include_once __DIR__ . '/../config/auth_check.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/restaurante_context.php';
include_once __DIR__ . '/../Model/Turno.php';
include_once __DIR__ . '/../Service/TurnoService.php';

requirePermissionOrRedirect(['ADMIN']);

$database = new Database();
$db = $database->getConnection();
$turnoService = new TurnoService($database);
$restauranteId = session_restaurante_contexto_id();
$ativos = $turnoService->ativosHojeArray($restauranteId);
$metricas = $turnoService->obterMetricasDashboard($restauranteId);
$auditoria = $turnoService->listarAuditoria($restauranteId, 12);

$stmt_usuarios = $db->prepare("SELECT id, nome, perfil FROM usuarios WHERE restaurante_id = :rid AND ativo = 1 ORDER BY nome");
$stmt_usuarios->execute(['rid' => $restauranteId]);
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt" class="premium-ui">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Turnos - Restaurante SaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="css/premium.css" rel="stylesheet">
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #F7931E;
            --dark: #0f0f23;
            --dark-2: #1a1a2e;
            --dark-3: #16213e;
            --text: #1e293b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        html,
        body {
            overflow-x: hidden;
        }

        body.premium-ui {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
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
            padding: 24px;
            min-height: 100vh;
            background: var(--bg);
        }

        .top-bar {
            background: white;
            padding: 24px;
            border-radius: 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            background: #fff;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B35, #F7931E);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
        }

        .turnos-topbar-main {
            min-width: 0;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .top-bar-right,
            .top-bar .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="premium-ui">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="sidebar-topbar-main turnos-topbar-main">
                <h1 class="mb-1"><i class="fas fa-clock text-warning me-2"></i>Gestão de Turnos</h1>
                <p class="text-muted mb-0">Planeje manhã/noite e veja quem está ativo</p>
            </div>
            <div class="top-bar-right">
                <button type="button" class="btn btn-primary" onclick="carregarTurnos()">
                    <i class="fas fa-sync-alt me-1"></i>Atualizar
                </button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <div id="turnoAdminAlert" class="alert" style="display:none;"></div>
            </div>

            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small mb-2">Funcionários ativos</div>
                        <h2 class="mb-0" id="metricaAtivos"><?php echo (int)($metricas['funcionarios_ativos'] ?? 0); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small mb-2">Turnos não encerrados</div>
                        <h2 class="mb-0 text-danger" id="metricaNaoEncerrados"><?php echo (int)($metricas['turnos_nao_encerrados'] ?? 0); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small mb-2">Tempo total em turno</div>
                        <h2 class="mb-0" id="metricaTempoTurno"><?php echo htmlspecialchars((string)($metricas['tempo_turno_formatado'] ?? '0 min')); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small mb-2">Online / Offline</div>
                        <h2 class="mb-0"><span id="metricaOnline"><?php echo (int)($metricas['online'] ?? 0); ?></span> / <span class="text-muted" id="metricaOffline"><?php echo (int)($metricas['offline'] ?? 0); ?></span></h2>
                    </div>
                </div>
            </div>

            <!-- Widget Ativos Hoje -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0"><i class="fas fa-users text-success me-1"></i>👥 Quem está ativo hoje</h6>
                        <span class="badge bg-success" id="activeTodayCount"><?php echo count($ativos); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div id="listaAtivos">
                            <?php if (count($ativos) === 0): ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                                    <p>Nenhum funcionário ativo</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($ativos as $ativo): ?>
                                    <?php $nomeAtivo = trim((string)($ativo['funcionario_nome'] ?? $ativo['nome'] ?? 'Sem nome')); ?>
                                    <div class="d-flex align-items-center p-3 border-bottom">
                                        <div class="avatar me-3"><?php echo strtoupper(substr($nomeAtivo, 0, 2)); ?></div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo htmlspecialchars($nomeAtivo); ?></div>
                                            <small class="text-muted"><?php echo strtoupper($ativo['turno']); ?> • <?php echo !empty($ativo['hora_entrada']) ? date('H:i', strtotime($ativo['hora_entrada'])) : '--:--'; ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adicionar Turno -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-plus text-primary me-1"></i>Adicionar Novo Turno</h6>
                    </div>
                    <div class="card-body">
                        <form id="formNovoTurno">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-600">📅 Data</label>
                                    <input type="date" name="data" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">👤 Funcionário</label>
                                    <select name="usuario_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($usuarios as $u): ?>
                                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nome'] . ' • ' . strtoupper($u['perfil'])); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-600">🕐 Turno</label>
                                    <select name="turno" id="novoTurnoTipo" class="form-select" required>
                                        <option value="manha">Manhã ☀️</option>
                                        <option value="noite">Noite 🌙</option>
                                        <option value="integral">Integral 🕛</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-600">⏰ Entrada</label>
                                    <input type="time" name="hora_entrada" id="novoTurnoHoraEntrada" class="form-control" value="08:00">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-600">📝 Motivo / Observação</label>
                                    <input type="text" name="motivo" class="form-control" placeholder="Obrigatório para intervenção manual do caixa/admin">
                                </div>
                                <div class="col-md-3 pt-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        ➕ Adicionar
                                    </button>
                                </div>
                                <div class="col-md-3 pt-2">
                                    <button type="button" class="btn btn-outline-danger w-100" onclick="fecharTurnoManualSelecionado()">
                                        <i class="fas fa-user-slash me-1"></i>Encerrar Manualmente
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabela Turnos -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list text-info me-1"></i>📋 Todos os Turnos</h6>
                        <div>
                            <select id="filtroData" class="form-select form-select-sm d-inline-block w-auto">
                                <option value="">Todos</option>
                                <option value="hoje">Hoje</option>
                                <option value="amanha">Amanhã</option>
                                <option value="semana">Semana</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="exportarTurnos()">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Funcionário</th>
                                    <th>Turno</th>
                                    <th>Entrada/Saída</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="turnosLista">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-clock fa-2x mb-2 opacity-50"></i>
                                        <div>Carregando turnos...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="editarTurnoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-pen text-info me-2"></i>Editar Turno</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="formEditarTurno">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editarTurnoId">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-600">📅 Data</label>
                                <input type="date" name="data" id="editarTurnoData" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-600">👤 Funcionário</label>
                                <select name="usuario_id" id="editarTurnoUsuario" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nome'] . ' • ' . strtoupper($u['perfil'])); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-600">📌 Status</label>
                                <select name="status" id="editarTurnoStatus" class="form-select" required>
                                    <option value="planejado">Planejado</option>
                                    <option value="ativo">Ativo</option>
                                    <option value="finalizado">Finalizado</option>
                                    <option value="ausente">Ausente</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-600">🕐 Turno</label>
                                <select name="turno" id="editarTurnoTipo" class="form-select" required>
                                    <option value="manha">Manhã ☀️</option>
                                    <option value="noite">Noite 🌙</option>
                                    <option value="integral">Integral 🕛</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-600">⏰ Entrada</label>
                                <input type="time" name="hora_entrada" id="editarTurnoHoraEntrada" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-600">⏱️ Saída</label>
                                <input type="time" name="hora_saida" id="editarTurnoHoraSaida" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-600">📝 Observações</label>
                                <textarea name="observacoes" id="editarTurnoObservacoes" class="form-control" rows="3" placeholder="Observações opcionais"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-600">🛡️ Motivo da correção</label>
                                <input type="text" name="motivo" id="editarTurnoMotivo" class="form-control" placeholder="Descreva o motivo da correção administrativa">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSalvarEdicaoTurno">
                            <i class="fas fa-save me-1"></i>Salvar alterações
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-shield-alt text-danger me-1"></i>Auditoria de Turnos</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Quando</th>
                                    <th>Responsável</th>
                                    <th>Funcionário</th>
                                    <th>Ação</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody id="auditoriaLista">
                                <?php if (empty($auditoria)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Nenhum log de auditoria</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($auditoria as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)$item['criado_em']); ?></td>
                                            <td><?php echo htmlspecialchars((string)($item['responsavel_nome'] ?? '—')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($item['funcionario_nome'] ?? '—')); ?></td>
                                            <td><?php echo htmlspecialchars((string)$item['tipo_acao']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$item['motivo']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="funcionarios_turnos.js"></script>
    <script>
        setInterval(function() {
            fetch('api/online_ping.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        }, 60000);
    </script>
</body>

</html>

