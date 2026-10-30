-- =====================================================================
-- 01_silabos_revertir.sql
-- BASE: la de silabos_v2. En local, silabo_2.
--
-- Revierte 01_silabos_aplicar.sql. Antes: OBSERVATORIO_ENABLED=false en el
-- .env de silabos_v2 (y `php artisan config:cache` si la configuración
-- está cacheada) y `php artisan queue:restart`. Con el flag en false los
-- envíos que queden en la cola `observatorio` terminan sin enviar; el
-- bloque 0 debe mostrar 0 jobs de envío. No hace falta detener el worker.
-- Si se despliega el código anterior, este paso es el último.
--
-- Selecciona la base como predeterminada y ejecuta con auto-commit y
-- "Stop on error" activos. No toca jobs ni failed_jobs, ni los sílabos:
-- la integración solo lee de ellos.
-- =====================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;


-- ---------------------------------------------------------------------
-- 0. Lo que se va a perder (solo lectura): el historial de envíos.
--    Jobs de envío que siguen en la cola (deberían ser 0: si no, esperar
--    a que el worker los consuma, lo que con el flag en false es inmediato).
-- ---------------------------------------------------------------------

SELECT estado, COUNT(*) AS envios FROM observatorio_envios GROUP BY estado;

SELECT COUNT(*) AS jobs_de_envio_en_cola FROM jobs WHERE payload LIKE '%EnviarSilaboObservatorio%';


-- ---------------------------------------------------------------------
-- 1. Quitar la tabla y sus índices.
-- ---------------------------------------------------------------------

DROP TABLE observatorio_envios;


-- ---------------------------------------------------------------------
-- 2. Quitar el registro de migrations. Esperado: 1 fila.
-- ---------------------------------------------------------------------

DELETE FROM migrations WHERE migration = '2026_10_28_000001_create_observatorio_envios_table';
