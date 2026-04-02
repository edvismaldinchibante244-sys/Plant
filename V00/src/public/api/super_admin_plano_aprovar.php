<?php

/**
   API - Super Admin Aprovar/Rejeitar Compra de Plano
   Usado pelo super admin para ativar o plano após verificar o pagamento
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/plano_check.php';
include_once '../../config/csrf.php';
include_once '../../config/super_admin_permissions.php';
include_once '../../config/plano_notificacoes.php';
include_once '../../config/email_helper.php';
include_once '../../config/password_reset_helper.php';

header('Content-Type: application/json');

// Verificar se é super admin (fundador SaaS)
$isSuperAdmin = isset($_SESSION['logado'], $_SESSION['super_admin'])
    && $_SESSION['logado'] === true
    && intval($_SESSION['super_admin']) === 1;

if (!$isSuperAdmin) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Acesso negado"]);
    exit;
}

super_admin_require_permission_json('approve_plans');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_json();

    $requestId = 'approve_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    $log = static function ($nivel, $mensagem, array $ctx = []) use ($requestId) {
        $ctx['request_id'] = $requestId;
        error_log('[PLANO_APROVACAO][' . $nivel . '] ' . $mensagem . ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE));
    };

    $compra_id = intval($_POST['compra_id'] ?? 0);
    $acao = $_POST['acao'] ?? ''; // 'aprovar' ou 'rejeitar'
    $observacao = trim($_POST['observacao'] ?? ''); // Observação do super admin

    if ($compra_id <= 0) {
        echo json_encode(["success" => false, "message" => "ID inválido"]);
        exit;
    }

    if (!in_array($acao, ['aprovar', 'rejeitar'])) {
        echo json_encode(["success" => false, "message" => "Ação inválida"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    $colunasCompra = [];
    $stmtCols = $db->query('SHOW COLUMNS FROM compras_planos');
    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
        $colunasCompra[] = $col['Field'];
    }

    $colunasRestaurante = [];
    $stmtColsRest = $db->query('SHOW COLUMNS FROM restaurantes');
    while ($col = $stmtColsRest->fetch(PDO::FETCH_ASSOC)) {
        $colunasRestaurante[] = $col['Field'];
    }

    $campoTelefone = in_array('telefone', $colunasRestaurante, true)
        ? 'r.telefone'
        : "''";

    // Buscar compra (inclui email do restaurante e email do admin do restaurante)
    $stmt = $db->prepare("
        SELECT cp.*, r.nome as restaurante_nome, r.email as restaurante_email, {$campoTelefone} as restaurante_telefone,
               (SELECT u.email FROM usuarios u WHERE u.restaurante_id = cp.restaurante_id AND u.perfil = 'ADMIN' ORDER BY u.id ASC LIMIT 1) AS admin_email
        FROM compras_planos cp
        INNER JOIN restaurantes r ON cp.restaurante_id = r.id
        WHERE cp.id = ?
    ");
    $stmt->execute([$compra_id]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$compra) {
        echo json_encode(["success" => false, "message" => "Compra não encontrada"]);
        exit;
    }

    $stmtAdmin = $db->prepare("
        SELECT id, nome, email, ativo, senha
        FROM usuarios
        WHERE restaurante_id = ? AND perfil = 'ADMIN'
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmtAdmin->execute([$compra['restaurante_id']]);
    $adminUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: null;
    $compra['admin_email'] = trim((string)($adminUser['email'] ?? $compra['admin_email'] ?? ''));
    $compra['admin_nome'] = trim((string)($adminUser['nome'] ?? 'Administrador'));
    $compra['restaurante_email'] = trim((string)($compra['restaurante_email'] ?? ''));
    $compra['contato_email'] = $compra['restaurante_email'] !== ''
        ? $compra['restaurante_email']
        : $compra['admin_email'];
    $passwordSetupRequiredPreview = $adminUser
        && (((int)($adminUser['ativo'] ?? 0) !== 1) || trim((string)($adminUser['senha'] ?? '')) === '');

    if ($acao === 'aprovar' && $passwordSetupRequiredPreview) {
        password_reset_ensure_table($db);
    }

    if ($compra['status'] !== 'PENDENTE') {
        echo json_encode(["success" => false, "message" => "Esta compra já foi processada"]);
        exit;
    }

    if ($acao === 'rejeitar') {
        // Apenas rejeitar
        $obs_final = !empty($observacao) ? $observacao : 'Rejeitado pelo Super Admin';
        if (in_array('observacao', $colunasCompra, true)) {
            $stmt = $db->prepare("UPDATE compras_planos SET status = 'REJEITADO', observacao = ? WHERE id = ?");
            $stmt->execute([$obs_final, $compra_id]);
        } else {
            $stmt = $db->prepare("UPDATE compras_planos SET status = 'REJEITADO' WHERE id = ?");
            $stmt->execute([$compra_id]);
        }

        $emailRejeicaoEnviado = false;
        $warningMessage = null;
        if (!empty($compra['contato_email'])) {
            $emailRejeicaoEnviado = plano_notificar_rejeitado(
                (string)$compra['contato_email'],
                (string)($compra['restaurante_telefone'] ?? ''),
                (string)($compra['restaurante_nome'] ?? 'Restaurante'),
                (string)($compra['plano_novo'] ?? ''),
                $obs_final
            );

            if (!$emailRejeicaoEnviado) {
                $emailErrorDetail = function_exists('saas_email_get_last_error') ? saas_email_get_last_error() : null;
                $warningMessage = 'Compra rejeitada, mas nao foi possivel enviar o email de notificacao.';
                if (is_string($emailErrorDetail) && $emailErrorDetail !== '') {
                    if (stripos($emailErrorDetail, 'authenticate') !== false) {
                        $warningMessage .= ' O SMTP do Gmail rejeitou a autenticacao. Verifique o SMTP_USERNAME e use uma senha de app valida no SMTP_PASSWORD.';
                    } else {
                        $warningMessage .= ' Detalhe tecnico: ' . $emailErrorDetail;
                    }
                }
            }
        } else {
            $warningMessage = 'Compra rejeitada, mas o restaurante nao possui email de administrador para notificacao.';
        }

        $response = [
            "success" => true,
            "message" => "Compra rejeitada",
            "data" => [
                "email_enviado" => $emailRejeicaoEnviado === true,
                "request_id" => $requestId,
            ],
        ];

        if ($warningMessage !== null) {
            $response['warning'] = $warningMessage;
        }

        echo json_encode($response);

        $log('INFO', 'fluxo de rejeicao processado', [
            'compra_id' => $compra_id,
            'email' => (string)($compra['contato_email'] ?? ''),
            'email_enviado' => $emailRejeicaoEnviado,
            'warning' => $warningMessage,
        ]);

        exit;
    }

    // Aprovar - atualizar compra e plano do restaurante
    try {
        $db->beginTransaction();
        $passwordSetupLink = null;
        $passwordSetupExpiresAt = null;
        $passwordSetupRequired = $passwordSetupRequiredPreview;

        // 1. Atualizar status da compra
        $obs_final = !empty($observacao) ? $observacao : 'Aprovado pelo Super Admin';
        $setParts = ["status = 'APROVADO'"];
        $paramsCompra = [];

        if (in_array('data_pagamento', $colunasCompra, true)) {
            $setParts[] = "data_pagamento = NOW()";
        }

        if (in_array('observacao', $colunasCompra, true)) {
            $setParts[] = "observacao = ?";
            $paramsCompra[] = $obs_final;
        }

        $paramsCompra[] = $compra_id;
        $stmt = $db->prepare("UPDATE compras_planos SET " . implode(', ', $setParts) . " WHERE id = ?");
        $stmt->execute($paramsCompra);
        $log('INFO', 'compra marcada como APROVADA', ['compra_id' => $compra_id, 'restaurante_id' => (int)$compra['restaurante_id']]);

        // 2. Calcular ciclo e data fim do plano
        $cicloCompra = strtoupper((string)($compra['ciclo'] ?? 'MENSAL'));
        if (!in_array($cicloCompra, ['MENSAL', 'TRIMESTRAL', 'ANUAL'], true)) {
            if (!empty($compra['metodo_pagamento']) && strpos((string)$compra['metodo_pagamento'], '-') !== false) {
                $partes = explode('-', (string)$compra['metodo_pagamento']);
                $possivelCiclo = strtoupper(trim(end($partes)));
                if ($possivelCiclo === 'MENSAL' || $possivelCiclo === 'TRIMESTRAL' || $possivelCiclo === 'ANUAL') {
                    $cicloCompra = $possivelCiclo;
                }
            }
        }

        $diasPlano = ['MENSAL' => 30, 'TRIMESTRAL' => 90, 'ANUAL' => 365][$cicloCompra] ?? 30;

        $colunaFim = null;
        if (in_array('data_fim', $colunasRestaurante, true)) {
            $colunaFim = 'data_fim';
        } elseif (in_array('data_fim_plano', $colunasRestaurante, true)) {
            $colunaFim = 'data_fim_plano';
        }

        $baseDate = new DateTimeImmutable('today');

        $novaDataFim = $baseDate->modify('+' . $diasPlano . ' days')->format('Y-m-d');
        $log('INFO', 'validade calculada para o novo plano', [
            'compra_id' => $compra_id,
            'restaurante_id' => (int)$compra['restaurante_id'],
            'ciclo' => $cicloCompra,
            'dias' => $diasPlano,
            'coluna_base' => $colunaFim,
            'data_base' => $baseDate->format('Y-m-d'),
            'nova_data_fim' => $novaDataFim,
        ]);

        // 3. Sincronizar plano (função robusta que trata erros internamente)
        $log('INFO', 'iniciando sincronizacao de plano', [
            'compra_id' => $compra_id,
            'restaurante_id' => (int)$compra['restaurante_id'],
            'plano_novo' => (string)$compra['plano_novo'],
            'nova_data_fim' => $novaDataFim,
        ]);
        $sincronizado = plano_sincronizar_restaurante_plano(
            $compra['restaurante_id'],
            $compra['plano_novo'],
            $novaDataFim,
            'PAGO',
            'Aprovado pelo Super Admin',
            $db
        );

        if (!$sincronizado) {
            // Nao bloquear aprovacao por falha na camada de sincronizacao auxiliar.
            $log('WARN', 'falha ao sincronizar plano auxiliar, aprovacao principal mantida', [
                'compra_id' => $compra_id,
                'restaurante_id' => (int)$compra['restaurante_id'],
            ]);
        } else {
            $log('INFO', 'plano sincronizado com sucesso', [
                'compra_id' => $compra_id,
                'restaurante_id' => (int)$compra['restaurante_id'],
            ]);
        }

        // 4. Ativar o usuário admin do restaurante.
        $stmt = $db->prepare("UPDATE usuarios SET ativo = 1 WHERE restaurante_id = ? AND perfil = 'ADMIN'");
        $stmt->execute([$compra['restaurante_id']]);
        $log('INFO', 'usuario admin ativado sem sobrescrever senha existente', ['restaurante_id' => (int)$compra['restaurante_id']]);

        if ($passwordSetupRequired && $compra['contato_email'] !== '') {
            $resetData = password_reset_create_token(
                $db,
                $compra['contato_email'],
                '+1 hour',
                $adminUser ? (int)$adminUser['id'] : null
            );
            $passwordSetupLink = password_reset_build_link($resetData['token']);
            $passwordSetupExpiresAt = $resetData['expires_at'];
            $log('INFO', 'token seguro para definicao de senha criado', [
                'email' => $compra['contato_email'],
                'restaurante_id' => (int)$compra['restaurante_id'],
                'expires_at' => $passwordSetupExpiresAt,
            ]);
        }

        $db->commit();
        $log('INFO', 'transacao concluida com sucesso', ['compra_id' => $compra_id]);

        $emailEnviado = null;
        $warningMessage = null;
        $isLocal = isset($_SERVER['HTTP_HOST']) && (
            stripos((string)$_SERVER['HTTP_HOST'], 'localhost') !== false
            || stripos((string)$_SERVER['HTTP_HOST'], '127.0.0.1') !== false
        );

        if ($passwordSetupRequired) {
            if (empty($compra['contato_email'])) {
                $warningMessage = 'Plano aprovado, mas o restaurante nao possui email de contacto para enviar o link de definicao de senha.';
                $log('WARN', 'fluxo de definicao de senha sem email de admin', [
                    'compra_id' => $compra_id,
                    'restaurante_id' => (int)$compra['restaurante_id'],
                ]);
            } elseif ($passwordSetupLink === null) {
                $warningMessage = 'Plano aprovado, mas nao foi possivel preparar o link seguro para definir a senha.';
                $log('WARN', 'fluxo de definicao de senha sem link gerado', [
                    'compra_id' => $compra_id,
                    'restaurante_id' => (int)$compra['restaurante_id'],
                ]);
            } else {
                $nomeRestaurante = $compra['restaurante_nome'] ?? 'Seu Restaurante';
                $nomeAdmin = $compra['admin_nome'] ?: 'Administrador';
                $emailAdmin = $compra['contato_email'];

                $mensagemEmail = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #FF6B35; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #eee; border-radius: 0 0 8px 8px; }
                        .info-box { background-color: #fff4ea; padding: 15px; border-left: 4px solid #FF6B35; margin: 20px 0; }
                        .label { font-weight: bold; color: #FF6B35; }
                        .value { font-family: monospace; background: white; padding: 5px 10px; border-radius: 4px; display: inline-block; }
                        .footer { margin-top: 20px; font-size: 12px; color: #999; }
                        a.button { display: inline-block; background-color: #FF6B35; color: white; padding: 10px 30px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Plano aprovado com sucesso</h2>
                        </div>
                        <div class='content'>
                            <p>Ola, {$nomeAdmin}.</p>
                            <p>O plano do restaurante <strong>{$nomeRestaurante}</strong> foi aprovado e o acesso administrativo ja esta liberado.</p>
                            <div class='info-box'>
                                <p><span class='label'>Login:</span> <span class='value'>{$emailAdmin}</span></p>
                                <p><span class='label'>Senha:</span> voce deve criar uma senha nova pelo link seguro abaixo.</p>
                            </div>
                            <p style='text-align: center;'>
                                <a href='{$passwordSetupLink}' class='button'>Definir minha senha</a>
                            </p>
                            <p>Se preferir, copie e cole este link no navegador:</p>
                            <p>{$passwordSetupLink}</p>
                            <p><strong>Este link expira em 1 hora.</strong></p>
                            <div class='footer'>
                                <p>Por seguranca, nenhuma senha e enviada por email.</p>
                                <p>&copy; RestauranteSaaS - Gestao Inteligente de Restaurantes</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $emailEnviado = saas_enviar_email($emailAdmin, 'Plano aprovado - defina sua senha de acesso', $mensagemEmail);
                if ($emailEnviado) {
                    $log('INFO', 'email com link seguro enviado com sucesso', [
                        'email' => $emailAdmin,
                        'restaurante_id' => (int)$compra['restaurante_id'],
                    ]);
                } else {
                    $emailErrorDetail = function_exists('saas_email_get_last_error') ? saas_email_get_last_error() : null;
                    $warningMessage = 'Plano aprovado, mas nao foi possivel enviar o email com o link seguro para definir a senha.';
                    if (is_string($emailErrorDetail) && $emailErrorDetail !== '') {
                        if (stripos($emailErrorDetail, 'authenticate') !== false) {
                            $warningMessage .= ' O SMTP do Gmail rejeitou a autenticacao. Verifique o SMTP_USERNAME e use uma senha de app valida no SMTP_PASSWORD.';
                        } else {
                            $warningMessage .= ' Detalhe tecnico: ' . $emailErrorDetail;
                        }
                    }
                    $log('WARN', 'falha ao enviar email com link seguro', [
                        'email' => $emailAdmin,
                        'restaurante_id' => (int)$compra['restaurante_id'],
                        'email_error' => $emailErrorDetail,
                    ]);
                }
            }
        }

        $mensagemResposta = 'Plano ativado com sucesso para ' . $compra['restaurante_nome'] . '!';
        if ($passwordSetupRequired && $emailEnviado) {
            $mensagemResposta .= ' Um link seguro para definir a senha foi enviado ao email do administrador.';
        }

        $responseData = [
            "success" => true,
            "message" => $mensagemResposta,
            "data" => [
                "plano_ativado" => $compra['plano_novo'],
                "restaurante" => $compra['restaurante_nome'],
                "restaurante_id" => $compra['restaurante_id'],
                "request_id" => $requestId,
                "requer_senha" => $passwordSetupRequired,
                "email_enviado" => $emailEnviado === true
            ]
        ];

        if ($warningMessage !== null) {
            $responseData['warning'] = $warningMessage;
        }

        if ($passwordSetupLink !== null && ($isLocal || $emailEnviado === false)) {
            $responseData['data']['password_setup_url'] = $passwordSetupLink;
        }

        if ($passwordSetupExpiresAt !== null) {
            $responseData['data']['password_setup_expires_at'] = $passwordSetupExpiresAt;
        }

        echo json_encode($responseData);

        $log('INFO', 'notificacao de aprovacao pulada no request sincrono', [
            'compra_id' => $compra_id,
            'email' => (string)($compra['contato_email'] ?? ''),
            'restaurante_id' => (int)$compra['restaurante_id'],
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $errorMsg = $e->getMessage();
        $log('ERROR', 'falha ao aprovar plano', [
            'compra_id' => $compra_id,
            'restaurante_id' => (int)($compra['restaurante_id'] ?? 0),
            'erro' => $errorMsg,
            'codigo' => (int)$e->getCode(),
        ]);

        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Erro ao processar aprovação de plano. Consulte os logs com request_id.",
            "request_id" => $requestId,
            "debug" => (php_sapi_name() === 'cli' ? $errorMsg : null)
        ]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}



