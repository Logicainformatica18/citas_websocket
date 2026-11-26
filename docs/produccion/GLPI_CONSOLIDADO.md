# GLPI consolidado — Despliegue del Observatorio en PRODUCCIÓN (QA y PROD)

**Reemplaza** a `GLPI_DESPLIEGUE_FINAL.md` y a `clasificacion_ia/GLPI_CLASIFICACION_IA.md` (quedan como detalle técnico).
**Probado** el 2026-11-26 sobre una copia de la base de producción del 2026-11-25 (`observatorio_3`, 418 451 ofertas), en el
orden de este documento. Los tiempos son de esa prueba con MySQL local y `innodb_buffer_pool_size` = 128 MB: con 4 GB
deberían ser bastante menores.

**Estado de producción antes de este despliegue** (verificado en la copia): ya están aplicados
`DESPLIEGUE_RANKINGS_PRODUCCION.sql` (rankings, entidades activas, reglas del matcher, tablas de exclusiones) y la primera
ejecución de `entities:exclude-associations` (fecha de confianza 2026-11-23 15:42:35). Falta todo lo demás.

**Qué lleva:**
1. Funciones del puesto en `job_offers` (`functions`, `functions_headline`, `functions_origin`).
2. Exclusión de ofertas por título (roles que no son de TI) e índices de los indicadores.
3. Tablas resumen de los indicadores (`indicator_facts`) y su precálculo diario (`indicators:refresh-all`); alineamiento
   por menciones e índices de filtros.
4. Clasificación con IA: estado único de la oferta (`job_offers.ti_status`) y de los reportes de tendencia
   (`entity_trends.relevance_status`), comandos `ai:*` y el módulo "Clasificación con IA". Se retiran
   `trends:discover-languages/-technologies/-methodologies`.

Mismos pasos en **QA** y luego en **PROD**. Anotar los tiempos en QA.

---

## ⚠️ Importante

- **NUNCA ejecutar `php artisan migrate` sin `--path`.** `migrate:status` muestra como pendientes unas 23 migraciones
  antiguas que NO se ejecutan (las `2025_*` y las `2026_11_12_*` / `2026_11_16_*`, que ya aplicó el SQL de rankings con
  otra forma). Ejecutar SOLO el comando del paso 5.
- Las migraciones reconstruyen `job_offers` (tiene un índice FULLTEXT): **bloquean escrituras unos minutos**. Los scrapers
  deben estar detenidos.
- **No ejecutar `ai:classify-history --submit`** hasta que el área usuaria confirme el costo del paso 14.
- No correr `jobs:run-all` ni los scrapers desde el paso 3 hasta el paso 16.
- No usar `php artisan optimize` (falla `route:cache` por dos rutas `logout`; ver paso 6).

---

## DBA (antes del despliegue)

1. **Permisos** del usuario de la aplicación sobre la base del Observatorio: `CREATE`, `ALTER`, `INDEX` (además de
   `SELECT`, `INSERT`, `UPDATE`, `DELETE`). `DROP` solo si hubiera que revertir.
   ```sql
   GRANT CREATE, ALTER, INDEX ON <base_observatorio>.* TO '<usuario_app>'@'<host>';
   ```
2. **`innodb_buffer_pool_size` de al menos 4 GB** (con 128 MB todo lee de disco y tarda varias veces más):
   ```sql
   SET PERSIST innodb_buffer_pool_size = 4294967296;
   SELECT @@innodb_buffer_pool_size / 1024 / 1024 AS mb;
   ```
3. **Respaldo** completo (`mysqldump --single-transaction`). Ningún paso borra datos.

---

## Operador (servidor de la aplicación, desde la carpeta del proyecto)

### Código

1. ```sh
   git pull origin observer
   ```
2. ```sh
   npm run build
   ```
3. Detener `jobs:run-all`, los scrapers y el cron/tareas programadas que los lanzan, hasta el paso 16.

### Migraciones

4. Revisar (solo lectura): deben figurar como **Pending** las 10 del paso 5, además de las antiguas que no se ejecutan.
   ```sh
   php artisan migrate:status
   ```
