<?php

/*
 
   API - DELETAR RESTAURANTE
   Remove um restaurante do sistema
 
 */

include_once '../../config/database.php';
include_once '../../config/super_admin_check.php';
include_once '../../config/csrf.php';

requireSuperAdminPermission('manage_restaurants');

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

// Validar ID
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do restaurante inválido'
    ]);
    exit;
}

// Verificar se o restaurante existe
$check = $db->prepare("SELECT nome FROM restaurantes WHERE id = ?");
$check->execute([$id]);
if ($check->rowCount() == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Restaurante não encontrado'
    ]);
    exit;
}

try {
    $db->beginTransaction();

    // Alguns itens ainda têm RESTRICT sobre produtos e precisam sair antes do cascade do restaurante.
    $db->prepare("
        DELETE iv
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE v.restaurante_id = ?
    ")->execute([$id]);

    $db->prepare("
        DELETE ip
        FROM itens_pedido ip
        INNER JOIN pedidos p ON p.id = ip.pedido_id
        WHERE p.restaurante_id = ?
    ")->execute([$id]);

    // Tabela legada que ainda pode existir em bases antigas.
    $db->prepare("
        DELETE pi
        FROM pedido_itens pi
        INNER JOIN pedidos p ON p.id = pi.pedido_id
        WHERE p.restaurante_id = ?
    ")->execute([$id]);

    // Turnos de funcionários também travam a remoção do plano/restaurante em bases antigas.
    $db->prepare("
        DELETE ft
        FROM funcionarios_turnos ft
        INNER JOIN usuarios u ON u.id = ft.usuario_id
        WHERE u.restaurante_id = ?
    ")->execute([$id]);

    $stmt = $db->prepare("DELETE FROM restaurantes WHERE id = ?");
    $stmt->execute([$id]);

    // Usuários não possuem cascade para restaurantes, então precisam ser removidos explicitamente.
    $db->prepare("DELETE FROM usuarios WHERE restaurante_id = ?")->execute([$id]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Restaurante deletado com sucesso! Dados associados, turnos e usuarios vinculados também foram removidos.'
    ]);
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar restaurante: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erro inesperado ao deletar restaurante: ' . $e->getMessage()
    ]);
}
