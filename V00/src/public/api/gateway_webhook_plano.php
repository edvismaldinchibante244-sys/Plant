<?php

/**
 * API - Webhook de Pagamento (integração futura)
 *
 * Objetivo:
 * - receber eventos do gateway (futuro)
 * - aprovar/rejeitar compras_planos automaticamente
 *
 * Evento esperado (exemplo):
 * {
 *   "event": "payment.approved",
 *   "compra_id": 123,
 *   "transaction_id": "abc123",
 *   "provider": "gateway-x"
 * }
 */

session_start();
include_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Token simples para hardening inicial (substituir por assinatura HMAC do gateway)
$configuredToken = getenv('PLANO_WEBHOOK_TOKEN') ?: '';
$incomingToken = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';
if (!empty($configuredToken) && !hash_equals($configuredToken, $incomingToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'Payload inválido']);
    exit;
}

$event = strtolower(trim((string)($payload['event'] ?? '')));
$compraId = (int)($payload['compra_id'] ?? 0);
$transactionId = trim((string)($payload['transaction_id'] ?? ''));
$provider = trim((string)($payload['provider'] ?? 'gateway'));

if ($compraId <= 0 || $event === '') {
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios ausentes']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de conexão']);
    exit;
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT id, status FROM compras_planos WHERE id = ? FOR UPDATE');
    $stmt->execute([$compraId]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$compra) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Compra não encontrada']);
        exit;
    }

    if ($compra['status'] !== 'PENDENTE') {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Compra já processada']);
        exit;
    }

    if ($event === 'payment.approved') {
        // TODO próximo passo: reaproveitar fluxo de aprovacao de super_admin_plano_aprovar.php
        // para ativar plano, atualizar data_fim e enviar notificacoes.
        $obs = 'Webhook ' . $provider . ' tx=' . $transactionId;
        $up = $db->prepare("UPDATE compras_planos SET status='APROVADO', observacao=? WHERE id=?");
        $up->execute([$obs, $compraId]);
    } elseif ($event === 'payment.rejected' || $event === 'payment.failed') {
        $obs = 'Webhook ' . $provider . ' tx=' . $transactionId;
        $up = $db->prepare("UPDATE compras_planos SET status='REJEITADO', observacao=? WHERE id=?");
        $up->execute([$obs, $compraId]);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Evento não suportado']);
        exit;
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Webhook processado',
        'data' => [
            'event' => $event,
            'compra_id' => $compraId
        ]
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no webhook: ' . $e->getMessage()]);
}
