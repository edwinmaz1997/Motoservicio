<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
refreshSession();
$user = currentUser();

$db = getDB();
$clientes  = $db->prepare("SELECT * FROM clientes WHERE sucursal_id=? AND activo=1 ORDER BY nombre,apellido");
$clientes->execute([$user['sucursal_id']]); $clientes = $clientes->fetchAll();
$productos = $db->query("SELECT p.*, c.nombre as categoria FROM productos p LEFT JOIN categorias c ON c.id=p.categoria_id WHERE p.activo=1 ORDER BY p.tipo, p.nombre")->fetchAll();
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
    $metodo    = $_POST['metodo_pago'] ?? 'efectivo';
    $notas     = trim($_POST['notas'] ?? '');
    $fecha     = $_POST['fecha'] ?? date('Y-m-d');
    $items     = $_POST['items'] ?? [];

    if (empty($items)) $errors[] = 'Agrega al menos un producto.';

    if (empty($errors)) {
        $total = 0;
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

<div class="flex items-center gap-3 mb-5">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Nueva Venta</h1>
</div>

<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div x-data="ventaForm()" class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Columna productos -->
    <div class="xl:col-span-2 space-y-4">

        <!-- Datos de la venta -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Datos</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fecha</label>
                    <input type="date" id="f_fecha" name="fecha" value="<?= date('Y-m-d') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cliente</label>
                    <select id="f_cliente" name="cliente_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Consumidor final</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['nombre'].' '.$c['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Método de pago</label>
                    <select id="f_metodo" name="metodo_pago" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="efectivo">💵 Efectivo</option>
                        <option value="tarjeta">💳 Tarjeta</option>
                        <option value="transferencia">🏦 Transferencia</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notas</label>
                    <input type="text" id="f_notas" name="notas" placeholder="Opcional..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Catálogo de productos -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Catálogo</h2>

            <!-- Buscador + filtro categoría -->
            <div class="flex gap-2 mb-3 flex-wrap">
                <div class="relative flex-1 min-w-48">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                    <input type="text" x-model="busqueda" placeholder="Buscar producto..."
                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <select x-model="categoriaFiltro" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas las categorías</option>
                    <option value="servicio">Servicios</option>
                    <option value="estandar">Productos</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="cat_<?= $cat['id'] ?>"><?= sanitize($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grid de cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-3 gap-2 max-h-96 overflow-y-auto pr-1">
                <?php foreach ($productos as $p): ?>
                <div
                    x-show="productoVisible(<?= $p['id'] ?>)"
                    @click="agregarProducto(<?= $p['id'] ?>)"
                    class="border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-all select-none relative group"
                    :class="itemCount(<?= $p['id'] ?>) > 0 ? 'border-blue-400 bg-blue-50' : ''"
                    data-id="<?= $p['id'] ?>"
                    data-nombre="<?= sanitize(strtolower($p['nombre'])) ?>"
                    data-tipo="<?= $p['tipo'] ?>"
                    data-cat="<?= $p['categoria_id'] ?? '' ?>">

                    <!-- Badge cantidad -->
                    <div x-show="itemCount(<?= $p['id'] ?>) > 0"
                         class="absolute -top-1.5 -right-1.5 bg-blue-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold"
                         x-text="itemCount(<?= $p['id'] ?>)"></div>

                    <div class="text-center">
                        <!-- Ícono según tipo -->
                        <div class="text-2xl mb-1">
                            <?= $p['tipo']==='servicio' ? '🔧' : '📦' ?>
                        </div>
                        <div class="text-xs font-medium text-gray-800 leading-tight mb-1 line-clamp-2" title="<?= sanitize($p['nombre']) ?>">
                            <?= sanitize($p['nombre']) ?>
                        </div>
                        <div class="text-sm font-bold text-blue-700"><?= formatMoney($p['precio']) ?></div>
                        <?php if ($p['tipo']==='estandar'): ?>
                        <div class="text-xs text-gray-400 mt-0.5">
                            Stock: <span class="<?= $p['stock'] <= 0 ? 'text-red-500 font-semibold' : '' ?>"><?= $p['stock'] ?></span>
                        </div>
                        <?php else: ?>
                        <div class="text-xs text-purple-500 mt-0.5">Servicio</div>
                        <?php endif; ?>
                    </div>

                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <div class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold shadow">+</div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Sin resultados -->
                <div x-show="productosFiltrados === 0" class="col-span-3 text-center py-8 text-gray-400 text-sm">
                    Sin productos que coincidan
                </div>
            </div>
        </div>

        <!-- Items seleccionados -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5" x-show="items.length > 0">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Items seleccionados</h2>
                <span class="text-xs text-gray-400" x-text="items.length + ' item(s)'"></span>
            </div>
            <div class="space-y-2">
                <template x-for="(item, index) in items" :key="index">
                    <div class="flex items-center gap-3 py-2 border-b last:border-0">
                        <!-- Hidden inputs para el POST -->
                        <input type="hidden" :name="'items['+index+'][producto_id]'" :value="item.producto_id">
                        <input type="hidden" :name="'items['+index+'][precio]'" :value="item.precio">

                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate" x-text="item.nombre"></div>
                            <div class="text-xs text-gray-400" x-text="'Q ' + parseFloat(item.precio).toFixed(2) + ' c/u'"></div>
                        </div>

                        <!-- Cantidad con +/- -->
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button" @click="decrementar(index)"
                                    class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center">−</button>
                            <input type="number" :name="'items['+index+'][cantidad]'" x-model="item.cantidad"
                                   @input="recalcular(index)" min="0.1" step="1"
                                   class="w-12 text-center border border-gray-200 rounded-lg py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <button type="button" @click="incrementar(index)"
                                    class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center">+</button>
                        </div>

                        <div class="text-sm font-bold text-gray-800 w-20 text-right flex-shrink-0" x-text="'Q ' + item.subtotal.toFixed(2)"></div>

                        <button type="button" @click="eliminarItem(index)"
                                class="text-red-400 hover:text-red-600 flex-shrink-0 ml-1">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </template>
            </div>
        </div>

    </div>

    <!-- Resumen + botón -->
    <div>
        <div class="bg-white rounded-xl shadow p-5 xl:sticky xl:top-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Resumen</h2>

            <div x-show="items.length === 0" class="text-center py-6 text-gray-400 text-sm">
                <i class="fas fa-shopping-cart text-3xl mb-2 block"></i>
                Selecciona productos del catálogo
            </div>

            <div x-show="items.length > 0" class="space-y-2 mb-4">
                <template x-for="item in items" :key="item.producto_id">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 truncate flex-1 mr-2" x-text="item.cantidad + 'x ' + item.nombre"></span>
                        <span class="font-medium flex-shrink-0" x-text="'Q ' + item.subtotal.toFixed(2)"></span>
                    </div>
                </template>
            </div>

            <div class="border-t pt-3 flex justify-between font-bold text-lg">
                <span>Total</span>
                <span class="text-blue-700" x-text="'Q ' + total.toFixed(2)"></span>
            </div>

            <form id="form-venta" method="POST" class="mt-5">
                <!-- Campos del form ocultos que se llenarán desde Alpine -->
                <div id="hidden-fields"></div>
            </form>

            <button type="button" @click="submitVenta()"
                    :disabled="items.length === 0"
                    :class="items.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-800'"
                    class="mt-2 w-full bg-blue-700 text-white py-3 rounded-lg font-medium text-sm transition">
                <i class="fas fa-cash-register mr-1"></i> Registrar Venta
            </button>
            <a href="index.php" class="mt-2 block text-center text-gray-500 text-sm hover:text-gray-700">Cancelar</a>
        </div>
    </div>

</div>

<script>
const productosData = <?= json_encode(array_column($productos, null, 'id')) ?>;

function ventaForm() {
    return {
        items: [],
        total: 0,
        busqueda: '',
        categoriaFiltro: '',
        productosFiltrados: <?= count($productos) ?>,

        productoVisible(id) {
            const p = productosData[id];
            if (!p) return false;
            const b = this.busqueda.toLowerCase();
            const matchNombre = !b || p.nombre.toLowerCase().includes(b);
            let matchCat = true;
            if (this.categoriaFiltro === 'servicio') matchCat = p.tipo === 'servicio';
            else if (this.categoriaFiltro === 'estandar') matchCat = p.tipo === 'estandar';
            else if (this.categoriaFiltro.startsWith('cat_')) matchCat = String(p.categoria_id) === this.categoriaFiltro.replace('cat_','');
            return matchNombre && matchCat;
        },

        itemCount(id) {
            const item = this.items.find(i => i.producto_id == id);
            return item ? parseFloat(item.cantidad) : 0;
        },

        agregarProducto(id) {
            const p = productosData[id];
            if (!p) return;
            const idx = this.items.findIndex(i => i.producto_id == id);
            if (idx >= 0) {
                this.items[idx].cantidad = parseFloat(this.items[idx].cantidad) + 1;
                this.items[idx].subtotal = this.items[idx].cantidad * parseFloat(this.items[idx].precio);
            } else {
                this.items.push({
                    producto_id: id,
                    nombre: p.nombre,
                    precio: parseFloat(p.precio),
                    cantidad: 1,
                    subtotal: parseFloat(p.precio)
                });
            }
            this.calcularTotal();
        },

        incrementar(index) {
            this.items[index].cantidad = parseFloat(this.items[index].cantidad) + 1;
            this.recalcular(index);
        },

        decrementar(index) {
            const cant = parseFloat(this.items[index].cantidad) - 1;
            if (cant <= 0) { this.eliminarItem(index); return; }
            this.items[index].cantidad = cant;
            this.recalcular(index);
        },

        recalcular(index) {
            const cant = parseFloat(this.items[index].cantidad) || 0;
            this.items[index].subtotal = cant * parseFloat(this.items[index].precio);
            this.calcularTotal();
        },

        eliminarItem(index) {
            this.items.splice(index, 1);
            this.calcularTotal();
        },

        calcularTotal() {
            this.total = this.items.reduce((s, i) => s + (i.subtotal || 0), 0);
        },

        submitVenta() {
            if (this.items.length === 0) return;
            const form = document.getElementById('form-venta');
            // Copiar datos del encabezado
            const addField = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = name; input.value = value;
                form.appendChild(input);
            };
            addField('fecha',      document.getElementById('f_fecha').value);
            addField('cliente_id', document.getElementById('f_cliente').value);
            addField('metodo_pago',document.getElementById('f_metodo').value);
            addField('notas',      document.getElementById('f_notas').value);
            this.items.forEach((item, i) => {
                addField(`items[${i}][producto_id]`, item.producto_id);
                addField(`items[${i}][cantidad]`,    item.cantidad);
                addField(`items[${i}][precio]`,      item.precio);
            });
            form.submit();
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
