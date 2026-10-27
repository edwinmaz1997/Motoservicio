<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/empresa.php';
requireLogin();
requireSuper();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM empresas WHERE id=?");
$stmt->execute([$id]);
$e = $stmt->fetch();
if (!$e) { flashMessage('error','Esa empresa no existe.'); header('Location: index.php'); exit; }

$planes = [
    'basico'        => ['Básico',        1,  3, 0],
    'taller'        => ['Taller',        1, 15, 1],
    'multisucursal' => ['Multisucursal', 5, 50, 1],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $db->prepare("UPDATE empresas SET nombre=?, nit=?, contacto_nombre=?, contacto_telefono=?, contacto_correo=? WHERE id=?")
           ->execute([
               trim($_POST['nombre']), trim($_POST['nit']) ?: null,
               trim($_POST['contacto_nombre']) ?: null,
               trim($_POST['contacto_telefono']) ?: null,
               trim($_POST['contacto_correo']) ?: null,
               $id,
           ]);
        masterLog('editar_datos', $id, trim($_POST['nombre']));
        flashMessage('success','Datos actualizados.');

    } elseif ($accion === 'contrato') {
        $plan = $_POST['plan'] ?? $e['plan'];
        [$lbl,$ms,$mu,$fel] = $planes[$plan] ?? $planes['taller'];
        $db->prepare("UPDATE empresas SET plan=?, estado=?, mensualidad=?, max_sucursales=?, max_usuarios=?, fel_habilitado=? WHERE id=?")
           ->execute([
               $plan, $_POST['estado'], (float)$_POST['mensualidad'],
               max((int)$_POST['max_sucursales'], 1),
               max((int)$_POST['max_usuarios'], 1),
               isset($_POST['fel_habilitado']) ? 1 : 0,
               $id,
           ]);
        masterLog('editar_contrato', $id, "plan $plan · estado " . $_POST['estado']);
        flashMessage('success','Contrato actualizado.');

    } elseif ($accion === 'renovar') {
        $meses = max(1, (int)$_POST['meses']);
        $base  = ($e['fecha_vence'] && $e['fecha_vence'] >= date('Y-m-d')) ? $e['fecha_vence'] : date('Y-m-d');
        $nuevo = date('Y-m-d', strtotime("$base +$meses month"));
        $db->prepare("UPDATE empresas SET fecha_vence=?, estado=IF(estado IN ('suspendida','prueba'),'activa',estado) WHERE id=?")
           ->execute([$nuevo, $id]);
        masterLog('renovar', $id, "+$meses mes(es), vence $nuevo");
        flashMessage('success', "Renovada hasta el " . formatDate($nuevo) . '.');

    } elseif ($accion === 'estado') {
        $nuevo = $_POST['estado'];
        $db->prepare("UPDATE empresas SET estado=? WHERE id=?")->execute([$nuevo, $id]);
        masterLog('cambiar_estado', $id, $nuevo);
        flashMessage('success', 'Empresa marcada como ' . $nuevo . '.');

    } elseif ($accion === 'reset_pass') {
        $uid  = (int)$_POST['usuario_id'];
        $pass = trim($_POST['nueva_pass']);
        if (strlen($pass) < 6) {
            flashMessage('error','La contraseña debe tener al menos 6 caracteres.');
        } else {
            $db->prepare("UPDATE usuarios u JOIN sucursales s ON s.id=u.sucursal_id
                          SET u.password_hash=? WHERE u.id=? AND s.empresa_id=?")
               ->execute([password_hash($pass, PASSWORD_BCRYPT), $uid, $id]);
            masterLog('reset_pass', $id, 'usuario #' . $uid);
            flashMessage('success','Contraseña cambiada.');
        }
    }

    header('Location: ver.php?id=' . $id); exit;
}

$uso = usoEmpresa($id);

// Sucursales con su actividad
$suc = $db->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM usuarios WHERE sucursal_id=s.id AND activo=1) AS n_usr,
           (SELECT COUNT(*) FROM ordenes  WHERE sucursal_id=s.id) AS n_ord
    FROM sucursales s WHERE s.empresa_id=? ORDER BY s.nombre");
