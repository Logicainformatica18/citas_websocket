# GLPI — Clasificación con IA de ofertas y tendencias (QA y PROD)

**Rama:** `observer`. **Requisito:** desplegado antes `docs/produccion/GLPI_DESPLIEGUE_FINAL.md` (exclusión por título,
`indicator_facts`, `indicators:refresh-all`).

**Qué lleva:**
1. Estado único de la oferta para los indicadores (`job_offers.ti_status`: TI / no TI / dudosa / pendiente / en proceso)
   y de los reportes de tendencia (`entity_trends.relevance_status`). Las ofertas ya excluidas por título pasan a "no TI"
   (origen keyword): **los indicadores no cambian** al desplegar.
2. Comandos `ai:classify` (lo nuevo, en `jobs:run-all`), `ai:classify-history` (histórico con la Batch API de OpenAI)
   y `ai:prompt-test`.
3. Módulo "Clasificación con IA" (menú Datos, administradores): resumen, prompts y versiones, probar versión,
   revisión manual. Insignias y filtros en Empleos y en Entity Trends.
4. Se retiran `trends:discover-languages/-technologies/-methodologies` (generaban reportes inventados sin búsqueda web).

Mismos pasos en **QA** y luego en **PROD**. Anotar los tiempos en QA.

---

## ⚠️ Importante

- **NUNCA ejecutar `php artisan migrate` sin `--path`** (ver GLPI anterior). Solo el comando del paso 4.
- **No ejecutar `ai:classify-history --submit`** hasta que el área usuaria confirme el costo estimado del paso 9.
- No correr `jobs:run-all` ni los scrapers entre los pasos 2 y 8.
- El filtro de tendencias en los rankings queda **apagado**; se activa solo cuando el área usuaria lo pida (paso 12).

---

## DBA

1. Respaldo de la base (`mysqldump --single-transaction`). Las migraciones no borran datos; agregan columnas (INSTANT),
   índices (INPLACE, sin bloqueo) y 9 tablas nuevas `ai_*`. Permisos del usuario de la aplicación: `CREATE`, `ALTER`,
   `INDEX` (los mismos del GLPI anterior).

## Operador (servidor de la aplicación)

2. Detener `jobs:run-all` y los scrapers.
3. Desplegar el código (`git pull` de `observer`) y compilar el frontend (`npm ci && npm run build`, si el servidor lo
   compila; si no, subir `public/build`).
4. Migraciones (en QA local: 4 min con 403 881 ofertas):
   ```sh
   symfony php artisan migrate:status
   symfony php artisan migrate --path=database/migrations/2026_11_25_000004_add_ti_status_to_job_offers.php --path=database/migrations/2026_11_25_000005_add_relevance_to_entity_trends.php --path=database/migrations/2026_11_25_000006_create_ai_classification_tables.php --force
   ```
5. ```sh
   symfony php artisan optimize:clear
   symfony php artisan config:cache
   symfony php artisan queue:restart
   ```
6. Comprobar que nada cambió en los indicadores (cada uno debe decir "Ofertas no TI: IGNORADAS" y "Reportes de tendencia:
   se cuentan todos"):
   ```sh
   symfony php artisan ranking:refresh-languages
   symfony php artisan ranking:refresh-technologies
   symfony php artisan ranking:refresh-certifications
   ```
7. Prueba corta de la clasificación de lo nuevo (llama a la IA; unos centavos de dólar):
   ```sh
   symfony php artisan ai:classify --dry-run
   symfony php artisan ai:classify --limit=100
   ```
8. Reanudar `jobs:run-all` y los scrapers. `jobs:run-all` ya incluye `ai:classify-history --collect` y `ai:classify`
   (después de `job-offers:apply-title-exclusions` y antes de los `ranking:refresh-*`).
9. Costo estimado del histórico (no envía nada) → **enviar la salida al área usuaria**:
   ```sh
   symfony php artisan ai:classify-history --dry-run
   ```
10. **Solo con la confirmación del área usuaria**, enviar el histórico (una vez; queda "en proceso" y no se envía dos
    veces):
    ```sh
    symfony php artisan ai:classify-history --submit
    ```
    OpenAI responde en menos de 24 h. `jobs:run-all` recoge los resultados (`--collect`) y sigue enviando lo que falte
    (segunda pasada y LinkedIn al final). Para recoger antes, a mano: `symfony php artisan ai:classify-history --collect`.
    Si un lote falla por el límite de tokens en cola de la cuenta, sus elementos vuelven a pendiente y se reenvían en la
    siguiente recolección; se puede bajar el tamaño: `--max-requests=500 --max-in-flight=1`.
11. Crons a quitar si estuvieran fuera del repositorio: `trends:discover-languages`, `trends:discover-technologies`,
    `trends:discover-methodologies` (ahora no hacen nada).
12. (Más adelante, a pedido del área usuaria) activar el filtro de tendencias en los rankings:
    ```sql
    UPDATE ai_classification_settings SET trends_filter_enabled = 1, updated_by = 'GLPI', updated_at = NOW();
    ```
    y luego los tres `ranking:refresh-*`. Para volver: `trends_filter_enabled = 0` y los tres `ranking:refresh-*`.

## Verificación

- Módulo `/ai-classification`: el resumen muestra las ofertas "No TI" de origen keyword (las excluidas por título) y el
  resto "Pendiente"; la última corrida de `ai:classify` con su costo.
- `SELECT ti_status, ti_origin, COUNT(*) FROM job_offers GROUP BY 1, 2;` — no_ti/keyword = ofertas con
  `title_exclusion_id` no nulo.
- Log: `storage/logs/laravel.log`, líneas `[AI_CLASSIFY]`.

## Interruptor de emergencia

Módulo → Resumen → "Apagar" (o `UPDATE ai_classification_settings SET enabled = 0`): los comandos dejan de llamar a la
IA. Las decisiones ya tomadas no cambian. Para reprocesar lo clasificado con una versión del prompt:
`symfony php artisan ai:classify --kind=offers --reclassify-version=N` (lo nuevo) o
`symfony php artisan ai:classify-history --kind=offers --reclassify-version=N --submit` (histórico).

## Reversión

Desplegar el código anterior. Las columnas y tablas nuevas pueden quedarse (el código anterior no las usa), pero los
índices `*_excl` que usa ese código quedaron invisibles: hay que revertir las migraciones (vuelven a ser visibles; se
borran las columnas, índices y tablas `ai_*` nuevas, con lo clasificado):
```sh
symfony php artisan migrate:rollback --path=database/migrations/2026_11_25_000004_add_ti_status_to_job_offers.php --path=database/migrations/2026_11_25_000005_add_relevance_to_entity_trends.php --path=database/migrations/2026_11_25_000006_create_ai_classification_tables.php --force
```
Si se quiere conservar lo clasificado, en lugar del rollback basta con volver visibles los índices:
`ALTER TABLE job_offers ALTER INDEX idx_jo_pub_excl VISIBLE, ALTER INDEX idx_jo_created_excl VISIBLE, ALTER INDEX idx_jo_source_created_excl VISIBLE;`
