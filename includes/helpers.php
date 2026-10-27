<?php

function formatMoney($amount) {
    return 'Q ' . number_format($amount, 2);
}

function formatDate($date) {
    if (!$date) return '—';
    return date('d/m/Y', strtotime($date));
}

function sanitize($val) {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateNumero($prefix, $table, $column) {
    $db   = getDB();
    $stmt = $db->query("SELECT MAX(CAST(SUBSTRING($column, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) as max_num FROM $table");
    $row  = $stmt->fetch();
    $next = ($row['max_num'] ?? 0) + 1;
    return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Token del enlace público de una orden. Lo crea si aún no tiene.
 */
function tokenOrden($ordenId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT token_publico FROM ordenes WHERE id=?");
    $stmt->execute([$ordenId]);
    $t = $stmt->fetchColumn();
    if ($t) return $t;

    for ($i = 0; $i < 5; $i++) {
        $nuevo = bin2hex(random_bytes(10));
        try {
            $db->prepare("UPDATE ordenes SET token_publico=? WHERE id=?")->execute([$nuevo, $ordenId]);
            return $nuevo;
        } catch (Exception $e) { /* colisión: reintenta */ }
    }
    return null;
}

/**
 * Enlace que se le comparte al cliente.
 */
function urlSeguimiento($ordenId) {
    $t = tokenOrden($ordenId);
    return $t ? BASE_URL . '/orden.php?t=' . $t : null;
}

/**
 * Normaliza un teléfono guatemalteco a formato internacional para WhatsApp.
 */
function telWhatsApp($tel) {
    $n = preg_replace('/[^0-9]/', '', (string)$tel);
    if ($n === '') return null;
    if (strlen($n) === 8) $n = '502' . $n;      // número local
    return $n;
}
