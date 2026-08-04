<?php
ob_start(); // Buffer output — permite usar header() en cualquier punto

define('APP_NAME', 'DIAZ Motoservicio');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://motoserviciodiaz.nuevaexpress.com'); // Producción

// Zona horaria
date_default_timezone_set('America/Guatemala');

// Sesión
session_name('diaz_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Roles de usuario
define('ROL_ADMIN',          1);
define('ROL_TECNICO',        2);
define('ROL_VENDEDOR',       3);
define('ROL_CAJERO',         4);
define('ROL_ASESOR_VENTAS',  5);

$ROLES = [
    ROL_ADMIN         => 'Administrador',
    ROL_TECNICO       => 'Técnico',
    ROL_VENDEDOR      => 'Vendedor',
    ROL_CAJERO        => 'Cajero',
    ROL_ASESOR_VENTAS => 'Asesor de Ventas',
];