5. Ejecutar SOLO estas 10 migraciones, en un único comando (prueba: **5 min 43 s**):
   ```sh
   php artisan migrate --path=database/migrations/2026_11_17_000001_add_functions_to_job_offers_table.php --path=database/migrations/2026_11_17_000002_add_functions_origin_to_job_offers_table.php --path=database/migrations/2026_11_23_000001_create_job_title_exclusions.php --path=database/migrations/2026_11_24_000001_add_indicator_indexes_to_job_offers.php --path=database/migrations/2026_11_25_000001_create_indicator_facts.php --path=database/migrations/2026_11_25_000002_add_mentions_to_job_offer_alignment.php --path=database/migrations/2026_11_25_000003_add_filter_indexes_to_job_offers.php --path=database/migrations/2026_11_25_000004_add_ti_status_to_job_offers.php --path=database/migrations/2026_11_25_000005_add_relevance_to_entity_trends.php --path=database/migrations/2026_11_25_000006_create_ai_classification_tables.php --force
   ```

   | Migración | Qué hace | Prueba |
   |---|---|---|
   | `2026_11_17_000001_add_functions_to_job_offers_table` | `functions`, `functions_headline` en `job_offers` | 57 s |
   | `2026_11_17_000002_add_functions_origin_to_job_offers_table` | `functions_origin` (y la completa en GetOnBoard) | 35 s |
   | `2026_11_23_000001_create_job_title_exclusions` | Tablas de la exclusión por título, 116 patrones, `job_offers.title_exclusion_id` | 1 min 25 s |
   | `2026_11_24_000001_add_indicator_indexes_to_job_offers` | 2 índices de indicadores | 5 s |
   | `2026_11_25_000001_create_indicator_facts` | `indicator_facts`, `indicator_fact_runs` | < 1 s |
   | `2026_11_25_000002_add_mentions_to_job_offer_alignment` | `job_offer_alignment.mentions` | < 1 s |
   | `2026_11_25_000003_add_filter_indexes_to_job_offers` | 2 índices de filtros (en línea) | 14 s |
   | `2026_11_25_000004_add_ti_status_to_job_offers` | Estado único (`ti_status`, `ti_origin`, `title_key`…), índices nuevos; los `*_excl` quedan invisibles | 2 min 19 s |
   | `2026_11_25_000005_add_relevance_to_entity_trends` | Estado de los reportes de tendencia y `source_snippet` | 1 s |
   | `2026_11_25_000006_create_ai_classification_tables` | 9 tablas `ai_*`, prompts v1 activos, configuración | < 1 s |

   Idempotentes: si se ejecuta dos veces, la segunda dice "Nothing to migrate" (prueba: 3 s). Al terminar,
   `migrate:status` debe mostrar las 10 como **Ran**.

### Caché y colas

6. ```sh
   php artisan optimize:clear
   php artisan config:cache
   php artisan event:cache
   php artisan view:cache
   php artisan queue:restart
   ```
   (Prueba: 3-11 s cada uno.) Comprobar que el worker sigue con `php artisan queue:work --sleep=3 --tries=3 --timeout=170`.

### Exclusiones del detector (ya ejecutadas en producción: solo comprobar)

7. ```sh
   php artisan entities:exclusions
   ```
   Debe decir **ACTIVAS**, "Fecha de confianza 2026-11-23 15:42:35" y unas 252 729 / 1 569 786 / 13 827 vigentes
   (lenguajes / tecnologías / certificaciones). **Solo si dice que no hay fecha de confianza**, ejecutar
   `php artisan entities:exclude-associations` (prueba: 21 min; en la copia de producción no cambió nada porque ya se
   había ejecutado).

### Exclusión por título (roles que no son de TI)

8. Simulación (no escribe nada; prueba: 65 s):
   ```sh
   php artisan job-offers:apply-title-exclusions --dry-run
   ```
   Esperado: **60 752 de 418 451 ofertas (14,5 %)** con la copia del 2026-11-25 (crecerá con las ofertas nuevas). Si se
   aleja mucho, no seguir y avisar.
9. Aplicar (marca, no borra; también escribe el estado único `no_ti / keyword`; prueba: 3 min 13 s):
   ```sh
   php artisan job-offers:apply-title-exclusions
   ```

### Rankings

10. ```sh
    php artisan ranking:refresh-languages
    php artisan ranking:refresh-technologies
    php artisan ranking:refresh-certifications
    ```
    (Prueba: 19 s, 72 s y 23 s.) Cada uno debe imprimir "Exclusiones del matcher: APLICADAS", "Ofertas no TI: IGNORADAS" y
    "Reportes de tendencia: se cuentan todos".

### Demás indicadores (precálculo)

11. **Primera ejecución: 25-45 min, por consola, en horario sin uso** (reescribe `job_offer_alignment`):
    ```sh
    php artisan indicators:refresh-all
    ```
    Debe terminar con **"Listo: 114 pasos en … s"**. Si termina sin esa línea, volver a ejecutarlo (es idempotente): en la
    prueba la primera ejecución se cortó sin mensaje al empezar el paso 99 (evolución de Empresas) y la segunda terminó
    bien (114 pasos, 44 min, 98 MB). El paso que se cortó funciona solo (`php artisan companies:backfill-all 2026 --month=4`,
    84 s).
12. Comprobar el programador (los tres `ranking:refresh-*` e `indicators:refresh-all` a las 07:00) y que exista el cron del
    sistema `* * * * * php artisan schedule:run`:
    ```sh
    php artisan schedule:list
    ```

### Clasificación con IA

