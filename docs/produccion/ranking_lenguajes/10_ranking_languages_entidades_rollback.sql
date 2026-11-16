-- =====================================================================
-- 10_ranking_languages_entidades_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 09_ranking_languages_entidades.sql con los valores guardados en
--   _respaldo_lenguajes_2026_11:
--     - reactiva Shell y Ada (y limpia su motivo y fecha);
--     - devuelve el slug de F# a 'f';
--     - quita el vínculo de languages Go/Rust/Scala y de las filas de
--       language_job que 09 completó;
--     - vuelve a habilitar GNU/Linux y CLI.
--   Solo toca filas que siguen con el valor que puso 09. Al final elimina la
--   tabla de respaldo.
--
-- REQUISITOS
--   MySQL 8.0+. No requiere desplegar otro código.
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s.
--
-- RE-EJECUCIÓN
--   Idempotente (si la tabla de respaldo no existe, no hace nada).
--
-- DESPUÉS
--   php artisan ranking:refresh-languages
-- =====================================================================

SET @existe = (SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = '_respaldo_lenguajes_2026_11');
SELECT IF(@existe > 0, 'Respaldo encontrado: se revierte', 'Sin respaldo: nada que revertir') AS resultado;

-- Si no existe, se crea vacía para que las sentencias siguientes no fallen; se borra al final.
CREATE TABLE IF NOT EXISTS `_respaldo_lenguajes_2026_11` (
  `tabla`          VARCHAR(64)  NOT NULL,
  `fila_id`        BIGINT UNSIGNED NOT NULL,
  `columna`        VARCHAR(64)  NOT NULL,
  `valor_anterior` VARCHAR(255) NULL,
  `respaldado_en`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabla`, `fila_id`, `columna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

UPDATE market_entities me
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'is_active'
   SET me.is_active = r.valor_anterior, me.inactive_reason = NULL, me.inactive_at = NULL
 WHERE me.is_active = 0 AND me.inactive_reason LIKE '[Lenguajes 2026-11]%';
SELECT CONCAT('Entidades reactivadas: ', ROW_COUNT()) AS resultado;

UPDATE market_entities me
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'market_entities' AND r.fila_id = me.id AND r.columna = 'slug'
   SET me.slug = r.valor_anterior
 WHERE me.slug = 'fsharp';
SELECT CONCAT('Slug de F# restaurado: ', ROW_COUNT()) AS resultado;

UPDATE language_job lj
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'language_job' AND r.fila_id = lj.id AND r.columna = 'market_entity_id'
   SET lj.market_entity_id = NULL;
SELECT CONCAT('language_job desvinculadas: ', ROW_COUNT()) AS resultado;

UPDATE languages l
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'languages' AND r.fila_id = l.id AND r.columna = 'market_entity_id'
   SET l.market_entity_id = NULL;
SELECT CONCAT('languages desvinculados: ', ROW_COUNT()) AS resultado;

UPDATE languages l
  JOIN `_respaldo_lenguajes_2026_11` r ON r.tabla = 'languages' AND r.fila_id = l.id AND r.columna = 'enabled'
   SET l.enabled = r.valor_anterior, l.updated_at = NOW()
 WHERE l.enabled = 0;
SELECT CONCAT('languages rehabilitados: ', ROW_COUNT()) AS resultado;

COMMIT;

DROP TABLE IF EXISTS `_respaldo_lenguajes_2026_11`;
SELECT 'OK 10: reversión de 09 terminada' AS resultado;
