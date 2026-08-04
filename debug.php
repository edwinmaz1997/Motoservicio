<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
refreshSession();
$db = getDB();
header('Content-Type: application/json');
$hoy = date('Y-m-d');
echo json_encode([
    'session'    => ['usuario_id'=>$_SESSION['usuario_id'],'sucursal_id'=>$_SESSION['sucursal_id']??null,'rol_id'=>$_SESSION['rol_id']??null],
    'user'       => currentUser(),
    'clientes'   => $db->query("SELECT id,nombre,sucursal_id,activo FROM clientes")->fetchAll(),
    'hoy_server' => $hoy,
    'ventas_hoy' => $db->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE fecha=?")->execute([$hoy]) ? 'ok' : 'err',
    'anticipos_hoy' => $db->prepare("SELECT COALESCE(SUM(monto),0) FROM anticipos WHERE DATE(created_at)=?")->execute([$hoy]) ? 'ok' : 'err',
    'all_anticipos' => $db->query("SELECT id,monto,created_at FROM anticipos ORDER BY id DESC LIMIT 5")->fetchAll(),
    'all_ventas'    => $db->query("SELECT id,total,fecha,estado FROM ventas ORDER BY id DESC LIMIT 5")->fetchAll(),
], JSON_PRETTY_PRINT);
