<?php
$pageTitle = 'Nuevo Producto';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
$errors = [];
$data = ['nombre'=>'','tipo'=>'estandar','descripcion'=>'','precio'=>'','unidad'=>'UND','medida'=>'','stock'=>0,'stock_minimo'=>0,'categoria_id'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k=>$_) $data[$k] = trim($_POST[$k] ?? '');
    if (!$data['nombre']) $errors[] = 'El nombre es requerido.';
    if ($data['precio'] === '') $errors[] = 'El precio es requerido.';
    if (empty($errors)) {
        $db->prepare("INSERT INTO productos (categoria_id,tipo,nombre,descripcion,precio,unidad,medida,stock,stock_minimo) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$data['categoria_id']?:null,$data['tipo'],$data['nombre'],$data['descripcion'],$data['precio'],$data['unidad'],$data['medida'],
                      $data['tipo']==='servicio' ? 0 : (int)$data['stock'],
                      $data['tipo']==='servicio' ? 0 : (int)$data['stock_minimo']]);
        flashMessage('success','Producto creado.');
        header('Location: index.php'); exit;
    }
}
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Nuevo Producto</h1>
</div>
<?php if ($errors): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl" x-data="{ tipo: '<?= $data['tipo'] ?>' }">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="nombre" value="<?= sanitize($data['nombre']) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                <select name="tipo" x-model="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="estandar">Estándar (con inventario)</option>
                    <option value="servicio">Servicio (sin inventario)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                <select name="categoria_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Sin categoría —</option>
                    <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= $data['categoria_id']==$c['id']?'selected':'' ?>><?= sanitize($c['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio *</label>
                <input type="number" name="precio" value="<?= sanitize($data['precio']) ?>" step="0.01" min="0" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidad</label>
                <input type="text" name="unidad" value="<?= sanitize($data['unidad']) ?>" placeholder="UND, LT, KG..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Medida</label>
                <input type="text" name="medida" value="<?= sanitize($data['medida']) ?>" placeholder="1L, 500ml..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <!-- Stock: solo para estándar -->
            <div x-show="tipo === 'estandar'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock actual</label>
                <input type="number" name="stock" value="<?= (int)$data['stock'] ?>" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div x-show="tipo === 'estandar'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock mínimo (alerta)</label>
                <input type="number" name="stock_minimo" value="<?= (int)$data['stock_minimo'] ?>" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="descripcion" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['descripcion']) ?></textarea>
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 text-sm font-medium"><i class="fas fa-save mr-1"></i> Guardar</button>
            <a href="index.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
