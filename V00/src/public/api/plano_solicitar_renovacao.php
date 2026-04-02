<?php

/*
   API - Solicitar Renovacao/Alteracao de Plano (manual guiada)
   Cria uma compra pendente para aprovacao do super admin.
*/

include_once __DIR__ . '/../../config/security.php';
security_start_session();
security_set_headers();
security_regenerate_session(15);
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/plano_notificacoes.php';
$planos_config = require __DIR__ . '/../../config/planos.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
    exit;
}

if (!isset($_SESSION['restaurante_id']) || $_SESSION['perfil'] !== 'ADMIN') {
    echo json_encode(["success" => false, "message" => "Sem permissão"]);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$plano_novo = strtoupper(trim((string)($_POST['plano'] ?? '')));
$ciclo = strtoupper(trim((string)($_POST['ciclo'] ?? 'MENSAL')));
$metodo = strtoupper(trim((string)($_POST['metodo'] ?? '')));
$arquivo_comprovativo = $_FILES['comprovativo'] ?? null;

$planos_validos = ['BASICO', 'PROFISSIONAL', 'EMPRESARIAL'];
$ciclos_validos = ['MENSAL', 'TRIMESTRAL', 'ANUAL'];
$metodos_validos = ['DINHEIRO', 'MPESA', 'CARTAO', 'TRANSFERENCIA'];

if (!in_array($plano_novo, $planos_validos, true)) {
    echo json_encode(["success" => false, "message" => "Plano inválido"]);
    exit;
}

if (!in_array($ciclo, $ciclos_validos, true)) {
    echo json_encode(["success" => false, "message" => "Ciclo inválido"]);
    exit;
}

if (!in_array($metodo, $metodos_validos, true)) {
    echo json_encode(["success" => false, "message" => "Método de pagamento inválido"]);
    exit;
}

$arquivo_ok = isset($arquivo_comprovativo['error']) && $arquivo_comprovativo['error'] === UPLOAD_ERR_OK;
if (!$arquivo_ok) {
    echo json_encode(["success" => false, "message" => "Comprovativo obrigatório"]);
    exit;
}

$chave_ciclo = [
    'MENSAL' => 'mensal',
    'TRIMESTRAL' => 'trimestral',
    'ANUAL' => 'anual',
][$ciclo] ?? 'mensal';

$valor = (float)($planos_config[$plano_novo]['precos'][$chave_ciclo] ?? 0);
if ($valor <= 0) {
    echo json_encode(["success" => false, "message" => "Preço inválido para plano/ciclo selecionado"]);
    exit;
}

$extensoes_permitidas = ['jpg', 'jpeg', 'png', 'pdf'];
$mimes_permitidos = ['image/jpeg', 'image/png', 'application/pdf'];
$max_tamanho = 5 * 1024 * 1024;
$extensao = strtolower(pathinfo($arquivo_comprovativo['name'] ?? '', PATHINFO_EXTENSION));

if (!in_array($extensao, $extensoes_permitidas, true)) {
    echo json_encode(["success" => false, "message" => "Comprovativo inválido. Use JPG, PNG ou PDF"]);
    exit;
}

if (($arquivo_comprovativo['size'] ?? 0) > $max_tamanho) {
    echo json_encode(["success" => false, "message" => "Comprovativo excede 5MB"]);
    exit;
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

if ($mime !== '' && !in_array($mime, $mimes_permitidos, true)) {
    echo json_encode(["success" => false, "message" => "Tipo de arquivo inválido para comprovativo"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(["success" => false, "message" => "Erro de conexão"]);
    exit;
}

try {
    $stmtRest = $db->prepare("SELECT id, nome, email, telefone, plano FROM restaurantes WHERE id = ?");
    $stmtRest->execute([$_SESSION['restaurante_id']]);
    $restaurante = $stmtRest->fetch(PDO::FETCH_ASSOC);

    if (!$restaurante) {
        echo json_encode(["success" => false, "message" => "Restaurante não encontrado"]);
        exit;
    }

    $stmtPend = $db->prepare("SELECT COUNT(*) FROM compras_planos WHERE restaurante_id = ? AND status = 'PENDENTE'");
    $stmtPend->execute([$_SESSION['restaurante_id']]);
    if ((int)$stmtPend->fetchColumn() > 0) {
        echo json_encode(["success" => false, "message" => "Já existe uma solicitação pendente. Aguarde a análise do super admin."]);
        exit;
    }

    $dir_upload_absoluto = __DIR__ . '/../uploads/comprovativos';
    if (!is_dir($dir_upload_absoluto) && !mkdir($dir_upload_absoluto, 0755, true)) {
        echo json_encode(["success" => false, "message" => "Não foi possível preparar diretório de comprovativos"]);
        exit;
    }

    $nome_comprovativo = sprintf(
        'comp_%s_%s.%s',
        date('YmdHis'),
        bin2hex(random_bytes(6)),
        $extensao
    );

    $caminho_absoluto = $dir_upload_absoluto . '/' . $nome_comprovativo;
    $caminho_relativo = 'uploads/comprovativos/' . $nome_comprovativo;

    $uploadError = null;
    if (!security_validate_upload(
        $arquivo_comprovativo,
        ['png', 'jpg', 'jpeg', 'pdf'],
        ['image/png', 'image/jpeg', 'application/pdf'],
        5 * 1024 * 1024,
        $uploadError
    )) {
        echo json_encode(["success" => false, "message" => $uploadError ?: "Arquivo inválido."]);
        exit;
    }

    if (!move_uploaded_file($arquivo_comprovativo['tmp_name'], $caminho_absoluto)) {
        echo json_encode(["success" => false, "message" => "Falha ao salvar comprovativo"]);
        exit;
    }

    $colunasCompra = [];
    $stmtCols = $db->query('SHOW COLUMNS FROM compras_planos');
    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
        $colunasCompra[] = $col['Field'];
    }

    $insertCols = ['restaurante_id', 'plano_atual', 'plano_novo', 'valor', 'metodo_pagamento', 'status'];
    $insertVals = ['?', '?', '?', '?', '?', "'PENDENTE'"];
    $params = [$_SESSION['restaurante_id'], $restaurante['plano'] ?? 'BASICO', $plano_novo, $valor, $metodo];

    if (in_array('ciclo', $colunasCompra, true)) {
        $insertCols[] = 'ciclo';
        $insertVals[] = '?';
        $params[] = $ciclo;
    }

    if (in_array('comprovativo_path', $colunasCompra, true)) {
        $insertCols[] = 'comprovativo_path';
        $insertVals[] = '?';
        $params[] = $caminho_relativo;
    }

    if (in_array('observacao', $colunasCompra, true)) {
        $insertCols[] = 'observacao';
        $insertVals[] = '?';
        $params[] = 'Renovação manual guiada';
    }

    if (in_array('created_at', $colunasCompra, true)) {
        $insertCols[] = 'created_at';
        $insertVals[] = 'NOW()';
    } elseif (in_array('criado_em', $colunasCompra, true)) {
        $insertCols[] = 'criado_em';
        $insertVals[] = 'NOW()';
    }

    $stmtIns = $db->prepare('INSERT INTO compras_planos (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')');
    $stmtIns->execute($params);

    echo json_encode([
        "success" => true,
        "message" => "Pedido de renovação enviado com sucesso! Aguarde aprovação do super admin.",
        "data" => [
            "plano" => $plano_novo,
            "ciclo" => $ciclo,
            "status" => 'PENDENTE'
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Erro ao solicitar renovação: " . $e->getMessage()]);
}
