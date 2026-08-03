<?php
$pageTitle = 'Editar Motocicleta';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT m.*, c.sucursal_id FROM motocicletas m JOIN clientes c ON c.id=m.cliente_id WHERE m.id=?");
$stmt->execute([$id]); $moto = $stmt->fetch();
if (!$moto || $moto['sucursal_id'] != $user['sucursal_id']) { flashMessage('error','No encontrado.'); header('Location: index.php'); exit; }
$marcas   = $db->query("SELECT * FROM marcas ORDER BY nombre")->fetchAll();
$clientes = $db->prepare("SELECT * FROM clientes WHERE sucursal_id=? AND activo=1 ORDER BY nombre");
$clientes->execute([$user['sucursal_id']]); $clientes = $clientes->fetchAll();
$errors = []; $data = $moto;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['cliente_id','marca_id','marca_texto','modelo','anio','color','kilometraje','vin','placa','notas'] as $k) $data[$k] = trim($_POST[$k] ?? '');
    if (!$data['marca_texto']) $errors[] = 'La marca es requerida.';
    if (empty($errors)) {
        $db->prepare("UPDATE motocicletas SET cliente_id=?,marca_id=?,marca_texto=?,modelo=?,anio=?,color=?,kilometraje=?,vin=?,placa=?,notas=? WHERE id=?")
           ->execute([$data['cliente_id'],$data['marca_id']?:null,$data['marca_texto'],$data['modelo'],$data['anio']?:null,$data['color'],(int)$data['kilometraje'],$data['vin'],$data['placa'],$data['notas'],$id]);
        flashMessage('success','Motocicleta actualizada.');
        header('Location: index.php'); exit;
    }
}
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Editar Motocicleta</h1>
</div>
<?php if ($errors): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <select name="cliente_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>" <?= $data['cliente_id']==$c['id']?'selected':'' ?>><?= sanitize($c['nombre'].' '.$c['apellido']) ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Marca (lista)</label>
                <select name="marca_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($marcas as $m): ?><option value="<?= $m['id'] ?>" <?= $data['marca_id']==$m['id']?'selected':'' ?>><?= sanitize($m['nombre']) ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Marca *</label><input type="text" name="marca_texto" value="<?= sanitize($data['marca_texto']??'') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Modelo</label><input type="text" name="modelo" value="<?= sanitize($data['modelo']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Año</label><input type="number" name="anio" value="<?= sanitize($data['anio']??'') ?>" min="1980" max="<?= date('Y')+1 ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Color</label><input type="text" name="color" value="<?= sanitize($data['color']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Kilometraje</label><input type="number" name="kilometraje" value="<?= (int)($data['kilometraje']??0) ?>" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Placa</label><input type="text" name="placa" value="<?= sanitize($data['placa']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">VIN / Chasis</label><input type="text" name="vin" value="<?= sanitize($data['vin']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Notas</label><textarea name="notas" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['notas']??'') ?></textarea></div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 text-sm font-medium"><i class="fas fa-save mr-1"></i> Actualizar</button>
            <a href="index.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
