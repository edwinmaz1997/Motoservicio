<?php
/**
 * Generación de XML para documentos FEL (SAT Guatemala).
 * Esquema dte 0.2.0 — Proceso Unificado INFILE.
 */

/**
 * Escapa texto para XML.
 */
function felEsc($txt) {
    return htmlspecialchars((string)$txt, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Normaliza un NIT: quita guiones y espacios, mayúsculas.
 * Un NIT vacío o inválido se convierte en CF (consumidor final).
 */
function felNit($nit) {
    $nit = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)$nit));
    return $nit !== '' ? $nit : 'CF';
}

/**
 * Desglosa un precio que YA incluye IVA.
 * Devuelve [montoGravable, montoImpuesto].
 */
function felDesglosarIva($totalConIva, $porcentajeIva = 12.00) {
    $factor   = 1 + ($porcentajeIva / 100);
    $gravable = round($totalConIva / $factor, 2);
    $impuesto = round($totalConIva - $gravable, 2);
    return [$gravable, $impuesto];
}

/**
 * Fecha en formato ISO con offset de Guatemala.
 */
function felFechaHora($ts = null) {
    $ts = $ts ?: time();
    return date('Y-m-d\TH:i:s', $ts) . '-06:00';
}

/**
 * Genera un identificador único por transacción.
 */
function felIdentificador($prefijo = 'DTE') {
    return $prefijo . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(6)), 0, 10);
}

/**
 * Construye el XML de un documento tributario electrónico.
 *
 * @param array $cfg      Fila de fel_config
 * @param array $receptor ['nit','nombre','correo','direccion','codigo_postal','municipio','departamento']
 * @param array $items    Cada uno: ['tipo'=>'B'|'S','cantidad','unidad','descripcion','precio_unit','descuento']
 * @param array $opts     ['tipo_dte'=>'FACT','moneda'=>'GTQ','adenda'=>[clave=>valor]]
 * @return string XML
 */
