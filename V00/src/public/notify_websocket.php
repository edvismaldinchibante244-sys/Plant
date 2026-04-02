<?php

/*
   Notifica servidor WebSocket sobre mudanças de pedido.
   Deve ser sempre não-bloqueante e nunca quebrar o fluxo HTTP principal.
 */

if (empty($pedido_data) || !is_array($pedido_data)) {
    return;
}

if (!function_exists('curl_init')) {
    // cURL indisponível: ignorar notificação sem impactar o pedido.
    return;
}

$restauranteId = isset($pedido_data['restaurante_id'])
    ? (int)$pedido_data['restaurante_id']
    : (int)($_SESSION['restaurante_id'] ?? 0);

if ($restauranteId <= 0) {
    return;
}

$payload = json_encode([
    'restaurante_id' => $restauranteId,
    'pedido' => $pedido_data,
], JSON_UNESCAPED_UNICODE);

if ($payload === false) {
    return;
}

$ch = curl_init('http://localhost:3001/notify');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 1,
    CURLOPT_CONNECTTIMEOUT => 1,
]);

curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    $numero = $pedido_data['numero_pedido'] ?? '?';
    error_log("WebSocket notified: pedido {$numero}");
}
