# Pendientes del Observatorio

Hallazgos sin resolver al 2026-10-05, en la base `observatorio_2`. Están ordenados por prioridad.

## Alta

- **Etiquetado de lenguajes por búsqueda.** Cada oferta recibe el lenguaje con el que se buscó en el portal; nadie verifica que aparezca en el texto. Solo el 7,5 % de las 160 373 relaciones de `language_job` tiene evidencia en el título; por ejemplo, R tiene 9 337 relaciones y apenas 18 con evidencia. Propuesta: una columna `origin` (búsqueda o texto) y un detector con reglas estrictas para nombres ambiguos.
- **Arbeitnow asigna todos los lenguajes a cada oferta.** La API ignora el parámetro `search`, así que cada consulta devuelve la misma página. Por eso 3 022 ofertas tienen 22 lenguajes cada una. El filtro por texto solo se aplica cuando la API no devuelve resultados (`ArbeitnowByLanguagesCommand`).
- **Reparación de ubicaciones.** `observatorio:reparar-ubicacion-ofertas` corrige 1 507 ofertas de Computrabajo cuyo país no coincide con el dominio; solo se ha ejecutado con `--dry-run`. La opción `--geocodificar` cubre 645 ofertas y unas 40 ciudades. Antes de aplicarla hay que revisar los homónimos dentro de un mismo país: por ejemplo, «San Nicolás» se resolvió en San Nicolás de los Arroyos y no en el barrio de Buenos Aires. Después de aplicarla hay que:
  - ejecutar `companies:backfill-all 2026`;
  - subir `geo_indicator:version`;
  - limpiar la caché `vera_sql_countries`;
  - refrescar los widgets 77, 78, 84, 85, 91 y 114.

## Media

- **Catálogo de lenguajes.** IDC y OECD son organizaciones, no lenguajes. Se tomaron de los prompts de tendencias, tienen unas 3 100 relaciones cada una y su duplicado provocó 22 corridas fallidas de `computrabajo:languages`. JSON, XML, CLI y GNU/Linux no son lenguajes de programación: conviene reclasificarlos como tecnologías o fusionarlos con ellas. Además, convendría agregar un tipo (programación, consulta o marcado).
- **Descripciones de Computrabajo.** Los comandos no consultan la página de detalle. `scrapeJobDetail()` nunca se llamó y su selector ya no existe; la descripción está en el JSON-LD `JobPosting`. Propuesta: un Job en cola, solo para ofertas nuevas, con 1 petición cada 3 s y backoff ante 429/403. GetOnBoard ya envía la descripción y se guardará a partir de la próxima corrida.
- **Scheduler de Laravel 12.** El método `schedule()` de `app/Console/Kernel.php` probablemente no se ejecuta, porque `bootstrap/app.php` no usa `withSchedule` y `routes/console.php` no programa nada. Además, `scrape:computrabajo` no existe como comando.
- **Catálogo unificado para la integración con ISILabos.** Los sílabos crean entradas por nombre exacto (`firstOrCreate`) sin `market_entity_id`. Los cálculos de alineación usan dos claves: el id de catálogo y `market_entity_id`. El catálogo tiene sinónimos sin unificar (S3, Power BI, Linux…). Propuesta:
  - usar `market_entities` como identificador único;
  - crear una tabla `entity_aliases`;
  - compartir un `TechMentionDetector` entre ofertas y sílabos;
  - identificar el curso con un id externo en lugar de su nombre.

## Baja

- **Ruta de refresh de widgets.** `DashboardWidgetController::refresh()` existe pero no tiene ruta. Los widgets muestran `rows` congelados en la base de datos hasta que alguien los recree.
- **`technology_metrics.countries_breakdown`.** Es un contador en memoria del scraper y no se puede recalcular desde `job_offers`. 500 filas de Computrabajo tienen claves de país fuera de sus dominios o numéricas (por ejemplo, `-77`). Hay que limpiarlas a mano o documentarlas como dato histórico no fiable.
