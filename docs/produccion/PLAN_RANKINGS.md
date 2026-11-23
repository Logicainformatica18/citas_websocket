# Plan de los rankings del Observatorio (Lenguajes, Tecnologías, Certificaciones)

Documento para retomar el trabajo en una sesión nueva. Estado al **2026-11-23** (exclusión por título: Bloque 7).
Rama de trabajo: `observer`. Plan completo; todo commiteado y subido (ver "Estado del repositorio").

---

## 1. Contexto en una página

Las páginas de ranking (`/dashboard/ranking/languages`, `/dashboard/ranking/technologies`, y después
`/dashboard/ranking-certificaciones`) calculaban todo en vivo y superaban el `max_execution_time` de 30 s.
Ahora leen de **tablas de resumen** que llena un comando por indicador. Durante el trabajo aparecieron
problemas de datos de fondo (entidades que no corresponden, detección de ofertas con muchísimos falsos
positivos) que se están corrigiendo por fases.

Piezas principales:

| Pieza | Dónde |
|---|---|
| Servicio genérico (cálculo + lectura + evolución + lock + estado del botón) | `app/Services/Ranking/EntityRankingService.php` |
| Configuración por indicador (entity_type, tabla pivote, tablas resumen, ponderaciones, tabla de carreras, lock, log) | `config/entity_rankings.php` |
| Subclases por indicador | `app/Services/LanguageRankingService.php`, `app/Services/TechnologyRankingService.php` |
| Comandos | `ranking:refresh-languages`, `ranking:refresh-technologies` (base: `app/Console/Commands/Ranking/RefreshEntityRankingCommand.php`) |
| Job del botón | `app/Jobs/RefreshEntityRankingJob.php` (`RefreshLanguageRankingJob` queda por compatibilidad) |
| Botón en controladores | trait `app/Http/Controllers/Dashboard/Concerns/RefreshesEntityRanking.php` |
| Export de evolución (streaming, memoria constante) | `app/Exports/EntityEvolutionExport.php` + `app/Support/Xlsx/StreamingXlsxWriter.php` |
| Frontend compartido | `resources/js/components/ranking/RankingEvolutionModal.tsx`, `RankingRefreshControl.tsx` |
| Scripts para el DBA | `docs/produccion/ranking_lenguajes/`, `docs/produccion/ranking_tecnologias/` (cada carpeta con su README) |
| Prueba de regresión | `docs/produccion/herramientas/foto_dorada_lenguajes.php` |

Entorno local: PHP **8.3 de Herd** → `C:\Users\acardenas\.config\herd\bin\php83\php.exe` (el `php` del PATH es 8.1
y no sirve). MySQL 8.0.46 en `localhost:3307`, base `observatorio_2`. Cliente: `C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe`.

---

## 2. Decisiones tomadas (no re-discutir sin motivo)

1. **Tablas de resumen por indicador** (`{tipo}_rankings`, `{tipo}_ranking_meta`, `{tipo}_ranking_weekly`,
   `{tipo}_ranking_weekly_meta`), no una tabla común con `entity_type`. Lenguajes no cambia de tablas.
2. **Un solo servicio genérico** (`EntityRankingService`) parametrizado por `config/entity_rankings.php`.
   Cada indicador nuevo = una entrada de configuración + una subclase de 10 líneas + un comando de 10 líneas.
3. **`labor_score` y `trend_score` se guardan; `final_score` y `position` se calculan AL LEER** con las
   ponderaciones vigentes (`Prueba::getActive($context)`, por defecto 0,7 / 0,3). Cambiar pesos se ve al instante.
   Los `final_score`/`position` guardados son solo referencia.
4. Fórmula: `labor = LOG(ofertas+1)/LOG(max+1)*100`, `trend = LOG(reportes+1)/LOG(max+1)*100`. Los máximos se
   toman solo de entidades **activas del mismo tipo**.
5. **Semestres con rango semiabierto** `[1-ene, 1-jul)` y `[1-jul, 1-ene)`: el código original perdía el 30-jun y el 31-dic.
6. **Evolución = FOTO del ranking semestral en cada fecha de corte**, reconstruida en cada recálculo (no se congela):
   - ventanas: semana ISO lunes-domingo, quincena 1-15 / 16-fin de mes, mes calendario;
   - corte = último día de la ventana, o hoy si está en curso;
   - vacantes ACUMULADAS desde el inicio del semestre del corte (en semestre nuevo empieza de cero; el modal lo indica);
   - reportes del semestre disponibles hasta el corte (`entity_trends.created_at <= corte`);
   - cambio de posición respecto de la ventana anterior (↑ ↓ = nuevo), salvo en inicio de semestre;
   - vacantes únicas acumuladas en la cabecera y "nuevas del periodo" como dato secundario;
   - etiquetas en español: "Semana 45 · 02-nov → 08-nov", "1ra quincena octubre", "Octubre".
   - Criterio de aceptación: **la foto de la semana actual = el ranking principal** (verificado en ambos indicadores).
7. **Zona horaria**: se agrupa por `DATE(published_at)` en la zona de la sesión por defecto (la misma con que Laravel
   escribe la hora de pared de America/Lima). No se fuerza `time_zone='-05:00'`: si el MySQL de producción estuviera
   en UTC correría todo 5 horas.
8. **Un recorrido por semestre** alimenta el ranking del semestre y sus fotos (cada oferta tiene una sola fecha →
   las sumas diarias son exactas). Filtro de activos con `IN (ids activos)` (2-3× más rápido que JOIN) y
   `WITH ROLLUP` para las vacantes únicas en el mismo recorrido. Cada semestre (ranking y fotos) se reemplaza en su
   propia transacción: nunca se ve vacío ni a medias.
9. **`market_entities.is_active` es la única fuente de verdad** de "entidad activa" (+ `inactive_reason`, `inactive_at`).
   Lo respetan ranking, evolución, export y scrapers (`MarketEntity::active()`, `Language::active()`, `Technology::active()`).
   La lectura también filtra, así que desactivar algo se ve al instante sin recalcular.
10. **"Eliminar" en la administración DESACTIVA** (no borra): borrar arrastraba en cascada las ofertas de la tabla
    pivote y los scrapers recreaban la entidad. El interruptor activa/desactiva también la entidad vinculada.
11. **`$fillable` con `market_entity_id`** en `Language` y `Technology` (sin él, `firstOrCreate` perdía el vínculo).
12. **Tabla de carreras única: `market_entity_career`** (la escribe la administración de entidades). `career_market_entity`
    quedó sin uso; NO se borra todavía.
