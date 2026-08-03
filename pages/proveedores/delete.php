<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$check = $db->prepare("SELECT COUNT(*) FROM compras WHERE proveedor_id = ?");
$check->execute([$id]);
if ($check->fetchColumn() > 0) {
    flashMessage('error','No se puede eliminar: el proveedor tiene compras registradas.');
    header('Location: index.php'); exit;
}
$db->prepare("DELETE FROM proveedores WHERE id = ?")->execute([$id]);
flashMessage('success','Proveedor eliminado.');
header('Location: index.php'); exit;
