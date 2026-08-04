<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$db = getDB();
$rows = $db->query("SELECT id, numero_orden, fecha_ingreso, estado, sucursal_id FROM ordenes ORDER BY id DESC")->fetchAll();
header('Content-Type: application/json');
echo json_encode($rows, JSON_PRETTY_PRINT);