13. **Producción no usa migraciones**: el DBA aplica scripts SQL idempotentes (con rollback y verificación). Las
    migraciones de Laravel existen y son idempotentes (si alguien corre `migrate` después, no hacen nada).
14. **Botón "Recalcular ranking"**: solo administradores (`permission:administrar`), `throttle:3,1`, encola
    `RefreshEntityRankingJob` y el frontend consulta el estado cada 3 s. Recalcula **el semestre que se está viendo**
    (ranking + sus fotos); el completo lo hacen los comandos. `Cache::lock` por indicador: nunca dos recálculos a la
    vez; si el proceso muere, el lock expira a los 15 min.
15. Recálculo automático: al final de `jobs:run-all` (lenguajes y luego tecnologías) y a las 07:00 America/Lima
    (`routes/console.php`, `withoutOverlapping`).
16. **Export** en streaming (XLSX propio sobre ZipStream): memoria constante; los ceros se muestran como 0.
17. Comandos antiguos de evolución (`languages:take-snapshot`, `languages:backfill-all`, `technologies:take-snapshot`,
    `technologies:backfill-all`) **retirados**: sin `--legacy` no hacen nada. Archivos y tablas `*_evolution_cache` se conservan.
18. Los cambios se hacen **por fases, deteniéndose al final de cada una** para confirmación.

---

### Decisiones tomadas sin consulta (ejecución de corrido, desde 2026-11-16)
Confirmadas por el usuario antes de empezar: JavaScript no se infiere de Node.js/Next.js/Vue.js; en Adzuna se acepta
la pérdida de ~15 % por extracto truncado; no se guarda en el pivote qué campo coincidió.

Bloque 1 (Fase B de Lenguajes):
- **Servicio común `OfferEntityLinker`** (match/confirms/attach) para todos los scrapers; el pivote se escribe con
  `market_entity_id` al insertar. Una fila de catálogo sin entidad activa no se asocia (criterio estricto).
- **Arbeitnow**: descarga el feed una vez (`--pages=5`, ~100 ofertas por página) y clasifica cada oferta; las ofertas
  sin ningún lenguaje no se guardan. Se quitó `getLanguageFromMarketEntity` (creaba filas de `languages` por nombre).
- **Greenhouse**: `content=true` (descripción en la misma llamada, sin costo extra) y guarda `description`.
- **RemoteOk y Reed no asociaban ningún lenguaje** (creaban la oferta sin pivote): corregido al integrar.
- **JobsDataFeeds**: su puntaje con alias amplios (node/react → JavaScript, py → Python) se reemplazó por `match()`.
- **GetOnBoard**: la descripción completa se arma con `description_headline`, `description`, `functions`, `desirable`
  y `projects` (antes solo `description`, vacío en el 72 %).
- **Computrabajo**: confirma con el título; si no basta y la oferta es nueva, descarga su detalle una vez por URL y
  corrida (pausa 0,3-0,8 s) y lo guarda en `description`. Las ofertas ya existentes no se vuelven a descargar.
- Jooble, reed y USAJOBS guardan su fragmento/extracto en `requirements` (antes se descartaba).
- Migraciones `2026_11_12_000002/000006`: salen sin hacer nada fuera de MySQL (usan `information_schema`).

Bloque 2 (Fase C de Lenguajes):
- **Limpieza estricta**: se borra toda fila de entidad activa que el matcher no confirma en el texto guardado,
  también si la oferta no tiene texto o solo un extracto (coherente con "exigir la confirmación"). Afecta sobre todo
  al histórico de Computrabajo (81 %, solo título), GetOnBoard (95 %) y Greenhouse (97 %).
- Se conservan las filas de entidades inactivas (se recuperan al reactivar) y las sin entidad resoluble (2 210, p. ej.
  GNU/Linux y CLI).
- La decisión de qué borrar la toma la aplicación (`entities:clean-associations`, solo escribe una tabla de trabajo);
  el borrado lo hace el DBA con SQL, por lotes de 5 000 y siempre respaldando antes en la misma transacción.
- La foto dorada se recapturó después de la limpieza (`storage/app/foto_dorada/lenguajes_2026-11-16_post_limpieza/`)
  como línea base de los bloques 3 y 4.
- R quedó en 163 vacantes en 2026-s2 (el plan estimaba ~600): el criterio estricto también descarta los extractos de
  Adzuna sin mención (pérdida aceptada de ~15 %).

Bloque 3 (Fases 3 y 4 de Tecnologías):
- **Reglas** (`database/data/entity_aliases/technologies.php`, 968): estrictas para palabras comunes (REST/SOAP/AJAX
  con contexto de APIs, Express, Unity, Excel, Apache sin "Apache Kafka/Spark…", Teams, Monday, Tableau sin "Tableau de",
  Postman, Bootstrap, SAN/NAS, servicios de AWS con siglas…), los 41 duplicados como alias de su principal; el resto
  usa su nombre. Entidades con nombre genérico y significado incierto quedan solo con alias inequívocos: Pure
  ("Pure Storage", "Pure Data"), Foundation (Zurb), Eraser ("Eraser.io"), Translate/Transcribe/Polly/Comprehend/Forecast/
  Lex/MQ (solo con "Amazon"/"AWS").
- **Transformers reactivado** con reglas estrictas (Hugging Face o contexto de ML; descarta "power transformers").
- **Mismo criterio estricto que Lenguajes**: se borran 1 622 637 de 1 646 266 filas evaluadas (98,6 %). Arbeitnow aporta
  1,45 M con precisión ~0 % (asociaba ~87 tecnologías por oferta). Adzuna/reed/Jooble/GetOnBoard pierden 82-96 % porque
  sus scrapers de tecnologías **no guardaban texto** (solo título): no se puede confirmar. El ranking se reconstruye con
  los scrapers, que ahora guardan el texto.
- Duplicados: si la oferta ya tenía la principal, la fila del duplicado se borra; si no, se reasigna (1 202).
- **Jooble (tecnologías) y RemoteOK (tecnologías) no asociaban la tecnología al crear la oferta**: corregido.
- Computrabajo (tecnologías): mismo criterio que lenguajes, con `ComputrabajoHelper::fetchDescription()` (detalle una vez
  por URL y proceso).
- `OfferEntityLinker::plain()` reemplaza `strip_tags()` al guardar y al confirmar: `strip_tags` pegaba palabras de
  elementos contiguos ("REST APIsErfahrung") y rompía los límites de palabra. El texto histórico ya guardado sigue pegado
  (pérdida menor en la limpieza: algunas menciones al final de un elemento HTML no se reconocen).
