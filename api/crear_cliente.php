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

$nombre   = trim($_POST['nombre']   ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo   = trim($_POST['correo']   ?? '');
$dpi      = trim($_POST['dpi']      ?? '');
$nit      = trim($_POST['nit']      ?? '');
$direccion= trim($_POST['direccion']?? '');

if (!$nombre || !$apellido) {
    jsonResponse(['error' => 'Nombre y apellido son requeridos'], 422);
}

$db = getDB();
$db->prepare("INSERT INTO clientes (sucursal_id,nombre,apellido,telefono,correo,dpi,nit,direccion) VALUES (?,?,?,?,?,?,?,?)")
   ->execute([$user['sucursal_id'],$nombre,$apellido,$telefono,$correo,$dpi,$nit,$direccion]);

$id = $db->lastInsertId();
jsonResponse(['id' => $id, 'nombre' => $nombre, 'apellido' => $apellido, 'label' => "$nombre $apellido"]);