$suc->execute([$id]);
$sucursales = $suc->fetchAll();

// Usuarios de la empresa
$usr = $db->prepare("
    SELECT u.id,u.nombre,u.apellido,u.username,u.rol_id,u.activo,s.nombre AS sucursal
    FROM usuarios u JOIN sucursales s ON s.id=u.sucursal_id
    WHERE s.empresa_id=? ORDER BY u.rol_id, u.nombre");
$usr->execute([$id]);
$usuarios = $usr->fetchAll();

$roles = [ROL_ADMIN=>'Administrador', ROL_TECNICO=>'Técnico', ROL_VENDEDOR=>'Vendedor',
          ROL_CAJERO=>'Cajero', ROL_ASESOR_VENTAS=>'Asesor de ventas'];

// Actividad del taller
$act = $db->prepare("
    SELECT (SELECT COUNT(*) FROM ordenes o JOIN sucursales s ON s.id=o.sucursal_id WHERE s.empresa_id=?) AS ordenes,
           (SELECT COUNT(*) FROM clientes c JOIN sucursales s ON s.id=c.sucursal_id WHERE s.empresa_id=?) AS clientes,
           (SELECT COALESCE(SUM(v.total),0) FROM ventas v JOIN sucursales s ON s.id=v.sucursal_id WHERE s.empresa_id=?) AS facturado,
           (SELECT MAX(o.created_at) FROM ordenes o JOIN sucursales s ON s.id=o.sucursal_id WHERE s.empresa_id=?) AS ultima");
$act->execute([$id,$id,$id,$id]);
$a = $act->fetch();

$bloqueo = empresaBloqueada($id);
$pageTitle = $e['nombre'] . ' — Panel maestro';
require_once __DIR__ . '/includes/layout.php';
?>

<div class="flex items-start justify-between gap-4 flex-wrap mb-6">
    <div class="flex items-center gap-3">
        <a href="index.php" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-[24px] font-semibold tracking-tight"><?= sanitize($e['nombre']) ?></h1>
            <p class="text-slate-400 text-[13px] mt-0.5">
                <?= $e['nit'] ? 'NIT ' . sanitize($e['nit']) . ' · ' : '' ?>
                Cliente desde <?= formatDate($e['fecha_inicio']) ?>
            </p>
        </div>
    </div>
    <?php if ($sucursales): ?>
    <a href="entrar.php?id=<?= $id ?>" class="bg-blue-600 hover:bg-blue-500 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-[10px] flex items-center gap-2">
        <i class="fas fa-right-to-bracket text-[11px]"></i> Entrar al sistema
    </a>
    <?php endif; ?>
</div>

<?php if ($bloqueo): ?>
<div class="mb-5 px-4 py-3 rounded-xl text-[13.5px] bg-amber-500/10 border border-amber-500/30 text-amber-300 flex items-center gap-3">
    <i class="fas fa-triangle-exclamation"></i>
    <span>
        <?php if ($bloqueo === 'vencida'): ?>
            El contrato venció el <?= formatDate($e['fecha_vence']) ?>. El taller no puede entrar hasta que lo renueves.
        <?php elseif ($bloqueo === 'suspendida'): ?>
            Esta empresa está suspendida. Nadie del taller puede entrar.
        <?php else: ?>
            Esta empresa está cancelada.
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>

<!-- Actividad -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <?php
    $cards = [
        ['Órdenes',    number_format($a['ordenes']),   'fa-clipboard-list'],
        ['Clientes',   number_format($a['clientes']),  'fa-users'],
        ['Facturado',  formatMoney($a['facturado']),   'fa-receipt'],
        ['Última orden', $a['ultima'] ? formatDate($a['ultima']) : '—', 'fa-clock'],
    ];
    foreach ($cards as [$lbl,$val,$ic]): ?>
    <div class="panel rounded-xl p-4">
        <i class="fas <?= $ic ?> text-slate-500 text-[13px] mb-3 block"></i>
        <div class="text-[20px] font-semibold tabular leading-none"><?= $val ?></div>
        <div class="text-[12px] text-slate-400 mt-1.5"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Contrato -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-4">Contrato</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="contrato">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label>Plan</label>
                    <select name="plan">
                        <?php foreach ($planes as $k=>[$lbl,$ms,$mu,$f]): ?>
                        <option value="<?= $k ?>" <?= $e['plan']===$k?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Estado</label>
                    <select name="estado">
                        <?php foreach (['activa'=>'Activa','prueba'=>'En prueba','suspendida'=>'Suspendida','cancelada'=>'Cancelada'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $e['estado']===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Mensualidad (Q)</label>
                    <input type="number" name="mensualidad" value="<?= $e['mensualidad'] ?>" step="0.01" min="0">
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 cursor-pointer mb-0">
                        <input type="checkbox" name="fel_habilitado" value="1" class="w-4 h-4" <?= $e['fel_habilitado']?'checked':'' ?>>
                        <span class="text-[13px] text-slate-300">Puede usar FEL</span>
                    </label>
                </div>
                <div>
                    <label>Máx. sucursales <span class="text-slate-500">(usa <?= $uso['sucursales'] ?>)</span></label>
                    <input type="number" name="max_sucursales" value="<?= $e['max_sucursales'] ?>" min="1">
                </div>
                <div>
                    <label>Máx. usuarios <span class="text-slate-500">(usa <?= $uso['usuarios'] ?>)</span></label>
                    <input type="number" name="max_usuarios" value="<?= $e['max_usuarios'] ?>" min="1">
                </div>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-[10px]">
                Guardar contrato
            </button>
        </form>
    </section>

    <!-- Vigencia -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-1">Vigencia</h2>
        <p class="text-[13px] text-slate-400 mb-4">
            <?php if (!$e['fecha_vence']): ?>
                Sin fecha de vencimiento.
            <?php else:
                $dias = floor((strtotime($e['fecha_vence']) - strtotime(date('Y-m-d'))) / 86400); ?>
                Vence el <strong class="text-slate-200"><?= formatDate($e['fecha_vence']) ?></strong>
                <?= $dias < 0 ? '· hace ' . abs($dias) . ' día(s)' : '· en ' . $dias . ' día(s)' ?>
            <?php endif; ?>
        </p>

        <form method="POST" class="flex gap-2 items-end mb-5">
            <input type="hidden" name="accion" value="renovar">
            <div class="flex-1">
                <label>Renovar por</label>
                <select name="meses">
                    <option value="1">1 mes</option>
                    <option value="3">3 meses</option>
                    <option value="6">6 meses</option>
                    <option value="12">12 meses</option>
                </select>
            </div>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-[10px] whitespace-nowrap">
                Renovar
            </button>
        </form>

        <div class="pt-4 border-t border-white/10 flex gap-2 flex-wrap">
            <?php if ($e['estado'] !== 'suspendida'): ?>
            <form method="POST" onsubmit="return confirm('¿Suspender a <?= sanitize($e['nombre']) ?>? Nadie del taller podrá entrar.')">
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="estado" value="suspendida">
                <button type="submit" class="text-[13px] px-3 py-2 rounded-lg border border-amber-500/30 text-amber-300 hover:bg-amber-500/10">
                    <i class="fas fa-pause text-[11px] mr-1.5"></i>Suspender
                </button>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="estado" value="activa">
                <button type="submit" class="text-[13px] px-3 py-2 rounded-lg border border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/10">
                    <i class="fas fa-play text-[11px] mr-1.5"></i>Reactivar
                </button>
            </form>
            <?php endif; ?>

            <?php if ($e['estado'] !== 'cancelada'): ?>
            <form method="POST" onsubmit="return confirm('¿Cancelar el servicio de <?= sanitize($e['nombre']) ?>? Sus datos se conservan, pero pierde el acceso.')">
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="estado" value="cancelada">
                <button type="submit" class="text-[13px] px-3 py-2 rounded-lg border border-red-500/30 text-red-300 hover:bg-red-500/10">
                    <i class="fas fa-ban text-[11px] mr-1.5"></i>Cancelar servicio
                </button>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- Datos -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-4">Datos y contacto</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="datos">
            <div><label>Nombre</label><input type="text" name="nombre" value="<?= sanitize($e['nombre']) ?>" required></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>NIT</label><input type="text" name="nit" value="<?= sanitize($e['nit']) ?>"></div>
                <div><label>Contacto</label><input type="text" name="contacto_nombre" value="<?= sanitize($e['contacto_nombre']) ?>"></div>
                <div><label>Teléfono</label><input type="text" name="contacto_telefono" value="<?= sanitize($e['contacto_telefono']) ?>"></div>
                <div><label>Correo</label><input type="email" name="contacto_correo" value="<?= sanitize($e['contacto_correo']) ?>"></div>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-[10px]">
                Guardar datos
            </button>
        </form>
    </section>

    <!-- Sucursales -->
    <section class="panel rounded-xl p-5">
        <h2 class="text-[15px] font-semibold mb-4">
            Sucursales <span class="text-slate-500 font-normal text-[13px]">· <?= $uso['sucursales'] ?> de <?= $uso['max_sucursales'] ?></span>
        </h2>
        <div class="space-y-2">
            <?php if (!$sucursales): ?>
            <p class="text-[13px] text-slate-500">Todavía no tiene sucursales.</p>
            <?php endif; ?>
            <?php foreach ($sucursales as $s): ?>
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-white/[.03] border border-white/5">
                <div>
                    <div class="text-[13.5px] font-medium"><?= sanitize($s['nombre']) ?></div>
                    <div class="text-[12px] text-slate-500"><?= sanitize($s['direccion'] ?: 'Sin dirección') ?></div>
                </div>
                <div class="text-[12px] text-slate-400 tabular whitespace-nowrap">
                    <?= $s['n_usr'] ?> usr · <?= $s['n_ord'] ?> órd
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<!-- Usuarios -->
<section class="panel rounded-xl overflow-hidden mt-4" x-data="{ reset: null }">
    <div class="px-5 py-4 border-b border-white/10">
        <h2 class="text-[15px] font-semibold">
            Usuarios <span class="text-slate-500 font-normal text-[13px]">· <?= $uso['usuarios'] ?> de <?= $uso['max_usuarios'] ?></span>
        </h2>
    </div>
    <table>
        <thead><tr>
            <th>Nombre</th><th>Usuario</th><th>Rol</th><th>Sucursal</th><th>Estado</th><th></th>
        </tr></thead>
        <tbody>
            <?php if (!$usuarios): ?>
            <tr><td colspan="6" class="text-center text-slate-500 py-8">Sin usuarios</td></tr>
            <?php endif; ?>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td class="font-medium"><?= sanitize(trim($u['nombre'].' '.$u['apellido'])) ?></td>
                <td class="text-slate-300"><?= sanitize($u['username']) ?></td>
                <td class="text-slate-400"><?= $roles[$u['rol_id']] ?? '—' ?></td>
                <td class="text-slate-400"><?= sanitize($u['sucursal']) ?></td>
                <td>
                    <?php if ($u['activo']): ?>
                    <span class="inline-block px-2 py-0.5 rounded-full text-[11.5px] font-medium border bg-emerald-500/15 text-emerald-300 border-emerald-500/25">Activo</span>
                    <?php else: ?>
                    <span class="inline-block px-2 py-0.5 rounded-full text-[11.5px] font-medium border bg-slate-500/15 text-slate-400 border-slate-500/25">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <button type="button" @click="reset = reset === <?= $u['id'] ?> ? null : <?= $u['id'] ?>"
                            class="text-[12.5px] text-slate-400 hover:text-white">
                        <i class="fas fa-key text-[11px] mr-1"></i>Contraseña
                    </button>
                </td>
            </tr>
            <tr x-show="reset === <?= $u['id'] ?>" x-cloak>
                <td colspan="6" class="bg-white/[.03]">
                    <form method="POST" class="flex gap-2 items-center flex-wrap">
                        <input type="hidden" name="accion" value="reset_pass">
                        <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                        <span class="text-[12.5px] text-slate-400">Nueva contraseña para <?= sanitize($u['username']) ?>:</span>
                        <input type="text" name="nueva_pass" required autocomplete="off" placeholder="mínimo 6 caracteres" class="w-56">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white text-[13px] px-3 py-2 rounded-lg">Cambiar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<style>[x-cloak]{display:none!important}</style>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pie.php'; ?>
