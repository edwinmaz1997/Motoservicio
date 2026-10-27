<?php
$pageTitle = 'Bitácora — Panel maestro';
require_once __DIR__ . '/includes/layout.php';

$db = getDB();
$empresaF = (int)($_GET['empresa'] ?? 0);
$pagina   = max(1, (int)($_GET['page'] ?? 1));
$porPag   = 60;

$where = "WHERE 1=1"; $params = [];
if ($empresaF) { $where .= " AND l.empresa_id = ?"; $params[] = $empresaF; }

$cnt = $db->prepare("SELECT COUNT(*) FROM master_log l $where");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$paginas = max(1, (int)ceil($total / $porPag));
$pagina  = min($pagina, $paginas);
$offset  = ($pagina - 1) * $porPag;

$stmt = $db->prepare("
    SELECT l.*, e.nombre AS empresa, u.nombre AS usuario, u.username
    FROM master_log l
    LEFT JOIN empresas e ON e.id = l.empresa_id
    LEFT JOIN usuarios u ON u.id = l.usuario_id
    $where
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT $porPag OFFSET $offset");
$stmt->execute($params);
$filas = $stmt->fetchAll();

$empresas = $db->query("SELECT id,nombre FROM empresas ORDER BY nombre")->fetchAll();

$etiqueta = [
    'crear_empresa'    => ['Empresa creada',       'fa-plus',               'text-emerald-400'],
    'editar_datos'     => ['Datos editados',       'fa-pen',                'text-slate-400'],
    'editar_contrato'  => ['Contrato editado',     'fa-file-signature',     'text-blue-400'],
    'renovar'          => ['Renovación',           'fa-rotate',             'text-emerald-400'],
    'cambiar_estado'   => ['Cambio de estado',     'fa-toggle-on',          'text-amber-400'],
    'reset_pass'       => ['Contraseña cambiada',  'fa-key',                'text-amber-400'],
    'entrar_empresa'   => ['Ingreso a empresa',    'fa-right-to-bracket',   'text-blue-400'],
];
?>

<div class="flex items-end justify-between gap-4 flex-wrap mb-6">
    <div>
        <h1 class="text-[24px] font-semibold tracking-tight">Bitácora</h1>
        <p class="text-slate-400 text-[13.5px] mt-0.5"><?= number_format($total) ?> movimiento(s)</p>
    </div>
    <form method="GET">
        <select name="empresa" onchange="this.form.submit()" class="w-auto">
            <option value="0">Todas las empresas</option>
            <?php foreach ($empresas as $em): ?>
            <option value="<?= $em['id'] ?>" <?= $empresaF===(int)$em['id']?'selected':'' ?>><?= sanitize($em['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="panel rounded-xl overflow-hidden">
    <table>
        <thead><tr>
            <th>Fecha</th><th>Acción</th><th>Empresa</th><th>Detalle</th><th>Quién</th><th>IP</th>
        </tr></thead>
        <tbody>
            <?php if (!$filas): ?>
            <tr><td colspan="6" class="text-center text-slate-500 py-10">Sin movimientos registrados</td></tr>
            <?php endif; ?>
            <?php foreach ($filas as $f):
                [$txt,$ic,$col] = $etiqueta[$f['accion']] ?? [$f['accion'], 'fa-circle', 'text-slate-400']; ?>
            <tr>
                <td class="tabular text-slate-400 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($f['created_at'])) ?></td>
                <td class="whitespace-nowrap">
                    <i class="fas <?= $ic ?> <?= $col ?> text-[11px] mr-2"></i><?= sanitize($txt) ?>
                </td>
                <td class="text-slate-300">
                    <?php if ($f['empresa_id'] && $f['empresa']): ?>
                    <a href="ver.php?id=<?= $f['empresa_id'] ?>" class="hover:text-blue-300"><?= sanitize($f['empresa']) ?></a>
                    <?php else: ?><span class="text-slate-600">—</span><?php endif; ?>
                </td>
                <td class="text-slate-400"><?= sanitize($f['detalle'] ?: '—') ?></td>
                <td class="text-slate-400"><?= sanitize($f['username'] ?: '—') ?></td>
                <td class="tabular text-slate-600 text-[12.5px]"><?= sanitize($f['ip'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($paginas > 1): ?>
<div class="flex items-center justify-center gap-1 mt-5">
    <?php for ($p = 1; $p <= $paginas; $p++): ?>
    <a href="?empresa=<?= $empresaF ?>&page=<?= $p ?>"
       class="w-9 h-9 flex items-center justify-center rounded-lg text-[13px] tabular
              <?= $p === $pagina ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10' ?>">
        <?= $p ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
