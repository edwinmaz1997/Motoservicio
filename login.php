<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT u.*, s.nombre as sucursal_nombre FROM usuarios u
                              JOIN sucursales s ON s.id = u.sucursal_id
                              WHERE u.username = ? AND u.activo = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['usuario_id']      = $user['id'];
            $_SESSION['nombre']          = $user['nombre'];
            $_SESSION['apellido']        = $user['apellido'];
            $_SESSION['rol_id']          = $user['rol_id'];
            $_SESSION['sucursal_id']     = $user['sucursal_id'];
            $_SESSION['sucursal_nombre'] = $user['sucursal_nombre'];
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-900 to-blue-700 min-h-screen flex items-center justify-center">

<div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">

    <div class="text-center mb-8">
        <div class="text-5xl mb-3">🏍️</div>
        <h1 class="text-2xl font-bold text-blue-900"><?= APP_NAME ?></h1>
        <p class="text-gray-500 text-sm mt-1">Sistema de Gestión de Taller</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
        <i class="fas fa-exclamation-circle mr-2"></i><?= sanitize($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
            <div class="relative">
                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-user"></i></span>
                <input type="text" name="username" required autofocus
                       value="<?= sanitize($_POST['username'] ?? '') ?>"
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="usuario">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
            <div class="relative">
                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-lock"></i></span>
                <input type="password" name="password" required
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="••••••••">
            </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 rounded-lg transition">
            Iniciar sesión
        </button>
    </form>

    <p class="text-center text-xs text-gray-400 mt-6">
        <?= APP_NAME ?> &copy; <?= date('Y') ?>
    </p>
</div>

</body>
</html>