13. Prueba corta (prueba: 27 s y 15 s):
    ```sh
    php artisan ai:classify --dry-run
    php artisan ai:classify --limit=100
    ```
    Justo después del despliegue **procesan 0 elementos** (no hay nada "nuevo": lo existente es histórico). Es lo esperado.
    Lo nuevo se clasifica dentro de `jobs:run-all`.
14. Costo estimado del histórico (no envía nada; prueba: 44 s) → **enviar la salida al área usuaria**:
    ```sh
    php artisan ai:classify-history --dry-run
    ```
    Con la copia del 2026-11-25: ofertas sin LinkedIn **5,00 USD** (123 558 títulos), tendencias **2,33 USD** (59 821 a la
    IA y 35 690 por regla, gratis), LinkedIn **1,45 USD**: **≈ 8,8 USD** con la Batch API.

### Verificación y reanudación

15. Revisar en el navegador que cargan y muestran "Última actualización": Seniority, Demanda geográfica, Empresas,
    Modalidad, Ranking de carreras, Alineamiento PE, CCTC y los tres rankings; y el módulo Datos → "Clasificación con IA"
    (ofertas "No TI" de origen keyword = las excluidas por título, el resto "Pendiente").
    ```sql
    SELECT ti_status, ti_origin, COUNT(*) FROM job_offers GROUP BY 1, 2;
    SELECT SUM((title_exclusion_id IS NOT NULL) <> (ti_status = 'no_ti')) AS desajuste FROM job_offers;   -- 0
    ```
16. Reanudar `jobs:run-all` y los scrapers. `jobs:run-all` ya incluye, después de los scrapers:
    `job-offers:apply-title-exclusions`, `ai:classify-history --collect`, `ai:classify`, los tres `ranking:refresh-*` e
    `indicators:refresh-all`.

### Crons a quitar (crontab o panel de tareas)

- `languages:take-snapshot`, `technologies:take-snapshot`, `certifications:take-snapshot` y sus `*:backfill-all`.
- `companies:take-snapshot`, `careers:take-snapshot`, `seniority:take-snapshot`, `companies:backfill-all`,
  `careers:backfill-all`, `seniority:backfill-all`, `jobs:build-alignment`, `indicators:curricular-demand`.
- `trends:discover-languages`, `trends:discover-technologies`, `trends:discover-methodologies` (retirados: ya no hacen nada).
- Revisar que `greenhouse:technologies` no esté programado.

**Se mantienen:** `* * * * * php artisan schedule:run` y el worker `queue:work --sleep=3 --tries=3 --timeout=170`.

---

## Después, solo a pedido del área usuaria

17. **Enviar el histórico a la Batch API** (con la confirmación del costo del paso 14):
    ```sh
    php artisan ai:classify-history --submit
    ```
    Queda "en proceso" y no se envía dos veces. `jobs:run-all` recoge los resultados (`--collect`) y envía lo que falte
    (segunda pasada y LinkedIn al final). Recoger a mano: `php artisan ai:classify-history --collect`. Si un lote falla por
    el límite de tokens en cola de la cuenta, sus elementos vuelven a pendiente; bajar el tamaño con
    `--max-requests=500 --max-in-flight=1`.
18. **Activar el filtro de tendencias en los rankings** (después de revisar su efecto con el histórico clasificado):
    ```sql
    UPDATE ai_classification_settings SET trends_filter_enabled = 1, updated_by = 'GLPI', updated_at = NOW();
    ```
    y los tres `ranking:refresh-*`. Para volver: `trends_filter_enabled = 0` y los tres `ranking:refresh-*`.

## Interruptor de emergencia de la IA

Módulo → Resumen → "Apagar" (o `UPDATE ai_classification_settings SET enabled = 0;`): los comandos dejan de llamar a la
IA; las decisiones tomadas no cambian.

## Reversión (solo si hace falta)

- Desplegar el código anterior y revertir las migraciones de la más nueva a la más vieja, siempre con `--path`:
  ```sh
  php artisan migrate:rollback --path=database/migrations/2026_11_25_000006_create_ai_classification_tables.php --path=database/migrations/2026_11_25_000005_add_relevance_to_entity_trends.php --path=database/migrations/2026_11_25_000004_add_ti_status_to_job_offers.php --force
  ```
  (devuelve visibles los índices `*_excl` que usa el código anterior). Las demás, una por una con
  `php artisan migrate:rollback --path=database/migrations/<archivo> --force` o con el SQL de
  `indicadores_optimizacion/03_reversion.sql`, `ofertas_exclusion/04_indices_indicadores_rollback.sql` y
  `ofertas_exclusion/02_exclusion_titulos_rollback.sql`.
- Solo la exclusión por título: `php artisan job-offers:apply-title-exclusions --clear` y los tres `ranking:refresh-*`.
- Solo el detector: `php artisan entities:exclusions --disable` y los tres `ranking:refresh-*`.

Detalle técnico: `docs/produccion/PLAN_RANKINGS.md` (Bloques 6 a 10).
