<?php

/*
 
   API - SELECIONAR RESTAURANTE PARA ACESSO
   Define qual restaurante o Super Admin irá acessar
 
 */

session_start();
include_once '../../config/csrf.php';

if (!isset($_SESSION['logado']) || !isset($_SESSION['super_admin']) || $_SESSION['super_admin'] != 1) {
    header("Location: ../index.php");
    exit;
}

$restaurante_id = intval($_POST['restaurante_id'] ?? 0);

csrf_validate_or_json();

if ($restaurante_id > 0) {
    $_SESSION['restaurante_selecionado'] = $restaurante_id;
    $_SESSION['restaurante_id'] = $restaurante_id;
    $_SESSION['restaurante_base_id'] = $restaurante_id;
    unset($_SESSION['matriz_id'], $_SESSION['filial_id'], $_SESSION['filial_nome']);
    echo json_encode(['success' => true, 'message' => 'Restaurante selecionado']);
} else {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
}
