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

$db     = getDB();
$suc    = $user['sucursal_id'];
$estado = $_GET['estado'] ?? '';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

$where  = "WHERE d.sucursal_id = ?";
$params = [$suc];
if ($estado) { $where .= " AND d.estado = ?"; $params[] = $estado; }
if ($q) {
    $like = "%$q%";
    $where .= " AND (d.uuid LIKE ? OR d.nombre_receptor LIKE ? OR d.nit_receptor LIKE ? OR d.numero LIKE ?)";
    $params = array_merge($params, [$like,$like,$like,$like]);
}

$total = $db->prepare("SELECT COUNT(*) FROM fel_documentos d $where");
$total->execute($params);
$totalRows  = $total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $db->prepare("SELECT d.*, v.numero_venta, o.numero_orden
                      FROM fel_documentos d
                      LEFT JOIN ventas  v ON v.id = d.venta_id
                      LEFT JOIN ordenes o ON o.id = d.orden_id
                      $where ORDER BY d.id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$docs = $stmt->fetchAll();

$pageTitle = 'Documentos FEL';
require_once __DIR__ . '/../../includes/header.php';

$cls = ['certificado'=>'bg-green-100 text-green-700','pendiente'=>'bg-yellow-100 text-yellow-700',
        'rechazado'=>'bg-red-100 text-red-700','anulado'=>'bg-gray-100 text-gray-600'];
?>

<div class="flex items-center gap-3 mb-5">
    <a href="index.php" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex-shrink-0"><i class="fas fa-arrow-left text-sm"></i></a>
    <h1 class="text-[22px] lg:text-[25px] font-semibold text-slate-900 tracking-tight">Documentos FEL</h1>
</div>

<form method="GET" class="mb-4 flex gap-2 flex-wrap">
    <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="Buscar por autorización, receptor o NIT..."
           class="flex-1 min-w-48 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <select name="estado" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Todos los estados</option>
        <?php foreach (['certificado'=>'Certificado','pendiente'=>'Pendiente','rechazado'=>'Rechazado','anulado'=>'Anulado'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $estado===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
    </select>
    <button class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-search"></i></button>
    <?php if ($q || $estado): ?><a href="documentos.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
</form>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-2 text-left">Tipo</th>
                    <th class="px-4 py-2 text-left">Origen</th>
                    <th class="px-4 py-2 text-left">Receptor</th>
                    <th class="px-4 py-2 text-left">NIT</th>
                    <th class="px-4 py-2 text-right">Total</th>
                    <th class="px-4 py-2 text-left">Autorización</th>
                    <th class="px-4 py-2 text-left">Estado</th>
                    <th class="px-4 py-2 text-left">Fecha</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($docs)): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Sin documentos</td></tr>
                <?php endif; ?>
                <?php foreach ($docs as $d): ?>
                <tr class="hover:bg-gray-50 <?= $d['estado']==='anulado'?'opacity-50':'' ?>">
                    <td class="px-4 py-2 font-mono text-xs"><?= sanitize($d['tipo_dte']) ?></td>
                    <td class="px-4 py-2 text-xs">
                        <?php if ($d['numero_venta']): ?>
                            <a href="../ventas/view.php?id=<?= $d['venta_id'] ?>" class="text-blue-600 hover:underline font-mono"><?= sanitize($d['numero_venta']) ?></a>
                        <?php elseif ($d['numero_orden']): ?>
                            <a href="../ordenes/view.php?id=<?= $d['orden_id'] ?>" class="text-blue-600 hover:underline font-mono"><?= sanitize($d['numero_orden']) ?></a>
                        <?php else: ?>
                            <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2"><?= sanitize($d['nombre_receptor']) ?></td>
                    <td class="px-4 py-2 font-mono text-xs"><?= sanitize($d['nit_receptor']) ?></td>
                    <td class="px-4 py-2 text-right font-semibold"><?= formatMoney($d['gran_total']) ?></td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        <?= $d['uuid'] ? sanitize(substr($d['uuid'],0,18)).'…' : '<span class="text-gray-400">—</span>' ?>
                    </td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 rounded-full text-xs <?= $cls[$d['estado']] ?? '' ?>"><?= ucfirst($d['estado']) ?></span>
                    </td>
                    <td class="px-4 py-2 text-gray-500 text-xs"><?= formatDate($d['created_at']) ?></td>
                    <td class="px-4 py-2">
                        <a href="ver.php?id=<?= $d['id'] ?>" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-500">
        <span><?= $totalRows ?> documentos</span>
        <div class="flex gap-1">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&estado=<?= urlencode($estado) ?>"
               class="px-3 py-1 rounded <?= $i===$page?'bg-blue-700 text-white':'bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
