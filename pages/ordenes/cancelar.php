<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM ordenes WHERE id=? AND sucursal_id=?");
$stmt->execute([$id, $user['sucursal_id']]); $o = $stmt->fetch();
if (!$o || in_array($o['estado'],['entregada','cancelada'])) { flashMessage('error','No se puede cancelar.'); header('Location: index.php'); exit; }

// Devolver stock
$detalle = $db->prepare("SELECT od.*, p.tipo FROM orden_detalle od JOIN productos p ON p.id=od.producto_id WHERE od.orden_id=?");
$detalle->execute([$id]);
foreach ($detalle->fetchAll() as $d) {
    if ($d['tipo'] === 'estandar') {
        $db->prepare("UPDATE productos SET stock=stock+? WHERE id=?")->execute([$d['cantidad'], $d['producto_id']]);
    }
}

$db->prepare("UPDATE ordenes SET estado='cancelada' WHERE id=?")->execute([$id]);
flashMessage('success','Orden cancelada.');
header('Location: view.php?id='.$id); exit;
