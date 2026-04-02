<?php

/**
 
   PÁGINA DE ESQUECI A SENHA
 
 */

session_start();
include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

// Se já está logado, redireciona
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci a Senha - Sistema RestaurantESA</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary: #6c5ce7;
            --secondary: #a29bfe;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            overflow-x: hidden;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 30px;
            text-align: center;
            color: white;
        }

        .login-header h2 {
            margin: 0;
            font-weight: 600;
        }

        .login-header p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .login-body {
            padding: 40px;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #5b4cdb;
            transform: translateY(-2px);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 10px;
        }

        @media (max-width: 576px) {
            body {
                align-items: flex-start;
                justify-content: flex-start;
                padding: 10px;
                min-height: 100dvh;
            }

            .login-card {
                max-width: 100%;
                border-radius: 16px;
            }

            .login-header {
                padding: 24px 18px;
            }

            .login-header h2 {
                font-size: 1.35rem;
            }

            .login-body {
                padding: 24px 18px;
            }

            .btn-primary {
                padding: 11px;
            }

            .back-link {
                margin-top: 16px;
            }
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="login-header">
            <h2><i class="fas fa-key me-2"></i>Recuperar Senha</h2>
            <p>Digite seu email para receber o link de recuperação</p>
        </div>

        <div class="login-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form id="formRecuperar" method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="email" required placeholder="Seu email cadastrado">
                </div>

                <button type="submit" class="btn btn-primary w-100" id="btnEnviar">
                    <i class="fas fa-paper-plane me-2"></i>Enviar Link de Recuperação
                </button>
            </form>

            <div id="debugLinkContainer" style="margin-top:12px;"></div>

            <div class="back-link">
                <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Voltar para Login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('formRecuperar').addEventListener('submit', function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const btn = document.getElementById('btnEnviar');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';

            fetch('api/esqueci_senha.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'email=' + encodeURIComponent(email)
                })
                .then(async (response) => {
                    const raw = await response.text();
                    try {
                        return JSON.parse(raw);
                    } catch (e) {
                        throw new Error('Resposta inválida do servidor. Verifique o log do PHP para detalhes.');
                    }
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        document.getElementById('formRecuperar').reset();
                        if (data.debug_link) {
                            const cont = document.getElementById('debugLinkContainer');
                            cont.innerHTML = '<div class="alert alert-info">Link de teste (apenas desenvolvimento): <a href="' + data.debug_link + '" target="_blank">' + data.debug_link + '</a></div>';
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    alert('Erro: ' + err.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });
    </script>
</body>

</html>
