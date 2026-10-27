<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/empresa.php';
requireLogin();
refreshSession();
$user = currentUser();
$db = getDB(); $id = (int)($_GET['id'] ?? 0);
$check = $db->prepare("SELECT (SELECT COUNT(*) FROM orden_detalle WHERE producto_id=?) + (SELECT COUNT(*) FROM venta_detalle WHERE producto_id=?) + (SELECT COUNT(*) FROM compra_detalle WHERE producto_id=?) as total");
$check->execute([$id,$id,$id]);
if ($check->fetchColumn() > 0) { flashMessage('error','No se puede eliminar: el producto tiene movimientos registrados.'); header('Location: index.php'); exit; }
$db->prepare("DELETE FROM productos WHERE id=? AND empresa_id=?")->execute([$id, empresaActual()]);
flashMessage('success','Producto eliminado.'); header('Location: index.php'); exit;
