<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/empresa.php';
requireLogin();
refreshSession();
$user = currentUser();
$db = getDB();
$id     = (int)($_GET['id'] ?? 0);
$estado = $_GET['estado'] ?? '';
$validos = ['en_proceso','lista','entregada','cancelada'];
if (!in_array($estado, $validos)) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT * FROM ordenes WHERE id=? AND sucursal_id=?");
$stmt->execute([$id, $user['sucursal_id']]); $o = $stmt->fetch();
if (!$o) { flashMessage('error','Orden no encontrada.'); header('Location: index.php'); exit; }

$upd = ["estado=?"];
$params = [$estado];
if ($estado === 'entregada') {
    $upd[] = "fecha_salida_real=?";
    $params[] = date('Y-m-d');
}
$params[] = $id;
$db->prepare("UPDATE ordenes SET ".implode(',',$upd)." WHERE id=?")->execute($params);

flashMessage('success','Estado actualizado.');
header('Location: view.php?id='.$id); exit;
