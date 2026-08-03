<?php
$pageTitle = 'Motocicletas';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$search     = trim($_GET['q'] ?? '');
$clienteId  = (int)($_GET['cliente_id'] ?? 0);
$page       = max(1,(int)($_GET['page'] ?? 1));
$limit      = 20;
$offset     = ($page-1)*$limit;
$where      = "WHERE c.sucursal_id = ?";
$params     = [$user['sucursal_id']];
if ($clienteId) { $where .= " AND m.cliente_id = ?"; $params[] = $clienteId; }
if ($search) {
    $like = "%$search%";
    $where .= " AND (m.marca_texto LIKE ? OR m.modelo LIKE ? OR m.placa LIKE ? OR m.vin LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ?)";
    $params = array_merge($params,[$like,$like,$like,$like,$like,$like]);
}
$total = $db->prepare("SELECT COUNT(*) FROM motocicletas m JOIN clientes c ON c.id=m.cliente_id $where");
$total->execute($params);
$totalRows  = $total->fetchColumn();
$totalPages = ceil($totalRows/$limit);

$stmt = $db->prepare("SELECT m.*, CONCAT(c.nombre,' ',c.apellido) as cliente, c.id as cid
    FROM motocicletas m JOIN clientes c ON c.id=m.cliente_id $where ORDER BY m.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$motos = $stmt->fetchAll();

$clienteFiltro = null;
if ($clienteId) {
    $cs = $db->prepare("SELECT * FROM clientes WHERE id=?"); $cs->execute([$clienteId]); $clienteFiltro = $cs->fetch();
}
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Motocicletas</h1>
        <?php if ($clienteFiltro): ?>
        <p class="text-sm text-gray-500">Filtrando por: <strong><?= sanitize($clienteFiltro['nombre'].' '.$clienteFiltro['apellido']) ?></strong>
            <a href="index.php" class="text-blue-600 ml-2 hover:underline">Ver todas</a></p>
        <?php endif; ?>
    </div>
    <a href="create.php<?= $clienteId ? '?cliente_id='.$clienteId : '' ?>" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 text-sm">
        <i class="fas fa-plus mr-1"></i> Nueva moto
    </a>
</div>
<form method="GET" class="mb-4 flex gap-2">
    <?php if ($clienteId): ?><input type="hidden" name="cliente_id" value="<?= $clienteId ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por marca, modelo, placa, VIN, cliente..."
           class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($search): ?><a href="?<?= $clienteId?'cliente_id='.$clienteId:'' ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
</form>
<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Cliente</th>
                <th class="px-4 py-3 text-left">Marca / Modelo</th>
                <th class="px-4 py-3 text-left">Año</th>
                <th class="px-4 py-3 text-left">Color</th>
                <th class="px-4 py-3 text-left">Placa</th>
                <th class="px-4 py-3 text-left">VIN</th>
                <th class="px-4 py-3 text-left">KM</th>
                <th class="px-4 py-3 text-left">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($motos)): ?>
            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No se encontraron motocicletas</td></tr>
            <?php endif; ?>
            <?php foreach ($motos as $m): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3"><a href="../clientes/view.php?id=<?= $m['cid'] ?>" class="text-blue-600 hover:underline"><?= sanitize($m['cliente']) ?></a></td>
                <td class="px-4 py-3 font-medium"><?= sanitize(($m['marca_texto']??'') . ' ' . ($m['modelo']??'')) ?></td>
                <td class="px-4 py-3"><?= sanitize($m['anio'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= sanitize($m['color'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= sanitize($m['placa'] ?? '—') ?></td>
                <td class="px-4 py-3 font-mono text-xs"><?= sanitize($m['vin'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= number_format($m['kilometraje'] ?? 0) ?></td>
                <td class="px-4 py-3 flex gap-2">
                    <a href="edit.php?id=<?= $m['id'] ?>" class="text-yellow-500 hover:text-yellow-700"><i class="fas fa-edit"></i></a>
                    <a href="delete.php?id=<?= $m['id'] ?>" class="text-red-500 hover:text-red-700"
                       onclick="return confirm('¿Eliminar esta motocicleta?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
