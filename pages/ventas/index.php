<?php
$pageTitle = 'Ventas';
require_once __DIR__ . '/../../includes/header.php';

$db     = getDB();
$search = trim($_GET['q'] ?? '');
$fecha  = $_GET['fecha'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = "WHERE v.sucursal_id = ?";
$params = [$user['sucursal_id']];

if ($search) {
    $like = "%$search%";
    $where .= " AND (v.numero_venta LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ?)";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($fecha) {
    $where .= " AND v.fecha = ?";
    $params[] = $fecha;
}

$total = $db->prepare("SELECT COUNT(*) FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id $where");
$total->execute($params);
$totalRows  = $total->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $db->prepare("
    SELECT v.*, CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')) as cliente,
           CONCAT(u.nombre,' ',u.apellido) as vendedor
    FROM ventas v
    LEFT JOIN clientes c ON c.id=v.cliente_id
    LEFT JOIN usuarios u ON u.id=v.vendedor_id
    $where
    ORDER BY v.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$ventas = $stmt->fetchAll();

// Total del día
$hoy = date('Y-m-d');
$totalHoy = $db->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE sucursal_id=? AND fecha=? AND estado='pagada'");
$totalHoy->execute([$user['sucursal_id'], $hoy]);
$totalHoy = $totalHoy->fetchColumn();
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Ventas</h1>
        <p class="text-sm text-gray-500">Total hoy: <span class="font-semibold text-green-600"><?= formatMoney($totalHoy) ?></span></p>
    </div>
    <a href="create.php" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 text-sm">
        <i class="fas fa-plus mr-1"></i> Nueva venta
    </a>
</div>

<form method="GET" class="mb-4 flex gap-2 flex-wrap">
    <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por # venta o cliente..."
           class="flex-1 min-w-48 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <input type="date" name="fecha" value="<?= sanitize($fecha) ?>"
           class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($search || $fecha): ?><a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
</form>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left"># Venta</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Vendedor</th>
                    <th class="px-4 py-3 text-left">Método</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-left">Estado</th>
                    <th class="px-4 py-3 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($ventas)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No se encontraron ventas</td></tr>
                <?php endif; ?>
                <?php foreach ($ventas as $v): ?>
                <tr class="hover:bg-gray-50 <?= $v['estado']==='anulada'?'opacity-50':'' ?>">
                    <td class="px-4 py-3 font-mono font-semibold text-blue-700"><?= sanitize($v['numero_venta']) ?></td>
                    <td class="px-4 py-3"><?= formatDate($v['fecha']) ?></td>
                    <td class="px-4 py-3"><?= sanitize(trim($v['cliente']) ?: 'Consumidor final') ?></td>
                    <td class="px-4 py-3"><?= sanitize($v['vendedor'] ?? '—') ?></td>
                    <td class="px-4 py-3 capitalize"><?= sanitize($v['metodo_pago']) ?></td>
                    <td class="px-4 py-3 text-right font-semibold"><?= formatMoney($v['total']) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $v['estado']==='pagada'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>">
                            <?= ucfirst($v['estado']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="view.php?id=<?= $v['id'] ?>" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></a>
                        <?php if ($v['estado']==='pagada'): ?>
                        <a href="anular.php?id=<?= $v['id'] ?>" class="text-red-500 hover:text-red-700"
                           onclick="return confirm('¿Anular esta venta?')"><i class="fas fa-ban"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-500">
        <span><?= $totalRows ?> ventas</span>
        <div class="flex gap-1">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&fecha=<?= urlencode($fecha) ?>"
               class="px-3 py-1 rounded <?= $i===$page?'bg-blue-700 text-white':'bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
