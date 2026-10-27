<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/fel/FelClient.php';
requireLogin();
refreshSession();
$user = currentUser();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT v.*, CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')) as cliente,
                             c.nit as cliente_nit, c.correo as cliente_correo, c.direccion as cliente_direccion,
                             c.codigo_postal as cliente_cp, c.municipio as cliente_muni, c.departamento as cliente_depto,
                             CONCAT(u.nombre,' ',u.apellido) as vendedor
                      FROM ventas v
                      LEFT JOIN clientes c ON c.id=v.cliente_id
                      LEFT JOIN usuarios u ON u.id=v.vendedor_id
                      WHERE v.id=? AND v.sucursal_id=?");
$stmt->execute([$id,$user['sucursal_id']]); $v=$stmt->fetch();
if (!$v) { flashMessage('error','Venta no encontrada.'); header('Location: index.php'); exit; }

$felActivo = felActivo($user['sucursal_id']);
$dte       = felDocumentoDeVenta($id);

// Emitir factura de una venta que se registró sin FEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'emitir_fel') {
    if (!$felActivo) {
        flashMessage('error','FEL no está habilitado.');
    } elseif ($dte && $dte['estado'] === 'certificado') {
        flashMessage('error','Esta venta ya tiene un documento certificado.');
    } elseif ($v['estado'] !== 'pagada') {
        flashMessage('error','No se puede facturar una venta anulada.');
    } else {
        $det = $db->prepare("SELECT vd.*, p.nombre, p.tipo, p.unidad, p.unidad_sat
                             FROM venta_detalle vd JOIN productos p ON p.id=vd.producto_id WHERE vd.venta_id=?");
        $det->execute([$id]);
        $felItems = [];
        foreach ($det->fetchAll() as $d) {
            $felItems[] = [
                'tipo'        => $d['tipo']==='servicio' ? 'S' : 'B',
                'cantidad'    => (float)$d['cantidad'],
                'unidad'      => $d['unidad_sat'] ?: ($d['unidad'] ?: 'UND'),
                'descripcion' => $d['nombre'],
                'precio_unit' => (float)$d['precio_unit'],
                'descuento'   => 0,
            ];
        }
        $receptor = [
            'nit'           => trim($_POST['fel_nit'] ?? '') ?: ($v['cliente_nit'] ?: 'CF'),
            'nombre'        => trim($_POST['fel_nombre'] ?? '') ?: (trim($v['cliente']) ?: 'CONSUMIDOR FINAL'),
            'correo'        => trim($_POST['fel_correo'] ?? '') ?: ($v['cliente_correo'] ?: ''),
            'direccion'     => $v['cliente_direccion'] ?: '',
            'codigo_postal' => $v['cliente_cp'] ?: '',
            'municipio'     => $v['cliente_muni'] ?: '',
            'departamento'  => $v['cliente_depto'] ?: '',
        ];
        $res = felCertificar($user['sucursal_id'], $receptor, $felItems, [
            'tipo_dte'   => 'FACT',
            'venta_id'   => $id,
            'usuario_id' => $user['id'],
            'adenda'     => ['Numero_venta' => $v['numero_venta']],
        ]);
        if ($res['ok']) {
            $db->prepare("UPDATE ventas SET emitir_fel=1 WHERE id=?")->execute([$id]);
            flashMessage('success','Factura emitida. Autorización: ' . substr($res['uuid'],0,20) . '…');
        } else {
            flashMessage('error','No se pudo certificar: ' . $res['error']);
        }
    }
    header('Location: view.php?id='.$id); exit;
}

$detalle = $db->prepare("SELECT vd.*, p.nombre as producto FROM venta_detalle vd JOIN productos p ON p.id=vd.producto_id WHERE vd.venta_id=?");
$detalle->execute([$id]); $detalle=$detalle->fetchAll();

