<?php
/**
 * Acceso a la configuración FEL de una sucursal.
 */

function felConfig($sucursalId) {
    static $cache = [];
    if (isset($cache[$sucursalId])) return $cache[$sucursalId];

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM fel_config WHERE sucursal_id = ? LIMIT 1");
    $stmt->execute([$sucursalId]);
    $cfg  = $stmt->fetch();

    if (!$cfg) {
        $db->prepare("INSERT INTO fel_config (sucursal_id) VALUES (?)")->execute([$sucursalId]);
        $stmt->execute([$sucursalId]);
        $cfg = $stmt->fetch();
    }

    $cache[$sucursalId] = $cfg;
    return $cfg;
}

/**
 * ¿La sucursal tiene FEL habilitado y completamente configurado?
 */
function felHabilitado($sucursalId) {
    $cfg = felConfig($sucursalId);
    if (!$cfg || !$cfg['activo']) return false;
    foreach (['usuario_firma','llave_firma','usuario_api','llave_api','nit_emisor','nombre_emisor'] as $campo) {
        if (empty($cfg[$campo])) return false;
    }
    return true;
}

/**
 * Lista de campos que faltan por configurar (para mostrar al usuario).
 */
function felCamposFaltantes($sucursalId) {
    $cfg = felConfig($sucursalId);
    $req = [
        'usuario_firma'    => 'Usuario de firma',
        'llave_firma'      => 'Llave de firma (Token Signer)',
        'usuario_api'      => 'Usuario API',
        'llave_api'        => 'Llave API',
        'nit_emisor'       => 'NIT del emisor',
        'nombre_emisor'    => 'Nombre / razón social del emisor',
        'nombre_comercial' => 'Nombre comercial',
        'correo_emisor'    => 'Correo del emisor',
        'direccion'        => 'Dirección fiscal',
        'municipio'        => 'Municipio',
        'departamento'     => 'Departamento',
    ];
    $faltan = [];
    foreach ($req as $campo => $label) {
        if (empty($cfg[$campo])) $faltan[] = $label;
    }
    return $faltan;
}

/**
 * Catálogo de unidades de medida aceptadas por SAT.
 */
function felUnidadesMedida() {
    return [
        'UND' => 'Unidad',
        'LB'  => 'Libra',
        'KG'  => 'Kilogramo',
        'LT'  => 'Litro',
        'GAL' => 'Galón',
        'MT'  => 'Metro',
        'CJ'  => 'Caja',
        'PAR' => 'Par',
        'SRV' => 'Servicio',
    ];
}

/**
 * Catálogo de afiliaciones IVA.
 */
function felAfiliacionesIVA() {
    return [
        'GEN' => 'General (contribuyente normal)',
        'PEQ' => 'Pequeño contribuyente',
        'PES' => 'Pequeño contribuyente — electrónico especial',
        'AGR' => 'Contribuyente agropecuario',
        'EXE' => 'Exento',
    ];
}
