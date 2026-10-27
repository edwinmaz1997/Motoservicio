<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/empresa.php';
requireLogin();
requireSuper();

$flash = getFlash();
$_pag  = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'Panel maestro') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= BASE_URL ?>/assets/icons/favicon.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{ font-family:'Inter',system-ui,sans-serif; background:#0b1220; color:#e2e8f0; -webkit-font-smoothing:antialiased; }
        .tabular{ font-variant-numeric: tabular-nums; }
        .panel{ background:#111a2e; border:1px solid rgba(255,255,255,.08); }
        table{ width:100%; border-collapse:collapse; }
        thead th{
            font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase;
            color:#64748b; padding:11px 16px; text-align:left;
            border-bottom:1px solid rgba(255,255,255,.08); white-space:nowrap;
        }
        tbody td{ padding:13px 16px; font-size:13.5px; border-bottom:1px solid rgba(255,255,255,.05); }
        tbody tr:last-child td{ border-bottom:0; }
        tbody tr:hover{ background:rgba(255,255,255,.025); }
        input,select,textarea{
            background:#0b1220; border:1px solid rgba(255,255,255,.12); border-radius:9px;
            color:#e2e8f0; font-size:13.5px; padding:9px 12px; width:100%;
        }
        input:focus,select:focus,textarea:focus{
            outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.18);
        }
        label{ font-size:12px; color:#94a3b8; display:block; margin-bottom:5px; }
        ::-webkit-scrollbar{ width:9px; height:9px; }
        ::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.14); border-radius:99px; }
        @media(max-width:900px){ table{ display:block; overflow-x:auto; white-space:nowrap; } }
        @media (prefers-reduced-motion: reduce){ *{transition:none!important} }
    </style>
</head>
<body class="min-h-screen">

<header class="border-b border-white/10 bg-[#0e1626] sticky top-0 z-20">
    <div class="max-w-6xl mx-auto px-5 h-14 flex items-center gap-5">
        <a href="<?= BASE_URL ?>/master/index.php" class="flex items-center gap-2.5 flex-shrink-0">
            <img src="<?= BASE_URL ?>/assets/brand/simbolo.png" alt="" class="w-7 h-7 object-contain">
            <span class="font-semibold text-[14px] tracking-tight">Panel maestro</span>
        </a>

        <nav class="flex items-center gap-1 ml-2 text-[13px] overflow-x-auto">
            <?php
            $nav = [
                'index.php'    => ['Empresas', 'fa-building'],
                'nueva.php'    => ['Nueva',    'fa-plus'],
                'bitacora.php' => ['Bitácora', 'fa-clock-rotate-left'],
            ];
            foreach ($nav as $archivo => [$txt,$ic]):
                $act = $_pag === $archivo;
            ?>
            <a href="<?= BASE_URL ?>/master/<?= $archivo ?>"
               class="px-3 py-1.5 rounded-lg whitespace-nowrap <?= $act ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white hover:bg-white/5' ?>">
                <i class="fas <?= $ic ?> text-[11px] mr-1.5"></i><?= $txt ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="ml-auto flex items-center gap-3 flex-shrink-0">
            <span class="text-[12.5px] text-slate-400 hidden sm:block"><?= sanitize($_SESSION['nombre']) ?></span>
            <a href="<?= BASE_URL ?>/logout.php" title="Cerrar sesión"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10">
                <i class="fas fa-arrow-right-from-bracket text-[12px]"></i>
            </a>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-5 py-7">
    <?php if ($flash): ?>
    <div class="mb-5 flex items-start gap-3 px-4 py-3 rounded-xl text-[13.5px] border
        <?= $flash['type']==='success'
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
            : 'bg-red-500/10 border-red-500/30 text-red-300' ?>">
        <i class="fas fa-<?= $flash['type']==='success'?'circle-check':'circle-exclamation' ?> mt-0.5"></i>
        <span><?= sanitize($flash['message']) ?></span>
    </div>
    <?php endif; ?>
