<?php

/*
   API - Super Admin Definir Senha do Restaurante
   Usado após aprovar um plano para definir a senha inicial do restaurante
 */

session_start();
include_once '../../config/database.php';

header('Content-Type: application/json');

// Verificar se é super admin (fundador SaaS)
$isSuperAdmin = isset($_SESSION['logado'], $_SESSION['super_admin'])
    && $_SESSION['logado'] === true
    && intval($_SESSION['super_admin']) === 1;

if (!$isSuperAdmin) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Acesso negado"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $restaurante_id = intval($_POST['restaurante_id'] ?? 0);
    $senha = trim($_POST['senha'] ?? '');

    if ($restaurante_id <= 0) {
        echo json_encode(["success" => false, "message" => "ID do restaurante inválido"]);
        exit;
    }

    if (strlen($senha) < 6) {
        echo json_encode(["success" => false, "message" => "A senha deve ter pelo menos 6 caracteres"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    try {
        // Buscar restaurante (independente do status)
        $stmt = $db->prepare("SELECT nome, senha_admin, senha_criada_em, status FROM restaurantes WHERE id = ?");
        $stmt->execute([$restaurante_id]);
        $restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$restaurante) {
            echo json_encode(["success" => false, "message" => "Restaurante não encontrado"]);
            exit;
        }

        // Se estiver bloqueado, ativar automaticamente
        if ($restaurante['status'] !== 'ATIVO') {
            // Ativar restaurante e prolongar data_fim do plano para +1 hora
            $novaDataFim = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');
            $stmt = $db->prepare("UPDATE restaurantes SET status = 'ATIVO', data_fim = :data_fim WHERE id = :id");
            $stmt->bindValue(':data_fim', $novaDataFim);
            $stmt->bindValue(':id', $restaurante_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Verificar se já existe uma senha recente (menos de 20 minutos)
        if ($restaurante['senha_admin'] && $restaurante['senha_criada_em']) {
            $data_criacao = new DateTime($restaurante['senha_criada_em']);
            $agora = new DateTime();
            $diferenca = $agora->diff($data_criacao);

            $minutos_passados = ($diferenca->days * 24 * 60) + ($diferenca->h * 60) + $diferenca->i;

            if ($minutos_passados < 20) {
                echo json_encode([
                    "success" => false,
                    "message" => "A senha foi criada recentemente. Aguarde " . (20 - $minutos_passados) . " minutos para alterar novamente."
                ]);
                exit;
            }
        }

        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

        // Atualizar senha e timestamps
        $stmt = $db->prepare("UPDATE restaurantes SET senha_admin = ?, senha_criada_em = NOW(), senha_pode_alterar = 1 WHERE id = ?");
        $stmt->execute([$senha_hash, $restaurante_id]);

        echo json_encode([
            "success" => true,
            "message" => "Senha definida com sucesso para " . $restaurante['nome'] . "! O restaurante foi ativado e pode fazer login.",
            "data" => [
                "restaurante" => $restaurante['nome'],
                "proxima_alteracao" => date('H:i', strtotime('+20 minutes'))
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Erro ao definir senha: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
