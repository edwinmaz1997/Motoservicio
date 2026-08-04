<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$db = getDB();
header('Content-Type: application/json');
echo json_encode([
    'mi_sucursal' => $user['sucursal_id'],
    'clientes' => $db->query("SELECT id, nombre, apellido, sucursal_id, activo FROM clientes ORDER BY id DESC")->fetchAll(),
], JSON_PRETTY_PRINT);
