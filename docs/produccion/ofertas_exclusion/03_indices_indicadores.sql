-- =====================================================================
-- 03_indices_indicadores.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Agrega a job_offers los índices que usan los indicadores de análisis
--   al ignorar las ofertas excluidas por título (title_exclusion_id IS NULL,
--   criterio común App\Services\Indicators\IndicatorScope). Sin ellos, los
--   indicadores dan el mismo resultado pero más lentos.
--   Equivale a la migración
--   2026_11_24_000001_add_indicator_indexes_to_job_offers.php
--
-- REQUISITO
--   Haber ejecutado antes 01_exclusion_titulos.sql (columna title_exclusion_id).
--   Si la columna no existe, el script no hace nada.
--
-- TIEMPO
--   ~5 s por índice con ~400 000 ofertas (ALGORITHM=INPLACE, LOCK=NONE: no
--   bloquea lecturas ni escrituras).
--
-- RE-EJECUCIÓN
--   Idempotente: solo crea los índices que faltan. Sin DELETE, TRUNCATE ni DROP.
--   Reversión: 04_indices_indicadores_rollback.sql
-- =====================================================================

SET @col = (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND column_name = 'title_exclusion_id');

-- Datos Generales: conteo y última inserción por fuente
SET @idx = 'idx_jo_source_created_excl';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@col > 0 AND @existe = 0,
    'ALTER TABLE `job_offers` ADD INDEX `idx_jo_source_created_excl` (`source`, `created_at`, `title_exclusion_id`), ALGORITHM=INPLACE, LOCK=NONE',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Datos Generales: ofertas del mes y del semestre por fecha de alta
SET @idx = 'idx_jo_created_excl';
SET @existe = (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'job_offers' AND index_name = @idx);
SET @sql = IF(@col > 0 AND @existe = 0,
    'ALTER TABLE `job_offers` ADD INDEX `idx_jo_created_excl` (`created_at`, `title_exclusion_id`), ALGORITHM=INPLACE, LOCK=NONE',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verificación: cada línea indica el valor esperado entre corchetes
SELECT CONCAT('title_exclusion_id existe [1]: ', @col) AS verificacion
UNION ALL
SELECT CONCAT('índices nuevos presentes [2]: ', COUNT(DISTINCT index_name))
  FROM information_schema.statistics
 WHERE table_schema = DATABASE() AND table_name = 'job_offers'
   AND index_name IN ('idx_jo_source_created_excl', 'idx_jo_created_excl');
