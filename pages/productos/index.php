<?php
$pageTitle = 'Productos';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$search = trim($_GET['q'] ?? '');
$tipo   = $_GET['tipo'] ?? '';
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20; $offset = ($page-1)*$limit;
$where  = "WHERE 1=1"; $params = [];
if ($search) { $like="%$search%"; $where.=" AND (p.nombre LIKE ? OR p.descripcion LIKE ?)"; $params=[$like,$like]; }
if ($tipo)   { $where.=" AND p.tipo=?"; $params[]=$tipo; }
$total = $db->prepare("SELECT COUNT(*) FROM productos p $where"); $total->execute($params); $totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$limit);
$stmt = $db->prepare("SELECT p.*, c.nombre as categoria FROM productos p LEFT JOIN categorias c ON c.id=p.categoria_id $where ORDER BY p.nombre LIMIT $limit OFFSET $offset");
$stmt->execute($params); $productos=$stmt->fetchAll();
?>
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Productos</h1>
    <a href="create.php" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 text-sm"><i class="fas fa-plus mr-1"></i> Nuevo producto</a>
</div>
<form method="GET" class="mb-4 flex gap-2 flex-wrap">
    <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por nombre..."
           class="flex-1 min-w-48 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <select name="tipo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Todos</option>
        <option value="estandar" <?= $tipo==='estandar'?'selected':'' ?>>Estándar</option>
        <option value="servicio" <?= $tipo==='servicio'?'selected':'' ?>>Servicio</option>
    </select>
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($search||$tipo): ?><a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
</form>
<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Nombre</th>
                <th class="px-4 py-3 text-left">Tipo</th>
                <th class="px-4 py-3 text-left">Categoría</th>
                <th class="px-4 py-3 text-left">Precio</th>
                <th class="px-4 py-3 text-left">Stock</th>
                <th class="px-4 py-3 text-left">Unidad</th>
                <th class="px-4 py-3 text-left">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($productos)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No se encontraron productos</td></tr><?php endif; ?>
            <?php foreach ($productos as $p): ?>
            <tr class="hover:bg-gray-50 <?= $p['tipo']==='estandar' && $p['stock'] <= $p['stock_minimo'] && $p['stock_minimo'] > 0 ? 'bg-orange-50' : '' ?>">
                <td class="px-4 py-3 font-medium"><?= sanitize($p['nombre']) ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs <?= $p['tipo']==='servicio' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700' ?>">
                        <?= $p['tipo']==='servicio' ? 'Servicio' : 'Estándar' ?>
                    </span>
                </td>
                <td class="px-4 py-3"><?= sanitize($p['categoria'] ?? '—') ?></td>
                <td class="px-4 py-3 font-semibold"><?= formatMoney($p['precio']) ?></td>
                <td class="px-4 py-3">
                    <?php if ($p['tipo']==='servicio'): ?>
                        <span class="text-gray-400 text-xs">N/A</span>
                    <?php else: ?>
                        <span class="<?= $p['stock'] <= $p['stock_minimo'] && $p['stock_minimo']>0 ? 'text-red-600 font-semibold' : '' ?>">
                            <?= $p['stock'] ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3"><?= sanitize($p['unidad'] ?? '') ?></td>
                <td class="px-4 py-3 flex gap-2">
                    <a href="edit.php?id=<?= $p['id'] ?>" class="text-yellow-500 hover:text-yellow-700"><i class="fas fa-edit"></i></a>
                    <a href="delete.php?id=<?= $p['id'] ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('¿Eliminar?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
