<?php
require_once __DIR__ . '/../../includes/header.php';
requireRol(ROL_ADMIN);
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id === $user['id']) { flashMessage('error','No puedes desactivarte a ti mismo.'); header('Location: index.php'); exit; }
$stmt = $db->prepare("SELECT activo FROM usuarios WHERE id=?");
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) { flashMessage('error','Usuario no encontrado.'); header('Location: index.php'); exit; }
$nuevo = $u['activo'] ? 0 : 1;
$db->prepare("UPDATE usuarios SET activo=? WHERE id=?")->execute([$nuevo,$id]);
flashMessage('success', $nuevo ? 'Usuario activado.' : 'Usuario desactivado.');
header('Location: index.php'); exit;