- Herramienta de foto dorada generalizada: `docs/produccion/herramientas/foto_dorada.php <tipo> capture|compare <dir>`
  (`foto_dorada_lenguajes.php` queda como envoltorio). Fotos post-limpieza en `storage/app/foto_dorada/`.

Bloque 4 (Certificaciones completo):
- Refactor a `EntityRankingService` (`config/entity_rankings.php` 'certifications', `CertificationRankingService`,
  `ranking:refresh-certifications`, tablas resumen, evolución como foto, botón, export en streaming). La tendencia pasa a
  la fórmula común `LOG(reportes+1)/LOG(max+1)*100` (antes menciones/total de reportes). Se conservan 4 tarjetas por
  página y el filtro por área (`EntityRankingService::paginate` admite `$categories`).
- **"Eliminar" en `MarketEntityController::destroy` ahora desactiva** para todos los tipos de entidad (antes borraba la
  entidad y, en cascada, sus ofertas; Lenguajes y Tecnologías ya desactivaban desde sus propios controladores).
- 12 duplicados desactivados (CISSP/CISM con nombre largo, los "Microsoft Certified: …" y "AWS Certified …" repetidos,
  versiones 2021 de Oracle). No se tocaron certificaciones dudosas que podrían no existir ("Azure AI Ethics Specialist",
  "Responsible AI Certification by Microsoft"): quedan activas con su nombre como única regla.
- CompTIA A+ dada de alta; Cisco CyberOps Associate ya existía. Sus 13 701 asociaciones de `technology_job` se copiaron
  a `certification_job` y la limpieza conservó las que el matcher confirma (16 de CompTIA A+, 0 de CyberOps).
- Los scrapers de certificaciones conservan su búsqueda por keywords, pero **toda escritura al pivote pasa por
  `OfferEntityLinker::attachIfConfirmed()`** (confirma con el texto de la oferta). Solo recorren certificaciones activas.
- Las filas de `certifications` sin keywords reciben `["<nombre>"]` (la columna tiene restricción JSON).
- `certification_aliases` (22 alias genéricos: "AWS", "AZURE", "SCRUM") queda sin uso; no se borra.
- No se infiere el nivel: "AWS Solutions Architect" sin Associate/Professional no cuenta para ninguna.
- `certification_job` admite `certification_id` NULL (filas escritas por entidad): la plantilla de limpieza lo respeta.

Bloque 5 (vacantes de Certificaciones y despliegue consolidado, 2026-11-16):
- **`App\Services\Matching\SearchTerms`**: decodifica `certifications.keywords` (JSON, arreglo o el formato antiguo con
  comas); si está vacío o es inválido usa el nombre y los alias fuertes de `market_entity_aliases`. El primer término es
  el principal (el que se envía a las APIs que admiten uno solo por llamada).
- Los 10 scrapers de certificaciones buscan con esos términos y confirman con el matcher antes de crear la oferta
  (no quedan ofertas sin asociar); `attachIfConfirmed()` recibe también el texto de la API (reed, Jooble, Remotive…
  no lo guardaban y la confirmación fallaba). Arbeitnow descarga el feed una vez (5 llamadas en lugar de 79-141);
  Greenhouse pide `content=true`; Adzuna y Computrabajo dejan de exigir el nombre completo exacto en el texto.
- Ningún scraper de lenguajes ni de tecnologías tenía el bug (allí `keywords` es solo el nombre de un parámetro).
- `ranking_certificaciones/12` (y su reversión `13`): keywords de búsqueda para las 79 activas (sigla o código y
  nombre oficial). `05` deja desactivada la copia de asociaciones desde tecnologías (`@copiar_desde_tecnologias = 0`).
- **Despliegue consolidado**: `DESPLIEGUE_RANKINGS_PRODUCCION.sql` (+ `DESPLIEGUE_RANKINGS_ROLLBACK.sql`), generado
  por `herramientas/generar_despliegue_consolidado.py`. Para que no tenga DELETE/TRUNCATE/DROP: la evolución antigua de
  Lenguajes se renombra; las reglas se insertan con `NOT EXISTS` (sin borrado previo); la FK de
  `market_entity_aliases` ya no es `ON DELETE CASCADE` (las entidades no se borran); certificaciones usa tablas
  derivadas en lugar de temporales; Transformers no se desactiva para reactivarse después.
- Probado en local simulando producción: base devuelta al estado previo con los rollbacks (incluida la limpieza),
  despliegue ejecutado dos veces (1ª: 122 s; 2ª: 15 s, sin cambios ni errores, solo Warning 1681), reversión
  consolidada (6 s) y nuevo despliegue doble; esquema igual al previo salvo las tablas de la limpieza y la FK; los tres
  `ranking:refresh-*` y las páginas (HTTP 200).
- **Ponderaciones de Certificaciones (recomendación, NO aplicada)**: ver la sección 3.

Bloque 6 (exclusión de asociaciones sin borrar, 2026-11-16):
- **`entity_association_exclusions`** (`ranking_comun/01-02`, migración `2026_11_16_000004`, última parte del
  consolidado): pares (pivote, oferta, entidad) que el matcher no confirma, con motivo, fuente y `vigente`.
- **`php artisan entities:exclude-associations [--type=all] [--dry-run]`**: la llena en bloques con el mismo criterio
  estricto que la limpieza; nunca borra filas (las que dejan de aplicar pasan a `vigente = 0`).
- **`EntityRankingService::exclusionSql()`**: `NOT EXISTS` sobre la tabla en la única consulta que lee el pivote, así
  que afecta al ranking, la evolución y las vacantes únicas de los tres indicadores. Las tablas pivote no se tocan.
- **Sin variables de entorno** (decisión del usuario): la configuración vive en la fila única de
  `entity_association_exclusion_settings` (`App\Services\Ranking\ExclusionSettings`). `enabled = 1` por defecto;
  apagado de emergencia con `php artisan entities:exclusions --disable` (y `--enable`), que recuerda ejecutar
  `ranking:refresh-*`. El filtro se omite solo si la tabla no existe. `ranking:refresh-*` imprime si se aplicó.
- **Fecha de confianza automática** (`trust_since`): la primera ejecución de `entities:exclude-associations` (no
  `--dry-run`) guarda su fecha y hora; en las siguientes, las filas del pivote creadas desde entonces no se re-evalúan.
  Sin esto, el comando excluía asociaciones que los scrapers nuevos confirmaron con el texto completo de la API (reed,
  Remotive… guardan poco o nada): en la prueba CCNA bajaba de 10 a 2 vacantes.
