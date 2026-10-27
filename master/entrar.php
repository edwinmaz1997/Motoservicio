<?php
/** El superadministrador entra a trabajar dentro de una empresa. */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/empresa.php';
requireLogin();
requireSuper();

$id = (int)($_GET['id'] ?? 0);

if (entrarAEmpresa($id)) {
    header('Location: ' . BASE_URL . '/index.php');
} else {
    flashMessage('error','No se pudo entrar: la empresa no existe o no tiene sucursales.');
    header('Location: index.php');
}
exit;
