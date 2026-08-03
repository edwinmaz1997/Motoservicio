<?php
$pageTitle = 'Proveedores';
require_once __DIR__ . '/../../includes/header.php';

$db     = getDB();
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$where  = "WHERE 1=1";
$params = [];

if ($search) {
    $where  .= " AND (nombre LIKE ? OR apellido LIKE ? OR nit LIKE ? OR telefono LIKE ?)";
    $like    = "%$search%";
    $params  = [$like,$like,$like,$like];
}

$total = $db->prepare("SELECT COUNT(*) FROM proveedores $where");
$total->execute($params);
$totalRows  = $total->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $db->prepare("SELECT * FROM proveedores $where ORDER BY nombre LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$proveedores = $stmt->fetchAll();
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Proveedores</h1>
    <a href="create.php" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition text-sm">
        <i class="fas fa-plus mr-1"></i> Nuevo proveedor
    </a>
</div>

<form method="GET" class="mb-4 flex gap-2">
    <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por nombre, NIT, teléfono..."
           class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($search): ?><a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
</form>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Nombre</th>
                <th class="px-4 py-3 text-left">Teléfono</th>
                <th class="px-4 py-3 text-left">NIT</th>
                <th class="px-4 py-3 text-left">Correo</th>
                <th class="px-4 py-3 text-left">Dirección</th>
                <th class="px-4 py-3 text-left">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($proveedores)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No se encontraron proveedores</td></tr>
            <?php endif; ?>
            <?php foreach ($proveedores as $p): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?= sanitize($p['nombre'] . ' ' . ($p['apellido'] ?? '')) ?></td>
                <td class="px-4 py-3"><?= sanitize($p['telefono'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= sanitize($p['nit'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= sanitize($p['correo'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= sanitize($p['direccion'] ?? '—') ?></td>
                <td class="px-4 py-3 flex gap-2">
                    <a href="edit.php?id=<?= $p['id'] ?>" class="text-yellow-500 hover:text-yellow-700"><i class="fas fa-edit"></i></a>
                    <a href="delete.php?id=<?= $p['id'] ?>" class="text-red-500 hover:text-red-700"
                       onclick="return confirm('¿Eliminar este proveedor?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
