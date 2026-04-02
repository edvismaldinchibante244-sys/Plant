<?php

require_once __DIR__ . '/plano_check.php';
require_once __DIR__ . '/restaurante_context.php';

if (!function_exists('filiais_restaurantes_colunas')) {
    function filiais_restaurantes_colunas(PDO $db): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $stmt = $db->query('SHOW COLUMNS FROM restaurantes');
        while ($coluna = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[] = $coluna['Field'];
        }

        return $cache;
    }
}

if (!function_exists('filiais_limpar_texto')) {
    function filiais_limpar_texto($valor, int $limite = 255): string
    {
        $valor = trim((string)$valor);
        $valor = preg_replace('/\s+/u', ' ', $valor) ?? $valor;

        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $limite);
        }

        return substr($valor, 0, $limite);
    }
}

if (!function_exists('filiais_normalizar_status')) {
    function filiais_normalizar_status($status): string
    {
        $status = strtoupper(trim((string)$status));
        return $status === 'INATIVO' ? 'INATIVO' : 'ATIVO';
    }
}

if (!function_exists('filiais_obter_restaurante')) {
    function filiais_obter_restaurante(PDO $db, int $restauranteId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM restaurantes WHERE id = ? LIMIT 1');
        $stmt->execute([$restauranteId]);
        $restaurante = $stmt->fetch(PDO::FETCH_ASSOC);

        return $restaurante ?: null;
    }
}

if (!function_exists('filiais_obter_contexto')) {
    function filiais_obter_contexto(PDO $db, int $restauranteAtualId, int $matrizSessionId = 0): array
    {
        if ($restauranteAtualId <= 0) {
            throw new RuntimeException('Restaurante atual invalido.');
        }

        $restauranteAtual = filiais_obter_restaurante($db, $restauranteAtualId);
        if (!$restauranteAtual) {
            throw new RuntimeException('Restaurante atual nao encontrado.');
        }

        $restauranteAtualEhMatriz = ((int)($restauranteAtual['is_matriz'] ?? 1)) === 1;
        $matrizId = $restauranteAtualEhMatriz
            ? (int)$restauranteAtual['id']
            : (int)($restauranteAtual['filial_id'] ?? 0);

        if (!$restauranteAtualEhMatriz && $matrizId <= 0 && $matrizSessionId > 0) {
            $matrizId = $matrizSessionId;
        }

        if ($matrizId <= 0) {
            throw new RuntimeException('Matriz vinculada a esta filial nao foi encontrada.');
        }

        $matriz = filiais_obter_restaurante($db, $matrizId);
        if (!$matriz) {
            throw new RuntimeException('Matriz principal nao encontrada.');
        }

        if (((int)($matriz['is_matriz'] ?? 0)) !== 1) {
            throw new RuntimeException('O restaurante principal informado nao e uma matriz valida.');
        }

        if (!$restauranteAtualEhMatriz && (int)($restauranteAtual['filial_id'] ?? 0) !== $matrizId) {
            throw new RuntimeException('A filial atual nao pertence a matriz informada.');
        }

        return [
            'restaurante_atual' => $restauranteAtual,
            'restaurante_atual_eh_matriz' => $restauranteAtualEhMatriz,
            'matriz' => $matriz,
            'matriz_id' => $matrizId,
            'tem_multi_filial' => plano_tem_funcionalidade_db($matrizId, 'multi_filial'),
            'plano' => plano_get_dados($matrizId),
        ];
    }
}

if (!function_exists('filiais_obter_por_id')) {
    function filiais_obter_por_id(PDO $db, int $matrizId, int $filialId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM restaurantes WHERE id = ? AND filial_id = ? AND is_matriz = 0 LIMIT 1');
        $stmt->execute([$filialId, $matrizId]);
        $filial = $stmt->fetch(PDO::FETCH_ASSOC);

        return $filial ?: null;
    }
}

if (!function_exists('filiais_contar')) {
    function filiais_contar(PDO $db, int $matrizId): int
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM restaurantes WHERE filial_id = ? AND is_matriz = 0');
        $stmt->execute([$matrizId]);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('filiais_validar_dados')) {
    function filiais_validar_dados(array $dados, bool $incluirStatus = true): array
    {
        $nome = filiais_limpar_texto($dados['nome_filial'] ?? $dados['nome'] ?? '', 120);
        if ($nome === '') {
            throw new InvalidArgumentException('O nome da filial e obrigatorio.');
        }

        $email = filiais_limpar_texto($dados['email_filial'] ?? $dados['email'] ?? '', 160);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um email valido para a filial.');
        }

        $telefone = filiais_limpar_texto($dados['telefone_filial'] ?? $dados['telefone'] ?? '', 40);
        $endereco = filiais_limpar_texto($dados['endereco_filial'] ?? $dados['endereco'] ?? '', 180);
        $cidade = filiais_limpar_texto($dados['cidade_filial'] ?? $dados['cidade'] ?? '', 80);

        $payload = [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'cidade' => $cidade,
        ];

        if ($incluirStatus) {
            $payload['status'] = filiais_normalizar_status($dados['status'] ?? 'ATIVO');
        }

        return $payload;
    }
}

