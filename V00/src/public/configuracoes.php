<?php
// Proteção da página - apenas ADMIN
include_once __DIR__ . '/../config/auth_check.php';

// Verificar restaurante_id
$restaurante_id = session_restaurante_contexto_id();
$restauranteCapabilityId = session_restaurante_capability_id();
if ($restaurante_id <= 0) {
    header("Location: index.php?erro=sem_restaurante");
    exit;
}

// Verificar se é admin
if (($_SESSION['perfil'] ?? '') !== 'ADMIN') {
    header("Location: dashboard.php");
    exit;
}

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/plano_check.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM restaurantes WHERE id = :id LIMIT 1";
$stmt  = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['restaurante_id']);
$stmt->execute();
$restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

// Garantir que $restaurante é um array válido para evitar warnings
if (!$restaurante || !is_array($restaurante)) {
    $restaurante = [
        'nome' => '',
        'telefone' => '',
        'endereco' => '',
        'cidade' => '',
        'nuit' => '',
        'logo' => '',
        'plano' => 'BASICO',
        'status' => 'INATIVO',
        'data_fim' => date('Y-m-d', strtotime('+30 days'))
    ];
}

$mensagem = '';
$tipo_msg = '';

// Verificar se há uma seção específica para mostrar
$secao = $_GET['secao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade   = trim($_POST['cidade'] ?? '');
    $nuit     = trim($_POST['nuit'] ?? '');
    $logoAtual = trim((string)($restaurante['logo'] ?? ''));
    $logoNovo = $logoAtual;
    $logoTmpAbsolute = null;

    if (empty($nome)) {
        $mensagem = 'O nome do restaurante é obrigatório.';
        $tipo_msg = 'danger';
    } else {
        if (isset($_FILES['logo']) && (int)($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $logoError = (int)($_FILES['logo']['error'] ?? UPLOAD_ERR_OK);
            if ($logoError !== UPLOAD_ERR_OK) {
                $mensagem = 'Falha no envio do logotipo. Tente novamente.';
                $tipo_msg = 'danger';
            } else {
                $logoExt = strtolower(pathinfo((string)($_FILES['logo']['name'] ?? ''), PATHINFO_EXTENSION));
                $logoAllowedExt = ['jpg', 'jpeg', 'png', 'webp'];
                $logoAllowedMime = ['image/jpeg', 'image/png', 'image/webp'];
                $logoMaxSize = 4 * 1024 * 1024;

                if (!in_array($logoExt, $logoAllowedExt, true)) {
                    $mensagem = 'Logotipo inválido. Envie JPG, PNG ou WEBP.';
                    $tipo_msg = 'danger';
                } elseif ((int)($_FILES['logo']['size'] ?? 0) > $logoMaxSize) {
                    $mensagem = 'Logotipo excede 4MB.';
                    $tipo_msg = 'danger';
                } else {
                    $logoMime = '';
                    if (function_exists('finfo_open')) {
                        $finfoLogo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfoLogo) {
                            $logoMime = finfo_file($finfoLogo, $_FILES['logo']['tmp_name']);
                            finfo_close($finfoLogo);
                        }
                    }

                    if ($logoMime === '' && function_exists('mime_content_type')) {
                        $logoMime = mime_content_type($_FILES['logo']['tmp_name']) ?: '';
                    }

                    if ($logoMime !== '' && !in_array($logoMime, $logoAllowedMime, true)) {
                        $mensagem = 'Tipo de arquivo inválido para o logotipo.';
                        $tipo_msg = 'danger';
                    } else {
                        $logoDirAbsolute = __DIR__ . '/uploads/restaurantes/logos';
                        if (!is_dir($logoDirAbsolute) && !mkdir($logoDirAbsolute, 0755, true)) {
                            $mensagem = 'Não foi possível preparar o diretório do logotipo.';
                            $tipo_msg = 'danger';
                        } else {
                            try {
                                $logoFilename = sprintf(
                                    'logo_%s_%s.%s',
                                    date('YmdHis'),
                                    bin2hex(random_bytes(6)),
                                    $logoExt
                                );
                            } catch (Throwable $e) {
                                $logoFilename = '';
                            }

                            if ($logoFilename === '') {
                                $mensagem = 'Não foi possível gerar um nome seguro para o logotipo.';
                                $tipo_msg = 'danger';
                            } else {
                                $logoTmpAbsolute = $logoDirAbsolute . '/' . $logoFilename;
                                $logoNovo = 'uploads/restaurantes/logos/' . $logoFilename;

                                $uploadError = null;
                                $allowedExt = ['png', 'jpg', 'jpeg'];
                                $allowedMime = ['image/png', 'image/jpeg'];
                                if (!security_validate_upload($_FILES['logo'], $allowedExt, $allowedMime, 2 * 1024 * 1024, $uploadError)) {
                                    $mensagem = $uploadError ?: 'Arquivo inválido.';
                                    $tipo_msg = 'danger';
                                    $logoTmpAbsolute = null;
                                    $logoNovo = $logoAtual;
                                } elseif (!move_uploaded_file($_FILES['logo']['tmp_name'], $logoTmpAbsolute)) {
                                    $mensagem = 'Falha ao salvar o logotipo.';
                                    $tipo_msg = 'danger';
                                    $logoTmpAbsolute = null;
                                    $logoNovo = $logoAtual;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($tipo_msg !== 'danger') {
        $query_upd = "UPDATE restaurantes SET nome = :nome, telefone = :telefone, endereco = :endereco, cidade = :cidade, nuit = :nuit, logo = :logo WHERE id = :id";
        $stmt_upd  = $db->prepare($query_upd);
        $stmt_upd->bindParam(':nome',     $nome);
        $stmt_upd->bindParam(':telefone', $telefone);
        $stmt_upd->bindParam(':endereco', $endereco);
        $stmt_upd->bindParam(':cidade',   $cidade);
        $stmt_upd->bindParam(':nuit',     $nuit);
        $stmt_upd->bindParam(':logo',     $logoNovo);
        $stmt_upd->bindParam(':id',       $_SESSION['restaurante_id']);

        if ($stmt_upd->execute()) {
            if ($logoTmpAbsolute && $logoAtual && $logoAtual !== $logoNovo) {
                $logoAtualAbsolute = __DIR__ . '/' . ltrim(str_replace('\\', '/', $logoAtual), '/');
                if (is_file($logoAtualAbsolute)) {
                    @unlink($logoAtualAbsolute);
                }
            }
            $mensagem = 'Configurações salvas com sucesso!';
            $tipo_msg = 'success';
            $stmt->execute();
            $restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

            // Garantir que $restaurante continua sendo um array válido
            if (!$restaurante || !is_array($restaurante)) {
                $restaurante = [
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'endereco' => $endereco,
                    'cidade' => $cidade,
                    'nuit' => $nuit,
                    'logo' => $logoNovo,
                    'plano' => 'BASICO',
                    'status' => 'INATIVO',
                    'data_fim' => date('Y-m-d', strtotime('+30 days'))
                ];
            }
        } else {
            if ($logoTmpAbsolute && is_file($logoTmpAbsolute)) {
                @unlink($logoTmpAbsolute);
            }
            $mensagem = 'Erro ao salvar configurações.';
            $tipo_msg = 'danger';
        }
    }
}

// Valores padrão seguros - buscar do banco de dados
$nome_restaurante = $restaurante['nome'] ?? '';
$telefone_restaurante = $restaurante['telefone'] ?? '';
$endereco_restaurante = $restaurante['endereco'] ?? '';
$cidade_restaurante = $restaurante['cidade'] ?? '';
$nuit_restaurante = $restaurante['nuit'] ?? '';
$logo_restaurante = trim((string)($restaurante['logo'] ?? ''));

// Usar o plano efetivo do sistema de planos
$dados_plano = plano_get_dados($restauranteCapabilityId);
$plano_atual = plano_normalizar_nome($dados_plano['plano_nome'] ?? ($restaurante['plano'] ?? 'BASICO'));

// Carregar configuração dos planos
$planos_config = require __DIR__ . '/../config/planos.php';

$basico_e_gratis = (bool)($planos_config['BASICO']['e_gratis'] ?? false);
$basico_preco_mensal = (float)($planos_config['BASICO']['precos']['mensal'] ?? 0);
$basico_preco_trimestral = (float)($planos_config['BASICO']['precos']['trimestral'] ?? 0);
$basico_preco_anual = (float)($planos_config['BASICO']['precos']['anual'] ?? 0);
$status_atual = $dados_plano['status'] ?? ($restaurante['status'] ?? 'INATIVO');
$data_fim = $dados_plano['data_fim'] ?? ($restaurante['data_fim'] ?? date('Y-m-d', strtotime('+30 days')));
$dias_restantes = ceil((strtotime($data_fim) - time()) / 86400);

// Atualizar sessão com o plano do banco
$_SESSION['plano'] = $plano_atual;
$plano_nome = $planos_config[$plano_atual]['nome'] ?? $plano_atual;
$recursos_plano_atual = plano_get_resumo_recursos($plano_atual);
$recursos_planos_catalogo = [
    'BASICO' => plano_get_resumo_recursos('BASICO'),
    'PROFISSIONAL' => plano_get_resumo_recursos('PROFISSIONAL'),
    'EMPRESARIAL' => plano_get_resumo_recursos('EMPRESARIAL'),
];

// Verificar se o plano atual permite recursos de backup
$tem_backup = plano_tem_funcionalidade_db($restauranteCapabilityId, 'backup_automatico')
    || plano_tem_funcionalidade_db($restauranteCapabilityId, 'backup_diario')
    || plano_tem_funcionalidade_db($restauranteCapabilityId, 'backup_manual')
    || plano_tem_funcionalidade_db($restauranteCapabilityId, 'download_banco');
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Botão de menu mobile */
        .menu-toggle-btn {
            display: none;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 22px;
            margin-right: 12px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 2001;
        }

        @media (max-width: 992px) {
            .menu-toggle-btn {
                display: inline-flex;
            }

            .sidebar {
                transition: left 0.3s;
                left: 0;
            }

            .sidebar.hide-mobile {
                left: -320px;
            }

            .main-content.menu-closed {
                margin-left: 0 !important;
            }
        }

        /* Fallback para quando Font Awesome não carregar */
        .fas,
        .far,
        .fal,
        .fab {
            font-family: 'Font Awesome 6 Free', 'Font Awesome 6 Pro', 'FontAwesome' !important;
            font-weight: 900;
            display: inline-block;
            width: 1.25em;
            text-align: center;
        }

        /* Garante que ícones apareçam mesmo com cache antigo */
        .menu-item i::before,
        .sidebar-brand i::before {
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }
    </style>
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
            padding: 0;
            min-height: 100vh;
            background: var(--bg);
        }

        .top-bar {
            background: white;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--primary);
        }

        .content-area {
            padding: 32px;
        }

        .plan-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            padding: 24px;
            color: white;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .plan-card>* {
            position: relative;
            z-index: 1;
        }

        .plan-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }

        .plan-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .plan-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .plan-details {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .plan-detail {
            display: flex;
            flex-direction: column;
        }

        .plan-detail-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-detail-value {
            font-size: 18px;
            font-weight: 700;
        }

        .plan-days {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 42px;
            font-weight: 700;
        }

        .plan-days-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: none;
            overflow: hidden;
            transition: all 0.3s;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 20px 24px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 18px;
        }

        .card-body {
            padding: 24px;
        }

        .upgrade-section .card-header {
            background: linear-gradient(180deg, #fff, #fff8f4);
            border-bottom: 1px solid #ffe2d2;
        }

        .plans-grid {
            row-gap: 20px;
        }

        .plan-col {
            display: flex;
        }

        .plan-option {
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            transition: all 0.35s;
            width: 100%;
            min-height: 560px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .plan-option:hover {
            border-color: var(--primary);
            transform: translateY(-6px);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
        }

        .plan-option.selected {
            border-color: var(--primary);
            background: linear-gradient(180deg, #fff, #fff8f3);
            box-shadow: 0 12px 26px rgba(255, 107, 53, 0.18);
        }

        .plan-option.current {
            border-color: var(--success);
            background: linear-gradient(180deg, #ffffff, #f4fffb);
        }

        .plan-option-icon {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 16px;
        }

        .plan-option-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1;
        }

        .plan-option-price {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 42px;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 14px;
        }

        .plan-option-price span {
            font-size: 26px;
            color: var(--text-muted);
            font-family: 'Outfit', sans-serif;
            font-weight: 500;
            margin-left: 4px;
        }

        .plan-option-features {
            list-style: none;
            padding: 0;
            margin: 14px 0 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
        }

        .plan-option-features li {
            padding: 8px 10px;
            font-size: 14px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 10px;
            border: 1px solid #edf2f7;
            background: #f8fafc;
        }

        .plan-option-features li i {
            color: var(--success);
        }

        .badge-custom {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-primary {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }

        .btn-outline-secondary {
            border: 2px solid var(--border);
            color: var(--text-light);
            background: transparent;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline-secondary:hover {
            border-color: var(--text-light);
            color: var(--text);
            background: var(--bg);
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid var(--border);
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Outfit', sans-serif;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }

        .alert {
            border-radius: 12px;
            padding: 16px 20px;
            border: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--bg);
            border: none;
            padding: 14px 16px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .table tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--bg);
        }

        .modal-content {
            border: none;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
            border-radius: 24px 24px 0 0;
        }

        .modal-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 20px;
        }

        .modal-body {
            padding: 28px;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-light);
            font-size: 14px;
        }

        .info-value {
            font-weight: 600;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .content-area {
                padding: 20px;
            }

            .plan-option {
                min-height: auto;
            }

            .plan-option-name {
                font-size: 30px;
            }

            .plan-option-price {
                font-size: 34px;
            }

            .plan-option-price span {
                font-size: 20px;
            }
        }

        @media (max-width: 991px) {
            .top-bar {
                padding: 16px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .page-title {
                font-size: 20px;
            }

            .content-area {
                padding: 20px;
            }

            .plan-card {
                padding: 20px;
            }

            .plan-details {
                gap: 16px;
            }

            .plan-col {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .plan-option {
                min-height: auto;
                padding: 20px;
            }

            .plan-option-name {
                font-size: 30px;
            }

            .plan-option-price {
                font-size: 34px;
            }

            .plan-option-price span {
                font-size: 20px;
            }

            .row.g-4.mt-2>.col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .card-header {
                padding: 18px 20px;
            }

            .card-body {
                padding: 20px;
            }

            .modal-dialog.modal-lg {
                max-width: calc(100% - 20px);
                margin: 10px;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                padding: 12px 16px;
                gap: 8px;
            }

            .page-title {
                font-size: 18px;
                gap: 8px;
            }

            .content-area {
                padding: 12px;
            }

            .plan-card,
            .card,
            .plan-option {
                border-radius: 18px;
            }

            .plan-card {
                padding: 16px;
            }

            .plan-icon {
                width: 52px;
                height: 52px;
                font-size: 24px;
            }

            .plan-name {
                font-size: 20px;
            }

            .plan-details {
                gap: 12px;
            }

            .plan-detail-value {
                font-size: 16px;
            }

            .plan-days {
                font-size: 32px;
            }

            .card-header {
                padding: 16px;
            }

            .card-body {
                padding: 16px;
            }

            .plan-option {
                padding: 16px;
            }

            .plan-option-icon {
                width: 60px;
                height: 60px;
                font-size: 26px;
            }

            .plan-option-name {
                font-size: 24px;
            }

            .plan-option-price {
                font-size: 30px;
            }

            .plan-option-price span {
                font-size: 16px;
            }

            .plan-option-features li {
                font-size: 13px;
                padding: 7px 9px;
            }

            .btn-primary,
            .btn-outline-primary,
            .btn-outline-secondary {
                width: 100%;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding-left: 16px;
                padding-right: 16px;
            }

            .modal-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .modal-footer .btn {
                width: 100%;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 14px;
                font-size: 12px;
                white-space: normal;
            }
        }
    </style>
</head>

<body class="premium-ui">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

            <!-- MAIN CONTENT -->
            <main class="main-content col-md-9 ms-sm-auto col-lg-10">
                <div class="top-bar">
                    <h1 class="page-title"><i class="fas fa-cog"></i> Configurações</h1>
                </div>

                <div class="content-area">
                    <?php if ($mensagem): ?>
                        <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $tipo_msg == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- PLANO ATUAL -->
                    <?php if ($secao !== 'plano'): ?>
                        <div class="plan-card mb-4">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                <div>
                                    <div class="plan-icon">👑</div>
                                    <div class="plan-name">Plano <?php echo htmlspecialchars($plano_nome); ?></div>
                                    <div class="plan-status">
                                        <i class="fas fa-<?php echo $status_atual == 'ATIVO' ? 'check-circle' : 'clock'; ?>"></i>
                                        <?php echo htmlspecialchars($status_atual); ?>
                                    </div>
                                    <div class="plan-details mt-3">
                                        <div class="plan-detail">
                                            <span class="plan-detail-label">Validade</span>
                                            <span class="plan-detail-value"><?php echo date('d/m/Y', strtotime($data_fim)); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-md-end">
                                    <div class="plan-days"><?php echo max(0, $dias_restantes); ?></div>
                                    <div class="plan-days-label">dias restantes</div>
                                    <?php if ($plano_atual !== 'EMPRESARIAL'): ?>
                                        <a href="configuracoes.php?secao=plano" class="btn btn-light mt-3">
                                            <i class="fas fa-arrow-up me-2"></i>Upgrade
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="plan-detail-label mb-2">Recursos do Plano</div>
                                <ul class="list-unstyled mb-0" style="font-size: 14px; color: var(--text-light);">
                                    <?php foreach ($recursos_plano_atual as $recurso): ?>
                                        <li class="mb-1"><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($recurso); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- SEÇÃO PLANO (quando acessar via upgrade) -->
                    <?php if ($secao === 'plano'): ?>
                        <div class="card mb-4 upgrade-section" id="plano">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div><i class="fas fa-crown me-2 text-warning"></i>Planos de Assinatura</div>
                                <a href="configuracoes.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-1"></i> Fechar
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="row g-4 plans-grid align-items-stretch">
                                    <!-- Plano Básico -->
                                    <div class="col-lg-4 col-md-4 plan-col">
                                        <div class="plan-option <?php echo $plano_atual === 'BASICO' ? 'current selected' : ''; ?>">
                                            <div class="plan-option-icon" style="background: linear-gradient(135deg, #6c757d, #343a40); color: white;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="plan-option-name">Básico</div>
                                            <div class="plan-option-price">
                                                <?php if ($basico_e_gratis || $basico_preco_mensal <= 0): ?>
                                                    Grátis<span>/mês</span>
                                                <?php else: ?>
                                                    <?php echo number_format($basico_preco_mensal, 0, ',', '.'); ?><span> MZN/mês</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Tri: <?php echo number_format($basico_preco_trimestral, 0, ',', '.'); ?> MZN | Anual: <?php echo number_format($basico_preco_anual, 0, ',', '.'); ?> MZN
                                            </small>

                                            <ul class="plan-option-features">
                                                <?php foreach (($recursos_planos_catalogo['BASICO'] ?? []) as $recurso): ?>
                                                    <li class="text-success"><i class="fas fa-check"></i> <?php echo htmlspecialchars($recurso); ?></li>
                                                <?php endforeach; ?>
                                            </ul>

                                            <?php if ($plano_atual === 'BASICO'): ?>
                                                <span class="badge-custom badge-success">Plano Atual</span>
                                            <?php elseif ($plano_atual === 'PROFISSIONAL' || $plano_atual === 'EMPRESARIAL'): ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" disabled>
                                                    <i class="fas fa-arrow-down me-1"></i> Plano Inferior
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCompraPlano" onclick="selecionarPlano('BASICO')">
                                                    <i class="fas fa-check me-1"></i> Ativar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Plano Profissional -->
                                    <div class="col-lg-4 col-md-4 plan-col">
                                        <div class="plan-option <?php echo $plano_atual === 'PROFISSIONAL' ? 'current selected' : ''; ?>" style="border-color: #17a2b8;">
                                            <div class="plan-option-icon" style="background: linear-gradient(135deg, #17a2b8, #0dcaf0); color: white;">
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <div class="plan-option-name">Profissional</div>
                                            <div class="plan-option-price"><?php echo number_format((float)($planos_config['PROFISSIONAL']['precos']['mensal'] ?? 0), 0, ',', '.'); ?><span> MZN/mês</span></div>
                                            <small class="text-muted d-block mt-1">
                                                Tri: <?php echo number_format((float)($planos_config['PROFISSIONAL']['precos']['trimestral'] ?? 0), 0, ',', '.'); ?> MZN | Anual: <?php echo number_format((float)($planos_config['PROFISSIONAL']['precos']['anual'] ?? 0), 0, ',', '.'); ?> MZN
                                            </small>

                                            <ul class="plan-option-features">
                                                <?php foreach (($recursos_planos_catalogo['PROFISSIONAL'] ?? []) as $recurso): ?>
                                                    <li class="text-success"><i class="fas fa-check"></i> <?php echo htmlspecialchars($recurso); ?></li>
                                                <?php endforeach; ?>
                                            </ul>

                                            <?php if ($plano_atual === 'PROFISSIONAL'): ?>
                                                <span class="badge-custom badge-success">Plano Atual</span>
                                            <?php elseif ($plano_atual === 'EMPRESARIAL'): ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" disabled>
                                                    <i class="fas fa-arrow-down me-1"></i> Plano Inferior
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCompraPlano" onclick="selecionarPlano('PROFISSIONAL')">
                                                    <i class="fas fa-shopping-cart me-1"></i> Comprar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Plano Empresarial -->
                                    <div class="col-lg-4 col-md-4 plan-col">
                                        <div class="plan-option <?php echo $plano_atual === 'EMPRESARIAL' ? 'current selected' : ''; ?>" style="border-color: #FF6B35;">
                                            <div class="plan-option-icon" style="background: linear-gradient(135deg, #FF6B35, #F7931E); color: white;">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <div class="plan-option-name">Empresarial</div>
                                            <div class="plan-option-price"><?php echo number_format((float)($planos_config['EMPRESARIAL']['precos']['mensal'] ?? 0), 0, ',', '.'); ?><span> MZN/mês</span></div>
                                            <small class="text-muted d-block mt-1">
                                                Tri: <?php echo number_format((float)($planos_config['EMPRESARIAL']['precos']['trimestral'] ?? 0), 0, ',', '.'); ?> MZN | Anual: <?php echo number_format((float)($planos_config['EMPRESARIAL']['precos']['anual'] ?? 0), 0, ',', '.'); ?> MZN
                                            </small>

                                            <ul class="plan-option-features">
                                                <?php foreach (($recursos_planos_catalogo['EMPRESARIAL'] ?? []) as $recurso): ?>
                                                    <li class="text-success"><i class="fas fa-check"></i> <?php echo htmlspecialchars($recurso); ?></li>
                                                <?php endforeach; ?>
                                            </ul>

                                            <?php if ($plano_atual === 'EMPRESARIAL'): ?>
                                                <span class="badge-custom badge-success">Plano Atual</span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCompraPlano" onclick="selecionarPlano('EMPRESARIAL')">
                                                    <i class="fas fa-rocket me-1"></i> Comprar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-4 border-0 shadow-sm">
                                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Histórico de Compras</div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnExportarHistoricoCsv">
                                            <i class="fas fa-file-csv me-1"></i> Exportar CSV
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-lg-5">
                                                <input type="text" class="form-control" id="filtroHistoricoTexto" placeholder="Pesquisar por plano, método ou status">
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <select class="form-select" id="filtroHistoricoCiclo">
                                                    <option value="">Todos os ciclos</option>
                                                    <option value="MENSAL">Mensal</option>
                                                    <option value="TRIMESTRAL">Trimestral</option>
                                                    <option value="ANUAL">Anual</option>
                                                </select>
                                            </div>
                                            <div class="col-lg-4 col-md-6">
                                                <select class="form-select" id="filtroHistoricoStatus">
                                                    <option value="">Todos os status</option>
                                                    <option value="PENDENTE">Pendente</option>
                                                    <option value="APROVADO">Aprovado</option>
                                                    <option value="REJEITADO">Rejeitado</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="table-responsive" id="historicoCompras">
                                            <p class="text-center text-muted py-4 mb-0">Carregando...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4 mt-2">
                        <!-- DADOS DO RESTAURANTE -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <i class="fas fa-store me-2"></i>Dados do Restaurante
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="configuracoes.php" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label">Nome do Restaurante *</label>
                                            <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($nome_restaurante); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telefone</label>
                                            <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($telefone_restaurante); ?>" placeholder="+258 84 000 0000">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Endereço</label>
                                            <input type="text" name="endereco" class="form-control" value="<?php echo htmlspecialchars($endereco_restaurante); ?>" placeholder="Av. Eduardo Mondlane, 123">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Cidade</label>
                                            <input type="text" name="cidade" class="form-control" value="<?php echo htmlspecialchars($cidade_restaurante); ?>" placeholder="Maputo">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">NUIT</label>
                                            <input type="text" name="nuit" class="form-control" value="<?php echo htmlspecialchars($nuit_restaurante); ?>" placeholder="400000000">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Logotipo do Restaurante</label>
                                            <input
                                                type="file"
                                                name="logo"
                                                id="logoRestauranteInput"
                                                class="form-control"
                                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                                onchange="previewLogoRestauranteConfig(this)">
                                            <small class="text-muted d-block mt-2">Formatos aceites: JPG, PNG ou WEBP. Tamanho máximo: 4MB.</small>
                                            <div class="mt-3 d-flex flex-column gap-2">
                                                <img
                                                    id="logoPreviewImage"
                                                    src="<?php echo htmlspecialchars($logo_restaurante !== '' ? $logo_restaurante : '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    alt="Logotipo do restaurante"
                                                    style="<?php echo $logo_restaurante !== '' ? 'display:block;' : 'display:none;'; ?>max-width: 180px; max-height: 180px; border-radius: 18px; border: 1px solid #e2e8f0; background: #fff; object-fit: contain; padding: 10px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);">
                                                <div class="small text-muted" id="logoPreviewInfo">
                                                    <?php echo $logo_restaurante !== '' ? htmlspecialchars(basename($logo_restaurante), ENT_QUOTES, 'UTF-8') : 'Nenhum logotipo enviado para este restaurante.'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-save me-2"></i>Salvar Alterações
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MINHA CONTA -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-user me-2"></i>Minha Conta
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="user-avatar me-3" style="width: 60px; height: 60px; font-size: 22px;">
                                            <?php echo substr($_SESSION['nome'], 0, 2); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="font-size: 18px;"><?php echo htmlspecialchars($_SESSION['nome']); ?></div>
                                            <span class="badge-custom <?php echo $_SESSION['perfil'] == 'ADMIN' ? 'badge-danger' : 'badge-primary'; ?>">
                                                <?php echo htmlspecialchars($_SESSION['perfil'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Restaurante ID</span>
                                        <span class="info-value">#<?php echo $_SESSION['restaurante_id']; ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Plano Atual</span>
                                        <span class="info-value"><?php echo htmlspecialchars($plano_nome); ?></span>
                                    </div>
                                    <a href="usuarios.php" class="btn btn-outline-primary w-100 mt-4">
                                        <i class="fas fa-users me-2"></i>Gerenciar Usuários
                                    </a>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-info-circle me-2"></i>Informações do Sistema
                                </div>
                                <div class="card-body p-0">
                                    <div class="info-row">
                                        <span class="info-label">Versão</span>
                                        <span class="info-value">1.0.0</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">PHP</span>
                                        <span class="info-value"><?php echo phpversion(); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Suporte</span>
                                        <span class="info-value">edvisjoaochibante2002@gmail.com</span>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Compra de Plano -->
    <div class="modal fade" id="modalCompraPlano" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-shopping-cart me-2"></i>Confirmar Compra de Plano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="alertModalPlano" class="alert" style="display: none;"></div>

                    <div class="text-center mb-4">
                        <div class="plan-option-icon d-inline-flex" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; width: 80px; height: 80px; font-size: 36px;">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h5 class="mt-3" id="planoSelecionadoNome"></h5>
                        <div class="plan-option-price" id="planoSelecionadoValor"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Periodicidade *</label>
                        <select class="form-select" id="periodicidadePlano" onchange="atualizarValorPlanoSelecionado()">
                            <option value="MENSAL">Mensal</option>
                            <option value="TRIMESTRAL">Trimestral</option>
                            <option value="ANUAL">Anual</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Método de Pagamento *</label>
                        <select class="form-select" id="metodoPagamento">
                            <option value="">Selecione...</option>
                            <option value="DINHEIRO">💵 Dinheiro</option>
                            <option value="MPESA">📱 M-Pesa</option>
                            <option value="CARTAO">💳 Cartão</option>
                            <option value="TRANSFERENCIA">🏦 Transferência</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comprovativo de Pagamento *</label>
                        <input type="file" class="form-control" id="comprovativoPagamento" accept=".jpg,.jpeg,.png,.pdf">
                        <small class="text-muted">Formatos: JPG, PNG ou PDF (máx. 5MB)</small>
                    </div>

                    <div class="alert alert-secondary py-2 mb-3" id="infoValorPeriodicidade"></div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Após confirmar, aguarde a verificação do pagamento. O plano será ativado após aprovação do administrador.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarCompraPlano" onclick="confirmarCompraPlano()">
                        <i class="fas fa-check me-1"></i> Confirmar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Preview Comprovativo -->
    <div class="modal fade" id="modalComprovativo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Comprovativo de Pagamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="comprovativoModalBody" style="min-height:300px;">
                    <div class="d-flex justify-content-center align-items-center" style="height:300px;">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <a id="comprovativoDownloadLink" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i>Abrir em nova aba</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var planoAtual = '';
        var valorAtual = 0;
        var precosPlanos = <?php
                            echo json_encode([
                                'BASICO' => [
                                    'MENSAL' => (float)($planos_config['BASICO']['precos']['mensal'] ?? 0),
                                    'TRIMESTRAL' => (float)($planos_config['BASICO']['precos']['trimestral'] ?? 0),
                                    'ANUAL' => (float)($planos_config['BASICO']['precos']['anual'] ?? 0),
                                ],
                                'PROFISSIONAL' => [
                                    'MENSAL' => (float)($planos_config['PROFISSIONAL']['precos']['mensal'] ?? 0),
                                    'TRIMESTRAL' => (float)($planos_config['PROFISSIONAL']['precos']['trimestral'] ?? 0),
                                    'ANUAL' => (float)($planos_config['PROFISSIONAL']['precos']['anual'] ?? 0),
                                ],
                                'EMPRESARIAL' => [
                                    'MENSAL' => (float)($planos_config['EMPRESARIAL']['precos']['mensal'] ?? 0),
                                    'TRIMESTRAL' => (float)($planos_config['EMPRESARIAL']['precos']['trimestral'] ?? 0),
                                    'ANUAL' => (float)($planos_config['EMPRESARIAL']['precos']['anual'] ?? 0),
                                ]
                            ], JSON_UNESCAPED_UNICODE);
                            ?>;

        function formatarValorMzn(valor) {
            return Number(valor || 0).toLocaleString('pt-BR') + ' MZN';
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function descreverPrecoPlano(valor, periodicidade) {
            var periodicidadeLabel = periodicidade.charAt(0) + periodicidade.slice(1).toLowerCase();
            if (Number(valor || 0) <= 0) {
                return 'Grátis<span>/' + periodicidadeLabel.toLowerCase() + '</span>';
            }

            return formatarValorMzn(valor) + '<span>/' + periodicidadeLabel.toLowerCase() + '</span>';
        }

        function atualizarValorPlanoSelecionado() {
            var periodicidade = document.getElementById('periodicidadePlano').value || 'MENSAL';
            var valores = precosPlanos[planoAtual] || {};
            valorAtual = Number(valores[periodicidade] || 0);

            var periodicidadeLabel = periodicidade.charAt(0) + periodicidade.slice(1).toLowerCase();
            document.getElementById('planoSelecionadoValor').innerHTML = descreverPrecoPlano(valorAtual, periodicidade);

            var info = document.getElementById('infoValorPeriodicidade');
            info.innerHTML = '<strong>Ciclo selecionado:</strong> ' + periodicidadeLabel + ' | <strong>Valor:</strong> ' + (valorAtual <= 0 ? 'Grátis' : formatarValorMzn(valorAtual));
        }

        // Selecionar plano
        function selecionarPlano(plano) {
            planoAtual = plano;
            document.getElementById('planoSelecionadoNome').textContent = plano;
            document.getElementById('periodicidadePlano').value = 'MENSAL';
            atualizarValorPlanoSelecionado();
            document.getElementById('metodoPagamento').value = '';
            document.getElementById('comprovativoPagamento').value = '';
            document.getElementById('alertModalPlano').style.display = 'none';
            var btnConfirmar = document.getElementById('btnConfirmarCompraPlano');
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="fas fa-check me-1"></i> Confirmar Pedido';
            }
        }

        function obterHistoricoPlano() {
            return fetch('api/plano_listar.php', {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (!data.success || !Array.isArray(data.data)) {
                        throw new Error(data.message || 'Falha ao consultar histórico');
                    }
                    return data.data;
                });
        }

        function reconciliarSolicitacaoPendente(plano, ciclo) {
            return obterHistoricoPlano()
                .then(function(itens) {
                    return itens.some(function(item) {
                        return String(item.status || '').toUpperCase() === 'PENDENTE' &&
                            String(item.plano_novo || '').toUpperCase() === String(plano || '').toUpperCase() &&
                            String(item.ciclo || 'MENSAL').toUpperCase() === String(ciclo || 'MENSAL').toUpperCase();
                    });
                })
                .catch(function(err) {
                    console.error('Falha ao reconciliar solicitação pendente:', err);
                    return false;
                });
        }

        // Confirmar compra
        function confirmarCompraPlano() {
            var metodo = document.getElementById('metodoPagamento').value;
            var periodicidade = document.getElementById('periodicidadePlano').value;
            var comprovativoInput = document.getElementById('comprovativoPagamento');
            var comprovativo = comprovativoInput && comprovativoInput.files ? comprovativoInput.files[0] : null;
            var alertDiv = document.getElementById('alertModalPlano');
            var btnConfirmar = document.getElementById('btnConfirmarCompraPlano');

            if (btnConfirmar && btnConfirmar.disabled) {
                return;
            }

            if (valorAtual > 0 && !metodo) {
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'Selecione o método de pagamento!';
                alertDiv.style.display = 'block';
                return;
            }

            if (valorAtual > 0 && !comprovativo) {
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'Envie o comprovativo de pagamento!';
                alertDiv.style.display = 'block';
                return;
            }

            if (comprovativo && comprovativo.size > 5 * 1024 * 1024) {
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'Comprovativo excede 5MB.';
                alertDiv.style.display = 'block';
                return;
            }

            // Criar form data
            var formData = new FormData();
            formData.append('plano', planoAtual);
            formData.append('ciclo', periodicidade);
            if (metodo) {
                formData.append('metodo', metodo);
            }
            if (comprovativo) {
                formData.append('comprovativo', comprovativo);
            }

            if (btnConfirmar) {
                btnConfirmar.disabled = true;
                btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processando...';
            }

            var controller = new AbortController();
            var timeoutId = setTimeout(function() {
                controller.abort();
                if (btnConfirmar) {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="fas fa-check me-1"></i> Confirmar Pedido';
                }
                alertDiv.className = 'alert alert-warning';
                alertDiv.textContent = 'Tempo limite atingido. Verificando status real do pedido...';
                alertDiv.style.display = 'block';

                reconciliarSolicitacaoPendente(planoAtual, periodicidade).then(function(existePendente) {
                    if (existePendente) {
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = 'O pedido já foi registado e está pendente de aprovação.';
                        alertDiv.style.display = 'block';
                        setTimeout(function() {
                            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCompraPlano'));
                            if (modal) modal.hide();
                            window.location.reload();
                        }, 1200);
                    } else {
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = 'Timeout: A operação demorou muito. Tente novamente.';
                        alertDiv.style.display = 'block';
                    }
                });
            }, 30000);

            // Chamar API
            fetch('api/plano_solicitar_renovacao.php', {
                    method: 'POST',
                    signal: controller.signal,
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    clearTimeout(timeoutId);
                    return response.json();
                })
                .then(function(data) {
                    if (btnConfirmar) {
                        btnConfirmar.disabled = false;
                        btnConfirmar.innerHTML = '<i class="fas fa-check me-1"></i> Confirmar Pedido';
                    }

                    if (data.success) {
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = data.message;
                        alertDiv.style.display = 'block';

                        // Fechar modal e recarregar após 2 segundos
                        setTimeout(function() {
                            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCompraPlano'));
                            modal.hide();
                            window.location.reload();
                        }, 2000);
                    } else {
                        if (String(data.message || '').toLowerCase().indexOf('já existe uma solicitação pendente') !== -1) {
                            reconciliarSolicitacaoPendente(planoAtual, periodicidade).then(function(existePendente) {
                                alertDiv.className = existePendente ? 'alert alert-success' : 'alert alert-warning';
                                alertDiv.textContent = existePendente ?
                                    'Já existe um pedido pendente para este plano/ciclo. Aguardando aprovação.' :
                                    data.message;
                                alertDiv.style.display = 'block';
                                if (existePendente) {
                                    setTimeout(function() {
                                        var modal = bootstrap.Modal.getInstance(document.getElementById('modalCompraPlano'));
                                        if (modal) modal.hide();
                                        window.location.reload();
                                    }, 1200);
                                }
                            });
                        } else {
                            alertDiv.className = 'alert alert-danger';
                            alertDiv.textContent = data.message;
                            alertDiv.style.display = 'block';
                        }
                    }
                })
                .catch(function(err) {
                    clearTimeout(timeoutId);
                    if (btnConfirmar) {
                        btnConfirmar.disabled = false;
                        btnConfirmar.innerHTML = '<i class="fas fa-check me-1"></i> Confirmar Pedido';
                    }

                    if (err.name === 'AbortError') {
                        return;
                    }

                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'Erro: ' + err.message;
                    alertDiv.style.display = 'block';
                });
        }

        function abrirComprovativoConf(pathEncoded) {
            var path = decodeURIComponent(pathEncoded || '');
            var bodyEl = document.getElementById('comprovativoModalBody');
            var dlLink = document.getElementById('comprovativoDownloadLink');
            if (!bodyEl || !dlLink) {
                return;
            }

            var url;
            try {
                url = new URL(path, window.location.origin);
            } catch (e) {
                bodyEl.textContent = 'Comprovativo inválido.';
                return;
            }

            if (!/^https?:$/.test(url.protocol)) {
                bodyEl.textContent = 'Comprovativo inválido.';
                return;
            }

            dlLink.href = url.href;
            bodyEl.textContent = '';
            var ext = (url.pathname.split('.').pop() || '').toLowerCase();
            if (ext === 'pdf') {
                var iframe = document.createElement('iframe');
                iframe.src = url.href;
                iframe.style.width = '100%';
                iframe.style.height = '500px';
                iframe.style.border = 'none';
                bodyEl.appendChild(iframe);
            } else {
                var img = document.createElement('img');
                img.src = url.href;
                img.className = 'img-fluid rounded';
                img.alt = 'Comprovativo';
                img.style.maxHeight = '500px';
                img.onerror = function() {
                    bodyEl.textContent = 'Não foi possível carregar o ficheiro.';
                };
                bodyEl.appendChild(img);
            }
            var modal = new bootstrap.Modal(document.getElementById('modalComprovativo'));
            modal.show();
        }

        // Carregar histórico de compras
        <?php if ($secao === 'plano'): ?>
            var historicoComprasDados = [];

            function normalizarTextoHistorico(valor) {
                return String(valor || '').toLowerCase();
            }

            function obterHistoricoComprasFiltrado() {
                var texto = normalizarTextoHistorico((document.getElementById('filtroHistoricoTexto') || {}).value || '');
                var cicloSelecionado = normalizarTextoHistorico((document.getElementById('filtroHistoricoCiclo') || {}).value || '').toUpperCase();
                var statusSelecionado = normalizarTextoHistorico((document.getElementById('filtroHistoricoStatus') || {}).value || '').toUpperCase();

                return historicoComprasDados.filter(function(c) {
                    var ciclo = c.ciclo ? String(c.ciclo).toUpperCase() : (((c.metodo_pagamento || '').indexOf('-') !== -1 ? (c.metodo_pagamento || '').split('-').pop().trim() : 'MENSAL').toUpperCase());
                    var status = String(c.status || '').toUpperCase();
                    if (cicloSelecionado && ciclo !== cicloSelecionado) {
                        return false;
                    }
                    if (statusSelecionado && status !== statusSelecionado) {
                        return false;
                    }
                    if (!texto) {
                        return true;
                    }
                    var hay = [c.plano_atual, c.plano_novo, c.metodo_pagamento, c.status, ciclo].map(normalizarTextoHistorico).join(' ');
                    return hay.indexOf(texto) !== -1;
                });
            }

            function renderHistoricoComprasFiltrado() {
                var container = document.getElementById('historicoCompras');
                var dadosFiltrados = obterHistoricoComprasFiltrado();

                if (!dadosFiltrados.length) {
                    container.innerHTML = '<p class="text-center text-muted py-4">Nenhuma compra encontrada</p>';
                    return;
                }

                var html = '<table class="table"><thead><tr><th>ID</th><th>De</th><th>Para</th><th>Valor</th><th>Método</th><th>Ciclo</th><th>Comprovativo</th><th>Status</th><th>Data</th></tr></thead><tbody>';
                dadosFiltrados.forEach(function(c) {
                    var statusClass = c.status === 'APROVADO' ? 'success' : (c.status === 'PENDENTE' ? 'warning' : 'danger');
                    var dataRef = c.data_compra || c.criado_em || c.created_at;
                    var ciclo = c.ciclo ? c.ciclo : ((c.metodo_pagamento || '').indexOf('-') !== -1 ? (c.metodo_pagamento || '').split('-').pop().trim() : 'MENSAL');
                    var comprovativo = c.comprovativo_path ? '<button class="btn btn-sm btn-outline-primary btn-comprovativo-hist" onclick="abrirComprovativoConf(\'' + encodeURIComponent(c.comprovativo_path) + '\')" title="Ver comprovativo"><i class="fas fa-file-invoice"></i></button>' : '-';
                    html += '<tr><td>#' + escapeHtml(c.id) + '</td><td>' + escapeHtml(c.plano_atual) + '</td><td>' + escapeHtml(c.plano_novo) + '</td><td>' + parseFloat(c.valor).toFixed(2) + ' MZN</td><td>' + escapeHtml(c.metodo_pagamento) + '</td><td>' + escapeHtml(ciclo) + '</td><td class="text-center">' + comprovativo + '</td><td><span class="badge-custom badge-' + statusClass + '">' + escapeHtml(c.status) + '</span></td><td>' + (dataRef ? new Date(dataRef).toLocaleDateString('pt-BR') : '-') + '</td></tr>';
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            }

            function exportarHistoricoComprasCsv() {
                var dadosFiltrados = obterHistoricoComprasFiltrado();
                if (!dadosFiltrados.length) {
                    alert('Nenhum registo para exportar.');
                    return;
                }

                var linhas = [];
                linhas.push(['ID', 'Plano De', 'Plano Para', 'Valor (MZN)', 'Metodo', 'Ciclo', 'Status', 'Data'].join(';'));

                dadosFiltrados.forEach(function(c) {
                    var dataRef = c.data_compra || c.criado_em || c.created_at;
                    var dataFormatada = dataRef ? new Date(dataRef).toLocaleDateString('pt-BR') : '-';
                    var ciclo = c.ciclo ? c.ciclo : ((c.metodo_pagamento || '').indexOf('-') !== -1 ? (c.metodo_pagamento || '').split('-').pop().trim() : 'MENSAL');
                    var valor = (parseFloat(c.valor) || 0).toFixed(2);

                    var linha = [
                        String(c.id || ''),
                        String(c.plano_atual || ''),
                        String(c.plano_novo || ''),
                        String(valor),
                        String(c.metodo_pagamento || ''),
                        String(ciclo || ''),
                        String(c.status || ''),
                        String(dataFormatada)
                    ].map(function(v) {
                        return '"' + v.replace(/"/g, '""') + '"';
                    }).join(';');

                    linhas.push(linha);
                });

                var csv = '\uFEFF' + linhas.join('\n');
                var blob = new Blob([csv], {
                    type: 'text/csv;charset=utf-8;'
                });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'historico_compras_' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }

            fetch('api/plano_listar.php', {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.success && data.data && data.data.length > 0) {
                        historicoComprasDados = data.data;
                        renderHistoricoComprasFiltrado();
                    } else {
                        document.getElementById('historicoCompras').innerHTML = '<p class="text-center text-muted py-4">Nenhuma compra encontrada</p>';
                    }
                })
                .catch(function(err) {
                    console.error('Erro ao carregar compras:', err);
                    document.getElementById('historicoCompras').innerHTML = '<p class="text-center text-danger py-4">Erro ao carregar histórico</p>';
                });

            ['filtroHistoricoTexto', 'filtroHistoricoCiclo', 'filtroHistoricoStatus'].forEach(function(id) {
                var el = document.getElementById(id);
                if (!el) {
                    return;
                }
                var ev = id === 'filtroHistoricoTexto' ? 'input' : 'change';
                el.addEventListener(ev, renderHistoricoComprasFiltrado);
            });

            var btnExportar = document.getElementById('btnExportarHistoricoCsv');
            if (btnExportar) {
                btnExportar.addEventListener('click', exportarHistoricoComprasCsv);
            }
        <?php endif; ?>

        function previewLogoRestauranteConfig(input) {
            var previewImage = document.getElementById('logoPreviewImage');
            var previewInfo = document.getElementById('logoPreviewInfo');

            if (!previewImage || !previewInfo) {
                return;
            }

            if (!input.files || !input.files.length) {
                <?php if ($logo_restaurante !== ''): ?>
                    previewImage.src = <?php echo json_encode($logo_restaurante, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
                    previewImage.style.display = 'block';
                    previewInfo.textContent = <?php echo json_encode(basename($logo_restaurante), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
                <?php else: ?>
                    previewImage.removeAttribute('src');
                    previewImage.style.display = 'none';
                    previewInfo.textContent = 'Nenhum logotipo enviado para este restaurante.';
                <?php endif; ?>
                return;
            }

            var file = input.files[0];
            previewInfo.textContent = file.name;

            if (typeof FileReader === 'undefined') {
                return;
            }

            var reader = new FileReader();
            reader.onload = function(event) {
                previewImage.src = event.target.result;
                previewImage.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>

</html>

