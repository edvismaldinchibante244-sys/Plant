<?php

/*
   API - Editar Usuário
 */

include_once '../../config/security.php';
security_start_session();
security_set_headers();
security_regenerate_session(15);
include_once '../../config/database.php';
include_once '../../config/restaurante_context.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restauranteId = session_restaurante_contexto_id();

    if ($restauranteId <= 0 || $_SESSION['perfil'] !== 'ADMIN') {
        echo json_encode(array("success" => false, "message" => "Sem permissão"));
        exit;
    }

    if (empty($_POST['usuario_id']) || empty($_POST['nome']) || empty($_POST['email']) || empty($_POST['perfil'])) {
        echo json_encode(array("success" => false, "message" => "Preencha todos os campos obrigatórios"));
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

    // Verificar se a conexão foi estabelecida
    if (!$db) {
        echo json_encode(array("success" => false, "message" => "Erro de conexão com o banco de dados"));
        exit;
    }

    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $id    = intval($_POST['usuario_id']);

    // Verificar se email já existe em outro usuário
    $query_check = "SELECT id FROM usuarios WHERE email = :email AND id != :id LIMIT 1";
    $stmt_check  = $db->prepare($query_check);
    $stmt_check->bindParam(':email', $_POST['email']);
    $stmt_check->bindParam(':id', $id);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        echo json_encode(array("success" => false, "message" => "Este email já está em uso por outro usuário"));
        exit;
    }

    // Processar upload de foto - PNG e JPEG
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
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

    // Atualizar dados básicos
    if (!empty($foto)) {
        // Com foto nova
        $query = "UPDATE usuarios SET nome = :nome, email = :email, perfil = :perfil, ativo = :ativo, foto = :foto
                  WHERE id = :id AND restaurante_id = :rid";
        $stmt  = $db->prepare($query);
        $stmt->bindParam(':foto', $foto);
    } else {
        // Mantém foto atual
        $query = "UPDATE usuarios SET nome = :nome, email = :email, perfil = :perfil, ativo = :ativo
                  WHERE id = :id AND restaurante_id = :rid";
        $stmt  = $db->prepare($query);
    }

    $stmt->bindParam(':nome',   $_POST['nome']);
    $stmt->bindParam(':email',  $_POST['email']);
    $stmt->bindValue(':perfil', $perfil);
    $stmt->bindParam(':ativo',  $ativo, PDO::PARAM_INT);
    $stmt->bindParam(':id',     $id, PDO::PARAM_INT);
    $stmt->bindValue(':rid',    $restauranteId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Evita sucesso falso quando o banco usa ENUM antigo e grava perfil vazio.
        $stmtPerfil = $db->prepare("SELECT perfil FROM usuarios WHERE id = :id AND restaurante_id = :rid LIMIT 1");
        $stmtPerfil->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtPerfil->bindValue(':rid', $restauranteId, PDO::PARAM_INT);
        $stmtPerfil->execute();
        $perfilSalvo = strtoupper(trim((string)($stmtPerfil->fetchColumn() ?: '')));

        if ($perfilSalvo !== $perfil) {
            echo json_encode(array(
                "success" => false,
                "message" => "Perfil não foi salvo corretamente no banco. Execute o script src/config/sql_perfis_cozinha_bar.sql (ALTER TABLE usuarios ...)."
            ));
            exit;
        }

        // Atualizar senha se fornecida
        if (!empty($_POST['senha'])) {
            if (strlen($_POST['senha']) < 6) {
                echo json_encode(array("success" => false, "message" => "A senha deve ter pelo menos 6 caracteres"));
                exit;
            }
            $senha_hash  = password_hash($_POST['senha'], PASSWORD_BCRYPT);
            $query_senha = "UPDATE usuarios SET senha = :senha WHERE id = :id";
            $stmt_senha  = $db->prepare($query_senha);
            $stmt_senha->bindParam(':senha', $senha_hash);
            $stmt_senha->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_senha->execute();
        }
        echo json_encode(array("success" => true, "message" => "Usuário atualizado com sucesso!"));
    } else {
        echo json_encode(array("success" => false, "message" => "Erro ao atualizar usuário"));
    }
} else {
    echo json_encode(array("success" => false, "message" => "Método não permitido"));
}