if (!function_exists('filiais_email_em_uso')) {
    function filiais_email_em_uso(PDO $db, string $email, int $ignorarRestauranteId = 0): bool
    {
        if ($email === '') {
            return false;
        }

        $sql = 'SELECT id FROM restaurantes WHERE LOWER(email) = LOWER(?)';
        $parametros = [$email];

        if ($ignorarRestauranteId > 0) {
            $sql .= ' AND id <> ?';
            $parametros[] = $ignorarRestauranteId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('filiais_nome_duplicado')) {
    function filiais_nome_duplicado(PDO $db, int $matrizId, string $nome, int $ignorarFilialId = 0): bool
    {
        $sql = 'SELECT id FROM restaurantes WHERE filial_id = ? AND is_matriz = 0 AND LOWER(nome) = LOWER(?)';
        $parametros = [$matrizId, $nome];

        if ($ignorarFilialId > 0) {
            $sql .= ' AND id <> ?';
            $parametros[] = $ignorarFilialId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('filiais_validar_limite')) {
    function filiais_validar_limite(PDO $db, int $matrizId): array
    {
        $quantidadeAtual = filiais_contar($db, $matrizId);
        return plano_verificar_limite_db($matrizId, 'filiais', $quantidadeAtual);
    }
}

if (!function_exists('filiais_mensagem_limite')) {
    function filiais_mensagem_limite(array $verificacao): string
    {
        $plano = $verificacao['plano'] ?? 'atual';
        $limite = (int)($verificacao['limite'] ?? 0);
        return "Limite de filiais atingido. O plano {$plano} permite ate {$limite} filiais.";
    }
}

if (!function_exists('filiais_criar')) {
    function filiais_criar(PDO $db, int $matrizId, array $dados): array
    {
        $payload = filiais_validar_dados($dados);
        $verificacao = filiais_validar_limite($db, $matrizId);
        if (!$verificacao['permitido']) {
            return [
                'success' => false,
                'message' => filiais_mensagem_limite($verificacao),
                'code' => 'LIMITE_FILIAIS',
            ];
        }

        if (filiais_nome_duplicado($db, $matrizId, $payload['nome'])) {
            return [
                'success' => false,
                'message' => 'Ja existe uma filial com este nome na sua matriz.',
                'code' => 'FILIAL_DUPLICADA',
            ];
        }

        if (filiais_email_em_uso($db, $payload['email'])) {
            return [
                'success' => false,
                'message' => 'Este email ja esta associado a outro restaurante ou filial.',
                'code' => 'EMAIL_EM_USO',
            ];
        }

        $matriz = filiais_obter_restaurante($db, $matrizId);
        if (!$matriz) {
            return [
                'success' => false,
                'message' => 'Matriz principal nao encontrada para concluir a criacao.',
            ];
        }

        $colunasTabela = filiais_restaurantes_colunas($db);
        $colunas = ['nome', 'email', 'telefone', 'endereco', 'cidade', 'filial_id', 'is_matriz', 'status'];
        $placeholders = [':nome', ':email', ':telefone', ':endereco', ':cidade', ':filial_id', ':is_matriz', ':status'];
        $parametros = [
            ':nome' => $payload['nome'],
            ':email' => $payload['email'] !== '' ? $payload['email'] : null,
            ':telefone' => $payload['telefone'] !== '' ? $payload['telefone'] : null,
            ':endereco' => $payload['endereco'] !== '' ? $payload['endereco'] : null,
            ':cidade' => $payload['cidade'] !== '' ? $payload['cidade'] : null,
            ':filial_id' => $matrizId,
            ':is_matriz' => 0,
            ':status' => $payload['status'],
        ];

        foreach (['plano', 'plano_id', 'data_inicio', 'data_fim'] as $colunaOpcional) {
            if (in_array($colunaOpcional, $colunasTabela, true) && array_key_exists($colunaOpcional, $matriz)) {
                $colunas[] = $colunaOpcional;
                $placeholder = ':' . $colunaOpcional;
                $placeholders[] = $placeholder;
                $parametros[$placeholder] = $matriz[$colunaOpcional];
            }
        }

        if (in_array('created_at', $colunasTabela, true)) {
            $colunas[] = 'created_at';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO restaurantes (' . implode(', ', $colunas) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);

        return [
            'success' => true,
            'message' => 'Filial criada com sucesso.',
            'filial_id' => (int)$db->lastInsertId(),
        ];
    }
}

if (!function_exists('filiais_atualizar')) {
    function filiais_atualizar(PDO $db, int $matrizId, int $filialId, array $dados): array
    {
        if ($filialId <= 0) {
            return [
                'success' => false,
                'message' => 'Filial invalida.',
            ];
        }

        $filial = filiais_obter_por_id($db, $matrizId, $filialId);
        if (!$filial) {
            return [
                'success' => false,
                'message' => 'Filial nao encontrada para atualizacao.',
            ];
        }

        $payload = filiais_validar_dados($dados);
        $payload['status'] = filiais_normalizar_status($dados['status'] ?? ($filial['status'] ?? 'ATIVO'));

        if (filiais_nome_duplicado($db, $matrizId, $payload['nome'], $filialId)) {
            return [
                'success' => false,
                'message' => 'Ja existe outra filial com este nome na sua matriz.',
                'code' => 'FILIAL_DUPLICADA',
            ];
        }

        if (filiais_email_em_uso($db, $payload['email'], $filialId)) {
            return [
                'success' => false,
                'message' => 'Este email ja esta associado a outro restaurante ou filial.',
                'code' => 'EMAIL_EM_USO',
            ];
        }

        $colunasTabela = filiais_restaurantes_colunas($db);
        $set = [
            'nome = :nome',
            'email = :email',
            'telefone = :telefone',
            'endereco = :endereco',
            'cidade = :cidade',
            'status = :status',
        ];
        $parametros = [
            ':nome' => $payload['nome'],
            ':email' => $payload['email'] !== '' ? $payload['email'] : null,
            ':telefone' => $payload['telefone'] !== '' ? $payload['telefone'] : null,
            ':endereco' => $payload['endereco'] !== '' ? $payload['endereco'] : null,
            ':cidade' => $payload['cidade'] !== '' ? $payload['cidade'] : null,
            ':status' => $payload['status'],
            ':id' => $filialId,
            ':filial_id' => $matrizId,
        ];

        if (in_array('updated_at', $colunasTabela, true)) {
            $set[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE restaurantes SET ' . implode(', ', $set) . ' WHERE id = :id AND filial_id = :filial_id AND is_matriz = 0';
        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);

        return [
            'success' => true,
            'message' => 'Filial atualizada com sucesso.',
        ];
    }
}

if (!function_exists('filiais_assumir_contexto')) {
    function filiais_assumir_contexto(PDO $db, int $matrizId, int $filialId): array
    {
        $filial = filiais_obter_por_id($db, $matrizId, $filialId);
        if (!$filial) {
            return [
                'success' => false,
                'message' => 'Filial invalida ou sem acesso.',
            ];
        }

        if (filiais_normalizar_status($filial['status'] ?? 'ATIVO') !== 'ATIVO') {
            return [
                'success' => false,
                'message' => 'Esta filial esta inativa e nao pode ser acessada.',
            ];
        }

        $_SESSION['matriz_id'] = $matrizId;
        $_SESSION['filial_id'] = $filialId;
        $_SESSION['filial_nome'] = (string)($filial['nome'] ?? '');
        $_SESSION['restaurante_id'] = $filialId;
        $_SESSION['restaurante_selecionado'] = $filialId;
        $_SESSION['restaurante_base_id'] = session_restaurante_auth_id() > 0
            ? session_restaurante_auth_id()
            : $matrizId;
        $_SESSION['last_presence_ping'] = 0;

        return [
            'success' => true,
            'message' => 'Contexto da filial ativado.',
            'filial' => $filial,
        ];
    }
}

if (!function_exists('filiais_retornar_para_matriz')) {
    function filiais_retornar_para_matriz(int $matrizId): array
    {
        if ($matrizId <= 0) {
            return [
                'success' => false,
                'message' => 'Matriz invalida para restaurar o contexto.',
            ];
        }

        $_SESSION['restaurante_id'] = $matrizId;
        $_SESSION['restaurante_base_id'] = session_restaurante_auth_id() > 0
            ? session_restaurante_auth_id()
            : $matrizId;
        unset($_SESSION['matriz_id'], $_SESSION['filial_id'], $_SESSION['filial_nome'], $_SESSION['restaurante_selecionado']);
        $_SESSION['last_presence_ping'] = 0;

        return [
            'success' => true,
            'message' => 'Contexto da matriz restaurado.',
        ];
    }
}
