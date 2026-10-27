<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/empresa.php';
requireLogin();
refreshSession();
$user = currentUser();
requireRol(ROL_ADMIN);
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id === $user['id']) { flashMessage('error','No puedes desactivarte a ti mismo.'); header('Location: index.php'); exit; }
$stmt = $db->prepare("SELECT u.activo FROM usuarios u JOIN sucursales s ON s.id=u.sucursal_id WHERE u.id=? AND s.empresa_id=?");
$stmt->execute([$id, empresaActual()]);
$u = $stmt->fetch();
if (!$u) { flashMessage('error','Usuario no encontrado.'); header('Location: index.php'); exit; }
$nuevo = $u['activo'] ? 0 : 1;
$db->prepare("UPDATE usuarios SET activo=? WHERE id=?")->execute([$nuevo,$id]);
flashMessage('success', $nuevo ? 'Usuario activado.' : 'Usuario desactivado.');
header('Location: index.php'); exit;
