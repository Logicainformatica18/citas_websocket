-- =====================================================================
-- 01_silabos_aplicar.sql
-- BASE: la de silabos_v2. En local, silabo_2.
--
-- Antes: 00_verificar_silabos.sql sin ningún NO COINCIDE.
-- Selecciona la base como predeterminada (Workbench: doble clic en el
-- esquema) y ejecuta el archivo completo con Ctrl+Shift+Enter, con
-- auto-commit y "Stop on error" activos. No usa USE: si no hay base
-- seleccionada, el CREATE TABLE falla con "No database selected".
--
-- Crea la tabla observatorio_envios (registro de cada envío al
-- observatorio, para reintentar) y la registra en migrations. Equivale a
-- la migración 2026_10_28_000001_create_observatorio_envios_table (que se
-- mantiene solo para los tests). Solo crea; no modifica ni borra nada.
-- =====================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;


-- ---------------------------------------------------------------------
-- 1. observatorio_envios: un registro por (modelo, sílabo, versión), con
--    su estado (pendiente / enviado / error). Índice único por versión de
--    sílabo e índice por estado para buscar pendientes.
-- ---------------------------------------------------------------------

CREATE TABLE observatorio_envios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    modelo VARCHAR(10) NOT NULL,
    silabo_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    tipo_plantilla TINYINT UNSIGNED NULL,
    origen VARCHAR(20) NOT NULL,
    payload JSON NOT NULL,
    content_hash CHAR(64) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    intentos INT UNSIGNED NOT NULL DEFAULT 0,
    http_status SMALLINT UNSIGNED NULL,
    respuesta VARCHAR(50) NULL,
    ultimo_error TEXT NULL,
    enviado_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY observatorio_envios_unique (modelo, silabo_id, version),
    KEY observatorio_envios_estado_index (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Registro en migrations con el batch siguiente al máximo. No duplica.
--    Esperado: 1 fila afectada.
-- ---------------------------------------------------------------------

INSERT INTO migrations (migration, batch)
SELECT m.migration, (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations)
FROM (SELECT '2026_10_28_000001_create_observatorio_envios_table' AS migration) AS m
WHERE NOT EXISTS (SELECT 1 FROM migrations x WHERE x.migration = m.migration);


-- ---------------------------------------------------------------------
-- 3. Comprobación final (solo lectura).
-- ---------------------------------------------------------------------

-- 1 fila.
SELECT migration, batch FROM migrations WHERE migration = '2026_10_28_000001_create_observatorio_envios_table';
-- 3 índices: PRIMARY, observatorio_envios_unique, observatorio_envios_estado_index.
SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'observatorio_envios';
