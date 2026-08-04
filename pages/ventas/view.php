<?php
$pageTitle = 'Detalle Venta';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT v.*, CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')) as cliente, CONCAT(u.nombre,' ',u.apellido) as vendedor FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id LEFT JOIN usuarios u ON u.id=v.vendedor_id WHERE v.id=? AND v.sucursal_id=?");
$stmt->execute([$id,$user['sucursal_id']]); $v=$stmt->fetch();
if (!$v) { flashMessage('error','Venta no encontrada.'); header('Location: index.php'); exit; }
$detalle = $db->prepare("SELECT vd.*, p.nombre as producto FROM venta_detalle vd JOIN productos p ON p.id=vd.producto_id WHERE vd.venta_id=?");
$detalle->execute([$id]); $detalle=$detalle->fetchAll();
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($v['numero_venta']) ?></h1>
    <span class="px-3 py-1 rounded-full text-sm font-medium <?= $v['estado']==='pagada'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= ucfirst($v['estado']) ?></span>
    <?php if ($v['estado']==='pagada'): ?>
    <a href="anular.php?id=<?= $v['id'] ?>" class="ml-auto bg-red-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-600" onclick="return confirm('¿Anular esta venta?')"><i class="fas fa-ban mr-1"></i> Anular</a>
    <?php endif; ?>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow p-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-500 block">Cliente</span><span class="font-medium"><?= sanitize(trim($v['cliente']) ?: 'Consumidor final') ?></span></div>
                <div><span class="text-gray-500 block">Vendedor</span><span class="font-medium"><?= sanitize($v['vendedor']??'—') ?></span></div>
                <div><span class="text-gray-500 block">Fecha</span><span class="font-medium"><?= formatDate($v['fecha']) ?></span></div>
                <div><span class="text-gray-500 block">Método de pago</span><span class="font-medium capitalize"><?= sanitize($v['metodo_pago']) ?></span></div>
                <?php if ($v['notas']): ?><div class="sm:col-span-2"><span class="text-gray-500 block">Notas</span><span><?= sanitize($v['notas']) ?></span></div><?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Productos</h2>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2 text-right">Subtotal</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($detalle as $d): ?>
                    <tr><td class="px-3 py-2"><?= sanitize($d['producto']) ?></td><td class="px-3 py-2 text-right"><?= $d['cantidad'] ?></td><td class="px-3 py-2 text-right"><?= formatMoney($d['precio_unit']) ?></td><td class="px-3 py-2 text-right font-semibold"><?= formatMoney($d['subtotal']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="border-t-2">
                    <tr><td colspan="3" class="px-3 py-3 text-right font-bold">Total:</td><td class="px-3 py-3 text-right font-bold text-lg"><?= formatMoney($v['total']) ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
