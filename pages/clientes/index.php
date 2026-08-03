<?php
$pageTitle = 'Clientes';
require_once __DIR__ . '/../../includes/header.php';

$db     = getDB();
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = "WHERE c.sucursal_id = ?";
$params = [$user['sucursal_id']];

if ($search) {
    $where   .= " AND (c.nombre LIKE ? OR c.apellido LIKE ? OR c.telefono LIKE ? OR c.nit LIKE ? OR c.dpi LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

$total = $db->prepare("SELECT COUNT(*) FROM clientes c $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $db->prepare("SELECT c.*, s.nombre as sucursal FROM clientes c JOIN sucursales s ON s.id = c.sucursal_id $where ORDER BY c.nombre, c.apellido LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$clientes = $stmt->fetchAll();
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Clientes</h1>
    <a href="create.php" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition text-sm">
        <i class="fas fa-plus mr-1"></i> Nuevo cliente
    </a>
</div>

<!-- Búsqueda -->
<form method="GET" class="mb-4 flex gap-2">
    <input type="text" name="q" value="<?= sanitize($search) ?>"
           placeholder="Buscar por nombre, teléfono, NIT, DPI..."
           class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-800">
        <i class="fas fa-search"></i>
    </button>
    <?php if ($search): ?>
    <a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Limpiar</a>
    <?php endif; ?>
</form>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Teléfono</th>
                    <th class="px-4 py-3 text-left">NIT</th>
                    <th class="px-4 py-3 text-left">DPI</th>
                    <th class="px-4 py-3 text-left">Correo</th>
                    <th class="px-4 py-3 text-left">Motos</th>
                    <th class="px-4 py-3 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($clientes)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No se encontraron clientes</td></tr>
                <?php endif; ?>
                <?php foreach ($clientes as $c): ?>
                <?php
                $motos = $db->prepare("SELECT COUNT(*) FROM motocicletas WHERE cliente_id = ?");
                $motos->execute([$c['id']]);
                $nMotos = $motos->fetchColumn();
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= sanitize($c['nombre'] . ' ' . $c['apellido']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($c['telefono'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= sanitize($c['nit'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= sanitize($c['dpi'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= sanitize($c['correo'] ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <a href="../motos/index.php?cliente_id=<?= $c['id'] ?>" class="text-blue-600 hover:underline">
                            <?= $nMotos ?> moto<?= $nMotos !== '1' ? 's' : '' ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="view.php?id=<?= $c['id'] ?>" class="text-blue-500 hover:text-blue-700" title="Ver"><i class="fas fa-eye"></i></a>
                        <a href="edit.php?id=<?= $c['id'] ?>" class="text-yellow-500 hover:text-yellow-700" title="Editar"><i class="fas fa-edit"></i></a>
                        <a href="delete.php?id=<?= $c['id'] ?>" class="text-red-500 hover:text-red-700" title="Eliminar"
                           onclick="return confirm('¿Eliminar este cliente?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-500">
        <span><?= $totalRows ?> registros</span>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"
               class="px-3 py-1 rounded <?= $i === $page ? 'bg-blue-700 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
