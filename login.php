<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT u.*, s.nombre AS sucursal_nombre, s.empresa_id,
                   e.nombre AS empresa_nombre, e.estado AS empresa_estado, e.fecha_vence
            FROM usuarios u
            LEFT JOIN sucursales s ON s.id = u.sucursal_id
            LEFT JOIN empresas   e ON e.id = s.empresa_id
            WHERE u.username = ? AND u.activo = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['usuario_id']      = $user['id'];
            $_SESSION['nombre']          = $user['nombre'];
            $_SESSION['apellido']        = $user['apellido'];
            $_SESSION['rol_id']          = $user['rol_id'];
            $_SESSION['es_super']        = !empty($user['es_super']);

            if (!empty($user['es_super'])) {
                // El superadministrador entra a su propio panel
                header('Location: ' . BASE_URL . '/master/index.php');
                exit;
            }

            if (empty($user['sucursal_id'])) {
                $error = 'Tu usuario no tiene sucursal asignada. Contacta al administrador.';
            } else {
                $_SESSION['sucursal_id']     = $user['sucursal_id'];
                $_SESSION['sucursal_nombre'] = $user['sucursal_nombre'];
                $_SESSION['empresa_id']      = $user['empresa_id'];
                $_SESSION['empresa_nombre']  = $user['empresa_nombre'];
                header('Location: ' . BASE_URL . '/index.php');
                exit;
            }
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Escribe tu usuario y contraseña.';
    }
}

// Identidad visual de la sucursal principal
$marca = ['nombre' => APP_NAME, 'logo_path' => null, 'color_primario' => '#1d4ed8', 'slogan' => null];
try {
    $m = getDB()->query("SELECT nombre, logo_path, color_primario, slogan FROM sucursales WHERE activa=1 ORDER BY id LIMIT 1")->fetch();
    if ($m) $marca = array_merge($marca, array_filter($m, function($v){ return $v !== null && $v !== ''; }));
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — <?= sanitize($marca['nombre']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <meta name="theme-color" content="<?= $marca['color_primario'] ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= sanitize($marca['nombre']) ?>">
    <link rel="icon" href="<?= BASE_URL ?>/assets/icons/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/icons/icon-32.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/icon-180.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{ --cp: <?= $marca['color_primario'] ?>; }
        body{
            font-family:'Inter',system-ui,sans-serif;
            -webkit-font-smoothing:antialiased;
        }
        .panel{ background: color-mix(in srgb, var(--cp) 16%, #0b1220); }
        input{
            border:1px solid #e3e8ef; border-radius:10px;
            transition: border-color .14s ease, box-shadow .14s ease;
        }
        input:focus{
            outline:none; border-color:var(--cp);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--cp) 14%, transparent);
        }
        .btn{ background: var(--cp); transition: filter .14s ease, transform .1s ease; }
        .btn:hover{ filter: brightness(1.08); }
        .btn:active{ transform: translateY(1px); }
        /* Rejilla sutil: la textura de un plano de taller */
        .grid-bg{
            background-image:
                linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
            background-size: 34px 34px;
        }
        @media (prefers-reduced-motion: reduce){ *{transition:none!important} }
    </style>
</head>
<body class="min-h-screen bg-[#f6f8fb] lg:grid lg:grid-cols-[1.05fr_1fr]">

<!-- Panel de marca -->
<div class="panel grid-bg hidden lg:flex flex-col justify-between p-12 text-white relative overflow-hidden">
    <div class="flex items-center gap-3">
        <?php if ($marca['logo_path']): ?>
            <img src="<?= BASE_URL ?>/<?= sanitize($marca['logo_path']) ?>" alt=""
                 class="w-11 h-11 object-contain rounded-lg bg-white/10 p-1">
            <span class="text-[15px] font-semibold tracking-tight"><?= sanitize($marca['nombre']) ?></span>
        <?php else: ?>
            <img src="<?= BASE_URL ?>/assets/brand/logo-light.png" alt="<?= sanitize($marca['nombre']) ?>"
                 class="h-10 w-auto object-contain">
        <?php endif; ?>
    </div>

    <div class="max-w-md">
        <h1 class="text-[40px] leading-[1.1] font-semibold tracking-[-.025em] mb-5">
            Cada moto que entra,<br>bajo control.
        </h1>
        <p class="text-white/55 text-[15px] leading-relaxed">
            <?= $marca['slogan']
                ? sanitize($marca['slogan'])
                : 'Órdenes de servicio, inventario, caja y reportes en un solo lugar.' ?>
        </p>
    </div>

    <div class="flex gap-10 text-white/45 text-[13px]">
        <div><span class="block text-white text-[19px] font-semibold tabular-nums">11</span>módulos</div>
        <div><span class="block text-white text-[19px] font-semibold tabular-nums">7</span>reportes</div>
        <div><span class="block text-white text-[19px] font-semibold">FEL</span>facturación</div>
    </div>
</div>

<!-- Formulario -->
<div class="flex items-center justify-center p-6 lg:p-12 min-h-screen lg:min-h-0">
    <div class="w-full max-w-[380px]">

        <!-- Marca en móvil -->
        <div class="lg:hidden flex items-center gap-3 mb-10">
            <?php if ($marca['logo_path']): ?>
                <img src="<?= BASE_URL ?>/<?= sanitize($marca['logo_path']) ?>" alt=""
                     class="w-10 h-10 object-contain rounded-lg border border-slate-200 p-1">
                <span class="text-[15px] font-semibold text-slate-900 tracking-tight"><?= sanitize($marca['nombre']) ?></span>
            <?php else: ?>
                <img src="<?= BASE_URL ?>/assets/brand/logo.png" alt="<?= sanitize($marca['nombre']) ?>"
                     class="h-10 w-auto object-contain">
            <?php endif; ?>
        </div>

        <h2 class="text-[26px] font-semibold text-slate-900 tracking-tight mb-1.5">Entrar</h2>
        <p class="text-slate-500 text-[14px] mb-8">Usa el usuario que te dieron en el taller.</p>

        <?php if ($error): ?>
        <div class="flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-[13.5px] mb-5">
            <i class="fas fa-circle-exclamation mt-0.5 flex-shrink-0"></i>
            <span><?= sanitize($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-[13px] font-medium text-slate-700 mb-1.5">Usuario</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username"
                       value="<?= sanitize($_POST['username'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 text-[14px] text-slate-900 placeholder:text-slate-400"
                       placeholder="tu usuario">
            </div>

            <div>
                <label for="password" class="block text-[13px] font-medium text-slate-700 mb-1.5">Contraseña</label>
                <div class="relative" x-data="{ ver: false }">
                    <input :type="ver ? 'text' : 'password'" id="password" name="password" required autocomplete="current-password"
                           class="w-full px-3.5 py-2.5 pr-11 text-[14px] text-slate-900 placeholder:text-slate-400"
                           placeholder="••••••••">
                    <button type="button" @click="ver = !ver" tabindex="-1"
                            :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            class="absolute right-1.5 top-1.5 w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                        <i :class="ver ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-[13px]"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                    class="btn w-full text-white font-medium py-2.5 rounded-[10px] text-[14px] mt-2">
                Entrar
            </button>
        </form>

        <p class="text-center text-[12px] text-slate-400 mt-10">
            <?= sanitize($marca['nombre']) ?> · v<?= APP_VERSION ?>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
if ('serviceWorker' in navigator) {
    addEventListener('load', () => navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js', { scope: '/' }).catch(() => {}));
}
</script>
</body>
</html>
