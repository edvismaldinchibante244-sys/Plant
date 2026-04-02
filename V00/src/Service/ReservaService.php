<?php

namespace App\Service;

use App\Model\Reserva;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

class ReservaService
{
    private const JANELA_CONFLITO_MINUTOS = 120;
    private const ORIGENS_VALIDAS = ['app', 'telefone', 'presencial'];

    private PDO $db;
    private Reserva $modelReserva;
    private DateTimeZone $timezone;

    public function __construct(PDO $db, ?Reserva $modelReserva = null)
    {
        $this->db = $db;
        $this->modelReserva = $modelReserva ?? new Reserva($db);
        $this->timezone = new DateTimeZone(date_default_timezone_get() ?: 'Africa/Maputo');
    }

    public function criarReserva(array $dados): array
    {
        $dadosNormalizados = $this->validarPayloadReserva($dados);
        $restauranteId = (int)$dadosNormalizados['restaurante_id'];
        $mesaId = isset($dadosNormalizados['mesa_atribuida']) ? (int)$dadosNormalizados['mesa_atribuida'] : 0;

        $mesasDisponiveis = $this->validarDisponibilidade(
            $restauranteId,
            $dadosNormalizados['data_reserva'],
            $dadosNormalizados['hora_reserva'],
            (int)$dadosNormalizados['quantidade_pessoas']
        );

        if (empty($mesasDisponiveis)) {
            return [
                'success' => false,
                'message' => 'Nao ha mesas disponiveis para este horario e quantidade de pessoas.',
                'code' => 'SEM_DISPONIBILIDADE',
            ];
        }

        if ($mesaId > 0) {
            $mesaSelecionada = $this->mesaDisponivelNaLista($mesasDisponiveis, $mesaId);
            if (!$mesaSelecionada) {
                return [
                    'success' => false,
                    'message' => 'A mesa escolhida nao esta disponivel para esta reserva.',
                    'code' => 'MESA_INDISPONIVEL',
                ];
            }
        } else {
            $mesaSelecionada = $mesasDisponiveis[0];
            $mesaId = (int)$mesaSelecionada['id'];
        }

        $dadosNormalizados['mesa_atribuida'] = $mesaId;

        $this->db->beginTransaction();
        try {
            $dadosNormalizados['cliente_id'] = $this->sincronizarClienteReserva($dadosNormalizados);

            $criado = $this->modelReserva->criar($dadosNormalizados);
            if (!$criado) {
                throw new \RuntimeException('Falha ao persistir a reserva.');
            }

            $reservaId = (int)$this->db->lastInsertId();
            $this->sincronizarStatusMesaReservaHoje($restauranteId, $mesaId);
            $this->db->commit();

            $this->agendarLembrete(
                $reservaId,
                $dadosNormalizados['data_reserva'] . ' ' . $dadosNormalizados['hora_reserva']
            );

            return [
                'success' => true,
                'message' => 'Reserva criada com sucesso.',
                'reserva_id' => $reservaId,
                'cliente_id' => $dadosNormalizados['cliente_id'],
                'mesa_atribuida' => $mesaId,
                'mesa_numero' => $mesaSelecionada['numero'] ?? null,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Nao foi possivel criar a reserva agora.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function confirmarReserva(int $reservaId, int $restauranteId): array
    {
        $reserva = $this->modelReserva->obter($reservaId, $restauranteId);
        if (!$reserva) {
            return ['success' => false, 'message' => 'Reserva nao encontrada.'];
        }

        if (in_array($reserva['status'], ['cancelado', 'no-show'], true)) {
            return ['success' => false, 'message' => 'A reserva nao pode mais ser confirmada.'];
        }

        $mesaId = (int)($reserva['mesa_atribuida'] ?? 0);
        $mesasDisponiveis = $this->validarDisponibilidade(
            $restauranteId,
            (string)$reserva['data_reserva'],
            (string)$reserva['hora_reserva'],
            (int)$reserva['quantidade_pessoas'],
            $reservaId
        );

        if ($mesaId > 0 && !$this->mesaDisponivelNaLista($mesasDisponiveis, $mesaId)) {
            $mesaId = 0;
        }

        if ($mesaId <= 0) {
            if (empty($mesasDisponiveis)) {
                return ['success' => false, 'message' => 'Nao ha mesa disponivel para confirmar esta reserva.'];
            }
            $mesaId = (int)$mesasDisponiveis[0]['id'];
        }

        $this->db->beginTransaction();
        try {
            if (!$this->modelReserva->atribuirMesa($reservaId, $mesaId, $restauranteId)) {
                throw new \RuntimeException('Falha ao atribuir mesa.');
            }

            if (!$this->modelReserva->confirmar($reservaId, $restauranteId, (string)($reserva['telefone_cliente'] ?? ''))) {
                throw new \RuntimeException('Falha ao confirmar reserva.');
            }

            $this->sincronizarStatusMesaReservaHoje($restauranteId, $mesaId);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Reserva confirmada com sucesso.',
                'mesa_atribuida' => $mesaId,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Nao foi possivel confirmar a reserva.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function fazerCheckIn(int $reservaId, int $mesaId, int $restauranteId): array
    {
        $reserva = $this->modelReserva->obter($reservaId, $restauranteId);
        if (!$reserva) {
            return ['success' => false, 'message' => 'Reserva nao encontrada.'];
        }

        if (in_array($reserva['status'], ['cancelado', 'no-show'], true)) {
            return ['success' => false, 'message' => 'Esta reserva nao permite check-in.'];
        }

        $mesa = $this->obterMesa($mesaId, $restauranteId);
        if (!$mesa) {
            return ['success' => false, 'message' => 'Mesa invalida para check-in.'];
        }

        if ((int)$mesa['capacidade'] < (int)$reserva['quantidade_pessoas']) {
            return ['success' => false, 'message' => 'A mesa selecionada nao comporta a quantidade de pessoas da reserva.'];
        }

        $mesasDisponiveis = $this->validarDisponibilidade(
            $restauranteId,
            (string)$reserva['data_reserva'],
            (string)$reserva['hora_reserva'],
            (int)$reserva['quantidade_pessoas'],
            $reservaId
        );

        if (!$this->mesaDisponivelNaLista($mesasDisponiveis, $mesaId)) {
            return ['success' => false, 'message' => 'A mesa escolhida nao esta disponivel para check-in.'];
        }

        $this->db->beginTransaction();
        try {
            if (!$this->modelReserva->atribuirMesa($reservaId, $mesaId, $restauranteId)) {
                throw new \RuntimeException('Falha ao vincular mesa.');
            }

            if (!$this->modelReserva->confirmar($reservaId, $restauranteId, (string)($reserva['telefone_cliente'] ?? ''))) {
                throw new \RuntimeException('Falha ao consolidar a reserva.');
            }

            $stmtMesa = $this->db->prepare("UPDATE mesas SET status = 'OCUPADA' WHERE id = :id AND restaurante_id = :restaurante_id");
            $stmtMesa->execute([
                ':id' => $mesaId,
                ':restaurante_id' => $restauranteId,
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Check-in realizado com sucesso.',
                'mesa_atribuida' => $mesaId,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Nao foi possivel realizar o check-in.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelarReserva(int $reservaId, int $restauranteId): array
    {
        $reserva = $this->modelReserva->obter($reservaId, $restauranteId);
        if (!$reserva) {
            return ['success' => false, 'message' => 'Reserva nao encontrada.'];
        }

        $this->db->beginTransaction();
        try {
            if (!$this->modelReserva->cancelar($reservaId, $restauranteId)) {
                throw new \RuntimeException('Falha ao cancelar.');
            }

            $mesaId = (int)($reserva['mesa_atribuida'] ?? 0);
            if ($mesaId > 0) {
                $this->sincronizarStatusMesaReservaHoje($restauranteId, $mesaId);
            }

            $this->db->commit();

            return ['success' => true, 'message' => 'Reserva cancelada com sucesso.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Nao foi possivel cancelar a reserva.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function marcarNoShow(int $reservaId, int $restauranteId): array
    {
        $reserva = $this->modelReserva->obter($reservaId, $restauranteId);
        if (!$reserva) {
            return ['success' => false, 'message' => 'Reserva nao encontrada.'];
        }

        $this->db->beginTransaction();
        try {
            if (!$this->modelReserva->marcarNoShow($reservaId, $restauranteId)) {
                throw new \RuntimeException('Falha ao marcar no-show.');
            }

            $mesaId = (int)($reserva['mesa_atribuida'] ?? 0);
            if ($mesaId > 0) {
                $this->sincronizarStatusMesaReservaHoje($restauranteId, $mesaId);
            }

            $this->db->commit();

            return ['success' => true, 'message' => 'Reserva marcada como no-show.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Nao foi possivel marcar no-show.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function validarDisponibilidade(int $restauranteId, string $data, string $hora, int $quantidade, int $ignorarReservaId = 0): array
    {
        if ($quantidade <= 0) {
            return [];
        }

        if (!$this->dataValida($data) || !$this->horaValida($hora)) {
            return [];
        }

        $sqlMesas = "SELECT id, numero, capacidade, status
            FROM mesas
            WHERE restaurante_id = :restaurante_id
              AND capacidade >= :quantidade
              AND status <> 'OCUPADA'
            ORDER BY capacidade ASC, numero ASC";

        $stmtMesas = $this->db->prepare($sqlMesas);
        $stmtMesas->execute([
            ':restaurante_id' => $restauranteId,
            ':quantidade' => $quantidade,
        ]);
        $mesas = $stmtMesas->fetchAll(PDO::FETCH_ASSOC);

        if (empty($mesas)) {
            return [];
        }

        $sqlConflitos = "SELECT mesa_atribuida
            FROM reservas
            WHERE restaurante_id = :restaurante_id
              AND data_reserva = :data_reserva
              AND status IN ('pendente', 'confirmado')
              AND mesa_atribuida IS NOT NULL
              AND ABS(TIME_TO_SEC(TIMEDIFF(hora_reserva, :hora_reserva))) < :janela_segundos";

        $params = [
            ':restaurante_id' => $restauranteId,
            ':data_reserva' => $data,
            ':hora_reserva' => $hora,
            ':janela_segundos' => self::JANELA_CONFLITO_MINUTOS * 60,
        ];

        if ($ignorarReservaId > 0) {
            $sqlConflitos .= " AND id <> :ignorar_id";
            $params[':ignorar_id'] = $ignorarReservaId;
        }

        $stmtConflitos = $this->db->prepare($sqlConflitos);
        $stmtConflitos->execute($params);

        $mesasOcupadasNoHorario = [];
        while ($row = $stmtConflitos->fetch(PDO::FETCH_ASSOC)) {
            $mesaId = (int)($row['mesa_atribuida'] ?? 0);
            if ($mesaId > 0) {
                $mesasOcupadasNoHorario[$mesaId] = true;
            }
        }

        return array_values(array_filter($mesas, static function (array $mesa) use ($mesasOcupadasNoHorario) {
            return !isset($mesasOcupadasNoHorario[(int)$mesa['id']]);
        }));
    }

    public function calcularOcupacao(int $restauranteId, string $data): array
    {
        $stmtTotalMesas = $this->db->prepare("SELECT COUNT(*) FROM mesas WHERE restaurante_id = :restaurante_id");
        $stmtTotalMesas->execute([':restaurante_id' => $restauranteId]);
        $totalMesas = (int)$stmtTotalMesas->fetchColumn();

        $stmtReservas = $this->db->prepare("
            SELECT status, COUNT(*) AS total
            FROM reservas
            WHERE restaurante_id = :restaurante_id
              AND data_reserva = :data_reserva
            GROUP BY status
        ");
        $stmtReservas->execute([
            ':restaurante_id' => $restauranteId,
            ':data_reserva' => $data,
        ]);

        $totais = [
            'pendente' => 0,
            'confirmado' => 0,
            'cancelado' => 0,
            'no-show' => 0,
        ];

        while ($row = $stmtReservas->fetch(PDO::FETCH_ASSOC)) {
            $status = (string)$row['status'];
            if (array_key_exists($status, $totais)) {
                $totais[$status] = (int)$row['total'];
            }
        }

        $ativas = $totais['pendente'] + $totais['confirmado'];
        $percentual = $totalMesas > 0 ? round(($ativas / $totalMesas) * 100, 1) : 0.0;

        return [
            'total_mesas' => $totalMesas,
            'reservas_ativas' => $ativas,
            'pendentes' => $totais['pendente'],
            'confirmadas' => $totais['confirmado'],
            'canceladas' => $totais['cancelado'],
            'no_show' => $totais['no-show'],
            'percentual' => $percentual,
        ];
    }

    public function agendarLembrete(int $reservaId, string $dataHoraReserva): bool
    {
        return $reservaId > 0 && $dataHoraReserva !== '';
    }

    private function validarPayloadReserva(array $dados): array
    {
        $restauranteId = (int)($dados['restaurante_id'] ?? 0);
        $clienteId = !empty($dados['cliente_id']) ? (int)$dados['cliente_id'] : null;
        $clienteExistente = null;
        $dataReserva = trim((string)($dados['data_reserva'] ?? $dados['data'] ?? ''));
        $horaReserva = trim((string)($dados['hora_reserva'] ?? $dados['hora'] ?? ''));
        $quantidadePessoas = (int)($dados['quantidade_pessoas'] ?? $dados['pessoas'] ?? 1);
        $status = $this->normalizarStatus((string)($dados['status'] ?? $dados['novo_status'] ?? 'pendente'));
        $origem = $this->normalizarOrigem($dados['origem'] ?? 'app');
        $statusValidos = ['pendente', 'confirmado', 'cancelado', 'no-show'];

        if ($restauranteId <= 0) {
            throw new \InvalidArgumentException('Restaurante invalido para a reserva.');
        }

        if ($clienteId !== null) {
            $clienteExistente = $this->buscarClientePorIdNoRestaurante($clienteId, $restauranteId);
            if (!$clienteExistente) {
                throw new \InvalidArgumentException('Cliente informado nao pertence ao restaurante.');
            }
        }

        $nomeCliente = trim((string)($dados['nome_cliente'] ?? $dados['nome'] ?? ($clienteExistente['nome'] ?? '')));
        $emailCliente = $dados['email_cliente'] ?? $dados['email'] ?? ($clienteExistente['email'] ?? null);
        $telefoneCliente = $dados['telefone_cliente'] ?? $dados['telefone'] ?? ($clienteExistente['telefone'] ?? null);

        if ($nomeCliente === '') {
            throw new \InvalidArgumentException('O nome do cliente e obrigatorio.');
        }

        if (!$this->dataValida($dataReserva)) {
            throw new \InvalidArgumentException('Data da reserva invalida.');
        }

        if (!$this->horaValida($horaReserva)) {
            throw new \InvalidArgumentException('Hora da reserva invalida.');
        }

        if ($quantidadePessoas <= 0) {
            throw new \InvalidArgumentException('A quantidade de pessoas deve ser maior que zero.');
        }

        if (!in_array($status, $statusValidos, true)) {
            $status = 'pendente';
        }

        $reservaDateTime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $dataReserva . ' ' . substr($horaReserva, 0, 5),
            $this->timezone
        );

        if (!$reservaDateTime) {
            throw new \InvalidArgumentException('Nao foi possivel interpretar a data e hora da reserva.');
        }

        if ($reservaDateTime < new DateTimeImmutable('now', $this->timezone)) {
            throw new \InvalidArgumentException('Nao e permitido criar reservas em horario passado.');
        }

        return [
            'restaurante_id' => $restauranteId,
            'cliente_id' => $clienteId,
            'nome_cliente' => $nomeCliente,
            'email_cliente' => $this->normalizarEmail($emailCliente),
            'telefone_cliente' => $this->normalizarTelefone($telefoneCliente),
            'data_reserva' => $dataReserva,
            'hora_reserva' => substr($horaReserva, 0, 5) . ':00',
            'quantidade_pessoas' => $quantidadePessoas,
            'observacoes' => $this->normalizarTexto($dados['observacoes'] ?? $dados['observacao'] ?? null, 500),
            'status' => $status,
            'origem' => $origem,
            'mesa_atribuida' => !empty($dados['mesa_atribuida']) ? (int)$dados['mesa_atribuida'] : (!empty($dados['mesa_id']) ? (int)$dados['mesa_id'] : null),
        ];
    }

    private function sincronizarClienteReserva(array &$dados): ?int
    {
        $restauranteId = (int)$dados['restaurante_id'];
        $clienteId = (int)($dados['cliente_id'] ?? 0);
        $cliente = null;

        if ($clienteId > 0) {
            $cliente = $this->buscarClientePorIdNoRestaurante($clienteId, $restauranteId);
        } else {
            $cliente = $this->buscarClientePorContato(
                $restauranteId,
                $dados['telefone_cliente'] ?? null,
                $dados['email_cliente'] ?? null
            );
        }

        if ($cliente) {
            $this->validarClienteDisponivelParaReserva($cliente);

            if ($dados['email_cliente'] === null && !empty($cliente['email'])) {
                $dados['email_cliente'] = (string)$cliente['email'];
            }

            if ($dados['telefone_cliente'] === null && !empty($cliente['telefone'])) {
                $dados['telefone_cliente'] = (string)$cliente['telefone'];
            }

            $this->atualizarClienteParaReserva((int)$cliente['id'], $restauranteId, $dados, $cliente);
            return (int)$cliente['id'];
        }

        if ($clienteId > 0) {
            throw new \InvalidArgumentException('Cliente informado nao pertence ao restaurante.');
        }

        if (($dados['telefone_cliente'] ?? null) === null && ($dados['email_cliente'] ?? null) === null) {
            return null;
        }

        return $this->criarClienteParaReserva($restauranteId, $dados);
    }

    private function obterMesa(int $mesaId, int $restauranteId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, numero, capacidade, status
            FROM mesas
            WHERE id = :id AND restaurante_id = :restaurante_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $mesaId,
            ':restaurante_id' => $restauranteId,
        ]);

        $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
        return $mesa ?: null;
    }

    private function mesaDisponivelNaLista(array $mesasDisponiveis, int $mesaId): ?array
    {
        foreach ($mesasDisponiveis as $mesa) {
            if ((int)$mesa['id'] === $mesaId) {
                return $mesa;
            }
        }

        return null;
    }

    private function sincronizarStatusMesaReservaHoje(int $restauranteId, int $mesaId): void
    {
        if ($mesaId <= 0) {
            return;
        }

        $mesa = $this->obterMesa($mesaId, $restauranteId);
        if (!$mesa || strtoupper((string)($mesa['status'] ?? '')) === 'OCUPADA') {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM reservas
            WHERE restaurante_id = :restaurante_id
              AND mesa_atribuida = :mesa_id
              AND data_reserva = CURDATE()
              AND status IN ('pendente', 'confirmado')
        ");
        $stmt->execute([
            ':restaurante_id' => $restauranteId,
            ':mesa_id' => $mesaId,
        ]);
        $temReservaHoje = (int)$stmt->fetchColumn() > 0;

        $novoStatus = $temReservaHoje ? 'RESERVADA' : 'LIVRE';
        $stmtMesa = $this->db->prepare("
            UPDATE mesas
            SET status = :status
            WHERE id = :id
              AND restaurante_id = :restaurante_id
              AND status <> 'OCUPADA'
        ");
        $stmtMesa->execute([
            ':status' => $novoStatus,
            ':id' => $mesaId,
            ':restaurante_id' => $restauranteId,
        ]);
    }

    private function dataValida(string $data): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data, $this->timezone);
        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $data;
    }

    private function horaValida(string $hora): bool
    {
        $horaCurta = substr($hora, 0, 5);
        $dt = DateTimeImmutable::createFromFormat('H:i', $horaCurta, $this->timezone);
        return $dt instanceof DateTimeImmutable && $dt->format('H:i') === $horaCurta;
    }

    private function normalizarEmail($valor): ?string
    {
        $email = trim((string)$valor);
        if ($email === '') {
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email do cliente invalido.');
        }

        return $email;
    }

    private function normalizarTelefone($valor): ?string
    {
        $telefone = trim((string)$valor);
        if ($telefone === '') {
            return null;
        }

        $prefixo = str_starts_with($telefone, '+') ? '+' : '';
        $digitos = preg_replace('/\D+/', '', $telefone);
        if ($digitos === '' || strlen($digitos) < 8) {
            throw new \InvalidArgumentException('Telefone do cliente invalido.');
        }

        return substr($prefixo . $digitos, 0, 30);
    }

    private function normalizarTexto($valor, int $limite): ?string
    {
        $texto = trim((string)$valor);
        if ($texto === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($texto, 0, $limite) : substr($texto, 0, $limite);
    }

    private function normalizarStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'confirmada' => 'confirmado',
            'cancelada' => 'cancelado',
            'no_show', 'noshow' => 'no-show',
            default => $status !== '' ? $status : 'pendente',
        };
    }

    private function normalizarOrigem($origem): string
    {
        $origem = strtolower(trim((string)$origem));
        return in_array($origem, self::ORIGENS_VALIDAS, true) ? $origem : 'app';
    }

    private function buscarClientePorIdNoRestaurante(int $clienteId, int $restauranteId): ?array
    {
        if ($clienteId <= 0 || $restauranteId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM clientes
            WHERE id = :id
              AND restaurante_id = :restaurante_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $clienteId,
            ':restaurante_id' => $restauranteId,
        ]);

        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cliente ?: null;
    }

    private function buscarClientePorContato(int $restauranteId, ?string $telefone, ?string $email): ?array
    {
        $condicoes = [];
        $params = [':restaurante_id' => $restauranteId];

        if ($telefone !== null && $telefone !== '') {
            $condicoes[] = 'telefone = :telefone';
            $params[':telefone'] = $telefone;
        }

        if ($email !== null && $email !== '') {
            $condicoes[] = 'LOWER(email) = LOWER(:email)';
            $params[':email'] = $email;
        }

        if (empty($condicoes)) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM clientes
            WHERE restaurante_id = :restaurante_id
              AND (" . implode(' OR ', $condicoes) . ")
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute($params);

        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cliente ?: null;
    }

    private function validarClienteDisponivelParaReserva(array $cliente): void
    {
        if (isset($cliente['ativo']) && (int)$cliente['ativo'] === 0) {
            throw new \InvalidArgumentException('O cliente informado esta inativo.');
        }

        if (isset($cliente['bloqueado']) && (int)$cliente['bloqueado'] === 1) {
            $motivo = trim((string)($cliente['motivo_bloqueio'] ?? ''));
            throw new \InvalidArgumentException(
                $motivo !== ''
                    ? 'O cliente esta bloqueado: ' . $motivo
                    : 'O cliente informado esta bloqueado.'
            );
        }
    }

    private function atualizarClienteParaReserva(int $clienteId, int $restauranteId, array $dados, array $clienteAtual): void
    {
        $set = [];
        $params = [
            ':id' => $clienteId,
            ':restaurante_id' => $restauranteId,
        ];

        $nome = trim((string)($dados['nome_cliente'] ?? ''));
        $email = $dados['email_cliente'] ?? null;
        $telefone = $dados['telefone_cliente'] ?? null;

        if ($nome !== '' && trim((string)($clienteAtual['nome'] ?? '')) !== $nome) {
            $set[] = 'nome = :nome';
            $params[':nome'] = $nome;
        }

        if ($email !== null && trim((string)($clienteAtual['email'] ?? '')) !== $email) {
            $set[] = 'email = :email';
            $params[':email'] = $email;
        }

        if ($telefone !== null && trim((string)($clienteAtual['telefone'] ?? '')) !== $telefone) {
            $set[] = 'telefone = :telefone';
            $params[':telefone'] = $telefone;
        }

        if (empty($set)) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE clientes
            SET " . implode(', ', $set) . "
            WHERE id = :id
              AND restaurante_id = :restaurante_id
        ");
        $stmt->execute($params);
    }

    private function criarClienteParaReserva(int $restauranteId, array $dados): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO clientes (restaurante_id, nome, email, telefone, tipo_cliente, data_primeira_visita)
            VALUES (:restaurante_id, :nome, :email, :telefone, 'comum', CURDATE())
        ");
        $stmt->execute([
            ':restaurante_id' => $restauranteId,
            ':nome' => $dados['nome_cliente'],
            ':email' => $dados['email_cliente'] ?? null,
            ':telefone' => $dados['telefone_cliente'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
