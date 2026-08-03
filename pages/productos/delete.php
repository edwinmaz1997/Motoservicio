<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB(); $id = (int)($_GET['id'] ?? 0);
$check = $db->prepare("SELECT (SELECT COUNT(*) FROM orden_detalle WHERE producto_id=?) + (SELECT COUNT(*) FROM venta_detalle WHERE producto_id=?) + (SELECT COUNT(*) FROM compra_detalle WHERE producto_id=?) as total");
$check->execute([$id,$id,$id]);
if ($check->fetchColumn() > 0) { flashMessage('error','No se puede eliminar: el producto tiene movimientos registrados.'); header('Location: index.php'); exit; }
$db->prepare("DELETE FROM productos WHERE id=?")->execute([$id]);
flashMessage('success','Producto eliminado.'); header('Location: index.php'); exit;