function felGenerarXml(array $cfg, array $receptor, array $items, array $opts = []) {
    $tipoDte = $opts['tipo_dte'] ?? 'FACT';
    $moneda  = $opts['moneda']   ?? 'GTQ';
    $pctIva  = (float)($cfg['porcentaje_iva'] ?? 12.00);
    $fecha   = felFechaHora($opts['timestamp'] ?? null);

    // --- Receptor ---
    $nitReceptor = felNit($receptor['nit'] ?? 'CF');
    $nombreRec   = trim($receptor['nombre'] ?? '');
    if ($nombreRec === '') $nombreRec = 'CONSUMIDOR FINAL';
    // SAT exige que un NIT CF lleve nombre genérico salvo que se indique otro
    $correoRec   = trim($receptor['correo'] ?? '');

    // --- Items y totales ---
    $xmlItems    = '';
    $totalIva    = 0.00;
    $granTotal   = 0.00;
    $linea       = 0;

    foreach ($items as $it) {
        $linea++;
        $cantidad   = round((float)($it['cantidad'] ?? 1), 2);
        $precioUnit = round((float)($it['precio_unit'] ?? 0), 2);
        $descuento  = round((float)($it['descuento'] ?? 0), 2);
        $bienServ   = ($it['tipo'] ?? 'B') === 'S' ? 'S' : 'B';
        $unidad     = $it['unidad'] ?? 'UND';
        $descripcion= $it['descripcion'] ?? 'Producto';

        $precioBruto = round($cantidad * $precioUnit, 2);   // antes de descuento
        $totalLinea  = round($precioBruto - $descuento, 2); // lo que paga el cliente

        [$gravable, $impuesto] = felDesglosarIva($totalLinea, $pctIva);

        $totalIva  = round($totalIva + $impuesto, 2);
        $granTotal = round($granTotal + $totalLinea, 2);

        $xmlItems .= '
          <dte:Item BienOServicio="' . $bienServ . '" NumeroLinea="' . $linea . '">
            <dte:Cantidad>' . number_format($cantidad, 2, '.', '') . '</dte:Cantidad>
            <dte:UnidadMedida>' . felEsc($unidad) . '</dte:UnidadMedida>
            <dte:Descripcion>' . felEsc($descripcion) . '</dte:Descripcion>
            <dte:PrecioUnitario>' . number_format($precioUnit, 2, '.', '') . '</dte:PrecioUnitario>
            <dte:Precio>' . number_format($precioBruto, 2, '.', '') . '</dte:Precio>
            <dte:Descuento>' . number_format($descuento, 2, '.', '') . '</dte:Descuento>
            <dte:Impuestos>
              <dte:Impuesto>
                <dte:NombreCorto>IVA</dte:NombreCorto>
                <dte:CodigoUnidadGravable>1</dte:CodigoUnidadGravable>
                <dte:MontoGravable>' . number_format($gravable, 2, '.', '') . '</dte:MontoGravable>
                <dte:MontoImpuesto>' . number_format($impuesto, 2, '.', '') . '</dte:MontoImpuesto>
              </dte:Impuesto>
            </dte:Impuestos>
            <dte:Total>' . number_format($totalLinea, 2, '.', '') . '</dte:Total>
          </dte:Item>';
    }

    // --- Dirección del receptor (SAT acepta genérica) ---
    $dirRec    = trim($receptor['direccion'] ?? '') ?: 'CIUDAD';
    $cpRec     = trim($receptor['codigo_postal'] ?? '') ?: '01001';
    $muniRec   = trim($receptor['municipio'] ?? '') ?: 'GUATEMALA';
    $deptoRec  = trim($receptor['departamento'] ?? '') ?: 'GUATEMALA';

    // --- Correo del receptor (atributo opcional) ---
    $attrCorreoRec = $correoRec !== '' ? ' CorreoReceptor="' . felEsc($correoRec) . '"' : '';

    // --- Adenda ---
    $xmlAdenda = '';
    if (!empty($opts['adenda']) && is_array($opts['adenda'])) {
        $campos = '';
        foreach ($opts['adenda'] as $k => $v) {
            $tag = preg_replace('/[^A-Za-z0-9_]/', '_', $k);
            $campos .= "\n      <$tag>" . felEsc($v) . "</$tag>";
        }
        if ($campos !== '') {
            $xmlAdenda = "\n    <dte:Adenda>$campos\n    </dte:Adenda>";
        }
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<dte:GTDocumento xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="0.1" xsi:schemaLocation="http://www.sat.gob.gt/dte/fel/0.2.0">
  <dte:SAT ClaseDocumento="dte">
    <dte:DTE ID="DatosCertificados">
      <dte:DatosEmision ID="DatosEmision">
        <dte:DatosGenerales CodigoMoneda="' . felEsc($moneda) . '" FechaHoraEmision="' . $fecha . '" Tipo="' . felEsc($tipoDte) . '"></dte:DatosGenerales>
        <dte:Emisor AfiliacionIVA="' . felEsc($cfg['afiliacion_iva'] ?: 'GEN') . '" CodigoEstablecimiento="' . felEsc($cfg['codigo_establecimiento'] ?: '1') . '" CorreoEmisor="' . felEsc($cfg['correo_emisor']) . '" NITEmisor="' . felNit($cfg['nit_emisor']) . '" NombreComercial="' . felEsc($cfg['nombre_comercial'] ?: $cfg['nombre_emisor']) . '" NombreEmisor="' . felEsc($cfg['nombre_emisor']) . '">
          <dte:DireccionEmisor>
            <dte:Direccion>' . felEsc($cfg['direccion'] ?: 'CIUDAD') . '</dte:Direccion>
            <dte:CodigoPostal>' . felEsc($cfg['codigo_postal'] ?: '01001') . '</dte:CodigoPostal>
            <dte:Municipio>' . felEsc($cfg['municipio'] ?: 'GUATEMALA') . '</dte:Municipio>
            <dte:Departamento>' . felEsc($cfg['departamento'] ?: 'GUATEMALA') . '</dte:Departamento>
            <dte:Pais>' . felEsc($cfg['pais'] ?: 'GT') . '</dte:Pais>
          </dte:DireccionEmisor>
        </dte:Emisor>
        <dte:Receptor' . $attrCorreoRec . ' IDReceptor="' . felEsc($nitReceptor) . '" NombreReceptor="' . felEsc($nombreRec) . '">
          <dte:DireccionReceptor>
            <dte:Direccion>' . felEsc($dirRec) . '</dte:Direccion>
            <dte:CodigoPostal>' . felEsc($cpRec) . '</dte:CodigoPostal>
            <dte:Municipio>' . felEsc($muniRec) . '</dte:Municipio>
            <dte:Departamento>' . felEsc($deptoRec) . '</dte:Departamento>
            <dte:Pais>GT</dte:Pais>
          </dte:DireccionReceptor>
        </dte:Receptor>
        <dte:Frases>
          <dte:Frase CodigoEscenario="1" TipoFrase="1"></dte:Frase>
        </dte:Frases>
        <dte:Items>' . $xmlItems . '
        </dte:Items>
        <dte:Totales>
          <dte:TotalImpuestos>
            <dte:TotalImpuesto NombreCorto="IVA" TotalMontoImpuesto="' . number_format($totalIva, 2, '.', '') . '"></dte:TotalImpuesto>
          </dte:TotalImpuestos>
          <dte:GranTotal>' . number_format($granTotal, 2, '.', '') . '</dte:GranTotal>
        </dte:Totales>
      </dte:DatosEmision>
    </dte:DTE>' . $xmlAdenda . '
  </dte:SAT>
</dte:GTDocumento>';

    return [
        'xml'            => $xml,
        'gran_total'     => $granTotal,
        'monto_iva'      => $totalIva,
        'monto_gravable' => round($granTotal - $totalIva, 2),
    ];
}

/**
 * XML de anulación de un documento ya certificado.
 */
function felGenerarXmlAnulacion(array $cfg, $uuidDocumento, $nitReceptor, $fechaEmisionOriginal, $motivo) {
    $fechaEmision  = date('Y-m-d\TH:i:s', strtotime($fechaEmisionOriginal)) . '-06:00';
    $fechaAnulacion= felFechaHora();

    return '<?xml version="1.0" encoding="UTF-8"?>
<dte:GTAnulacionDocumento xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="0.1">
  <dte:SAT>
    <dte:AnulacionDTE ID="DatosCertificados">
      <dte:DatosGenerales FechaEmisionDocumentoAnular="' . $fechaEmision . '" FechaHoraAnulacion="' . $fechaAnulacion . '" ID="DatosAnulacion" IDReceptor="' . felEsc(felNit($nitReceptor)) . '" MotivoAnulacion="' . felEsc($motivo) . '" NITEmisor="' . felNit($cfg['nit_emisor']) . '" NumeroDocumentoAAnular="' . felEsc($uuidDocumento) . '"></dte:DatosGenerales>
    </dte:AnulacionDTE>
  </dte:SAT>
</dte:GTAnulacionDocumento>';
}
