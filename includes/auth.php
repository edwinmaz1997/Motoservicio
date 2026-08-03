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