- Resultado en local (base en estado de producción): Lenguajes excluye 233 503 (las mismas que la limpieza),
  Tecnologías 1 477 634, Certificaciones 13 796. Top 10 de Lenguajes idéntico al de la limpieza; en Tecnologías difieren
  Microsoft Azure y GCP porque no se reasignan los duplicados.
- Incidente local: durante la prueba quité de la base local 13 685 filas de `certification_job` que eran la copia
  desde tecnologías de CompTIA A+ y Cyberops (script 05, que en producción va desactivado). La reversión de la
  limpieza las había restaurado sin su marca porque la plantilla de respaldo no guarda `certification_name_raw`. El
  volcado previo salió vacío (`mysqldump --where` con subconsulta a otra tabla no bloqueada), así que no hay respaldo
  de esas filas; eran copias regenerables desde `technology_job`, que está intacta.

Bloque 7 (exclusión de ofertas por TÍTULO — roles no TI, 2026-11-23; ver `docs/produccion/ofertas_exclusion/README.md`):
- Criterio y semilla: `docs/produccion/herramientas/exportar_periodos_ti.py` (`PATRONES_EXCLUIR`, título en minúsculas y
  sin tildes). La semilla (`database/data/job_title_exclusions/seed.php`, 116 patrones) adapta cada expresión: literal →
  `text`; `\bliteral\b` → `text` + palabra completa; el resto → `regex` tal cual. `PATRONES_TI` y `PATRON_SIGLAS_TI` no
  deciden nada (el script usa `SOLO_EXCLUIR = True`): sirven para medir el riesgo de falso positivo ("con palabra TI") y
  como contexto de la IA (`config/job_title_exclusions.php`). **Paridad comprobada: 0 diferencias con el script de
  Python en los 202 096 títulos distintos** (28 933 excluidos en ambos).
- `regex` se compila con `(*UCP)` (bordes de palabra Unicode, como el `re` de Python), sin distinguir mayúsculas y sin
  tildes en el patrón. No se admiten referencias a grupos (`\1`): todos los patrones se combinan en una sola expresión.
  Atribución ("qué patrón la excluyó"): el de menor id que coincide. Una excepción activa gana sobre cualquier exclusión.
- **Marca en `job_offers.title_exclusion_id`** (NULL = válida; si no, el patrón), no tabla aparte: una columna dice si
  está excluida y por cuál, y el índice `(published_at, title_exclusion_id)` cubre la consulta del ranking. El filtro
  (`EntityRankingService::titleExclusionSql()`) va en la única consulta que lee el pivote → afecta a los tres rankings,
  la evolución y las vacantes analizadas. Recálculo con el filtro: mismos tiempos (Lenguajes 21 s, Tecnologías 109 s).
- **Scrapers sin tocar uno por uno**: `JobOffer::creating` cancela el INSERT de un título excluido (los ~70 puntos de
  creación usan `create`/`updateOrCreate`). Para que el `sync` posterior al pivote no inserte `job_offer_id = NULL`,
  `JobOffer` usa `SavedParentBelongsToMany` (no escribe si el padre no se guardó). Los 3 `Adzuna*TrendsCommand` usaban
  `$jobOffer->id` directo: se les agregó `if (! $jobOffer->exists) continue;`. El conteo por scraper se registra al
  terminar cada comando (`CommandFinished`: log `[TITLE_EXCLUSION]` y línea en la salida que muestra `jobs:run-all`).
  Al cambiar el título de una oferta existente, `updating` recalcula su marca. Alta manual (`POST /job-offers`) con título
  excluido → 422 con el patrón.
- Lista nueva = solo ofertas NUEVAS al instante; las existentes se re-marcan con `job-offers:apply-title-exclusions`
  (en `RunAllJobs.php` antes de los `ranking:refresh-*`) o con "Excluir de los indicadores". La pantalla avisa si la lista
  cambió después de la última aplicación (`Cache` `job_title_exclusions:list_changed_at`). Borrar un patrón no desmarca
  sus ofertas hasta re-aplicar.
- **Colas**: ninguna nueva; todo en la cola por defecto. Cada Job trabaja ≤ 60 s y se re-encola (`JobOfferProcessJob`,
  `slice_seconds`) porque la cola `database` tiene `retry_after = 90` (con dos workers, un Job de más de 90 s se
  ejecutaría dos veces). Los `ranking:refresh-*` desde la interfaz van **un Job por indicador y semestre**
  (`RefreshRankingsChainJob`, 18 pasos): el completo de Tecnologías tarda ~110-150 s en local. El más largo medido:
  Tecnologías 2026-s1, 54 s. En el análisis, una fase pesada solo empieza si quedan 30 s del tramo (impacto de
  Tecnologías: 45 s tras unir sus dos consultas con `WITH ROLLUP`).
- `job_offer_processes` registra cada proceso (quién, cuándo, cuántas, progreso, resultado). Procesos que tocan la tabla
  completa (aplicar, analizar, eliminar, restaurar) de a uno; un proceso sin avance en 15 min se considera caído.
- "Analizar basura" guarda sus candidatos en `job_title_exclusion_candidates` (solo los del último análisis) y calcula el
  impacto por indicador con la fórmula del ranking: **verificado igual al recálculo real** (vacantes y top 10 de los tres
  indicadores en 2026-s2). "Eliminar definitivamente" exige escribir ELIMINAR, usa SOLO el último análisis terminado,
  respalda cada fila (JSON) en `job_offer_deletion_backups` antes de borrar (tablas con `job_offer_id` descubiertas en
  `information_schema`), y se revierte con "Restaurar" o `job-offers:restore-deleted` (INSERT IGNORE; huella idéntica
  verificada). El rollback SQL conserva `job_offer_deletion_backups` si tiene filas.
- "Sugerir con IA": la misma integración que "Detectar nuevos lenguajes con IA" (OpenAI `gpt-4.1-mini`, JSON). Envía la
  lista, los 250 títulos válidos más frecuentes + 150 al azar del resto y hasta 120 excluidos con palabra TI (para
  proponer excepciones). Cada sugerencia muestra ofertas afectadas, coincidencias, "con palabra TI" y ejemplos; se
  aprueban una a una o todas (origen `ai`, activas) o se descartan. Los títulos se recorren con una consulta NO
  bufferizada (`eachTitle`): con un arreglo de 200 000 títulos el worker superaba los 128 MB (incidente en la prueba).
