<?php

/**
 * API - Listar Produtos por Categoria/Busca
 * Suporta: ?categoria_id=ID&busca=termo&restaurante_id=ID
 */

include_once __DIR__ . '/../../config/auth_check.php';
include_once __DIR__ . '/../../config/restaurante_context.php';

if (!function_exists('produto_listar_debug_log')) {
    function produto_listar_debug_log(string $message): void
    {
        $logDir = sys_get_temp_dir();
        if (!$logDir || !is_dir($logDir) || !is_writable($logDir)) {
            return;
        }

        $logFile = rtrim($logDir, "/\\") . DIRECTORY_SEPARATOR . 'debug_produto_api.log';
        @file_put_contents(
            $logFile,
            date('Y-m-d H:i:s') . ' | ' . $message . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $restaurante_id = session_restaurante_contexto_id();
    $categoria_id = isset($_GET['categoria_id']) && $_GET['categoria_id'] !== '' ? $_GET['categoria_id'] : null;
    $busca = $_GET['busca'] ?? '';
    produto_listar_debug_log("restaurante_id=" . var_export($restaurante_id, true) . " | categoria_id=" . var_export($categoria_id, true) . " | busca=" . var_export($busca, true));
    if (!$restaurante_id) {
        echo json_encode(["success" => false, "message" => "Restaurante não informado"]);
        exit;
    }

    include_once __DIR__ . '/../../config/database.php';
    include_once __DIR__ . '/../../Model/Produto.php';
    include_once __DIR__ . '/../../Model/Categoria.php';

    $database = new Database();
    $db = $database->getConnection();

    $produto = new Produto($db);
    $produto->restaurante_id = $restaurante_id;
    // Listar com filtros
    $produtos = $produto->listarFiltrado($categoria_id, $busca);
    $produtos_array = $produtos->fetchAll(PDO::FETCH_ASSOC);
    produto_listar_debug_log('produtos retornados=' . count($produtos_array));
    echo json_encode([
        "success" => true,
        "data" => $produtos_array,
        "filtros" => [
            "categoria_id" => $categoria_id,
            "busca" => $busca
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $logMsg = date('Y-m-d H:i:s') . " | ERRO: " . $e->getMessage() . "\n";
    $logMsg .= $e->getTraceAsString() . "\n";
    produto_listar_debug_log(trim($logMsg));
    error_log("produto_listar.php - " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Erro interno: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
