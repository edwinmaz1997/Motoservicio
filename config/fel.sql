-- =============================================
-- MÓDULO FEL — Facturación Electrónica en Línea (Guatemala)
-- Certificador: INFILE S.A. — Proceso Unificado
-- =============================================

-- ---------------------------------------------
-- Configuración FEL por sucursal
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS fel_config (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED NOT NULL,

    -- Credenciales INFILE (headers del web service)
    usuario_firma       VARCHAR(100) DEFAULT NULL COMMENT 'UsuarioFirma / prefijo',
    llave_firma         VARCHAR(255) DEFAULT NULL COMMENT 'LlaveFirma / Token Signer',
    usuario_api         VARCHAR(100) DEFAULT NULL COMMENT 'UsuarioApi',
    llave_api           VARCHAR(255) DEFAULT NULL COMMENT 'LlaveApi',

    -- Endpoints
    url_certificacion   VARCHAR(255) DEFAULT 'https://certificador.feel.com.gt/fel/procesounificado/transaccion/v2/xml',
    url_anulacion       VARCHAR(255) DEFAULT 'https://certificador.feel.com.gt/fel/procesounificado/transaccion/v2/anulacion',

    -- Datos fiscales del emisor
    nit_emisor          VARCHAR(20)  DEFAULT NULL COMMENT 'Sin guiones, ej. 1000000000K',
    nombre_emisor       VARCHAR(200) DEFAULT NULL COMMENT 'Razón social',
    nombre_comercial    VARCHAR(200) DEFAULT NULL,
    correo_emisor       VARCHAR(100) DEFAULT NULL,
    afiliacion_iva      VARCHAR(10)  DEFAULT 'GEN' COMMENT 'GEN, PEQ, PES, AGR, EXE',
    codigo_establecimiento VARCHAR(10) DEFAULT '1',

    -- Dirección fiscal
    direccion           VARCHAR(255) DEFAULT NULL,
    codigo_postal       VARCHAR(10)  DEFAULT '01001',
    municipio           VARCHAR(100) DEFAULT NULL,
    departamento        VARCHAR(100) DEFAULT NULL,
    pais                VARCHAR(5)   DEFAULT 'GT',

    -- Operación
    activo              TINYINT(1) DEFAULT 0 COMMENT '1 = FEL habilitado para esta sucursal',
    ambiente            ENUM('implementacion','produccion') DEFAULT 'implementacion',
    porcentaje_iva      DECIMAL(5,2) DEFAULT 12.00,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sucursal (sucursal_id),
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Documentos electrónicos emitidos
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS fel_documentos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED NOT NULL,

    -- Origen del documento (uno de los dos)
    venta_id            INT UNSIGNED DEFAULT NULL,
    orden_id            INT UNSIGNED DEFAULT NULL,

    tipo_dte            VARCHAR(10) NOT NULL DEFAULT 'FACT' COMMENT 'FACT, RECI, NCRE, NDEB',
    identificador       VARCHAR(100) NOT NULL COMMENT 'Identificador único enviado al certificador',

    -- Respuesta del certificador
    estado              ENUM('pendiente','certificado','rechazado','anulado') DEFAULT 'pendiente',
    uuid                VARCHAR(100) DEFAULT NULL COMMENT 'Número de autorización SAT',
    serie               VARCHAR(50)  DEFAULT NULL,
    numero              VARCHAR(50)  DEFAULT NULL,
    fecha_certificacion DATETIME     DEFAULT NULL,

    -- Datos del receptor (congelados al momento de emitir)
    nit_receptor        VARCHAR(20)  DEFAULT 'CF',
    nombre_receptor     VARCHAR(200) DEFAULT NULL,
    correo_receptor     VARCHAR(100) DEFAULT NULL,

    -- Montos
    gran_total          DECIMAL(12,2) DEFAULT 0.00,
    monto_gravable      DECIMAL(12,2) DEFAULT 0.00,
    monto_iva           DECIMAL(12,2) DEFAULT 0.00,

    -- Payloads
    xml_enviado         MEDIUMTEXT DEFAULT NULL,
    xml_certificado     MEDIUMTEXT DEFAULT NULL COMMENT 'XML firmado devuelto por el certificador',
    respuesta_json      TEXT DEFAULT NULL,
    mensaje_error       TEXT DEFAULT NULL,

    -- Anulación
    motivo_anulacion    VARCHAR(255) DEFAULT NULL,
    fecha_anulacion     DATETIME     DEFAULT NULL,
    uuid_anulacion      VARCHAR(100) DEFAULT NULL,

    created_by          INT UNSIGNED DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_identificador (identificador),
    KEY idx_venta  (venta_id),
    KEY idx_orden  (orden_id),
    KEY idx_estado (estado),
    KEY idx_uuid   (uuid),
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
    FOREIGN KEY (venta_id)    REFERENCES ventas(id),
    FOREIGN KEY (orden_id)    REFERENCES ordenes(id),
    FOREIGN KEY (created_by)  REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Bitácora de peticiones al certificador
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS fel_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    documento_id    INT UNSIGNED DEFAULT NULL,
    accion          VARCHAR(30) NOT NULL COMMENT 'certificar, anular',
    http_code       INT DEFAULT NULL,
    request_body    MEDIUMTEXT DEFAULT NULL,
    response_body   MEDIUMTEXT DEFAULT NULL,
    duracion_ms     INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_documento (documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------
-- Campos adicionales en tablas existentes
-- ---------------------------------------------
ALTER TABLE ventas
    ADD COLUMN emitir_fel TINYINT(1) DEFAULT 0 COMMENT '1 = la venta debe generar DTE';

ALTER TABLE clientes
    ADD COLUMN codigo_postal VARCHAR(10)  DEFAULT NULL,
    ADD COLUMN municipio     VARCHAR(100) DEFAULT NULL,
    ADD COLUMN departamento  VARCHAR(100) DEFAULT NULL;

-- Unidades de medida SAT y tipo bien/servicio en productos
ALTER TABLE productos
    ADD COLUMN unidad_sat VARCHAR(10) DEFAULT 'UND' COMMENT 'Unidad de medida para el DTE';

-- Config FEL inicial para la sucursal 1
INSERT INTO fel_config (sucursal_id) VALUES (1)
    ON DUPLICATE KEY UPDATE sucursal_id = sucursal_id;
