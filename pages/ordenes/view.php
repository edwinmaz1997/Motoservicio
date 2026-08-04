<?php
$pageTitle = 'Detalle de Orden';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT o.*,
           CONCAT(c.nombre,' ',c.apellido) as cliente, c.telefono as cliente_tel, c.id as cliente_id,
           CONCAT(m.marca_texto,' ',COALESCE(m.modelo,'')) as moto, m.placa, m.color, m.vin, m.id as moto_id,
           CONCAT(t.nombre,' ',t.apellido) as tecnico,
           CONCAT(a.nombre,' ',a.apellido) as asesor,
           s.nombre as sucursal
    FROM ordenes o
    JOIN clientes c ON c.id=o.cliente_id
    JOIN motocicletas m ON m.id=o.motocicleta_id
    LEFT JOIN usuarios t ON t.id=o.tecnico_id
    LEFT JOIN usuarios a ON a.id=o.asesor_id
    JOIN sucursales s ON s.id=o.sucursal_id
    WHERE o.id=? AND o.sucursal_id=?
");
$stmt->execute([$id, $user['sucursal_id']]);
$o = $stmt->fetch();
if (!$o) { flashMessage('error','Orden no encontrada.'); header('Location: index.php'); exit; }

$detalle = $db->prepare("SELECT od.*, p.nombre as producto, p.tipo FROM orden_detalle od JOIN productos p ON p.id=od.producto_id WHERE od.orden_id=?");
$detalle->execute([$id]); $detalle = $detalle->fetchAll();

$anticipos = $db->prepare("SELECT an.*, CONCAT(u.nombre,' ',u.apellido) as cajero FROM anticipos an LEFT JOIN usuarios u ON u.id=an.cajero_id WHERE an.orden_id=? ORDER BY an.created_at");
$anticipos->execute([$id]); $anticipos = $anticipos->fetchAll();

$estadoColors = ['abierta'=>'bg-blue-100 text-blue-700','en_proceso'=>'bg-yellow-100 text-yellow-700','lista'=>'bg-green-100 text-green-700','entregada'=>'bg-gray-100 text-gray-600','cancelada'=>'bg-red-100 text-red-700'];
$estadoLabels = ['abierta'=>'Abierta','en_proceso'=>'En proceso','lista'=>'Lista','entregada'=>'Entregada','cancelada'=>'Cancelada'];
$siguienteEstado = ['abierta'=>'en_proceso','en_proceso'=>'lista','lista'=>'entregada'];
$siguienteLabel  = ['abierta'=>'Iniciar trabajo','en_proceso'=>'Marcar lista','lista'=>'Entregar al cliente'];
?>

