<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
refreshSession();
$user = currentUser();
requireRol(ROL_ADMIN);

$db  = getDB();
$suc = $user['sucursal_id'];

// Cargar datos actuales
$stmt = $db->prepare("SELECT * FROM sucursales WHERE id = ?");
$stmt->execute([$suc]);
$sucursal = $stmt->fetch();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'info';

    if ($action === 'info') {
        $nombre       = trim($_POST['nombre']        ?? '');
        $razonSocial  = trim($_POST['razon_social']  ?? '');
        $nit          = trim($_POST['nit']           ?? '');
        $telefono     = trim($_POST['telefono']      ?? '');
        $correo       = trim($_POST['correo']        ?? '');
        $direccion    = trim($_POST['direccion']     ?? '');
        $municipio    = trim($_POST['municipio']     ?? '');
        $departamento = trim($_POST['departamento']  ?? '');
        $slogan       = trim($_POST['slogan']        ?? '');
        $horario      = trim($_POST['horario']       ?? '');
        $colorPrim    = trim($_POST['color_primario']   ?? '#1d4ed8');
        $colorSec     = trim($_POST['color_secundario'] ?? '#1e40af');

        if (!$nombre) $errors[] = 'El nombre es requerido.';

        // Subir logo si viene
        $logoPath = $sucursal['logo_path'] ?? null;
        if (!empty($_FILES['logo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','svg','webp'])) {
                $errors[] = 'Formato de logo no válido. Usar JPG, PNG, SVG o WEBP.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $errors[] = 'El logo no debe superar 2MB.';
            } else {
                $filename = 'logo_' . $suc . '_' . time() . '.' . $ext;
                $dest     = __DIR__ . '/../../uploads/logos/' . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    // Borrar logo anterior
                    if ($logoPath && file_exists(__DIR__ . '/../../uploads/logos/' . basename($logoPath))) {
                        @unlink(__DIR__ . '/../../uploads/logos/' . basename($logoPath));
                    }
                    $logoPath = 'uploads/logos/' . $filename;
                } else {
                    $errors[] = 'No se pudo guardar el logo.';
                }
            }
        }

        // Eliminar logo
        if (isset($_POST['eliminar_logo']) && $logoPath) {
            @unlink(__DIR__ . '/../../uploads/logos/' . basename($logoPath));
            $logoPath = null;
        }

        if (empty($errors)) {
            $db->prepare("UPDATE sucursales SET nombre=?,razon_social=?,nit=?,telefono=?,correo=?,direccion=?,municipio=?,departamento=?,slogan=?,horario=?,color_primario=?,color_secundario=?,logo_path=? WHERE id=?")
               ->execute([$nombre,$razonSocial,$nit,$telefono,$correo,$direccion,$municipio,$departamento,$slogan,$horario,$colorPrim,$colorSec,$logoPath,$suc]);

            flashMessage('success', 'Configuración guardada.');
            header('Location: index.php'); exit;
        }
    }
}

// Recargar
$stmt->execute([$suc]);
$sucursal = $stmt->fetch();

$pageTitle = 'Configuración de Sucursal';
require_once __DIR__ . '/../../includes/header.php';

$colores = [
    '#1d4ed8' => 'Azul',
    '#1e40af' => 'Azul oscuro',
    '#0f766e' => 'Verde azulado',
    '#15803d' => 'Verde',
    '#b91c1c' => 'Rojo',
    '#b45309' => 'Ámbar',
    '#7c3aed' => 'Morado',
    '#be185d' => 'Rosa',
    '#0e7490' => 'Cian',
    '#374151' => 'Gris oscuro',
    '#111827' => 'Negro',
];
?>

<div class="flex items-center gap-3 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Configuración de Sucursal</h1>
</div>

