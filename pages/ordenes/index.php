<?php
$pageTitle = 'Órdenes de Servicio';
require_once __DIR__ . '/../../includes/header.php';

$db     = getDB();
$search = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = "WHERE o.sucursal_id = ?";
$params = [$user['sucursal_id']];

if ($search) {
    $like    = "%$search%";
    $where  .= " AND (o.numero_orden LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ? OR m.placa LIKE ? OR m.marca_texto LIKE ?)";
    $params  = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($estado) {
    $where  .= " AND o.estado = ?";
    $params[] = $estado;
}

$total = $db->prepare("SELECT COUNT(*) FROM ordenes o JOIN clientes c ON c.id=o.cliente_id JOIN motocicletas m ON m.id=o.motocicleta_id $where");
$total->execute($params);
$totalRows  = $total->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $db->prepare("
    SELECT o.*,
           CONCAT(c.nombre,' ',c.apellido) as cliente,
           CONCAT(m.marca_texto,' ',COALESCE(m.modelo,'')) as moto,
           m.placa,
           CONCAT(u.nombre,' ',u.apellido) as tecnico
    FROM ordenes o
    JOIN clientes c ON c.id = o.cliente_id
    JOIN motocicletas m ON m.id = o.motocicleta_id
    LEFT JOIN usuarios u ON u.id = o.tecnico_id
    $where
    ORDER BY o.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$ordenes = $stmt->fetchAll();

$hoy = date('Y-m-d');

$estadoColors = [
    'abierta'    => 'bg-blue-100 text-blue-700',
    'en_proceso' => 'bg-yellow-100 text-yellow-700',
    'lista'      => 'bg-green-100 text-green-700',
    'entregada'  => 'bg-gray-100 text-gray-600',
    'cancelada'  => 'bg-red-100 text-red-700',
];
$estadoLabels = [
    'abierta'    => 'Abierta',
    'en_proceso' => 'En proceso',
    'lista'      => 'Lista',
    'entregada'  => 'Entregada',
    'cancelada'  => 'Cancelada',
];
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Órdenes de Servicio</h1>
    <a href="create.php" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 text-sm">
        <i class="fas fa-plus mr-1"></i> Nueva orden
    </a>
</div>

<form method="GET" class="mb-4 flex gap-2 flex-wrap">
    <input type="text" name="q" value="<?= sanitize($search) ?>"
           placeholder="Buscar por # orden, cliente, placa..."
           class="flex-1 min-w-48 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <select name="estado" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Todos los estados</option>
        <?php foreach ($estadoLabels as $val => $label): ?>
        <option value="<?= $val ?>" <?= $estado===$val?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($search || $estado): ?>
    <a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a>
    <?php endif; ?>
</form>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left"># Orden</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Moto</th>
                    <th class="px-4 py-3 text-left">Técnico</th>
                    <th class="px-4 py-3 text-left">Ingreso</th>
                    <th class="px-4 py-3 text-left">Salida esp.</th>
                    <th class="px-4 py-3 text-left">Total</th>
                    <th class="px-4 py-3 text-left">Estado</th>
                    <th class="px-4 py-3 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($ordenes)): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No se encontraron órdenes</td></tr>
                <?php endif; ?>
                <?php foreach ($ordenes as $o): ?>
                <?php
                $urgencia = '';
                if (in_array($o['estado'], ['abierta','en_proceso'])) {
                    if ($o['fecha_salida_esperada'] < $hoy) $urgencia = 'bg-red-50';
                    elseif ($o['fecha_salida_esperada'] === $hoy) $urgencia = 'bg-green-50';
                }
                ?>
                <tr class="hover:bg-gray-50 <?= $urgencia ?>">
                    <td class="px-4 py-3 font-mono font-semibold text-blue-700"><?= sanitize($o['numero_orden']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($o['cliente']) ?></td>
                    <td class="px-4 py-3">
                        <?= sanitize($o['moto']) ?>
                        <?php if ($o['placa']): ?><span class="text-xs text-gray-400">(<?= sanitize($o['placa']) ?>)</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3"><?= sanitize($o['tecnico'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= formatDate($o['fecha_ingreso']) ?></td>
                    <td class="px-4 py-3">
                        <?php if (!$o['fecha_salida_esperada']): ?>
                            <span class="text-gray-400">—</span>
                        <?php elseif ($o['fecha_salida_esperada'] < $hoy && in_array($o['estado'],['abierta','en_proceso'])): ?>
                            <span class="text-red-600 font-semibold"><?= formatDate($o['fecha_salida_esperada']) ?> ⚠️</span>
                        <?php elseif ($o['fecha_salida_esperada'] === $hoy): ?>
                            <span class="text-green-600 font-semibold">Hoy</span>
                        <?php else: ?>
                            <?= formatDate($o['fecha_salida_esperada']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-semibold"><?= formatMoney($o['total']) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $estadoColors[$o['estado']] ?? '' ?>">
                            <?= $estadoLabels[$o['estado']] ?? $o['estado'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="view.php?id=<?= $o['id'] ?>" class="text-blue-500 hover:text-blue-700" title="Ver"><i class="fas fa-eye"></i></a>
                        <?php if (!in_array($o['estado'], ['entregada','cancelada'])): ?>
                        <a href="edit.php?id=<?= $o['id'] ?>" class="text-yellow-500 hover:text-yellow-700" title="Editar"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-500">
        <span><?= $totalRows ?> órdenes</span>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&estado=<?= urlencode($estado) ?>"
               class="px-3 py-1 rounded <?= $i===$page?'bg-blue-700 text-white':'bg-gray-100 hover:bg-gray-200' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
