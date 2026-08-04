<?php
require_once __DIR__ . '/../config/config.php';

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRol(...$roles) {
    requireLogin();
    if (!in_array($_SESSION['rol_id'], $roles)) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

function currentUser() {
    return [
        'id'          => $_SESSION['usuario_id']   ?? null,
        'nombre'      => $_SESSION['nombre']        ?? '',
        'apellido'    => $_SESSION['apellido']       ?? '',
        'rol_id'      => $_SESSION['rol_id']         ?? null,
        'sucursal_id' => $_SESSION['sucursal_id']    ?? null,
    ];
}

function isAdmin() {
    return ($_SESSION['rol_id'] ?? 0) === ROL_ADMIN;
}

// Si la sesión no tiene sucursal_id, recargar desde BD
function refreshSession() {
    if (isset($_SESSION['usuario_id']) && empty($_SESSION['sucursal_id'])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, s.nombre as sucursal_nombre FROM usuarios u JOIN sucursales s ON s.id=u.sucursal_id WHERE u.id=? LIMIT 1");
        $stmt->execute([$_SESSION['usuario_id']]);
        $u = $stmt->fetch();
        if ($u) {
            $_SESSION['sucursal_id']     = $u['sucursal_id'];
            $_SESSION['sucursal_nombre'] = $u['sucursal_nombre'];
            $_SESSION['rol_id']          = $u['rol_id'];
        }
    }
}
