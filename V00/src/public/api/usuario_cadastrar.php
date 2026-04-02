<?php

/*
   API - Cadastrar Usuário
 */

include_once '../../config/security.php';
security_start_session();
security_set_headers();
security_regenerate_session(15);
include_once '../../config/database.php';
include_once '../../config/plano_check.php';
include_once '../../config/restaurante_context.php';
include_once '../../Model/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restauranteId = session_restaurante_contexto_id();
    $restaurantePlanId = session_restaurante_capability_id();

    if ($restauranteId <= 0 || $_SESSION['perfil'] !== 'ADMIN') {
        echo json_encode(array("success" => false, "message" => "Sem permissão"));
        exit;
    }

    if (empty($_POST['nome']) || empty($_POST['email']) || empty($_POST['senha']) || empty($_POST['perfil'])) {
        echo json_encode(array("success" => false, "message" => "Preencha todos os campos obrigatórios"));
        exit;
    }

    if (strlen($_POST['senha']) < 6) {
        echo json_encode(array("success" => false, "message" => "A senha deve ter pelo menos 6 caracteres"));
        exit;
    }

    $perfil = strtoupper(trim((string)$_POST['perfil']));
    if ($perfil === 'GARÇOM') {
        $perfil = 'GARCOM';
    }
    if ($perfil === 'COZINHEIRO' || $perfil === 'CHEF') {
        $perfil = 'COZINHA';
    }
    if ($perfil === 'BARMAN' || $perfil === 'BARTENDER') {
        $perfil = 'BAR';
    }

    $perfis_validos = ['ADMIN', 'CAIXA', 'GARCOM', 'COZINHA', 'BAR'];
    if (!in_array($perfil, $perfis_validos, true)) {
        echo json_encode(array("success" => false, "message" => "Perfil inválido"));
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // Verificar se email já existe
    $query_check = "SELECT id FROM usuarios WHERE email = :email LIMIT 1";
    $stmt_check = $db->prepare($query_check);
    $stmt_check->bindParam(':email', $_POST['email']);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        echo json_encode(array("success" => false, "message" => "Este email já está em uso"));
        exit;
    }

    $temMultiFilial = $restaurantePlanId > 0 && plano_tem_funcionalidade_db($restaurantePlanId, 'multi_filial');
    if ($temMultiFilial) {
        $stmt_limit = $db->prepare("
            SELECT COUNT(*)
            FROM usuarios u
            INNER JOIN restaurantes r ON r.id = u.restaurante_id
            WHERE u.ativo = 1
              AND (u.restaurante_id = :base_restaurante_id OR r.filial_id = :filial_base_id)
        ");
        $stmt_limit->bindValue(':base_restaurante_id', $restaurantePlanId, PDO::PARAM_INT);
        $stmt_limit->bindValue(':filial_base_id', $restaurantePlanId, PDO::PARAM_INT);
    } else {
        $stmt_limit = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE restaurante_id = :rid AND ativo = 1");
        $stmt_limit->bindValue(':rid', $restauranteId, PDO::PARAM_INT);
    }
    $stmt_limit->execute();
    $totalUsuariosAtivos = (int)$stmt_limit->fetchColumn();

    $verificacaoPlano = plano_verificar_limite_db($restaurantePlanId > 0 ? $restaurantePlanId : $restauranteId, 'usuarios', $totalUsuariosAtivos);
    if (!$verificacaoPlano['permitido']) {
        echo json_encode(array(
            "success" => false,
            "message" => "Limite do plano atingido. O plano {$verificacaoPlano['plano']} permite até {$verificacaoPlano['limite']} usuários."
        ));
        exit;
    }

    // Processar upload de foto
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        // novo diretório público de imagens (relative à pasta api)
        $upload_dir = '../images/';

        // Criar diretório se não existir
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_ext = ['png', 'jpg', 'jpeg'];
        $allowed_mime = ['image/png', 'image/jpeg'];
        $upload_error = null;
        if (security_validate_upload($_FILES['foto'], $allowed_ext, $allowed_mime, 2 * 1024 * 1024, $upload_error)) {
            $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['foto']['name']));
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $safe_name)) {
                $foto = 'images/' . $safe_name;
            }
        }
    }

    // Hash da senha
    $senha_hash = password_hash($_POST['senha'], PASSWORD_BCRYPT);

    // Inserir usuário
    $query = "INSERT INTO usuarios (restaurante_id, nome, email, senha, perfil, ativo, foto) 
              VALUES (:rid, :nome, :email, :senha, :perfil, 1, :foto)";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':rid', $restauranteId, PDO::PARAM_INT);
    $stmt->bindParam(':nome', $_POST['nome']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':senha', $senha_hash);
    $stmt->bindValue(':perfil', $perfil);
    $stmt->bindParam(':foto', $foto);

    if ($stmt->execute()) {
        $novoId = (int)$db->lastInsertId();

        // Evita sucesso falso quando o banco usa ENUM antigo e grava perfil vazio.
        $stmtPerfil = $db->prepare("SELECT perfil FROM usuarios WHERE id = :id LIMIT 1");
        $stmtPerfil->bindValue(':id', $novoId, PDO::PARAM_INT);
        $stmtPerfil->execute();
        $perfilSalvo = strtoupper(trim((string)($stmtPerfil->fetchColumn() ?: '')));

        if ($perfilSalvo !== $perfil) {
            echo json_encode(array(
                "success" => false,
                "message" => "Perfil não foi salvo corretamente no banco. Execute o script src/config/sql_perfis_cozinha_bar.sql (ALTER TABLE usuarios ...)."
            ));
            exit;
        }

        echo json_encode(array("success" => true, "message" => "Usuário cadastrado com sucesso!"));
    } else {
        echo json_encode(array("success" => false, "message" => "Erro ao cadastrar usuário"));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
