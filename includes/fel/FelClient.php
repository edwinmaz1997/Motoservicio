<?php
/**
 * Cliente del Web Service Unificado de INFILE (firma + certificación).
 */

require_once __DIR__ . '/FelConfig.php';
require_once __DIR__ . '/FelXml.php';

/**
 * Ejecuta una petición al certificador.
 *
 * @return array ['ok'=>bool, 'http_code'=>int, 'data'=>array|null, 'raw'=>string, 'error'=>string|null, 'ms'=>int]
 */
function felPeticion($url, array $cfg, $identificador, $xmlBody) {
    $headers = [
        'UsuarioFirma: '  . $cfg['usuario_firma'],
        'LlaveFirma: '    . $cfg['llave_firma'],
        'UsuarioApi: '    . $cfg['usuario_api'],
        'LlaveApi: '      . $cfg['llave_api'],
        'identificador: ' . $identificador,
        'Content-Type: text/xml; charset=utf-8',
    ];

    $inicio = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xmlBody,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $ms = (int)round((microtime(true) - $inicio) * 1000);

    if ($raw === false) {
        return ['ok'=>false, 'http_code'=>0, 'data'=>null, 'raw'=>'', 'error'=>'Error de conexión: ' . $curlErr, 'ms'=>$ms];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok'=>false, 'http_code'=>$httpCode, 'data'=>null, 'raw'=>$raw,
                'error'=>'Respuesta no reconocida del certificador.', 'ms'=>$ms];
    }

    $ok = !empty($data['resultado']);
    return ['ok'=>$ok, 'http_code'=>$httpCode, 'data'=>$data, 'raw'=>$raw,
            'error'=>$ok ? null : felExtraerError($data), 'ms'=>$ms];
}

/**
 * Arma un mensaje legible a partir del JSON de error del certificador.
 */
function felExtraerError(array $data) {
    $partes = [];
    if (!empty($data['descripcion'])) $partes[] = $data['descripcion'];

    foreach (['descripcion_errores', 'errores', 'cantidad_errores'] as $campo) {
        if (empty($data[$campo])) continue;
        $val = $data[$campo];
        if (is_array($val)) {
            foreach ($val as $e) {
                if (is_array($e)) {
                    $msg = $e['mensaje_error'] ?? ($e['descripcion'] ?? json_encode($e, JSON_UNESCAPED_UNICODE));
                    $fnt = $e['fuente'] ?? null;
                    $partes[] = $fnt ? "$fnt: $msg" : $msg;
                } else {
                    $partes[] = (string)$e;
                }
            }
        } elseif (!is_numeric($val)) {
            $partes[] = (string)$val;
        }
    }

    $partes = array_values(array_unique(array_filter($partes)));
    return $partes ? implode(' | ', $partes) : 'El certificador rechazó el documento.';
}

/**
 * Registra la petición en fel_log.
 */
function felLog($documentoId, $accion, $httpCode, $request, $response, $ms) {
    try {
        getDB()->prepare("INSERT INTO fel_log (documento_id,accion,http_code,request_body,response_body,duracion_ms) VALUES (?,?,?,?,?,?)")
               ->execute([$documentoId, $accion, $httpCode, $request, $response, $ms]);
    } catch (Exception $e) {
        // La bitácora nunca debe romper la operación
    }
}

/**
 * Certifica un documento.
 *
 * @param int   $sucursalId
 * @param array $receptor  ver felGenerarXml()
 * @param array $items     ver felGenerarXml()
 * @param array $opts      ['tipo_dte','venta_id','orden_id','usuario_id','adenda']
 * @return array ['ok'=>bool,'documento_id'=>int,'uuid'=>string|null,'serie','numero','error'=>string|null]
 */
