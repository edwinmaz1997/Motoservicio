<?php
$pageTitle = 'Usuarios';
require_once __DIR__ . '/../../includes/header.php';
requireRol(ROL_ADMIN);
$db = getDB();
$search = trim($_GET['q'] ?? '');
$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $like = "%$search%";
    $where .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.username LIKE ?)";
    $params = [$like,$like,$like];
}
$stmt = $db->prepare("SELECT u.*, s.nombre as sucursal FROM usuarios u JOIN sucursales s ON s.id=u.sucursal_id $where ORDER BY u.nombre");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();
global $ROLES;
?>
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Usuarios</h1>
    <a href="create.php" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 text-sm">
        <i class="fas fa-plus mr-1"></i> Nuevo usuario
    </a>
</div>
<form method="GET" class="mb-4 flex gap-2">
    <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar..."
           class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($search): ?><a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
</form>
<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Nombre</th>
                <th class="px-4 py-3 text-left">Usuario</th>
                <th class="px-4 py-3 text-left">Rol</th>
                <th class="px-4 py-3 text-left">Sucursal</th>
                <th class="px-4 py-3 text-left">Teléfono</th>
                <th class="px-4 py-3 text-left">Estado</th>
                <th class="px-4 py-3 text-left">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($usuarios)): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No se encontraron usuarios</td></tr>
            <?php endif; ?>
            <?php foreach ($usuarios as $u): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?= sanitize($u['nombre'] . ' ' . $u['apellido']) ?></td>
                <td class="px-4 py-3 font-mono text-xs"><?= sanitize($u['username']) ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                        <?= sanitize($ROLES[$u['rol_id']] ?? 'Sin rol') ?>
                    </span>
                </td>
                <td class="px-4 py-3"><?= sanitize($u['sucursal']) ?></td>
                <td class="px-4 py-3"><?= sanitize($u['telefono'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs <?= $u['activo'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td class="px-4 py-3 flex gap-2">
                    <a href="edit.php?id=<?= $u['id'] ?>" class="text-yellow-500 hover:text-yellow-700"><i class="fas fa-edit"></i></a>
                    <?php if ($u['id'] !== $user['id']): ?>
                    <a href="toggle.php?id=<?= $u['id'] ?>" class="text-gray-500 hover:text-gray-700" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                        <i class="fas fa-<?= $u['activo'] ? 'ban' : 'check' ?>"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
