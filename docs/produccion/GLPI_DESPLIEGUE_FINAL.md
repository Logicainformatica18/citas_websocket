# GLPI — Despliegue final del Observatorio (QA y PROD)

**Rama:** `observer` (último commit incluido: ver `git log -1` tras el `git pull`).
**Ya aplicado en producción:** `docs/produccion/DESPLIEGUE_RANKINGS_PRODUCCION.sql` (rankings de Lenguajes, Tecnologías y
Certificaciones, y las tablas de exclusiones del detector `entity_association_exclusions` /
`entity_association_exclusion_settings`).

**Este ticket lleva lo pendiente desde ese despliegue:**
1. Exclusiones del detector: activarlas (las tablas ya existen; falta ejecutar el comando).
2. Exclusión de ofertas por título (roles que no son de TI).
3. Índices de los indicadores (Datos Generales, Empresas).
4. Tablas resumen de los indicadores (`indicator_facts`) y precálculo diario (`indicators:refresh-all`).
5. Alineamiento oferta–carrera por menciones (`job_offer_alignment.mentions`) e índices de filtros.
6. Funciones del puesto en `job_offers` (`functions`, `functions_headline`, `functions_origin`): también es posterior a ese
   despliegue y el código nuevo ya guarda esas columnas.

Mismos pasos en **QA** y luego en **PROD**. En QA, anotar los tiempos de cada paso para calcular la ventana de PROD.

---

## ⚠️ Importante

- **NUNCA ejecutar `php artisan migrate` sin `--path`.** Las migraciones del repositorio no coinciden con el esquema real
  de producción: hay unas 20 migraciones antiguas que figuran como "pendientes" y crearían o alterarían tablas que ya
  existen. Ejecutar SOLO el comando del paso 5, con un `--path` por archivo.
- La **primera** `indicators:refresh-all` (paso 12) tarda **unos 45 minutos**: ejecutarla **por consola**, en un
  horario sin uso, y no desde los botones de la interfaz.
- No correr `jobs:run-all` ni los scrapers desde el paso 3 hasta el paso 15.

---

## DBA (antes del despliegue)

1. **Permisos del usuario de base de datos de la aplicación** (el que usa Laravel), sobre la base del Observatorio:
   `CREATE`, `ALTER` e `INDEX` (las migraciones crean tablas, agregan columnas e índices a `job_offers` y
   `job_offer_alignment`). `DROP` solo si alguna vez hay que revertir. Además los habituales `SELECT`, `INSERT`,
   `UPDATE` y `DELETE`.
   ```sql
   GRANT CREATE, ALTER, INDEX ON <base_observatorio>.* TO '<usuario_app>'@'<host>';
   ```
2. **`innodb_buffer_pool_size`: al menos 4 GB** (si el servidor es solo para MySQL, entre el 60 % y el 70 % de la RAM).
   Las tablas más consultadas suman unos 2 GB (`job_offers` ~500 MB, `technology_job` ~770 MB,
   `entity_association_exclusions` ~415 MB, `job_offer_alignment`). Con 128 MB (valor por defecto) los recálculos
   leen de disco y tardan hasta 5 veces más. En MySQL 8 se puede cambiar en caliente:
   ```sql
   SET PERSIST innodb_buffer_pool_size = 4294967296;   -- 4 GB
   SELECT @@innodb_buffer_pool_size / 1024 / 1024 AS mb;
   ```
3. **Respaldo** completo de la base antes del paso 5 (`mysqldump --single-transaction`).

> Si no se pueden dar `CREATE`/`ALTER`/`INDEX` al usuario de la aplicación, el DBA aplica el SQL equivalente (todos
> idempotentes, sin DELETE, TRUNCATE ni DROP) y después el paso 5 igual se ejecuta: no hace nada y deja registradas las
> migraciones. `ofertas_exclusion/01_exclusion_titulos.sql` (= `2026_11_23_000001`),
> `ofertas_exclusion/03_indices_indicadores.sql` (= `2026_11_24_000001`),
> `indicadores_optimizacion/01_tablas_resumen.sql`, `02_alineamiento_menciones.sql` y `04_indices_filtros.sql`
> (= `2026_11_25_000001`, `000002` y `000003`). Las de `functions` (`2026_11_17_*`) no tienen SQL: requieren `ALTER`.

