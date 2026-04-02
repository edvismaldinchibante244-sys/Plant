<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Model/Reserva.php';
require_once __DIR__ . '/../Service/ReservaService.php';
require_once __DIR__ . '/notificar_cliente_whatsapp.php';
require_once __DIR__ . '/utils_restaurante.php';

function consultar_disponibilidade_publica(array $dados, ?PDO $db = null): array
{
    $restauranteId = (int)($dados['restaurante_id'] ?? 0);
    $dataReserva = trim((string)($dados['data'] ?? $dados['data_reserva'] ?? ''));
    $horaReserva = trim((string)($dados['hora'] ?? $dados['hora_reserva'] ?? ''));
    $quantidade = (int)($dados['quantidade'] ?? $dados['quantidade_pessoas'] ?? 0);

    if ($restauranteId <= 0) {
        return [
            'success' => false,
            'message' => 'Selecione o restaurante para consultar a disponibilidade.',
            'http_code' => 422,
        ];
    }

    if (!reserva_publica_data_valida($dataReserva)) {
        return [
            'success' => false,
            'message' => 'Data da reserva invalida.',
            'http_code' => 422,
        ];
    }

    if (!reserva_publica_hora_valida($horaReserva)) {
        return [
            'success' => false,
            'message' => 'Hora da reserva invalida.',
            'http_code' => 422,
        ];
    }

    if ($quantidade <= 0) {
        return [
            'success' => false,
            'message' => 'Informe uma quantidade de pessoas valida.',
            'http_code' => 422,
        ];
    }

    $timezone = new \DateTimeZone(date_default_timezone_get() ?: 'Africa/Maputo');
    $dataHoraReserva = \DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        $dataReserva . ' ' . substr($horaReserva, 0, 5),
        $timezone
    );

    if (!$dataHoraReserva || $dataHoraReserva < new \DateTimeImmutable('now', $timezone)) {
        return [
            'success' => false,
            'message' => 'Escolha uma data e hora futuras para consultar a disponibilidade.',
            'http_code' => 422,
        ];
    }

    try {
        $db = $db ?? (new Database())->getConnection();

        $stmtRestaurante = $db->prepare("SELECT id FROM restaurantes WHERE id = :id LIMIT 1");
        $stmtRestaurante->execute([':id' => $restauranteId]);
        if (!$stmtRestaurante->fetch(PDO::FETCH_ASSOC)) {
            return [
                'success' => false,
                'message' => 'Restaurante nao encontrado.',
                'http_code' => 404,
            ];
        }

        $modelReserva = new \App\Model\Reserva($db);
        $serviceReserva = new \App\Service\ReservaService($db, $modelReserva);
        $mesasDisponiveis = $serviceReserva->validarDisponibilidade(
            $restauranteId,
            $dataReserva,
            $horaReserva,
            $quantidade
        );

        $total = count($mesasDisponiveis);
        $mensagem = $total > 0
            ? $total . ' mesa(s) disponivel(is) para este horario.'
            : 'Nenhuma mesa disponivel para este horario e quantidade.';

        return [
            'success' => true,
            'message' => $mensagem,
            'mesas_disponiveis' => $mesasDisponiveis,
            'total' => $total,
            'http_code' => 200,
        ];
    } catch (\Throwable $e) {
        error_log('[RESERVA_PUBLICA][DISPONIBILIDADE][ERROR] ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Erro interno ao consultar a disponibilidade.',
            'http_code' => 500,
        ];
    }
}

