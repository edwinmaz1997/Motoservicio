<?php
$pageTitle = 'Nueva Venta';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

$db = getDB();
$clientes  = $db->prepare("SELECT * FROM clientes WHERE sucursal_id=? AND activo=1 ORDER BY nombre,apellido");
$clientes->execute([$user['sucursal_id']]); $clientes = $clientes->fetchAll();
$productos = $db->query("SELECT * FROM productos WHERE activo=1 ORDER BY tipo,nombre")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId  = (int)($_POST['cliente_id'] ?? 0) ?: null;
    $metodo     = $_POST['metodo_pago'] ?? 'efectivo';
    $notas      = trim($_POST['notas'] ?? '');
    $fecha      = $_POST['fecha'] ?? date('Y-m-d');
    $items      = $_POST['items'] ?? [];

    if (empty($items)) $errors[] = 'Agrega al menos un producto.';

    if (empty($errors)) {
        $total  = 0;
        foreach ($items as $item) $total += (float)($item['precio']??0) * (float)($item['cantidad']??1);

        $numero = generateNumero('VTA-', 'ventas', 'numero_venta');
        $db->prepare("INSERT INTO ventas (sucursal_id,cliente_id,vendedor_id,numero_venta,fecha,total,metodo_pago,notas) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$user['sucursal_id'],$clienteId,$user['id'],$numero,$fecha,$total,$metodo,$notas]);
        $ventaId = $db->lastInsertId();

        foreach ($items as $item) {
            if (empty($item['producto_id'])) continue;
            $cant  = (float)($item['cantidad']??1);
            $precio= (float)($item['precio']??0);
            $sub   = $cant * $precio;
            $db->prepare("INSERT INTO venta_detalle (venta_id,producto_id,cantidad,precio_unit,subtotal) VALUES (?,?,?,?,?)")
               ->execute([$ventaId,$item['producto_id'],$cant,$precio,$sub]);
            $db->prepare("UPDATE productos SET stock=stock-? WHERE id=? AND tipo='estandar'")->execute([$cant,$item['producto_id']]);
        }

        flashMessage('success',"Venta $numero registrada.");
        header('Location: view.php?id='.$ventaId); exit;
    }
}
$pageTitle = 'Nueva Venta';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Nueva Venta</h1>
</div>

<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" x-data="ventaForm()">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        <!-- Datos -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Datos de la Venta</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <select name="cliente_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Consumidor final</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['nombre'].' '.$c['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <input type="text" name="notas" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Productos -->
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700">Productos</h2>
                <button type="button" @click="agregarItem()"
                        class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-sm hover:bg-blue-200">
                    <i class="fas fa-plus mr-1"></i> Agregar
                </button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-2 text-left">Producto</th>
                        <th class="px-3 py-2 w-24">Cant.</th>
                        <th class="px-3 py-2 w-32">Precio</th>
                        <th class="px-3 py-2 w-32 text-right">Subtotal</th>
                        <th class="px-3 py-2 w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item,index) in items" :key="index">
                        <tr class="border-t">
                            <td class="px-3 py-2">
                                <select :name="'items['+index+'][producto_id]'" x-model="item.producto_id" @change="onProductoChange(index)"
                                        class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="">— Seleccionar —</option>
                                    <?php foreach ($productos as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>"><?= sanitize($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items['+index+'][cantidad]'" x-model="item.cantidad"
                                       @input="calcularSubtotal(index)" min="0.1" step="0.1"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items['+index+'][precio]'" x-model="item.precio"
                                       @input="calcularSubtotal(index)" min="0" step="0.01"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </td>
                            <td class="px-3 py-2 text-right font-semibold" x-text="'Q '+item.subtotal.toFixed(2)"></td>
                            <td class="px-3 py-2">
                                <button type="button" @click="items.splice(index,1);calcularTotal()" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="items.length===0">
                        <td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">Sin productos — haz clic en Agregar</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Resumen -->
    <div>
        <div class="bg-white rounded-xl shadow p-6 sticky top-6">
            <h2 class="font-semibold text-gray-700 mb-4">Resumen</h2>
            <div class="border-t pt-3 flex justify-between font-bold text-lg">
                <span>Total</span>
                <span x-text="'Q '+total.toFixed(2)"></span>
            </div>
            <button type="submit" class="mt-6 w-full bg-blue-700 text-white py-3 rounded-lg hover:bg-blue-800 font-medium text-sm">
                <i class="fas fa-cash-register mr-1"></i> Registrar Venta
            </button>
            <a href="index.php" class="mt-2 block text-center text-gray-500 text-sm hover:text-gray-700">Cancelar</a>
        </div>
    </div>
</div>
</form>

<script>
const productosData = <?= json_encode(array_column($productos, null, 'id')) ?>;
function ventaForm() {
    return {
        items: [], total: 0,
        agregarItem() { this.items.push({producto_id:'',cantidad:1,precio:0,subtotal:0}); },
        onProductoChange(i) {
            const pid = this.items[i].producto_id;
            if (pid && productosData[pid]) { this.items[i].precio = parseFloat(productosData[pid].precio); this.calcularSubtotal(i); }
        },
        calcularSubtotal(i) { this.items[i].subtotal = parseFloat(this.items[i].cantidad||0)*parseFloat(this.items[i].precio||0); this.calcularTotal(); },
        calcularTotal() { this.total = this.items.reduce((s,i)=>s+(i.subtotal||0),0); }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
