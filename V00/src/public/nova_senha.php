<?php

/*
 
   PÁGINA DE NOVA SENHA
   Usuário define nova senha após clicar no link

 */

session_start();
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/password_reset_helper.php';
include_once __DIR__ . '/../Model/Auth.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';
$tokenValido = false;
$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$reset = null;

// Se ja esta logado e nao veio com token de redefinicao, redireciona normalmente.
if ($token === '' && isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header("Location: dashboard.php");
    exit;
}

// Verificar se token é válido
if (!empty($token)) {
    $reset = password_reset_find_valid_token($db, $token);
    if ($reset) {
        $tokenValido = true;
    }
}

// Se token for inválido ou expirado, redirecionar para login sem mensagem
// Usar sessão para passar mensagem genérica (fluxo mais profissional)
if (!empty($token) && !$tokenValido) {
    $_SESSION['mensagem_login'] = 'Link de recuperação expirado. Solicite um novo link.';
    header("Location: index.php");
    exit;
}

// Processar formulário de nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';
    $confirmarSenha = isset($_POST['confirmar_senha']) ? $_POST['confirmar_senha'] : '';

    if (strlen($senha) < 6) {
        $message = "A senha deve ter pelo menos 6 caracteres.";
        $messageType = "danger";
    } elseif ($senha !== $confirmarSenha) {
        $message = "As senhas não conferem.";
        $messageType = "danger";
    } else {
        try {
            $db->beginTransaction();

            $resetBloqueado = password_reset_find_valid_token($db, $token, true);
            if (!$resetBloqueado) {
                throw new RuntimeException('Token de reset invalido ou expirado.');
            }

            $userIdToken = isset($resetBloqueado['user_id']) ? (int)$resetBloqueado['user_id'] : 0;
            if ($userIdToken > 0) {
                $stmtUsuario = $db->prepare("SELECT id, restaurante_id, super_admin FROM usuarios WHERE id = ? LIMIT 1 FOR UPDATE");
                $stmtUsuario->execute([$userIdToken]);
            } else {
                $stmtUsuario = $db->prepare("SELECT id, restaurante_id, super_admin FROM usuarios WHERE email = ? LIMIT 1 FOR UPDATE");
                $stmtUsuario->execute([$resetBloqueado['email']]);
            }
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                throw new RuntimeException('Usuario do token de reset nao encontrado.');
            }

            $auth = new Auth($db);
            if (!$auth->atualizarSenha((int)$usuario['id'], $senha)) {
                throw new RuntimeException('Falha ao atualizar senha do usuario.');
            }

            if ((int)($usuario['super_admin'] ?? 0) !== 1) {
                $restauranteId = (int)($usuario['restaurante_id'] ?? 0);

                $stmtAtivarUsuario = $db->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
                $stmtAtivarUsuario->execute([(int)$usuario['id']]);

                if ($restauranteId > 0) {
                    $stmtAtivarRestaurante = $db->prepare("UPDATE restaurantes SET status = 'ATIVO' WHERE id = ?");
                    $stmtAtivarRestaurante->execute([$restauranteId]);
                }
            }

            password_reset_mark_used($db, (int)$resetBloqueado['id']);
            password_reset_invalidate_email_tokens($db, (string)$resetBloqueado['email']);
            $db->commit();

            $message = "Senha redefinida com sucesso! Você já pode fazer login.";
            $messageType = "success";
            $tokenValido = false;
            header("Refresh: 3;url=index.php");
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Nova senha page Error: ' . $e->getMessage());
            $message = "Não foi possível redefinir a senha. Solicite um novo link.";
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha - Sistema RestaurantESA</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <!-- Premium UI: fontes, ícones e CSS global do sistema -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <span class="logo-icon"><i class="fas fa-lock"></i></span>
                <h1>Nova Senha</h1>
                <p>Crie uma nova senha segura</p>
            </div>
            <div>
                <?php if ($message): ?>
                    <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i><?php echo $message; ?>
                    </div>
                    <?php if ($messageType === 'success'): ?>
                        <a href="index.php" class="btn-primary" style="margin-top:18px;display:block;text-align:center;">
                            <i class="fas fa-sign-in-alt me-2"></i>Ir para Login
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!$tokenValido && empty($message)): ?>
                    <div class="token-invalido">
                        <i class="fas fa-times-circle" style="font-size:48px;color:var(--danger);"></i>
                        <h4>Link Inválido ou Expirado</h4>
                        <p class="text-muted">O link de recuperação de senha é inválido ou expirou.</p>
                        <a href="esqueci_senha.php" class="btn-primary" style="margin-top:18px;display:inline-block;">
                            <i class="fas fa-redo me-2"></i>Solicitar Novo Link
                        </a>
                    </div>
                <?php elseif ($tokenValido): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-shield-alt me-2"></i>Crie sua nova senha para ativar o acesso. Este link é válido por tempo limitado.
                    </div>
                    <form id="formNovaSenha" method="POST">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="senha">Nova Senha</label>
                            <input type="password" class="form-control" name="senha" id="senha" required placeholder="Mínimo 6 caracteres" minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" name="confirmar_senha" id="confirmar_senha" required placeholder="Digite a senha novamente" minlength="6">
                        </div>
                        <button type="submit" class="btn-primary" id="btnSalvar">
                            <i class="fas fa-save me-2"></i>Salvar Nova Senha
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!isset($message) || $messageType !== 'success'): ?>
                    <div class="login-footer">
                        <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Voltar para Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        <?php if ($tokenValido): ?>
            // Validação do formulário (frontend)
            document.getElementById('formNovaSenha').addEventListener('submit', function(e) {
                const senha = document.getElementById('senha').value;
                const confirmar = document.getElementById('confirmar_senha').value;
                if (senha !== confirmar) {
                    e.preventDefault();
                    alert('As senhas não conferem!');
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>