function felCertificar($sucursalId, array $receptor, array $items, array $opts = []) {
    $db  = getDB();
    $cfg = felConfig($sucursalId);

    if (!felHabilitado($sucursalId)) {
        return ['ok'=>false, 'documento_id'=>null, 'error'=>'FEL no está habilitado o le faltan datos de configuración.'];
    }
    if (empty($items)) {
        return ['ok'=>false, 'documento_id'=>null, 'error'=>'El documento no tiene líneas.'];
    }

    $tipoDte       = $opts['tipo_dte'] ?? 'FACT';
    $identificador = felIdentificador($tipoDte);
    $gen           = felGenerarXml($cfg, $receptor, $items, ['tipo_dte'=>$tipoDte, 'adenda'=>$opts['adenda'] ?? null]);

    // Registrar el documento como pendiente antes de enviarlo
    $db->prepare("INSERT INTO fel_documentos
            (sucursal_id,venta_id,orden_id,tipo_dte,identificador,estado,
             nit_receptor,nombre_receptor,correo_receptor,
             gran_total,monto_gravable,monto_iva,xml_enviado,created_by)
         VALUES (?,?,?,?,?,'pendiente',?,?,?,?,?,?,?,?)")
       ->execute([
            $sucursalId,
            $opts['venta_id'] ?? null,
            $opts['orden_id'] ?? null,
            $tipoDte,
            $identificador,
            felNit($receptor['nit'] ?? 'CF'),
            $receptor['nombre'] ?? 'CONSUMIDOR FINAL',
            $receptor['correo'] ?? null,
            $gen['gran_total'],
            $gen['monto_gravable'],
            $gen['monto_iva'],
            $gen['xml'],
            $opts['usuario_id'] ?? null,
       ]);
    $documentoId = (int)$db->lastInsertId();

    // Enviar al certificador
    $res = felPeticion($cfg['url_certificacion'], $cfg, $identificador, $gen['xml']);
    felLog($documentoId, 'certificar', $res['http_code'], $gen['xml'], $res['raw'], $res['ms']);

    if (!$res['ok']) {
        $db->prepare("UPDATE fel_documentos SET estado='rechazado', mensaje_error=?, respuesta_json=? WHERE id=?")
           ->execute([$res['error'], $res['raw'], $documentoId]);
        return ['ok'=>false, 'documento_id'=>$documentoId, 'error'=>$res['error']];
    }

    $d       = $res['data'];
    $uuid    = $d['uuid']   ?? null;
    $serie   = $d['serie']  ?? null;
    $numero  = $d['numero'] ?? null;
    $xmlCert = !empty($d['xml_certificado']) ? base64_decode($d['xml_certificado']) : null;
    $fechaC  = !empty($d['fecha']) ? date('Y-m-d H:i:s', strtotime($d['fecha'])) : date('Y-m-d H:i:s');

    $db->prepare("UPDATE fel_documentos
                     SET estado='certificado', uuid=?, serie=?, numero=?, fecha_certificacion=?,
                         xml_certificado=?, respuesta_json=?, mensaje_error=NULL
                   WHERE id=?")
       ->execute([$uuid, $serie, $numero, $fechaC, $xmlCert, $res['raw'], $documentoId]);

    return ['ok'=>true, 'documento_id'=>$documentoId, 'uuid'=>$uuid,
            'serie'=>$serie, 'numero'=>$numero, 'error'=>null];
}

/**
 * Anula un documento certificado.
 */
function felAnular($documentoId, $motivo) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM fel_documentos WHERE id=? LIMIT 1");
    $stmt->execute([$documentoId]);
    $doc  = $stmt->fetch();

    if (!$doc)                              return ['ok'=>false, 'error'=>'Documento no encontrado.'];
    if ($doc['estado'] !== 'certificado')   return ['ok'=>false, 'error'=>'Solo se puede anular un documento certificado.'];
    if (empty($doc['uuid']))                return ['ok'=>false, 'error'=>'El documento no tiene número de autorización.'];

    $cfg = felConfig($doc['sucursal_id']);
    if (!felHabilitado($doc['sucursal_id'])) {
        return ['ok'=>false, 'error'=>'FEL no está habilitado para esta sucursal.'];
    }

    $identificador = felIdentificador('ANUL');
    $xml = felGenerarXmlAnulacion($cfg, $doc['uuid'], $doc['nit_receptor'],
                                  $doc['fecha_certificacion'] ?: $doc['created_at'], $motivo);

    $res = felPeticion($cfg['url_anulacion'], $cfg, $identificador, $xml);
    felLog($documentoId, 'anular', $res['http_code'], $xml, $res['raw'], $res['ms']);

    if (!$res['ok']) {
        return ['ok'=>false, 'error'=>$res['error']];
    }

    $uuidAnul = $res['data']['uuid'] ?? null;
    $db->prepare("UPDATE fel_documentos SET estado='anulado', motivo_anulacion=?, fecha_anulacion=NOW(), uuid_anulacion=? WHERE id=?")
       ->execute([$motivo, $uuidAnul, $documentoId]);

    return ['ok'=>true, 'uuid_anulacion'=>$uuidAnul, 'error'=>null];
}

/**
 * Devuelve el DTE asociado a una venta (o null).
 */
function felDocumentoDeVenta($ventaId) {
    $stmt = getDB()->prepare("SELECT * FROM fel_documentos WHERE venta_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$ventaId]);
    return $stmt->fetch() ?: null;
}

/**
 * Devuelve el DTE asociado a una orden (o null).
 */
function felDocumentoDeOrden($ordenId) {
    $stmt = getDB()->prepare("SELECT * FROM fel_documentos WHERE orden_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$ordenId]);
    return $stmt->fetch() ?: null;
}