- Export: antes corría **en la petición** (Maatwebsite `FromCollection`, `limit(4000)`, `memory_limit -1`) y además, sin
  caché de rutas, `GET /job-offers/export-excel` caía en `show('export-excel')` (404) porque `/job-offers/{id}` se
  registraba antes. Ahora: `POST` encola `ExportJobOffersJob` (keyset por id, hoja XML por partes con
  `StreamingXlsxWriter::sheetStart/rowXml/package`), mismos filtros que el listado (`JobOfferFilters`), tope 1 048 575
  filas, celdas recortadas a 32 767, columna extra "Excluida por título (patrón)", archivo 3 días en
  `storage/app/job_offer_exports/`. 2026-s2 completo: 93 872 filas, 25,8 MB, 21 s. Las rutas `{id}` llevan `whereNumber`.
- Filtros del listado: año + semestre con los rangos del script (`[año-01-01, año-07-01)`, `[año-07-01, año+1-01-01)`;
  solo año = año completo), "Excluidas por título" (todas / válidas / excluidas), y las fechas pasan de `whereDate()` a
  rangos semiabiertos (usan el índice). **6 índices nuevos para los combos** (`company`, `city`, `modality`, `job_type`,
  `remote_type`, `workload`): cada `SELECT DISTINCT` recorría la tabla (1,7-2,9 s); la página pasa de ~11 s a ~2 s.
- Resultado en local (2026-s2): 10 338 de 93 872 ofertas excluidas (11,0 %); total 59 512 de 403 674 (14,7 %). Vacantes
  analizadas 2026-s2: Lenguajes 9 367 → 9 177, Tecnologías 7 921 → 7 600, Certificaciones 81 → 70. Fotos doradas
  (`storage/app/foto_dorada/exclusion_titulos_antes_2026-11-23/`): toda diferencia de Lenguajes y Tecnologías (ranking,
  meta y 36 479 filas de evolución) se explica exactamente por las ofertas excluidas; sin marcas, 0 diferencias.
- Falsos positivos vistos en la revisión (sin tocar la semilla, para que lo decida el usuario): `tutor\b` atrapa
  "Kotlin Developer for AI Tutor"; `\bsales?\b` atrapa "Technical Architect (Pre-Sales)" y "Sales Engineer" (el script lo
  excluye a propósito). Falsos negativos: "Dispensing Optician", "Lettings Negotiator", "Media-Buyer", "Line Setter".
  La IA propuso, entre otras, `trainee` (5 087 de 8 171 coincidencias con palabra TI: no aprobar) y excepciones como
  `clinical data` y `salesforce`. En local las dos aprobadas en la prueba (`caretaker`, `clinical data`) se borraron con
  el ciclo de rollback del SQL.

### Pendientes fuera de alcance (anotados, no tocados)
- Greenhouse, GetOnBoard, reed, Remotive, RemoteOk, USAJOBS, Wantedly y BuiltIn solo buscan lenguajes **con cursos**
  (`course_language`): los 6 lenguajes nuevos solo los buscan Adzuna, Arbeitnow, Computrabajo, Jooble, Selecta y
  JobsDataFeeds. Adzuna y Computrabajo recorren entidades (ya incluían a los 6); las filas nuevas suman 8 llamadas
  por lenguaje en Jooble.
- RemoteOk, BuiltIn, Remotive, Wantedly y JobsDataFeeds no están en `RunAllJobs.php` (no se ejecutan).
- Computrabajo no tiene asociaciones de lenguajes en 2026-s2 (¿scraper caído?).
- (Resuelto en el Bloque 3) Greenhouse guardaba en `requirements` el HTML escapado; ahora usa `OfferEntityLinker::plain()`.
- Adzuna busca la oferta existente por `external_id` sin filtrar la fuente.
- `tests/Unit/ExampleTest` falla con SQLite: `market_entities` (y otras tablas base) no se crean en ninguna migración.
- (Resuelto en el Bloque 5) Los scrapers de certificaciones leían `keywords` (JSON) sin decodificar.
- Rutas de Certificaciones a métodos inexistentes (error 500): `/trending` → `trendingCertifications`,
  `/trend/{trend}/jobs` → `jobsByTechnologyTrend`. (Las de Lenguajes, ya anotadas, siguen igual.)
- `app/Exports/CertificationsWeeklyExport.php` y `certification_evolution_cache` quedan sin uso (no se borran).
- La administración de entidades no tiene botón para **reactivar** una entidad desactivada (se hace por SQL).
- La plantilla de limpieza (`generar_limpieza_sql.php`) no respalda `certification_job.certification_name_raw`.
- Los modales "ver ofertas" de cada entidad no aplican las exclusiones (listan el pivote completo).
- Errores de TypeScript previos en las páginas de ranking (`tsc --noEmit`); `npm run build` compila igual.
- (Exclusión por título) `retry_after = 90` de la cola `database` con `--timeout=170`: los Jobs de más de 90 s ya
  existentes (p. ej. `RefreshEntityRankingJob` completo de Tecnologías desde el botón "Recalcular") pueden ejecutarse dos
  veces si hay más de un worker. No se cambió la configuración (decisión: sin tocar el worker ni el `.env`).
- (Exclusión por título) `ImportController` (importar CSV) y `JobOfferController::import` no informan cuántas filas se
  descartaron por título (quedan en el log `[TITLE_EXCLUSION]`).
- (Exclusión por título) Errores de TypeScript previos en `resources/js/pages/job_offers/` (`implicit any`): 46 antes,
  41 después (ninguno en los archivos nuevos).
- (Exclusión por título) `app/Exports/JobOffersExport.php` queda sin uso (no se borra).
- (Exclusión por título) La interfaz se probó por HTTP (mismo kernel, usuario administrador) y con `npm run build`; no se
  revisó visualmente en el navegador.

## 3. Estado actual (2026-11-16: plan completo)

### Lenguajes — hecho y probado
- Tablas de resumen, evolución como foto, botón, export, activos (Fase A), matcher en los 14 scrapers (Fase B) y
  limpieza de `language_job` (Fase C: 267 926 → 34 419 filas). Página: 35,7 s → ~0,06 s.
- Scripts DBA: `ranking_lenguajes/01-15` (ver su README).

