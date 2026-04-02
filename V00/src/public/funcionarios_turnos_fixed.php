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
$turnos = $turnoService->listar($restauranteId);
$ativos = $turnoService->ativosHoje($restauranteId);

$stmt_usuarios = $db->prepare("SELECT id, nome FROM usuarios WHERE restaurante_id = :rid AND ativo = 1 ORDER BY nome");
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
        /* [previous styles unchanged] */
    </style>
</head>

<body class="premium-ui">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <h1 class="mb-1"><i class="fas fa-clock text-warning me-2"></i>Gestão de Turnos</h1>
                <p class="text-muted mb-0">Planeje manhã/noite e veja quem está ativo</p>
            </div>
            <button class="btn btn-primary" onclick="carregarTurnos()">
                <i class="fas fa-sync-alt me-1"></i>Atualizar
            </button>
        </div>

        <div class="row g-4">
            <!-- Widget Ativos Hoje -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <h6 class="mb-0"><i class="fas fa-users text-success me-1"></i>👥 Quem está ativo hoje</h6>
                        <span class="badge bg-success"><?php echo $ativos->rowCount(); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div id="listaAtivos">
                            <?php if ($ativos->rowCount() == 0): ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                                    <p>Nenhum funcionário ativo</p>
                                </div>
                            <?php else: ?>
                                <?php $ativos->execute();
                                while ($ativo = $ativos->fetch()): ?>
                                    <div class="d-flex align-items-center p-3 border-bottom">
                                        <div class="avatar me-3"><?php echo substr(($ativo['nome'] ?? 'Sem nome'), 0, 2); ?></div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo htmlspecialchars($ativo['nome'] ?? 'Sem nome'); ?></div>
                                            <small class="text-muted"><?php echo strtoupper($ativo['turno']); ?> • <?php echo date('H:i', strtotime($ativo['hora_entrada'])); ?></small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- resto do HTML igual ao original -->
            <!-- ... (rest of the HTML remains the same) ... -->
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="funcionarios_turnos.js"></script>
</body>

</html>

