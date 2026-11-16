-- =====================================================================
-- 08_ranking_technologies_carreras.sql
-- ---------------------------------------------------------------------
-- QUÉ HACE
--   Unifica la relación entidad↔carrera en market_entity_career (fuente única:
--   la escriben la administración de entidades y los comandos de descubrimiento).
--   Copia a market_entity_career los pares de TECNOLOGÍAS que solo estaban en
--   career_market_entity (481 en la base de desarrollo):
--     - solo pares cuya entidad existe y es de tipo 'technology' y cuya carrera
--       existe (en desarrollo, 38 filas de career_market_entity apuntan a
--       entidades que ya no existen: se ignoran);
--     - source = 'manual' (venían del mapeo curso↔tecnología),
--       relevance_score = career_market_entity.weight.
--   Registra cada par insertado en market_entity_career_migracion_cme para que
--   el rollback (09) borre exactamente esos y nada más.
--   NO borra ni modifica career_market_entity.
--
--   Después de desplegar el código, el filtro por carrera de Tecnologías usa
--   market_entity_career (config/entity_rankings.php → career_table).
--
-- REQUISITOS
--   MySQL 8.0+. Ejecutar ANTES de desplegar el código que lee market_entity_career
--   para Tecnologías (si se despliega antes, el filtro por carrera queda casi vacío
--   hasta que se aplique este script).
--
-- TIEMPO / BLOQUEOS
--   Menos de 1 s (cientos de filas). Bloqueo de fila breve.
--
-- RE-EJECUCIÓN
--   Idempotente: la segunda vez inserta 0 filas.
--
-- REVERSIÓN
--   09_ranking_technologies_carreras_rollback.sql
-- =====================================================================

-- 1. Tabla de seguimiento (qué pares agregó esta migración)
CREATE TABLE IF NOT EXISTS `market_entity_career_migracion_cme` (
  `market_entity_id` BIGINT UNSIGNED NOT NULL,
  `career_id`        BIGINT UNSIGNED NOT NULL,
  `migrated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`market_entity_id`, `career_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Pares de tecnologías que faltan en market_entity_career
INSERT IGNORE INTO `market_entity_career_migracion_cme` (`market_entity_id`, `career_id`)
SELECT DISTINCT c.market_entity_id, c.career_id
  FROM career_market_entity c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'technology'
  JOIN careers ca ON ca.id = c.career_id
 WHERE NOT EXISTS (SELECT 1 FROM market_entity_career m
                    WHERE m.market_entity_id = c.market_entity_id AND m.career_id = c.career_id);
SELECT CONCAT('Pares registrados para migrar en esta ejecución: ', ROW_COUNT()) AS resultado;

-- 3. Insertarlos en market_entity_career (solo los que todavía no están)
INSERT INTO market_entity_career (market_entity_id, career_id, relevance_score, source, created_at, updated_at)
SELECT t.market_entity_id, t.career_id,
       (SELECT MAX(c.weight) FROM career_market_entity c
         WHERE c.market_entity_id = t.market_entity_id AND c.career_id = t.career_id),
       'manual', NOW(), NOW()
  FROM market_entity_career_migracion_cme t
 WHERE NOT EXISTS (SELECT 1 FROM market_entity_career m
                    WHERE m.market_entity_id = t.market_entity_id AND m.career_id = t.career_id);
SELECT CONCAT('Pares insertados en market_entity_career en esta ejecución: ', ROW_COUNT()) AS resultado;

-- 4. Verificación. Esperado: 0 pares de tecnologías de career_market_entity sin copiar.
SELECT COUNT(*) AS pares_tecnologia_sin_copiar
  FROM career_market_entity c
  JOIN market_entities me ON me.id = c.market_entity_id AND me.entity_type = 'technology'
  JOIN careers ca ON ca.id = c.career_id
 WHERE NOT EXISTS (SELECT 1 FROM market_entity_career m
                    WHERE m.market_entity_id = c.market_entity_id AND m.career_id = c.career_id);

SELECT (SELECT COUNT(*) FROM market_entity_career_migracion_cme) AS pares_migrados_total,
       (SELECT COUNT(*) FROM market_entity_career m
          JOIN market_entities me ON me.id = m.market_entity_id AND me.entity_type = 'technology') AS pares_tecnologia_en_market_entity_career;

SELECT 'OK 08: carreras de tecnologías unificadas en market_entity_career' AS resultado;
