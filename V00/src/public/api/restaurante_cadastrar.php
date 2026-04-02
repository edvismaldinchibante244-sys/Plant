<?php

/*
   API - Cadastrar Restaurante
*/
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/csrf.php';
include_once __DIR__ . '/../../config/restaurante_status_helper.php';
include_once __DIR__ . '/../../config/super_admin_permissions.php';

if (!super_admin_is_authenticated()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

super_admin_require_permission_json('manage_restaurants');

csrf_validate_or_json();

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco de dados']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$nome = isset($data['nome']) ? trim($data['nome']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$telefone = isset($data['telefone']) ? trim($data['telefone']) : '';
$endereco = isset($data['endereco']) ? trim($data['endereco']) : '';
$cpf = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', $data['cpf']) : '';

if (empty($nome) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Nome e email são obrigatórios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    // Verificar se email já existe
    $stmt = $db->prepare("SELECT id FROM restaurantes WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado para um restaurante']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado']);
        exit;
    }

    // A conta admin nasce sem senha reutilizavel; a definicao ocorre por link seguro.
    $senha_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

    $db->beginTransaction();

    // Inserir restaurante em estado inicial compativel com o schema local.
    $statusInicial = restaurante_status_resolver_inicial($db);
    $restauranteCols = ['nome', 'email', 'telefone', 'endereco', 'created_at'];
    $restauranteVals = ['?', '?', '?', '?', 'NOW()'];
    $restauranteParams = [$nome, $email, $telefone, $endereco];

    if ($statusInicial !== null) {
        $restauranteCols[] = 'status';
        $restauranteVals[] = '?';
        $restauranteParams[] = $statusInicial;
    }

    $stmt = $db->prepare(
        'INSERT INTO restaurantes (' . implode(', ', $restauranteCols) . ') VALUES (' . implode(', ', $restauranteVals) . ')'
    );
    $stmt->execute($restauranteParams);
    $restaurante_id = $db->lastInsertId();

    // Inserir usuário administrador (dono) já vinculado ao restaurante.
    $stmt = $db->prepare("INSERT INTO usuarios (restaurante_id, nome, email, senha, perfil, ativo, created_at) VALUES (?, ?, ?, ?, 'ADMIN', 0, NOW())");
    $stmt->execute([$restaurante_id, $nome, $email, $senha_hash]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Restaurante cadastrado com sucesso! Aguarde aprovação.',
        'restaurante_id' => $restaurante_id,
        'status' => $statusInicial
    ]);
} catch (Throwable $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
