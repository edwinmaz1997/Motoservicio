<?php
$pageTitle = 'Nueva Compra';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
$db = getDB();
$proveedores = $db->query("SELECT * FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
$productos   = $db->query("SELECT * FROM productos WHERE activo=1 AND tipo='estandar' ORDER BY nombre")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proveedorId = (int)($_POST['proveedor_id']??0) ?: null;
    $fecha       = $_POST['fecha'] ?? date('Y-m-d');
    $estado      = $_POST['estado'] ?? 'recibida';
    $notas       = trim($_POST['notas']??'');
    $items       = $_POST['items'] ?? [];
    if (empty($items)) $errors[] = 'Agrega al menos un producto.';
    if (empty($errors)) {
        $total = 0;
        foreach ($items as $item) $total += (float)($item['precio']??0) * (float)($item['cantidad']??1);
        $numero = generateNumero('CMP-','compras','numero_compra');
        $db->prepare("INSERT INTO compras (sucursal_id,proveedor_id,numero_compra,fecha,total,estado,notas) VALUES (?,?,?,?,?,?,?)")
           ->execute([$user['sucursal_id'],$proveedorId,$numero,$fecha,$total,$estado,$notas]);
        $compraId = $db->lastInsertId();
        foreach ($items as $item) {
            if (empty($item['producto_id'])) continue;
            $cant = (float)($item['cantidad']??1); $precio=(float)($item['precio']??0); $sub=$cant*$precio;
            $db->prepare("INSERT INTO compra_detalle (compra_id,producto_id,cantidad,precio_unit,subtotal) VALUES (?,?,?,?,?)")->execute([$compraId,$item['producto_id'],$cant,$precio,$sub]);
            if ($estado==='recibida') $db->prepare("UPDATE productos SET stock=stock+? WHERE id=?")->execute([$cant,$item['producto_id']]);
        }
        flashMessage('success',"Compra $numero registrada.");
        header('Location: view.php?id='.$compraId); exit;
    }
}
$pageTitle = 'Nueva Compra';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Nueva Compra</h1>
</div>
<?php if ($errors): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div><?php endif; ?>

<form method="POST" x-data="compraForm()">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Datos de la Compra</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
                    <select name="proveedor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Sin proveedor —</option>
                        <?php foreach ($proveedores as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize($p['nombre'].' '.($p['apellido']??'')) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="estado" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="recibida">Recibida (suma al stock)</option>
                        <option value="pendiente">Pendiente (no suma stock)</option>
                    </select></div>
                <div class="sm:col-span-3"><label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <input type="text" name="notas" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700">Productos</h2>
                <button type="button" @click="agregarItem()" class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-sm hover:bg-blue-200"><i class="fas fa-plus mr-1"></i> Agregar</button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 w-24">Cant.</th><th class="px-3 py-2 w-32">Precio unit.</th><th class="px-3 py-2 w-32 text-right">Subtotal</th><th class="px-3 py-2 w-8"></th></tr>
                </thead>
                <tbody>
                    <template x-for="(item,index) in items" :key="index">
                        <tr class="border-t">
                            <td class="px-3 py-2"><select :name="'items['+index+'][producto_id]'" x-model="item.producto_id" @change="onProductoChange(index)" class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"><option value="">— Seleccionar —</option><?php foreach ($productos as $p): ?><option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>"><?= sanitize($p['nombre']) ?></option><?php endforeach; ?></select></td>
                            <td class="px-3 py-2"><input type="number" :name="'items['+index+'][cantidad]'" x-model="item.cantidad" @input="calcularSubtotal(index)" min="0.1" step="0.1" class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"></td>
                            <td class="px-3 py-2"><input type="number" :name="'items['+index+'][precio]'" x-model="item.precio" @input="calcularSubtotal(index)" min="0" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"></td>
                            <td class="px-3 py-2 text-right font-semibold" x-text="'Q '+item.subtotal.toFixed(2)"></td>
                            <td class="px-3 py-2"><button type="button" @click="items.splice(index,1);calcularTotal()" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </template>
                    <tr x-show="items.length===0"><td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">Sin productos</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div><div class="bg-white rounded-xl shadow p-6 sticky top-6">
        <h2 class="font-semibold text-gray-700 mb-4">Resumen</h2>
        <div class="border-t pt-3 flex justify-between font-bold text-lg"><span>Total</span><span x-text="'Q '+total.toFixed(2)"></span></div>
        <button type="submit" class="mt-6 w-full bg-blue-700 text-white py-3 rounded-lg hover:bg-blue-800 font-medium text-sm"><i class="fas fa-save mr-1"></i> Registrar Compra</button>
        <a href="index.php" class="mt-2 block text-center text-gray-500 text-sm">Cancelar</a>
    </div></div>
</div>
</form>
<script>
const productosData = <?= json_encode(array_column($productos,null,'id')) ?>;
function compraForm(){return{items:[],total:0,agregarItem(){this.items.push({producto_id:'',cantidad:1,precio:0,subtotal:0})},onProductoChange(i){const pid=this.items[i].producto_id;if(pid&&productosData[pid]){this.items[i].precio=parseFloat(productosData[pid].precio);this.calcularSubtotal(i)}},calcularSubtotal(i){this.items[i].subtotal=parseFloat(this.items[i].cantidad||0)*parseFloat(this.items[i].precio||0);this.calcularTotal()},calcularTotal(){this.total=this.items.reduce((s,i)=>s+(i.subtotal||0),0)}}}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
