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
$tecnicos  = $db->prepare("SELECT * FROM usuarios WHERE sucursal_id=? AND rol_id=? AND activo=1 ORDER BY nombre");
$tecnicos->execute([$user['sucursal_id'], ROL_TECNICO]); $tecnicos = $tecnicos->fetchAll();
$asesores  = $db->prepare("SELECT * FROM usuarios WHERE sucursal_id=? AND rol_id IN (?,?) AND activo=1 ORDER BY nombre");
$asesores->execute([$user['sucursal_id'], ROL_ASESOR_VENTAS, ROL_VENDEDOR]); $asesores = $asesores->fetchAll();
$productos  = $db->query("SELECT p.*, c.nombre as categoria FROM productos p LEFT JOIN categorias c ON c.id=p.categoria_id WHERE p.activo=1 ORDER BY p.tipo, p.nombre")->fetchAll();
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
$marcas     = $db->query("SELECT * FROM marcas ORDER BY nombre")->fetchAll();
$errors = [];

$data = [
    'cliente_id'            => (int)($_GET['cliente_id'] ?? 0),
    'motocicleta_id'        => 0,
    'tecnico_id'            => '',
    'asesor_id'             => '',
    'fecha_ingreso'         => date('Y-m-d'),
    'fecha_salida_esperada' => '',
    'kilometraje_ingreso'   => '',
    'diagnostico'           => '',
    'observaciones'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) $data[$k] = trim($_POST[$k] ?? '');
    $items = $_POST['items'] ?? [];

    if (!$data['cliente_id'])     $errors[] = 'Selecciona un cliente.';
    if (!$data['motocicleta_id']) $errors[] = 'Selecciona una motocicleta.';
    if (!$data['fecha_ingreso'])  $errors[] = 'La fecha de ingreso es requerida.';

    if (empty($errors)) {
        $numero = generateNumero('ORD-', 'ordenes', 'numero_orden');
        $total  = 0;
        foreach ($items as $item) $total += (float)($item['precio']??0) * (float)($item['cantidad']??1);

        $db->prepare("INSERT INTO ordenes (sucursal_id,cliente_id,motocicleta_id,tecnico_id,asesor_id,numero_orden,fecha_ingreso,fecha_salida_esperada,kilometraje_ingreso,diagnostico,observaciones,total,saldo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$user['sucursal_id'],$data['cliente_id'],$data['motocicleta_id'],$data['tecnico_id']?:null,$data['asesor_id']?:null,$numero,$data['fecha_ingreso'],$data['fecha_salida_esperada']?:null,$data['kilometraje_ingreso']?:null,$data['diagnostico'],$data['observaciones'],$total,$total]);
        $ordenId = $db->lastInsertId();

        foreach ($items as $item) {
            if (empty($item['producto_id'])) continue;
            $cant=$item['cantidad']??1; $precio=$item['precio']??0; $sub=$cant*$precio;
            $db->prepare("INSERT INTO orden_detalle (orden_id,producto_id,cantidad,precio_unit,subtotal) VALUES (?,?,?,?,?)")->execute([$ordenId,$item['producto_id'],$cant,$precio,$sub]);
            $db->prepare("UPDATE productos SET stock=stock-? WHERE id=? AND tipo='estandar'")->execute([$cant,$item['producto_id']]);
        }

        flashMessage('success',"Orden $numero creada.");
        header('Location: view.php?id='.$ordenId); exit;
    }
}

$pageTitle = 'Nueva Orden';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex items-center gap-3 mb-5">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Nueva Orden de Servicio</h1>
</div>

