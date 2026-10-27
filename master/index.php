<?php
$pageTitle = 'Empresas — Panel maestro';
require_once __DIR__ . '/includes/layout.php';

$db = getDB();
$q  = trim($_GET['q'] ?? '');
$estadoF = $_GET['estado'] ?? '';

$where = "WHERE 1=1"; $params = [];
if ($q) {
    $like = "%$q%";
    $where .= " AND (e.nombre LIKE ? OR e.nit LIKE ? OR e.contacto_nombre LIKE ?)";
    $params = [$like,$like,$like];
}
if ($estadoF) { $where .= " AND e.estado = ?"; $params[] = $estadoF; }

$stmt = $db->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM sucursales WHERE empresa_id=e.id) AS n_suc,
           (SELECT COUNT(*) FROM usuarios u JOIN sucursales s ON s.id=u.sucursal_id
             WHERE s.empresa_id=e.id AND u.activo=1) AS n_usr,
           (SELECT COUNT(*) FROM ordenes o JOIN sucursales s ON s.id=o.sucursal_id
             WHERE s.empresa_id=e.id) AS n_ord
    FROM empresas e $where ORDER BY e.nombre");
$stmt->execute($params);
$empresas = $stmt->fetchAll();

// Resumen de cartera
$hoy = date('Y-m-d');
$activas = $porVencer = $vencidas = 0;
$ingreso = 0.0;
foreach ($empresas as $e) {
    if ($e['estado'] === 'activa') {
        $activas++;
        $ingreso += (float)$e['mensualidad'];
        if ($e['fecha_vence']) {
            $dias = (strtotime($e['fecha_vence']) - strtotime($hoy)) / 86400;
            if ($dias < 0)       $vencidas++;
            elseif ($dias <= 7)  $porVencer++;
        }
    }
}

$colorEstado = [
    'activa'     => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/25',
    'prueba'     => 'bg-blue-500/15 text-blue-300 border-blue-500/25',
    'suspendida' => 'bg-amber-500/15 text-amber-300 border-amber-500/25',
    'cancelada'  => 'bg-red-500/15 text-red-300 border-red-500/25',
];
?>

<div class="flex items-end justify-between gap-4 flex-wrap mb-6">
    <div>
        <h1 class="text-[24px] font-semibold tracking-tight">Empresas</h1>
        <p class="text-slate-400 text-[13.5px] mt-0.5"><?= count($empresas) ?> en total</p>
    </div>
    <a href="nueva.php" class="bg-blue-600 hover:bg-blue-500 text-white text-[13.5px] font-medium px-4 py-2.5 rounded-[10px] flex items-center gap-2">
        <i class="fas fa-plus text-[11px]"></i> Nueva empresa
    </a>
</div>

<!-- Resumen -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <?php
    $tarjetas = [
        ['Activas',           $activas,                     'fa-circle-check',  'text-emerald-400'],
        ['Ingreso mensual',   'Q ' . number_format($ingreso,2), 'fa-sack-dollar', 'text-blue-400'],
        ['Vencen esta semana',$porVencer,                   'fa-clock',         'text-amber-400'],
        ['Vencidas',          $vencidas,                    'fa-triangle-exclamation', 'text-red-400'],
    ];
    foreach ($tarjetas as [$lbl,$val,$ic,$col]): ?>
    <div class="panel rounded-xl p-4">
        <i class="fas <?= $ic ?> <?= $col ?> text-[13px] mb-3 block"></i>
        <div class="text-[22px] font-semibold tabular leading-none"><?= $val ?></div>
        <div class="text-[12px] text-slate-400 mt-1.5"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<form method="GET" class="flex gap-2 mb-4 flex-wrap">
    <input type="search" name="q" value="<?= sanitize($q) ?>" placeholder="Buscar empresa, NIT o contacto..."
           class="flex-1 min-w-[200px]">
    <select name="estado" class="w-auto">
        <option value="">Todos los estados</option>
        <?php foreach (['activa'=>'Activas','prueba'=>'En prueba','suspendida'=>'Suspendidas','cancelada'=>'Canceladas'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $estadoF===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
    </select>
</form>

<!-- Listado -->
<div class="panel rounded-xl overflow-hidden">
    <table>
        <thead>
            <tr>
                <th>Empresa</th>
                <th>Plan</th>
                <th>Estado</th>
                <th>Vence</th>
                <th class="text-right">Mensualidad</th>
                <th class="text-center">Suc.</th>
                <th class="text-center">Usr.</th>
                <th class="text-center">Órdenes</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($empresas)): ?>
            <tr><td colspan="9" class="text-center text-slate-500 py-10">
                <?= $q || $estadoF ? 'Sin resultados' : 'Todavía no hay empresas registradas' ?>
            </td></tr>
            <?php endif; ?>

            <?php foreach ($empresas as $e):
                $vence = null;
                if ($e['fecha_vence']) $vence = (strtotime($e['fecha_vence']) - strtotime($hoy)) / 86400;
            ?>
            <tr>
                <td>
                    <a href="ver.php?id=<?= $e['id'] ?>" class="font-medium text-white hover:text-blue-300">
                        <?= sanitize($e['nombre']) ?>
                    </a>
                    <?php if ($e['contacto_nombre']): ?>
                    <div class="text-[12px] text-slate-500"><?= sanitize($e['contacto_nombre']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="capitalize text-slate-300"><?= sanitize($e['plan']) ?></td>
                <td>
                    <span class="inline-block px-2 py-0.5 rounded-full text-[11.5px] font-medium border <?= $colorEstado[$e['estado']] ?>">
                        <?= ucfirst($e['estado']) ?>
                    </span>
                </td>
                <td class="tabular">
                    <?php if (!$e['fecha_vence']): ?>
                        <span class="text-slate-500">—</span>
                    <?php elseif ($vence < 0): ?>
                        <span class="text-red-400 font-medium"><?= formatDate($e['fecha_vence']) ?></span>
                    <?php elseif ($vence <= 7): ?>
                        <span class="text-amber-400 font-medium"><?= formatDate($e['fecha_vence']) ?></span>
                    <?php else: ?>
                        <span class="text-slate-400"><?= formatDate($e['fecha_vence']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-right tabular text-slate-300"><?= formatMoney($e['mensualidad']) ?></td>
                <td class="text-center tabular text-slate-400"><?= $e['n_suc'] ?>/<?= $e['max_sucursales'] ?></td>
                <td class="text-center tabular text-slate-400"><?= $e['n_usr'] ?>/<?= $e['max_usuarios'] ?></td>
                <td class="text-center tabular text-slate-400"><?= $e['n_ord'] ?></td>
                <td class="text-right whitespace-nowrap">
                    <?php if ($e['n_suc'] > 0): ?>
                    <a href="entrar.php?id=<?= $e['id'] ?>" title="Entrar a esta empresa"
                       class="inline-flex w-7 h-7 items-center justify-center rounded-md text-slate-400 hover:text-white hover:bg-white/10">
                        <i class="fas fa-right-to-bracket text-[12px]"></i>
                    </a>
                    <?php endif; ?>
                    <a href="ver.php?id=<?= $e['id'] ?>" title="Administrar"
                       class="inline-flex w-7 h-7 items-center justify-center rounded-md text-slate-400 hover:text-white hover:bg-white/10">
                        <i class="fas fa-sliders text-[12px]"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
