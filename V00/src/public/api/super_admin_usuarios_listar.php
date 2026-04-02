<?php

/*
   API - Super Admin Listar Usuários
   Lista todos os usuários de um restaurante específico
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/super_admin_permissions.php';
include_once '../../config/turno_schema.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Verificar se é super admin (fundador SaaS)
$isSuperAdmin = isset($_SESSION['logado'], $_SESSION['super_admin'])
    && $_SESSION['logado'] === true
    && intval($_SESSION['super_admin']) === 1;

if (!$isSuperAdmin) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Acesso negado"]);
    exit;
}

super_admin_require_permission_json('manage_users');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $restaurante_id = intval($_GET['restaurante_id'] ?? 0);

    if ($restaurante_id <= 0) {
        echo json_encode(["success" => false, "message" => "ID do restaurante inválido"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    turno_schema_garantir($db);

    // Buscar usuários do restaurante
    $stmt = $db->prepare("
        SELECT id, nome, email, perfil, ativo, foto
        FROM usuarios 
        WHERE restaurante_id = ? 
        ORDER BY nome
    ");
    $stmt->execute([$restaurante_id]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapear turnos ativos por usuário
    $turnosAtivos = [];
    $stmtTurnos = $db->prepare("
        SELECT id, usuario_id, turno, hora_entrada
        FROM funcionarios_turnos
        WHERE restaurante_id = ?
          AND status = 'ATIVO'
        ORDER BY id DESC
    ");
    $stmtTurnos->execute([$restaurante_id]);
    while ($t = $stmtTurnos->fetch(PDO::FETCH_ASSOC)) {
        $uid = (int)$t['usuario_id'];
        if (!isset($turnosAtivos[$uid])) {
            $turnosAtivos[$uid] = $t;
        }
    }

    foreach ($usuarios as &$u) {
        $uid = (int)($u['id'] ?? 0);
        $turno = $turnosAtivos[$uid] ?? null;
        $u['turno_ativo'] = $turno ? 1 : 0;
        $u['turno_id'] = $turno ? (int)$turno['id'] : null;
        $u['turno_tipo'] = $turno['turno'] ?? null;
        $u['turno_hora_entrada'] = $turno['hora_entrada'] ?? null;
    }
    unset($u);

    echo json_encode([
        "success" => true,
        "data" => $usuarios
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
