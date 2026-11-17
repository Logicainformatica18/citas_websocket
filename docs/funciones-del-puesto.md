# Funciones del puesto en `job_offers`

Las ofertas laborales guardan ahora, cuando la fuente lo permite, **las funciones del puesto**
(lo que la persona va a hacer) separadas de la descripción y de los requisitos. Sirve para
analizar qué tareas pide el mercado por carrera, tecnología o lenguaje sin depender del texto
completo de la descripción, que mezcla empresa, requisitos y beneficios.

## Columnas

| Columna | Tipo | Contenido |
|---|---|---|
| `functions` | `TEXT NULL` | Funciones en texto plano: una línea por párrafo, `- ` delante de cada ítem de lista. |
| `functions_headline` | `VARCHAR(255) NULL` | Título de la sección en la fuente: "Funciones del cargo", "What you'll do", "Major Duties", "こんなことやります"… |
| `functions_origin` | `VARCHAR(20) NULL` | `field`: la fuente trae las funciones en un campo propio. `heuristic`: se extrajeron de la descripción con `FunctionsSection`. |

Reglas:
- Si no hay funciones, las tres columnas quedan en `NULL`, y siempre van juntas: no hay `functions` sin `functions_origin`, ni al revés.
- Al volver a encontrar una oferta ya guardada, los scrapers **solo completan columnas que estén en `NULL`** y nunca sobrescriben.

## Por fuente

Cobertura observada en las pruebas del 2026-11-17 (muestras reales de cada API o sitio).

| Fuente | Estado | De dónde salen | Cobertura observada | Comandos modificados |
|---|---|---|---|---|
| Get on Board | Campo propio | `data.attributes.functions` y `functions_headline` de `/search/jobs` | 12/12 (`getonboard:import --query="qa automation"`) | `getonboard:languages`, `:technologies`, `:methodologies`, `:import`, `:competencies`, `:certifications`, y `JobOfferController::import` |
| Wantedly | Campo propio | `description` de `/api/v1/projects`: es la sección "こんなことやります" ("Esto es lo que harás") completa | 50/50 (47 nuevas y 3 existentes, `wantedly:methodologies`) | `wantedly:languages`, `:technologies`, `:methodologies`, `:competencies` |
| USAJOBS | Campo propio | `MatchedObjectDescriptor.UserArea.Details.MajorDuties` | Sin verificar en vivo (falta `USAJOBS_API_KEY`); cubierto con tests | `usajobs:languages`, `:technologies`, `:methodologies`, `:competencies` |
| Greenhouse | Heurística | `content` de `/boards/{empresa}/jobs?content=true` | 79/85 en muestras (Discord, GitLab, Cloudflare); 44/44 en la corrida de Brex | `greenhouse:languages`, `:technologies`, `:methodologies`, `:certifications`, `:competencies` |
| Jobicy | Heurística | `jobDescription` | 33/50 en muestra; 43/62 en la corrida de `jobicy:methodologies` | `jobicy:languages`, `:technologies`, `:methodologies`, `:certifications`, `:competencies` |
| RemoteOK | Heurística | `description` | 55/97 en muestra; 2/3 en la corrida de `remoteok:methodologies` | `remoteok:languages`, `:technologies`, `:methodologies`, `:certifications`, `:competencies` |
| Selecta (HiringRoom) | Heurística | `.main__description` del detalle de cada aviso (ya se descargaba) | 8/12 en muestra; 13/20 en el listado del 2026-11-17 | `selecta:languages` |
| Arbeitnow, Remotive, Reed, Computrabajo | No implementado | Se podría con la heurística (Reed exige pedir el detalle de cada oferta) | — | — |
| Adzuna | No disponible | `description` viene recortada a 500 caracteres | — | — |
| Jooble | No disponible | Solo trae `snippet` (y hoy la API responde 403) | — | — |
| BuiltIn | No disponible | El RSS ya no existe | — | — |
| JobsDataFeeds | No verificable | Sin clave de RapidAPI | — | — |

### Código

