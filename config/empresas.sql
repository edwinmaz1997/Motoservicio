-- =============================================
-- MULTIEMPRESA — cada taller es una empresa aislada
-- Ejecutar en orden. Seguro de correr una sola vez.
-- =============================================

-- 1) Empresas (los talleres que contratan el sistema)
CREATE TABLE IF NOT EXISTS empresas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    nit             VARCHAR(20)  DEFAULT NULL,
    slug            VARCHAR(60)  DEFAULT NULL,

    -- Contacto del dueño
    contacto_nombre   VARCHAR(120) DEFAULT NULL,
    contacto_telefono VARCHAR(30)  DEFAULT NULL,
    contacto_correo   VARCHAR(120) DEFAULT NULL,

    -- Contrato
    plan            ENUM('basico','taller','multisucursal') DEFAULT 'taller',
    estado          ENUM('activa','suspendida','prueba','cancelada') DEFAULT 'activa',
    fecha_inicio    DATE DEFAULT NULL,
    fecha_vence     DATE DEFAULT NULL COMMENT 'NULL = sin vencimiento',
    mensualidad     DECIMAL(10,2) DEFAULT 0.00,

    -- Límites del plan
    max_sucursales  INT UNSIGNED DEFAULT 1,
    max_usuarios    INT UNSIGNED DEFAULT 5,
    fel_habilitado  TINYINT(1) DEFAULT 0,

    notas           TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- La empresa que ya existe
INSERT INTO empresas (id, nombre, slug, plan, estado, fecha_inicio, max_sucursales, max_usuarios, fel_habilitado)
VALUES (1, 'Motoservicio DIAZ', 'motoservicio-diaz', 'taller', 'activa', CURDATE(), 5, 20, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- 2) Cada dato pasa a pertenecer a una empresa
ALTER TABLE sucursales   ADD COLUMN empresa_id INT UNSIGNED NOT NULL DEFAULT 1, ADD KEY idx_emp (empresa_id);
ALTER TABLE productos    ADD COLUMN empresa_id INT UNSIGNED NOT NULL DEFAULT 1, ADD KEY idx_emp (empresa_id);
ALTER TABLE categorias   ADD COLUMN empresa_id INT UNSIGNED NOT NULL DEFAULT 1, ADD KEY idx_emp (empresa_id);
ALTER TABLE proveedores  ADD COLUMN empresa_id INT UNSIGNED NOT NULL DEFAULT 1, ADD KEY idx_emp (empresa_id);

ALTER TABLE sucursales  ADD CONSTRAINT fk_suc_emp  FOREIGN KEY (empresa_id) REFERENCES empresas(id);
ALTER TABLE productos   ADD CONSTRAINT fk_prod_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id);
ALTER TABLE categorias  ADD CONSTRAINT fk_cat_emp  FOREIGN KEY (empresa_id) REFERENCES empresas(id);
ALTER TABLE proveedores ADD CONSTRAINT fk_prov_emp FOREIGN KEY (empresa_id) REFERENCES empresas(id);

-- 3) El superadministrador no pertenece a ninguna sucursal
ALTER TABLE usuarios MODIFY COLUMN sucursal_id INT UNSIGNED NULL;
ALTER TABLE usuarios ADD COLUMN es_super TINYINT(1) DEFAULT 0 COMMENT '1 = administra todas las empresas';

-- 4) Bitácora de lo que hace el superadministrador
CREATE TABLE IF NOT EXISTS master_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED DEFAULT NULL,
    empresa_id  INT UNSIGNED DEFAULT NULL,
    accion      VARCHAR(60) NOT NULL,
    detalle     VARCHAR(255) DEFAULT NULL,
    ip          VARCHAR(45) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_emp (empresa_id),
    KEY idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Crear el superadministrador
-- Usuario: super  ·  Contraseña: Super2026  (cámbiala al primer ingreso)
INSERT INTO usuarios (sucursal_id, nombre, apellido, rol_id, username, password_hash, es_super, activo)
VALUES (NULL, 'Super', 'Administrador', 1, 'super',
        '$2b$12$BlhUM0R7avwCRRwSD1tJTOImoHGCyS.i8W3FrSAfXjfDTZjEkOD2u', 1, 1);