### Tecnologías — hecho y probado
- Fase 2 (resumen, entidades, carreras), Fase 3 (matcher en los 12 scrapers, 968 reglas, Transformers reactivado) y
  Fase 4 (limpieza con reasignación de duplicados: `technology_job` 2 159 306 → 536 880). Página: 15-32 s → ~0,08 s.
- Scripts DBA: `ranking_tecnologias/01-14` (ver su README).

### Certificaciones — hecho y probado
- Todo el criterio de una vez (ver Bloque 4 en "Decisiones tomadas sin consulta"). `certification_job` 45 214 → 726.
  Página: cálculo en vivo → ~0,04 s.
- Scripts DBA: `ranking_certificaciones/01-11` (ver su README).

### Certificaciones: vacantes y ponderaciones (2026-11-16)
- Con los scrapers corregidos (corrida local de prueba): 20 certificaciones con vacantes en 2026-s2 (antes ~12), 112
  asociaciones, máximo 29 (CCIE). Siguen siendo pocas: 103 ofertas analizadas en el semestre.
- **Recomendación (no aplicada): 0,5 laboral / 0,5 tendencia mientras `vacantes_analizadas` del semestre sea menor
  que ~1 000; volver a 0,7 / 0,3 cuando haya más datos.** Con tan pocas ofertas, 1 vacante vale 20 puntos laborales
  (escala logarítmica sobre un máximo de 29) y el componente laboral es ruido; la tendencia sale de 5 833 reportes.
  Efecto real limitado: el top 10 es el mismo con 0,7/0,3, 0,5/0,5 y 0,4/0,6 (solo cambia el orden del 8 al 10),
  porque la tendencia está comprimida (93 de media, desviación 11). Lo que más moverá el ranking es acumular
  vacantes con los scrapers corregidos, no los pesos.

### Estado de la base local
- La base local quedó como quedará producción: **sin limpieza** (language_job 267 926 filas, technology_job
  2 159 517, certification_job 31 577), con el despliegue consolidado aplicado y las exclusiones calculadas
  (en la prueba local se fijó `trust_since = '2026-11-16 10:30:00'` por SQL, para simular una primera ejecución
  anterior a las pruebas de los scrapers nuevos de ese día; en producción la guarda el comando). Las
  fotos doradas `*_post_limpieza` ya no corresponden a estos datos: para comparar de nuevo hay que recapturarlas o
  volver a aplicar la limpieza local (scripts 13-14 / 12-13 / 09-10).

### Verificación final
- Foto de la semana actual = ranking principal: Lenguajes 26/26, Tecnologías 313/313, Certificaciones 79/79.
- Fotos doradas post-limpieza (`storage/app/foto_dorada/*_2026-11-16_post_limpieza/`): Lenguajes y Tecnologías siguen
  con 0 diferencias después de todo el Bloque 4; Certificaciones capturada y verificada.
- Tests: `tests/Unit/EntityTextMatcher*Test.php` (15 tests).

---

## 4. Pendientes conocidos

### Fase A de Lenguajes: decisiones confirmadas y aplicadas (2026-11-16)
- Script `ranking_lenguajes/09` (+ rollback `10`), probado en local y recalculado (26 lenguajes activos):
  Perl, Ruby, VBA, Lua, COBOL activos; Shell y Ada desactivados con motivo (Fase B: "Shell scripting" con alias
  Bash/sh; Ada se evalúa con el matcher); F# slug `f` → `fsharp`; Go/Rust/Scala vinculados; GNU/Linux y CLI `enabled = 0`.
- **Hallazgo:** Perl, Ruby, VBA, Lua, COBOL y F# **no tienen fila en `languages`**: ningún scraper los busca y salen
  con 0 ofertas. Crear sus filas en la Fase B (cuando el matcher confirme las asociaciones).
- Foto dorada recapturada después del recálculo: `storage/app/foto_dorada/lenguajes_2026-11-16/` (79 bloques, 0 dif.).

### Mitigación temporal (2026-11-16)
- `arbeitnow:languages`, `arbeitnow:technologies` y las 28 líneas de `greenhouse:languages` comentados en
  `RunAllJobs.php` hasta la Fase B. `greenhouse:technologies` no estaba en `RunAllJobs.php` (no hay nada que comentar;
  revisar que no esté en el crontab del servidor).
- Precisión medida en 2026-s2 (mención del lenguaje en título+texto guardado):
  - **Greenhouse**: 401 asociaciones, todas a R, 0 %. Busca con `/{nombre}/i` sin límites de palabra (R coincide con
    cualquier título con "r"); no guarda la descripción. `greenhouse:technologies` usa la misma expresión.
  - **GetOnBoard**: 25 % en ofertas con descripción (72 % no la guarda). Bien en SQL/Python/TypeScript/Java/JavaScript
    (75-91 %), mal en C#, C++, R, PHP, DAX (0-4 %): la búsqueda de la API es difusa.
  - Adzuna (referencia): 53 %.

### Bugs / deuda que no se tocaron
- **Bug de tendencias sin filtro de tipo en Lenguajes: NO aplica** (Lenguajes siempre filtró `entity_type='language'`).
- `app/Console/Commands/RunAllJobs.php`:
  - bloque `computrabajo:technologies` (9 países) **duplicado** (al inicio y otra vez antes de `adzuna:certifications`);
  - cada comando se lanza con `popen("php artisan …")`: usa el `php` del PATH (en local es 8.1 y falla). Usar `PHP_BINARY`.
- Rutas de Lenguajes que apuntan a métodos inexistentes en `RankingLenguajesController` (error 500):
  `/{language}/reports` → `reportsByLanguage`, `/trend/{trendId}` → `languageTrendDetail`,
  `/trend/{trend}/jobs` → `jobsByTrendLanguage`.
- `languages.slug` no existe pero `LanguageController::store` intenta escribirlo (lo descarta `$fillable`).
- `app/Console/Commands/getTrends/DiscoverLanguageGapsCommand.php` tiene cambios locales sin commitear que no son de este trabajo.
- Metodologías contiene organizaciones (Cisco Systems, CompTIA, SANS): revisar cuando se haga ese indicador.
- `*_evolution_cache` y `career_market_entity` sin uso: decidir cuándo borrarlas (no antes de validar en producción).
- Local: buffer pool InnoDB de 128 MB (los tiempos varían mucho). Pedir al DBA revisar `innodb_buffer_pool_size` en producción.
- Local: había un `php artisan queue:work` viejo (iniciado antes del refactor) que hace fallar los jobs nuevos →
  reiniciar workers (`php artisan queue:restart`) tras cada despliegue.

