<?php
$pageTitle = 'Compras';
require_once __DIR__ . '/../../includes/header.php';
$db     = getDB();
$search = trim($_GET['q'] ?? '');
$fecha  = $_GET['fecha'] ?? '';
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20; $offset = ($page-1)*$limit;
$where  = "WHERE c.sucursal_id=?"; $params = [$user['sucursal_id']];
if ($search) { $like="%$search%"; $where.=" AND (c.numero_compra LIKE ? OR p.nombre LIKE ?)"; $params=array_merge($params,[$like,$like]); }
if ($fecha) { $where.=" AND c.fecha=?"; $params[]=$fecha; }
$total = $db->prepare("SELECT COUNT(*) FROM compras c LEFT JOIN proveedores p ON p.id=c.proveedor_id $where");
$total->execute($params); $totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$limit);
$stmt = $db->prepare("SELECT c.*, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as proveedor FROM compras c LEFT JOIN proveedores p ON p.id=c.proveedor_id $where ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params); $compras=$stmt->fetchAll();
?>
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Compras</h1>
    <a href="create.php" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 text-sm"><i class="fas fa-plus mr-1"></i> Nueva compra</a>
</div>
<form method="GET" class="mb-4 flex gap-2 flex-wrap">
    <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por # compra o proveedor..."
           class="flex-1 min-w-48 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <input type="date" name="fecha" value="<?= sanitize($fecha) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($search||$fecha): ?><a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
</form>
<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left"># Compra</th>
                <th class="px-4 py-3 text-left">Fecha</th>
                <th class="px-4 py-3 text-left">Proveedor</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-left">Estado</th>
                <th class="px-4 py-3 text-left">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($compras)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No se encontraron compras</td></tr><?php endif; ?>
            <?php foreach ($compras as $c): ?>
            <tr class="hover:bg-gray-50 <?= $c['estado']==='anulada'?'opacity-50':'' ?>">
                <td class="px-4 py-3 font-mono font-semibold text-blue-700"><?= sanitize($c['numero_compra']) ?></td>
                <td class="px-4 py-3"><?= formatDate($c['fecha']) ?></td>
                <td class="px-4 py-3"><?= sanitize(trim($c['proveedor']) ?: '—') ?></td>
                <td class="px-4 py-3 text-right font-semibold"><?= formatMoney($c['total']) ?></td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs <?= $c['estado']==='recibida'?'bg-green-100 text-green-700':($c['estado']==='pendiente'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-700') ?>"><?= ucfirst($c['estado']) ?></span></td>
                <td class="px-4 py-3 flex gap-2">
                    <a href="view.php?id=<?= $c['id'] ?>" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></a>
                    <?php if ($c['estado']!=='anulada'): ?><a href="anular.php?id=<?= $c['id'] ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('¿Anular esta compra?')"><i class="fas fa-ban"></i></a><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
