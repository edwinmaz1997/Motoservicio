<?php
$pageTitle = 'Editar Usuario';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
requireRol(ROL_ADMIN);
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id=?");
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) { flashMessage('error','Usuario no encontrado.'); header('Location: index.php'); exit; }
$sucursales = $db->query("SELECT * FROM sucursales WHERE activa=1 ORDER BY nombre")->fetchAll();
global $ROLES;
$errors = [];
$data = $u;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nombre','apellido','telefono','direccion','rol_id','username','sucursal_id'] as $k) $data[$k] = trim($_POST[$k] ?? '');
    $password  = trim($_POST['password']  ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    if (!$data['nombre'])   $errors[] = 'El nombre es requerido.';
    if (!$data['username']) $errors[] = 'El usuario es requerido.';
    if ($password && $password !== $password2) $errors[] = 'Las contraseñas no coinciden.';

    $check = $db->prepare("SELECT id FROM usuarios WHERE username=? AND id!=?");
    $check->execute([$data['username'],$id]);
    if ($check->fetch()) $errors[] = 'El nombre de usuario ya existe.';

    if (empty($errors)) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare("UPDATE usuarios SET sucursal_id=?,nombre=?,apellido=?,telefono=?,direccion=?,rol_id=?,username=?,password_hash=? WHERE id=?")
               ->execute([$data['sucursal_id'],$data['nombre'],$data['apellido'],$data['telefono'],$data['direccion'],$data['rol_id'],$data['username'],$hash,$id]);
        } else {
            $db->prepare("UPDATE usuarios SET sucursal_id=?,nombre=?,apellido=?,telefono=?,direccion=?,rol_id=?,username=? WHERE id=?")
               ->execute([$data['sucursal_id'],$data['nombre'],$data['apellido'],$data['telefono'],$data['direccion'],$data['rol_id'],$data['username'],$id]);
        }
        flashMessage('success','Usuario actualizado.');
        header('Location: index.php'); exit;
    }
}
$pageTitle = 'Editar Usuario';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Editar Usuario</h1>
</div>
<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="nombre" value="<?= sanitize($data['nombre']) ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
                <input type="text" name="apellido" value="<?= sanitize($data['apellido']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="text" name="telefono" value="<?= sanitize($data['telefono'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
                <select name="sucursal_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $data['sucursal_id']==$s['id']?'selected':'' ?>><?= sanitize($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                <select name="rol_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($ROLES as $rid=>$rn): ?>
                    <option value="<?= $rid ?>" <?= $data['rol_id']==$rid?'selected':'' ?>><?= sanitize($rn) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Usuario *</label>
                <input type="text" name="username" value="<?= sanitize($data['username']) ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña <span class="text-gray-400 font-normal">(dejar vacío para no cambiar)</span></label>
                <input type="password" name="password" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                <input type="password" name="password2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                <input type="text" name="direccion" value="<?= sanitize($data['direccion'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 text-sm font-medium"><i class="fas fa-save mr-1"></i> Actualizar</button>
            <a href="index.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
