<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
refreshSession();
$db = getDB();
$suc = $_SESSION['sucursal_id'];
header('Content-Type: application/json');
echo json_encode([
    'suc' => $suc,
    'ordenes' => $db->prepare("SELECT id,numero_orden,fecha_ingreso,sucursal_id FROM ordenes WHERE sucursal_id=? ORDER BY id DESC")->execute([$suc]) ? $db->query("SELECT id,numero_orden,fecha_ingreso,sucursal_id FROM ordenes ORDER BY id DESC")->fetchAll() : [],
    'ordenes_directo' => $db->query("SELECT id,numero_orden,fecha_ingreso,sucursal_id FROM ordenes ORDER BY id DESC")->fetchAll(),
], JSON_PRETTY_PRINT);
