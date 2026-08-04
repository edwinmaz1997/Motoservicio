<?php
$pageTitle = 'Nueva Motocicleta';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
refreshSession();
$user = currentUser();
$db = getDB();
$marcas   = $db->query("SELECT * FROM marcas ORDER BY nombre")->fetchAll();
$clientes = $db->prepare("SELECT * FROM clientes WHERE sucursal_id=? AND activo=1 ORDER BY nombre");
$clientes->execute([$user['sucursal_id']]); $clientes = $clientes->fetchAll();
$preClienteId = (int)($_GET['cliente_id'] ?? 0);
$errors = [];
$data = ['cliente_id'=>$preClienteId,'marca_id'=>'','marca_texto'=>'','modelo'=>'','anio'=>'','color'=>'','kilometraje'=>0,'vin'=>'','placa'=>'','notas'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k=>$_) $data[$k] = trim($_POST[$k] ?? '');
    if (!$data['cliente_id'])  $errors[] = 'Selecciona un cliente.';
    if (!$data['marca_texto']) $errors[] = 'La marca es requerida.';

    if (empty($errors)) {
        $db->prepare("INSERT INTO motocicletas (cliente_id,marca_id,marca_texto,modelo,anio,color,kilometraje,vin,placa,notas) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$data['cliente_id'],$data['marca_id']?:null,$data['marca_texto'],$data['modelo'],$data['anio']?:null,$data['color'],(int)$data['kilometraje'],$data['vin'],$data['placa'],$data['notas']]);
        flashMessage('success','Motocicleta registrada.');
        $red = $preClienteId ? '../clientes/view.php?id='.$preClienteId : 'index.php';
        header('Location: '.$red); exit;
    }
}
$pageTitle = 'Nueva Motocicleta';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="flex items-center gap-3 mb-6">
    <a href="<?= $preClienteId ? '../clientes/view.php?id='.$preClienteId : 'index.php' ?>" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Nueva Motocicleta</h1>
</div>
<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                <select name="cliente_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Seleccionar cliente —</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $data['cliente_id']==$c['id']?'selected':'' ?>><?= sanitize($c['nombre'].' '.$c['apellido']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Marca *</label>
                <select name="marca_id" onchange="this.form.marca_texto.value=this.options[this.selectedIndex].text==='Otro'?'':this.options[this.selectedIndex].text"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($marcas as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $data['marca_id']==$m['id']?'selected':'' ?>><?= sanitize($m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Marca (texto) *</label>
                <input type="text" name="marca_texto" value="<?= sanitize($data['marca_texto']) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Modelo</label>
                <input type="text" name="modelo" value="<?= sanitize($data['modelo']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                <input type="number" name="anio" value="<?= sanitize($data['anio']) ?>" min="1980" max="<?= date('Y')+1 ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                <input type="text" name="color" value="<?= sanitize($data['color']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kilometraje</label>
                <input type="number" name="kilometraje" value="<?= (int)$data['kilometraje'] ?>" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Placa</label>
                <input type="text" name="placa" value="<?= sanitize($data['placa']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">VIN / Chasis</label>
                <input type="text" name="vin" value="<?= sanitize($data['vin']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                <textarea name="notas" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['notas']) ?></textarea>
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 text-sm font-medium"><i class="fas fa-save mr-1"></i> Guardar</button>
            <a href="index.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
