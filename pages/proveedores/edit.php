<?php
$pageTitle = 'Editar Proveedor';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM proveedores WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { flashMessage('error','Proveedor no encontrado.'); header('Location: index.php'); exit; }

$errors = [];
$data = $p;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nombre','apellido','correo','nit','dpi','direccion','telefono'] as $k) $data[$k] = trim($_POST[$k] ?? '');
    if (!$data['nombre']) $errors[] = 'El nombre es requerido.';
    if (empty($errors)) {
        $db->prepare("UPDATE proveedores SET nombre=?,apellido=?,correo=?,nit=?,dpi=?,direccion=?,telefono=? WHERE id=?")
           ->execute([$data['nombre'],$data['apellido'],$data['correo'],$data['nit'],$data['dpi'],$data['direccion'],$data['telefono'],$id]);
        flashMessage('success','Proveedor actualizado.');
        header('Location: index.php'); exit;
    }
}
?>
<div class="flex items-center gap-3 mb-6">
    <a href="index.php" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Editar Proveedor</h1>
</div>
<?php if ($errors): ?>
<div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm mb-5">
    <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php
            $fields = ['nombre'=>['Nombre *','text',true],'apellido'=>['Apellido','text',false],'telefono'=>['Teléfono','text',false],'correo'=>['Correo','email',false],'nit'=>['NIT','text',false],'dpi'=>['DPI','text',false]];
            foreach ($fields as $name => [$label,$type,$req]):
            ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= $label ?></label>
                <input type="<?= $type ?>" name="<?= $name ?>" value="<?= sanitize($data[$name] ?? '') ?>" <?= $req?'required':'' ?>
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <?php endforeach; ?>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                <input type="text" name="direccion" value="<?= sanitize($data['direccion'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 text-sm font-medium"><i class="fas fa-save mr-1"></i> Actualizar</button>
            <a href="index.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
