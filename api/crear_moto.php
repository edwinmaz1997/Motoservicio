<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$clienteId  = (int)($_POST['cliente_id']  ?? 0);
$marcaTexto = trim($_POST['marca_texto']  ?? '');
$marcaId    = (int)($_POST['marca_id']    ?? 0) ?: null;
$modelo     = trim($_POST['modelo']       ?? '');
$anio       = trim($_POST['anio']         ?? '') ?: null;
$color      = trim($_POST['color']        ?? '');
$km         = (int)($_POST['kilometraje'] ?? 0);
$placa      = trim($_POST['placa']        ?? '');
$vin        = trim($_POST['vin']          ?? '');

if (!$clienteId || !$marcaTexto) {
    jsonResponse(['error' => 'Cliente y marca son requeridos'], 422);
}

// Verificar que el cliente pertenece a la sucursal
$db = getDB();
$check = $db->prepare("SELECT id FROM clientes WHERE id=? AND sucursal_id=?");
$check->execute([$clienteId, $user['sucursal_id']]);
if (!$check->fetch()) jsonResponse(['error' => 'Cliente no válido'], 403);

$db->prepare("INSERT INTO motocicletas (cliente_id,marca_id,marca_texto,modelo,anio,color,kilometraje,placa,vin) VALUES (?,?,?,?,?,?,?,?,?)")
   ->execute([$clienteId,$marcaId,$marcaTexto,$modelo,$anio,$color,$km,$placa,$vin]);

$id = $db->lastInsertId();
$label = trim("$marcaTexto $modelo") . ($placa ? " ($placa)" : '');
jsonResponse(['id' => $id, 'label' => $label]);
