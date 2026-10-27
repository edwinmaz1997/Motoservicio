<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/fel/FelClient.php';
requireLogin();
refreshSession();
$user = currentUser();
requireRol(ROL_ADMIN);

$suc       = $user['sucursal_id'];
$resultado = null;
$xmlPreview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'enviar';

    $receptor = [
        'nit'      => trim($_POST['nit'] ?? 'CF'),
        'nombre'   => trim($_POST['nombre'] ?? 'CONSUMIDOR FINAL'),
        'correo'   => trim($_POST['correo'] ?? ''),
    ];
    $items = [[
        'tipo'        => $_POST['tipo_item'] ?? 'S',
        'cantidad'    => 1,
        'unidad'      => ($_POST['tipo_item'] ?? 'S') === 'S' ? 'SRV' : 'UND',
        'descripcion' => trim($_POST['descripcion'] ?? 'SERVICIO DE PRUEBA'),
        'precio_unit' => (float)($_POST['monto'] ?? 100),
        'descuento'   => 0,
    ]];

    if ($accion === 'previsualizar') {
        $gen = felGenerarXml(felConfig($suc), $receptor, $items, ['tipo_dte'=>'FACT']);
        $xmlPreview = $gen['xml'];
    } else {
        $resultado = felCertificar($suc, $receptor, $items, [
            'tipo_dte'   => 'FACT',
            'usuario_id' => $user['id'],
            'adenda'     => ['Origen' => 'Prueba de integracion'],
        ]);
    }
}

$pageTitle = 'Prueba FEL';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex items-center gap-3 mb-5">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Documento de prueba</h1>
</div>

<div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm mb-4">
    <i class="fas fa-info-circle mr-1"></i>
    Emite una factura real contra el certificador. Mientras el emisor esté en estado de implementación,
    el documento sale con serie de pruebas y no tiene valor fiscal ante SAT.
</div>

<?php if ($resultado): ?>
    <?php if ($resultado['ok']): ?>
    <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-4 rounded-lg text-sm mb-4">
        <div class="font-semibold mb-2"><i class="fas fa-check-circle mr-1"></i> Documento certificado correctamente</div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
            <div><span class="text-green-600 block">Serie</span><span class="font-mono"><?= sanitize($resultado['serie'] ?: '—') ?></span></div>
            <div><span class="text-green-600 block">Número</span><span class="font-mono"><?= sanitize($resultado['numero'] ?: '—') ?></span></div>
            <div class="sm:col-span-3"><span class="text-green-600 block">Autorización</span><span class="font-mono break-all"><?= sanitize($resultado['uuid'] ?: '—') ?></span></div>
        </div>
        <a href="ver.php?id=<?= $resultado['documento_id'] ?>" class="inline-block mt-3 text-green-700 underline">Ver el documento completo</a>
    </div>
    <?php else: ?>
    <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-4 rounded-lg text-sm mb-4">
        <div class="font-semibold mb-1"><i class="fas fa-times-circle mr-1"></i> El certificador rechazó el documento</div>
        <p class="text-red-700"><?= nl2br(sanitize($resultado['error'])) ?></p>
        <?php if (!empty($resultado['documento_id'])): ?>
        <a href="ver.php?id=<?= $resultado['documento_id'] ?>" class="inline-block mt-2 text-red-700 underline">Ver el XML enviado</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Datos del documento</h2>
        <form method="POST" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">NIT del receptor</label>
                    <input type="text" name="nit" value="<?= sanitize($_POST['nit'] ?? 'CF') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre del receptor</label>
                    <input type="text" name="nombre" value="<?= sanitize($_POST['nombre'] ?? 'CONSUMIDOR FINAL') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Correo del receptor (opcional)</label>
                    <input type="email" name="correo" value="<?= sanitize($_POST['correo'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
                    <input type="text" name="descripcion" value="<?= sanitize($_POST['descripcion'] ?? 'SERVICIO DE PRUEBA') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                    <select name="tipo_item" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="S" <?= ($_POST['tipo_item'] ?? 'S')==='S'?'selected':'' ?>>Servicio</option>
                        <option value="B" <?= ($_POST['tipo_item'] ?? '')==='B'?'selected':'' ?>>Bien</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Monto (IVA incluido)</label>
                    <input type="number" name="monto" value="<?= sanitize($_POST['monto'] ?? '100.00') ?>" step="0.01" min="0.01"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" name="accion" value="previsualizar"
                        class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
                    <i class="fas fa-code mr-1"></i> Ver XML
                </button>
                <button type="submit" name="accion" value="enviar"
                        class="bg-blue-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 font-medium">
                    <i class="fas fa-paper-plane mr-1"></i> Enviar al certificador
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">XML generado</h2>
        <?php if ($xmlPreview): ?>
        <pre class="bg-gray-900 text-green-300 text-xs p-3 rounded-lg overflow-x-auto max-h-[28rem]"><?= sanitize($xmlPreview) ?></pre>
        <?php else: ?>
        <div class="text-center py-12 text-gray-400 text-sm">
            <i class="fas fa-file-code text-3xl mb-2 block"></i>
            Pulsa «Ver XML» para revisar el documento antes de enviarlo.
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
