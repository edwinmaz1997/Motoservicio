<?php
$pageTitle = 'Nueva Orden';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$clientes  = $db->prepare("SELECT * FROM clientes WHERE sucursal_id=? AND activo=1 ORDER BY nombre,apellido");
$clientes->execute([$user['sucursal_id']]); $clientes = $clientes->fetchAll();
$tecnicos  = $db->prepare("SELECT * FROM usuarios WHERE sucursal_id=? AND rol_id=? AND activo=1 ORDER BY nombre");
$tecnicos->execute([$user['sucursal_id'], ROL_TECNICO]); $tecnicos = $tecnicos->fetchAll();
$asesores  = $db->prepare("SELECT * FROM usuarios WHERE sucursal_id=? AND rol_id IN (?,?) AND activo=1 ORDER BY nombre");
$asesores->execute([$user['sucursal_id'], ROL_ASESOR_VENTAS, ROL_VENDEDOR]); $asesores = $asesores->fetchAll();
$productos = $db->query("SELECT * FROM productos WHERE activo=1 ORDER BY tipo,nombre")->fetchAll();

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

        // Calcular total
        $total = 0;
        foreach ($items as $item) {
            $total += (float)($item['precio'] ?? 0) * (float)($item['cantidad'] ?? 1);
        }

        $db->prepare("INSERT INTO ordenes (sucursal_id,cliente_id,motocicleta_id,tecnico_id,asesor_id,numero_orden,fecha_ingreso,fecha_salida_esperada,kilometraje_ingreso,diagnostico,observaciones,total,saldo)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([
               $user['sucursal_id'],
               $data['cliente_id'], $data['motocicleta_id'],
               $data['tecnico_id'] ?: null, $data['asesor_id'] ?: null,
               $numero, $data['fecha_ingreso'],
               $data['fecha_salida_esperada'] ?: null,
               $data['kilometraje_ingreso'] ?: null,
               $data['diagnostico'], $data['observaciones'],
               $total, $total
           ]);
        $ordenId = $db->lastInsertId();

        // Insertar detalle y descontar stock
        foreach ($items as $item) {
            if (empty($item['producto_id'])) continue;
            $cant  = (float)($item['cantidad'] ?? 1);
            $precio = (float)($item['precio'] ?? 0);
            $sub   = $cant * $precio;
            $db->prepare("INSERT INTO orden_detalle (orden_id,producto_id,cantidad,precio_unit,subtotal) VALUES (?,?,?,?,?)")
               ->execute([$ordenId, $item['producto_id'], $cant, $precio, $sub]);
            // Descontar stock si es estándar
            $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND tipo = 'estandar'")->execute([$cant, $item['producto_id']]);
        }

        flashMessage('success', "Orden $numero creada exitosamente.");
        header('Location: view.php?id=' . $ordenId); exit;
    }
}
?>

<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Nueva Orden de Servicio</h1>
</div>

<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" x-data="ordenForm()">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Col izquierda: datos generales -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Cliente y moto -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Cliente y Motocicleta</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                    <select name="cliente_id" x-model="clienteId" @change="cargarMotos()" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $data['cliente_id']==$c['id']?'selected':'' ?>>
                            <?= sanitize($c['nombre'].' '.$c['apellido']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motocicleta *</label>
                    <select name="motocicleta_id" x-model="motoId" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Primero selecciona cliente —</option>
                        <template x-for="m in motos" :key="m.id">
                            <option :value="m.id" x-text="m.label"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Técnico</label>
                    <select name="tecnico_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= sanitize($t['nombre'].' '.$t['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asesor</label>
                    <select name="asesor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($asesores as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= sanitize($a['nombre'].' '.$a['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Fechas -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Fechas y Kilometraje</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kilometraje ingreso</label>
                    <input type="number" name="kilometraje_ingreso" value="<?= sanitize($data['kilometraje_ingreso']) ?>" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Diagnóstico -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-700 mb-4">Diagnóstico y Observaciones</h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico / Trabajo a realizar</label>
                    <textarea name="diagnostico" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['diagnostico']) ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['observaciones']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Productos / Servicios -->
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700">Productos y Servicios</h2>
                <button type="button" @click="agregarItem()"
                        class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-sm hover:bg-blue-200">
                    <i class="fas fa-plus mr-1"></i> Agregar
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-3 py-2 text-left">Producto / Servicio</th>
                            <th class="px-3 py-2 text-left w-24">Cant.</th>
                            <th class="px-3 py-2 text-left w-32">Precio</th>
                            <th class="px-3 py-2 text-left w-32">Subtotal</th>
                            <th class="px-3 py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="border-t">
                                <td class="px-3 py-2">
                                    <select :name="'items['+index+'][producto_id]'" x-model="item.producto_id"
                                            @change="onProductoChange(index)"
                                            class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="">— Seleccionar —</option>
                                        <?php foreach ($productos as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">
                                            [<?= $p['tipo']==='servicio'?'SVC':'PRD' ?>] <?= sanitize($p['nombre']) ?>
                                        </option>
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
                                <td class="px-3 py-2 font-semibold" x-text="'Q ' + item.subtotal.toFixed(2)"></td>
                                <td class="px-3 py-2">
                                    <button type="button" @click="items.splice(index,1); calcularTotal()"
                                            class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="items.length === 0">
                            <td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">Sin items — haz clic en Agregar</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Col derecha: resumen -->
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow p-6 sticky top-6">
            <h2 class="font-semibold text-gray-700 mb-4">Resumen</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Items</span>
                    <span x-text="items.length"></span>
                </div>
                <div class="flex justify-between border-t pt-3 font-bold text-lg">
                    <span>Total</span>
                    <span x-text="'Q ' + total.toFixed(2)"></span>
                </div>
            </div>
            <button type="submit"
                    class="mt-6 w-full bg-blue-700 text-white py-3 rounded-lg hover:bg-blue-800 font-medium text-sm">
                <i class="fas fa-save mr-1"></i> Crear Orden
            </button>
            <a href="index.php" class="mt-2 block text-center text-gray-500 text-sm hover:text-gray-700">Cancelar</a>
        </div>
    </div>

</div>
</form>

<script>
const productosData = <?= json_encode(array_column($productos, null, 'id')) ?>;

function ordenForm() {
    return {
        clienteId: '<?= $data['cliente_id'] ?>',
        motoId: '',
        motos: [],
        items: [],
        total: 0,

        async cargarMotos() {
            this.motos = [];
            this.motoId = '';
            if (!this.clienteId) return;
            const res = await fetch(`../../api/motos.php?cliente_id=${this.clienteId}`);
            const data = await res.json();
            this.motos = data.map(m => ({
                id: m.id,
                label: `${m.marca_texto} ${m.modelo || ''} ${m.placa ? '('+m.placa+')' : ''}`
            }));
        },

        agregarItem() {
            this.items.push({ producto_id: '', cantidad: 1, precio: 0, subtotal: 0 });
        },

        onProductoChange(index) {
            const pid = this.items[index].producto_id;
            if (pid && productosData[pid]) {
                this.items[index].precio = parseFloat(productosData[pid].precio);
                this.calcularSubtotal(index);
            }
        },

        calcularSubtotal(index) {
            const item = this.items[index];
            item.subtotal = parseFloat(item.cantidad || 0) * parseFloat(item.precio || 0);
            this.calcularTotal();
        },

        calcularTotal() {
            this.total = this.items.reduce((s, i) => s + (i.subtotal || 0), 0);
        },

        init() {
            if (this.clienteId) this.cargarMotos();
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
