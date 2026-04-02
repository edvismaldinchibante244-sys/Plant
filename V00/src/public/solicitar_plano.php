<?php

/**
 * ============================================
 * FORMULÁRIO PÚBLICO - SOLICITAR PLANO
 * Cliente solicita um plano antes de fazer login
 * ============================================
 */

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/security.php';
include_once __DIR__ . '/../config/plano_notificacoes.php';
include_once __DIR__ . '/../config/csrf.php';
include_once __DIR__ . '/../config/restaurante_status_helper.php';

$planos_catalogo = require __DIR__ . '/../config/planos.php';

security_start_session();
security_set_headers();
security_regenerate_session(15);

if (!function_exists('format_mzn')) {
    function format_mzn($valor)
    {
        return number_format((float)$valor, 0, ',', '.');
    }
}

if (!function_exists('solicitar_plano_escape')) {
    function solicitar_plano_escape($valor)
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('solicitar_plano_default_form')) {
    function solicitar_plano_default_form()
    {
        return [
            'nome' => '',
            'email' => '',
            'telefone' => '',
            'endereco' => '',
            'cidade' => '',
            'nuit' => '',
            'plano' => '',
            'ciclo' => 'MENSAL',
            'metodo' => '',
        ];
    }
}

$preco_basico = $planos_catalogo['BASICO']['precos'] ?? ['mensal' => 1500, 'trimestral' => 4000, 'anual' => 15000];
$preco_profissional = $planos_catalogo['PROFISSIONAL']['precos'] ?? ['mensal' => 3000, 'trimestral' => 8000, 'anual' => 30000];
$preco_empresarial = $planos_catalogo['EMPRESARIAL']['precos'] ?? ['mensal' => 6000, 'trimestral' => 16000, 'anual' => 60000];
$recursos_planos_catalogo = function_exists('plano_get_recursos_catalogo') ? [
    'BASICO' => plano_get_recursos_catalogo('BASICO'),
    'PROFISSIONAL' => plano_get_recursos_catalogo('PROFISSIONAL'),
    'EMPRESARIAL' => plano_get_recursos_catalogo('EMPRESARIAL'),
] : [
    'BASICO' => [],
    'PROFISSIONAL' => [],
    'EMPRESARIAL' => [],
];

