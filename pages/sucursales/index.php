<?php
$pageTitle = 'Sucursales';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
refreshSession();
$user = currentUser();
requireRol(ROL_ADMIN);
$db = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nombre'   => trim($_POST['nombre']   ?? ''),
        'direccion'=> trim($_POST['direccion'] ?? ''),
        'telefono' => trim($_POST['telefono']  ?? ''),
        'correo'   => trim($_POST['correo']    ?? ''),
    ];
    if (!$data['nombre']) $errors[] = 'El nombre es requerido.';
    if (empty($errors)) {
        if ($id) {
            $db->prepare("UPDATE sucursales SET nombre=?,direccion=?,telefono=?,correo=? WHERE id=?")
               ->execute([$data['nombre'],$data['direccion'],$data['telefono'],$data['correo'],$id]);
            flashMessage('success','Sucursal actualizada.');
        } else {
            $db->prepare("INSERT INTO sucursales (nombre,direccion,telefono,correo) VALUES (?,?,?,?)")
               ->execute([$data['nombre'],$data['direccion'],$data['telefono'],$data['correo']]);
            flashMessage('success','Sucursal creada.');
        }
        header('Location: index.php'); exit;
    }
}

if ($action === 'delete' && $id) {
    $check = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE sucursal_id=?"); $check->execute([$id]);
    if ($check->fetchColumn() > 0) { flashMessage('error','No se puede eliminar: tiene usuarios asignados.'); }
    else { $db->prepare("DELETE FROM sucursales WHERE id=?")->execute([$id]); flashMessage('success','Sucursal eliminada.'); }
    header('Location: index.php'); exit;
}

$editData = ['nombre'=>'','direccion'=>'','telefono'=>'','correo'=>''];
if (($action === 'edit') && $id) {
    $stmt = $db->prepare("SELECT * FROM sucursales WHERE id=?"); $stmt->execute([$id]); $editData = $stmt->fetch() ?: $editData;
}

$sucursales = $db->query("SELECT s.*, (SELECT COUNT(*) FROM usuarios WHERE sucursal_id=s.id) as n_usuarios FROM sucursales s ORDER BY s.nombre")->fetchAll();
$pageTitle = 'Sucursales';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Sucursales</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Formulario -->
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold text-gray-700 mb-4"><?= $action==='edit' ? 'Editar sucursal' : 'Nueva sucursal' ?></h2>
        <?php if ($errors): ?><div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2 rounded text-sm mb-4"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="POST" action="index.php<?= $action==='edit' ? '?action=edit&id='.$id : '' ?>">
            <div class="space-y-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input type="text" name="nombre" value="<?= sanitize($editData['nombre']) ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="telefono" value="<?= sanitize($editData['telefono']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Correo</label>
                    <input type="email" name="correo" value="<?= sanitize($editData['correo']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <input type="text" name="direccion" value="<?= sanitize($editData['direccion']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            </div>
            <div class="flex gap-2 mt-4">
                <button type="submit" class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-800"><i class="fas fa-save mr-1"></i> <?= $action==='edit'?'Actualizar':'Guardar' ?></button>
                <?php if ($action==='edit'): ?><a href="index.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Listado -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Teléfono</th>
                    <th class="px-4 py-3 text-left">Usuarios</th>
                    <th class="px-4 py-3 text-left">Estado</th>
                    <th class="px-4 py-3 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($sucursales as $s): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= sanitize($s['nombre']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($s['telefono']??'—') ?></td>
                    <td class="px-4 py-3"><?= $s['n_usuarios'] ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?= $s['activa']?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= $s['activa']?'Activa':'Inactiva' ?></span></td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="?action=edit&id=<?= $s['id'] ?>" class="text-yellow-500 hover:text-yellow-700"><i class="fas fa-edit"></i></a>
                        <a href="?action=delete&id=<?= $s['id'] ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('¿Eliminar sucursal?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