### Marcado para la Fase 3 de Tecnologías (alias y reglas estrictas)
- **REST, SOAP, AJAX**: activos, pero "rest" es palabra común en inglés → reglas estrictas (mayúsculas, contexto API/RESTful).
- **Transformers**: desactivado; reactivar con alias estrictos (Hugging Face Transformers, contexto ML).
- **31 protocolos/estándares/algoritmos desactivados** (Wi-Fi, TCP, UDP, SSL/TLS, SSH, SFTP, MQTT, AMQP, CoAP, LoRaWAN,
  Zigbee, IPsec, Kerberos, RADIUS, PKI, RSA, DSA, ECC, Diffie-Hellman, RC4, VAES, EtherChannel, NFS, AFS, STP, DHCP, NTP…):
  reconsiderar cuáles reactivar con alias estrictos.
- **41 duplicados** desactivados → pasan a alias de su principal; en la limpieza (Fase 4) sus ofertas se reasignan a la principal.
- Ambiguos a tratar con cuidado: Express (Express.js vs "American Express"/"express"), Unity (motor vs palabra),
  Swift (en lenguajes: lenguaje vs SWIFT bancario), Go (en lenguajes: sensible a mayúsculas + golang), Spring, Docker
  (0 % de precisión en reed), Excel.
- Cyberops y CompTIA A+ → dar de alta en Certificaciones si no existen.

---

## 5. Plan (histórico; completado el 2026-11-16)

**Antes de empezar cualquier paso:** recapturar la foto dorada de Lenguajes el mismo día
(`php83 docs/produccion/herramientas/foto_dorada_lenguajes.php capture <dir>`): la anterior quedó obsoleta por la fecha
y por mostrar 0 en el export.

### Paso 1 — Fase B: matcher genérico (validado en Lenguajes)

**Avance 2026-11-16 (sin commitear):** pasos B1 y B2 hechos, detenido antes de tocar scrapers.
- `app/Services/Matching/EntityTextMatcher.php` + `AliasSpec.php`; reglas en `database/data/entity_aliases/languages.php`
  (fuente única: tests y generador del SQL); `tests/Unit/EntityTextMatcherTest.php` (8 tests, 77 aserciones).
- Tabla `market_entity_aliases` (migración `2026_11_16_000001` + `ranking_lenguajes/11` y rollback `12`, 298 reglas;
  bloque generado con `herramientas/generar_aliases_sql.php`). Aplicado en local.
- `php artisan entities:match-audit --type=languages --semester=2026-s2` (chunks, ~56 MB, 21-105 s; CSV en
  `storage/app/match_audit/`). Resultado 2026-s2: Arbeitnow 2,5 % de precisión con texto completo; Adzuna 49 %
  (texto = extracto de ~500 caracteres); GetOnBoard 9 % (72 % sin texto); Greenhouse 0 %.
- Texto guardado: Adzuna/Arbeitnow/Jooble lo guardan en `requirements`, no en `description`; Computrabajo y Greenhouse no guardan texto.
- Pendiente de decisión: ¿JavaScript se infiere de Node.js/Next.js/Vue.js? (hoy no: 123 asociaciones rechazadas).
- `EntityTextMatcher` reutilizable por tipo de entidad + tabla `market_entity_aliases`
  (alias, sensible a mayúsculas, límites de palabra, contextos negativos).
- Reglas: R y Go sensibles a mayúsculas, `golang`, `C#`/`c sharp`, `F#`, Swift sin contexto bancario, `.NET`, etc.
- Aplicarlo en los 14 scrapers de lenguajes: los de **feed** (Arbeitnow — su API ignora `search` —, Greenhouse,
  GetOnBoard, …) descargan una vez y clasifican cada oferta por su texto; los de **búsqueda** solo asocian si el
  matcher lo confirma. Ojo: Adzuna y reed guardan solo un extracto de la descripción.
- Medir precisión antes/después y verdaderos positivos perdidos.
  Línea base (2026-s2): R 7 %, Go 12 %, Swift 3 %, DAX 1 %, Python 26 %, Java 17 %, SQL 17 %; Arbeitnow asocia
  cada oferta a ~17 lenguajes (0-15 % de precisión).
- Scripts SQL para el DBA (tabla de alias + datos) en `ranking_lenguajes/`, idempotentes con rollback y verificación.

### Paso 2 — Fase C: limpieza de `language_job`
- Revalidar asociaciones existentes con el matcher: comando con `--dry-run` que informe por lenguaje y fuente qué se borraría.
- Script SQL para el DBA con **tabla de respaldo** de las filas a borrar, borrado y rollback.
- Recalcular y comparar top 10 por periodo antes/después (R debería bajar de ~9 000 a ~600 en 2026-s2).

### Paso 3 — Fases 3 y 4 de Tecnologías
- Fase 3: el mismo matcher con alias/reglas de tecnologías (sección 4) en los 12 scrapers.
  Línea base de precisión (2026-s2): APIs 10 %, DevOps 16 %, Docker 3 %, REST 3 %, .NET 21 %, Linux 3 %, Express 0 %,
  MongoDB 1 %, jQuery 0 %, Excel 4 %, Kubernetes 63 %; Arbeitnow asocia cada oferta a ~87 tecnologías.
- Fase 4: limpieza de `technology_job` (dry-run por entidad y fuente, respaldo, reasignar ofertas de duplicados a su principal).
- Fase 5: recalcular y comparar top 10 por periodo.

### Paso 4 — Certificaciones con todo el criterio
Mismo orden que Tecnologías: diagnóstico → refactor a `EntityRankingService` (agregar `certifications` en
`config/entity_rankings.php`) + tablas/índices/activos/evolución/botón/export → entidades que no corresponden →
matcher (siglas PMP, CCNA, CISSP, ITIL con nombres completos) → limpieza → recálculo.
Scripts en `docs/produccion/ranking_certificaciones/`.

---

## 6. Producción: pasos pendientes (en orden)

> **Al DBA se le entrega UN SOLO archivo: `docs/produccion/DESPLIEGUE_RANKINGS_PRODUCCION.sql`** (generado con
> `python docs/produccion/herramientas/generar_despliegue_consolidado.py`; no editarlo a mano). Sin DELETE, TRUNCATE ni
> DROP; sin DELIMITER; idempotente. La reversión, `DESPLIEGUE_RANKINGS_ROLLBACK.sql`, no se entrega salvo que haga falta.
>
> **La limpieza de asociaciones queda fuera del despliegue** (no se ejecutan `entities:clean-associations` ni
> `ranking_lenguajes/13-15`, `ranking_tecnologias/12-14`, `ranking_certificaciones/09-11`). En su lugar, los rankings
> **ignoran sin borrar** las asociaciones que el matcher no confirma (`entities:exclude-associations`, ver
> `ranking_comun/README.md`). Lo que se pierde frente a la limpieza: las ofertas de las entidades duplicadas no pasan a
> su principal, y la copia de asociaciones de CompTIA A+/Cyberops desde tecnologías no se hace.