$form = solicitar_plano_default_form();
$mensagem = '';
$tipo_msg = '';
$csrf_token = csrf_get_token();
$logo_caminho_absoluto = null;
$logo_caminho_relativo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'nome' => trim((string)($_POST['nome'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'telefone' => trim((string)($_POST['telefone'] ?? '')),
        'endereco' => trim((string)($_POST['endereco'] ?? '')),
        'cidade' => trim((string)($_POST['cidade'] ?? '')),
        'nuit' => trim((string)($_POST['nuit'] ?? '')),
        'plano' => strtoupper(trim((string)($_POST['plano'] ?? ''))),
        'ciclo' => strtoupper(trim((string)($_POST['ciclo'] ?? 'MENSAL'))),
        'metodo' => strtoupper(trim((string)($_POST['metodo'] ?? ''))),
    ];

    $nome = $form['nome'];
    $email = $form['email'];
    $telefone = $form['telefone'];
    $endereco = $form['endereco'];
    $cidade = $form['cidade'];
    $nuit = $form['nuit'];
    $plano = $form['plano'];
    $ciclo = $form['ciclo'];
    $metodo = $form['metodo'];
    $arquivo_logo = $_FILES['logo'] ?? null;
    $arquivo_comprovativo = $_FILES['comprovativo'] ?? null;
    $ciclos_validos = ['MENSAL', 'TRIMESTRAL', 'ANUAL'];
    $metodos_validos = ['MPESA', 'CARTAO', 'TRANSFERENCIA'];
    $ciclo_para_chave = [
        'MENSAL' => 'mensal',
        'TRIMESTRAL' => 'trimestral',
        'ANUAL' => 'anual',
    ];
    $erros = [];
    $logoUploadError = $arquivo_logo['error'] ?? UPLOAD_ERR_NO_FILE;
    $uploadError = $arquivo_comprovativo['error'] ?? UPLOAD_ERR_NO_FILE;
    $telefoneDigitos = preg_replace('/\D+/', '', $telefone);
    $nuitDigitos = preg_replace('/\D+/', '', $nuit);

    if (!csrf_is_valid()) {
        $erros[] = 'Sessao expirada ou formulario invalido. Recarregue a pagina e tente novamente.';
    }

    if ($nome === '' || strlen($nome) < 3) {
        $erros[] = 'Informe o nome do restaurante com pelo menos 3 caracteres.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um email valido.';
    }

    if ($telefone !== '' && (strlen($telefoneDigitos) < 8 || strlen($telefoneDigitos) > 15)) {
        $erros[] = 'Informe um telefone valido.';
    }

    if ($nuit !== '' && strlen($nuitDigitos) !== 9) {
        $erros[] = 'NUIT invalido. Informe 9 digitos.';
    }

    if ($plano === '' || !isset($planos_catalogo[$plano])) {
        $erros[] = 'Selecione um plano valido.';
    }

    if (!in_array($ciclo, $ciclos_validos, true)) {
        $erros[] = 'Selecione um ciclo valido.';
    }

    if (!in_array($metodo, $metodos_validos, true)) {
        $erros[] = 'Selecione um metodo de pagamento valido.';
    }

    if ($logoUploadError !== UPLOAD_ERR_OK) {
        $erros[] = $logoUploadError === UPLOAD_ERR_NO_FILE
            ? 'Anexe o logotipo do restaurante.'
            : 'Falha no envio do logotipo. Tente novamente.';
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        $erros[] = $uploadError === UPLOAD_ERR_NO_FILE
            ? 'Anexe o comprovativo de pagamento.'
            : 'Falha no envio do comprovativo. Tente novamente.';
    }

    if (!empty($erros)) {
        $mensagem = implode("\n", $erros);
        $tipo_msg = 'danger';
    } else {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            SELECT
                r.id,
                r.status,
                EXISTS(
                    SELECT 1
                    FROM compras_planos cp
                    WHERE cp.restaurante_id = r.id
                      AND cp.status = 'PENDENTE'
                ) AS possui_compra_pendente
            FROM restaurantes r
            WHERE r.email = ?
            ORDER BY r.id DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $restauranteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($restauranteExistente) {
            $mensagem = ((int)($restauranteExistente['possui_compra_pendente'] ?? 0) === 1)
                ? 'Ja existe uma solicitacao pendente para este email. Aguarde a analise do super admin.'
                : 'Este email ja esta cadastrado. Faca login para continuar.';
            $tipo_msg = 'danger';
        } else {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $mensagem = 'Este email ja esta vinculado a um usuario existente. Utilize outro email ou recupere o acesso.';
                $tipo_msg = 'danger';
            } else {
                $chave_ciclo = $ciclo_para_chave[$ciclo] ?? 'mensal';
                $valor = (float)($planos_catalogo[$plano]['precos'][$chave_ciclo] ?? 0);

                if ($valor <= 0) {
                    $mensagem = 'Plano ou ciclo invalido.';
                    $tipo_msg = 'danger';
                } else {
                    $extensoes_logo_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
                    $mimes_logo_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
                    $max_tamanho_logo = 4 * 1024 * 1024;
                    $logo_extensao = strtolower(pathinfo($arquivo_logo['name'] ?? '', PATHINFO_EXTENSION));
                    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'pdf'];
                    $mimes_permitidos = ['image/jpeg', 'image/png', 'application/pdf'];
                    $max_tamanho = 5 * 1024 * 1024;
                    $extensao = strtolower(pathinfo($arquivo_comprovativo['name'] ?? '', PATHINFO_EXTENSION));

                    if (!in_array($logo_extensao, $extensoes_logo_permitidas, true)) {
                        $mensagem = 'Logotipo invalido. Envie JPG, PNG ou WEBP.';
                        $tipo_msg = 'danger';
                    } elseif (($arquivo_logo['size'] ?? 0) > $max_tamanho_logo) {
                        $mensagem = 'Logotipo excede 4MB.';
                        $tipo_msg = 'danger';
                    } elseif (!in_array($extensao, $extensoes_permitidas, true)) {
                        $mensagem = 'Comprovativo invalido. Envie JPG, PNG ou PDF.';
                        $tipo_msg = 'danger';
                    } elseif (($arquivo_comprovativo['size'] ?? 0) > $max_tamanho) {
                        $mensagem = 'Comprovativo excede 5MB.';
                        $tipo_msg = 'danger';
                    } else {
                        $logoMime = '';
                        if (function_exists('finfo_open')) {
                            $finfoLogo = finfo_open(FILEINFO_MIME_TYPE);
                            if ($finfoLogo) {
                                $logoMime = finfo_file($finfoLogo, $arquivo_logo['tmp_name']);
                                finfo_close($finfoLogo);
                            }
                        }

                        if ($logoMime === '' && function_exists('mime_content_type')) {
                            $logoMime = mime_content_type($arquivo_logo['tmp_name']) ?: '';
                        }

                        $mime = '';
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            if ($finfo) {
                                $mime = finfo_file($finfo, $arquivo_comprovativo['tmp_name']);
                                finfo_close($finfo);
                            }
                        }

                        if ($mime === '' && function_exists('mime_content_type')) {
                            $mime = mime_content_type($arquivo_comprovativo['tmp_name']) ?: '';
                        }

                        if ($logoMime !== '' && !in_array($logoMime, $mimes_logo_permitidos, true)) {
                            $mensagem = 'Tipo de arquivo invalido para o logotipo.';
                            $tipo_msg = 'danger';
                        } elseif ($mime !== '' && !in_array($mime, $mimes_permitidos, true)) {
                            $mensagem = 'Tipo de arquivo invalido para comprovativo.';
                            $tipo_msg = 'danger';
                        } else {
                            $dir_logo_absoluto = __DIR__ . '/uploads/restaurantes/logos';
                            $dir_upload_absoluto = __DIR__ . '/uploads/comprovativos';
                            if ((!is_dir($dir_logo_absoluto) && !mkdir($dir_logo_absoluto, 0755, true))
                                || (!is_dir($dir_upload_absoluto) && !mkdir($dir_upload_absoluto, 0755, true))) {
                                $mensagem = 'Nao foi possivel preparar os diretorios de upload.';
                                $tipo_msg = 'danger';
                            } else {
                                try {
                                    $nome_logo = sprintf(
                                        'logo_%s_%s.%s',
                                        date('YmdHis'),
                                        bin2hex(random_bytes(6)),
                                        $logo_extensao
                                    );
                                } catch (Throwable $e) {
                                    $nome_logo = '';
                                }

                                try {
                                    $nome_comprovativo = sprintf(
                                        'comp_%s_%s.%s',
                                        date('YmdHis'),
                                        bin2hex(random_bytes(6)),
                                        $extensao
                                    );
                                } catch (Throwable $e) {
                                    $nome_comprovativo = '';
                                }

                                if ($nome_logo === '' || $nome_comprovativo === '') {
                                    $mensagem = 'Nao foi possivel gerar identificadores seguros para os uploads.';
                                    $tipo_msg = 'danger';
                                } else {
                                    $logo_caminho_absoluto = $dir_logo_absoluto . '/' . $nome_logo;
                                    $logo_caminho_relativo = 'uploads/restaurantes/logos/' . $nome_logo;
                                    $caminho_absoluto = $dir_upload_absoluto . '/' . $nome_comprovativo;
                                    $caminho_relativo = 'uploads/comprovativos/' . $nome_comprovativo;

                                    $uploadError = null;
                                    $logoOk = security_validate_upload(
                                        $arquivo_logo,
                                        ['png', 'jpg', 'jpeg'],
                                        ['image/png', 'image/jpeg'],
                                        2 * 1024 * 1024,
                                        $uploadError
                                    );
                                    $compOk = security_validate_upload(
                                        $arquivo_comprovativo,
                                        ['png', 'jpg', 'jpeg', 'pdf'],
                                        ['image/png', 'image/jpeg', 'application/pdf'],
                                        5 * 1024 * 1024,
                                        $uploadError
                                    );

                                    if (!$logoOk) {
                                        $mensagem = $uploadError ?: 'Falha ao validar o logotipo.';
                                        $tipo_msg = 'danger';
                                    } elseif (!$compOk) {
                                        $mensagem = $uploadError ?: 'Falha ao validar comprovativo.';
                                        $tipo_msg = 'danger';
                                    } elseif (!move_uploaded_file($arquivo_logo['tmp_name'], $logo_caminho_absoluto)) {
                                        $mensagem = 'Falha ao salvar o logotipo.';
                                        $tipo_msg = 'danger';
                                    } elseif (!move_uploaded_file($arquivo_comprovativo['tmp_name'], $caminho_absoluto)) {
                                        if (is_file($logo_caminho_absoluto)) {
                                            @unlink($logo_caminho_absoluto);
                                        }
                                        $logo_caminho_absoluto = null;
                                        $logo_caminho_relativo = null;
                                        $mensagem = 'Falha ao salvar comprovativo.';
                                        $tipo_msg = 'danger';
                                    } else {
                                        $senha_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                                        $status_inicial = restaurante_status_resolver_inicial($db);
                                        $dias_por_ciclo = [
                                            'MENSAL' => 30,
                                            'TRIMESTRAL' => 90,
                                            'ANUAL' => 365,
                                        ];
                                        $data_fim = date('Y-m-d', strtotime('+' . ($dias_por_ciclo[$ciclo] ?? 30) . ' days'));
                                        $colunasRestaurante = [];
                                        $stmt_cols_rest = $db->query('SHOW COLUMNS FROM restaurantes');
                                        while ($col_rest = $stmt_cols_rest->fetch(PDO::FETCH_ASSOC)) {
                                            $colunasRestaurante[] = $col_rest['Field'];
                                        }

                                        try {
                                            $db->beginTransaction();

                                            $restauranteCols = ['nome', 'email', 'telefone', 'endereco', 'cidade', 'nuit', 'plano', 'data_fim', 'created_at'];
                                            $restauranteVals = ['?', '?', '?', '?', '?', '?', '?', '?', 'NOW()'];
                                            $restauranteParams = [$nome, $email, $telefone, $endereco, $cidade, $nuit, $plano, $data_fim];

                                            if (in_array('logo', $colunasRestaurante, true)) {
                                                $restauranteCols[] = 'logo';
                                                $restauranteVals[] = '?';
                                                $restauranteParams[] = $logo_caminho_relativo;
                                            }

                                            if ($status_inicial !== null) {
                                                $restauranteCols[] = 'status';
                                                $restauranteVals[] = '?';
                                                $restauranteParams[] = $status_inicial;
                                            }

                                            $stmt = $db->prepare(
                                                'INSERT INTO restaurantes (' . implode(', ', $restauranteCols) . ') VALUES (' . implode(', ', $restauranteVals) . ')'
                                            );
                                            $stmt->execute($restauranteParams);
                                            $restaurante_id = $db->lastInsertId();

                                            $stmt_user = $db->prepare("
                                                INSERT INTO usuarios (restaurante_id, nome, email, senha, perfil, ativo, created_at)
                                                VALUES (?, ?, ?, ?, 'ADMIN', 0, NOW())
                                            ");
                                            $stmt_user->execute([$restaurante_id, 'Administrador', $email, $senha_hash]);

                                            $colunas_compra = [];
                                            $stmt_cols = $db->query('SHOW COLUMNS FROM compras_planos');
                                            while ($col = $stmt_cols->fetch(PDO::FETCH_ASSOC)) {
                                                $colunas_compra[] = $col['Field'];
                                            }

                                            $insert_cols = ['restaurante_id', 'plano_atual', 'plano_novo', 'valor', 'metodo_pagamento', 'status'];
                                            $insert_vals = ['?', '?', '?', '?', '?', '?'];
                                            $insert_params = [$restaurante_id, 'BASICO', $plano, $valor, $metodo, 'PENDENTE'];

                                            if (in_array('ciclo', $colunas_compra, true)) {
                                                $insert_cols[] = 'ciclo';
                                                $insert_vals[] = '?';
                                                $insert_params[] = $ciclo;
                                            }

                                            if (in_array('comprovativo_path', $colunas_compra, true)) {
                                                $insert_cols[] = 'comprovativo_path';
                                                $insert_vals[] = '?';
                                                $insert_params[] = $caminho_relativo;
                                            }

                                            if (in_array('observacao', $colunas_compra, true)) {
                                                $insert_cols[] = 'observacao';
                                                $insert_vals[] = '?';
                                                $insert_params[] = 'Ciclo: ' . $ciclo . ' | Comprovativo: ' . $caminho_relativo;
                                            }

                                            if (in_array('created_at', $colunas_compra, true)) {
                                                $insert_cols[] = 'created_at';
                                                $insert_vals[] = 'NOW()';
                                            } elseif (in_array('criado_em', $colunas_compra, true)) {
                                                $insert_cols[] = 'criado_em';
                                                $insert_vals[] = 'NOW()';
                                            }

                                            $sql_compra = 'INSERT INTO compras_planos (' . implode(', ', $insert_cols) . ') VALUES (' . implode(', ', $insert_vals) . ')';
                                            $stmt_compra = $db->prepare($sql_compra);
                                            $stmt_compra->execute($insert_params);

                                            $db->commit();

                                            plano_notificar_solicitacao_recebida(
                                                $email,
                                                $telefone,
                                                $nome,
                                                $plano,
                                                $ciclo,
                                                $valor,
                                                $metodo
                                            );

                                            $mensagem = 'Solicitacao enviada com sucesso! Aguarde a aprovacao do administrador.';
                                            $tipo_msg = 'success';
                                            $form = solicitar_plano_default_form();
                                            $logo_caminho_absoluto = null;
                                            $logo_caminho_relativo = null;
                                        } catch (Throwable $e) {
                                            if ($db->inTransaction()) {
                                                $db->rollBack();
                                            }
                                            if (is_file($caminho_absoluto)) {
                                                @unlink($caminho_absoluto);
                                            }
                                            if ($logo_caminho_absoluto && is_file($logo_caminho_absoluto)) {
                                                @unlink($logo_caminho_absoluto);
                                            }
                                            error_log('[SOLICITAR_PLANO] ' . $e->getMessage());
                                            $mensagem = 'Erro ao processar a solicitacao. Tente novamente em instantes.';
                                            $tipo_msg = 'danger';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .public-page-shell {
            width: 100%;
            max-width: 1260px;
            position: relative;
            z-index: 1;
        }

        .public-mobile-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(14px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.22);
        }

        .public-mobile-brand {
            color: #fff;
            min-width: 0;
        }

        .public-mobile-brand strong {
            display: block;
            font-size: 15px;
            line-height: 1.1;
        }

        .public-mobile-brand span {
            display: block;
            font-size: 11px;
            opacity: 0.75;
        }

        .public-sidebar-toggle,
        .public-sidebar-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary, #FF6B35), #F7931E);
            color: #fff;
            font-size: 1.15rem;
            box-shadow: 0 16px 30px rgba(255, 107, 53, 0.22);
        }

        .public-sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.52);
            backdrop-filter: blur(3px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
            z-index: 1090;
        }

        .public-sidebar-menu {
            display: none;
        }

        @media (max-width: 991px) {
            body.public-menu-open {
                overflow: hidden;
            }

            body.public-menu-open .public-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }

            .public-mobile-bar {
                display: flex;
            }

            .public-sidebar-menu {
                display: block;
                position: fixed;
                top: 12px;
                left: 12px;
                width: min(280px, calc(100vw - 24px));
                min-height: calc(100dvh - 24px);
                padding: 18px;
                border-radius: 26px;
                background: linear-gradient(180deg, #ffffff 0%, #fff7ed 100%);
                box-shadow: 0 28px 60px rgba(15, 23, 42, 0.34);
                border: 1px solid rgba(255, 255, 255, 0.5);
                z-index: 1100;
                transform: translateX(-120%);
                transition: transform 0.28s ease;
                overflow-y: auto;
            }

            .public-sidebar-menu.is-open {
                transform: translateX(0);
            }

            .main-content.main-content-blur {
                filter: blur(2px);
                pointer-events: none;
                user-select: none;
            }
        }
    </style>
    <title>Solicitar Plano - RestauranteSaaS</title>`r`n    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #F7931E;
            --text: #1f2937;
            --text-muted: #94a3b8;
            --success: #10b981;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 999px;
            pointer-events: none;
            z-index: 0;
            filter: blur(2px);
        }

        body::before {
            width: 360px;
            height: 360px;
            top: -120px;
            left: -120px;
            background: radial-gradient(circle, rgba(255, 107, 53, 0.25) 0%, rgba(255, 107, 53, 0) 72%);
        }

        body::after {
            width: 420px;
            height: 420px;
            right: -150px;
            bottom: -170px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.22) 0%, rgba(59, 130, 246, 0) 72%);
        }

        .container {
            max-width: 1260px;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .main-content {
            transition: filter 0.25s ease;
        }

        .public-sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .public-sidebar-title {
            min-width: 0;
        }

        .public-sidebar-kicker {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 107, 53, 0.12);
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .public-sidebar-title strong {
            display: block;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            line-height: 1.05;
            color: #0f172a;
        }

        .public-sidebar-title span {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-top: 4px;
        }

        .public-sidebar-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }

        .public-sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 16px;
            text-decoration: none;
            color: #0f172a;
            background: #fff;
            border: 1px solid #e2e8f0;
            font-weight: 600;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .public-sidebar-link:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 107, 53, 0.35);
            box-shadow: 0 16px 28px rgba(255, 107, 53, 0.12);
        }

        .public-sidebar-link i {
            width: 18px;
            text-align: center;
            color: var(--primary);
        }

        .card-plans {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), #F7931E);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header-custom h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }

        .card-header-custom p {
            font-size: 15px;
            letter-spacing: 0.2px;
        }

        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 14px;
            color: #0f172a;
        }

        .section-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 18px;
            margin-bottom: 18px;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            min-height: 45px;
            box-shadow: none;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #fb923c;
            box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.16);
        }

        .plans-grid {
            row-gap: 18px;
        }

        .plan-col {
            display: flex;
        }

        .plan-option {
            border: 2px solid var(--border);
            border-radius: 18px;
            padding: 22px;
            text-align: center;
            transition: all 0.35s;
            cursor: pointer;
            width: 100%;
            min-height: 520px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .plan-option.plan-basic {
            border-color: #f97316;
        }

        .plan-option.plan-pro {
            border-color: #06b6d4;
        }

        .plan-option.plan-enterprise {
            border-color: #f97316;
        }

        .plan-option:hover {
            border-color: var(--primary);
            transform: translateY(-6px);
            box-shadow: 0 14px 26px rgba(15, 23, 42, 0.12);
        }

        .plan-option.selected {
            border-color: var(--primary);
            background: linear-gradient(180deg, #ffffff, #fff8f3);
            box-shadow: 0 12px 26px rgba(255, 107, 53, 0.18);
        }

        .plan-icon {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 16px;
        }

        .plan-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 42px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }

        .plan-price {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 34px;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 10px;
        }

        .plan-price span {
            font-size: 18px;
            color: var(--text-muted);
            font-weight: 500;
            margin-left: 3px;
        }

        .plan-cycle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .plan-option-features {
            list-style: none;
            padding: 0;
            margin: 14px 0 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
        }

        .plan-option-features li {
            padding: 8px 10px;
            font-size: 14px;
            color: #334155;
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

        .plan-option-features li.disabled {
            color: #6b7280;
            background: #f1f5f9;
        }

        .plan-option-features li.disabled i {
            color: #94a3b8;
        }

        .plan-select-indicator {
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #1f2937;
            font-weight: 500;
        }

        .plan-select-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            background: #fff;
            transition: all 0.25s;
        }

        .plan-option.selected .plan-select-dot {
            border-color: var(--primary);
            background: radial-gradient(circle, var(--primary) 0 45%, #fff 46% 100%);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 13px 34px;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(255, 107, 53, 0.33);
            transition: all 0.28s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(255, 107, 53, 0.4);
        }

        .alert-premium {
            border: 1px solid #fde68a;
            background: linear-gradient(180deg, #fffbeb, #fef3c7);
            border-radius: 12px;
            color: #92400e;
        }

        .choice-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .choice-option {
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .choice-option .title {
            font-weight: 700;
            font-size: 14px;
            color: #111827;
        }

        .choice-option .subtitle {
            font-size: 12px;
            color: #6b7280;
        }

        .choice-option:hover {
            border-color: #fb923c;
            transform: translateY(-1px);
        }

        .choice-option.active {
            border-color: var(--primary);
            background: linear-gradient(180deg, #fff7ed, #ffedd5);
            box-shadow: 0 6px 16px rgba(251, 146, 60, 0.2);
        }

        .payment-summary {
            border: 1px dashed #f59e0b;
            border-radius: 12px;
            padding: 12px;
            background: #fffbeb;
            color: #78350f;
            font-size: 14px;
        }

        .payment-summary strong {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            color: #b45309;
        }

        @media (max-width: 992px) {
            body {
                padding: 16px 12px;
            }

            .public-page-shell {
                max-width: 100%;
            }

            .card-plans {
                border-radius: 18px;
            }

            .card-header-custom {
                padding: 24px 22px;
            }

            .section-card {
                padding: 16px;
            }

            .plans-grid>.plan-col {
                flex: 0 0 100%;
                width: 100%;
                max-width: 100%;
            }

            .plan-option {
                min-height: auto;
            }

            .plan-name {
                font-size: 34px;
            }

            .plan-price {
                font-size: 30px;
            }

            .choice-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .payment-summary strong {
                font-size: 18px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px 10px;
                align-items: flex-start;
            }

            .public-page-shell,
            .container {
                max-width: 100%;
                padding: 0;
            }

            .card-plans {
                border-radius: 16px;
            }

            .card-header-custom {
                padding: 20px 16px;
            }

            .card-body {
                padding: 16px !important;
            }

            .section-title {
                font-size: 18px;
            }

            .plan-option {
                min-height: auto;
                padding: 18px;
            }

            .plan-name {
                font-size: 30px;
            }

            .plan-price {
                font-size: 26px;
            }

            .plan-price span {
                font-size: 15px;
            }

            .choice-grid {
                grid-template-columns: 1fr;
            }

            .choice-option {
                padding: 11px 10px;
            }

            .choice-option .title {
                font-size: 13px;
            }

            .choice-option .subtitle {
                font-size: 11px;
            }

            .btn-primary-custom {
                width: 100%;
                padding-inline: 18px;
            }

            .payment-summary {
                font-size: 13px;
            }

            .alert-premium {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 10px 8px;
            }

            .public-mobile-bar {
                padding: 11px 12px;
                border-radius: 16px;
            }

            .public-sidebar-toggle,
            .public-sidebar-close {
                width: 42px;
                height: 42px;
                border-radius: 14px;
            }

            .card-plans {
                border-radius: 14px;
            }

            .card-header-custom {
                padding: 18px 14px;
            }

            .card-header-custom h3 {
                font-size: 1.4rem;
            }

            .card-header-custom p {
                font-size: 13px;
            }

            .card-body {
                padding: 14px !important;
            }

            .section-title {
                font-size: 17px;
                margin-bottom: 12px;
            }

            .section-card {
                padding: 14px;
                margin-bottom: 14px;
            }

            .form-control,
            .form-select {
                min-height: 42px;
                font-size: 14px;
            }

            .plan-option {
                padding: 16px 14px;
                border-radius: 16px;
                min-height: auto;
            }

            .plan-icon {
                width: 54px;
                height: 54px;
                font-size: 24px;
            }

            .plan-name {
                font-size: 24px;
            }

            .plan-price {
                font-size: 22px;
                line-height: 1.1;
            }

            .plan-price span {
                font-size: 13px;
            }

            .plan-cycle {
                font-size: 12px;
            }

            .plan-option-features li {
                font-size: 13px;
                line-height: 1.35;
            }

            .plan-select-indicator {
                font-size: 13px;
            }

            .choice-option {
                padding: 10px 8px;
            }

            .choice-option .title {
                font-size: 12px;
            }

            .choice-option .subtitle {
                font-size: 10px;
            }

            .payment-summary {
                font-size: 12px;
            }

            .payment-summary strong {
                font-size: 16px;
            }

            .btn-primary-custom {
                width: 100%;
                font-size: 16px;
            }

            .alert-premium {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <div class="public-sidebar-backdrop" id="publicSidebarBackdrop" aria-hidden="true"></div>
    <div class="public-page-shell">
        <div class="public-mobile-bar d-lg-none">
            <button class="public-sidebar-toggle" id="publicSidebarToggleBtn" aria-label="Abrir menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <div class="public-mobile-brand">
                <strong>RestauranteSaaS</strong>
                <span>Solicitação de plano</span>
            </div>
        </div>

        <div class="container main-content" id="mainContent">
        <!-- Exemplo de menu lateral (sidebar) -->
        <nav class="public-sidebar-menu" id="sidebarMenu" tabindex="-1" aria-label="Menu principal">
            <div class="public-sidebar-header">
                <div class="public-sidebar-title">
                    <span class="public-sidebar-kicker">Acesso rápido</span>
                    <strong>Menu</strong>
                    <span>Navegue sem esconder o formulário principal.</span>
                </div>
                <button type="button" class="public-sidebar-close" id="publicSidebarCloseBtn" aria-label="Fechar menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="public-sidebar-links">
                <li><a href="index.php" class="public-sidebar-link"><i class="fas fa-home"></i><span>Início</span></a></li>
                <li><a href="solicitar_plano.php" class="public-sidebar-link"><i class="fas fa-crown"></i><span>Solicitar Plano</span></a></li>
                <li><a href="https://wa.me/258840000000" class="public-sidebar-link"><i class="fab fa-whatsapp"></i><span>Suporte WhatsApp</span></a></li>
            </ul>
        </nav>
        <!-- Fim do menu lateral -->
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card-plans">
                    <div class="card-header-custom">
                        <h3 class="mb-1"><i class="fas fa-utensils me-2"></i>RestauranteSaaS</h3>
                        <p class="mb-0 opacity-75">Solicite seu plano e comece a gerenciar seu restaurante</p>
                    </div>

                    <div class="card-body p-4">
                        <?php if ($mensagem): ?>
                            <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                                <?php echo nl2br(solicitar_plano_escape($mensagem)); ?>
                                <?php if ($tipo_msg === 'success'): ?>
                                    <br><small>Voce recebera um email quando sua solicitacao for aprovada.</small>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="_csrf" value="<?php echo solicitar_plano_escape($csrf_token); ?>">
                            <!-- Seção: Dados do Restaurante -->
                            <div class="section-card">
                                <div class="section-title"><i class="fas fa-store me-2 text-primary"></i>Dados do Restaurante</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome do Restaurante *</label>
                                        <input type="text" name="nome" class="form-control" placeholder="Ex: Restaurante Sabor" value="<?php echo solicitar_plano_escape($form['nome']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="email" class="form-control" placeholder="contato@restaurante.com" value="<?php echo solicitar_plano_escape($form['email']); ?>" autocomplete="email" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Telefone</label>
                                        <input type="tel" name="telefone" class="form-control" placeholder="+258 84 9087533" value="<?php echo solicitar_plano_escape($form['telefone']); ?>" autocomplete="tel">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" class="form-control" placeholder="Maputo" value="<?php echo solicitar_plano_escape($form['cidade']); ?>" autocomplete="address-level2">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Endereço</label>
                                        <input type="text" name="endereco" class="form-control" placeholder="Av. Principal, 123" value="<?php echo solicitar_plano_escape($form['endereco']); ?>" autocomplete="street-address">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">NUIT</label>
                                        <input type="text" name="nuit" class="form-control" placeholder="400000000" value="<?php echo solicitar_plano_escape($form['nuit']); ?>" inputmode="numeric">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Logotipo do Restaurante *</label>
                                        <div class="input-group">
                                            <input type="file" name="logo" class="form-control" id="logo_restaurante" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewLogoRestaurante(this)">
                                        </div>
                                        <small class="text-muted">Formatos aceitos: JPG, PNG ou WEBP (máx. 4MB)</small>
                                        <div id="preview_logo_restaurante" class="mt-2" style="display: none;">
                                            <img id="img_logo_restaurante" src="" alt="Preview do logotipo" style="max-width: 180px; max-height: 180px; border-radius: 14px; border: 2px solid #dee2e6; background: #fff; object-fit: contain; padding: 8px;">
                                            <div id="arquivo_logo_info" class="small text-muted mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Seção: Escolha do Plano -->
                            <div class="section-title"><i class="fas fa-crown me-2 text-warning"></i>Escolha o Plano</div>
                            <div class="row g-3 mb-4 plans-grid align-items-stretch">
                                <!-- Plano Básico -->
                                <div class="col-md-4 plan-col">
                                    <div class="plan-option plan-basic" onclick="selecionarPlano('BASICO', <?php echo (int)$preco_basico['mensal']; ?>)">
                                        <div class="plan-icon" style="background: linear-gradient(135deg, #6c757d, #343a40); color: white;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="plan-name">Básico</div>
                                        <div class="plan-price"><?php echo format_mzn($preco_basico['mensal']); ?><span>MZN/mês</span></div>
                                        <div class="plan-cycle">Tri: <?php echo format_mzn($preco_basico['trimestral']); ?> MZN | Anual: <?php echo format_mzn($preco_basico['anual']); ?> MZN</div>
                                        <hr>
                                        <ul class="plan-option-features">
                                            <?php foreach (($recursos_planos_catalogo['BASICO'] ?? []) as $recurso): ?>
                                                <li><i class="fas fa-check"></i><?php echo solicitar_plano_escape($recurso); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div class="plan-select-indicator"><span class="plan-select-dot"></span><span>Selecionar</span></div>
                                        <input type="radio" name="plano_selecionado" id="plano_basico" value="BASICO" class="d-none">
                                    </div>
                                </div>

                                <!-- Plano Profissional -->
                                <div class="col-md-4 plan-col">
                                    <div class="plan-option plan-pro" onclick="selecionarPlano('PROFISSIONAL', <?php echo (int)$preco_profissional['mensal']; ?>)">
                                        <div class="plan-icon" style="background: linear-gradient(135deg, #17a2b8, #0dcaf0); color: white;">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="plan-name">Profissional</div>
                                        <div class="plan-price"><?php echo format_mzn($preco_profissional['mensal']); ?><span>MZN/mês</span></div>
                                        <div class="plan-cycle">Tri: <?php echo format_mzn($preco_profissional['trimestral']); ?> MZN | Anual: <?php echo format_mzn($preco_profissional['anual']); ?> MZN</div>
                                        <hr>
                                        <ul class="plan-option-features">
                                            <?php foreach (($recursos_planos_catalogo['PROFISSIONAL'] ?? []) as $recurso): ?>
                                                <li><i class="fas fa-check"></i><?php echo solicitar_plano_escape($recurso); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div class="plan-select-indicator"><span class="plan-select-dot"></span><span>Selecionar</span></div>
                                        <input type="radio" name="plano_selecionado" id="plano_profissional" value="PROFISSIONAL" class="d-none">
                                    </div>
                                </div>

                                <!-- Plano Empresarial -->
                                <div class="col-md-4 plan-col">
                                    <div class="plan-option plan-enterprise" onclick="selecionarPlano('EMPRESARIAL', <?php echo (int)$preco_empresarial['mensal']; ?>)">
                                        <div class="plan-icon" style="background: linear-gradient(135deg, #FF6B35, #F7931E); color: white;">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                        <div class="plan-name">Empresarial</div>
                                        <div class="plan-price"><?php echo format_mzn($preco_empresarial['mensal']); ?><span>MZN/mês</span></div>
                                        <div class="plan-cycle">Tri: <?php echo format_mzn($preco_empresarial['trimestral']); ?> MZN | Anual: <?php echo format_mzn($preco_empresarial['anual']); ?> MZN</div>
                                        <hr>
                                        <ul class="plan-option-features">
                                            <?php foreach (($recursos_planos_catalogo['EMPRESARIAL'] ?? []) as $recurso): ?>
                                                <li><i class="fas fa-check"></i><?php echo solicitar_plano_escape($recurso); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div class="plan-select-indicator"><span class="plan-select-dot"></span><span>Selecionar</span></div>
                                        <input type="radio" name="plano_selecionado" id="plano_empresarial" value="EMPRESARIAL" class="d-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Campo oculto para o plano selecionado -->
                            <input type="hidden" name="plano" id="plano_escolhido" value="<?php echo solicitar_plano_escape($form['plano']); ?>">
                            <input type="hidden" name="ciclo" id="ciclo_escolhido" value="<?php echo solicitar_plano_escape($form['ciclo']); ?>">
                            <input type="hidden" name="metodo" id="metodo_pagamento" value="<?php echo solicitar_plano_escape($form['metodo']); ?>">

                            <!-- Seção: Pagamento (apenas para planos pagos) -->
                            <div id="secao_pagamento" style="display: none;">
                                <div class="section-card">
                                    <div class="section-title"><i class="fas fa-credit-card me-2 text-success"></i>Informações de Pagamento</div>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Período da Assinatura *</label>
                                            <div class="choice-grid" id="ciclo_grid">
                                                <button type="button" class="choice-option active" data-ciclo="MENSAL" onclick="selecionarCiclo('MENSAL')">
                                                    <div class="title">Mensal</div>
                                                    <div class="subtitle">Cobrança a cada 30 dias</div>
                                                </button>
                                                <button type="button" class="choice-option" data-ciclo="TRIMESTRAL" onclick="selecionarCiclo('TRIMESTRAL')">
                                                    <div class="title">Trimestral</div>
                                                    <div class="subtitle">Melhor custo-benefício</div>
                                                </button>
                                                <button type="button" class="choice-option" data-ciclo="ANUAL" onclick="selecionarCiclo('ANUAL')">
                                                    <div class="title">Anual</div>
                                                    <div class="subtitle">Maior economia</div>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Método de Pagamento *</label>
                                            <div class="choice-grid" id="metodo_grid">
                                                <button type="button" class="choice-option" data-metodo="MPESA" onclick="selecionarMetodo('MPESA')">
                                                    <div class="title">📱 M-Pesa</div>
                                                    <div class="subtitle">Pagamento móvel instantâneo</div>
                                                </button>
                                                <button type="button" class="choice-option" data-metodo="CARTAO" onclick="selecionarMetodo('CARTAO')">
                                                    <div class="title">💳 Cartão</div>
                                                    <div class="subtitle">Crédito ou débito</div>
                                                </button>
                                                <button type="button" class="choice-option" data-metodo="TRANSFERENCIA" onclick="selecionarMetodo('TRANSFERENCIA')">
                                                    <div class="title">🏦 Transferência</div>
                                                    <div class="subtitle">BCI / Millennium</div>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="payment-summary">
                                                <div>Resumo da cobrança</div>
                                                <strong id="resumo_valor">0 MZN</strong>
                                                <div id="resumo_plano" class="small">Selecione um plano para ver o valor final</div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Instruções de pagamento:</strong><br>
                                                <span id="instrucoes_pagamento"></span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Foto do Comprovativo *</label>
                                            <div class="input-group">
                                                <input type="file" name="comprovativo" class="form-control" id="comprovativo" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" onchange="previewComprovativo(this)">
                                            </div>
                                            <small class="text-muted">Formatos aceitos: JPG, PNG, PDF (máx. 5MB)</small>
                                            <div id="preview_comprovativo" class="mt-2" style="display: none;">
                                                <img id="img_comprovativo" src="" alt="Preview" style="max-width: 200px; border-radius: 8px; border: 2px solid #dee2e6;">
                                                <div id="arquivo_comprovativo_info" class="small text-muted mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-warning alert-premium">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Atencao:</strong> Sua solicitacao sera analisada pelo administrador. Quando for aprovada, o email do admin recebera um link seguro para definir a senha e ativar o acesso.
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary-custom btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Solicitação
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-2">Já tem uma conta?</p>
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> Fazer Login
                            </a>
                        </div>
                    </div>
                </div>

                <p class="text-center text-white-50 mt-3">
                    &copy; <?php echo date('Y'); ?> RestauranteSaaS - Sistema de Gestão de Restaurantes
                </p>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const publicSidebar = document.getElementById('sidebarMenu');
        const publicSidebarToggleBtn = document.getElementById('publicSidebarToggleBtn');
        const publicSidebarCloseBtn = document.getElementById('publicSidebarCloseBtn');
        const publicSidebarBackdrop = document.getElementById('publicSidebarBackdrop');
        const publicMainContent = document.getElementById('mainContent');
        const publicMobileQuery = window.matchMedia('(max-width: 991px)');

        function closeSidebar() {
            document.body.classList.remove('public-menu-open');
            publicSidebar.classList.remove('is-open');
            publicMainContent.classList.remove('main-content-blur');
            if (publicSidebarToggleBtn) {
                publicSidebarToggleBtn.setAttribute('aria-expanded', 'false');
            }
        }

        function openSidebar() {
            if (!publicMobileQuery.matches) {
                return;
            }

            document.body.classList.add('public-menu-open');
            publicSidebar.classList.add('is-open');
            publicMainContent.classList.add('main-content-blur');
            if (publicSidebarToggleBtn) {
                publicSidebarToggleBtn.setAttribute('aria-expanded', 'true');
            }
        }

        function toggleSidebar() {
            if (document.body.classList.contains('public-menu-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (publicSidebarToggleBtn) {
            publicSidebarToggleBtn.addEventListener('click', toggleSidebar);
        }

        if (publicSidebarCloseBtn) {
            publicSidebarCloseBtn.addEventListener('click', closeSidebar);
        }

        if (publicSidebarBackdrop) {
            publicSidebarBackdrop.addEventListener('click', closeSidebar);
        }

        publicSidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        window.addEventListener('resize', function() {
            if (!publicMobileQuery.matches) {
                closeSidebar();
            }
        });
    </script>
    <script>
        var formElement = document.querySelector('form');
        var submitButton = formElement.querySelector('button[type="submit"]');
        var planoSelecionado = <?php echo json_encode($form['plano'], JSON_UNESCAPED_UNICODE); ?>;
        var cicloSelecionado = <?php echo json_encode($form['ciclo'], JSON_UNESCAPED_UNICODE); ?>;
        var valorPlano = 0;
        var metodoSelecionado = <?php echo json_encode($form['metodo'], JSON_UNESCAPED_UNICODE); ?>;

        var precosPorPlano = <?php echo json_encode([
                                    'BASICO' => [
                                        'MENSAL' => (int)$preco_basico['mensal'],
                                        'TRIMESTRAL' => (int)$preco_basico['trimestral'],
                                        'ANUAL' => (int)$preco_basico['anual'],
                                    ],
                                    'PROFISSIONAL' => [
                                        'MENSAL' => (int)$preco_profissional['mensal'],
                                        'TRIMESTRAL' => (int)$preco_profissional['trimestral'],
                                        'ANUAL' => (int)$preco_profissional['anual'],
                                    ],
                                    'EMPRESARIAL' => [
                                        'MENSAL' => (int)$preco_empresarial['mensal'],
                                        'TRIMESTRAL' => (int)$preco_empresarial['trimestral'],
                                        'ANUAL' => (int)$preco_empresarial['anual'],
                                    ],
                                ], JSON_UNESCAPED_UNICODE); ?>;

        function atualizarEstadoPagamento() {
            var secaoPagamento = document.getElementById('secao_pagamento');
            var campoComprovativo = document.getElementById('comprovativo');

            if (planoSelecionado) {
                secaoPagamento.style.display = 'block';
                campoComprovativo.required = true;
            } else {
                secaoPagamento.style.display = 'none';
                campoComprovativo.required = false;
            }
        }

        function formatarPlano(plano) {
            if (!plano) {
                return 'Nao selecionado';
            }

            return plano.charAt(0) + plano.slice(1).toLowerCase();
        }

        function selecionarPlano(plano) {
            planoSelecionado = plano;
            valorPlano = (precosPorPlano[plano] && precosPorPlano[plano][cicloSelecionado]) ? precosPorPlano[plano][cicloSelecionado] : 0;

            document.querySelectorAll('.plan-option').forEach(function(el) {
                el.classList.remove('selected');
            });

            if (plano === 'BASICO') {
                document.getElementById('plano_basico').closest('.plan-option').classList.add('selected');
                document.getElementById('plano_basico').checked = true;
            } else if (plano === 'PROFISSIONAL') {
                document.getElementById('plano_profissional').closest('.plan-option').classList.add('selected');
                document.getElementById('plano_profissional').checked = true;
            } else if (plano === 'EMPRESARIAL') {
                document.getElementById('plano_empresarial').closest('.plan-option').classList.add('selected');
                document.getElementById('plano_empresarial').checked = true;
            }

            document.getElementById('plano_escolhido').value = plano;
            document.getElementById('ciclo_escolhido').value = cicloSelecionado;
            atualizarEstadoPagamento();
            atualizarResumoPagamento();
            atualizarInstrucoes();
        }

        function selecionarCiclo(ciclo) {
            cicloSelecionado = ciclo;
            document.getElementById('ciclo_escolhido').value = ciclo;

            document.querySelectorAll('#ciclo_grid .choice-option').forEach(function(btn) {
                btn.classList.remove('active');
            });
            var alvo = document.querySelector('#ciclo_grid .choice-option[data-ciclo="' + ciclo + '"]');
            if (alvo) alvo.classList.add('active');

            if (planoSelecionado) {
                valorPlano = precosPorPlano[planoSelecionado][cicloSelecionado] || valorPlano;
                atualizarResumoPagamento();
                atualizarInstrucoes();
            }
        }

        function selecionarMetodo(metodo) {
            metodoSelecionado = metodo;
            document.getElementById('metodo_pagamento').value = metodo;

            document.querySelectorAll('#metodo_grid .choice-option').forEach(function(btn) {
                btn.classList.remove('active');
            });
            var alvo = document.querySelector('#metodo_grid .choice-option[data-metodo="' + metodo + '"]');
            if (alvo) alvo.classList.add('active');

            atualizarInstrucoes();
        }

        function atualizarResumoPagamento() {
            var elValor = document.getElementById('resumo_valor');
            var elPlano = document.getElementById('resumo_plano');
            var planoLabel = formatarPlano(planoSelecionado);
            var cicloLabel = cicloSelecionado.charAt(0) + cicloSelecionado.slice(1).toLowerCase();

            elValor.textContent = (valorPlano || 0).toLocaleString('pt-PT') + ' MZN';
            elPlano.textContent = 'Plano ' + planoLabel + ' | Ciclo ' + cicloLabel;
        }

        function atualizarInstrucoes() {
            var instrucoes = '';
            var metodo = metodoSelecionado;

            if (metodo === 'MPESA') {
                instrucoes = 'Envie ' + valorPlano + ' MZN para o número +258 84 9087533 e anexe o comprovativo.';
            } else if (metodo === 'CARTAO') {
                instrucoes = 'Use cartão de crédito ou débito no ponto de pagamento e anexe o comprovativo.';
            } else if (metodo === 'TRANSFERENCIA') {
                instrucoes = 'Transfira para a conta BCI - #3456789088 / Millennium - #123097654 e anexe o comprovativo da operação.';
            } else {
                instrucoes = 'Selecione o método de pagamento para ver as instruções detalhadas.';
            }

            document.getElementById('instrucoes_pagamento').textContent = instrucoes;
        }

        formElement.addEventListener('submit', function(e) {
            var comprovativoInput = document.getElementById('comprovativo');

            if (!planoSelecionado) {
                e.preventDefault();
                alert('Selecione um plano para continuar.');
                return;
            }
            if (!metodoSelecionado) {
                e.preventDefault();
                alert('Selecione um método de pagamento para continuar.');
                return;
            }
            if (!comprovativoInput.files || !comprovativoInput.files[0]) {
                e.preventDefault();
                alert('Anexe o comprovativo para continuar.');
                return;
            }

            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
        });

        function previewComprovativo(input) {
            var preview = document.getElementById('preview_comprovativo');
            var img = document.getElementById('img_comprovativo');
            var info = document.getElementById('arquivo_comprovativo_info');

            if (input.files && input.files[0]) {
                var file = input.files[0];
                var tamanhoKb = Math.max(1, Math.round(file.size / 1024));
                info.textContent = file.name + ' (' + tamanhoKb + ' KB)';
                preview.style.display = 'block';

                if (file.type.startsWith('image/')) {
                    img.style.display = 'block';
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    img.src = '';
                    img.style.display = 'none';
                }
            }

            if (!input.files || !input.files[0]) {
                img.src = '';
                img.style.display = 'none';
                info.textContent = '';
                preview.style.display = 'none';
            }
        }

        function previewLogoRestaurante(input) {
            var preview = document.getElementById('preview_logo_restaurante');
            var img = document.getElementById('img_logo_restaurante');
            var info = document.getElementById('arquivo_logo_info');

            if (input.files && input.files[0]) {
                var file = input.files[0];
                var tamanhoKb = Math.max(1, Math.round(file.size / 1024));
                info.textContent = file.name + ' (' + tamanhoKb + ' KB)';
                preview.style.display = 'block';

                if (file.type.startsWith('image/')) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    img.src = '';
                }
                return;
            }

            img.src = '';
            info.textContent = '';
            preview.style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            atualizarEstadoPagamento();
            selecionarCiclo(cicloSelecionado || 'MENSAL');

            if (planoSelecionado) {
                selecionarPlano(planoSelecionado);
            } else {
                atualizarResumoPagamento();
                atualizarInstrucoes();
            }

            if (metodoSelecionado) {
                selecionarMetodo(metodoSelecionado);
            }
        });
    </script>
</body>

</html>

