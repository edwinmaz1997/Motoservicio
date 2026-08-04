<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

$db  = getDB();
$suc = $user['sucursal_id'];

// Filtros
$tipo      = $_GET['tipo']   ?? 'ventas';
$rango     = $_GET['rango']  ?? 'hoy';
$fechaDesde = $_GET['desde'] ?? date('Y-m-d');
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

// Calcular rango
switch ($rango) {
    case 'hoy':
        $fechaDesde = $fechaHasta = date('Y-m-d');
        break;
    case 'semana':
        $fechaDesde = date('Y-m-d', strtotime('monday this week'));
        $fechaHasta = date('Y-m-d');
        break;
    case 'mes':
        $fechaDesde = date('Y-m-01');
        $fechaHasta = date('Y-m-d');
        break;
    case 'anio':
        $fechaDesde = date('Y-01-01');
        $fechaHasta = date('Y-m-d');
        break;
    case 'personalizado':
        $fechaDesde = $_GET['desde'] ?? date('Y-m-d');
        $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
        break;
}

// ---- DATOS SEGÚN TIPO ----
$rows    = [];
$totales = [];

if ($tipo === 'ventas') {
    $stmt = $db->prepare("
        SELECT v.numero_venta, v.fecha, v.metodo_pago, v.total, v.estado,
               CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')) as cliente,
               CONCAT(u.nombre,' ',u.apellido) as vendedor
        FROM ventas v
        LEFT JOIN clientes c ON c.id=v.cliente_id
        LEFT JOIN usuarios u ON u.id=v.vendedor_id
        WHERE v.sucursal_id=? AND v.fecha BETWEEN ? AND ?
        ORDER BY v.fecha DESC, v.id DESC
    ");
    $stmt->execute([$suc, $fechaDesde, $fechaHasta]);
    $rows = $stmt->fetchAll();

    $totEfectivo = array_sum(array_column(array_filter($rows, fn($r)=>$r['metodo_pago']==='efectivo'&&$r['estado']==='pagada'), 'total'));
    $totTarjeta  = array_sum(array_column(array_filter($rows, fn($r)=>$r['metodo_pago']==='tarjeta'&&$r['estado']==='pagada'), 'total'));
    $totTransf   = array_sum(array_column(array_filter($rows, fn($r)=>$r['metodo_pago']==='transferencia'&&$r['estado']==='pagada'), 'total'));
    $totGeneral  = array_sum(array_column(array_filter($rows, fn($r)=>$r['estado']==='pagada'), 'total'));
    $totales = ['Efectivo'=>$totEfectivo,'Tarjeta'=>$totTarjeta,'Transferencia'=>$totTransf,'Total'=>$totGeneral];
}

elseif ($tipo === 'ordenes') {
    $stmt = $db->prepare("
        SELECT o.numero_orden, o.fecha_ingreso, o.fecha_salida_esperada, o.fecha_salida_real,
               o.estado, o.total, o.anticipo, o.saldo,
               CONCAT(c.nombre,' ',c.apellido) as cliente,
               CONCAT(m.marca_texto,' ',COALESCE(m.modelo,'')) as moto, m.placa,
               CONCAT(t.nombre,' ',t.apellido) as tecnico
        FROM ordenes o
        JOIN clientes c ON c.id=o.cliente_id
        JOIN motocicletas m ON m.id=o.motocicleta_id
        LEFT JOIN usuarios t ON t.id=o.tecnico_id
        WHERE o.sucursal_id=? AND o.fecha_ingreso BETWEEN ? AND ?
        ORDER BY o.fecha_ingreso DESC, o.id DESC
    ");
    $stmt->execute([$suc, $fechaDesde, $fechaHasta]);
    $rows = $stmt->fetchAll();

    $totales = [
        'Total facturado' => array_sum(array_column($rows,'total')),
        'Anticipos'       => array_sum(array_column($rows,'anticipo')),
        'Saldo pendiente' => array_sum(array_column($rows,'saldo')),
    ];
}

elseif ($tipo === 'anticipos') {
    $stmt = $db->prepare("
        SELECT a.created_at, a.monto, a.metodo_pago, a.notas,
               o.numero_orden,
               CONCAT(c.nombre,' ',c.apellido) as cliente,
               CONCAT(u.nombre,' ',u.apellido) as cajero
        FROM anticipos a
        JOIN ordenes o ON o.id=a.orden_id
        JOIN clientes c ON c.id=o.cliente_id
        LEFT JOIN usuarios u ON u.id=a.cajero_id
        WHERE o.sucursal_id=? AND DATE(a.created_at) BETWEEN ? AND ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$suc, $fechaDesde, $fechaHasta]);
    $rows = $stmt->fetchAll();
    $totales = ['Total cobrado' => array_sum(array_column($rows,'monto'))];
}

elseif ($tipo === 'compras') {
    $stmt = $db->prepare("
        SELECT c.numero_compra, c.fecha, c.total, c.estado,
               CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as proveedor
        FROM compras c
        LEFT JOIN proveedores p ON p.id=c.proveedor_id
        WHERE c.sucursal_id=? AND c.fecha BETWEEN ? AND ?
        ORDER BY c.fecha DESC, c.id DESC
    ");
    $stmt->execute([$suc, $fechaDesde, $fechaHasta]);
    $rows = $stmt->fetchAll();
    $totales = ['Total compras' => array_sum(array_column(array_filter($rows,fn($r)=>$r['estado']!=='anulada'),'total'))];
}

elseif ($tipo === 'clientes') {
    $stmt = $db->prepare("
        SELECT c.nombre, c.apellido, c.telefono, c.nit, c.correo, c.created_at,
               (SELECT COUNT(*) FROM motocicletas WHERE cliente_id=c.id) as n_motos,
               (SELECT COUNT(*) FROM ordenes WHERE cliente_id=c.id) as n_ordenes,
               (SELECT COALESCE(SUM(total),0) FROM ordenes WHERE cliente_id=c.id AND estado='entregada') as total_gastado
        FROM clientes c
        WHERE c.sucursal_id=?
        ORDER BY total_gastado DESC
    ");
    $stmt->execute([$suc]);
    $rows = $stmt->fetchAll();
    $totales = ['Total clientes' => count($rows)];
}

elseif ($tipo === 'inventario') {
    $stmt = $db->query("
        SELECT p.nombre, p.tipo, p.precio, p.stock, p.stock_minimo, p.unidad,
               c.nombre as categoria
        FROM productos p
        LEFT JOIN categorias c ON c.id=p.categoria_id
        WHERE p.activo=1
        ORDER BY p.tipo, p.nombre
    ");
    $rows = $stmt->fetchAll();
    $totales = [
        'Productos estándar' => count(array_filter($rows,fn($r)=>$r['tipo']==='estandar')),
        'Servicios'          => count(array_filter($rows,fn($r)=>$r['tipo']==='servicio')),
        'Bajo stock'         => count(array_filter($rows,fn($r)=>$r['tipo']==='estandar'&&$r['stock']<=$r['stock_minimo']&&$r['stock_minimo']>0)),
    ];
}

elseif ($tipo === 'tecnicos') {
    $stmt = $db->prepare("
        SELECT CONCAT(u.nombre,' ',u.apellido) as tecnico,
               COUNT(o.id) as total_ordenes,
               SUM(CASE WHEN o.estado='entregada' THEN 1 ELSE 0 END) as entregadas,
               SUM(CASE WHEN o.estado IN ('abierta','en_proceso','lista') THEN 1 ELSE 0 END) as activas,
               COALESCE(SUM(o.total),0) as monto_total
        FROM usuarios u
        LEFT JOIN ordenes o ON o.tecnico_id=u.id AND o.fecha_ingreso BETWEEN ? AND ?
        WHERE u.sucursal_id=? AND u.rol_id=?
        GROUP BY u.id, u.nombre, u.apellido
        ORDER BY total_ordenes DESC
    ");
    $stmt->execute([$fechaDesde, $fechaHasta, $suc, ROL_TECNICO]);
    $rows = $stmt->fetchAll();
    $totales = [];
}

$pageTitle = 'Reportes';
require_once __DIR__ . '/../../includes/header.php';

$tipos = [
    'ventas'     => ['Ventas',      'fa-cash-register'],
    'ordenes'    => ['Órdenes',     'fa-clipboard-list'],
    'anticipos'  => ['Cobros / Anticipos', 'fa-hand-holding-usd'],
    'compras'    => ['Compras',     'fa-truck'],
    'clientes'   => ['Clientes',    'fa-users'],
    'inventario' => ['Inventario',  'fa-box'],
    'tecnicos'   => ['Técnicos',    'fa-user-cog'],
];
?>

<div class="flex items-center justify-between mb-5">
    <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Reportes</h1>
    <button onclick="window.print()" class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-800">
        <i class="fas fa-print mr-1"></i> Imprimir
    </button>
</div>

<!-- Tabs tipo -->
<div class="flex gap-1 flex-wrap mb-4">
    <?php foreach ($tipos as $key => [$label, $icon]): ?>
    <a href="?tipo=<?= $key ?>&rango=<?= $rango ?>&desde=<?= $fechaDesde ?>&hasta=<?= $fechaHasta ?>"
       class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition
              <?= $tipo===$key ? 'bg-blue-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' ?>">
        <i class="fas <?= $icon ?> text-xs"></i> <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filtros de fecha -->
<form method="GET" class="bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="tipo" value="<?= $tipo ?>">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Rango</label>
        <select name="rango" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="hoy"          <?= $rango==='hoy'?'selected':'' ?>>Hoy</option>
            <option value="semana"       <?= $rango==='semana'?'selected':'' ?>>Esta semana</option>
            <option value="mes"          <?= $rango==='mes'?'selected':'' ?>>Este mes</option>
            <option value="anio"         <?= $rango==='anio'?'selected':'' ?>>Este año</option>
            <option value="personalizado"<?= $rango==='personalizado'?'selected':'' ?>>Personalizado</option>
        </select>
    </div>
    <?php if ($rango==='personalizado'): ?>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
        <input type="date" name="desde" value="<?= $fechaDesde ?>"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
        <input type="date" name="hasta" value="<?= $fechaHasta ?>"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-800">Filtrar</button>
    <?php endif; ?>
    <?php if (!in_array($tipo,['clientes','inventario'])): ?>
    <div class="text-xs text-gray-400 self-center">
        <?= formatDate($fechaDesde) ?><?= $fechaDesde!==$fechaHasta ? ' — '.formatDate($fechaHasta) : '' ?>
    </div>
    <?php endif; ?>
</form>

<!-- Cards totales -->
<?php if (!empty($totales)): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <?php foreach ($totales as $label => $val): ?>
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-xs text-gray-500 mb-1"><?= $label ?></div>
        <div class="text-xl font-bold text-gray-800">
            <?= is_float($val) || (is_numeric($val) && str_contains((string)$val,'.')) ? formatMoney($val) : $val ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tabla de resultados -->
<div class="bg-white rounded-xl shadow overflow-hidden print:shadow-none">
    <div class="px-4 py-3 border-b flex items-center justify-between">
        <span class="font-semibold text-gray-700 text-sm"><?= $tipos[$tipo][0] ?> — <?= count($rows) ?> registros</span>
    </div>
    <div class="overflow-x-auto">
    <?php if ($tipo==='ventas'): ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left"># Venta</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Cliente</th><th class="px-4 py-2 text-left">Vendedor</th><th class="px-4 py-2 text-left">Método</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-left">Estado</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50 <?= $r['estado']==='anulada'?'opacity-40':'' ?>">
                <td class="px-4 py-2 font-mono text-blue-700"><a href="../ventas/view.php?id=" class=""><?= sanitize($r['numero_venta']) ?></a></td>
                <td class="px-4 py-2"><?= formatDate($r['fecha']) ?></td>
                <td class="px-4 py-2"><?= sanitize(trim($r['cliente'])?:' Consumidor final') ?></td>
                <td class="px-4 py-2"><?= sanitize($r['vendedor']??'—') ?></td>
                <td class="px-4 py-2 capitalize"><?= sanitize($r['metodo_pago']) ?></td>
                <td class="px-4 py-2 text-right font-semibold"><?= formatMoney($r['total']) ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-xs <?= $r['estado']==='pagada'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= ucfirst($r['estado']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin registros</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tipo==='ordenes'): ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left"># Orden</th><th class="px-4 py-2 text-left">Ingreso</th><th class="px-4 py-2 text-left">Cliente</th><th class="px-4 py-2 text-left">Moto</th><th class="px-4 py-2 text-left">Técnico</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-right">Anticipo</th><th class="px-4 py-2 text-right">Saldo</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php
            $estadoColors=['abierta'=>'bg-blue-100 text-blue-700','en_proceso'=>'bg-yellow-100 text-yellow-700','lista'=>'bg-green-100 text-green-700','entregada'=>'bg-gray-100 text-gray-600','cancelada'=>'bg-red-100 text-red-700'];
            foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-blue-700"><a href="../ordenes/view.php?id="><?= sanitize($r['numero_orden']) ?></a></td>
                <td class="px-4 py-2"><?= formatDate($r['fecha_ingreso']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['cliente']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['moto']) ?> <?= $r['placa']?'<span class="text-gray-400 text-xs">('.$r['placa'].')</span>':'' ?></td>
                <td class="px-4 py-2"><?= sanitize($r['tecnico']??'—') ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-xs <?= $estadoColors[$r['estado']]??'' ?>"><?= ucfirst(str_replace('_',' ',$r['estado'])) ?></span></td>
                <td class="px-4 py-2 text-right font-semibold"><?= formatMoney($r['total']) ?></td>
                <td class="px-4 py-2 text-right text-green-600"><?= formatMoney($r['anticipo']) ?></td>
                <td class="px-4 py-2 text-right text-red-600"><?= formatMoney($r['saldo']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Sin registros</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tipo==='anticipos'): ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left"># Orden</th><th class="px-4 py-2 text-left">Cliente</th><th class="px-4 py-2 text-left">Cajero</th><th class="px-4 py-2 text-left">Método</th><th class="px-4 py-2 text-right">Monto</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= formatDate($r['created_at']) ?></td>
                <td class="px-4 py-2 font-mono text-blue-700"><?= sanitize($r['numero_orden']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['cliente']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['cajero']??'—') ?></td>
                <td class="px-4 py-2 capitalize"><?= sanitize($r['metodo_pago']) ?></td>
                <td class="px-4 py-2 text-right font-semibold text-green-600"><?= formatMoney($r['monto']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Sin registros</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tipo==='compras'): ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left"># Compra</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Proveedor</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-left">Estado</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50 <?= $r['estado']==='anulada'?'opacity-40':'' ?>">
                <td class="px-4 py-2 font-mono text-blue-700"><?= sanitize($r['numero_compra']) ?></td>
                <td class="px-4 py-2"><?= formatDate($r['fecha']) ?></td>
                <td class="px-4 py-2"><?= sanitize(trim($r['proveedor'])?:'—') ?></td>
                <td class="px-4 py-2 text-right font-semibold"><?= formatMoney($r['total']) ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-xs <?= $r['estado']==='recibida'?'bg-green-100 text-green-700':($r['estado']==='pendiente'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-700') ?>"><?= ucfirst($r['estado']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin registros</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tipo==='clientes'): ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left">Cliente</th><th class="px-4 py-2 text-left">Teléfono</th><th class="px-4 py-2 text-left">NIT</th><th class="px-4 py-2 text-center">Motos</th><th class="px-4 py-2 text-center">Órdenes</th><th class="px-4 py-2 text-right">Total gastado</th><th class="px-4 py-2 text-left">Registro</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-medium"><a href="../clientes/view.php?id=" class="text-blue-600 hover:underline"><?= sanitize($r['nombre'].' '.$r['apellido']) ?></a></td>
                <td class="px-4 py-2"><?= sanitize($r['telefono']??'—') ?></td>
                <td class="px-4 py-2"><?= sanitize($r['nit']??'—') ?></td>
                <td class="px-4 py-2 text-center"><?= $r['n_motos'] ?></td>
                <td class="px-4 py-2 text-center"><?= $r['n_ordenes'] ?></td>
                <td class="px-4 py-2 text-right font-semibold"><?= formatMoney($r['total_gastado']) ?></td>
                <td class="px-4 py-2 text-gray-400"><?= formatDate($r['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin registros</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tipo==='inventario'): ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left">Producto</th><th class="px-4 py-2 text-left">Categoría</th><th class="px-4 py-2 text-left">Tipo</th><th class="px-4 py-2 text-right">Precio</th><th class="px-4 py-2 text-center">Stock</th><th class="px-4 py-2 text-center">Mín.</th><th class="px-4 py-2 text-left">Unidad</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
            <?php $alerta = $r['tipo']==='estandar' && $r['stock_minimo']>0 && $r['stock']<=$r['stock_minimo']; ?>
            <tr class="hover:bg-gray-50 <?= $alerta?'bg-orange-50':'' ?>">
                <td class="px-4 py-2 font-medium"><?= sanitize($r['nombre']) ?> <?= $alerta?'<span class="text-orange-500 text-xs">⚠ bajo stock</span>':'' ?></td>
                <td class="px-4 py-2"><?= sanitize($r['categoria']??'—') ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-xs <?= $r['tipo']==='servicio'?'bg-purple-100 text-purple-700':'bg-green-100 text-green-700' ?>"><?= $r['tipo']==='servicio'?'Servicio':'Estándar' ?></span></td>
                <td class="px-4 py-2 text-right"><?= formatMoney($r['precio']) ?></td>
                <td class="px-4 py-2 text-center <?= $alerta?'text-red-600 font-bold':'' ?>"><?= $r['tipo']==='servicio'?'—':$r['stock'] ?></td>
                <td class="px-4 py-2 text-center"><?= $r['tipo']==='servicio'?'—':$r['stock_minimo'] ?></td>
                <td class="px-4 py-2"><?= sanitize($r['unidad']??'') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin registros</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($tipo==='tecnicos'): ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left">Técnico</th><th class="px-4 py-2 text-center">Total órdenes</th><th class="px-4 py-2 text-center">Entregadas</th><th class="px-4 py-2 text-center">Activas</th><th class="px-4 py-2 text-right">Monto facturado</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-medium"><?= sanitize($r['tecnico']) ?></td>
                <td class="px-4 py-2 text-center"><?= $r['total_ordenes'] ?></td>
                <td class="px-4 py-2 text-center text-green-600"><?= $r['entregadas'] ?></td>
                <td class="px-4 py-2 text-center text-yellow-600"><?= $r['activas'] ?></td>
                <td class="px-4 py-2 text-right font-semibold"><?= formatMoney($r['monto_total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin técnicos registrados</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>

<style>
@media print {
    aside, header, form, .no-print { display: none !important; }
    main { margin: 0 !important; padding: 0 !important; }
    table { font-size: 11px; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
