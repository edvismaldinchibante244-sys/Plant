<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/reserva_notificacoes.php';

function notificar_cliente($reserva_id, $novo_status)
{
    $db = (new Database())->getConnection();
    return reserva_notificacoes_enviar_status_cliente($db, (int)$reserva_id, (string)$novo_status);
}
