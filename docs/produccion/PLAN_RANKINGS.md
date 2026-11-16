# Plan de los rankings del Observatorio (Lenguajes, Tecnologías, Certificaciones)

Documento para retomar el trabajo en una sesión nueva. Estado al **2026-11-16**.
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
- **Scrapers de certificaciones: leen `certifications.keywords` (JSON) sin decodificar** (GetOnBoard, RemoteOk, Remotive…)
  y buscan literalmente `["…"]`; encuentran poco. Corregirlo exige cambiar cómo cada uno lee la columna.
- Rutas de Certificaciones a métodos inexistentes (error 500): `/trending` → `trendingCertifications`,
  `/trend/{trend}/jobs` → `jobsByTechnologyTrend`. (Las de Lenguajes, ya anotadas, siguen igual.)
- `app/Exports/CertificationsWeeklyExport.php` y `certification_evolution_cache` quedan sin uso (no se borran).
- La administración de entidades no tiene botón para **reactivar** una entidad desactivada (se hace por SQL).
- Errores de TypeScript previos en las páginas de ranking (`tsc --noEmit`); `npm run build` compila igual.

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

0. **Respaldo completo de la base** (o, como mínimo, de `language_job`, `technology_job`, `certification_job`,
   `market_entities`, `languages`, `technologies`, `certifications`, `market_entity_career`, `job_offers`, `scraper_runs`).
   No correr `jobs:run-all` ni scrapers durante los pasos 1-6.
1. **DBA, Lenguajes** (`ranking_lenguajes/`): `01` → `02` → `05` → `06` → `09` → `11` → `13`.
2. **DBA, Tecnologías** (`ranking_tecnologias/`): `05` → `01` → `02` → `08` → `06` → `10` → `12`.
3. **DBA, Certificaciones** (`ranking_certificaciones/`): `01` → `02` → `05` → `07` → `09`.
4. **Desplegar el código** y en el servidor: `php artisan optimize:clear` y `php artisan queue:restart`.
5. **Limpieza** (la decide la aplicación, la aplica el DBA; los scripts usan DELIMITER → cliente `mysql`):
   ```sh
   php artisan entities:clean-associations --type=languages --dry-run      # informe (opcional)
   php artisan entities:clean-associations --type=languages                # → DBA: ranking_lenguajes/14
   php artisan entities:clean-associations --type=technologies             # → DBA: ranking_tecnologias/13
   php artisan entities:clean-associations --type=certifications           # → DBA: ranking_certificaciones/10
   ```
6. Recalcular:
   ```sh
   php artisan ranking:refresh-languages
   php artisan ranking:refresh-technologies
   php artisan ranking:refresh-certifications
   php artisan schedule:list   # 0 7 * * * para los tres
   ```
7. Verificación (solo lectura): `ranking_lenguajes/04` y `08`, `ranking_tecnologias/04`, `ranking_certificaciones/04`.
8. **Crons a quitar** (no están en el repo; crontab del servidor o panel de tareas): `languages:take-snapshot`,
   `technologies:take-snapshot`, `certifications:take-snapshot` y los `*:backfill-all` si estuvieran programados (sin
   `--legacy` ya no hacen nada). Revisar también que `greenhouse:technologies` no esté programado fuera del repo.
9. Comprobar el cron `* * * * * php artisan schedule:run` y el worker `php artisan queue:work --sleep=3 --tries=3 --timeout=170`.
10. **Adzuna**: `adzuna:languages` recorre las entidades de lenguaje activas, no las filas de `languages`: hoy 26 × 17
    países = 442 llamadas por ciclo completo (antes de la Fase A eran 28 entidades → 476). Las filas nuevas de
    `languages` agregan llamadas en Jooble (8 por lenguaje). Revisar en el panel de desarrollador de Adzuna el límite del
    plan (por minuto, día y mes) frente al consumo real de `adzuna:languages`, `:technologies` y `:certifications`
    (17 países cada uno); si no alcanza, bajar países o la frecuencia.
11. Pedir al DBA: `SELECT @@global.time_zone, @@session.time_zone` e `innodb_buffer_pool_size`.

Reversión por indicador: ver el README de cada carpeta (cada script tiene su rollback, probado en local).

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

**El commit `b048f75` no funciona solo**: sus archivos de Lenguajes ya usan `EntityRankingService`, que entra en
`d84a4dd`. **A partir de `d84a4dd` todo es coherente.** No conviene desplegar ni hacer checkout de `b048f75` aislado.

Fuera de los commits quedan, a propósito, `app/Console/Commands/getTrends/DiscoverLanguageGapsCommand.php`
(cambios locales que no son de este trabajo) y `MAPEO_OBSERVATORIO.md`.