- `App\Support\HtmlText::toPlain()`: convierte HTML a texto plano y conserva párrafos y listas.
- `App\Support\GetOnBoardFunctions`: mapeo de Get on Board (`fromAttributes`, `fillMissing`).
- `App\Support\JobFunctions`: mapeo para el resto de las fuentes.
  - `fromField()` y `fromList()`: funciones que vienen en un campo propio.
  - `fromWantedly()` y `fromUsajobs()`: el mapeo de cada una de esas dos fuentes.
  - `fromDescription()`: aplica la heurística sobre la descripción.
  - `fillMissing()` y `fillMissingFor()`: completan una oferta existente sin pisar nada.
  - `withOrigin()`: agrega el origen a lo que devuelve `GetOnBoardFunctions`.
- `App\Support\FunctionsSection::extract()`: la heurística.

Cada comando solo llama a estas clases; no repite lógica.

## Cómo funciona la heurística (`FunctionsSection`)

Prioriza **precisión sobre cobertura**: ante la duda devuelve `null`.

1. La descripción se pasa a texto plano con `HtmlText::toPlain()`. Antes se adapta la entrada:
   - si es texto plano, sus saltos de línea se convierten a `<br>`;
   - si es HTML escapado como entidades (Greenhouse: `&lt;p&gt;`), se decodifica una vez.
2. Se buscan **encabezados de funciones** de una lista cerrada (`HEADINGS`, en inglés, español y alemán): "Responsibilities", "What you'll do", "Funciones", "Responsabilidades", "Aufgaben"…
   - El encabezado tiene que ocupar su propia línea: corta (70 caracteres o menos), sin viñeta y sin terminar en punto, coma o punto y coma.
   - Se admiten sufijos como "What you'll do **as a Customer Engineer**".
   - **No cuentan** "The role", "Your impact" ni "Role overview", porque suelen introducir prosa y no una lista de funciones.
3. La sección se recoge línea a línea hasta que aparece alguno de estos casos:
   - un encabezado con una palabra de cierre (`STOP`): requisitos, qualifications, benefits, "Must Haves", "What we look for", salary…;
   - otro encabezado de funciones;
   - un encabezado que habla del candidato o de la empresa (`OTHER_SECTION`: you, we, our, why, how, tu, nosotros, wir… o que termina en "?");
   - prosa larga después de una lista, que marca que la lista terminó.

   Las líneas de metadatos `Etiqueta: valor` ("Location:", "Reports to:"…) se descartan.
4. La sección **se rechaza** en cualquiera de estos casos:
   - queda vacía;
   - no tiene lista y no la cierra ningún encabezado;
   - supera 40 líneas o 6.000 caracteres.
5. Si hay varias secciones candidatas, se elige **la primera que sea una lista**. Ejemplo: en Brex, "What you'll do" es un párrafo y la lista real está en "Responsibilities".

### Agregar un encabezado nuevo

1. Agrégalo a `FunctionsSection::HEADINGS`: en minúsculas, con apóstrofo recto (`'`) y sin ":" final. Ejemplo: `'tus principales funciones'`.
2. Si la sección siguiente no se corta bien, agrega la palabra que la abre a `STOP` (sin `\b` final, para que también matchee plurales).
3. Agrega un fixture real a `tests/Fixtures/FunctionsSection/` y un test en `tests/Unit/FunctionsSectionTest.php`. Incluye también un caso que deba devolver `null`.
4. Corre `php vendor/bin/phpunit tests/Unit/FunctionsSectionTest.php`.

## Comandos para el operador

```bash
# 1. Revisar qué migraciones están aplicadas. Las dos de este trabajo deben figurar "Pending".
#    No ejecutar "php artisan migrate" a secas: hay otras migraciones pendientes que fallarían.
php artisan migrate:status

# 2. Columnas functions y functions_headline. En local tardó 44 s (403.531 filas).
php artisan migrate --path=database/migrations/2026_11_17_000001_add_functions_to_job_offers_table.php --force

# 3. Columna functions_origin + relleno de 'field' en las ofertas de Get on Board con funciones.
#    En local tardó 18 s (12 filas a rellenar). El relleno va por lotes de 5.000 ids.
php artisan migrate --path=database/migrations/2026_11_17_000002_add_functions_origin_to_job_offers_table.php --force

# 4. Confirmar que ambas figuran como "Ran".
php artisan migrate:status
```
- Las dos migraciones se pueden repetir sin riesgo, porque revisan si la columna ya existe antes de crearla.
- Los tiempos crecen con el tamaño de la tabla. El `ALTER` tardó más de lo esperable para agregar una columna; probablemente MySQL reconstruye la tabla por el índice FULLTEXT `ft_job_content`, aunque no lo verifiqué. Conviene correrlas cuando no haya scrapers en ejecución.
- La migración corregida `2025_09_24_124847_create_job_offers_table.php` no necesita comando: donde `job_offers` ya existe figura como ejecutada y además está protegida con `hasTable`.

