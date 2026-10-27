<?php
/**
 * Pantalla que ve el taller cuando su contrato no está al día.
 * No muestra nada del sistema, sólo el motivo y a quién llamar.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/empresa.php';
requireLogin();

$motivo  = $_SESSION['bloqueo_motivo'] ?? 'suspendida';
$empresa = empresaInfo();

// Si ya se arregló, de vuelta al sistema
if (!empresaBloqueada()) {
    unset($_SESSION['bloqueo_motivo']);
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$textos = [
    'vencida'    => ['Tu suscripción venció', 'El acceso al sistema está en pausa hasta renovar. Tus datos están intactos y vuelven a estar disponibles en cuanto se active de nuevo.'],
    'suspendida' => ['Cuenta suspendida',     'El acceso al sistema está temporalmente suspendido. Tus datos están intactos.'],
    'cancelada'  => ['Servicio cancelado',    'Este servicio fue cancelado. Si necesitas recuperar tu información, comunícate con soporte.'],
];
[$titulo, $detalle] = $textos[$motivo] ?? $textos['suspendida'];

$soporteTel = '50200000000';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($titulo) ?> — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased}</style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-5">

<div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl p-8 text-center">
    <div class="w-14 h-14 mx-auto mb-5 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center">
        <i class="fas fa-<?= $motivo==='cancelada' ? 'circle-xmark' : 'clock' ?> text-amber-500 text-xl"></i>
    </div>

    <h1 class="text-[20px] font-semibold text-slate-900 tracking-tight"><?= sanitize($titulo) ?></h1>
    <p class="text-[14px] text-slate-500 mt-2 leading-relaxed"><?= sanitize($detalle) ?></p>

    <?php if ($empresa && $motivo === 'vencida' && $empresa['fecha_vence']): ?>
    <div class="mt-5 py-3 px-4 rounded-xl bg-slate-50 border border-slate-200 text-[13px] text-slate-600">
        Venció el <strong class="text-slate-900"><?= formatDate($empresa['fecha_vence']) ?></strong>
    </div>
    <?php endif; ?>

    <a href="https://wa.me/<?= $soporteTel ?>?text=<?= urlencode('Hola, necesito reactivar ' . ($empresa['nombre'] ?? 'mi taller') . ' en el sistema.') ?>"
       target="_blank" rel="noopener"
       class="mt-6 w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[14px] px-5 py-3 rounded-xl">
        <i class="fab fa-whatsapp text-base"></i> Hablar con soporte
    </a>

    <a href="<?= BASE_URL ?>/logout.php" class="mt-3 block text-[13px] text-slate-400 hover:text-slate-600">
        Cerrar sesión
    </a>
</div>

</body>
</html>
