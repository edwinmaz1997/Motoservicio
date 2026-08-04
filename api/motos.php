<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$clienteId = (int)($_GET['cliente_id'] ?? 0);
if (!$clienteId) { echo '[]'; exit; }

$db   = getDB();
$stmt = $db->prepare("SELECT id, marca_texto, modelo, placa, vin FROM motocicletas WHERE cliente_id = ? AND activa = 1 ORDER BY marca_texto, modelo");
$stmt->execute([$clienteId]);
echo json_encode($stmt->fetchAll());
