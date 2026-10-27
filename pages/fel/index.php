<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/fel/FelConfig.php';
requireLogin();
refreshSession();
$user = currentUser();
requireRol(ROL_ADMIN);

$db  = getDB();
$suc = $user['sucursal_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = [
        'usuario_firma','llave_firma','usuario_api','llave_api',
        'url_certificacion','url_anulacion',
        'nit_emisor','nombre_emisor','nombre_comercial','correo_emisor',
        'afiliacion_iva','codigo_establecimiento',
        'direccion','codigo_postal','municipio','departamento',
    ];
    $data = [];
    foreach ($campos as $c) $data[$c] = trim($_POST[$c] ?? '');

    $data['activo']         = isset($_POST['activo']) ? 1 : 0;
    $data['ambiente']       = $_POST['ambiente'] ?? 'implementacion';
    $data['porcentaje_iva'] = (float)($_POST['porcentaje_iva'] ?? 12);

    if ($data['nit_emisor']) $data['nit_emisor'] = strtoupper(preg_replace('/[^0-9A-Za-z]/','',$data['nit_emisor']));
    if (!$data['url_certificacion']) $data['url_certificacion'] = 'https://certificador.feel.com.gt/fel/procesounificado/transaccion/v2/xml';
    if (!$data['url_anulacion'])     $data['url_anulacion']     = 'https://certificador.feel.com.gt/fel/procesounificado/transaccion/v2/anulacion';


    if (empty($errors)) {
        felConfig($suc); // asegura que exista la fila
        $sets = implode(',', array_map(function($c){ return "$c=?"; }, array_keys($data)));
        $vals = array_values($data);
        $vals[] = $suc;
        $db->prepare("UPDATE fel_config SET $sets WHERE sucursal_id=?")->execute($vals);
        flashMessage('success','Configuración FEL guardada.');
        header('Location: index.php'); exit;
    }
}

$cfg    = felConfig($suc);
$faltan = felCamposFaltantes($suc);

// Últimos documentos emitidos
$docs = $db->prepare("SELECT * FROM fel_documentos WHERE sucursal_id=? ORDER BY id DESC LIMIT 10");
$docs->execute([$suc]);
$docs = $docs->fetchAll();

$pageTitle = 'Facturación Electrónica (FEL)';
require_once __DIR__ . '/../../includes/header.php';

$afiliaciones = felAfiliacionesIVA();
$deptos = ['Alta Verapaz','Baja Verapaz','Chimaltenango','Chiquimula','El Progreso','Escuintla','Guatemala','Huehuetenango','Izabal','Jalapa','Jutiapa','Petén','Quetzaltenango','Quiché','Retalhuleu','Sacatepéquez','San Marcos','Santa Rosa','Sololá','Suchitepéquez','Totonicapán','Zacapa'];
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-2">
    <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Facturación Electrónica (FEL)</h1>
    <?php if ($cfg['activo']): ?>
        <span class="px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-700">
            <i class="fas fa-check-circle mr-1"></i> Activo ·
            <?= $cfg['ambiente']==='produccion' ? 'Producción' : 'Implementación (pruebas)' ?>
        </span>
    <?php else: ?>
        <span class="px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
            <i class="fas fa-circle-pause mr-1"></i> Inactivo
        </span>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($faltan): ?>
