<?php
// Genera los 3 scripts SQL de limpieza de un pivote oferta↔entidad (misma plantilla para todos los indicadores).
// Uso, desde la raíz del proyecto:
//   php83 docs/produccion/herramientas/generar_limpieza_sql.php <pivote> <fk_catálogo> <tipo_config> <carpeta> <n1> <n2> <n3>
//   ej.: ... language_job language_id languages docs/produccion/ranking_lenguajes 13 14 15
// Escribe {n1}_limpieza_preparar.sql, {n2}_limpieza_aplicar.sql y {n3}_limpieza_rollback.sql.

[$_, $pivot, $fk, $type, $dir, $n1, $n2, $n3] = $argv + array_fill(0, 8, null);
if (! $n3) {
    fwrite(STDERR, "Uso: generar_limpieza_sql.php <pivote> <fk> <tipo_config> <carpeta> <n1> <n2> <n3>\n");
    exit(1);
}
$work = "{$pivot}_limpieza";
$bak = "{$pivot}_respaldo_limpieza";
$proc = "proc_limpieza_{$pivot}";
$hdr = fn (string $name, string $body) => "-- =====================================================================\n-- {$name}\n-- ---------------------------------------------------------------------\n{$body}-- =====================================================================\n\n";

$prep = $hdr("{$n1}_limpieza_preparar.sql", <<<TXT
-- QUÉ HACE
--   Crea las dos tablas de la limpieza de {$pivot} (no toca {$pivot}):
--     {$work}            tabla de trabajo: filas que el matcher no confirma
--                                (accion = 'borrar') o duplicados que pasan a su
--                                entidad principal (accion = 'reasignar'). La llena
--                                la aplicación con el comando de abajo.
--     {$bak}   respaldo de cada fila antes de borrarla o reasignarla.
--
-- ORDEN COMPLETO
--   1. Este script.
--   2. En el servidor de la aplicación:
--        php artisan entities:clean-associations --type={$type} --dry-run   (informe, opcional)
--        php artisan entities:clean-associations --type={$type}             (llena {$work})
--   3. {$n2}_limpieza_aplicar.sql   (respaldo + borrado por lotes)
--   4. php artisan ranking:refresh-{$type}
--   Reversión: {$n3}_limpieza_rollback.sql
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s. Tablas nuevas.
--
-- RE-EJECUCIÓN
--   Idempotente (CREATE TABLE IF NOT EXISTS).

TXT);
$prep .= <<<SQL
CREATE TABLE IF NOT EXISTS `{$work}` (
  `pivot_id`               BIGINT UNSIGNED NOT NULL,
  `job_offer_id`           BIGINT UNSIGNED NOT NULL,
  `market_entity_id`       BIGINT UNSIGNED NULL,
  `accion`                 ENUM('borrar','reasignar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrar',
  `nuevo_catalog_id`       BIGINT UNSIGNED NULL,
  `nuevo_market_entity_id` BIGINT UNSIGNED NULL,
  `source`                 VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `motivo`                 VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `aplicado`               TINYINT NOT NULL DEFAULT 0,
  `created_at`             TIMESTAMP NULL,
  PRIMARY KEY (`pivot_id`),
  KEY `idx_{$pivot}_limp_aplicado` (`aplicado`, `pivot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{$bak}` (
  `id`                     BIGINT UNSIGNED NOT NULL,           -- id original en {$pivot}
  `{$fk}`                  BIGINT UNSIGNED NULL,               -- certification_job admite NULL
  `market_entity_id`       BIGINT UNSIGNED NULL,
  `job_offer_id`           BIGINT UNSIGNED NOT NULL,
  `created_at`             TIMESTAMP NULL,
  `updated_at`             TIMESTAMP NULL,
  `accion`                 ENUM('borrar','reasignar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nuevo_catalog_id`       BIGINT UNSIGNED NULL,
  `nuevo_market_entity_id` BIGINT UNSIGNED NULL,
  `motivo`                 VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `respaldado_en`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `restaurado_en`          TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_{$pivot}_resp_entidad` (`market_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'OK {$n1}: tablas {$work} y {$bak} listas' AS resultado;

SQL;

$apply = $hdr("{$n2}_limpieza_aplicar.sql", <<<TXT
-- QUÉ HACE
--   Aplica la limpieza preparada en {$work}, en lotes de 5 000 filas, cada
--   lote en su propia transacción:
--     1. copia la fila original a {$bak};
--     2. accion = 'borrar'    → DELETE de {$pivot};
--        accion = 'reasignar' → UPDATE de {$fk} y market_entity_id a la entidad
--                               principal (UPDATE IGNORE: si la oferta ya la tenía, la
--                               fila queda como estaba y se informa al final);
--     3. marca el lote como aplicado.
--   NUNCA borra una fila sin haberla respaldado antes en la misma transacción.
--
-- REQUISITOS
--   {$n1}_limpieza_preparar.sql aplicado y la tabla de trabajo llena por
--   php artisan entities:clean-associations --type={$type}
--   Ejecutar con el cliente mysql (usa DELIMITER) y cuando no corra jobs:run-all.
--
-- TIEMPO / BLOQUEOS
--   Bloqueos de fila breves por lote. Lenguajes en local: ~230 000 filas en
--   menos de 1 minuto.
--
-- RE-EJECUCIÓN
--   Idempotente: continúa con los lotes no aplicados (aplicado = 0).
--
-- DESPUÉS
--   php artisan ranking:refresh-{$type}
--
-- REVERSIÓN
--   {$n3}_limpieza_rollback.sql

TXT);
$apply .= <<<SQL
SELECT accion, COUNT(*) AS filas, SUM(aplicado = 0) AS pendientes FROM `{$work}` GROUP BY accion;

DROP PROCEDURE IF EXISTS `{$proc}`;
DELIMITER //
CREATE PROCEDURE `{$proc}`()
BEGIN
  DECLARE n INT DEFAULT 1;
  WHILE n > 0 DO
    START TRANSACTION;
    DROP TEMPORARY TABLE IF EXISTS `_lote_limpieza`;
    CREATE TEMPORARY TABLE `_lote_limpieza` (PRIMARY KEY (pivot_id))
      SELECT pivot_id FROM `{$work}` WHERE aplicado = 0 ORDER BY pivot_id LIMIT 5000;
    SET n = (SELECT COUNT(*) FROM `_lote_limpieza`);

    INSERT INTO `{$bak}`
          (id, `{$fk}`, market_entity_id, job_offer_id, created_at, updated_at,
           accion, nuevo_catalog_id, nuevo_market_entity_id, motivo, respaldado_en)
    SELECT p.id, p.`{$fk}`, p.market_entity_id, p.job_offer_id, p.created_at, p.updated_at,
           w.accion, w.nuevo_catalog_id, w.nuevo_market_entity_id, w.motivo, NOW()
      FROM `_lote_limpieza` l
      JOIN `{$work}` w ON w.pivot_id = l.pivot_id
      JOIN `{$pivot}` p ON p.id = l.pivot_id
    ON DUPLICATE KEY UPDATE                        -- re-aplicación después de un rollback
           restaurado_en = NULL, respaldado_en = NOW(), accion = w.accion, motivo = w.motivo,
           nuevo_catalog_id = w.nuevo_catalog_id, nuevo_market_entity_id = w.nuevo_market_entity_id;

    DELETE p FROM `{$pivot}` p
      JOIN `_lote_limpieza` l ON l.pivot_id = p.id
      JOIN `{$work}` w ON w.pivot_id = l.pivot_id
      JOIN `{$bak}` b ON b.id = p.id
     WHERE w.accion = 'borrar';

    UPDATE IGNORE `{$pivot}` p
      JOIN `_lote_limpieza` l ON l.pivot_id = p.id
      JOIN `{$work}` w ON w.pivot_id = l.pivot_id
      JOIN `{$bak}` b ON b.id = p.id
       SET p.`{$fk}` = w.nuevo_catalog_id, p.market_entity_id = w.nuevo_market_entity_id, p.updated_at = NOW()
     WHERE w.accion = 'reasignar';

    UPDATE `{$work}` w JOIN `_lote_limpieza` l ON l.pivot_id = w.pivot_id SET w.aplicado = 1;
    COMMIT;
  END WHILE;
  DROP TEMPORARY TABLE IF EXISTS `_lote_limpieza`;
END //
DELIMITER ;

CALL `{$proc}`();
DROP PROCEDURE IF EXISTS `{$proc}`;

-- Verificación
SELECT accion, COUNT(*) AS filas, SUM(aplicado = 1) AS aplicadas FROM `{$work}` GROUP BY accion;
SELECT COUNT(*) AS filas_respaldadas FROM `{$bak}` WHERE restaurado_en IS NULL;
SELECT COUNT(*) AS borradas_que_siguen_en_el_pivote
  FROM `{$work}` w JOIN `{$pivot}` p ON p.id = w.pivot_id WHERE w.accion = 'borrar';
SELECT COUNT(*) AS reasignaciones_no_aplicadas_por_duplicado
  FROM `{$work}` w JOIN `{$pivot}` p ON p.id = w.pivot_id
 WHERE w.accion = 'reasignar' AND p.market_entity_id <> w.nuevo_market_entity_id;
SELECT 'OK {$n2}: limpieza aplicada; ahora php artisan ranking:refresh-{$type}' AS resultado;

SQL;

$rollback = $hdr("{$n3}_limpieza_rollback.sql", <<<TXT
-- QUÉ HACE
--   Deshace {$n2}_limpieza_aplicar.sql desde {$bak}:
--     - vuelve a insertar las filas borradas (con su id original);
--     - devuelve las reasignadas a su {$fk} y market_entity_id originales.
--   Marca restaurado_en en el respaldo (no lo borra) y pone aplicado = 0 en la
--   tabla de trabajo. Si una oferta se borró después, su fila no se puede
--   restaurar (INSERT IGNORE la omite) y se informa.
--
-- TIEMPO / BLOQUEOS
--   Lenguajes en local: menos de 1 minuto. Bloqueos de fila.
--
-- RE-EJECUCIÓN
--   Idempotente (solo filas con restaurado_en vacío).
--
-- DESPUÉS
--   php artisan ranking:refresh-{$type}

TXT);
$rollback .= <<<SQL
SET @existe = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$bak}');
SELECT IF(@existe > 0, 'Respaldo encontrado', 'Sin respaldo: nada que revertir') AS resultado;

CREATE TABLE IF NOT EXISTS `{$bak}` (`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY, `{$fk}` BIGINT UNSIGNED NULL,
  `market_entity_id` BIGINT UNSIGNED NULL, `job_offer_id` BIGINT UNSIGNED NOT NULL, `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL, `accion` ENUM('borrar','reasignar') NOT NULL, `nuevo_catalog_id` BIGINT UNSIGNED NULL,
  `nuevo_market_entity_id` BIGINT UNSIGNED NULL, `motivo` VARCHAR(100) NULL,
  `respaldado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `restaurado_en` TIMESTAMP NULL)
  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT IGNORE INTO `{$pivot}` (id, `{$fk}`, market_entity_id, job_offer_id, created_at, updated_at)
SELECT b.id, b.`{$fk}`, b.market_entity_id, b.job_offer_id, b.created_at, b.updated_at
  FROM `{$bak}` b
 WHERE b.accion = 'borrar' AND b.restaurado_en IS NULL;
SELECT CONCAT('Filas borradas restauradas: ', ROW_COUNT()) AS resultado;

UPDATE IGNORE `{$pivot}` p
  JOIN `{$bak}` b ON b.id = p.id AND b.accion = 'reasignar' AND b.restaurado_en IS NULL
   SET p.`{$fk}` = b.`{$fk}`, p.market_entity_id = b.market_entity_id, p.updated_at = NOW();
SELECT CONCAT('Filas reasignadas devueltas: ', ROW_COUNT()) AS resultado;

SELECT COUNT(*) AS no_restauradas_oferta_borrada
  FROM `{$bak}` b LEFT JOIN `{$pivot}` p ON p.id = b.id
 WHERE b.restaurado_en IS NULL AND p.id IS NULL;

UPDATE `{$bak}` b JOIN `{$pivot}` p ON p.id = b.id SET b.restaurado_en = NOW() WHERE b.restaurado_en IS NULL;

SET @hay_trabajo = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$work}');
SET @sql = IF(@hay_trabajo > 0, 'UPDATE `{$work}` SET aplicado = 0', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT 'OK {$n3}: limpieza revertida; ahora php artisan ranking:refresh-{$type}' AS resultado;

SQL;

file_put_contents("{$dir}/{$n1}_limpieza_preparar.sql", $prep);
file_put_contents("{$dir}/{$n2}_limpieza_aplicar.sql", $apply);
file_put_contents("{$dir}/{$n3}_limpieza_rollback.sql", $rollback);
echo "Generados {$n1}, {$n2} y {$n3} en {$dir}\n";
