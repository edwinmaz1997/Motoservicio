<?php
$pageTitle = 'Editar Orden';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM ordenes WHERE id=? AND sucursal_id=?");
$stmt->execute([$id, $user['sucursal_id']]); $o = $stmt->fetch();
if (!$o || in_array($o['estado'],['entregada','cancelada'])) { flashMessage('error','No se puede editar.'); header('Location: index.php'); exit; }

$tecnicos = $db->prepare("SELECT * FROM usuarios WHERE sucursal_id=? AND rol_id=? AND activo=1 ORDER BY nombre");
$tecnicos->execute([$user['sucursal_id'], ROL_TECNICO]); $tecnicos=$tecnicos->fetchAll();
$asesores = $db->prepare("SELECT * FROM usuarios WHERE sucursal_id=? AND rol_id IN (?,?) AND activo=1 ORDER BY nombre");
$asesores->execute([$user['sucursal_id'],ROL_ASESOR_VENTAS,ROL_VENDEDOR]); $asesores=$asesores->fetchAll();
$errors=[]; $data=$o;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['tecnico_id','asesor_id','fecha_salida_esperada','diagnostico','observaciones','estado'] as $k) $data[$k]=trim($_POST[$k]??'');
    $db->prepare("UPDATE ordenes SET tecnico_id=?,asesor_id=?,fecha_salida_esperada=?,diagnostico=?,observaciones=?,estado=? WHERE id=?")
       ->execute([$data['tecnico_id']?:null,$data['asesor_id']?:null,$data['fecha_salida_esperada']?:null,$data['diagnostico'],$data['observaciones'],$data['estado'],$id]);
    flashMessage('success','Orden actualizada.');
    header('Location: view.php?id='.$id); exit;
}
$estadoOpts=['abierta'=>'Abierta','en_proceso'=>'En proceso','lista'=>'Lista'];
?>
<div class="flex items-center gap-3 mb-6">
    <a href="view.php?id=<?= $id ?>" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Editar Orden <?= sanitize($o['numero_orden']) ?></h1>
</div>
<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Técnico</label>
                <select name="tecnico_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Sin asignar —</option>
                    <?php foreach ($tecnicos as $t): ?><option value="<?= $t['id'] ?>" <?= $data['tecnico_id']==$t['id']?'selected':'' ?>><?= sanitize($t['nombre'].' '.$t['apellido']) ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Asesor</label>
                <select name="asesor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Sin asignar —</option>
                    <?php foreach ($asesores as $a): ?><option value="<?= $a['id'] ?>" <?= $data['asesor_id']==$a['id']?'selected':'' ?>><?= sanitize($a['nombre'].' '.$a['apellido']) ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Fecha salida esperada</label>
                <input type="date" name="fecha_salida_esperada" value="<?= $data['fecha_salida_esperada'] ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="estado" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($estadoOpts as $v=>$l): ?><option value="<?= $v ?>" <?= $data['estado']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
                </select></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico</label>
                <textarea name="diagnostico" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['diagnostico']??'') ?></textarea></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                <textarea name="observaciones" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= sanitize($data['observaciones']??'') ?></textarea></div>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 text-sm font-medium"><i class="fas fa-save mr-1"></i> Actualizar</button>
            <a href="view.php?id=<?= $id ?>" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg text-sm">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
