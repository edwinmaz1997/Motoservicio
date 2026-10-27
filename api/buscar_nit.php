<?php
/**
 * Busca un cliente por NIT dentro de la sucursal del usuario.
 * Sirve para autocompletar el nombre del receptor al facturar.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();
refreshSession();
$user = currentUser();

header('Content-Type: application/json; charset=utf-8');

$nit = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $_GET['nit'] ?? ''));

if ($nit === '' || $nit === 'CF') {
    echo json_encode([
        'encontrado' => true,
        'nombre'     => 'CONSUMIDOR FINAL',
        'correo'     => '',
        'generico'   => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDB();
// Comparar ignorando guiones y espacios guardados en la ficha
$stmt = $db->prepare("
    SELECT nombre, apellido, correo, direccion
    FROM clientes
    WHERE sucursal_id = ?
      AND UPPER(REPLACE(REPLACE(COALESCE(nit,''),'-',''),' ','')) = ?
    LIMIT 1
");
$stmt->execute([$user['sucursal_id'], $nit]);
$c = $stmt->fetch();

if ($c) {
    echo json_encode([
        'encontrado' => true,
        'nombre'     => trim($c['nombre'] . ' ' . $c['apellido']),
        'correo'     => $c['correo'] ?: '',
        'direccion'  => $c['direccion'] ?: '',
        'generico'   => false,
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'encontrado' => false,
        'nombre'     => '',
        'correo'     => '',
        'generico'   => false,
    ], JSON_UNESCAPED_UNICODE);
}
