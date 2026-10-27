<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/empresa.php';
requireLogin();
refreshSession();
exigirEmpresaActiva();
$user  = currentUser();

// Configuración visual de la sucursal
$_sucConfig = [];
if (!empty($user['sucursal_id'])) {
    $_sucStmt = getDB()->prepare("SELECT * FROM sucursales WHERE id=?");
    $_sucStmt->execute([$user['sucursal_id']]);
    $_sucConfig = $_sucStmt->fetch() ?: [];
}
$_colorPrim = $_sucConfig['color_primario']   ?? '#1d4ed8';
$_colorSec  = $_sucConfig['color_secundario'] ?? '#1e40af';
$_logoPath  = $_sucConfig['logo_path'] ?? null;
// Logo de marca incluido en el sistema; la sucursal puede sustituirlo
$_logoMarca      = BASE_URL . '/assets/brand/logo-light.png';   // para fondo oscuro
$_simboloMarca   = BASE_URL . '/assets/brand/simbolo.png';
$_appName   = $_sucConfig['nombre']    ?? APP_NAME;
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
    <!-- Aplicación instalable -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <meta name="theme-color" content="<?= $_colorPrim ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= sanitize($_appName) ?>">
    <link rel="icon" href="<?= BASE_URL ?>/assets/icons/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/icons/icon-32.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/icon-180.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>document.addEventListener("alpine:init",()=>{const s=document.createElement("style");s.textContent="[x-cloak]{display:none!important}";document.head.appendChild(s);});</script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
    :root{
        --cp: <?= $_colorPrim ?>;
        --cs: <?= $_colorSec ?>;
        --bg:        #f6f8fb;
        --surface:   #ffffff;
        --line:      #e3e8ef;
        --line-soft: #eef1f6;
        --ink:       #0f172a;
        --ink-2:     #475467;
        --ink-3:     #667085;
        --ink-4:     #98a2b3;
        --radius:    10px;
    }

    *{ -webkit-tap-highlight-color: transparent; }
    /* Nada se anima durante la carga de la página */
    #sidebar, #main{ transition: none; }
    #sidebar{ height:100vh; height:100dvh; }
    #sidebar nav{ -webkit-overflow-scrolling: touch; }
    body.ready #sidebar, body.ready #main{
        transition: width .2s ease, margin .2s ease, transform .2s ease;
    }
    [x-cloak]{ display:none !important; }

    html{ -webkit-text-size-adjust:100%; }
    body{
        font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
        background: var(--bg);
        color: var(--ink);
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
        font-feature-settings: 'cv05' 1, 'cv11' 1;
    }

    /* Cifras alineadas: lo que hace legible una columna de dinero */
    table, .tabular, input[type=number]{ font-variant-numeric: tabular-nums; }

    /* ---------- Tipografía de encabezados ---------- */
    h1{ letter-spacing:-.021em; font-weight:650; color:var(--ink); }
    h2{ letter-spacing:-.014em; }

    /* ---------- Superficies ---------- */
    .bg-white{ background: var(--surface) !important; }
    .shadow, .shadow-sm{ box-shadow: none !important; }
    .bg-white.rounded-xl,
    .bg-white.rounded-2xl{
        border: 1px solid var(--line);
        border-radius: 14px;
    }
    .rounded-lg{ border-radius: var(--radius); }
    .shadow-xl{ box-shadow: 0 16px 48px -12px rgba(15,23,42,.24) !important; }

    /* ---------- Tablas ---------- */
    table{ width:100%; border-collapse: collapse; }
    thead{ background: #fbfcfe !important; }
    thead th{
        font-size:11px; font-weight:600; letter-spacing:.04em;
        color: var(--ink-4) !important;
        text-transform: uppercase;
        padding: 11px 16px !important;
        border-bottom:1px solid var(--line);
        white-space: nowrap;
    }
    tbody td{
        padding: 13px 16px !important;
        font-size: 13.5px;
        color: var(--ink-2);
        border-bottom: 1px solid var(--line-soft);
        vertical-align: middle;
    }
    tbody tr:last-child td{ border-bottom: 0; }
    tbody tr{ transition: background .12s ease; }
    tbody tr:hover{ background: #f9fafc; }
    tfoot td{ padding: 14px 16px !important; border-top:1px solid var(--line); }
    .divide-y > :not([hidden]) ~ :not([hidden]){ border-color: var(--line-soft) !important; }

    /* Cualquier tabla se desplaza sola en pantallas chicas */
    @media (max-width: 1023px){
        table{ display:block; overflow-x:auto; white-space:nowrap; }
        thead th, tbody td{ padding-left:12px !important; padding-right:12px !important; }
    }

    /* ---------- Formularios ---------- */
    input[type=text],input[type=number],input[type=email],input[type=password],
    input[type=date],input[type=search],select,textarea{
        background: var(--surface);
        border:1px solid var(--line) !important;
        border-radius: var(--radius) !important;
        color: var(--ink);
        font-size: 13.5px;
        transition: border-color .14s ease, box-shadow .14s ease;
    }
    input:focus,select:focus,textarea:focus{
        outline: none !important;
        border-color: var(--cp) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--cp) 14%, transparent) !important;
    }
    input::placeholder, textarea::placeholder{ color: var(--ink-4); }
    label{ color: var(--ink-2); }
    select{
        appearance:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2398a2b3' stroke-width='2.5' stroke-linecap='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat:no-repeat;
        background-position:right 11px center;
        padding-right: 32px !important;
    }

    /* ---------- Botones ---------- */
    button, .btn, a[class*="bg-"][class*="text-white"]{ transition: all .14s ease; }
    .bg-blue-700, .bg-blue-600{ background-color: var(--cp) !important; }
    .hover\:bg-blue-800:hover, .hover\:bg-blue-700:hover{
        background-color: var(--cs) !important;
    }
    button:active, a[class*="bg-"]:active{ transform: translateY(1px); }
    .text-blue-700, .text-blue-600{ color: var(--cp) !important; }
    .bg-blue-100{ background-color: color-mix(in srgb, var(--cp) 11%, white) !important; }
    .focus\:ring-blue-500:focus{ --tw-ring-color: var(--cp) !important; }

    /* ---------- Estados: píldora con punto ---------- */
    span[class*="rounded-full"][class*="text-xs"]{
        display:inline-flex; align-items:center; gap:6px;
        font-weight:500; letter-spacing:.005em;
        padding: 3px 10px 3px 8px !important;
        border:1px solid transparent;
    }
    span[class*="rounded-full"][class*="text-xs"]::before{
        content:''; width:5px; height:5px; border-radius:50%;
        background: currentColor; opacity:.85; flex-shrink:0;
    }
    span[class*="rounded-full"][class*="text-xs"] i{ display:none; }
    .bg-green-100{ background:#ecfdf3 !important; } .text-green-700{ color:#067647 !important; }
    .bg-red-100  { background:#fef3f2 !important; } .text-red-700  { color:#b42318 !important; }
    .bg-yellow-100{background:#fffaeb !important; } .text-yellow-700{color:#b54708 !important; }
    .bg-gray-100 { background:#f2f4f7 !important; } .text-gray-600 { color:#475467 !important; }
    .bg-teal-100 { background:#eefaf7 !important; } .text-teal-700 { color:#0e7066 !important; }
    .bg-purple-100{background:#f6f2ff !important; } .text-purple-700{color:#6938ef !important; }

    /* ---------- Sidebar ---------- */
    aside{
        background: color-mix(in srgb, var(--cp) 16%, #0b1220) !important;
        border-right: 1px solid rgba(255,255,255,.07);
    }
    aside .border-blue-800{ border-color: rgba(255,255,255,.09) !important; }
    aside nav a{
        font-weight:450;
        color: rgba(255,255,255,.62) !important;
        border-radius: 9px;
        position: relative;
    }
    aside nav a:hover{
        background: rgba(255,255,255,.07) !important;
        color: #fff !important;
    }
    aside nav a.is-active{
        background: var(--cp) !important;
        color:#fff !important;
        font-weight:550;
    }
    aside nav a.is-active i{ color:#fff !important; }
    aside nav a i{ color: rgba(255,255,255,.5); font-size:13px; }
    header.topbar{
        background: color-mix(in srgb, var(--cp) 16%, #0b1220) !important;
    }

    /* ---------- Scrollbar ---------- */
    ::-webkit-scrollbar{ width:9px; height:9px; }
    ::-webkit-scrollbar-track{ background:transparent; }
    ::-webkit-scrollbar-thumb{ background:#cfd6e2; border-radius:99px; border:2px solid var(--bg); }
    ::-webkit-scrollbar-thumb:hover{ background:#b4bdcd; }
    aside ::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.16); border-color:transparent; }

    /* ---------- Varios ---------- */
    a{ text-decoration:none; }
    .text-gray-500{ color: var(--ink-3) !important; }
    .text-gray-400{ color: var(--ink-4) !important; }
    .text-gray-800, .text-gray-700{ color: var(--ink) !important; }
    .border-gray-200, .border-gray-300{ border-color: var(--line) !important; }
    .bg-gray-50{ background:#fbfcfe !important; }
    .font-mono{ font-feature-settings:'tnum' 1; letter-spacing:-.01em; }

    /* Búsqueda en vivo */
    input[type=search]{
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%2398a2b3' stroke-width='2.2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='M20 20l-3.5-3.5'/%3E%3C/svg%3E");
        background-repeat:no-repeat;
        background-position:left 12px center;
        padding-left: 36px !important;
    }
    input[type=search]::-webkit-search-cancel-button{
        -webkit-appearance:none; appearance:none;
        width:14px; height:14px; cursor:pointer;
        background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2398a2b3' stroke-width='2.5' stroke-linecap='round'%3E%3Cpath d='M18 6L6 18M6 6l12 12'/%3E%3C/svg%3E") no-repeat center;
    }
    /* El botón de lupa se oculta: la búsqueda ya corre sola */
    .search-indicator{ display:none !important; }
    form.is-searching input[type=search]{
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%2398a2b3' stroke-width='2.4' stroke-linecap='round'%3E%3Cpath d='M12 3a9 9 0 1 0 9 9' /%3E%3C/svg%3E");
        animation: none;
    }
    form.is-searching input[type=search]{ opacity:.7; }
    form.is-searching input[name="q"]{ opacity:.65; }
    @keyframes spin{ to{ transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce){
        *{ transition:none !important; animation:none !important; }
    }
    </style>
</head>
<body x-data="{ sidebarOpen: window.innerWidth >= 1024, mobileOpen: false }"
      @resize.window="sidebarOpen = window.innerWidth >= 1024"
      :class="mobileOpen ? 'overflow-hidden lg:overflow-auto' : ''">

<?php if (!empty($_SESSION['suplantando'])): ?>
<!-- Aviso: el superadministrador está trabajando dentro de una empresa -->
<div class="fixed top-0 left-0 right-0 z-50 bg-amber-500 text-amber-950 text-[12.5px] font-medium
            px-4 py-2 flex items-center justify-center gap-3 flex-wrap">
    <span class="flex items-center gap-2">
        <i class="fas fa-user-shield"></i>
        Estás dentro de <strong><?= sanitize($_SESSION['empresa_nombre'] ?? '') ?></strong> como administrador maestro
    </span>
    <a href="<?= BASE_URL ?>/master/salir.php"
       class="underline underline-offset-2 hover:no-underline font-semibold whitespace-nowrap">
        Volver al panel maestro
    </a>
</div>
<style>
    body{ padding-top:34px; }
    #sidebar{ top:34px !important; height:calc(100dvh - 34px) !important; }
</style>
<?php endif; ?>

<!-- Overlay móvil -->
<div x-show="mobileOpen" x-cloak @click="mobileOpen=false"
     class="fixed inset-0 bg-slate-900/50 backdrop-blur-[2px] z-30 lg:hidden"></div>

<!-- SIDEBAR -->
<aside id="sidebar"
       class="fixed top-0 left-0 h-screen w-64 text-white z-40 flex flex-col
              -translate-x-full lg:translate-x-0"
       style="height:100dvh"
       :class="[
           mobileOpen ? 'translate-x-0' : '-translate-x-full',
           sidebarOpen ? 'lg:w-64' : 'lg:w-[68px]'
       ]">

    <!-- Marca -->
    <div class="flex items-center gap-2.5 px-4 h-16 border-b border-blue-800 flex-shrink-0">
        <?php if ($_logoPath): ?>
            <img src="<?= BASE_URL ?>/<?= sanitize($_logoPath) ?>" alt=""
                 class="w-8 h-8 object-contain rounded-md flex-shrink-0 bg-white/10 p-0.5">
            <span x-show="sidebarOpen || mobileOpen"
                  class="text-[13.5px] font-semibold truncate tracking-tight"><?= sanitize($_appName) ?></span>
        <?php else: ?>
            <img src="<?= $_simboloMarca ?>" alt=""
                 x-show="!sidebarOpen && !mobileOpen" x-cloak
                 class="w-8 h-8 object-contain flex-shrink-0">
            <img src="<?= $_logoMarca ?>" alt="<?= sanitize($_appName) ?>"
                 x-show="sidebarOpen || mobileOpen"
                 class="h-7 w-auto object-contain object-left">
        <?php endif; ?>
        <button @click="sidebarOpen = !sidebarOpen"
                class="hidden lg:flex ml-auto w-6 h-6 items-center justify-center rounded-md text-white/40 hover:text-white hover:bg-white/10"
                :title="sidebarOpen ? 'Contraer menú' : 'Expandir menú'">
            <i :class="sidebarOpen ? 'fas fa-angles-left' : 'fas fa-angles-right'" class="text-[11px]"></i>
        </button>
        <button @click="mobileOpen=false"
                class="lg:hidden ml-auto w-7 h-7 flex items-center justify-center rounded-md text-white/50 hover:text-white hover:bg-white/10">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden overscroll-contain py-3 px-2.5 space-y-0.5">
        <?php
        $links = [
            ['dashboard',  '/',                   'fa-gauge-high',    'Inicio'],
            ['ordenes',    '/pages/ordenes/',     'fa-clipboard-list','Órdenes'],
            ['clientes',   '/pages/clientes/',    'fa-users',         'Clientes'],
            ['motos',      '/pages/motos/',       'fa-motorcycle',    'Motocicletas'],
            ['productos',  '/pages/productos/',   'fa-box',           'Productos'],
            ['ventas',     '/pages/ventas/',      'fa-cash-register', 'Ventas'],
            ['compras',    '/pages/compras/',     'fa-truck',         'Compras'],
            ['proveedores','/pages/proveedores/', 'fa-industry',      'Proveedores'],
            ['reportes',   '/pages/reportes/',    'fa-chart-simple',  'Reportes'],
        ];
        foreach ($links as [$dir, $path, $icon, $label]): ?>
        <a href="<?= BASE_URL . $path ?>index.php" @click="mobileOpen=false"
           class="flex items-center gap-3 px-2.5 py-2 text-[13.5px] <?= navActive($dir) ? 'is-active' : '' ?>"
           title="<?= $label ?>">
            <i class="fas <?= $icon ?> w-5 text-center flex-shrink-0"></i>
            <span x-show="sidebarOpen || mobileOpen" class="truncate"><?= $label ?></span>
        </a>
        <?php endforeach; ?>

        <?php if (isAdmin()): ?>
        <div class="pt-3 pb-1 px-2.5" x-show="sidebarOpen || mobileOpen">
            <div class="h-px bg-white/10 mb-3"></div>
            <span class="text-[10.5px] font-medium text-white/35 tracking-wide">Administración</span>
        </div>
        <div class="h-px bg-white/10 my-3 mx-1" x-show="!sidebarOpen && !mobileOpen" x-cloak></div>
        <?php
        $adminLinks = [
            ['usuarios',     '/pages/usuarios/',      'fa-user-gear',          'Usuarios'],
            ['sucursales',   '/pages/sucursales/',    'fa-store',              'Sucursales'],
            ['configuracion','/pages/configuracion/', 'fa-sliders',            'Configuración'],
            ['fel',          '/pages/fel/',           'fa-file-invoice-dollar','Facturación FEL'],
        ];
        foreach ($adminLinks as [$dir, $path, $icon, $label]): ?>
        <a href="<?= BASE_URL . $path ?>index.php" @click="mobileOpen=false"
           class="flex items-center gap-3 px-2.5 py-2 text-[13.5px] <?= navActive($dir) ? 'is-active' : '' ?>"
           title="<?= $label ?>">
            <i class="fas <?= $icon ?> w-5 text-center flex-shrink-0"></i>
            <span x-show="sidebarOpen || mobileOpen" class="truncate"><?= $label ?></span>
        </a>
        <?php endforeach; endif; ?>
    </nav>

    <!-- Usuario -->
    <div class="border-t border-blue-800 p-2.5 flex-shrink-0">
        <div class="flex items-center gap-2.5 px-1.5 py-1.5">
            <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-[12px] font-semibold flex-shrink-0">
                <?= strtoupper(substr($user['nombre'],0,1) . substr($user['apellido'],0,1)) ?>
            </div>
            <div x-show="sidebarOpen || mobileOpen" class="min-w-0 flex-1">
                <div class="text-[12.5px] font-medium truncate leading-tight"><?= sanitize($user['nombre'] . ' ' . $user['apellido']) ?></div>
                <div class="text-[11px] text-white/40 truncate"><?= sanitize($ROLES[$user['rol_id']] ?? '') ?></div>
            </div>
        </div>

        <!-- Instalar la app (solo si el navegador lo ofrece) -->
        <button id="btn-instalar" type="button" hidden
                class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13px]
                       text-white/60 hover:text-white hover:bg-white/10 transition-colors">
            <i class="fas fa-circle-down w-5 text-center flex-shrink-0 text-[12px]"></i>
            <span x-show="sidebarOpen || mobileOpen">Instalar app</span>
        </button>

        <!-- Cerrar sesión -->
        <a href="<?= BASE_URL ?>/logout.php" title="Cerrar sesión"
           class="mt-1.5 flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13px]
                  text-white/60 hover:text-white hover:bg-white/10 transition-colors">
            <i class="fas fa-arrow-right-from-bracket w-5 text-center flex-shrink-0 text-[12px]"></i>
            <span x-show="sidebarOpen || mobileOpen">Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- Barra superior móvil -->
<header class="topbar lg:hidden fixed top-0 left-0 right-0 h-14 text-white z-20 flex items-center px-3 gap-3">
    <button @click="mobileOpen=true" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-white/10">
        <i class="fas fa-bars"></i>
    </button>
    <?php if ($_logoPath): ?>
        <img src="<?= BASE_URL ?>/<?= sanitize($_logoPath) ?>" alt="" class="w-7 h-7 object-contain rounded bg-white/10 p-0.5">
        <span class="font-semibold text-[14px] truncate tracking-tight"><?= sanitize($_appName) ?></span>
    <?php else: ?>
        <img src="<?= $_logoMarca ?>" alt="<?= sanitize($_appName) ?>" class="h-6 w-auto object-contain">
    <?php endif; ?>
    <div class="ml-auto w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-[11px] font-semibold">
        <?= strtoupper(substr($user['nombre'],0,1) . substr($user['apellido'],0,1)) ?>
    </div>
</header>

<!-- CONTENIDO -->
<main id="main" :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-[68px]'"
      class="lg:ml-64 min-h-screen pt-[72px] lg:pt-8 px-4 pb-10 lg:px-8 lg:pb-12">

    <?php if ($flash): ?>
    <div class="mb-5 flex items-start gap-3 px-4 py-3 rounded-xl text-[13.5px] border
        <?= $flash['type']==='success'
            ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
            : 'bg-red-50 border-red-200 text-red-800' ?>">
        <i class="fas fa-<?= $flash['type']==='success'?'circle-check':'circle-exclamation' ?> mt-0.5 flex-shrink-0"></i>
        <span><?= sanitize($flash['message']) ?></span>
    </div>
    <?php endif; ?>
