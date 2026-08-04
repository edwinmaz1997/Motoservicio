<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB(); $id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM ventas WHERE id=? AND sucursal_id=? AND estado='pagada'");
$stmt->execute([$id,$user['sucursal_id']]); $v=$stmt->fetch();
if (!$v) { flashMessage('error','Venta no encontrada o ya anulada.'); header('Location: index.php'); exit; }
// Devolver stock
$det = $db->prepare("SELECT vd.*,p.tipo FROM venta_detalle vd JOIN productos p ON p.id=vd.producto_id WHERE vd.venta_id=?");
$det->execute([$id]);
foreach ($det->fetchAll() as $d) if ($d['tipo']==='estandar') $db->prepare("UPDATE productos SET stock=stock+? WHERE id=?")->execute([$d['cantidad'],$d['producto_id']]);
$db->prepare("UPDATE ventas SET estado='anulada' WHERE id=?")->execute([$id]);
flashMessage('success','Venta anulada.'); header('Location: view.php?id='.$id); exit;
