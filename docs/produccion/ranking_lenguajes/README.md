# Ranking de Lenguajes: despliegue en producción

> **Producción:** al DBA se le entrega solo `docs/produccion/DESPLIEGUE_RANKINGS_PRODUCCION.sql`, que consolida los
> scripts necesarios de esta carpeta (ver `PLAN_RANKINGS.md`, sección 6). Los scripts de aquí siguen siendo la fuente
> del consolidado y sirven para aplicar o revertir partes sueltas. **La limpieza de asociaciones queda fuera del
> despliegue.**
> En su lugar, los rankings ignoran sin borrar las asociaciones que el matcher no confirma
> (`php artisan entities:exclude-associations`; ver `docs/produccion/ranking_comun/README.md`). Sin cambios en el `.env`.

La página `/dashboard/ranking/languages` ya no calcula el ranking en vivo (35-60 s, superaba el límite de 30 s).
Lee de las tablas de resumen `language_rankings` y `language_ranking_meta`, que llena el comando
`php artisan ranking:refresh-languages`.

En producción no se usan migraciones de Laravel: el DBA aplica estos scripts.
(Las migraciones `2026_11_12_000001/000002` son idempotentes: si alguien ejecuta `migrate` después, no hacen nada.)

## Scripts para el DBA (en este orden)

| # | Script | Qué hace | Tiempo (local) | Bloqueo |
|---|---|---|---|---|
| 0 | — | Respaldo de `language_job`, `job_offers`, `scraper_runs` (o de la base completa) | — | — |
| 1 | `01_ranking_languages_tablas.sql` | Crea las 2 tablas del ranking (y `cache`, `cache_locks`, `jobs`, `failed_jobs` si faltaran) | < 1 s | Ninguno |
| 2 | `02_ranking_languages_indices.sql` | 4-5 índices en `language_job`, `job_offers`, `scraper_runs` | ~25 s | En línea (`LOCK=NONE`) |
| 3 | `04_ranking_languages_verificacion.sql` | Solo lectura: confirma tablas, índices y plan de ejecución | < 1 s | Ninguno |

- Requiere **MySQL 8.0+**.
- Ejecutar el 02 cuando no esté corriendo `jobs:run-all` ni un scraper (un ALTER espera a las transacciones largas).
- 01 y 02 se pueden ejecutar dos veces sin error (verificado).
- Reversión: `03_ranking_languages_rollback.sql` (desplegar antes el código anterior).

## Después de desplegar el código (en el servidor de la aplicación)

```sh
php artisan optimize:clear            # o config:clear + route:clear + view:clear
php artisan ranking:refresh-languages # primera carga: ~20-30 s
php artisan queue:restart             # para que el worker cargue el Job nuevo
```

Comprobar:

```sh
php artisan schedule:list   # debe aparecer: 0 7 * * *  php artisan ranking:refresh-languages
```

y abrir `/dashboard/ranking/languages`: debe cargar en menos de 1 s y mostrar
"Actualizado: dd/mm/aaaa hh:mm (hora de Perú)".

## Requisitos de operación

