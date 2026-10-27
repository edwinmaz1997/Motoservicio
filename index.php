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

<?php
$dias  = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
$meses = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril','May'=>'mayo','June'=>'junio',
          'July'=>'julio','August'=>'agosto','September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];
$fechaLarga = $dias[date('l')] . ', ' . date('j') . ' de ' . $meses[date('F')];
?>
<div class="flex items-end justify-between gap-4 flex-wrap mb-6">
    <div>
        <h1 class="text-[22px] lg:text-[25px] font-semibold text-slate-900 tracking-tight">
            Hola, <?= sanitize($user['nombre']) ?>
        </h1>
        <p class="text-slate-500 text-[13.5px] mt-0.5">
            <?= $fechaLarga ?> · <?= sanitize($_SESSION['sucursal_nombre']) ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/pages/ordenes/create.php"
       class="bg-blue-700 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-[10px] hover:bg-blue-800 flex items-center gap-2">
        <i class="fas fa-plus text-[11px]"></i> Nueva orden
    </a>
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
        'url'    => BASE_URL . '/pages/reportes/index.php?tipo=caja&rango=hoy',
    ],
];
?>

<!-- Indicadores del día -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
    <?php foreach ($cards as $c): ?>
    <a href="<?= $c['url'] ?>"
       class="group bg-white rounded-xl p-4 lg:p-5 block hover:border-slate-300 transition-colors">
        <div class="flex items-start justify-between mb-3">
            <span class="<?= $c['color'] ?> w-9 h-9 rounded-lg flex items-center justify-center text-[14px]">
                <i class="fas <?= $c['icono'] ?>"></i>
            </span>
            <i class="fas fa-arrow-right text-[11px] text-slate-300 group-hover:text-slate-500 transition-colors"></i>
        </div>
        <div class="text-[26px] leading-none font-semibold text-slate-900 tabular-nums truncate"><?= $c['valor'] ?></div>
        <div class="text-[12.5px] text-slate-500 mt-1.5"><?= $c['label'] ?></div>
    </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-7">
    <a href="<?= BASE_URL ?>/pages/ordenes/index.php?vista=con_saldo"
       class="group bg-white rounded-xl p-4 lg:p-5 flex items-center gap-4 hover:border-slate-300 transition-colors">
        <span class="bg-purple-100 text-purple-700 w-9 h-9 rounded-lg flex items-center justify-center text-[14px] flex-shrink-0">
            <i class="fas fa-hand-holding-dollar"></i>
        </span>
        <div class="min-w-0 flex-1">
            <div class="text-[19px] font-semibold text-slate-900 tabular-nums truncate leading-tight"><?= formatMoney($anticiposPendientes) ?></div>
            <div class="text-[12.5px] text-slate-500">Cobrado en órdenes aún abiertas</div>
        </div>
        <i class="fas fa-arrow-right text-[11px] text-slate-300 group-hover:text-slate-500 flex-shrink-0 transition-colors"></i>
    </a>

    <a href="<?= BASE_URL ?>/pages/ventas/index.php?fecha=<?= $hoy ?>"
       class="group bg-white rounded-xl p-4 lg:p-5 flex items-center gap-4 hover:border-slate-300 transition-colors">
        <span class="bg-teal-100 text-teal-700 w-9 h-9 rounded-lg flex items-center justify-center text-[14px] flex-shrink-0">
            <i class="fas fa-cash-register"></i>
        </span>
        <div class="min-w-0 flex-1">
            <div class="text-[19px] font-semibold text-slate-900 leading-tight">Ventas de hoy</div>
            <div class="text-[12.5px] text-slate-500">Detalle del mostrador</div>
        </div>
        <i class="fas fa-arrow-right text-[11px] text-slate-300 group-hover:text-slate-500 flex-shrink-0 transition-colors"></i>
    </a>
</div>