<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" x-data="{ colorPrim: '<?= sanitize($sucursal['color_primario'] ?? '#1d4ed8') ?>', colorSec: '<?= sanitize($sucursal['color_secundario'] ?? '#1e40af') ?>' }">
<input type="hidden" name="action" value="info">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Columna principal -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Identidad -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Identidad</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre comercial *</label>
                    <input type="text" name="nombre" value="<?= sanitize($sucursal['nombre'] ?? '') ?>" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Razón social</label>
                    <input type="text" name="razon_social" value="<?= sanitize($sucursal['razon_social'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIT</label>
                    <input type="text" name="nit" value="<?= sanitize($sucursal['nit'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slogan</label>
                    <input type="text" name="slogan" value="<?= sanitize($sucursal['slogan'] ?? '') ?>" placeholder="Tu slogan aquí..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Contacto -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Contacto y Ubicación</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="telefono" value="<?= sanitize($sucursal['telefono'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Correo</label>
                    <input type="email" name="correo" value="<?= sanitize($sucursal['correo'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <input type="text" name="direccion" value="<?= sanitize($sucursal['direccion'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Municipio</label>
                    <input type="text" name="municipio" value="<?= sanitize($sucursal['municipio'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                    <select name="departamento" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Seleccionar —</option>
                        <?php
                        $deptos = ['Alta Verapaz','Baja Verapaz','Chimaltenango','Chiquimula','El Progreso','Escuintla','Guatemala','Huehuetenango','Izabal','Jalapa','Jutiapa','Petén','Quetzaltenango','Quiché','Retalhuleu','Sacatepéquez','San Marcos','Santa Rosa','Sololá','Suchitepéquez','Totonicapán','Zacapa'];
                        foreach ($deptos as $d):
                        ?>
                        <option value="<?= $d ?>" <?= ($sucursal['departamento']??'')===$d?'selected':'' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Horario de atención</label>
                    <input type="text" name="horario" value="<?= sanitize($sucursal['horario'] ?? '') ?>" placeholder="Lunes a Viernes 8:00 - 18:00"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Logo -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Logo</h2>
            <div class="flex items-start gap-6 flex-wrap">
                <!-- Preview actual -->
                <div class="flex-shrink-0">
                    <?php if (!empty($sucursal['logo_path'])): ?>
                    <div class="w-32 h-32 border-2 border-gray-200 rounded-xl overflow-hidden bg-gray-50 flex items-center justify-center">
                        <img src="<?= BASE_URL ?>/<?= sanitize($sucursal['logo_path']) ?>" alt="Logo" class="max-w-full max-h-full object-contain p-2">
                    </div>
                    <label class="flex items-center gap-2 mt-2 text-xs text-red-500 cursor-pointer hover:text-red-700">
                        <input type="checkbox" name="eliminar_logo" value="1"> Eliminar logo
                    </label>
                    <?php else: ?>
                    <div class="w-32 h-32 border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 flex flex-col items-center justify-center text-gray-400">
                        <i class="fas fa-image text-3xl mb-2"></i>
                        <span class="text-xs">Sin logo</span>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Upload -->
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Subir nuevo logo</label>
                    <input type="file" name="logo" accept=".jpg,.jpeg,.png,.svg,.webp"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-2">JPG, PNG, SVG o WEBP — máx 2MB. Recomendado: fondo transparente PNG.</p>
                </div>
            </div>
        </div>

        <!-- Colores -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Colores del sistema</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Color primario</label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <?php foreach ($colores as $hex => $nombre): ?>
                        <button type="button"
                                @click="colorPrim = '<?= $hex ?>'"
                                :class="colorPrim === '<?= $hex ?>' ? 'ring-2 ring-offset-2 ring-gray-400 scale-110' : ''"
                                class="w-8 h-8 rounded-full transition-transform"
                                style="background-color: <?= $hex ?>"
                                title="<?= $nombre ?>">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="color" x-model="colorPrim" name="color_primario"
                               class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                        <span class="text-sm font-mono text-gray-600" x-text="colorPrim"></span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Color secundario</label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <?php foreach ($colores as $hex => $nombre): ?>
                        <button type="button"
                                @click="colorSec = '<?= $hex ?>'"
                                :class="colorSec === '<?= $hex ?>' ? 'ring-2 ring-offset-2 ring-gray-400 scale-110' : ''"
                                class="w-8 h-8 rounded-full transition-transform"
                                style="background-color: <?= $hex ?>"
                                title="<?= $nombre ?>">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="color" x-model="colorSec" name="color_secundario"
                               class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                        <span class="text-sm font-mono text-gray-600" x-text="colorSec"></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Preview sidebar -->
    <div>
        <div class="bg-white rounded-xl shadow p-5 sticky top-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wide">Vista previa</h2>

            <!-- Mini sidebar preview -->
            <div class="rounded-xl overflow-hidden border border-gray-200 mb-4">
                <div class="px-3 py-3 flex items-center gap-2" :style="'background-color: ' + colorPrim">
                    <?php if (!empty($sucursal['logo_path'])): ?>
                    <img src="<?= BASE_URL ?>/<?= sanitize($sucursal['logo_path']) ?>" class="w-7 h-7 object-contain rounded" alt="">
                    <?php else: ?>
                    <div class="w-7 h-7 rounded bg-white bg-opacity-20 flex items-center justify-center text-white text-xs font-bold">
                        🏍️
                    </div>
                    <?php endif; ?>
                    <span class="text-white text-sm font-bold truncate"><?= sanitize($sucursal['nombre'] ?? 'DIAZ') ?></span>
                </div>
                <div class="p-3 space-y-1.5 bg-gray-900">
                    <?php foreach (['Dashboard','Órdenes','Clientes','Motocicletas'] as $item): ?>
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded text-gray-400 text-xs">
                        <div class="w-3 h-3 rounded-sm bg-gray-600"></div>
                        <?= $item ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Preview botón -->
            <div class="space-y-2">
                <div class="w-full py-2 rounded-lg text-white text-sm text-center font-medium" :style="'background-color: ' + colorPrim">
                    Botón primario
                </div>
                <div class="w-full py-2 rounded-lg text-white text-sm text-center font-medium" :style="'background-color: ' + colorSec">
                    Botón secundario
                </div>
            </div>

            <p class="text-xs text-gray-400 mt-3 text-center">Los colores se aplicarán al sidebar y botones del sistema.</p>

            <button type="submit" class="mt-5 w-full bg-blue-700 text-white py-3 rounded-lg hover:bg-blue-800 font-medium text-sm transition">
                <i class="fas fa-save mr-1"></i> Guardar configuración
            </button>
        </div>
    </div>

</div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