## Consultas de verificación

```sql
-- Cobertura por fuente: total, con funciones y por origen
SELECT source,
       COUNT(*)                                        AS total,
       SUM(functions IS NOT NULL)                      AS con_funciones,
       ROUND(100 * SUM(functions IS NOT NULL) / COUNT(*), 1) AS pct,
       SUM(functions_origin = 'field')                 AS origen_field,
       SUM(functions_origin = 'heuristic')             AS origen_heuristic
FROM job_offers
GROUP BY source
ORDER BY total DESC;

-- Lo mismo, solo ofertas creadas en los últimos 30 días (refleja el comportamiento actual de los scrapers)
SELECT source,
       COUNT(*)                   AS total,
       SUM(functions IS NOT NULL) AS con_funciones,
       SUM(functions_origin = 'field')     AS origen_field,
       SUM(functions_origin = 'heuristic') AS origen_heuristic
FROM job_offers
WHERE created_at >= NOW() - INTERVAL 30 DAY
GROUP BY source
ORDER BY total DESC;

-- Consistencia: ambas consultas deben devolver 0
SELECT COUNT(*) AS functions_sin_origen FROM job_offers WHERE functions IS NOT NULL AND functions_origin IS NULL;
SELECT COUNT(*) AS origen_sin_functions FROM job_offers WHERE functions IS NULL AND functions_origin IS NOT NULL;

-- Encabezados más frecuentes por fuente (para revisar la heurística)
SELECT source, functions_headline, COUNT(*) AS n
FROM job_offers
WHERE functions_headline IS NOT NULL
GROUP BY source, functions_headline
ORDER BY source, n DESC;
```

## Problemas fuera de alcance

Detectados durante este trabajo; quedan para otras tareas.

1. **Computrabajo:** el selector `.description_offer .t_word_wrap` ya no existe (la descripción ahora está en `div[div-link="oferta"] p.mbB`). Las ofertas de los últimos 30 días se guardaron sin descripción.
2. **Jooble:** la API devuelve 403 y no guarda ofertas desde el 2026-05-05.
3. **Claves faltantes:** `USAJOBS_API_KEY` y `JOBSDATAFEEDS_RAPIDAPI_KEY`.
4. **Fuentes que no se ejecutan desde febrero de 2026:** Remotive, USAJOBS y Wantedly. Las APIs de Remotive y Wantedly responden bien (Wantedly guardó 47 ofertas el 2026-11-17), así que el problema parece de programación de tareas.
5. **USAJOBS competencies** (`Scraping/USAJOBSByCompetenciesCommand`): usa `RegionHelper` sin importarlo y fallaría al crear una oferta.
6. **Greenhouse:** no reconoce el país de las ubicaciones de Cloudflare ("Hybrid", "Distributed", "In-Office") ni de Discord ("San Francisco Bay Area", "Remote (U.S.)"), así que descarta sus ofertas. El país real estaría en el campo `offices`, que el comando no usa.
7. **BuiltIn:** el RSS ya no existe (la URL devuelve la página HTML normal); nunca guardó ofertas.
8. **RemoteOK, texto mal codificado:** algunas descripciones llegan con caracteres corruptos desde la fuente ("â¢" en lugar de "•", "partnerâs" en lugar de "partner's"). Se guardan tal cual en `description` y `functions`, y las viñetas "â¢" no se reconocen como lista.
9. **Jobicy, duplicados:** los comandos de Jobicy detectan duplicados por `external_id` **sin filtrar por fuente** (`JobOffer::where('external_id', …)->exists()`). Una oferta de otra fuente con el mismo `external_id` haría que se descarte una de Jobicy. Para completar funciones se usa `JobFunctions::fillMissingFor('Jobicy', …)`, que sí filtra por fuente.
