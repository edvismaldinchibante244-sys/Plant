<?php
// Função para buscar telefone do restaurante
function buscar_telefone_restaurante($db, $restaurante_id) {
    $stmt = $db->prepare("SELECT telefone FROM restaurantes WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $restaurante_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['telefone'] ?? null;
}
