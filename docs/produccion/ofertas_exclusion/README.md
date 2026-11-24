# Exclusión de ofertas por título (roles que no son de TI)

Las ofertas cuyo **título** corresponde a un rol que no es de TI (comercial, marketing, call center, salud…) no cuentan
en los indicadores de Lenguajes, Tecnologías y Certificaciones ni en su evolución, aunque sus requisitos mencionen
Java u otra tecnología. Criterio: el de `docs/produccion/herramientas/exportar_periodos_ti.py` (título en minúsculas y
sin tildes; la semilla son sus `PATRONES_EXCLUIR`).

- **Ofertas nuevas**: los scrapers no las guardan (`JobOffer::creating`); el log registra cuántas descartó cada scraper
  (`[TITLE_EXCLUSION] Ofertas nuevas descartadas por título`), y la salida de `jobs:run-all` también lo muestra.
- **Ofertas existentes**: no se borran. `php artisan job-offers:apply-title-exclusions` las marca en
  `job_offers.title_exclusion_id` y `EntityRankingService` las ignora (rankings, evolución y vacantes analizadas).
- **Administración**: `/job-offers/title-exclusions` (lista, ejemplos, "Sugerir con IA", "Analizar basura",
  "Excluir de los indicadores", "Eliminar definitivamente" con respaldo y su restauración). Todo en la cola por defecto.

| Script | Qué hace |
|---|---|
| `01_exclusion_titulos.sql` | Crea las 4 tablas, carga la semilla (116 patrones), agrega `job_offers.title_exclusion_id` y 8 índices (2 de la exclusión y 6 para los combos del listado, que pasa de ~11 s a ~2 s). Idempotente; sin DELETE, TRUNCATE ni DROP. **Generado** con `herramientas/generar_exclusion_titulos_sql.php` |
| `02_exclusion_titulos_rollback.sql` | Revierte lo anterior. `job_offer_deletion_backups` solo se elimina si está vacía |
| `03_indices_indicadores.sql` | Índices para que todos los indicadores ignoren las excluidas sin recorrer la tabla (`idx_jo_source_created_excl`, `idx_jo_created_excl`) y deja INVISIBLE `idx_jo_title_excl`. Idempotente; sin DELETE, TRUNCATE ni DROP. Equivale a la migración `2026_11_24_000001` |
| `04_indices_indicadores_rollback.sql` | Revierte `03` |

Equivale a la migración (solo esa):

```sh
php artisan migrate --path=database/migrations/2026_11_23_000001_create_job_title_exclusions.php
```

## Pasos en producción

**DBA**
1. Coordinar con el operador que no corran `jobs:run-all` ni los scrapers durante el paso 2 (cambia `job_offers`).
2. Ejecutar `01_exclusion_titulos.sql` en la base del Observatorio. En local (~400 000 ofertas) tardó 80 s: 43 s la
   columna de `job_offers` y 3-5 s cada índice. Re-ejecutarlo no cambia nada (15 s).
3. Revisar la verificación del final (cada línea indica el valor esperado entre corchetes) y avisar al operador.

**Operador** (servidor de la aplicación; con el prefijo `symfony` como en el resto de comandos del servidor)
```sh
symfony php artisan optimize:clear
symfony php artisan queue:restart                          # el worker carga el código nuevo (no cambia su configuración)
symfony php artisan job-offers:apply-title-exclusions --dry-run   # cuántas excluiría, por categoría (no escribe)
symfony php artisan job-offers:apply-title-exclusions      # marca las ofertas (~2 min con 400 000)
symfony php artisan ranking:refresh-languages
symfony php artisan ranking:refresh-technologies
symfony php artisan ranking:refresh-certifications         # cada uno imprime "Ofertas excluidas por título: IGNORADAS"
```
Después, reanudar `jobs:run-all` (ya incluye `job-offers:apply-title-exclusions` antes de los `ranking:refresh-*`).

**Mantenimiento**: cuando cambie la lista, "Excluir de los indicadores" en la pantalla (en cola: marca y recalcula
los tres rankings, un Job por indicador y semestre) o `job-offers:apply-title-exclusions --refresh` en consola.
**Apagado de emergencia**: `job-offers:apply-title-exclusions --clear` (quita todas las marcas) y los `ranking:refresh-*`;
los scrapers siguen descartando títulos con patrones activos, así que también conviene desactivarlos en la pantalla.
**Revertir una eliminación**: botón "Restaurar" o `php artisan job-offers:restore-deleted <id del proceso>` (sin id,
lista las eliminaciones con respaldo).

## Colas
Ninguna cola nueva: todo usa la cola por defecto que ya atiende el worker
(`queue:work --sleep=3 --tries=3 --timeout=170`). Cada Job trabaja como máximo 60 s y encola el siguiente tramo
(`config/job_title_exclusions.php` → `slice_seconds`), por debajo del `--timeout=170` y del `retry_after = 90` de la cola
`database`. Los recálculos de rankings lanzados desde la pantalla van un Job por indicador y semestre.

## Todos los indicadores (2026-11-24)
Los indicadores de análisis (Datos Generales, Empresas, Modalidad, Seniority, Carreras, Geo, Alineamiento PE, CCTC,
modales de los rankings, tendencias y demanda por ciudad) usan el criterio común `App\Services\Indicators\IndicatorScope`:
sin ofertas excluidas por título y, donde cuentan entidades, sin asociaciones excluidas por el detector ni de entidades
inactivas. Las vistas operativas (job-offers, fuentes, scraping) siguen mostrando todo. Detalle y mediciones en
`PLAN_RANKINGS.md`, Bloque 8.

**DBA**: `03_indices_indicadores.sql` (~10 s; no bloquea).

**Operador** (después de desplegar el código):
```sh
symfony php artisan optimize:clear
symfony php artisan queue:restart
symfony php artisan indicators:curricular-demand        # PE y CCTC (~30 s)
symfony php artisan seniority:backfill-all 2026         # ~5 s
symfony php artisan companies:backfill-all 2026         # ~90 s
symfony php artisan careers:backfill-all 2026           # ~90 s
symfony php artisan jobs:build-alignment --truncate     # ~4 min (Geo: ciudades por carrera)
symfony php artisan pe:recalculate <carrera> --year=2026  # por cada carrera activa (o botón "Actualizar" de PE)
```
`jobs:run-all` ya incluye `indicators:curricular-demand` y los tres `*:backfill-all` después de
`job-offers:apply-title-exclusions`. Después de cada `entities:exclude-associations`, ejecutar también
`jobs:build-alignment --truncate` e `indicators:curricular-demand`.
