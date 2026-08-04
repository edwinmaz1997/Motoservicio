<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();
$user  = currentUser();
$flash = getFlash();
global $ROLES;
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function navActive($dir) {
    global $currentDir, $currentFile;
    if ($dir === 'dashboard') return $currentFile === 'index.php' && $currentDir !== 'pages';
    return $currentDir === $dir;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>document.addEventListener("alpine:init",()=>{const s=document.createElement("style");s.textContent="[x-cloak]{display:none!important}";document.head.appendChild(s);});</script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        body { overflow-x: hidden; }
    </style>
</head>
<body class="bg-gray-100 font-sans" x-data="{ sidebarOpen: window.innerWidth >= 1024, mobileOpen: false }" @resize.window="sidebarOpen = window.innerWidth >= 1024">

<!-- OVERLAY mobile -->
<div x-show="mobileOpen" x-cloak @click="mobileOpen=false"
     class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden"></div>

<!-- SIDEBAR -->
<aside
       :class="[
           'fixed top-0 left-0 h-screen bg-blue-900 text-white z-40 flex flex-col transition-all duration-300',
           'lg:translate-x-0',
           mobileOpen ? 'translate-x-0 w-64' : '-translate-x-full w-64',
           sidebarOpen ? 'lg:w-64' : 'lg:w-16'
       ]">

    <!-- Logo -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-blue-800 min-h-[64px]">
        <span x-show="sidebarOpen || mobileOpen" class="text-xl font-bold tracking-wide truncate">🏍️ DIAZ</span>
        <span x-show="!sidebarOpen && !mobileOpen" class="text-xl">🏍️</span>
        <button @click="sidebarOpen = !sidebarOpen" class="text-blue-300 hover:text-white hidden lg:block ml-auto">
            <i :class="sidebarOpen ? 'fas fa-chevron-left' : 'fas fa-chevron-right'"></i>
        </button>
        <button @click="mobileOpen=false" class="text-blue-300 hover:text-white lg:hidden">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Nav links -->
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
        <?php
        $links = [
            ['dashboard', '/',           'fa-tachometer-alt', 'Dashboard'],
            ['ordenes',   '/pages/ordenes/',   'fa-clipboard-list','Órdenes'],
            ['clientes',  '/pages/clientes/',  'fa-users',         'Clientes'],
            ['motos',     '/pages/motos/',     'fa-motorcycle',    'Motocicletas'],
            ['productos', '/pages/productos/', 'fa-box',           'Productos'],
            ['ventas',    '/pages/ventas/',    'fa-cash-register', 'Ventas'],
            ['compras',   '/pages/compras/',   'fa-truck',         'Compras'],
            ['proveedores','/pages/proveedores/','fa-industry',    'Proveedores'],
            ['reportes',  '/pages/reportes/',  'fa-chart-bar',     'Reportes'],
        ];
        foreach ($links as [$dir, $path, $icon, $label]):
            $active = navActive($dir) ? 'bg-blue-700 text-white' : 'text-blue-200 hover:bg-blue-800 hover:text-white';
        ?>
        <a href="<?= BASE_URL . $path ?>index.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $active ?>"
           @click="mobileOpen=false">
            <i class="fas <?= $icon ?> w-5 text-center flex-shrink-0 text-sm"></i>
            <span x-show="sidebarOpen || mobileOpen" class="text-sm truncate"><?= $label ?></span>
        </a>
        <?php endforeach; ?>

        <?php if (isAdmin()): ?>
        <div class="border-t border-blue-800 my-2"></div>
        <?php
        $adminLinks = [
            ['usuarios',   '/pages/usuarios/',   'fa-user-cog', 'Usuarios'],
            ['sucursales', '/pages/sucursales/', 'fa-store',    'Sucursales'],
        ];
        foreach ($adminLinks as [$dir, $path, $icon, $label]):
            $active = navActive($dir) ? 'bg-blue-700 text-white' : 'text-blue-200 hover:bg-blue-800 hover:text-white';
        ?>
        <a href="<?= BASE_URL . $path ?>index.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $active ?>"
           @click="mobileOpen=false">
            <i class="fas <?= $icon ?> w-5 text-center flex-shrink-0 text-sm"></i>
            <span x-show="sidebarOpen || mobileOpen" class="text-sm truncate"><?= $label ?></span>
        </a>
        <?php endforeach; endif; ?>
    </nav>

    <!-- User -->
    <div class="border-t border-blue-800 px-3 py-3">
        <div x-show="sidebarOpen || mobileOpen" class="text-xs text-blue-300 mb-2 truncate">
            <div class="font-semibold text-white truncate"><?= sanitize($user['nombre'] . ' ' . $user['apellido']) ?></div>
            <div><?= sanitize($ROLES[$user['rol_id']] ?? '') ?></div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php"
           class="flex items-center gap-2 text-red-400 hover:text-red-300 text-sm px-1">
            <i class="fas fa-sign-out-alt flex-shrink-0"></i>
            <span x-show="sidebarOpen || mobileOpen">Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- TOPBAR mobile -->
<header class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-blue-900 text-white z-20 flex items-center px-4 gap-3">
    <button @click="mobileOpen=true" class="text-white text-xl">
        <i class="fas fa-bars"></i>
    </button>
    <span class="font-bold text-lg">🏍️ DIAZ</span>
    <div class="ml-auto text-sm text-blue-300"><?= sanitize($user['nombre']) ?></div>
</header>

<!-- MAIN -->
<main :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-16'"
      class="transition-all duration-300 min-h-screen pt-16 lg:pt-0 px-3 py-4 lg:px-6 lg:py-6">

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
        <?= $flash['type']==='success' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300' ?>">
        <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?> mr-2"></i>
        <?= sanitize($flash['message']) ?>
    </div>
    <?php endif; ?>
