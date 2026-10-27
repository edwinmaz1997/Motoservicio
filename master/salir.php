<?php
/** Vuelve al panel maestro dejando la empresa. */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/empresa.php';
requireLogin();
requireSuper();

salirDeEmpresa();
header('Location: ' . BASE_URL . '/master/index.php');
exit;
