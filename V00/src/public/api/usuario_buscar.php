<?php

/*
   API - Buscar Usuário por ID
 */
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
include_once '../../config/restaurante_context.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

// Verificações de sessão
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    $response['message'] = 'Não autenticado';
    echo json_encode($response);
    exit;
}

if (session_restaurante_contexto_id() <= 0) {
    $response['message'] = 'Restaurante não identificado';
    echo json_encode($response);
    exit;
}

if ($_SESSION['perfil'] !== 'ADMIN') {
    $response['message'] = 'Sem permissão';
    echo json_encode($response);
    exit;
}

if (empty($_GET['id'])) {
    $response['message'] = 'ID não fornecido';
    echo json_encode($response);
    exit;
}

// Conectar ao banco
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    $response['message'] = 'Erro de conexão';
    echo json_encode($response);
    exit;
}

// Buscar usuário (incluindo campo foto)
$id = intval($_GET['id']);
$rid = session_restaurante_contexto_id();

$query = "SELECT id, nome, email, perfil, ativo, foto FROM usuarios WHERE id = ? AND restaurante_id = ? LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([$id, $rid]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario) {
    echo json_encode(['success' => true, 'data' => $usuario]);
} else {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
}