---

## Operador

En el servidor de la aplicación, desde la carpeta del proyecto. Los comandos de PHP llevan el prefijo `symfony`.

### Código

1. Bajar el código:
   ```sh
   git pull origin observer
   ```
2. Compilar el frontend:
   ```sh
   npm run build
   ```
3. Detener `jobs:run-all` y los scrapers (cron o tareas programadas) hasta el paso 15.

### Migraciones

4. Revisar el estado (solo lectura). Deben figurar como **Pending** las 7 de la lista del paso 5, además de las
   antiguas que NO se ejecutan:
   ```sh
   symfony php artisan migrate:status
   ```
5. Ejecutar SOLO estas 7 migraciones, en un único comando:
   ```sh
   symfony php artisan migrate --path=database/migrations/2026_11_17_000001_add_functions_to_job_offers_table.php --path=database/migrations/2026_11_17_000002_add_functions_origin_to_job_offers_table.php --path=database/migrations/2026_11_23_000001_create_job_title_exclusions.php --path=database/migrations/2026_11_24_000001_add_indicator_indexes_to_job_offers.php --path=database/migrations/2026_11_25_000001_create_indicator_facts.php --path=database/migrations/2026_11_25_000002_add_mentions_to_job_offer_alignment.php --path=database/migrations/2026_11_25_000003_add_filter_indexes_to_job_offers.php --force
   ```

   | Migración | Qué hace | Tiempo en local (~400 000 ofertas) |
   |---|---|---|
   | `2026_11_17_000001_add_functions_to_job_offers_table` | Columnas `functions` y `functions_headline` en `job_offers` | sin medir (columnas nulas) |
   | `2026_11_17_000002_add_functions_origin_to_job_offers_table` | Columna `functions_origin` y la completa en las ofertas de GetOnBoard | sin medir |
   | `2026_11_23_000001_create_job_title_exclusions` | Tablas de la exclusión por título, sus 116 patrones, columna `job_offers.title_exclusion_id` e índices | ~2 min 20 s |
   | `2026_11_24_000001_add_indicator_indexes_to_job_offers` | 2 índices de `job_offers` para los indicadores; deja INVISIBLE `idx_jo_title_excl` | ~5-10 s |
   | `2026_11_25_000001_create_indicator_facts` | Tablas resumen `indicator_facts` e `indicator_fact_runs` | < 1 s |
   | `2026_11_25_000002_add_mentions_to_job_offer_alignment` | Columna `job_offer_alignment.mentions` (ALGORITHM=INSTANT) | < 1 s |
   | `2026_11_25_000003_add_filter_indexes_to_job_offers` | 2 índices en línea (INPLACE, LOCK=NONE) para los filtros | ~31 s |

   Todas son idempotentes: si una tabla, columna o índice ya existe, no hacen nada. `--force` es obligatorio en
   producción (sin él Laravel pide confirmación). Al terminar, `migrate:status` debe mostrar las 7 como **Ran**. Si se
   ejecuta dos veces, la segunda dice "Nothing to migrate".

### Caché y colas

6. ```sh
   symfony php artisan optimize:clear
   symfony php artisan config:cache
   symfony php artisan event:cache
   symfony php artisan view:cache
   symfony php artisan queue:restart      # los workers cargan el código nuevo
   ```
   **No usar `symfony php artisan optimize`**: además de lo anterior cachea las rutas, y `route:cache` falla por un
   problema que ya existía (dos rutas con el nombre `logout`: `GET /logout` en `routes/web.php` y `POST logout` en
   `routes/auth.php`). Corregirlo toca el cierre de sesión (SAML) y queda como pendiente aparte. Sin la caché de rutas
   la aplicación funciona igual.
   Comprobar que el worker sigue activo con la misma configuración:
   `php artisan queue:work --sleep=3 --tries=3 --timeout=170` (cola por defecto; no hay colas nuevas).

### Exclusiones del detector

