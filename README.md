# 🏍️ DIAZ Motoservicio

Sistema de gestión para talleres de motocicletas — multisucursal.

## Stack
- PHP puro + PDO
- MySQL
- Tailwind CSS (CDN)
- Alpine.js (CDN)
- Font Awesome (CDN)

## Instalación en cPanel

1. Clonar o subir los archivos al `public_html` (o subdirectorio).
2. Crear la base de datos en cPanel → MySQL Databases.
3. Importar `config/database.sql` desde phpMyAdmin.
4. Copiar `config/database.example.php` → `config/database.php` y ajustar credenciales.
5. Ajustar `BASE_URL` en `config/config.php`.

## Credenciales por defecto
- Usuario: `admin`
- Contraseña: `Admin123`

> ⚠️ Cambiar la contraseña al primer ingreso.

## Módulos
- Dashboard con órdenes activas, alertas de vencimiento y resumen financiero
- Clientes
- Motocicletas
- Órdenes de servicio
- Productos e inventario
- Ventas y compras
- Proveedores
- Usuarios y roles
- Sucursales
- Reportes con filtros
<!-- deploy test -->
