<?php
$pageTitle = 'Detalle Compra';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB(); $id=(int)($_GET['id']??0);
$stmt=$db->prepare("SELECT c.*,CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as proveedor FROM compras c LEFT JOIN proveedores p ON p.id=c.proveedor_id WHERE c.id=? AND c.sucursal_id=?");
$stmt->execute([$id,$user['sucursal_id']]); $c=$stmt->fetch();
if(!$c){flashMessage('error','Compra no encontrada.');header('Location: index.php');exit;}
$detalle=$db->prepare("SELECT cd.*,p.nombre as producto FROM compra_detalle cd JOIN productos p ON p.id=cd.producto_id WHERE cd.compra_id=?");
$detalle->execute([$id]);$detalle=$detalle->fetchAll();
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($c['numero_compra']) ?></h1>
    <span class="px-3 py-1 rounded-full text-sm font-medium <?= $c['estado']==='recibida'?'bg-green-100 text-green-700':($c['estado']==='pendiente'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-700') ?>"><?= ucfirst($c['estado']) ?></span>
    <?php if ($c['estado']!=='anulada'): ?>
    <a href="anular.php?id=<?= $c['id'] ?>" class="ml-auto bg-red-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-600" onclick="return confirm('¿Anular esta compra?')"><i class="fas fa-ban mr-1"></i> Anular</a>
    <?php endif; ?>
</div>
<div class="bg-white rounded-xl shadow p-6 max-w-3xl">
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm mb-6">
        <div><span class="text-gray-500 block">Proveedor</span><span class="font-medium"><?= sanitize(trim($c['proveedor'])?: '—') ?></span></div>
        <div><span class="text-gray-500 block">Fecha</span><span class="font-medium"><?= formatDate($c['fecha']) ?></span></div>
        <div><span class="text-gray-500 block">Estado</span><span class="font-medium capitalize"><?= $c['estado'] ?></span></div>
        <?php if($c['notas']): ?><div class="sm:col-span-3"><span class="text-gray-500 block">Notas</span><span><?= sanitize($c['notas']) ?></span></div><?php endif; ?>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">Precio unit.</th><th class="px-3 py-2 text-right">Subtotal</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach($detalle as $d): ?>
            <tr><td class="px-3 py-2"><?= sanitize($d['producto']) ?></td><td class="px-3 py-2 text-right"><?= $d['cantidad'] ?></td><td class="px-3 py-2 text-right"><?= formatMoney($d['precio_unit']) ?></td><td class="px-3 py-2 text-right font-semibold"><?= formatMoney($d['subtotal']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="border-t-2"><tr><td colspan="3" class="px-3 py-3 text-right font-bold">Total:</td><td class="px-3 py-3 text-right font-bold text-lg"><?= formatMoney($c['total']) ?></td></tr></tfoot>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
