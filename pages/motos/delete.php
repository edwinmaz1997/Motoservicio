<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$check = $db->prepare("SELECT COUNT(*) FROM ordenes WHERE motocicleta_id=?");
$check->execute([$id]);
if ($check->fetchColumn() > 0) { flashMessage('error','No se puede eliminar: tiene órdenes registradas.'); header('Location: index.php'); exit; }
$db->prepare("DELETE FROM motocicletas WHERE id=?")->execute([$id]);
flashMessage('success','Motocicleta eliminada.');
header('Location: index.php'); exit;