### Pasos, en orden (sin ningún cambio en el `.env`)

**DBA**
0. Respaldo de la base (recomendado; el script no borra datos). Coordinar con el operador que no corran
   `jobs:run-all` ni scrapers.
1. Ejecutar `DESPLIEGUE_RANKINGS_PRODUCCION.sql` en la base del Observatorio (1-3 min; mysql, Workbench o HeidiSQL).
2. Revisar las verificaciones del final del archivo (cada una indica el resultado esperado) y avisar al operador.

**Operador** (servidor de la aplicación)
3. Mantener detenidos `jobs:run-all` y los scrapers hasta el paso 8.
4. Desplegar el código.
5. ```sh
   php artisan optimize:clear
   php artisan queue:restart       # los workers deben cargar el código nuevo
   ```
6. ```sh
   php artisan entities:exclude-associations   # 1ª ejecución: evalúa todo y guarda la fecha de confianza (3-6 min)
   php artisan entities:exclusions             # comprobar: ACTIVAS, fecha de confianza y exclusiones por pivote
   ```
7. ```sh
   php artisan ranking:refresh-languages
   php artisan ranking:refresh-technologies
   php artisan ranking:refresh-certifications  # cada uno imprime "Exclusiones del matcher: APLICADAS"
   php artisan schedule:list                   # 0 7 * * * para los tres ranking:refresh-*
   ```
8. Reanudar `jobs:run-all` y los scrapers.
9. **Crons a quitar** (crontab del servidor o panel de tareas): `languages:take-snapshot`,
   `technologies:take-snapshot`, `certifications:take-snapshot` y los `*:backfill-all` si estuvieran programados.
   Revisar que `greenhouse:technologies` no esté programado fuera del repo.
10. Comprobar el cron `* * * * * php artisan schedule:run` y el worker
    `php artisan queue:work --sleep=3 --tries=3 --timeout=170`.
11. **Adzuna**: `adzuna:languages` = 26 entidades × 17 países = 442 llamadas por ciclo completo; `adzuna:certifications`
    = 79 × 17 = 1 343. Ambos retoman desde la última entidad procesada. Revisar el límite del plan en el panel de Adzuna.
12. Pedir al DBA: `SELECT @@global.time_zone, @@session.time_zone` e `innodb_buffer_pool_size`.

**Exclusión por título (2026-11-23, independiente del despliegue anterior)**: el DBA ejecuta
`docs/produccion/ofertas_exclusion/01_exclusion_titulos.sql` (80 s en local; detener scrapers mientras tanto); el
operador ejecuta `symfony php artisan optimize:clear`, `symfony php artisan queue:restart`,
`symfony php artisan job-offers:apply-title-exclusions` y los tres `symfony php artisan ranking:refresh-*`. Detalle en
`docs/produccion/ofertas_exclusion/README.md`.

**Mantenimiento**: volver a ejecutar `entities:exclude-associations` (y luego `ranking:refresh-*`) cuando cambien las
reglas del matcher o se reactive una entidad. **Apagado de emergencia**: `php artisan entities:exclusions --disable` y
`ranking:refresh-*` (para volver: `--enable` y `ranking:refresh-*`).

Reversión: `DESPLIEGUE_RANKINGS_ROLLBACK.sql` (desplegar antes el código anterior).

---

## 7. Estado del repositorio

Todo el trabajo está commiteado y subido en la rama `observer`:

| Commit | Contenido |
|---|---|
| `b048f75` | Optimización y Fase A del Ranking de Lenguajes (tablas de resumen, evolución, activos, botón, scrapers, migraciones 000001-000004) |
| `d84a4dd` | Servicio genérico `EntityRankingService`, comando/Job/trait genéricos, export en streaming y componentes compartidos |
| `439803e` | Fase 2 del Ranking de Tecnologías (incluidos entidades, carreras y export; migraciones 000005-000006) |
| `e927c61` | `docs/produccion`: scripts SQL, README, CSV de entidades, `PLAN_RANKINGS.md` y herramientas |
| `65d50cf` | Decisiones de la Fase A de Lenguajes (scripts 09/10) y pausa temporal de Arbeitnow y Greenhouse |
| `957b7dd` | Matcher (`EntityTextMatcher`), tabla `market_entity_aliases`, auditoría `entities:match-audit`, tests |
| `bd41c7d` | Matcher en los 14 scrapers de lenguajes, `OfferEntityLinker`, filas de `languages` nuevas |
| `fa93728` | Limpieza de `language_job` (`entities:clean-associations`, scripts 13-15) |
| `83c841c` | Fases 3 y 4 de Tecnologías (reglas, 12 scrapers, limpieza con reasignación, scripts 10-14) |
| `1990bdf` | Ranking de Certificaciones completo (scripts `ranking_certificaciones/01-11`) |
| `0e4f50d` | Búsqueda de certificaciones corregida (keywords, script 12-13) y despliegue consolidado `DESPLIEGUE_RANKINGS_PRODUCCION.sql` |
| `42c724d` | Exclusión de asociaciones sin borrar (`entity_association_exclusions`, `entities:exclude-associations`) |
| `b83044e` | Configuración de la exclusión en la base (`entities:exclusions --enable/--disable`, fecha de confianza automática), sin `.env` |
| `5b771a9` | Exclusión de ofertas por título (roles no TI): lista y pantalla, scrapers, `title_exclusion_id`, IA, análisis, eliminación con respaldo, filtros por periodo, export en cola, índices (`ofertas_exclusion/01-02`) |

**El commit `b048f75` no funciona solo**: sus archivos de Lenguajes ya usan `EntityRankingService`, que entra en
`d84a4dd`. **A partir de `d84a4dd` todo es coherente.** No conviene desplegar ni hacer checkout de `b048f75` aislado.

Fuera de los commits quedan, a propósito, `app/Console/Commands/getTrends/DiscoverLanguageGapsCommand.php`
(cambios locales que no son de este trabajo) y `MAPEO_OBSERVATORIO.md`.
