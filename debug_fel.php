<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "PHP: " . PHP_VERSION . "\n\n";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
refreshSession();
$user = currentUser();
echo "Usuario OK. sucursal_id = " . var_export($user['sucursal_id'], true) . "\n\n";

$db = getDB();

// Tablas FEL
foreach (['fel_config','fel_documentos','fel_log'] as $t) {
    try {
        $n = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "tabla $t: OK ($n filas)\n";
    } catch (Exception $e) {
        echo "tabla $t: ERROR -> " . $e->getMessage() . "\n";
    }
}

// Columnas nuevas
$cols = [
    'ventas'    => ['emitir_fel'],
    'clientes'  => ['codigo_postal','municipio','departamento'],
    'productos' => ['unidad_sat'],
];
echo "\n";
foreach ($cols as $tabla => $lista) {
    foreach ($lista as $c) {
        try {
            $db->query("SELECT $c FROM $tabla LIMIT 1");
            echo "columna $tabla.$c: OK\n";
        } catch (Exception $e) {
            echo "columna $tabla.$c: FALTA -> " . $e->getMessage() . "\n";
        }
    }
}

// Carga de los archivos FEL
echo "\n";
try {
    require_once __DIR__ . '/includes/fel/FelClient.php';
    echo "FelClient.php: cargado\n";
} catch (Throwable $e) {
    echo "FelClient.php: ERROR -> " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
    echo "</pre>"; exit;
}

try {
    $cfg = felConfig($user['sucursal_id']);
    echo "felConfig(): " . ($cfg ? "OK (id {$cfg['id']})" : "devolvió null") . "\n";
    echo "felHabilitado(): " . var_export(felHabilitado($user['sucursal_id']), true) . "\n";
} catch (Throwable $e) {
    echo "felConfig/felHabilitado: ERROR -> " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
}

// curl disponible
echo "\ncurl: " . (function_exists('curl_init') ? "disponible" : "NO DISPONIBLE — el módulo FEL lo necesita") . "\n";

echo "</pre>";
