-- =============================================
-- PLATAFORMA DIAZ - MOTOSERVICIO
-- Base de datos completa
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------
-- SUCURSALES
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS sucursales (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    direccion   VARCHAR(255),
    telefono    VARCHAR(20),
    correo      VARCHAR(100),
    activa      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- USUARIOS DE PLATAFORMA
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id   INT UNSIGNED NOT NULL,
    nombre        VARCHAR(100) NOT NULL,
    apellido      VARCHAR(100) NOT NULL,
    telefono      VARCHAR(20),
    direccion     VARCHAR(255),
    rol_id        TINYINT UNSIGNED NOT NULL COMMENT '1=Admin,2=Técnico,3=Vendedor,4=Cajero,5=Asesor',
    username      VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    activo        TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- CLIENTES
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT UNSIGNED NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    apellido    VARCHAR(100) NOT NULL,
    direccion   VARCHAR(255),
    dpi         VARCHAR(20),
    nit         VARCHAR(20),
    telefono    VARCHAR(20),
    correo      VARCHAR(100),
    activo      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- PROVEEDORES
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS proveedores (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    apellido    VARCHAR(100),
    correo      VARCHAR(100),
    nit         VARCHAR(20),
    dpi         VARCHAR(20),
    direccion   VARCHAR(255),
    telefono    VARCHAR(20),
    activo      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- MARCAS DE MOTOCICLETAS
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS marcas (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- MOTOCICLETAS
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS motocicletas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id  INT UNSIGNED NOT NULL,
    marca_id    INT UNSIGNED,
    marca_texto VARCHAR(100),
    modelo      VARCHAR(100),
    anio        YEAR,
    color       VARCHAR(60),
    kilometraje INT UNSIGNED DEFAULT 0,
    vin         VARCHAR(50),
    placa       VARCHAR(20),
    notas       TEXT,
    activa      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (marca_id)   REFERENCES marcas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- CATEGORÍAS DE PRODUCTOS
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- PRODUCTOS (servicios y estándar)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED,
    tipo         ENUM('servicio','estandar') NOT NULL DEFAULT 'estandar',
    nombre       VARCHAR(150) NOT NULL,
    descripcion  TEXT,
    precio       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unidad       VARCHAR(30) DEFAULT 'UND',
    medida       VARCHAR(30),
    stock        INT DEFAULT 0 COMMENT 'Solo aplica para tipo=estandar',
    stock_minimo INT DEFAULT 0,
    activo       TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- ÓRDENES DE SERVICIO
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS ordenes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED NOT NULL,
    cliente_id          INT UNSIGNED NOT NULL,
    motocicleta_id      INT UNSIGNED NOT NULL,
    tecnico_id          INT UNSIGNED,
    asesor_id           INT UNSIGNED,
    numero_orden        VARCHAR(20) NOT NULL UNIQUE,
    fecha_ingreso       DATE NOT NULL,
    fecha_salida_esperada DATE,
    fecha_salida_real   DATE,
    kilometraje_ingreso INT UNSIGNED,
    estado              ENUM('abierta','en_proceso','lista','entregada','cancelada') DEFAULT 'abierta',
    diagnostico         TEXT,
    observaciones       TEXT,
    total               DECIMAL(10,2) DEFAULT 0.00,
    anticipo            DECIMAL(10,2) DEFAULT 0.00,
    saldo               DECIMAL(10,2) DEFAULT 0.00,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sucursal_id)    REFERENCES sucursales(id),
    FOREIGN KEY (cliente_id)     REFERENCES clientes(id),
    FOREIGN KEY (motocicleta_id) REFERENCES motocicletas(id),
    FOREIGN KEY (tecnico_id)     REFERENCES usuarios(id),
    FOREIGN KEY (asesor_id)      REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- DETALLE DE ORDEN (productos/servicios usados)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS orden_detalle (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orden_id    INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad    DECIMAL(10,2) NOT NULL DEFAULT 1,
    precio_unit DECIMAL(10,2) NOT NULL,
    subtotal    DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (orden_id)    REFERENCES ordenes(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- ANTICIPOS
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS anticipos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orden_id    INT UNSIGNED NOT NULL,
    cajero_id   INT UNSIGNED,
    monto       DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo','tarjeta','transferencia','otro') DEFAULT 'efectivo',
    notas       VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orden_id)  REFERENCES ordenes(id),
    FOREIGN KEY (cajero_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- VENTAS (mostrador, sin orden)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS ventas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT UNSIGNED NOT NULL,
    cliente_id  INT UNSIGNED,
    vendedor_id INT UNSIGNED,
    numero_venta VARCHAR(20) NOT NULL UNIQUE,
    fecha       DATE NOT NULL,
    total       DECIMAL(10,2) DEFAULT 0.00,
    metodo_pago ENUM('efectivo','tarjeta','transferencia','otro') DEFAULT 'efectivo',
    estado      ENUM('pagada','anulada') DEFAULT 'pagada',
    notas       TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
    FOREIGN KEY (cliente_id)  REFERENCES clientes(id),
    FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS venta_detalle (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id    INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad    DECIMAL(10,2) NOT NULL DEFAULT 1,
    precio_unit DECIMAL(10,2) NOT NULL,
    subtotal    DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id)    REFERENCES ventas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- COMPRAS A PROVEEDORES
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS compras (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id     INT UNSIGNED NOT NULL,
    proveedor_id    INT UNSIGNED,
    numero_compra   VARCHAR(20) NOT NULL UNIQUE,
    fecha           DATE NOT NULL,
    total           DECIMAL(10,2) DEFAULT 0.00,
    estado          ENUM('recibida','pendiente','anulada') DEFAULT 'recibida',
    notas           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sucursal_id)  REFERENCES sucursales(id),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compra_detalle (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    compra_id   INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad    DECIMAL(10,2) NOT NULL DEFAULT 1,
    precio_unit DECIMAL(10,2) NOT NULL,
    subtotal    DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (compra_id)   REFERENCES compras(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- DATOS INICIALES
-- ---------------------------------------------
INSERT INTO sucursales (nombre, direccion) VALUES ('Sucursal Principal', 'Guatemala');

INSERT INTO categorias (nombre) VALUES
    ('Aceites y lubricantes'),
    ('Filtros'),
    ('Frenos'),
    ('Eléctrico'),
    ('Suspensión'),
    ('Servicios generales');

INSERT INTO marcas (nombre) VALUES
    ('Honda'),('Yamaha'),('Suzuki'),('Kawasaki'),('KTM'),
    ('Bajaj'),('Hero'),('AKT'),('Italika'),('Otro');

-- Usuario admin por defecto (password: Admin123)
INSERT INTO usuarios (sucursal_id, nombre, apellido, rol_id, username, password_hash)
VALUES (1, 'Administrador', 'Sistema', 1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

SET FOREIGN_KEY_CHECKS = 1;
