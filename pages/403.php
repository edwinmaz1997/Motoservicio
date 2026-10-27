<?php
// Página de acceso denegado. Se muestra sin el layout para evitar
// enviar HTML antes de una posible redirección.
if (!defined('APP_NAME')) require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',system-ui,sans-serif}</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm max-w-md w-full p-8 text-center">
        <img src="<?= BASE_URL ?>/assets/brand/logo.png" alt="" class="h-8 w-auto object-contain mx-auto mb-6 opacity-70">
        <div class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center mx-auto mb-4 text-2xl">
            <i class="fas fa-lock"></i>
        </div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Acceso denegado</h1>
        <p class="text-sm text-slate-500 mb-6">
            Tu usuario no tiene permiso para ver esta sección. Si crees que es un error, contacta al administrador.
        </p>
        <a href="<?= BASE_URL ?>/index.php"
           class="inline-flex items-center gap-2 bg-slate-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-900 transition">
            <i class="fas fa-arrow-left text-xs"></i> Volver al inicio
        </a>
    </div>
</body>
</html>