$pageTitle = 'Venta ' . $v['numero_venta'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex items-center gap-3 mb-5 flex-wrap">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-lg lg:text-2xl font-bold text-gray-800"><?= sanitize($v['numero_venta']) ?></h1>
    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $v['estado']==='pagada'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= ucfirst($v['estado']) ?></span>

    <?php if ($dte && $dte['estado']==='certificado'): ?>
    <span class="px-2 py-1 rounded-full text-xs font-medium bg-teal-100 text-teal-700">
        <i class="fas fa-file-invoice-dollar mr-1"></i> Facturado
    </span>
    <?php elseif ($dte && $dte['estado']==='rechazado'): ?>
    <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
        <i class="fas fa-triangle-exclamation mr-1"></i> FEL rechazado
    </span>
    <?php endif; ?>

    <div class="ml-auto flex gap-2 flex-wrap">
        <?php if ($felActivo && $v['estado']==='pagada' && (!$dte || $dte['estado'] !== 'certificado')): ?>
        <button onclick="document.getElementById('modal-fel').classList.remove('hidden')"
                class="bg-teal-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-teal-700">
            <i class="fas fa-file-invoice-dollar mr-1"></i> <?= $dte ? 'Reintentar factura' : 'Emitir factura' ?>
        </button>
        <?php endif; ?>
        <?php if ($v['estado']==='pagada'): ?>
        <a href="anular.php?id=<?= $v['id'] ?>" class="bg-red-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-600"
           onclick="return confirm('¿Anular esta venta?')"><i class="fas fa-ban mr-1"></i> Anular</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">

        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-400 text-xs block">Cliente</span><span class="font-medium"><?= sanitize(trim($v['cliente']) ?: 'Consumidor final') ?></span></div>
                <div><span class="text-gray-400 text-xs block">Vendedor</span><span class="font-medium"><?= sanitize($v['vendedor']??'—') ?></span></div>
                <div><span class="text-gray-400 text-xs block">Fecha</span><span class="font-medium"><?= formatDate($v['fecha']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">Método de pago</span><span class="font-medium capitalize"><?= sanitize($v['metodo_pago']) ?></span></div>
                <?php if ($v['notas']): ?><div class="col-span-2"><span class="text-gray-400 text-xs block">Notas</span><span><?= sanitize($v['notas']) ?></span></div><?php endif; ?>
            </div>
        </div>

        <!-- Productos -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Productos</h2>

            <div class="lg:hidden space-y-2">
                <?php foreach ($detalle as $d): ?>
                <div class="flex justify-between items-center py-2 border-b last:border-0 text-sm">
                    <div>
                        <div class="font-medium"><?= sanitize($d['producto']) ?></div>
                        <div class="text-xs text-gray-400"><?= $d['cantidad'] ?> x <?= formatMoney($d['precio_unit']) ?></div>
                    </div>
                    <div class="font-semibold"><?= formatMoney($d['subtotal']) ?></div>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between font-bold pt-2 text-sm">
                    <span>Total</span><span><?= formatMoney($v['total']) ?></span>
                </div>
            </div>

            <div class="hidden lg:block">
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

        <?php if ($dte): ?>
        <!-- Documento electrónico -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Factura electrónica</h2>
                <a href="../fel/ver.php?id=<?= $dte['id'] ?>" class="text-sm text-blue-600 hover:underline">Ver documento</a>
            </div>
            <?php if ($dte['estado']==='certificado'): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-400 text-xs block">Serie</span><span class="font-mono"><?= sanitize($dte['serie'] ?: '—') ?></span></div>
                <div><span class="text-gray-400 text-xs block">Número</span><span class="font-mono"><?= sanitize($dte['numero'] ?: '—') ?></span></div>
                <div><span class="text-gray-400 text-xs block">NIT receptor</span><span class="font-mono"><?= sanitize($dte['nit_receptor']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">Gravable</span><span><?= formatMoney($dte['monto_gravable']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">IVA</span><span><?= formatMoney($dte['monto_iva']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">Total</span><span class="font-bold"><?= formatMoney($dte['gran_total']) ?></span></div>
                <div class="col-span-2 sm:col-span-3">
                    <span class="text-gray-400 text-xs block">Autorización</span>
                    <span class="font-mono text-xs break-all"><?= sanitize($dte['uuid']) ?></span>
                </div>
            </div>
            <?php elseif ($dte['estado']==='anulado'): ?>
            <div class="text-sm text-gray-600">
                Documento anulado el <?= formatDate($dte['fecha_anulacion']) ?>.
                <div class="text-xs text-gray-400 mt-1"><?= sanitize($dte['motivo_anulacion']) ?></div>
            </div>
            <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                <?= nl2br(sanitize($dte['mensaje_error'] ?: 'El documento no se certificó.')) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($felActivo && $v['estado']==='pagada' && (!$dte || $dte['estado'] !== 'certificado')): ?>
<!-- Modal emitir factura -->
<div id="modal-fel" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
                <h3 class="font-semibold text-gray-800">Emitir factura electrónica</h3>
                <p class="text-xs text-gray-500">Venta <?= sanitize($v['numero_venta']) ?> · <?= formatMoney($v['total']) ?></p>
            </div>
            <button onclick="document.getElementById('modal-fel').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-3" onsubmit="return confirmarEmision(this)">
            <input type="hidden" name="accion" value="emitir_fel">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">NIT del receptor</label>
                <div class="relative">
                    <input type="text" name="fel_nit" id="fel_nit" value="<?= sanitize($v['cliente_nit'] ?: 'CF') ?>"
                           oninput="buscarNitDebounce()" onblur="buscarNit()"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-9 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <span id="nit-spinner" class="hidden absolute right-3 top-2.5 text-gray-400"><i class="fas fa-circle-notch fa-spin text-xs"></i></span>
                    <span id="nit-check"   class="hidden absolute right-3 top-2.5 text-green-500"><i class="fas fa-check text-xs"></i></span>
                </div>
                <p id="nit-aviso" class="hidden text-xs text-amber-600 mt-1">NIT no registrado — escribe el nombre manualmente.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nombre del receptor</label>
                <input type="text" name="fel_nombre" id="fel_nombre" value="<?= sanitize(trim($v['cliente']) ?: 'CONSUMIDOR FINAL') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Correo (opcional)</label>
                <input type="email" name="fel_correo" id="fel_correo" value="<?= sanitize($v['cliente_correo'] ?: '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                Una vez certificada ante SAT solo se puede anular, no editar.
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit" class="bg-teal-600 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-teal-700 font-medium">
                    <i class="fas fa-paper-plane mr-1"></i> Emitir
                </button>
                <button type="button" onclick="document.getElementById('modal-fel').classList.add('hidden')"
                        class="bg-gray-100 text-gray-700 px-5 py-2.5 rounded-lg text-sm">Cancelar</button>
            </div>
        </form>

        <script>
        let nitTimer = null;
        function buscarNitDebounce() {
            clearTimeout(nitTimer);
            nitTimer = setTimeout(buscarNit, 500);
        }
        async function buscarNit() {
            const nit = document.getElementById('fel_nit').value.trim();
            const spin = document.getElementById('nit-spinner');
            const chk  = document.getElementById('nit-check');
            const avi  = document.getElementById('nit-aviso');
            chk.classList.add('hidden'); avi.classList.add('hidden');
            if (!nit) return;
            spin.classList.remove('hidden');
            try {
                const res = await fetch('<?= BASE_URL ?>/api/buscar_nit.php?nit=' + encodeURIComponent(nit));
                const d = await res.json();
                if (d.generico) {
                    document.getElementById('fel_nombre').value = 'CONSUMIDOR FINAL';
                } else if (d.encontrado) {
                    document.getElementById('fel_nombre').value = d.nombre;
                    if (d.correo) document.getElementById('fel_correo').value = d.correo;
                    chk.classList.remove('hidden');
                } else {
                    avi.classList.remove('hidden');
                }
            } catch (e) {}
            spin.classList.add('hidden');
        }
        function confirmarEmision(form) {
            const nit = form.fel_nit.value.trim() || 'CF';
            const nom = form.fel_nombre.value.trim() || 'CONSUMIDOR FINAL';
            return confirm('¿Emitir la factura electrónica?\n\nNIT: ' + nit + '\nNombre: ' + nom + '\nTotal: <?= formatMoney($v['total']) ?>\n\nUna vez certificada ante SAT solo se puede anular.');
        }
        </script>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
