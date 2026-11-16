-- =====================================================================
-- 02_ranking_certifications_indices.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Agrega índices para que el cálculo del Ranking de Certificaciones y el
--   panel "Datos Generales" (JobMarketStatus) no recorran tablas completas:
--
--   tabla         índice                              columnas
--   ------------  ----------------------------------  ----------------------------------
--   certification_job idx_cj_job_entity                  (job_offer_id, market_entity_id)
--   job_offers    idx_jo_published_at                 (published_at)
--   job_offers    idx_jo_created_at                   (created_at)
--   job_offers    idx_jo_source_created               (source, created_at)
--   scraper_runs  idx_scraper_runs_entity_finished    (entity, finished_at)
--
--   Antes de crear cada uno revisa information_schema y lo OMITE si:
--     - ya existe un índice con ese nombre, o
--     - ya existe otro índice que empieza por esas mismas columnas, o
--     - falta la tabla o alguna columna.
--   Cada paso imprime una fila "resultado" con lo que hizo.
--
-- REQUISITOS
--   MySQL 8.0 o superior. Ejecutar conectado a la base del observatorio.
--   No usa DELIMITER ni procedimientos: funciona en el cliente mysql,
--   Workbench, DBeaver, etc.
--
-- TIEMPO ESTIMADO (medido en local, MySQL 8.0.46, disco SSD)
--   Crece de forma aproximadamente lineal con el número de filas.
--   Revisa antes el tamaño en producción:
--     SELECT table_name, table_rows FROM information_schema.tables
--      WHERE table_schema = DATABASE()
--        AND table_name IN ('job_offers','certification_job','scraper_runs');
--
--   índice                            filas en local   tiempo local   ~por cada 100k filas
--   idx_cj_job_entity (certification_job) 31 500       < 1 s          < 1 s
--   job_offers / scraper_runs         los mismos del 02 de Lenguajes: si ya se aplicó,
--                                     salen "YA EXISTE" (si no: ~20 s en total)
--   Con el doble de filas, calcula ~70 s; en un servidor con disco más lento o
--   con carga, 2-3 veces más. Al ser en línea, la tabla sigue operativa.
--
-- BLOQUEOS
--   Se crean en línea: ALGORITHM=INPLACE, LOCK=NONE. La tabla sigue
--   aceptando lecturas y escrituras mientras se construye el índice; solo
--   hay un bloqueo de metadatos muy breve al inicio y al final. Si una
--   transacción larga está usando la tabla, el ALTER espera a que termine
--   (y las consultas nuevas esperan detrás del ALTER): ejecútalo cuando no
--   esté corriendo `jobs:run-all` ni un scraper.
--   Si MySQL respondiera "LOCK=NONE is not supported", NO se creó nada:
--   ese índice se puede crear en una ventana de mantenimiento cambiando
--   LOCK=NONE por LOCK=SHARED (permite lecturas, bloquea escrituras).
--
-- RE-EJECUCIÓN
--   Idempotente: la segunda vez todos los pasos dicen "YA EXISTE".
--
-- REVERSIÓN
--   03_ranking_certifications_rollback.sql
-- =====================================================================

-- Plantilla de cada paso:
--   @tbl  = tabla, @idx = nombre del índice, @cols = columnas separadas por coma
--   @existe   = índices con ese nombre o con las mismas columnas iniciales
--   @cols_ok  = 1 si la tabla y todas las columnas existen

-- ---------------------------------------------------------------------
-- 1. certification_job (job_offer_id, market_entity_id)
--    Join con job_offers y GROUP BY market_entity_id leyendo solo el índice.
-- ---------------------------------------------------------------------
SET @tbl = 'certification_job', @idx = 'idx_cj_job_entity', @cols = 'job_offer_id,market_entity_id';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 1 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 2. job_offers (published_at)
--    Rango por fecha de publicación. En la base de desarrollo ya existe
--    como idx_jo_published_at; si existe con otro nombre, se omite.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_published_at', @cols = 'published_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 2 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 3. job_offers (created_at)
--    JobMarketStatus: MIN/MAX(created_at), ofertas del mes y del periodo.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_created_at', @cols = 'created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 3 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 4. job_offers (source, created_at)
--    JobMarketStatus: COUNT(*) y MAX(created_at) por fuente.
-- ---------------------------------------------------------------------
SET @tbl = 'job_offers', @idx = 'idx_jo_source_created', @cols = 'source,created_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 4 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

-- ---------------------------------------------------------------------
-- 5. scraper_runs (entity, finished_at)
--    ScrapingStatusService: últimas ejecuciones por entidad.
-- ---------------------------------------------------------------------
SET @tbl = 'scraper_runs', @idx = 'idx_scraper_runs_entity_finished', @cols = 'entity,finished_at';

SET @existe = (
  SELECT COUNT(*) FROM (
    SELECT index_name AS idx_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS idx_cols
      FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = @tbl
     GROUP BY index_name
  ) x
  WHERE x.idx_name = @idx OR x.idx_cols = @cols OR x.idx_cols LIKE CONCAT(@cols, ',%')
);
SET @cols_ok = (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = @tbl AND FIND_IN_SET(column_name, @cols)
) = (LENGTH(@cols) - LENGTH(REPLACE(@cols, ',', '')) + 1);

SET @accion = CASE
  WHEN @cols_ok = 0 THEN 'OMITIDO (falta la tabla o una columna)'
  WHEN @existe > 0  THEN 'YA EXISTE (con ese nombre o uno equivalente)'
  ELSE 'CREADO'
END;
SET @sql = IF(@accion = 'CREADO',
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `', @idx, '` (`', REPLACE(@cols, ',', '`, `'), '`), ALGORITHM=INPLACE, LOCK=NONE'),
  'DO 0');
SET @t0 = NOW(6);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT CONCAT('Paso 5 - ', @tbl, '.', @idx, ': ', @accion,
              IF(@accion = 'CREADO', CONCAT(' en ', ROUND(TIMESTAMPDIFF(MICROSECOND, @t0, NOW(6)) / 1e6, 1), ' s'), '')) AS resultado;

SELECT 'OK 02: índices del ranking de certificaciones revisados' AS resultado;
