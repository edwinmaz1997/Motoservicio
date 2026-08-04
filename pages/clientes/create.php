<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

$errors = [];
$data = ['nombre'=>'','apellido'=>'','direccion'=>'','dpi'=>'','nit'=>'','telefono'=>'','correo'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nombre'    => trim($_POST['nombre']    ?? ''),
        'apellido'  => trim($_POST['apellido']  ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'dpi'       => trim($_POST['dpi']       ?? ''),
        'nit'       => trim($_POST['nit']       ?? ''),
        'telefono'  => trim($_POST['telefono']  ?? ''),
        'correo'    => trim($_POST['correo']    ?? ''),
    ];
    if (!$data['nombre'])   $errors[] = 'El nombre es requerido.';
    if (!$data['apellido']) $errors[] = 'El apellido es requerido.';
    if (empty($errors)) {
        $db = getDB();
        $db->prepare("INSERT INTO clientes (sucursal_id,nombre,apellido,direccion,dpi,nit,telefono,correo) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$_SESSION['sucursal_id'],$data['nombre'],$data['apellido'],$data['direccion'],$data['dpi'],$data['nit'],$data['telefono'],$data['correo']]);
        flashMessage('success','Cliente creado exitosamente.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Nuevo Cliente';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Nuevo Cliente</h1>
</div>
<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><i class="fas fa-exclamation-circle mr-1"></i><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label><input type="text" name="nombre" value="<?= sanitize($data['nombre']) ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Apellido <span class="text-red-500">*</span></label><input type="text" name="apellido" value="<?= sanitize($data['apellido']) ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label><input type="text" name="telefono" value="<?= sanitize($data['telefono']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label><input type="email" name="correo" value="<?= sanitize($data['correo']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">DPI</label><input type="text" name="dpi" value="<?= sanitize($data['dpi']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">NIT</label><input type="text" name="nit" value="<?= sanitize($data['nit']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label><input type="text" name="direccion" value="<?= sanitize($data['direccion']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 transition text-sm font-medium"><i class="fas fa-save mr-1"></i> Guardar</button>
            <a href="index.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-200 transition text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
