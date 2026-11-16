# Ranking de Tecnologías: despliegue en producción

La página `/dashboard/ranking/technologies` ya no calcula en vivo (tardaba 15-32 s y superaba el límite de 30 s).
Lee de `technology_rankings` / `technology_ranking_meta` y la evolución de `technology_ranking_weekly(_meta)`,
que llena `php artisan ranking:refresh-technologies`. Usa el mismo servicio genérico que Lenguajes
(`App\Services\Ranking\EntityRankingService`, configurado en `config/entity_rankings.php`).

En producción no se usan migraciones de Laravel: el DBA aplica estos scripts. (Las migraciones
`2026_11_12_000005/000006` son idempotentes: si alguien ejecuta `migrate` después, no hacen nada.)

## Scripts para el DBA (en este orden)

| # | Script | Qué hace | Tiempo (local) | Bloqueo |
|---|---|---|---|---|
| 0 | — | Respaldo de `technology_job` y `market_entities` | — | — |
| 1 | `05_ranking_technologies_activos.sql` | Columnas `market_entities.is_active` / `inactive_reason` / `inactive_at` (mismas que `ranking_lenguajes/05`; si ya se aplicó, todo sale "YA EXISTE") | < 2 s | INSTANT / en línea |
| 2 | `01_ranking_technologies_tablas.sql` | 4 tablas de resumen (+ `cache`, `cache_locks`, `jobs`, `failed_jobs` si faltaran) | < 1 s | Ninguno |
| 3 | `02_ranking_technologies_indices.sql` | `technology_job (job_offer_id, market_entity_id)` + los de `job_offers` / `scraper_runs` si faltan | ~35 s por 2,16 M filas | En línea (`LOCK=NONE`) |
| 4 | `08_ranking_technologies_carreras.sql` | Copia a `market_entity_career` (fuente única) los 481 pares tecnología↔carrera que solo estaban en `career_market_entity`. **Aplicar antes de desplegar el código** | < 1 s | Ninguno |
| 5 | `06_ranking_technologies_entidades.sql` | Desactiva 140 entidades según `entidades_propuesta.csv` (aprobado 2026-11-16) | < 1 s | Ninguno |
| 6 | `04_ranking_technologies_verificacion.sql` | Solo lectura (consultas 4-7 después de la carga) | 1-3 s | Ninguno |

- Requiere **MySQL 8.0+**. Ejecutar el 02 cuando no corra `jobs:run-all` ni un scraper.
- 01, 02, 05, 06 y 08 se pueden ejecutar dos veces sin error (verificado).
- Reversión: `03_ranking_technologies_rollback.sql` (tablas e índice de technology_job; desplegar antes el
  código anterior), `07_ranking_technologies_entidades_rollback.sql` (reactiva solo lo que desactivó el 06) y
  `09_ranking_technologies_carreras_rollback.sql` (borra solo los pares que agregó el 08; `career_market_entity`
  no se toca y sigue existiendo).
- Filtro por carrera tras el 08: mismas tecnologías y posiciones que antes en todas las carreras, más 30 pares
  (tecnologías descubiertas por IA) que ya estaban asignados en `market_entity_career` y el filtro anterior ignoraba.
- `innodb_buffer_pool_size`: en local es de 128 MB y technology_job (2,16 M filas) no cabe; el recálculo
  varía mucho (100-200 s completo). Conviene revisarlo en producción.

## Después de desplegar el código

```sh
php artisan optimize:clear
php artisan ranking:refresh-technologies   # primera carga: 2-3 min (6 semestres + evolución)
php artisan queue:restart                  # IMPORTANTE: los workers cargan el código nuevo
php artisan schedule:list                  # 0 7 * * *  ranking:refresh-technologies
```

(Si el 06 se aplica después de la primera carga, ejecutar `php artisan ranking:refresh-technologies` otra vez.)

**Crons a desactivar:** `technologies:take-snapshot` (y `technologies:backfill-all` si estuviera programado).
No están en el repositorio (crontab del servidor o panel de tareas). Por los datos de `technology_evolution_cache`,
corría los viernes y sábados por la noche (hora del servidor). Sin `--legacy` ya no hace nada.

## Operación

- Cuándo se recalcula: al final de `jobs:run-all` (después de Lenguajes), a las 07:00 America/Lima, y con el botón
  "Recalcular ranking" (administradores, 3 intentos por minuto). El botón recalcula **el semestre que se está viendo**
  (ranking + sus fotos de evolución, ~25-80 s); el recálculo completo lo hace el comando.
- `Cache::lock('ranking-technologies-refresh')`: nunca dos recálculos a la vez. Si el proceso muere (p. ej. lo mata
  el sistema), el lock se libera solo a los 15 min.
- Worker: el mismo de Lenguajes (`php artisan queue:work --sleep=3 --tries=3 --timeout=170`). **Tras cada despliegue,
  `php artisan queue:restart`**: un worker iniciado antes del despliegue tiene el código viejo y los jobs nuevos fallan.
- Logs: `storage/logs/laravel.log`, prefijo `[TECHNOLOGY_RANKING]`.
