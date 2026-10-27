<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/empresa.php';
require_once __DIR__ . '/../../includes/fel/FelClient.php';
requireLogin();
refreshSession();
$user = currentUser();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT d.*, v.numero_venta, o.numero_orden
                      FROM fel_documentos d
                      LEFT JOIN ventas  v ON v.id = d.venta_id
                      LEFT JOIN ordenes o ON o.id = d.orden_id
                      WHERE d.id=? AND d.sucursal_id=?");
$stmt->execute([$id, $user['sucursal_id']]);
$doc = $stmt->fetch();
if (!$doc) { flashMessage('error','Documento no encontrado.'); header('Location: documentos.php'); exit; }

// Anulación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'anular') {
    if (!isAdmin()) { flashMessage('error','Solo un administrador puede anular documentos.'); header('Location: ver.php?id='.$id); exit; }
    $motivo = trim($_POST['motivo'] ?? '');
    if ($motivo === '') {
        flashMessage('error','Debes indicar el motivo de la anulación.');
    } else {
        $res = felAnular($id, $motivo);
        flashMessage($res['ok'] ? 'success' : 'error',
                     $res['ok'] ? 'Documento anulado ante SAT.' : 'No se pudo anular: ' . $res['error']);
    }
    header('Location: ver.php?id='.$id); exit;
}

// Descarga del XML certificado
if (($_GET['descargar'] ?? '') === 'xml' && $doc['xml_certificado']) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="DTE-' . ($doc['uuid'] ?: $doc['id']) . '.xml"');
    echo $doc['xml_certificado'];
    exit;
}

$pageTitle = 'Documento FEL';
require_once __DIR__ . '/../../includes/header.php';

$cls = ['certificado'=>'bg-green-100 text-green-700','pendiente'=>'bg-yellow-100 text-yellow-700',
        'rechazado'=>'bg-red-100 text-red-700','anulado'=>'bg-gray-100 text-gray-600'];
?>

<div class="flex items-center gap-3 mb-5 flex-wrap">
    <a href="documentos.php" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex-shrink-0"><i class="fas fa-arrow-left text-sm"></i></a>
    <h1 class="text-[20px] lg:text-[25px] font-semibold text-slate-900 tracking-tight"><?= sanitize($doc['tipo_dte']) ?> · <?= sanitize($doc['serie'] ?: '—') ?>-<?= sanitize($doc['numero'] ?: '—') ?></h1>
    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $cls[$doc['estado']] ?? '' ?>"><?= ucfirst($doc['estado']) ?></span>

    <div class="ml-auto flex gap-2">
        <?php if ($doc['xml_certificado']): ?>
        <a href="?id=<?= $id ?>&descargar=xml" class="bg-gray-700 text-white px-3 py-2 rounded-lg text-sm hover:bg-gray-800">
            <i class="fas fa-download mr-1"></i> XML
        </a>
        <?php endif; ?>
        <?php if ($doc['estado']==='certificado' && isAdmin()): ?>
        <button onclick="document.getElementById('modal-anular').classList.remove('hidden')"
                class="bg-red-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-600">
            <i class="fas fa-ban mr-1"></i> Anular
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-xl shadow p-4 lg:p-5">
            <h2 class="text-[15px] font-semibold text-slate-900 mb-3 tracking-tight">Datos del documento</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><span class="text-gray-400 text-xs block">Receptor</span><span class="font-medium"><?= sanitize($doc['nombre_receptor']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">NIT</span><span class="font-medium font-mono"><?= sanitize($doc['nit_receptor']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">Correo</span><span class="font-medium"><?= sanitize($doc['correo_receptor'] ?: '—') ?></span></div>
                <div><span class="text-gray-400 text-xs block">Monto gravable</span><span class="font-medium"><?= formatMoney($doc['monto_gravable']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">IVA</span><span class="font-medium"><?= formatMoney($doc['monto_iva']) ?></span></div>
                <div><span class="text-gray-400 text-xs block">Gran total</span><span class="font-bold text-base"><?= formatMoney($doc['gran_total']) ?></span></div>
                <div class="col-span-2 sm:col-span-3">
                    <span class="text-gray-400 text-xs block">Número de autorización</span>
                    <span class="font-mono text-xs break-all"><?= sanitize($doc['uuid'] ?: '—') ?></span>
                </div>
                <div><span class="text-gray-400 text-xs block">Certificado</span><span class="font-medium"><?= $doc['fecha_certificacion'] ? formatDate($doc['fecha_certificacion']) : '—' ?></span></div>
                <div><span class="text-gray-400 text-xs block">Origen</span>
                    <?php if ($doc['numero_venta']): ?>
                        <a href="../ventas/view.php?id=<?= $doc['venta_id'] ?>" class="font-mono text-blue-600 hover:underline"><?= sanitize($doc['numero_venta']) ?></a>
                    <?php elseif ($doc['numero_orden']): ?>
                        <a href="../ordenes/view.php?id=<?= $doc['orden_id'] ?>" class="font-mono text-blue-600 hover:underline"><?= sanitize($doc['numero_orden']) ?></a>
                    <?php else: ?><span class="text-gray-400">—</span><?php endif; ?>
                </div>
            </div>

            <?php if ($doc['estado']==='anulado'): ?>
            <div class="mt-4 pt-4 border-t">
                <span class="text-gray-400 text-xs block mb-1">Motivo de anulación</span>
                <p class="text-sm"><?= sanitize($doc['motivo_anulacion']) ?></p>
                <span class="text-gray-400 text-xs"><?= formatDate($doc['fecha_anulacion']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($doc['mensaje_error']): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <div class="font-semibold text-red-800 text-sm mb-1">Respuesta del certificador</div>
            <p class="text-sm text-red-700"><?= nl2br(sanitize($doc['mensaje_error'])) ?></p>
        </div>
        <?php endif; ?>

        <details class="bg-white rounded-xl shadow p-4 lg:p-5">
            <summary class="font-semibold text-gray-700 text-sm uppercase tracking-wide cursor-pointer">XML enviado</summary>
            <pre class="mt-3 bg-gray-900 text-green-300 text-xs p-3 rounded-lg overflow-x-auto max-h-96"><?= sanitize($doc['xml_enviado']) ?></pre>
        </details>

        <?php if ($doc['xml_certificado']): ?>
        <details class="bg-white rounded-xl shadow p-4 lg:p-5">
            <summary class="font-semibold text-gray-700 text-sm uppercase tracking-wide cursor-pointer">XML certificado</summary>
            <pre class="mt-3 bg-gray-900 text-blue-300 text-xs p-3 rounded-lg overflow-x-auto max-h-96"><?= sanitize($doc['xml_certificado']) ?></pre>
        </details>
        <?php endif; ?>
    </div>

    <div class="space-y-4 text-sm">
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-gray-400 text-xs mb-1">Identificador</div>
            <div class="font-mono text-xs break-all"><?= sanitize($doc['identificador']) ?></div>
            <div class="text-gray-400 text-xs mt-3 mb-1">Creado</div>
            <div><?= formatDate($doc['created_at']) ?></div>
        </div>
    </div>
</div>

<!-- Modal anular -->
<div id="modal-anular" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800">Anular documento</h3>
            <button onclick="document.getElementById('modal-anular').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-3">
            <input type="hidden" name="accion" value="anular">
            <p class="text-sm text-gray-600">La anulación se registra ante SAT y no se puede revertir.</p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo *</label>
                <textarea name="motivo" rows="3" required
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-red-700 font-medium">Anular ante SAT</button>
                <button type="button" onclick="document.getElementById('modal-anular').classList.add('hidden')"
                        class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
