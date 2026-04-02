<?php

require_once __DIR__ . '/notificar_cliente_whatsapp.php';
require_once __DIR__ . '/../config/email_helper.php';

if (!function_exists('reserva_notificacoes_normalizar_status')) {
    function reserva_notificacoes_normalizar_status(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'confirmada', 'confirmado', 'confirmar' => 'confirmado',
            'cancelada', 'cancelado', 'cancelar' => 'cancelado',
            'no_show', 'noshow' => 'no-show',
            'checkin', 'check-in' => 'checkin',
            default => $status,
        };
    }
}

if (!function_exists('reserva_notificacoes_textos_status')) {
    function reserva_notificacoes_textos_status(array $reserva, string $status): array
    {
        $nomeCliente = trim((string)($reserva['nome_cliente'] ?? 'cliente'));
        if ($nomeCliente === '') {
            $nomeCliente = 'cliente';
        }

        $restauranteNome = trim((string)($reserva['restaurante_nome'] ?? 'restaurante'));
        if ($restauranteNome === '') {
            $restauranteNome = 'restaurante';
        }

        $dataReserva = trim((string)($reserva['data_reserva'] ?? ''));
        $horaReserva = substr(trim((string)($reserva['hora_reserva'] ?? '')), 0, 5);

        $assunto = 'Atualizacao da sua reserva';
        $frase = 'teve o status atualizado';

        switch ($status) {
            case 'confirmado':
                $assunto = 'Sua reserva foi confirmada';
                $frase = 'foi confirmada';
                break;
            case 'cancelado':
                $assunto = 'Sua reserva foi cancelada';
                $frase = 'foi cancelada';
                break;
            case 'no-show':
                $assunto = 'Sua reserva foi marcada como no-show';
                $frase = 'foi marcada como no-show';
                break;
            case 'checkin':
                $assunto = 'Check-in da sua reserva registrado';
                $frase = 'teve o check-in registrado';
                break;
        }

        $mensagemWhatsapp = "Ola {$nomeCliente}, sua reserva no {$restauranteNome} para {$dataReserva} as {$horaReserva} {$frase}.";
        $mensagemEmail = '<p>Ola ' . htmlspecialchars($nomeCliente, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Sua reserva no <strong>' . htmlspecialchars($restauranteNome, ENT_QUOTES, 'UTF-8') . '</strong> para <strong>'
            . htmlspecialchars($dataReserva, ENT_QUOTES, 'UTF-8') . '</strong> as <strong>'
            . htmlspecialchars($horaReserva, ENT_QUOTES, 'UTF-8') . '</strong> ' . htmlspecialchars($frase, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p>Se precisar de suporte, responda este contato.</p>';

        return [
            'assunto' => $assunto,
            'whatsapp' => $mensagemWhatsapp,
            'email' => $mensagemEmail,
        ];
    }
}

if (!function_exists('reserva_notificacoes_buscar_reserva')) {
    function reserva_notificacoes_buscar_reserva(PDO $db, int $reservaId, ?int $restauranteId = null): ?array
    {
        if ($reservaId <= 0) {
            return null;
        }

        $sql = "
            SELECT
                r.id,
                r.restaurante_id,
                r.nome_cliente,
                r.email_cliente,
                r.telefone_cliente,
                r.data_reserva,
                r.hora_reserva,
                r.status,
                c.email AS cliente_email,
                c.telefone AS cliente_telefone,
                rest.nome AS restaurante_nome
            FROM reservas r
            LEFT JOIN clientes c ON c.id = r.cliente_id
            INNER JOIN restaurantes rest ON rest.id = r.restaurante_id
            WHERE r.id = :id
        ";

        $params = [':id' => $reservaId];
        if ($restauranteId !== null && $restauranteId > 0) {
            $sql .= ' AND r.restaurante_id = :restaurante_id';
            $params[':restaurante_id'] = $restauranteId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reserva ?: null;
    }
}

if (!function_exists('reserva_notificacoes_enviar_status_cliente')) {
    function reserva_notificacoes_enviar_status_cliente(PDO $db, int $reservaId, string $novoStatus, ?int $restauranteId = null): bool
    {
        $reserva = reserva_notificacoes_buscar_reserva($db, $reservaId, $restauranteId);
        if (!$reserva) {
            return false;
        }

        $status = reserva_notificacoes_normalizar_status($novoStatus !== '' ? $novoStatus : (string)($reserva['status'] ?? ''));
        if ($status === '') {
            return false;
        }

        $textos = reserva_notificacoes_textos_status($reserva, $status);
        $telefone = trim((string)($reserva['telefone_cliente'] ?: ($reserva['cliente_telefone'] ?? '')));
        $email = trim((string)($reserva['email_cliente'] ?: ($reserva['cliente_email'] ?? '')));
        $enviado = false;

        if ($telefone !== '') {
            $enviado = notificar_cliente_whatsapp($telefone, $textos['whatsapp']) || $enviado;
        }

        if ($email !== '') {
            $enviado = saas_enviar_email($email, $textos['assunto'], $textos['email']) || $enviado;
        }

        if (!$enviado) {
            error_log('[RESERVA][NOTIFICACAO] Nenhum canal disponivel ou envio falhou para a reserva ' . $reservaId . '.');
        }

        return $enviado;
    }
}
