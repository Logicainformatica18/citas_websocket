# Plan de los rankings del Observatorio (Lenguajes, Tecnologías, Certificaciones)

Documento para retomar el trabajo en una sesión nueva. Estado al **2026-11-16**.
Rama de trabajo: `observer`. **Nada de esto está commiteado todavía** (ver "Estado del repositorio").

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

## 3. Estado actual

### Lenguajes — hecho y probado
- Tablas de resumen, índices, comando, botón, evolución como foto, export en streaming: ✅
- Filtro de activos en ranking/evolución/export/14 scrapers; "eliminar" desactiva; `$fillable` corregido: ✅
- IDC y OECD desactivadas (organizaciones). ✅
- Página: 35,7 s → ~0,1 s. Recálculo completo: 12-45 s (depende del caché de InnoDB local).
- Refactor a `EntityRankingService` validado con **foto dorada: 0 diferencias** (antes del cambio de mostrar ceros en el export).
- Scripts DBA: `ranking_lenguajes/01-08` (ver su README).

### Tecnologías — Fase 2 hecha y probada
- Página: 15,0 s / 32,0 s → ~0,3 s. Recálculo completo: 77-200 s (buffer pool local de 128 MB); un semestre: 25-80 s.
- Corregidos 3 bugs de cálculo: máximo de tendencias de cualquier tipo de entidad (era IDC, 163); `reportes_analizados`
  de todos los tipos; normalización laboral contra el grupo de ofertas sin entidad (11 243 > Docker).
- Filtro `ranking_type` eliminado (se leía y nunca se aplicaba; la interfaz no tenía control).
- 140 entidades desactivadas (script 06, aprobado): organizaciones, normas/marcos, conceptos genéricos,
  protocolos/algoritmos, otro indicador (CompTIA A+, Cyberops), Transformers (ambiguo) y 41 duplicados → quedan **312**.
  Detalle: `ranking_tecnologias/entidades_propuesta.csv`.
- Carreras migradas a `market_entity_career` (481 pares, script 08). Filtro por carrera verificado: mismas tecnologías y
  posiciones en las 9 carreras × 2 semestres, más 30 pares (tecnologías de IA) que ya estaban en `market_entity_career`.
- Foto de la semana actual = ranking principal (312/312). Export semanal: 20 000+ filas con 128 MB.
- Scripts DBA: `ranking_tecnologias/01-09` (ver su README).

### Certificaciones — sin empezar
Solo diagnosticado de pasada: tiene el mismo patrón (cálculo en vivo, `certification_evolution_cache`,
`certifications:take-snapshot`/`backfill-all`) y usa `JobMarketStatusController` (ya cacheado).

---

## 4. Pendientes conocidos

### Decisiones pendientes del usuario (de la Fase A de Lenguajes, nunca confirmadas)
- 8 entidades de lenguaje creadas por IA el 2026-11-12: Perl, Ruby, VBA, Lua, COBOL (propuesta: mantener);
  Shell (desactivar hasta Fase B; luego "Shell scripting" con alias Bash/sh), Ada (desactivar: ambiguo),
  F# (mantener, corregir slug `f` → `fsharp`).
- Vincular `languages` Go/Rust/Scala (ids 37/38/39) con sus entidades 3901/3902/3912.
- `languages` GNU/Linux (125) y CLI (126): `enabled = 0` (no son lenguajes; tienen cursos vinculados, no borrar).

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

## 5. Plan restante (en orden; detenerse al final de cada paso)

**Antes de empezar cualquier paso:** recapturar la foto dorada de Lenguajes el mismo día
(`php83 docs/produccion/herramientas/foto_dorada_lenguajes.php capture <dir>`): la anterior quedó obsoleta por la fecha
y por mostrar 0 en el export.

### Paso 1 — Fase B: matcher genérico (validado en Lenguajes)
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

1. **DBA, Lenguajes** (`ranking_lenguajes/`): respaldo → `01` → `02` → `05` → `06` → `04` y `08` (verificación).
2. **DBA, Tecnologías** (`ranking_tecnologias/`): respaldo de `technology_job`, `market_entities`, `market_entity_career` →
   `05` → `01` → `02` → `08` (carreras) → `06` (entidades) → `04` (verificación).
3. **Desplegar el código.**
4. En el servidor:
   ```sh
   php artisan optimize:clear
   php artisan ranking:refresh-languages
   php artisan ranking:refresh-technologies
   php artisan queue:restart          # obligatorio: los workers deben cargar el código nuevo
   php artisan schedule:list          # 07:00 ranking:refresh-languages y ranking:refresh-technologies
   ```
5. **Crons a quitar** (no están en el repo; crontab del servidor o panel de tareas):
   `languages:take-snapshot`, `technologies:take-snapshot`, y `languages:backfill-all` / `technologies:backfill-all`
   si estuvieran programados. (Sin `--legacy` ya no hacen nada, pero conviene quitarlos.)
6. Comprobar que existe el cron `* * * * * php artisan schedule:run` (si no, las 07:00 no se ejecutan; `jobs:run-all`
   igual recalcula al final).
7. Worker de colas: `php artisan queue:work --sleep=3 --tries=3 --timeout=170` (el botón lo necesita).
8. Pedir al DBA: `SELECT @@global.time_zone, @@session.time_zone` e `innodb_buffer_pool_size`.

---

## 7. Estado del repositorio

Todo está **sin commitear** en la rama `observer` (archivos nuevos en `app/Services/Ranking`, `app/Console/Commands/Ranking`,
`app/Http/Controllers/Dashboard/Concerns`, `app/Support/Xlsx`, `config/entity_rankings.php`, migraciones
`2026_11_12_00000[1-6]`, `resources/js/components/ranking`, `docs/produccion/ranking_*`, `docs/produccion/herramientas`
y modificaciones en controladores, modelos, 26 scrapers, rutas, `RunAllJobs.php` y páginas). Conviene commitear por
partes antes de la Fase B (Lenguajes / refactor genérico / Tecnologías), excluyendo `DiscoverLanguageGapsCommand.php`
y `MAPEO_OBSERVATORIO.md`, que no son de este trabajo.
