<?php
$pageTitle = 'Nueva Orden';
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
$productos = $db->query("SELECT * FROM productos WHERE activo=1 ORDER BY tipo,nombre")->fetchAll();
$marcas    = $db->query("SELECT * FROM marcas ORDER BY nombre")->fetchAll();

$errors = [];
$data = [
    'cliente_id'           => (int)($_GET['cliente_id'] ?? 0),
    'motocicleta_id'       => 0,
    'tecnico_id'           => '',
    'asesor_id'            => '',
    'fecha_ingreso'        => date('Y-m-d'),
    'fecha_salida_esperada'=> '',
    'kilometraje_ingreso'  => '',
    'diagnostico'          => '',
    'observaciones'        => '',
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
<form method="POST">
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    <div class="xl:col-span-2 space-y-4">

        <!-- Cliente y Moto -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Cliente y Motocicleta</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Cliente -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                    <div class="flex gap-1">
                        <select name="cliente_id" x-model="clienteId" @change="cargarMotos()" required
                                class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $data['cliente_id']==$c['id']?'selected':'' ?>><?= sanitize($c['nombre'].' '.$c['apellido']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" @click="modalCliente=true"
                                class="flex-shrink-0 bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 text-sm" title="Nuevo cliente">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <!-- Moto -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motocicleta *</label>
                    <div class="flex gap-1">
                        <select name="motocicleta_id" x-model="motoId" required
                                class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Selecciona cliente primero —</option>
                            <template x-for="m in motos" :key="m.id">
                                <option :value="m.id" x-text="m.label"></option>
                            </template>
                        </select>
                        <button type="button" @click="clienteId ? modalMoto=true : alert('Selecciona un cliente primero')"
                                class="flex-shrink-0 bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 text-sm" title="Nueva moto">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <!-- Técnico -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Técnico</label>
                    <select name="tecnico_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($tecnicos as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['nombre'].' '.$t['apellido']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <!-- Asesor -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asesor</label>
                    <select name="asesor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($asesores as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['nombre'].' '.$a['apellido']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Fechas -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Fechas y Kilometraje</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha ingreso *</label>
                    <input type="date" name="fecha_ingreso" value="<?= $data['fecha_ingreso'] ?>" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha salida esperada</label>
                    <input type="date" name="fecha_salida_esperada" value="<?= $data['fecha_salida_esperada'] ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kilometraje</label>
                    <input type="number" name="kilometraje_ingreso" value="<?= sanitize($data['kilometraje_ingreso']) ?>" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Diagnóstico -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Diagnóstico</h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trabajo a realizar</label>
                    <textarea name="diagnostico" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['diagnostico']) ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['observaciones']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Productos -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Productos y Servicios</h2>
                <button type="button" @click="agregarItem()"
                        class="bg-blue-100 text-blue-700 px-3 py-1.5 rounded-lg text-sm hover:bg-blue-200 font-medium">
                    <i class="fas fa-plus mr-1"></i> Agregar
                </button>
            </div>
            <!-- Mobile: cards -->
            <div class="lg:hidden space-y-3">
                <template x-for="(item,index) in items" :key="index">
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 font-medium">Item <span x-text="index+1"></span></span>
                            <button type="button" @click="items.splice(index,1);calcularTotal()" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-times"></i></button>
                        </div>
                        <select :name="'items['+index+'][producto_id]'" x-model="item.producto_id" @change="onProductoChange(index)"
                                class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Seleccionar producto —</option>
                            <?php foreach ($productos as $p): ?><option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">[<?= $p['tipo']==='servicio'?'SVC':'PRD' ?>] <?= sanitize($p['nombre']) ?></option><?php endforeach; ?>
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs text-gray-500">Cantidad</label>
                                <input type="number" :name="'items['+index+'][cantidad]'" x-model="item.cantidad" @input="calcularSubtotal(index)" min="0.1" step="0.1"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Precio</label>
                                <input type="number" :name="'items['+index+'][precio]'" x-model="item.precio" @input="calcularSubtotal(index)" min="0" step="0.01"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="text-right mt-2 font-semibold text-sm text-blue-700" x-text="'Subtotal: Q '+item.subtotal.toFixed(2)"></div>
                    </div>
                </template>
                <div x-show="items.length===0" class="text-center text-gray-400 text-sm py-4">Sin items — haz clic en Agregar</div>
            </div>
            <!-- Desktop: tabla -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-3 py-2 text-left">Producto / Servicio</th>
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
                                        <?php foreach ($productos as $p): ?><option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">[<?= $p['tipo']==='servicio'?'SVC':'PRD' ?>] <?= sanitize($p['nombre']) ?></option><?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2"><input type="number" :name="'items['+index+'][cantidad]'" x-model="item.cantidad" @input="calcularSubtotal(index)" min="0.1" step="0.1" class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"></td>
                                <td class="px-3 py-2"><input type="number" :name="'items['+index+'][precio]'" x-model="item.precio" @input="calcularSubtotal(index)" min="0" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"></td>
                                <td class="px-3 py-2 text-right font-semibold" x-text="'Q '+item.subtotal.toFixed(2)"></td>
                                <td class="px-3 py-2"><button type="button" @click="items.splice(index,1);calcularTotal()" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </template>
                        <tr x-show="items.length===0"><td colspan="5" class="px-3 py-6 text-center text-gray-400 text-sm">Sin items — haz clic en Agregar</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Resumen sticky -->
    <div>
        <div class="bg-white rounded-xl shadow p-4 lg:p-6 xl:sticky xl:top-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Resumen</h2>
            <div class="flex justify-between items-center border-t pt-3 font-bold text-lg">
                <span>Total</span>
                <span class="text-blue-700" x-text="'Q '+total.toFixed(2)"></span>
            </div>
            <button type="submit" class="mt-4 w-full bg-blue-700 text-white py-3 rounded-lg hover:bg-blue-800 font-medium text-sm transition">
                <i class="fas fa-save mr-1"></i> Crear Orden
            </button>
            <a href="index.php" class="mt-2 block text-center text-gray-500 text-sm hover:text-gray-700">Cancelar</a>
        </div>
    </div>
</div>
</form>

<!-- ===== MODAL: NUEVO CLIENTE ===== -->
<!-- Los modales van dentro del div x-data -->
<div x-show="modalCliente" x-cloak @click.self="modalCliente=false"
     class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
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

<!-- ===== MODAL: NUEVA MOTO ===== -->
<div x-show="modalMoto" x-cloak @click.self="modalMoto=false"
     class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
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
        modalCliente: false,
        modalMoto: false,

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
            // Agregar al select y seleccionar
            const sel = document.querySelector('select[name="cliente_id"]');
            const opt = new Option(data.label, data.id, true, true);
            sel.add(opt); sel.value = data.id;
            this.clienteId = String(data.id);
            this.modalCliente = false;
            await this.cargarMotos();
            // Limpiar campos
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

        agregarItem() { this.items.push({ producto_id:'', cantidad:1, precio:0, subtotal:0 }); },

        onProductoChange(i) {
            const pid = this.items[i].producto_id;
            if (pid && productosData[pid]) { this.items[i].precio = parseFloat(productosData[pid].precio); this.calcularSubtotal(i); }
        },

        calcularSubtotal(i) { this.items[i].subtotal = parseFloat(this.items[i].cantidad||0) * parseFloat(this.items[i].precio||0); this.calcularTotal(); },
        calcularTotal() { this.total = this.items.reduce((s,i) => s+(i.subtotal||0), 0); },

        init() { if (this.clienteId) this.cargarMotos(); }
    }
}
</script>

</div><!-- fin x-data -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