<div class="bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded-lg text-sm mb-4">
    <div class="font-semibold mb-1">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <?= $cfg['activo'] ? 'Activo, pero aún no se puede certificar' : 'Faltan datos por configurar' ?>
    </div>
    <div class="text-amber-700 mb-1"><?= sanitize(implode(' · ', $faltan)) ?></div>
    <?php if ($cfg['activo']): ?>
    <div class="text-xs text-amber-600">
        El botón «Venta con FEL» ya aparece en el punto de venta, pero el certificador rechazará los envíos hasta completar estos datos.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST">
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <div class="xl:col-span-2 space-y-4">

        <!-- Credenciales -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-key"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Credenciales del certificador</h2>
                    <p class="text-xs text-gray-500">Las entrega INFILE al dar de alta el emisor.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Usuario de firma (prefijo)</label>
                    <input type="text" name="usuario_firma" value="<?= sanitize($cfg['usuario_firma'] ?? '') ?>" autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Usuario API</label>
                    <input type="text" name="usuario_api" value="<?= sanitize($cfg['usuario_api'] ?? '') ?>" autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Llave de firma (Token Signer)</label>
                    <input type="text" name="llave_firma" value="<?= sanitize($cfg['llave_firma'] ?? '') ?>" autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Llave API</label>
                    <input type="text" name="llave_api" value="<?= sanitize($cfg['llave_api'] ?? '') ?>" autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">URL de certificación</label>
                    <input type="text" name="url_certificacion" value="<?= sanitize($cfg['url_certificacion'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">URL de anulación</label>
                    <input type="text" name="url_anulacion" value="<?= sanitize($cfg['url_anulacion'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Datos del emisor -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-9 h-9 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Datos fiscales del emisor</h2>
                    <p class="text-xs text-gray-500">Deben coincidir exactamente con lo registrado en SAT.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">NIT del emisor</label>
                    <input type="text" name="nit_emisor" value="<?= sanitize($cfg['nit_emisor'] ?? '') ?>" placeholder="Sin guiones"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Código de establecimiento</label>
                    <input type="text" name="codigo_establecimiento" value="<?= sanitize($cfg['codigo_establecimiento'] ?? '1') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre del emisor / razón social</label>
                    <input type="text" name="nombre_emisor" value="<?= sanitize($cfg['nombre_emisor'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre comercial</label>
                    <input type="text" name="nombre_comercial" value="<?= sanitize($cfg['nombre_comercial'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Correo del emisor</label>
                    <input type="email" name="correo_emisor" value="<?= sanitize($cfg['correo_emisor'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Afiliación IVA</label>
                    <select name="afiliacion_iva" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($afiliaciones as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($cfg['afiliacion_iva']??'GEN')===$k?'selected':'' ?>><?= sanitize($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Porcentaje de IVA</label>
                    <input type="number" name="porcentaje_iva" value="<?= sanitize($cfg['porcentaje_iva'] ?? '12.00') ?>" step="0.01" min="0" max="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dirección fiscal</label>
                    <input type="text" name="direccion" value="<?= sanitize($cfg['direccion'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Municipio</label>
                    <input type="text" name="municipio" value="<?= sanitize($cfg['municipio'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Departamento</label>
                    <select name="departamento" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($deptos as $d): ?>
                        <option value="<?= $d ?>" <?= ($cfg['departamento']??'')===$d?'selected':'' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Código postal</label>
                    <input type="text" name="codigo_postal" value="<?= sanitize($cfg['codigo_postal'] ?? '01001') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Últimos documentos -->
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-800 text-sm uppercase tracking-wide">Últimos documentos emitidos</h2>
                <a href="documentos.php" class="text-sm text-blue-600 hover:underline">Ver todos</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-left">Receptor</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                            <th class="px-3 py-2 text-left">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($docs)): ?>
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Aún no se ha emitido ningún documento</td></tr>
                        <?php endif; ?>
                        <?php foreach ($docs as $d): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs"><?= sanitize($d['tipo_dte']) ?></td>
                            <td class="px-3 py-2"><?= sanitize($d['nombre_receptor']) ?></td>
                            <td class="px-3 py-2 text-right font-semibold"><?= formatMoney($d['gran_total']) ?></td>
                            <td class="px-3 py-2">
                                <?php
                                $cls = ['certificado'=>'bg-green-100 text-green-700','pendiente'=>'bg-yellow-100 text-yellow-700',
                                        'rechazado'=>'bg-red-100 text-red-700','anulado'=>'bg-gray-100 text-gray-600'];
                                ?>
                                <span class="px-2 py-0.5 rounded-full text-xs <?= $cls[$d['estado']] ?? '' ?>"><?= ucfirst($d['estado']) ?></span>
                            </td>
                            <td class="px-3 py-2 text-gray-500 text-xs"><?= formatDate($d['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Panel lateral -->
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow p-5 xl:sticky xl:top-6">
            <h2 class="font-semibold text-gray-800 mb-4 text-sm uppercase tracking-wide">Estado del servicio</h2>

            <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 mb-3">
                <input type="checkbox" name="activo" value="1" <?= $cfg['activo']?'checked':'' ?>
                       class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <div>
                    <div class="text-sm font-medium text-gray-800">Habilitar FEL</div>
                    <div class="text-xs text-gray-500">Muestra el botón «Venta con FEL» en el punto de venta. Puedes activarlo antes de tener las credenciales para revisar el flujo.</div>
                </div>
            </label>

            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Ambiente</label>
                <select name="ambiente" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="implementacion" <?= ($cfg['ambiente']??'')==='implementacion'?'selected':'' ?>>Implementación (pruebas)</option>
                    <option value="produccion"     <?= ($cfg['ambiente']??'')==='produccion'?'selected':'' ?>>Producción</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">En implementación los documentos salen con serie de pruebas y no tienen valor fiscal.</p>
            </div>

            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 mb-4">
                <div class="text-xs font-semibold text-blue-800 mb-1">Cómo funciona</div>
                <p class="text-xs text-blue-700 leading-relaxed">
                    El sistema arma el XML, lo envía a INFILE y recibe de vuelta el documento firmado con su número de autorización.
                    Las ventas pueden emitirse con o sin factura: el botón «Venta con FEL» aparece solo cuando este módulo está habilitado.
                </p>
            </div>

            <button type="submit" class="w-full bg-blue-700 text-white py-3 rounded-lg hover:bg-blue-800 font-medium text-sm transition">
                <i class="fas fa-save mr-1"></i> Guardar configuración
            </button>

            <?php if ($cfg['activo']): ?>
            <a href="prueba.php" class="mt-2 block text-center border border-gray-300 text-gray-700 py-2.5 rounded-lg hover:bg-gray-50 text-sm transition">
                <i class="fas fa-vial mr-1"></i> Enviar documento de prueba
            </a>
            <?php endif; ?>
        </div>
    </div>

</div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
