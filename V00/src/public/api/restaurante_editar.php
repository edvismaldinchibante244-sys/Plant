<?php

/*
 
   API - EDITAR RESTAURANTE
  Atualiza os dados de um restaurante
 
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/csrf.php';
include_once __DIR__ . '/../../config/restaurante_status_helper.php';
include_once __DIR__ . '/../../config/super_admin_permissions.php';

header('Content-Type: application/json; charset=utf-8');

if (!super_admin_is_authenticated()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado'
    ]);
    exit;
}

super_admin_require_permission_json('manage_restaurants');

csrf_validate_or_json();

$database = new Database();
$db = $database->getConnection();

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);

// Se não vier JSON, tentar POST
if (!$input) {
    $input = $_POST;
}

$id = intval($input['id'] ?? 0);
$nome = trim($input['nome'] ?? '');
$email = trim($input['email'] ?? '');
$telefone = trim($input['telefone'] ?? '');
$endereco = trim($input['endereco'] ?? '');
$cidade = trim($input['cidade'] ?? '');
$nuit = trim($input['nuit'] ?? '');
$plano = strtoupper(trim((string)($input['plano'] ?? 'BASICO')));
if ($plano === 'ENTERPRISE') {
    $plano = 'EMPRESARIAL';
}
$status = restaurante_status_normalizar($db, $input['status'] ?? '', 'ATIVO');

// Validar ID
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do restaurante inválido'
    ]);
    exit;
}

// Validar campos obrigatórios
if (empty($nome)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nome do restaurante é obrigatório'
    ]);
    exit;
}

if (empty($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email do restaurante é obrigatório'
    ]);
    exit;
}

// Validar email único (excluindo o próprio restaurante)
$check = $db->prepare("SELECT id FROM restaurantes WHERE email = ? AND id != ?");
$check->execute([$email, $id]);
if ($check->rowCount() > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Email já está em uso por outro restaurante'
    ]);
    exit;
}

// Validar planos permitidos
$planos_validos = ['BASICO', 'PROFISSIONAL', 'EMPRESARIAL'];
if (!in_array($plano, $planos_validos, true)) {
    $plano = 'BASICO';
}

try {
    // Atualizar restaurante
    $query = "UPDATE restaurantes SET 
                nome = :nome, 
                email = :email, 
                telefone = :telefone, 
                endereco = :endereco, 
                cidade = :cidade, 
                nuit = :nuit, 
                plano = :plano, 
                status = :status 
              WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':telefone' => $telefone,
        ':endereco' => $endereco,
        ':cidade' => $cidade,
        ':nuit' => $nuit,
        ':plano' => $plano,
        ':status' => $status,
        ':id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Restaurante atualizado com sucesso!',
        'data' => [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'plano' => $plano,
            'status' => $status
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar restaurante: ' . $e->getMessage()
    ]);
}
