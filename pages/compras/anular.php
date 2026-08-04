<?php
require_once __DIR__ . '/../../includes/header.php';
$db=getDB();$id=(int)($_GET['id']??0);
$stmt=$db->prepare("SELECT * FROM compras WHERE id=? AND sucursal_id=? AND estado!='anulada'");
$stmt->execute([$id,$user['sucursal_id']]);$c=$stmt->fetch();
if(!$c){flashMessage('error','No se puede anular.');header('Location: index.php');exit;}
// Revertir stock solo si estaba recibida
if($c['estado']==='recibida'){
    $det=$db->prepare("SELECT * FROM compra_detalle WHERE compra_id=?");$det->execute([$id]);
    foreach($det->fetchAll() as $d) $db->prepare("UPDATE productos SET stock=stock-? WHERE id=? AND stock>=?")->execute([$d['cantidad'],$d['producto_id'],$d['cantidad']]);
}
$db->prepare("UPDATE compras SET estado='anulada' WHERE id=?")->execute([$id]);
flashMessage('success','Compra anulada.');header('Location: view.php?id='.$id);exit;
