<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();
$user  = currentUser();
$flash = getFlash();
global $ROLES;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link.active { @apply bg-blue-700 text-white; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 font-sans" x-data="{ sidebarOpen: true }">

<!-- SIDEBAR -->
<aside :class="sidebarOpen ? 'w-64' : 'w-16'"
       class="fixed top-0 left-0 h-screen bg-blue-900 text-white transition-all duration-300 z-40 flex flex-col">

    <!-- Logo -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-blue-800">
        <span x-show="sidebarOpen" class="text-xl font-bold tracking-wide">🏍️ DIAZ</span>
        <button @click="sidebarOpen = !sidebarOpen" class="text-blue-300 hover:text-white">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-4 space-y-1 px-2">

        <a href="<?= BASE_URL ?>/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt w-5 text-center"></i>
            <span x-show="sidebarOpen">Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/ordenes/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-clipboard-list w-5 text-center"></i>
            <span x-show="sidebarOpen">Órdenes</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/clientes/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-users w-5 text-center"></i>
            <span x-show="sidebarOpen">Clientes</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/motos/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-motorcycle w-5 text-center"></i>
            <span x-show="sidebarOpen">Motocicletas</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/productos/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-box w-5 text-center"></i>
            <span x-show="sidebarOpen">Productos</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/ventas/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-cash-register w-5 text-center"></i>
            <span x-show="sidebarOpen">Ventas</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/compras/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-truck w-5 text-center"></i>
            <span x-show="sidebarOpen">Compras</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/proveedores/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-industry w-5 text-center"></i>
            <span x-show="sidebarOpen">Proveedores</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/reportes/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-chart-bar w-5 text-center"></i>
            <span x-show="sidebarOpen">Reportes</span>
        </a>

        <?php if (isAdmin()): ?>
        <hr class="border-blue-800 my-2">
        <a href="<?= BASE_URL ?>/pages/usuarios/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-user-cog w-5 text-center"></i>
            <span x-show="sidebarOpen">Usuarios</span>
        </a>
        <a href="<?= BASE_URL ?>/pages/sucursales/index.php"
           class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-700 transition sidebar-link">
            <i class="fas fa-store w-5 text-center"></i>
            <span x-show="sidebarOpen">Sucursales</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- User info -->
    <div class="border-t border-blue-800 px-4 py-3">
        <div x-show="sidebarOpen" class="text-sm text-blue-300">
            <div class="font-semibold text-white"><?= sanitize($user['nombre'] . ' ' . $user['apellido']) ?></div>
            <div><?= sanitize($ROLES[$user['rol_id']] ?? 'Sin rol') ?></div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="mt-2 flex items-center gap-2 text-red-400 hover:text-red-300 text-sm">
            <i class="fas fa-sign-out-alt"></i>
            <span x-show="sidebarOpen">Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main :class="sidebarOpen ? 'ml-64' : 'ml-16'" class="transition-all duration-300 min-h-screen p-6">

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300' ?>">
        <?= sanitize($flash['message']) ?>
    </div>
    <?php endif; ?>
