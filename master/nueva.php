<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/empresa.php';
requireLogin();
requireSuper();

$db = getDB();
$errors = [];

$planes = [
    'basico'        => ['Básico',        1,  3, 0, 400],
    'taller'        => ['Taller',        1, 15, 1, 600],
    'multisucursal' => ['Multisucursal', 5, 50, 1, 1200],
];

$d = [
    'nombre'=>'', 'nit'=>'', 'contacto_nombre'=>'', 'contacto_telefono'=>'', 'contacto_correo'=>'',
    'plan'=>'taller', 'estado'=>'prueba', 'mensualidad'=>'600.00', 'meses'=>'1',
    'sucursal_nombre'=>'', 'sucursal_direccion'=>'', 'sucursal_telefono'=>'',
    'admin_nombre'=>'', 'admin_apellido'=>'', 'admin_usuario'=>'', 'admin_pass'=>'',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($d as $k=>$_) $d[$k] = trim($_POST[$k] ?? '');

    if (!$d['nombre'])          $errors[] = 'Escribe el nombre de la empresa.';
    if (!$d['sucursal_nombre']) $errors[] = 'Escribe el nombre de la primera sucursal.';
    if (!$d['admin_nombre'])    $errors[] = 'Escribe el nombre del administrador.';
    if (!$d['admin_usuario'])   $errors[] = 'Escribe el usuario del administrador.';
    if (strlen($d['admin_pass']) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';

    $chk = $db->prepare("SELECT id FROM usuarios WHERE username=?");
    $chk->execute([$d['admin_usuario']]);
    if ($chk->fetch()) $errors[] = 'Ese usuario ya existe. Elige otro.';

    if (empty($errors)) {
        [$lbl, $maxSuc, $maxUsr, $fel, $precio] = $planes[$d['plan']] ?? $planes['taller'];
        $meses = max(0, (int)$d['meses']);
        $vence = $meses > 0 ? date('Y-m-d', strtotime("+$meses month")) : null;

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $d['nombre']));
        $slug = trim($slug,'-') . '-' . substr(bin2hex(random_bytes(2)),0,4);

        try {
            $db->beginTransaction();

            $db->prepare("INSERT INTO empresas
                    (nombre,nit,slug,contacto_nombre,contacto_telefono,contacto_correo,
                     plan,estado,fecha_inicio,fecha_vence,mensualidad,
                     max_sucursales,max_usuarios,fel_habilitado)
                  VALUES (?,?,?,?,?,?,?,?,CURDATE(),?,?,?,?,?)")
               ->execute([
                   $d['nombre'], $d['nit'] ?: null, $slug,
                   $d['contacto_nombre'] ?: null, $d['contacto_telefono'] ?: null, $d['contacto_correo'] ?: null,
                   $d['plan'], $d['estado'], $vence, (float)$d['mensualidad'],
                   $maxSuc, $maxUsr, $fel,
               ]);
            $empresaId = (int)$db->lastInsertId();

            // Primera sucursal
            $db->prepare("INSERT INTO sucursales (empresa_id,nombre,direccion,telefono) VALUES (?,?,?,?)")
               ->execute([$empresaId, $d['sucursal_nombre'], $d['sucursal_direccion'] ?: null, $d['sucursal_telefono'] ?: null]);
            $sucursalId = (int)$db->lastInsertId();

            // Administrador del taller
            $db->prepare("INSERT INTO usuarios (sucursal_id,nombre,apellido,rol_id,username,password_hash,activo)
                          VALUES (?,?,?,?,?,?,1)")
               ->execute([$sucursalId, $d['admin_nombre'], $d['admin_apellido'] ?: '',
                          ROL_ADMIN, $d['admin_usuario'], password_hash($d['admin_pass'], PASSWORD_BCRYPT)]);

            // Catálogo inicial para que no empiecen con la pantalla vacía
            $cats = ['Aceites y lubricantes','Filtros','Frenos','Eléctrico','Suspensión','Servicios generales'];
            $qc = $db->prepare("INSERT INTO categorias (empresa_id,nombre) VALUES (?,?)");
            foreach ($cats as $c) $qc->execute([$empresaId, $c]);

            // Configuración FEL vacía, lista para llenar
            try {
                $db->prepare("INSERT INTO fel_config (sucursal_id) VALUES (?)")->execute([$sucursalId]);
            } catch (Exception $e) { /* el módulo FEL puede no estar instalado */ }

            $db->commit();
            masterLog('crear_empresa', $empresaId, $d['nombre'] . ' · usuario ' . $d['admin_usuario']);
            flashMessage('success', "Empresa «{$d['nombre']}» creada. El administrador ya puede entrar con el usuario {$d['admin_usuario']}.");
            header('Location: ver.php?id=' . $empresaId); exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'No se pudo crear la empresa: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nueva empresa';
require_once __DIR__ . '/includes/layout.php';
?>

<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10">
        <i class="fas fa-arrow-left text-sm"></i>
    </a>
    <div>
        <h1 class="text-[24px] font-semibold tracking-tight">Nueva empresa</h1>
        <p class="text-slate-400 text-[13px] mt-0.5">Queda lista para usar: sucursal, administrador y catálogo inicial.</p>
    </div>
</div>

<?php if ($errors): ?>
<div class="mb-5 px-4 py-3 rounded-xl text-[13.5px] bg-red-500/10 border border-red-500/30 text-red-300">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-4 max-w-3xl" x-data="{ plan: '<?= $d['plan'] ?>' }">

    <!-- Datos de la empresa -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-4">Datos del taller</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label>Nombre de la empresa *</label>
                <input type="text" name="nombre" value="<?= sanitize($d['nombre']) ?>" required placeholder="Taller El Rápido">
            </div>
            <div><label>NIT</label><input type="text" name="nit" value="<?= sanitize($d['nit']) ?>"></div>
            <div><label>Contacto</label><input type="text" name="contacto_nombre" value="<?= sanitize($d['contacto_nombre']) ?>" placeholder="Nombre del dueño"></div>
            <div><label>Teléfono</label><input type="text" name="contacto_telefono" value="<?= sanitize($d['contacto_telefono']) ?>"></div>
            <div><label>Correo</label><input type="email" name="contacto_correo" value="<?= sanitize($d['contacto_correo']) ?>"></div>
        </div>
    </section>

    <!-- Contrato -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-4">Contrato</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label>Plan</label>
                <select name="plan" x-model="plan">
                    <?php foreach ($planes as $k=>[$lbl,$ms,$mu,$fel,$pr]): ?>
                    <option value="<?= $k ?>" <?= $d['plan']===$k?'selected':'' ?>>
                        <?= $lbl ?> — <?= $ms ?> sucursal(es), <?= $mu ?> usuarios<?= $fel ? ', con FEL' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Estado inicial</label>
                <select name="estado">
                    <option value="prueba" <?= $d['estado']==='prueba'?'selected':'' ?>>En prueba</option>
                    <option value="activa" <?= $d['estado']==='activa'?'selected':'' ?>>Activa</option>
                </select>
            </div>
            <div>
                <label>Mensualidad (Q)</label>
                <input type="number" name="mensualidad" value="<?= sanitize($d['mensualidad']) ?>" step="0.01" min="0">
            </div>
            <div>
                <label>Vence en</label>
                <select name="meses">
                    <option value="1"  <?= $d['meses']==='1'?'selected':'' ?>>1 mes</option>
                    <option value="3"  <?= $d['meses']==='3'?'selected':'' ?>>3 meses</option>
                    <option value="6"  <?= $d['meses']==='6'?'selected':'' ?>>6 meses</option>
                    <option value="12" <?= $d['meses']==='12'?'selected':'' ?>>12 meses</option>
                    <option value="0"  <?= $d['meses']==='0'?'selected':'' ?>>Sin vencimiento</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Primera sucursal -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-4">Primera sucursal</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label>Nombre *</label>
                <input type="text" name="sucursal_nombre" value="<?= sanitize($d['sucursal_nombre']) ?>" required placeholder="Sucursal Central">
            </div>
            <div><label>Dirección</label><input type="text" name="sucursal_direccion" value="<?= sanitize($d['sucursal_direccion']) ?>"></div>
            <div><label>Teléfono</label><input type="text" name="sucursal_telefono" value="<?= sanitize($d['sucursal_telefono']) ?>"></div>
        </div>
    </section>

    <!-- Administrador -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-1">Administrador del taller</h2>
        <p class="text-[12.5px] text-slate-400 mb-4">Con estas credenciales entra el dueño. Dáselas al entregar el sistema.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label>Nombre *</label><input type="text" name="admin_nombre" value="<?= sanitize($d['admin_nombre']) ?>" required></div>
            <div><label>Apellido</label><input type="text" name="admin_apellido" value="<?= sanitize($d['admin_apellido']) ?>"></div>
            <div><label>Usuario *</label><input type="text" name="admin_usuario" value="<?= sanitize($d['admin_usuario']) ?>" required autocomplete="off" placeholder="sin espacios"></div>
            <div><label>Contraseña *</label><input type="text" name="admin_pass" value="<?= sanitize($d['admin_pass']) ?>" required autocomplete="off" placeholder="mínimo 6 caracteres"></div>
        </div>
    </section>

    <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-medium text-[14px] px-6 py-3 rounded-[10px]">
            Crear empresa
        </button>
        <a href="index.php" class="px-6 py-3 text-[14px] text-slate-400 hover:text-white">Cancelar</a>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pie.php'; ?>
