<?php
require_once __DIR__ . '/../../includes/header.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$db       = getDB();
$ordenId  = (int)($_POST['orden_id'] ?? 0);
$monto    = (float)($_POST['monto'] ?? 0);
$metodo   = $_POST['metodo_pago'] ?? 'efectivo';
$notas    = trim($_POST['notas'] ?? '');

$stmt = $db->prepare("SELECT * FROM ordenes WHERE id=? AND sucursal_id=?");
$stmt->execute([$ordenId, $user['sucursal_id']]); $o = $stmt->fetch();
if (!$o || $monto <= 0) { flashMessage('error','Datos inválidos.'); header('Location: index.php'); exit; }

$db->prepare("INSERT INTO anticipos (orden_id, cajero_id, monto, metodo_pago, notas) VALUES (?,?,?,?,?)")
   ->execute([$ordenId, $user['id'], $monto, $metodo, $notas]);

// Actualizar anticipo y saldo en la orden
$nuevoAnticipo = $o['anticipo'] + $monto;
$nuevoSaldo    = max(0, $o['total'] - $nuevoAnticipo);
$db->prepare("UPDATE ordenes SET anticipo=?, saldo=? WHERE id=?")->execute([$nuevoAnticipo, $nuevoSaldo, $ordenId]);

flashMessage('success', 'Pago de '.formatMoney($monto).' registrado.');
header('Location: view.php?id='.$ordenId); exit;