function consultar_cardapio_publico(array $dados, ?PDO $db = null): array
{
    $restauranteId = (int)($dados['restaurante_id'] ?? $dados['rid'] ?? 0);
    if ($restauranteId <= 0) {
        return [
            'success' => false,
            'message' => 'Selecione o restaurante para visualizar o cardapio.',
            'http_code' => 422,
        ];
    }

    try {
        $db = $db ?? (new Database())->getConnection();

        $stmtRestaurante = $db->prepare("
            SELECT id, nome, cidade
            FROM restaurantes
            WHERE id = :id
            LIMIT 1
        ");
        $stmtRestaurante->execute([':id' => $restauranteId]);
        $restaurante = $stmtRestaurante->fetch(PDO::FETCH_ASSOC);

        if (!$restaurante) {
            return [
                'success' => false,
                'message' => 'Restaurante nao encontrado.',
                'http_code' => 404,
            ];
        }

        $stmtProdutos = $db->prepare("
            SELECT
                p.id,
                p.nome,
                p.descricao,
                p.preco,
                p.imagem,
                c.nome AS categoria_nome
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.restaurante_id = :restaurante_id
              AND p.ativo = 1
            ORDER BY c.nome ASC, p.nome ASC
        ");
        $stmtProdutos->execute([':restaurante_id' => $restauranteId]);
        $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

        $preview = [];
        foreach ($produtos as $produto) {
            $categoriaNome = trim((string)($produto['categoria_nome'] ?? ''));
            if ($categoriaNome === '') {
                $categoriaNome = 'Outros';
            }

            if (!isset($preview[$categoriaNome])) {
                if (count($preview) >= 3) {
                    continue;
                }

                $preview[$categoriaNome] = [
                    'nome' => $categoriaNome,
                    'itens' => [],
                ];
            }

            if (count($preview[$categoriaNome]['itens']) >= 4) {
                continue;
            }

            $preview[$categoriaNome]['itens'][] = [
                'id' => (int)$produto['id'],
                'nome' => (string)$produto['nome'],
                'descricao' => (string)($produto['descricao'] ?? ''),
                'preco' => (float)$produto['preco'],
                'imagem' => reserva_publica_normalizar_imagem_produto($produto['imagem'] ?? null),
            ];
        }

        return [
            'success' => true,
            'restaurante' => [
                'id' => (int)$restaurante['id'],
                'nome' => (string)$restaurante['nome'],
                'cidade' => (string)($restaurante['cidade'] ?? ''),
            ],
            'cardapio_preview' => array_values($preview),
            'total_produtos' => count($produtos),
            'cardapio_url' => 'cardapio.php?rid=' . $restauranteId,
            'message' => empty($produtos)
                ? 'Este restaurante ainda nao publicou produtos no cardapio.'
                : 'Preview do cardapio carregado com sucesso.',
            'http_code' => 200,
        ];
    } catch (\Throwable $e) {
        error_log('[RESERVA_PUBLICA][CARDAPIO][ERROR] ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Erro interno ao carregar o cardapio.',
            'http_code' => 500,
        ];
    }
}

function reserva_publica_normalizar_imagem_produto($imagem): ?string
{
    $imagem = trim((string)$imagem);
    if ($imagem === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $imagem)) {
        return $imagem;
    }

    $imagem = str_replace('\\', '/', $imagem);
    $imagem = preg_replace('#^(\./)+#', '', $imagem);
    $imagem = preg_replace('#^/?src/public/#i', '', $imagem);
    $imagem = ltrim($imagem, '/');

    if ($imagem === '') {
        return null;
    }

    $arquivoPublico = dirname(__DIR__) . '/public/' . $imagem;
    if (!is_file($arquivoPublico)) {
        return null;
    }

    return $imagem;
}

function criar_reserva_publica(array $dados, ?PDO $db = null): array
{
    try {
        $db = $db ?? (new Database())->getConnection();
        $modelReserva = new \App\Model\Reserva($db);
        $serviceReserva = new \App\Service\ReservaService($db, $modelReserva);

        $resultado = $serviceReserva->criarReserva($dados);
        if (!($resultado['success'] ?? false)) {
            $resultado['http_code'] = reserva_publica_http_code($resultado);
            return $resultado;
        }

        reserva_publica_notificar_cliente(
            $dados['telefone_cliente'] ?? null,
            $dados['nome_cliente'] ?? null
        );

        reserva_publica_notificar_restaurante(
            $db,
            (int)($dados['restaurante_id'] ?? 0),
            $dados,
            $resultado
        );

        $resultado['message'] = 'Reserva criada com sucesso! Aguarde confirmacao do restaurante.';
        $resultado['http_code'] = 200;

        return $resultado;
    } catch (\InvalidArgumentException $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'http_code' => 422,
        ];
    } catch (\Throwable $e) {
        error_log('[RESERVA_PUBLICA][ERROR] ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Erro interno ao criar a reserva.',
            'http_code' => 500,
        ];
    }
}

function reserva_publica_data_valida(string $data): bool
{
    $timezone = new \DateTimeZone(date_default_timezone_get() ?: 'Africa/Maputo');
    $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $data, $timezone);
    return $dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $data;
}

function reserva_publica_hora_valida(string $hora): bool
{
    $horaCurta = substr($hora, 0, 5);
    $timezone = new \DateTimeZone(date_default_timezone_get() ?: 'Africa/Maputo');
    $dt = \DateTimeImmutable::createFromFormat('H:i', $horaCurta, $timezone);
    return $dt instanceof \DateTimeImmutable && $dt->format('H:i') === $horaCurta;
}

function reserva_publica_http_code(array $resultado): int
{
    if (!empty($resultado['success'])) {
        return 200;
    }

    $code = (string)($resultado['code'] ?? '');
    if (in_array($code, ['SEM_DISPONIBILIDADE', 'MESA_INDISPONIVEL'], true)) {
        return 409;
    }

    return !empty($resultado['error']) ? 500 : 422;
}

function reserva_publica_notificar_cliente($telefone, $nomeCliente): void
{
    $telefone = trim((string)$telefone);
    if ($telefone === '') {
        return;
    }

    $nomeCliente = trim((string)$nomeCliente);
    if ($nomeCliente === '') {
        $nomeCliente = 'cliente';
    }

    $mensagem = "Ola {$nomeCliente}, sua reserva foi recebida e esta pendente de confirmacao. Em breve voce recebera uma resposta.";
    if (!notificar_cliente_whatsapp($telefone, $mensagem)) {
        error_log('[RESERVA_PUBLICA][WHATSAPP] Falha ao notificar cliente.');
    }
}

function reserva_publica_notificar_restaurante(PDO $db, int $restauranteId, array $dados, array $resultado): void
{
    if ($restauranteId <= 0) {
        return;
    }

    $telefoneRestaurante = buscar_telefone_restaurante($db, $restauranteId);
    if (!$telefoneRestaurante) {
        return;
    }

    $nomeCliente = trim((string)($dados['nome_cliente'] ?? 'Cliente'));
    $dataReserva = trim((string)($dados['data_reserva'] ?? ''));
    $horaReserva = substr(trim((string)($dados['hora_reserva'] ?? '')), 0, 5);
    $mesaNumero = trim((string)($resultado['mesa_numero'] ?? ''));

    $mensagem = "Nova reserva recebida de {$nomeCliente} para {$dataReserva} as {$horaReserva}.";
    if ($mesaNumero !== '') {
        $mensagem .= " Mesa sugerida: {$mesaNumero}.";
    }
    $mensagem .= ' Confirme pelo painel.';

    if (!notificar_cliente_whatsapp($telefoneRestaurante, $mensagem)) {
        error_log('[RESERVA_PUBLICA][WHATSAPP] Falha ao notificar restaurante.');
    }
}
