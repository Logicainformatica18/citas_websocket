# Optimización de los demás indicadores (2026-11-25)

Todos los indicadores de análisis **menos los tres rankings** (Lenguajes, Tecnologías, Certificaciones):
Seniority, Carreras (ranking y evolución), Demanda geográfica (carreras, ciudades, mapa de calor), Empresas (y su
evolución), Modalidad, Datos Generales, Demanda por ciudad, Alineamiento PE, CCTC y Tendencias.

- **Criterio**: solo se excluyen las ofertas por título (`job_offers.title_exclusion_id`). Las asociaciones
  oferta–entidad se usan completas (sin el filtro del detector ni el de entidades inactivas, que siguen solo en los
  rankings y sus modales).
- **Menciones**: donde se llega a una carrera por sus tecnologías (Seniority por carrera, evolución por carrera,
  demanda por carrera y ranking de ciudades de Geo), una oferta suma 1 por cada tecnología (o lenguaje/metodología en
  el ranking de ciudades) de los cursos de la carrera. La pantalla dice "menciones".
- **Precálculo**: las páginas leen tablas resumen (`indicator_facts`) y muestran la última actualización en hora de
  Perú. Las recalcula `php artisan indicators:refresh-all` (al final de `jobs:run-all` y a las 07:00 por el
  programador). Detalle y mediciones en `docs/produccion/PLAN_RANKINGS.md`, Bloque 9.

## Migraciones (producción: `migrate` con `--path`)

> **Nunca ejecutar `php artisan migrate` sin `--path`.** Las migraciones del repositorio no coinciden con el esquema
> real de producción (hay unas 20 migraciones antiguas "pendientes" que crearían o alterarían tablas existentes).
> Ejecutar SOLO estos archivos:

```sh
symfony php artisan migrate:status        # las de este cambio son las tres 2026_11_25_*; el resto NO se ejecuta
symfony php artisan migrate --path=database/migrations/2026_11_25_000001_create_indicator_facts.php --path=database/migrations/2026_11_25_000002_add_mentions_to_job_offer_alignment.php --path=database/migrations/2026_11_25_000003_add_filter_indexes_to_job_offers.php --force
```

| Migración | Qué hace | Tiempo |
|---|---|---|
| `2026_11_25_000001_create_indicator_facts` | Crea `indicator_facts` e `indicator_fact_runs` (nuevas, vacías) | < 1 s |
| `2026_11_25_000002_add_mentions_to_job_offer_alignment` | Agrega `job_offer_alignment.mentions` con `ALGORITHM=INSTANT` (no bloquea) | < 1 s |
| `2026_11_25_000003_add_filter_indexes_to_job_offers` | Índices `idx_jo_region_country` e `idx_jo_country_city` en `job_offers`, en línea (`ALGORITHM=INPLACE, LOCK=NONE`) | ~31 s en local (~400 000 ofertas) |

Las tres son idempotentes (si la tabla, la columna o el índice ya existe no hacen nada) y no borran datos.
`--force` es obligatorio en producción (sin él Laravel pide confirmación). Probado en local: la segunda ejecución
dice "Nothing to migrate"; con los objetos ya creados por el SQL del DBA, las migraciones corren sin hacer nada y
quedan registradas. Reversión, una por una y de la más nueva a la más vieja:
`symfony php artisan migrate:rollback --path=database/migrations/<archivo> --force` (000003, 000002, 000001; borra
las tablas resumen, que se regeneran con `indicators:refresh-all`, la columna y los índices).

**Permisos del usuario de base de datos de la aplicación**: `CREATE` (tablas nuevas), `ALTER` (columna de
`job_offer_alignment` e índices de `job_offers`: en MySQL, `ALTER TABLE … ADD INDEX` pide `ALTER`), `INDEX` (índice de
`indicator_facts` y de `job_offers`) y, solo para la reversión, `DROP`. Además los de uso
normal (`SELECT`, `INSERT`, `UPDATE`, `DELETE` sobre las tablas resumen, que `indicators:refresh-all` reemplaza por
periodo). Si el usuario de la aplicación no tiene `CREATE`/`ALTER`, el DBA puede aplicar el SQL equivalente:

| Script | Equivale a |
|---|---|
| `01_tablas_resumen.sql` | migración `000001` (idempotente; sin DELETE, TRUNCATE ni DROP) |
| `02_alineamiento_menciones.sql` | migración `000002` (idempotente; sin DELETE, TRUNCATE ni DROP) |
| `04_indices_filtros.sql` | migración `000003` (idempotente; sin DELETE, TRUNCATE ni DROP; ~31 s en local) |
| `03_reversion.sql` | `down()` de las tres |

## Operador (después de desplegar el código y migrar)

```sh
symfony php artisan optimize:clear
symfony php artisan queue:restart                 # los workers cargan el código nuevo
symfony php artisan indicators:refresh-all        # todos los precálculos (ver tiempos en el PLAN)
symfony php artisan schedule:list                 # indicators:refresh-all a las 07:00 (America/Lima)
```

`indicators:refresh-all --only=<indicador>` recalcula uno solo (`alignment`, `seniority`, `geo`, `companies`,
`market_status`, `modality`, `city_demand`, `curricular`, `pe`, `seniority_evolution`, `companies_evolution`,
`careers_evolution`); con `--queue` lo encola en la cola por defecto (un Job de menos de 60 s por paso).