<div class="flex items-center gap-3 mb-6 flex-wrap">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($o['numero_orden']) ?></h1>
    <span class="px-3 py-1 rounded-full text-sm font-medium <?= $estadoColors[$o['estado']] ?>">
        <?= $estadoLabels[$o['estado']] ?>
    </span>
    <div class="ml-auto flex gap-2 flex-wrap">
        <?php if (isset($siguienteEstado[$o['estado']])): ?>
        <a href="cambiar_estado.php?id=<?= $o['id'] ?>&estado=<?= $siguienteEstado[$o['estado']] ?>"
           class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700"
           onclick="return confirm('¿<?= $siguienteLabel[$o['estado']] ?>?')">
            <i class="fas fa-arrow-right mr-1"></i> <?= $siguienteLabel[$o['estado']] ?>
        </a>
        <?php endif; ?>
        <?php if (!in_array($o['estado'],['entregada','cancelada'])): ?>
        <a href="edit.php?id=<?= $o['id'] ?>" class="bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-600">
            <i class="fas fa-edit mr-1"></i> Editar
        </a>
        <a href="cancelar.php?id=<?= $o['id'] ?>" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-600"
           onclick="return confirm('¿Cancelar esta orden?')">
            <i class="fas fa-times mr-1"></i> Cancelar
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Info principal -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Datos generales -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Información General</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-500 block">Cliente</span>
                    <a href="../clientes/view.php?id=<?= $o['cliente_id'] ?>" class="font-medium text-blue-600 hover:underline"><?= sanitize($o['cliente']) ?></a>
                    <?php if ($o['cliente_tel']): ?><div class="text-gray-400"><?= sanitize($o['cliente_tel']) ?></div><?php endif; ?>
                </div>
                <div><span class="text-gray-500 block">Motocicleta</span>
                    <span class="font-medium"><?= sanitize($o['moto']) ?></span>
                    <?php if ($o['placa']): ?><div class="text-gray-400"><?= sanitize($o['placa']) ?></div><?php endif; ?>
                </div>
                <div><span class="text-gray-500 block">Técnico</span><span class="font-medium"><?= sanitize($o['tecnico'] ?? '—') ?></span></div>
                <div><span class="text-gray-500 block">Asesor</span><span class="font-medium"><?= sanitize($o['asesor'] ?? '—') ?></span></div>
                <div><span class="text-gray-500 block">Fecha ingreso</span><span class="font-medium"><?= formatDate($o['fecha_ingreso']) ?></span></div>
                <div><span class="text-gray-500 block">Salida esperada</span><span class="font-medium"><?= formatDate($o['fecha_salida_esperada']) ?></span></div>
                <?php if ($o['fecha_salida_real']): ?>
                <div><span class="text-gray-500 block">Salida real</span><span class="font-medium"><?= formatDate($o['fecha_salida_real']) ?></span></div>
                <?php endif; ?>
                <div><span class="text-gray-500 block">Kilometraje</span><span class="font-medium"><?= $o['kilometraje_ingreso'] ? number_format($o['kilometraje_ingreso']).' km' : '—' ?></span></div>
                <div><span class="text-gray-500 block">Sucursal</span><span class="font-medium"><?= sanitize($o['sucursal']) ?></span></div>
            </div>
            <?php if ($o['diagnostico']): ?>
            <div class="mt-4 pt-4 border-t">
                <span class="text-gray-500 text-sm block mb-1">Diagnóstico</span>
                <p class="text-sm"><?= nl2br(sanitize($o['diagnostico'])) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($o['observaciones']): ?>
            <div class="mt-3">
                <span class="text-gray-500 text-sm block mb-1">Observaciones</span>
                <p class="text-sm"><?= nl2br(sanitize($o['observaciones'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Detalle de productos -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Productos y Servicios</h2>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-2 text-left">Descripción</th>
                        <th class="px-3 py-2 text-left">Tipo</th>
                        <th class="px-3 py-2 text-right">Cant.</th>
                        <th class="px-3 py-2 text-right">Precio</th>
                        <th class="px-3 py-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($detalle)): ?>
                    <tr><td colspan="5" class="px-3 py-4 text-center text-gray-400">Sin items</td></tr>
                    <?php endif; ?>
                    <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td class="px-3 py-2 font-medium"><?= sanitize($d['producto']) ?></td>
                        <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full <?= $d['tipo']==='servicio'?'bg-purple-100 text-purple-700':'bg-green-100 text-green-700' ?>"><?= $d['tipo']==='servicio'?'Servicio':'Producto' ?></span></td>
                        <td class="px-3 py-2 text-right"><?= $d['cantidad'] ?></td>
                        <td class="px-3 py-2 text-right"><?= formatMoney($d['precio_unit']) ?></td>
                        <td class="px-3 py-2 text-right font-semibold"><?= formatMoney($d['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="border-t-2">
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-right font-bold">Total:</td>
                        <td class="px-3 py-3 text-right font-bold text-lg"><?= formatMoney($o['total']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Anticipos -->
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700">Anticipos / Pagos</h2>
                <?php if (!in_array($o['estado'],['entregada','cancelada'])): ?>
                <button onclick="document.getElementById('modal-anticipo').classList.remove('hidden')"
                        class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-sm hover:bg-blue-200">
                    <i class="fas fa-plus mr-1"></i> Registrar pago
                </button>
                <?php endif; ?>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-2 text-left">Fecha</th>
                        <th class="px-3 py-2 text-left">Cajero</th>
                        <th class="px-3 py-2 text-left">Método</th>
                        <th class="px-3 py-2 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($anticipos)): ?><tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">Sin pagos registrados</td></tr><?php endif; ?>
                    <?php foreach ($anticipos as $a): ?>
                    <tr>
                        <td class="px-3 py-2"><?= formatDate($a['created_at']) ?></td>
                        <td class="px-3 py-2"><?= sanitize($a['cajero'] ?? '—') ?></td>
                        <td class="px-3 py-2 capitalize"><?= sanitize($a['metodo_pago']) ?></td>
                        <td class="px-3 py-2 text-right font-semibold text-green-600"><?= formatMoney($a['monto']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-4 pt-4 border-t grid grid-cols-3 gap-4 text-sm text-right">
                <div><span class="text-gray-500 block">Total</span><span class="font-bold"><?= formatMoney($o['total']) ?></span></div>
                <div><span class="text-gray-500 block">Pagado</span><span class="font-bold text-green-600"><?= formatMoney($o['anticipo']) ?></span></div>
                <div><span class="text-gray-500 block">Saldo</span><span class="font-bold text-red-600"><?= formatMoney($o['saldo']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Col derecha -->
    <div class="space-y-4 text-sm">
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-gray-500 mb-1">Creado</div>
            <div><?= formatDate($o['created_at']) ?></div>
        </div>
    </div>
</div>

<!-- Modal anticipo -->
<div id="modal-anticipo" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
        <h3 class="font-semibold text-gray-800 mb-4">Registrar Pago / Anticipo</h3>
        <form method="POST" action="anticipo.php">
            <input type="hidden" name="orden_id" value="<?= $o['id'] ?>">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monto *</label>
                    <input type="number" name="monto" step="0.01" min="0.01" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                    <select name="metodo_pago" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <input type="text" name="notas" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" class="bg-blue-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800">Guardar</button>
                <button type="button" onclick="document.getElementById('modal-anticipo').classList.add('hidden')"
                        class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
