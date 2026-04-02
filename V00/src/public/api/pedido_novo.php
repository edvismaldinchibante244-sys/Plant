<?php

/*
  API - Criar Novo Pedido
  Suporta dois fluxos:
  Staff (autenticado) : FormData POST, usa $_SESSION['restaurante_id']
   QR Code (público)   : JSON POST com campo "rid" no body
   Em ambos os casos, ao criar o pedido a mesa é automaticamente
   marcada como OCUPADA (Abrir mesa).
 */

session_start();
include_once '../../config/database.php';
include_once '../../config/pedido_schema.php';
include_once '../../config/turno_helpers.php';
include_once '../../Service/TurnoService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// ─── 1. Detectar formato de entrada ──────────────────────────────────────────
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$is_json      = strpos($content_type, 'application/json') !== false;

if ($is_json) {
    $body = json_decode(file_get_contents('php://input'), true);
    $body = is_array($body) ? $body : [];
} else {
    $body = $_POST;
}

// ─── 2. Resolver restaurante_id ──────────────────────────────────────────────
if (isset($_SESSION['restaurante_id']) && (int)$_SESSION['restaurante_id'] > 0) {
    // Fluxo autenticado: garçom / staff
    $restaurante_id = (int)$_SESSION['restaurante_id'];
    $is_public      = false;

    $perfil_raw = strtoupper(trim((string)($_SESSION['perfil'] ?? '')));
    $perfil = turno_normalizar_perfil($perfil_raw === 'OPERADOR' ? 'GARCOM' : $perfil_raw);
    if (!in_array($perfil, ['ADMIN', 'GARCOM'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissao para criar pedido']);
        exit;
    }

    if (turno_usuario_exige_turno_ativo($perfil)) {
        $turnoService = new TurnoService(new Database());
        $turnoAtivo = $turnoService->obterTurnoAtivoUsuario((int)($_SESSION['usuario_id'] ?? 0), $restaurante_id);
        if (!$turnoAtivo) {
            echo json_encode(['success' => false, 'message' => 'É necessário ter turno ativo para criar pedidos']);
            exit;
        }
    }
} elseif (!empty($body['rid'])) {
    // Fluxo público: cardápio via QR Code
    $restaurante_id = (int)$body['rid'];
    $is_public      = true;
} else {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// ─── 3. Extrair e validar campos ──────────────────────────────────────────────
$mesa_id    = (int)($body['mesa_id'] ?? ($body['mesa'] ?? 0));
$observacao = trim((string)($body['observacao'] ?? ''));

// Itens: array direto (JSON body) ou string JSON (FormData)
if ($is_json) {
    $itens = is_array($body['itens'] ?? null) ? $body['itens'] : [];
} else {
    $itens = json_decode((string)($body['itens'] ?? '[]'), true);
    $itens = is_array($itens) ? $itens : [];
}

if ($mesa_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mesa inválida']);
    exit;
}
if (empty($itens)) {
    echo json_encode(['success' => false, 'message' => 'Itens inválidos']);
    exit;
}

$database = new Database();
$db       = $database->getConnection();
pedido_schema_garantir($db);

$hasProdutoDestino = false;
$hasItemDestino = false;
$hasItemStatus = false;

try {
    $hasProdutoDestino = (bool)$db->query("SHOW COLUMNS FROM produtos LIKE 'destino'")->fetch(PDO::FETCH_ASSOC);
    $hasItemDestino = (bool)$db->query("SHOW COLUMNS FROM itens_pedido LIKE 'destino'")->fetch(PDO::FETCH_ASSOC);
    $hasItemStatus = (bool)$db->query("SHOW COLUMNS FROM itens_pedido LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Mantem compatibilidade em bancos antigos sem bloquear criacao de pedido.
}

// ─── 4. Validar restaurante (fluxo público) ───────────────────────────────────
if ($is_public) {
    $stmt_rest = $db->prepare("SELECT id FROM restaurantes WHERE id = :id LIMIT 1");
    $stmt_rest->execute(['id' => $restaurante_id]);
    if (!$stmt_rest->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Restaurante não encontrado']);
        exit;
    }
}

// ─── 5. Validar que a mesa pertence ao restaurante ────────────────────────────
$stmt_mesa_check = $db->prepare(
    "SELECT id, numero, garcom_id FROM mesas WHERE id = :id AND restaurante_id = :rid LIMIT 1"
);
$stmt_mesa_check->execute(['id' => $mesa_id, 'rid' => $restaurante_id]);
$mesa_dados = $stmt_mesa_check->fetch(PDO::FETCH_ASSOC);
if (!$mesa_dados) {
    echo json_encode(['success' => false, 'message' => 'Mesa não encontrada']);
    exit;
}

$garcom_responsavel_id = $is_public
    ? intval($mesa_dados['garcom_id'] ?? 0)
    : intval($_SESSION['usuario_id'] ?? 0);

$origemPedido = $is_public
    ? 'QR'
    : (turno_normalizar_perfil($_SESSION['perfil'] ?? '') === 'GARCOM' ? 'GARCOM' : 'BALCAO');
$origemPedido = pedido_normalizar_origem($origemPedido);

$db->beginTransaction();

try {
    // ─── 6. Gerar número do pedido ────────────────────────────────────────────
    $stmt_num = $db->prepare(
        "SELECT LPAD(COALESCE(MAX(CAST(numero_pedido AS UNSIGNED)), 0) + 1, 4, '0') AS novo_numero
         FROM pedidos WHERE restaurante_id = :rid AND DATE(criado_em) = CURDATE()"
    );
    $stmt_num->execute(['rid' => $restaurante_id]);
    $numero_pedido = $stmt_num->fetch(PDO::FETCH_ASSOC)['novo_numero'];

    // ─── 7. Carregar produtos do banco e calcular total real ─────────────────
    $produtoIds = [];
    foreach ($itens as $item) {
        $pid = (int)(($item['id'] ?? $item['produto_id'] ?? 0));
        if ($pid > 0) {
            $produtoIds[] = $pid;
        }
    }
    $produtoIds = array_values(array_unique($produtoIds));
    if (empty($produtoIds)) {
        throw new Exception('Nenhum produto valido no pedido');
    }

    $in = implode(',', array_fill(0, count($produtoIds), '?'));
    $sqlProdutos = "SELECT p.id, p.preco, p.nome, c.nome AS categoria";
    if ($hasProdutoDestino) {
        $sqlProdutos .= ", p.destino";
    }
    $sqlProdutos .= " FROM produtos p
                     LEFT JOIN categorias c ON c.id = p.categoria_id
                     WHERE p.restaurante_id = ? AND p.ativo = 1 AND p.id IN ($in)";

    $stmtProdutos = $db->prepare($sqlProdutos);
    $paramsProdutos = array_merge([$restaurante_id], $produtoIds);
    $stmtProdutos->execute($paramsProdutos);
    $produtosDb = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

    $produtosMap = [];
    foreach ($produtosDb as $p) {
        $produtosMap[(int)$p['id']] = $p;
    }

    $normalizarTexto = function ($texto) {
        $txt = strtolower(trim((string)$texto));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        if ($ascii !== false) {
            $txt = strtolower($ascii);
        }
        return $txt;
    };

    $inferDestino = function ($categoria, $nome) use ($normalizarTexto) {
        $base = $normalizarTexto(($categoria ?? '') . ' ' . ($nome ?? ''));
        $bebidaKeywords = [
            'bebida',
            'bar',
            'drink',
            'coquetel',
            'cocktail',
            'sumo',
            'suco',
            'refrigerante',
            'agua',
            'agua mineral',
            'cerveja',
            'vinho',
            'whisky',
            'rum',
            'vodka',
            'gin',
            'licor',
            'cha',
            'te',
            'cafe',
            'espresso',
            'capuccino',
            'cappuccino',
            'latte',
            'milkshake',
            'batido',
            'smoothie',
            'energetico',
            'red bull',
            'guarana',
            'fanta',
            'sprite',
            'pepsi',
            'coca',
            'coca-cola',
            'schweppes',
            'tonica'
        ];

        foreach ($bebidaKeywords as $kw) {
            if (strpos($base, $kw) !== false) {
                return 'bar';
            }
        }

        // Regra padrão do negócio: se não for bebida, vai para cozinha.
        return 'cozinha';
    };


    // Padronização dos campos dos itens para ambos os fluxos
    // Comentário: Aceita tanto 'id' quanto 'produto_id', e 'qtd', 'qty' ou 'quantidade' para máxima compatibilidade
    $total = 0.0;
    $itensNormalizados = [];
    foreach ($itens as $item) {
        $produto_id = (int)(($item['id'] ?? $item['produto_id'] ?? 0));
        $qtd = (int)(($item['qtd'] ?? $item['qty'] ?? $item['quantidade'] ?? 0));
        if ($produto_id <= 0 || $qtd <= 0 || !isset($produtosMap[$produto_id])) {
            continue;
        }

        $p = $produtosMap[$produto_id];
        $preco = (float)($p['preco'] ?? 0);
        $subtotal = $preco * $qtd;
        $destino = $inferDestino($p['categoria'] ?? null, $p['nome'] ?? null);

        $itensNormalizados[] = [
            'produto_id' => $produto_id,
            'qtd' => $qtd,
            'preco' => $preco,
            'subtotal' => $subtotal,
            'destino' => $destino,
        ];
        $total += $subtotal;
    }

    if (empty($itensNormalizados)) {
        throw new Exception('Nenhum item válido para criar o pedido');
    }

    $destinosPedido = array_values(array_unique(array_map(static function ($item) {
        return strtolower((string)($item['destino'] ?? 'cozinha'));
    }, $itensNormalizados)));
    sort($destinosPedido);

    $destinoResumo = 'cozinha';
    if (in_array('bar', $destinosPedido, true) && in_array('cozinha', $destinosPedido, true)) {
        $destinoResumo = 'cozinha_bar';
    } elseif (in_array('bar', $destinosPedido, true)) {
        $destinoResumo = 'bar';
    }

    // ─── 7. Criar pedido ──────────────────────────────────────────────────────
    // Comentário: Aqui o pedido é criado e a mesa é marcada como OCUPADA. O número do pedido é sequencial por dia.
    $stmt_pedido = $db->prepare(
        "INSERT INTO pedidos (numero_pedido, mesa_id, garcom_id, status, total, observacao, origem, criado_em, atualizado_em, restaurante_id)
         VALUES (:numero, :mesa_id, :garcom_id, 'PREPARANDO', :total, :observacao, :origem, NOW(), NOW(), :rid)"
    );
    $stmt_pedido->execute([
        'numero'     => $numero_pedido,
        'mesa_id'    => $mesa_id,
        'garcom_id'  => $garcom_responsavel_id > 0 ? $garcom_responsavel_id : null,
        'total'      => $total,
        'observacao' => $observacao ?: null,
        'origem'     => $origemPedido,
        'rid'        => $restaurante_id,
    ]);
    $pedido_id = (int)$db->lastInsertId();

    // ─── 8. Criar itens do pedido ─────────────────────────────────────────────
    // Comentário: Cada item é inserido na tabela itens_pedido, com destino (cozinha/bar) se disponível.
    if ($hasItemDestino && $hasItemStatus) {
        $stmt_item = $db->prepare(
            "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, destino, status, preco_unitario, subtotal)
             VALUES (:pedido_id, :produto_id, :qtd, :destino, 'em_preparo', :preco, :subtotal)"
        );
    } else {
        $stmt_item = $db->prepare(
            "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
             VALUES (:pedido_id, :produto_id, :qtd, :preco, :subtotal)"
        );
    }

    foreach ($itensNormalizados as $item) {
        $paramsItem = [
            'pedido_id'  => $pedido_id,
            'produto_id' => $item['produto_id'],
            'qtd'        => $item['qtd'],
            'preco'      => $item['preco'],
            'subtotal'   => $item['subtotal'],
        ];
        if ($hasItemDestino && $hasItemStatus) {
            $paramsItem['destino'] = $item['destino'];
        }
        $stmt_item->execute($paramsItem);
    }

    // ─── 9. Abrir mesa → marcar como OCUPADA ────────────────────────────────
    // Comentário: Garante que a mesa está ocupada após o pedido, e associa o garçom se necessário.
    $stmt_mesa_upd = $db->prepare(
        "UPDATE mesas SET status = 'OCUPADA', garcom_id = COALESCE(garcom_id, :garcom_id) WHERE id = :id AND restaurante_id = :rid"
    );
    $stmt_mesa_upd->execute([
        'id' => $mesa_id,
        'rid' => $restaurante_id,
        'garcom_id' => $garcom_responsavel_id > 0 ? $garcom_responsavel_id : null,
    ]);

    $db->commit();

    // Notificação WebSocket para atualização em tempo real (painéis, etc)
    $pedido_data = [
        'id' => $pedido_id,
        'numero_pedido' => $numero_pedido,
        'mesa_id' => $mesa_id,
        'mesa_numero' => $mesa_dados['numero'] ?? '?',
        'garcom_id' => $garcom_responsavel_id > 0 ? $garcom_responsavel_id : null,
        'status' => 'PREPARANDO',
        'total' => $total,
        'criado_em' => date('c'),
        'restaurante_id' => $restaurante_id,
        'origem' => $origemPedido,
    ];
    include __DIR__ . '/../notify_websocket.php';

    echo json_encode([
        'success'       => true,
        'message'       => 'Pedido criado com sucesso!',
        'pedido_id'     => $pedido_id,
        'numero_pedido' => $numero_pedido,
        'total'         => number_format($total, 2, ',', '.'),
        'status'        => 'PREPARANDO',
        'origem'        => $origemPedido,
        'destinos'      => $destinosPedido,
        'destino_resumo'=> $destinoResumo,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
