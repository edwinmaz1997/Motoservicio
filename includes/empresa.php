<?php
/**
 * Aislamiento por empresa.
 *
 * Cada taller es una empresa. Los datos que antes eran globales
 * (productos, categorías, proveedores) ahora pertenecen a una, y las
 * sucursales cuelgan de ella. El superadministrador no pertenece a
 * ninguna: administra todas y puede entrar a cualquiera.
 */

/** ¿Ya se instaló el módulo multiempresa? */
function empresasInstalado() {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        getDB()->query("SELECT 1 FROM empresas LIMIT 1");
        $ok = true;
    } catch (Exception $e) {
        $ok = false;
    }
    return $ok;
}

/** ¿El usuario de la sesión administra todas las empresas? */
function esSuper() {
    return !empty($_SESSION['es_super']);
}

/**
 * Empresa sobre la que se está trabajando.
 * Para el super es la que eligió entrar; para los demás, la suya.
 */
function empresaActual() {
    return $_SESSION['empresa_id'] ?? null;
}

/** Datos de una empresa. */
function empresaInfo($empresaId = null) {
    static $cache = [];
    $id = $empresaId ?: empresaActual();
    if (!$id || !empresasInstalado()) return null;
    if (isset($cache[$id])) return $cache[$id];

    $stmt = getDB()->prepare("SELECT * FROM empresas WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $cache[$id] = $stmt->fetch() ?: null;
    return $cache[$id];
}

/**
 * Estado del contrato: si está suspendida o vencida, nadie entra
 * (salvo el superadministrador, que necesita poder arreglarlo).
 */
function empresaBloqueada($empresaId = null) {
    $e = empresaInfo($empresaId);
    if (!$e) return false;
    if (in_array($e['estado'], ['suspendida','cancelada'])) return $e['estado'];
    if ($e['fecha_vence'] && $e['fecha_vence'] < date('Y-m-d')) return 'vencida';
    return false;
}

/**
 * Corta el acceso cuando el contrato no está al día.
 * Se llama desde el header, antes de pintar nada.
 */
function exigirEmpresaActiva() {
    if (!empresasInstalado() || esSuper()) return;
    $motivo = empresaBloqueada();
    if ($motivo) {
        $_SESSION['bloqueo_motivo'] = $motivo;
        header('Location: ' . BASE_URL . '/suspendido.php');
        exit;
    }
}

/**
 * Sucursales de la empresa en curso.
 */
function sucursalesEmpresa($empresaId = null) {
    $id = $empresaId ?: empresaActual();
    if (!$id) return [];
    $stmt = getDB()->prepare("SELECT * FROM sucursales WHERE empresa_id=? AND activa=1 ORDER BY nombre");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

/**
 * Cláusula para filtrar catálogos compartidos por empresa.
 * Se usa donde antes la consulta no tenía filtro alguno.
 */
function filtroEmpresa($alias = '') {
    if (!empresasInstalado()) return ['sql' => '', 'params' => []];
    $col = $alias ? "$alias.empresa_id" : 'empresa_id';
    return ['sql' => " AND $col = ?", 'params' => [empresaActual()]];
}

/**
 * Registra una acción del superadministrador.
 */
function masterLog($accion, $empresaId = null, $detalle = null) {
    try {
        getDB()->prepare("INSERT INTO master_log (usuario_id,empresa_id,accion,detalle,ip) VALUES (?,?,?,?,?)")
               ->execute([
                   $_SESSION['usuario_id'] ?? null,
                   $empresaId,
                   $accion,
                   $detalle,
                   $_SERVER['REMOTE_ADDR'] ?? null,
               ]);
    } catch (Exception $e) { /* la bitácora nunca corta la operación */ }
}

/**
 * El superadministrador entra a trabajar dentro de una empresa.
 */
function entrarAEmpresa($empresaId) {
    if (!esSuper()) return false;
    $e = empresaInfo($empresaId);
    if (!$e) return false;

    $suc = sucursalesEmpresa($empresaId);
    if (!$suc) return false;

    $_SESSION['empresa_id']      = (int)$empresaId;
    $_SESSION['empresa_nombre']  = $e['nombre'];
    $_SESSION['sucursal_id']     = $suc[0]['id'];
    $_SESSION['sucursal_nombre'] = $suc[0]['nombre'];
    $_SESSION['suplantando']     = true;

    masterLog('entrar_empresa', $empresaId, $e['nombre']);
    return true;
}

/** El superadministrador vuelve a su panel. */
function salirDeEmpresa() {
    unset($_SESSION['empresa_id'], $_SESSION['empresa_nombre'],
          $_SESSION['sucursal_id'], $_SESSION['sucursal_nombre'],
          $_SESSION['suplantando']);
}

/** Sólo para el superadministrador. */
function requireSuper() {
    if (!esSuper()) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

/**
 * Cuántas sucursales y usuarios lleva la empresa, contra su límite.
 */
function usoEmpresa($empresaId = null) {
    $id = $empresaId ?: empresaActual();
    $e  = empresaInfo($id);
    if (!$e) return null;
    $db = getDB();

    $s = $db->prepare("SELECT COUNT(*) FROM sucursales WHERE empresa_id=?");
    $s->execute([$id]);
    $nSuc = (int)$s->fetchColumn();

    $u = $db->prepare("SELECT COUNT(*) FROM usuarios u JOIN sucursales s ON s.id=u.sucursal_id WHERE s.empresa_id=? AND u.activo=1");
    $u->execute([$id]);
    $nUsr = (int)$u->fetchColumn();

    return [
        'sucursales'     => $nSuc,
        'max_sucursales' => (int)$e['max_sucursales'],
        'usuarios'       => $nUsr,
        'max_usuarios'   => (int)$e['max_usuarios'],
    ];
}
