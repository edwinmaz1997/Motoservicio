<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT id FROM clientes WHERE id = ? AND sucursal_id = ?");
$stmt->execute([$id, $user['sucursal_id']]);
if (!$stmt->fetch()) { flashMessage('error','Cliente no encontrado.'); header('Location: index.php'); exit; }

// Verificar si tiene órdenes
$check = $db->prepare("SELECT COUNT(*) FROM ordenes WHERE cliente_id = ?");
$check->execute([$id]);
if ($check->fetchColumn() > 0) {
    flashMessage('error','No se puede eliminar: el cliente tiene órdenes registradas.');
    header('Location: index.php'); exit;
}

$db->prepare("DELETE FROM motocicletas WHERE cliente_id = ?")->execute([$id]);
$db->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
flashMessage('success','Cliente eliminado.');
header('Location: index.php'); exit;
