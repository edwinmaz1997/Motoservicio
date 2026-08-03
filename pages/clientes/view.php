<?php
$pageTitle = 'Detalle Cliente';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ? AND sucursal_id = ?");
$stmt->execute([$id, $user['sucursal_id']]);
$c = $stmt->fetch();
if (!$c) { flashMessage('error','Cliente no encontrado.'); header('Location: index.php'); exit; }

$motos = $db->prepare("SELECT * FROM motocicletas WHERE cliente_id = ? AND activa = 1");
$motos->execute([$id]);
$motos = $motos->fetchAll();

$ordenes = $db->prepare("SELECT o.*, CONCAT(u.nombre,' ',u.apellido) as tecnico FROM ordenes o LEFT JOIN usuarios u ON u.id = o.tecnico_id WHERE o.cliente_id = ? ORDER BY o.created_at DESC LIMIT 10");
$ordenes->execute([$id]);
$ordenes = $ordenes->fetchAll();
?>

<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($c['nombre'] . ' ' . $c['apellido']) ?></h1>
    <a href="edit.php?id=<?= $c['id'] ?>" class="ml-auto bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-600">
        <i class="fas fa-edit mr-1"></i> Editar
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Info -->
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold text-gray-700 mb-4">Información</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Teléfono</dt><dd><?= sanitize($c['telefono'] ?? '—') ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Correo</dt><dd><?= sanitize($c['correo'] ?? '—') ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">DPI</dt><dd><?= sanitize($c['dpi'] ?? '—') ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">NIT</dt><dd><?= sanitize($c['nit'] ?? '—') ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Dirección</dt><dd><?= sanitize($c['direccion'] ?? '—') ?></dd></div>
        </dl>
    </div>

    <!-- Motos -->
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-700">Motocicletas</h2>
            <a href="../motos/create.php?cliente_id=<?= $c['id'] ?>" class="text-blue-600 text-xs hover:underline">+ Agregar</a>
        </div>
        <?php if (empty($motos)): ?>
            <p class="text-gray-400 text-sm">Sin motocicletas registradas.</p>
        <?php endif; ?>
        <?php foreach ($motos as $m): ?>
        <div class="flex items-center gap-3 py-2 border-b last:border-0">
            <div class="text-2xl">🏍️</div>
            <div>
                <div class="font-medium text-sm"><?= sanitize(($m['marca_texto'] ?? '') . ' ' . ($m['modelo'] ?? '')) ?></div>
                <div class="text-xs text-gray-400"><?= sanitize($m['placa'] ?? $m['vin'] ?? '—') ?> · <?= sanitize($m['color'] ?? '') ?></div>
            </div>
            <a href="../motos/edit.php?id=<?= $m['id'] ?>" class="ml-auto text-gray-400 hover:text-gray-600"><i class="fas fa-edit text-xs"></i></a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Órdenes recientes -->
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-700">Últimas órdenes</h2>
            <a href="../ordenes/create.php?cliente_id=<?= $c['id'] ?>" class="text-blue-600 text-xs hover:underline">+ Nueva</a>
        </div>
        <?php if (empty($ordenes)): ?>
            <p class="text-gray-400 text-sm">Sin órdenes.</p>
        <?php endif; ?>
        <?php foreach ($ordenes as $o): ?>
        <div class="py-2 border-b last:border-0 text-sm">
            <div class="flex justify-between">
                <a href="../ordenes/view.php?id=<?= $o['id'] ?>" class="font-mono text-blue-600 hover:underline"><?= sanitize($o['numero_orden']) ?></a>
                <span class="<?= $o['estado'] === 'entregada' ? 'text-green-600' : 'text-yellow-600' ?> text-xs font-medium">
                    <?= ucfirst($o['estado']) ?>
                </span>
            </div>
            <div class="text-gray-400 text-xs"><?= formatDate($o['fecha_ingreso']) ?> · <?= formatMoney($o['total']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
