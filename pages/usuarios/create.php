<?php
$pageTitle = 'Nuevo Usuario';
require_once __DIR__ . '/../../includes/header.php';
requireRol(ROL_ADMIN);
$db = getDB();
$sucursales = $db->query("SELECT * FROM sucursales WHERE activa=1 ORDER BY nombre")->fetchAll();
global $ROLES;
$errors = [];
$data = ['nombre'=>'','apellido'=>'','telefono'=>'','direccion'=>'','rol_id'=>'','username'=>'','sucursal_id'=>$user['sucursal_id']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) $data[$k] = trim($_POST[$k] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    if (!$data['nombre'])    $errors[] = 'El nombre es requerido.';
    if (!$data['username'])  $errors[] = 'El usuario es requerido.';
    if (!$data['rol_id'])    $errors[] = 'El rol es requerido.';
    if (!$password)          $errors[] = 'La contraseña es requerida.';
    if ($password !== $password2) $errors[] = 'Las contraseñas no coinciden.';

    // Verificar username único
    $check = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
    $check->execute([$data['username']]);
    if ($check->fetch()) $errors[] = 'El nombre de usuario ya existe.';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO usuarios (sucursal_id,nombre,apellido,telefono,direccion,rol_id,username,password_hash) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$data['sucursal_id'],$data['nombre'],$data['apellido'],$data['telefono'],$data['direccion'],$data['rol_id'],$data['username'],$hash]);
        flashMessage('success','Usuario creado.');
        header('Location: index.php'); exit;
    }
}
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Nuevo Usuario</h1>
</div>
<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="nombre" value="<?= sanitize($data['nombre']) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                <input type="text" name="apellido" value="<?= sanitize($data['apellido']) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="text" name="telefono" value="<?= sanitize($data['telefono']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal *</label>
                <select name="sucursal_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $data['sucursal_id'] == $s['id'] ? 'selected' : '' ?>><?= sanitize($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rol *</label>
                <select name="rol_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($ROLES as $rid => $rnombre): ?>
                    <option value="<?= $rid ?>" <?= $data['rol_id'] == $rid ? 'selected' : '' ?>><?= sanitize($rnombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario *</label>
                <input type="text" name="username" value="<?= sanitize($data['username']) ?>" required autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña *</label>
                <input type="password" name="password" required autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña *</label>
                <input type="password" name="password2" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                <input type="text" name="direccion" value="<?= sanitize($data['direccion']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 text-sm font-medium"><i class="fas fa-save mr-1"></i> Guardar</button>
            <a href="index.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
