-- =====================================================================
-- 13_keywords_busqueda_certificaciones_rollback.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Deshace 12: devuelve certifications.keywords a su valor anterior desde
--   _respaldo_keywords_cert_2026_11 y elimina esa tabla.
--
-- TIEMPO / RE-EJECUCIÓN
--   Menos de 1 s. Idempotente (sin respaldo no hace nada).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `_respaldo_keywords_cert_2026_11` (
  `certification_id` BIGINT UNSIGNED NOT NULL,
  `keywords_anterior` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `respaldado_en`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`certification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE certifications c
  JOIN `_respaldo_keywords_cert_2026_11` r ON r.certification_id = c.id
   SET c.keywords = r.keywords_anterior, c.updated_at = NOW();
SELECT CONCAT('keywords restauradas: ', ROW_COUNT()) AS resultado;

DROP TABLE IF EXISTS `_respaldo_keywords_cert_2026_11`;
SELECT 'OK 13: reversión de 12 terminada' AS resultado;
