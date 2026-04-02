<?php
// Painel legado de reservas redirecionado para a tela atual de mesas.
include_once __DIR__ . '/../config/auth_check.php';

header('Location: mesas.php');
exit;
