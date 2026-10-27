<?php
$pageTitle = 'Dashboard — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';

$db  = getDB();
$hoy = date('Y-m-d');
$suc = $user['sucursal_id'];

// Órdenes abiertas
$stmt = $db->prepare("SELECT COUNT(*) FROM ordenes WHERE sucursal_id = ? AND estado IN ('abierta','en_proceso')");
$stmt->execute([$suc]);
$ordenesAbiertas = $stmt->fetchColumn();

// Motos que salen hoy
$stmt = $db->prepare("SELECT COUNT(*) FROM ordenes WHERE sucursal_id = ? AND fecha_salida_esperada = ? AND estado IN ('abierta','en_proceso')");
$stmt->execute([$suc, $hoy]);
$motosSalenHoy = $stmt->fetchColumn();

// Motos vencidas (fecha salida esperada < hoy y aún abiertas)
$stmt = $db->prepare("SELECT COUNT(*) FROM ordenes WHERE sucursal_id = ? AND fecha_salida_esperada < ? AND estado IN ('abierta','en_proceso')");
$stmt->execute([$suc, $hoy]);
$motosVencidas = $stmt->fetchColumn();

// Total recaudado hoy: ventas de mostrador + anticipos/pagos recibidos hoy en órdenes
$stmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE sucursal_id = ? AND fecha = ? AND estado = 'pagada'");
$stmt->execute([$suc, $hoy]);
$ventasHoy = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(a.monto),0) FROM anticipos a JOIN ordenes o ON o.id = a.orden_id WHERE o.sucursal_id = ? AND DATE(CONVERT_TZ(a.created_at,'+00:00','-06:00')) = ?");
$stmt->execute([$suc, $hoy]);
$ventasHoy += (float)$stmt->fetchColumn();

// Total anticipos pendientes
$stmt = $db->prepare("SELECT COALESCE(SUM(a.monto),0) FROM anticipos a JOIN ordenes o ON o.id = a.orden_id WHERE o.sucursal_id = ? AND o.estado IN ('abierta','en_proceso')");
$stmt->execute([$suc]);
$anticiposPendientes = $stmt->fetchColumn();

