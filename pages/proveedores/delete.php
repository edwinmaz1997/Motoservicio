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
$id = (int)($_GET['id'] ?? 0);
$check = $db->prepare("SELECT COUNT(*) FROM compras WHERE proveedor_id = ?");
$check->execute([$id]);
if ($check->fetchColumn() > 0) {
    flashMessage('error','No se puede eliminar: el proveedor tiene compras registradas.');
    header('Location: index.php'); exit;
}
$db->prepare("DELETE FROM proveedores WHERE id = ? AND empresa_id = ?")->execute([$id, empresaActual()]);
flashMessage('success','Proveedor eliminado.');
header('Location: index.php'); exit;
