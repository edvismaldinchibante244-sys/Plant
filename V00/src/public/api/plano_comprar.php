<?php

/*
   API - Comprar Plano
   Registra e aplica upgrade de plano imediatamente
*/

session_start();
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../config/plano_check.php';
include_once __DIR__ . '/../../config/plano_notificacoes.php';
$planos_config = require __DIR__ . '/../../config/planos.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['restaurante_id']) || $_SESSION['perfil'] !== 'ADMIN') {
        echo json_encode(["success" => false, "message" => "Sem permissão"]);
        exit;
    }

    $plano_novo = $_POST['plano'] ?? '';
    $ciclo = strtoupper(trim($_POST['ciclo'] ?? 'MENSAL'));
    $metodo = $_POST['metodo'] ?? '';
    $valor = 0;

    // Verificar plano válido
    $planos_validos = ['BASICO', 'PROFISSIONAL', 'EMPRESARIAL'];
    if (!in_array($plano_novo, $planos_validos)) {
        echo json_encode(["success" => false, "message" => "Plano inválido"]);
        exit;
    }

    // Verificar método de pagamento válido
    $metodos_validos = ['DINHEIRO', 'MPESA', 'CARTAO', 'TRANSFERENCIA'];
    if (!in_array($metodo, $metodos_validos)) {
        echo json_encode(["success" => false, "message" => "Método de pagamento inválido"]);
        exit;
    }

    $ciclos_validos = ['MENSAL', 'TRIMESTRAL', 'ANUAL'];
    if (!in_array($ciclo, $ciclos_validos, true)) {
        echo json_encode(["success" => false, "message" => "Ciclo inválido"]);
        exit;
    }

    $chave_ciclo = [
        'MENSAL' => 'mensal',
        'TRIMESTRAL' => 'trimestral',
        'ANUAL' => 'anual',
    ][$ciclo] ?? 'mensal';

    $valor = (float)($planos_config[$plano_novo]['precos'][$chave_ciclo] ?? 0);
    if ($valor <= 0) {
        echo json_encode(["success" => false, "message" => "Preço inválido para o plano/ciclo selecionado"]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(["success" => false, "message" => "Erro de conexão"]);
        exit;
    }

    // Buscar dados atuais do restaurante
    $stmt = $db->prepare("SELECT plano, nome, email, telefone FROM restaurantes WHERE id = ?");
    $stmt->execute([$_SESSION['restaurante_id']]);
    $restaurante = $stmt->fetch(PDO::FETCH_ASSOC);
    $plano_atual = $restaurante['plano'] ?? 'BASICO';

    // Hierarquia dos planos: BASICO(1) < PROFISSIONAL(2) < EMPRESARIAL(3)
    $hierarquia = ['BASICO' => 1, 'PROFISSIONAL' => 2, 'EMPRESARIAL' => 3];
    $nivel_atual = $hierarquia[$plano_atual] ?? 1;
    $nivel_novo = $hierarquia[$plano_novo] ?? 1;

    // Impedir downgrade (tentar mudar para plano inferior)
    if ($nivel_novo < $nivel_atual) {
        echo json_encode(["success" => false, "message" => "Não é possível fazer downgrade de plano"]);
        exit;
    }

    // Impedir comprar o mesmo plano
    if ($nivel_novo === $nivel_atual) {
        echo json_encode(["success" => false, "message" => "Você já possui este plano"]);
        exit;
    }

    // ATUALIZAR PLANO IMEDIATAMENTE (sem necessidade de aprovação)
    try {
        $db->beginTransaction();

        // 1. Atualizar plano efetivo do restaurante
        $dias = ['MENSAL' => 30, 'TRIMESTRAL' => 90, 'ANUAL' => 365][$ciclo] ?? 30;

        $colunasRestaurante = [];
        $stmtColsRest = $db->query('SHOW COLUMNS FROM restaurantes');
        while ($col = $stmtColsRest->fetch(PDO::FETCH_ASSOC)) {
            $colunasRestaurante[] = $col['Field'];
        }

        $colunaFim = null;
        if (in_array('data_fim', $colunasRestaurante, true)) {
            $colunaFim = 'data_fim';
        } elseif (in_array('data_fim_plano', $colunasRestaurante, true)) {
            $colunaFim = 'data_fim_plano';
        }

        $baseDate = new DateTimeImmutable('today');

        $novaDataFim = $baseDate->modify('+' . $dias . ' days')->format('Y-m-d');
        plano_sincronizar_restaurante_plano($_SESSION['restaurante_id'], $plano_novo, $novaDataFim, 'PAGO', 'Compra imediata do plano');

        // 2. Criar registro de compra para histórico
        $colunasCompra = [];
        $stmtCols = $db->query('SHOW COLUMNS FROM compras_planos');
        while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
            $colunasCompra[] = $col['Field'];
        }

        $insertCols = ['restaurante_id', 'plano_atual', 'plano_novo', 'valor', 'metodo_pagamento', 'status'];
        $insertVals = ['?', '?', '?', '?', '?', 'APROVADO'];
        $params = [$_SESSION['restaurante_id'], $plano_atual, $plano_novo, $valor, $metodo];

        if (in_array('ciclo', $colunasCompra, true)) {
            $insertCols[] = 'ciclo';
            $insertVals[] = '?';
            $params[] = $ciclo;
        }

        if (in_array('data_pagamento', $colunasCompra, true)) {
            $insertCols[] = 'data_pagamento';
            $insertVals[] = 'NOW()';
        }

        if (in_array('created_at', $colunasCompra, true)) {
            $insertCols[] = 'created_at';
            $insertVals[] = 'NOW()';
        } elseif (in_array('criado_em', $colunasCompra, true)) {
            $insertCols[] = 'criado_em';
            $insertVals[] = 'NOW()';
        }

        $stmt = $db->prepare('INSERT INTO compras_planos (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')');
        $stmt->execute($params);

        $db->commit();

        // Notificacao nao bloqueante
        plano_notificar_aprovado(
            $restaurante['email'] ?? ($_SESSION['email'] ?? ''),
            $restaurante['telefone'] ?? '',
            $restaurante['nome'] ?? ($_SESSION['nome'] ?? 'Restaurante'),
            $plano_novo,
            $ciclo,
            $novaDataFim
        );

        // Atualizar sessão
        $_SESSION['plano'] = $plano_novo;

        echo json_encode([
            "success" => true,
            "message" => "Plano atualizado com sucesso!",
            "data" => [
                "plano" => $plano_novo,
                "plano_anterior" => $plano_atual,
                "valor" => $valor,
                "ciclo" => $ciclo,
                "status" => "APROVADO"
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Erro ao atualizar plano: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método não permitido"]);
}