<!-- Órdenes en taller -->
<div class="bg-white rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-[15px] font-semibold text-slate-900 tracking-tight">En el taller</h2>
            <p class="text-[12.5px] text-slate-500 mt-0.5"><?= count($ordenesDetalle) ?> motos sin entregar</p>
        </div>
        <a href="<?= BASE_URL ?>/pages/ordenes/index.php?vista=activas"
           class="text-[13px] text-blue-700 hover:underline flex-shrink-0">Ver todas</a>
    </div>

    <?php if (empty($ordenesDetalle)): ?>
    <div class="px-5 py-14 text-center">
        <div class="w-11 h-11 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-clipboard-check"></i>
        </div>
        <p class="text-[14px] font-medium text-slate-700">No hay motos pendientes</p>
        <p class="text-[13px] text-slate-500 mt-1 mb-5">Cuando ingrese una, aparecerá aquí.</p>
        <a href="<?= BASE_URL ?>/pages/ordenes/create.php"
           class="inline-flex items-center gap-2 bg-blue-700 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-[10px] hover:bg-blue-800">
            <i class="fas fa-plus text-[11px]"></i> Ingresar una moto
        </a>
    </div>
    <?php else: ?>

    <!-- Móvil: tarjetas -->
    <div class="lg:hidden divide-y divide-slate-100">
        <?php foreach ($ordenesDetalle as $o): ?>
        <a href="<?= BASE_URL ?>/pages/ordenes/view.php?id=<?= $o['id'] ?>" class="block px-4 py-3.5 active:bg-slate-50">
            <div class="flex items-center justify-between gap-3 mb-1">
                <span class="font-mono text-[13px] font-semibold text-blue-700"><?= sanitize($o['numero_orden']) ?></span>
                <?php if ($o['urgencia']==='vencida'): ?>
                    <span class="text-[11.5px] font-medium text-red-700">Vencida · <?= formatDate($o['fecha_salida_esperada']) ?></span>
                <?php elseif ($o['urgencia']==='hoy'): ?>
                    <span class="text-[11.5px] font-medium text-emerald-700">Sale hoy</span>
                <?php else: ?>
                    <span class="text-[11.5px] text-slate-400"><?= formatDate($o['fecha_salida_esperada']) ?></span>
                <?php endif; ?>
            </div>
            <div class="text-[14px] text-slate-900 font-medium truncate"><?= sanitize($o['cliente']) ?></div>
            <div class="text-[12.5px] text-slate-500 truncate">
                <?= sanitize($o['moto']) ?><?= $o['tecnico'] ? ' · ' . sanitize($o['tecnico']) : '' ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Escritorio: tabla -->
    <div class="hidden lg:block">
        <table>
            <thead>
                <tr>
                    <th class="text-left">Orden</th>
                    <th class="text-left">Cliente</th>
                    <th class="text-left">Moto</th>
                    <th class="text-left">Técnico</th>
                    <th class="text-left">Ingreso</th>
                    <th class="text-left">Entrega</th>
                    <th class="text-left">Estado</th>
                    <th class="w-20"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenesDetalle as $o): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/pages/ordenes/view.php?id=<?= $o['id'] ?>"
                           class="font-mono font-semibold text-blue-700 hover:underline"><?= sanitize($o['numero_orden']) ?></a>
                    </td>
                    <td class="text-slate-900 font-medium"><?= sanitize($o['cliente']) ?></td>
                    <td><?= sanitize($o['moto']) ?></td>
                    <td><?= sanitize($o['tecnico'] ?: '—') ?></td>
                    <td class="text-slate-500"><?= formatDate($o['fecha_ingreso']) ?></td>
                    <td>
                        <?php if ($o['urgencia'] === 'vencida'): ?>
                            <span class="text-red-700 font-medium"><?= formatDate($o['fecha_salida_esperada']) ?></span>
                            <span class="text-[11px] text-red-600 block leading-tight">vencida</span>
                        <?php elseif ($o['urgencia'] === 'hoy'): ?>
                            <span class="text-emerald-700 font-medium">Hoy</span>
                        <?php else: ?>
                            <span class="text-slate-500"><?= formatDate($o['fecha_salida_esperada']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="rounded-full text-xs <?= $o['estado'] === 'abierta' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700' ?>">
                            <?= $o['estado'] === 'abierta' ? 'Abierta' : 'En proceso' ?>
                        </span>
                    </td>
                    <td class="text-right">
                        <a href="<?= BASE_URL ?>/pages/ordenes/view.php?id=<?= $o['id'] ?>" title="Ver"
                           class="inline-flex w-7 h-7 items-center justify-center rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i class="fas fa-eye text-[12px]"></i></a>
                        <a href="<?= BASE_URL ?>/pages/ordenes/edit.php?id=<?= $o['id'] ?>" title="Editar"
                           class="inline-flex w-7 h-7 items-center justify-center rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i class="fas fa-pen text-[11px]"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