// Órdenes abiertas detalle
$stmt = $db->prepare("
    SELECT o.*, 
           CONCAT(c.nombre,' ',c.apellido) as cliente,
           CONCAT(m.marca_texto,' ',m.modelo) as moto,
           CONCAT(u.nombre,' ',u.apellido) as tecnico,
           CASE 
             WHEN o.fecha_salida_esperada < ? AND o.estado IN ('abierta','en_proceso') THEN 'vencida'
             WHEN o.fecha_salida_esperada = ? THEN 'hoy'
             ELSE 'normal'
           END as urgencia
    FROM ordenes o
    JOIN clientes c ON c.id = o.cliente_id
    JOIN motocicletas m ON m.id = o.motocicleta_id
    LEFT JOIN usuarios u ON u.id = o.tecnico_id
    WHERE o.sucursal_id = ? AND o.estado IN ('abierta','en_proceso')
    ORDER BY urgencia DESC, o.fecha_salida_esperada ASC
    LIMIT 20
");
$stmt->execute([$hoy, $hoy, $suc]);
$ordenesDetalle = $stmt->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
    <p class="text-gray-500 text-sm"><?= date('l, d \d\e F \d\e Y') ?> — <?= sanitize($_SESSION['sucursal_nombre']) ?></p>
</div>

<?php
$cards = [
    [
        'valor'  => $ordenesAbiertas,
        'label'  => 'Órdenes abiertas',
        'icono'  => 'fa-clipboard-list',
        'color'  => 'bg-blue-100 text-blue-700',
        'url'    => BASE_URL . '/pages/ordenes/index.php?vista=activas',
    ],
    [
        'valor'  => $motosSalenHoy,
        'label'  => 'Salen hoy',
        'icono'  => 'fa-calendar-check',
        'color'  => 'bg-green-100 text-green-700',
        'url'    => BASE_URL . '/pages/ordenes/index.php?vista=hoy',
    ],
    [
        'valor'  => $motosVencidas,
        'label'  => 'Fecha vencida',
        'icono'  => 'fa-exclamation-triangle',
        'color'  => 'bg-red-100 text-red-700',
        'url'    => BASE_URL . '/pages/ordenes/index.php?vista=vencidas',
    ],
    [
        'valor'  => formatMoney($ventasHoy),
        'label'  => 'Recaudado hoy',
        'icono'  => 'fa-money-bill-wave',
        'color'  => 'bg-yellow-100 text-yellow-700',
        'url'    => BASE_URL . '/pages/reportes/index.php?tipo=anticipos&rango=hoy',
    ],
];
?>

<!-- Cards resumen -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ($cards as $c): ?>
    <a href="<?= $c['url'] ?>"
       class="group bg-white rounded-xl shadow p-5 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all">
        <div class="<?= $c['color'] ?> rounded-full p-3 text-xl flex-shrink-0">
            <i class="fas <?= $c['icono'] ?>"></i>
        </div>
        <div class="min-w-0">
            <div class="text-2xl font-bold text-gray-800 truncate"><?= $c['valor'] ?></div>
            <div class="text-sm text-gray-500"><?= $c['label'] ?></div>
        </div>
        <i class="fas fa-chevron-right ml-auto text-gray-300 group-hover:text-gray-400 text-sm flex-shrink-0"></i>
    </a>
    <?php endforeach; ?>
</div>

<!-- Segunda fila de cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
    <a href="<?= BASE_URL ?>/pages/ordenes/index.php?vista=con_saldo"
       class="group bg-white rounded-xl shadow p-5 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all">
        <div class="bg-purple-100 text-purple-700 rounded-full p-3 text-xl flex-shrink-0"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="min-w-0">
            <div class="text-2xl font-bold text-gray-800 truncate"><?= formatMoney($anticiposPendientes) ?></div>
            <div class="text-sm text-gray-500">Anticipos en órdenes activas</div>
        </div>
        <i class="fas fa-chevron-right ml-auto text-gray-300 group-hover:text-gray-400 text-sm flex-shrink-0"></i>
    </a>

    <a href="<?= BASE_URL ?>/pages/ventas/index.php?fecha=<?= $hoy ?>"
       class="group bg-white rounded-xl shadow p-5 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all">
        <div class="bg-teal-100 text-teal-700 rounded-full p-3 text-xl flex-shrink-0"><i class="fas fa-cash-register"></i></div>
        <div class="min-w-0">
            <div class="text-2xl font-bold text-gray-800 truncate">Ventas de hoy</div>
            <div class="text-sm text-gray-500">Ver el detalle del mostrador</div>
        </div>
        <i class="fas fa-chevron-right ml-auto text-gray-300 group-hover:text-gray-400 text-sm flex-shrink-0"></i>
    </a>
</div>

<!-- Tabla de órdenes activas -->
<div class="bg-white rounded-xl shadow">
    <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Órdenes en taller</h2>
        <a href="<?= BASE_URL ?>/pages/ordenes/create.php"
           class="bg-blue-700 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-800 transition">
            <i class="fas fa-plus mr-1"></i> Nueva orden
        </a>
    </div>
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
                    <th class="px-4 py-3 text-left">Estado</th>
                    <th class="px-4 py-3 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($ordenesDetalle)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No hay órdenes abiertas</td></tr>
                <?php endif; ?>
                <?php foreach ($ordenesDetalle as $o): ?>
                <tr class="hover:bg-gray-50 <?= $o['urgencia'] === 'vencida' ? 'bg-red-50' : ($o['urgencia'] === 'hoy' ? 'bg-green-50' : '') ?>">
                    <td class="px-4 py-3 font-mono font-semibold text-blue-700"><?= sanitize($o['numero_orden']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($o['cliente']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($o['moto']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($o['tecnico'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= formatDate($o['fecha_ingreso']) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($o['urgencia'] === 'vencida'): ?>
                            <span class="text-red-600 font-semibold"><?= formatDate($o['fecha_salida_esperada']) ?> ⚠️</span>
                        <?php elseif ($o['urgencia'] === 'hoy'): ?>
                            <span class="text-green-600 font-semibold">Hoy ✓</span>
                        <?php else: ?>
                            <?= formatDate($o['fecha_salida_esperada']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            <?= $o['estado'] === 'abierta' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700' ?>">
                            <?= ucfirst(str_replace('_', ' ', $o['estado'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="<?= BASE_URL ?>/pages/ordenes/view.php?id=<?= $o['id'] ?>"
                           class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-eye"></i></a>
                        <a href="<?= BASE_URL ?>/pages/ordenes/edit.php?id=<?= $o['id'] ?>"
                           class="text-gray-500 hover:text-gray-700"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