7. Primera ejecución: evalúa todas las asociaciones y guarda la fecha de confianza (3-6 min):
   ```sh
   symfony php artisan entities:exclude-associations
   ```
8. Comprobar: debe decir **ACTIVAS**, mostrar la fecha de confianza y las exclusiones por tabla.
   ```sh
   symfony php artisan entities:exclusions
   ```

### Exclusión por título (roles que no son de TI)

9. Simulación (no escribe nada): muestra cuántas ofertas excluiría, por categoría.
   ```sh
   symfony php artisan job-offers:apply-title-exclusions --dry-run
   ```
   En local: 59 512 de 403 674 ofertas (14,7 %). Si la cifra se aleja mucho, no seguir y avisar.
10. Aplicar (~2 min con 400 000 ofertas; marca, no borra):
   ```sh
   symfony php artisan job-offers:apply-title-exclusions
   ```

### Rankings

11. ```sh
    symfony php artisan ranking:refresh-languages
    symfony php artisan ranking:refresh-technologies
    symfony php artisan ranking:refresh-certifications
    ```
    Cada uno debe imprimir "Exclusiones del matcher: APLICADAS" y "Ofertas excluidas por título: IGNORADAS".

### Demás indicadores (precálculo)

12. **Primera ejecución: ~45 minutos, por consola, en horario sin uso** (reescribe todo `job_offer_alignment`):
    ```sh
    symfony php artisan indicators:refresh-all
    ```
    Debe terminar con "Listo: N pasos en … s". Las ejecuciones diarias siguientes tardan unos 12 minutos (en local).
13. Comprobar el programador: `indicators:refresh-all` y los tres `ranking:refresh-*` a las 07:00 (America/Lima).
    ```sh
    symfony php artisan schedule:list
    ```
    Debe existir el cron del sistema `* * * * * php artisan schedule:run`.
14. Revisar en el navegador que estas páginas cargan rápido y muestran "Última actualización": Seniority, Demanda
    geográfica, Empresas, Modalidad, Ranking de carreras, Alineamiento PE y CCTC.
15. Reanudar `jobs:run-all` y los scrapers. `jobs:run-all` ya incluye `job-offers:apply-title-exclusions`, los tres
    `ranking:refresh-*` e `indicators:refresh-all` al final.

### Crons a quitar

Del crontab del servidor o del panel de tareas programadas (ya no se usan o los reemplaza `indicators:refresh-all`):
- `languages:take-snapshot`, `technologies:take-snapshot`, `certifications:take-snapshot` (retirados: los reemplazan
  los `ranking:refresh-*`).
- `languages:backfill-all`, `technologies:backfill-all`, `certifications:backfill-all`.
- `companies:take-snapshot`, `careers:take-snapshot`, `seniority:take-snapshot`, y `companies:backfill-all`,
  `careers:backfill-all`, `seniority:backfill-all` (la evolución del año en curso la recalcula `indicators:refresh-all`
  todos los días).
- Cualquier cron de `jobs:build-alignment` o `indicators:curricular-demand` (ahora van dentro de `indicators:refresh-all`).
- Revisar que `greenhouse:technologies` no esté programado fuera del repositorio.

**Se mantienen:** `* * * * * php artisan schedule:run` y el worker `queue:work --sleep=3 --tries=3 --timeout=170`.

---

## Reversión (solo si hace falta)

- Exclusiones del detector: `symfony php artisan entities:exclusions --disable` y los tres `ranking:refresh-*`.
- Exclusión por título: `symfony php artisan job-offers:apply-title-exclusions --clear` y los tres `ranking:refresh-*`.
- Migraciones, una por una y de la más nueva a la más vieja:
  `symfony php artisan migrate:rollback --path=database/migrations/<archivo> --force`. SQL equivalente:
  `indicadores_optimizacion/03_reversion.sql`, `ofertas_exclusion/04_indices_indicadores_rollback.sql` y
  `ofertas_exclusion/02_exclusion_titulos_rollback.sql`.

Detalle técnico: `docs/produccion/PLAN_RANKINGS.md` (Bloques 6 a 9), `docs/produccion/ofertas_exclusion/README.md` y
`docs/produccion/indicadores_optimizacion/README.md`.