<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div x-data="ordenForm()">
<form id="form-orden" method="POST">
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <div class="xl:col-span-2 space-y-4">

        <!-- Cliente y Moto -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Cliente y Motocicleta</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cliente *</label>
                    <div class="flex gap-1">
                        <select name="cliente_id" x-model="clienteId" @change="cargarMotos()" required
                                class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $data['cliente_id']==$c['id']?'selected':'' ?>><?= sanitize($c['nombre'].' '.$c['apellido']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" @click="modalCliente=true" class="flex-shrink-0 bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 text-sm" title="Nuevo cliente"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Motocicleta *</label>
                    <div class="flex gap-1">
                        <select name="motocicleta_id" x-model="motoId" required
                                class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Selecciona cliente primero —</option>
                            <template x-for="m in motos" :key="m.id"><option :value="m.id" x-text="m.label"></option></template>
                        </select>
                        <button type="button" @click="clienteId ? modalMoto=true : alert('Selecciona un cliente primero')" class="flex-shrink-0 bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 text-sm" title="Nueva moto"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Técnico</label>
                    <select name="tecnico_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($tecnicos as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['nombre'].' '.$t['apellido']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Asesor</label>
                    <select name="asesor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($asesores as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['nombre'].' '.$a['apellido']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Fechas -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Fechas y Kilometraje</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fecha ingreso *</label>
                    <input type="date" name="fecha_ingreso" value="<?= $data['fecha_ingreso'] ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Salida esperada</label>
                    <input type="date" name="fecha_salida_esperada" value="<?= $data['fecha_salida_esperada'] ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kilometraje</label>
                    <input type="number" name="kilometraje_ingreso" value="<?= sanitize($data['kilometraje_ingreso']) ?>" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Diagnóstico -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Diagnóstico</h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Trabajo a realizar</label>
                    <textarea name="diagnostico" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['diagnostico']) ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['observaciones']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Catálogo productos/servicios -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Productos y Servicios</h2>

            <!-- Buscador + filtro -->
            <div class="flex gap-2 mb-3 flex-wrap">
                <div class="relative flex-1 min-w-48">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                    <input type="text" x-model="busqueda" placeholder="Buscar servicio o producto..."
                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <select x-model="categoriaFiltro" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <option value="servicio">🔧 Servicios</option>
                    <option value="estandar">📦 Productos</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="cat_<?= $cat['id'] ?>"><?= sanitize($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grid cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-80 overflow-y-auto pr-1">
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

                    <div x-show="itemCount(<?= $p['id'] ?>) > 0"
                         class="absolute -top-1.5 -right-1.5 bg-blue-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold"
                         x-text="itemCount(<?= $p['id'] ?>)"></div>

                    <div class="text-center">
                        <div class="text-2xl mb-1"><?= $p['tipo']==='servicio' ? '🔧' : '📦' ?></div>
                        <div class="text-xs font-medium text-gray-800 leading-tight mb-1 line-clamp-2" title="<?= sanitize($p['nombre']) ?>"><?= sanitize($p['nombre']) ?></div>
                        <div class="text-sm font-bold text-blue-700"><?= formatMoney($p['precio']) ?></div>
                        <?php if ($p['tipo']==='estandar'): ?>
                        <div class="text-xs text-gray-400 mt-0.5">Stock: <span class="<?= $p['stock'] <= 0 ? 'text-red-500 font-semibold' : '' ?>"><?= $p['stock'] ?></span></div>
                        <?php else: ?>
                        <div class="text-xs text-purple-500 mt-0.5">Servicio</div>
                        <?php endif; ?>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <div class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold shadow">+</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div x-show="productosFiltrados === 0" class="col-span-3 text-center py-6 text-gray-400 text-sm">Sin resultados</div>
            </div>

            <!-- Items seleccionados -->
            <div x-show="items.length > 0" class="mt-4 pt-4 border-t space-y-2">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Items agregados</span>
                    <span class="text-xs text-gray-400" x-text="items.length + ' item(s)'"></span>
                </div>
                <template x-for="(item, index) in items" :key="index">
                    <div class="flex items-center gap-3 py-2 border-b last:border-0">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate" x-text="item.nombre"></div>
                            <div class="text-xs text-gray-400" x-text="'Q ' + parseFloat(item.precio).toFixed(2) + ' c/u'"></div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button" @click="decrementar(index)" class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center">−</button>
                            <span class="w-8 text-center text-sm font-medium" x-text="item.cantidad"></span>
                            <button type="button" @click="incrementar(index)" class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center">+</button>
                        </div>
                        <div class="text-sm font-bold text-gray-800 w-20 text-right flex-shrink-0" x-text="'Q ' + item.subtotal.toFixed(2)"></div>
                        <button type="button" @click="eliminarItem(index)" class="text-red-400 hover:text-red-600 flex-shrink-0"><i class="fas fa-times text-sm"></i></button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Resumen -->
    <div>
        <div class="bg-white rounded-xl shadow p-5 xl:sticky xl:top-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Resumen</h2>
            <div x-show="items.length === 0" class="text-center py-6 text-gray-400 text-sm">
                <i class="fas fa-tools text-3xl mb-2 block"></i>
                Selecciona productos o servicios
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
            <button type="button" @click="submitOrden()"
                    class="mt-5 w-full bg-blue-700 text-white py-3 rounded-lg hover:bg-blue-800 font-medium text-sm transition">
                <i class="fas fa-save mr-1"></i> Crear Orden
            </button>
            <a href="index.php" class="mt-2 block text-center text-gray-500 text-sm hover:text-gray-700">Cancelar</a>
        </div>
    </div>

</div>
</form>

<!-- Modal nuevo cliente -->
<div x-show="modalCliente" x-cloak @click.self="modalCliente=false" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800">Nuevo Cliente</h3>
            <button @click="modalCliente=false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <div id="error-cliente" class="hidden bg-red-50 border border-red-300 text-red-700 px-3 py-2 rounded text-sm mb-4"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label><input type="text" id="nc_nombre" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Apellido *</label><input type="text" id="nc_apellido" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Teléfono</label><input type="text" id="nc_telefono" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Correo</label><input type="email" id="nc_correo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">DPI</label><input type="text" id="nc_dpi" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">NIT</label><input type="text" id="nc_nit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div class="sm:col-span-2"><label class="block text-xs font-medium text-gray-700 mb-1">Dirección</label><input type="text" id="nc_direccion" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            </div>
            <div class="flex gap-3 mt-5">
                <button @click="guardarCliente()" class="bg-blue-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 font-medium">Guardar</button>
                <button @click="modalCliente=false" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal nueva moto -->
<div x-show="modalMoto" x-cloak @click.self="modalMoto=false" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800">Nueva Motocicleta</h3>
            <button @click="modalMoto=false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <div id="error-moto" class="hidden bg-red-50 border border-red-300 text-red-700 px-3 py-2 rounded text-sm mb-4"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Marca (lista)</label>
                    <select id="nm_marca_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="if(this.value){document.getElementById('nm_marca_texto').value=this.options[this.selectedIndex].text==='Otro'?'':this.options[this.selectedIndex].text}">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($marcas as $m): ?><option value="<?= $m['id'] ?>"><?= sanitize($m['nombre']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Marca *</label><input type="text" id="nm_marca_texto" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Modelo</label><input type="text" id="nm_modelo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Año</label><input type="number" id="nm_anio" min="1980" max="<?= date('Y')+1 ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Color</label><input type="text" id="nm_color" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Kilometraje</label><input type="number" id="nm_km" value="0" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">Placa</label><input type="text" id="nm_placa" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-xs font-medium text-gray-700 mb-1">VIN / Chasis</label><input type="text" id="nm_vin" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            </div>
            <div class="flex gap-3 mt-5">
                <button @click="guardarMoto()" class="bg-blue-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 font-medium">Guardar</button>
                <button @click="modalMoto=false" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm">Cancelar</button>
            </div>
        </div>
    </div>
</div>

</div><!-- fin x-data -->

<script>
const productosData = <?= json_encode(array_column($productos, null, 'id')) ?>;
const BASE_URL = '<?= BASE_URL ?>';

function ordenForm() {
    return {
        clienteId: '<?= $data['cliente_id'] ?>',
        motoId: '',
        motos: [],
        items: [],
        total: 0,
        busqueda: '',
        categoriaFiltro: '',
        productosFiltrados: <?= count($productos) ?>,
        modalCliente: false,
        modalMoto: false,

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
                this.items.push({ producto_id: id, nombre: p.nombre, precio: parseFloat(p.precio), cantidad: 1, subtotal: parseFloat(p.precio) });
            }
            this.calcularTotal();
        },

        incrementar(i) { this.items[i].cantidad++; this.items[i].subtotal = this.items[i].cantidad * this.items[i].precio; this.calcularTotal(); },
        decrementar(i) { if (this.items[i].cantidad <= 1) { this.eliminarItem(i); return; } this.items[i].cantidad--; this.items[i].subtotal = this.items[i].cantidad * this.items[i].precio; this.calcularTotal(); },
        eliminarItem(i) { this.items.splice(i, 1); this.calcularTotal(); },
        calcularTotal() { this.total = this.items.reduce((s, i) => s + (i.subtotal || 0), 0); },

        submitOrden() {
            const form = document.getElementById('form-orden');
            const add = (n, v) => { const i = document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
            this.items.forEach((item, i) => {
                add(`items[${i}][producto_id]`, item.producto_id);
                add(`items[${i}][cantidad]`,    item.cantidad);
                add(`items[${i}][precio]`,      item.precio);
            });
            form.submit();
        },

        async cargarMotos() {
            this.motos = []; this.motoId = '';
            if (!this.clienteId) return;
            const res = await fetch(`${BASE_URL}/api/motos.php?cliente_id=${this.clienteId}`);
            const data = await res.json();
            this.motos = data.map(m => ({ id: m.id, label: `${m.marca_texto} ${m.modelo||''} ${m.placa?'('+m.placa+')':''}`.trim() }));
        },

        async guardarCliente() {
            const err = document.getElementById('error-cliente');
            err.classList.add('hidden');
            const fd = new FormData();
            fd.append('nombre',    document.getElementById('nc_nombre').value.trim());
            fd.append('apellido',  document.getElementById('nc_apellido').value.trim());
            fd.append('telefono',  document.getElementById('nc_telefono').value.trim());
            fd.append('correo',    document.getElementById('nc_correo').value.trim());
            fd.append('dpi',       document.getElementById('nc_dpi').value.trim());
            fd.append('nit',       document.getElementById('nc_nit').value.trim());
            fd.append('direccion', document.getElementById('nc_direccion').value.trim());
            const res = await fetch(`${BASE_URL}/api/crear_cliente.php`, { method:'POST', body:fd });
            const data = await res.json();
            if (data.error) { err.textContent = data.error; err.classList.remove('hidden'); return; }
            const sel = document.querySelector('select[name="cliente_id"]');
            const opt = new Option(data.label, data.id, true, true);
            sel.add(opt); sel.value = data.id;
            this.clienteId = String(data.id);
            this.modalCliente = false;
            await this.cargarMotos();
            ['nombre','apellido','telefono','correo','dpi','nit','direccion'].forEach(f => document.getElementById('nc_'+f).value='');
        },

        async guardarMoto() {
            const err = document.getElementById('error-moto');
            err.classList.add('hidden');
            const fd = new FormData();
            fd.append('cliente_id',  this.clienteId);
            fd.append('marca_id',    document.getElementById('nm_marca_id').value);
            fd.append('marca_texto', document.getElementById('nm_marca_texto').value.trim());
            fd.append('modelo',      document.getElementById('nm_modelo').value.trim());
            fd.append('anio',        document.getElementById('nm_anio').value);
            fd.append('color',       document.getElementById('nm_color').value.trim());
            fd.append('kilometraje', document.getElementById('nm_km').value);
            fd.append('placa',       document.getElementById('nm_placa').value.trim());
            fd.append('vin',         document.getElementById('nm_vin').value.trim());
            const res = await fetch(`${BASE_URL}/api/crear_moto.php`, { method:'POST', body:fd });
            const data = await res.json();
            if (data.error) { err.textContent = data.error; err.classList.remove('hidden'); return; }
            this.motos.push({ id: data.id, label: data.label });
            this.motoId = String(data.id);
            this.modalMoto = false;
            ['marca_id','marca_texto','modelo','anio','color','placa','vin'].forEach(f => { const el=document.getElementById('nm_'+f); if(el) el.value=''; });
            document.getElementById('nm_km').value='0';
        },

        init() { if (this.clienteId) this.cargarMotos(); }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