| Qué | Para qué | Si falta |
|---|---|---|
| Worker de colas: `php artisan queue:work --sleep=3 --tries=3 --timeout=170` (sección 5 de `PASOS_PRODUCCION.md`) | Botón "Recalcular ranking" (encola `RefreshLanguageRankingJob`) | El botón queda "en cola" y a los 3 min avisa que el worker no lo tomó. El ranking diario sigue funcionando. |
| Cron `* * * * * cd /ruta/app && php artisan schedule:run >> /dev/null 2>&1` | Respaldo diario a las 07:00 (America/Lima) | Solo se recalcula al final de `jobs:run-all` o con el botón. |
| `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | Lock del recálculo, caché de "Datos Generales", cola | — |

Cuándo se recalcula:
1. Al final de `jobs:run-all` (último comando de la lista).
2. Todos los días a las 07:00 hora de Perú (scheduler de Laravel).
3. Con el botón "Recalcular ranking" (solo usuarios con permiso `administrar`, máximo 3 intentos por minuto).

Nunca corren dos a la vez: comando, Job y botón comparten `Cache::lock('ranking-languages-refresh')`.
Si ya hay uno en curso, el comando termina avisando (sin error) y el botón responde "Ya hay un recálculo en curso".

## Fase A: lenguajes activos y evolución por ventana

Scripts (después de 01-04, en este orden):

| # | Script | Qué hace | Tiempo | Bloqueo |
|---|---|---|---|---|
| 5 | `05_ranking_languages_activos.sql` | `market_entities.is_active` / `inactive_reason` / `inactive_at` + índice; IDC y OECD quedan inactivas | < 2 s | INSTANT / en línea |
| 6 | `06_ranking_languages_evolucion.sql` | Tablas `language_ranking_weekly` y `language_ranking_weekly_meta`: foto del ranking semestral en el corte de cada semana ISO, quincena y mes. Si existen con la estructura anterior (sin `cutoff_date`), las recrea (solo tienen datos calculados) | < 1 s | Ninguno |
| 8 | `08_ranking_languages_verificacion_fase_a.sql` | Solo lectura (las consultas 4-6, después de la carga) | < 1 s | Ninguno |

Reversión: `07_ranking_languages_rollback_fase_a.sql` (desplegar antes el código anterior; muestra en pantalla
las entidades desactivadas antes de borrar las columnas).

Después de desplegar el código:

```sh
php artisan optimize:clear
php artisan ranking:refresh-languages   # recalcula ranking + evolución (~25 s la primera vez)
```

**Cron a desactivar:** `languages:take-snapshot` (y `languages:backfill-all` si estuviera programado).
No están en el repositorio: deben estar en el crontab del servidor (`crontab -l`) o en el panel de tareas.
Por los datos de `language_evolution_cache`, corría cada fin de semana (sábado/domingo de madrugada).
El comando ya no hace nada sin `--legacy`, así que dejarlo programado no rompe nada, pero conviene quitarlo.
La tabla `language_evolution_cache` ya no la lee nadie; no se borra.

Lenguaje activo = `market_entities.is_active = 1`. Lo respetan el ranking, la evolución, el export y los 14
scrapers de lenguajes. Desde la administración de lenguajes, "eliminar" y el interruptor desactivan
(o reactivan) también la entidad, sin borrar ofertas.

## Logs

`storage/logs/laravel.log`, prefijo `[LANGUAGE_RANKING]`: inicio, cada periodo (filas, vacantes, reportes, duración),
fin y errores. Si un periodo falla se revierte solo ese y conserva sus datos anteriores.

## Entidades de lenguajes (decisiones de la Fase A, 2026-11-16)

| # | Script | Qué hace | Tiempo | Bloqueo |
|---|---|---|---|---|
| 9 | `09_ranking_languages_entidades.sql` | Desactiva Shell y Ada (con motivo), corrige el slug de F# (`f` → `fsharp`), vincula `languages` Go/Rust/Scala con sus entidades (y completa sus filas de `language_job` sin entidad), y deshabilita GNU/Linux y CLI (`enabled = 0`, sin borrar: tienen cursos). Guarda cada valor anterior en `_respaldo_lenguajes_2026_11` | < 1 s | Filas |

- Requiere `05`. Idempotente (probado: aplicar dos veces, revertir, revertir otra vez, aplicar de nuevo).
- Reversión: `10_ranking_languages_entidades_rollback.sql` (restaura desde la tabla de respaldo y la elimina).
- Después: `php artisan ranking:refresh-languages`.
- `market_entities.slug` no se usa en URLs ni en otras tablas; solo lo usa `languages:discover-gaps` para evitar
  duplicados, y ese comando también compara por nombre.

## Fase B: matcher de entidades (2026-11-16)

| # | Script | Qué hace | Tiempo | Bloqueo |
|---|---|---|---|---|
| 11 | `11_matcher_aliases.sql` | Crea `market_entity_aliases` (reglas del matcher, común a todos los indicadores), carga 298 reglas de lenguajes y crea las filas de `languages` de Perl, Ruby, VBA, Lua, COBOL y F# | < 1 s | Ninguno |

- Requiere `09`. Idempotente (probado: aplicar dos veces, revertir, volver a aplicar).
- Reversión: `12_matcher_aliases_rollback.sql` (elimina la tabla y deshabilita las 6 filas creadas; no las borra).
- El bloque de reglas se genera con `docs/produccion/herramientas/generar_aliases_sql.php languages language lenguajes`
  desde `database/data/entity_aliases/languages.php`: no editar a mano.
- Desde este despliegue los 14 scrapers de lenguajes solo asocian una oferta si el matcher confirma el lenguaje en
  su texto, y escriben `language_job.market_entity_id` al insertar.
- Auditoría (solo lectura): `php artisan entities:match-audit --type=languages --semester=2026-s2`.

## Fase C: limpieza de language_job (2026-11-16)

> **Producción: la limpieza de asociaciones NO forma parte del despliegue** (decisión del 2026-11-16).
> No se ejecutan `entities:clean-associations` ni los scripts `13`, `14` y `15`. Se conservan en el
> repositorio por si se decide aplicarla más adelante (probados en local, con respaldo y reversión).
> Consecuencia: las asociaciones históricas no confirmadas siguen contando en el ranking; desde el despliegue,
> los scrapers solo agregan asociaciones que el matcher confirma.

| # | Script / comando | Qué hace | Tiempo (local) | Bloqueo |
|---|---|---|---|---|
| 0 | — | **Respaldo de `language_job`** (además del respaldo propio del script 14) | — | — |
| 13 | `13_limpieza_preparar.sql` | Crea `language_job_limpieza` (trabajo) y `language_job_respaldo_limpieza` (respaldo) | < 1 s | Ninguno |
| — | `php artisan entities:clean-associations --type=languages --dry-run` | Informe por fuente y lenguaje (solo lectura) | ~20 s | Ninguno |
| — | `php artisan entities:clean-associations --type=languages` | Llena la tabla de trabajo (no toca `language_job`) | ~30 s | Ninguno |
| 14 | `14_limpieza_aplicar.sql` | Respalda y borra en lotes de 5 000, cada lote en su transacción. **Usa DELIMITER: ejecutar con el cliente `mysql`** | ~30 s (233 507 filas) | Filas, por lote |
| — | `php artisan ranking:refresh-languages` | Recalcula | ~5 s | — |

- Reversión: `15_limpieza_rollback.sql` (restaura desde el respaldo con los ids originales). Probado:
  aplicar → revertir → aplicar → revertir dos veces → aplicar dos veces.
- Criterio: se borra la fila si su lenguaje está activo y el matcher no lo confirma en el texto guardado (incluidas
  las ofertas sin texto o con solo un extracto). Se conservan las filas de lenguajes inactivos y las sin entidad.
- Resultado local: 267 926 → 34 419 filas. R en 2026-s2: 8 979 → 163 vacantes.
- Los tres scripts se generan con `docs/produccion/herramientas/generar_limpieza_sql.php` (misma plantilla para
  tecnologías y certificaciones).
