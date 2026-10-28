# Observatorio tecnológico

Procesa sílabos, extrae lenguajes, tecnologías y metodologías, y mide la alineación de cada curso con el mercado laboral.

- **Stack:** PHP 8.2+, Laravel 12, MySQL, Inertia + React. Cola `database`; websockets con Reverb.
- **Ejecutar:**

  ```sh
  composer install && npm install
  php artisan serve              # o Herd / Laragon
  npm run dev
  php artisan queue:work         # procesa los sílabos (PDF y silabos_v2)
  php artisan reverb:start       # actualizaciones en vivo
  ```

- **Tests:** `php artisan test --filter "SyllabusIngestTest|PeriodoOrdenTest"`. Usan sqlite en memoria.

## Ingesta de sílabos desde silabos_v2

Cuando un sílabo se aprueba en silabos_v2 (estado 5), silabos_v2 lo envía a este endpoint. El observatorio:

1. lo guarda;
2. lo pasa por la misma extracción que los PDF (`App\Services\Syllabus\SyllabusExtractionService`);
3. regenera los pivotes del curso (`course_language`, `course_technology`, `course_methodology`).

La alineación por curso (CCTC) se calcula al vuelo a partir de esos pivotes, así que refleja el sílabo nuevo en cuanto termina el job.

La carga antigua por PDF (`/silabos`) sigue funcionando igual.

### Puesta en marcha

1. Ejecutar solo las migraciones nuevas. Hay migraciones antiguas pendientes cuyas tablas ya existen, así que **no** se debe usar un `migrate` a secas:

   ```sh
   php artisan migrate --path=database/migrations/2026_10_28_000001_add_integration_fields_to_syllabus_table.php
   php artisan migrate --path=database/migrations/2026_10_28_000002_add_code_to_courses_table.php
   ```

2. Configurar el `.env` (ver `.env.example`):

   | Variable | Descripción |
   |---|---|
   | `SILABOS_INGEST_API_KEY` | Clave compartida con silabos_v2, que la envía en `X-Api-Key`. Si está vacía, el endpoint rechaza todo. Generar con `php -r "echo bin2hex(random_bytes(32));"`. |
   | `SILABOS_TBD_POSITION` | `last` o `first`: dónde se ordenan los periodos `TBD`/`TBD_EE` al desempatar versiones. **Valor provisional `last`, pendiente de confirmar.** |
   | `SILABOS_ANALYZE_AFTER_INGEST` | `true` para lanzar `AnalyzeCourseJob` (análisis con IA, con costo en OpenAI) tras cada ingesta. Por defecto `false`. |

3. Revisar el mapeo de carreras en `config/silabos_v2.php` → `career_map`.
   - Solo están activas las coincidencias exactas por nombre normalizado.
   - Los candidatos están comentados.
   - El listado completo está en `docs/mapeo-carreras-silabos.md`.

### `POST /api/silabos/ingest`

**Cabeceras:** `X-Api-Key: <SILABOS_INGEST_API_KEY>` y `Content-Type: application/json`. El límite es de 120 peticiones por minuto.

| Respuesta | Significado |
|---|---|
| `202 {"id", "status": "queued"}` | Guardado y encolado para extracción. |
| `200 {"id", "status": "unchanged"}` | Ya existía con el mismo contenido, nombre, carreras, versión y periodo. No se reprocesa. |
| `200 {"id", "status": "superseded", "superseded_by"}` | Guardado, pero ya hay una versión vigente mayor. No se procesa ni toca los pivotes. |
| `401` | Falta `X-Api-Key`, la clave es incorrecta o no está configurada. |
| `422 {"message", "errors"}` | Payload inválido. |

**Payload (contrato v1):**

```json
{
  "schema_version": 1,
  "origen": "silabos_v2",
  "modelo": "isil",
  "silabo_id": 1234,
  "codigo_familia": "IS-101",
  "version": 3,
  "curso": {
    "codigo": "IS-101", "nombre": "DESARROLLO WEB", "creditos": 4,
    "tipo_plantilla": 1, "periodo": "202610",
    "unidad_didactica": null, "departamento": null,
    "total_horas_periodo": "64", "total_horas_semana": "4"
  },
  "carreras": [{ "codigo": "CSEG", "nombre": "CIBERSEGURIDAD" }],
  "aprobado_at": "2026-10-28T15:04:05-05:00",
  "aprobado_at_fuente": "finalizar_revision",
  "contenido": {
    "secciones": {
      "generales": { "sumilla": "...", "competencia_espefica_ud": "..." },
      "unidades": [{ "numero": 1, "titulo": "...", "logro": "...", "sesiones": "3" }],
      "sesiones": [{ "numero": 1, "titulo": "...", "temas": "...", "procedimental": "..." }],
      "software_recursos": [{ "nombre": "...", "descripcion": "..." }],
      "bibliografia": "...",
      "otros": {}
    },
    "texto_plano": "Curso: DESARROLLO WEB (IS-101)\n\nSumilla:\n..."
  }
}
```

- `modelo`: `isil` o `scl` (Santa Catalina).
- `tipo_plantilla`: 1 Pregrado, 2 CT, 3 Educación Ejecutiva.
- `aprobado_at_fuente` indica de dónde sale la fecha de aprobación, que es **solo informativa**:
  - `finalizar_revision`: el momento de la aprobación;
  - `log_17` / `log_20`: en la carga inicial, el historial de estados;
  - `updated_at`: respaldo cuando no hay historial.
- `texto_plano` es lo que se pasa a la extracción (mínimo 50 caracteres).

### Versiones y vigencia

- **Identidad del sílabo:** `(modelo, silabo_id)`. Si llega otra vez, se actualiza la misma fila y nunca se duplica.
- **Familia de versiones:** `(modelo, codigo_familia)`. Un sílabo ISIL nunca reemplaza a uno SCL.
- **Vigente:** el mayor por `(version, periodo, silabo_id)`. La versión manda, y el periodo y el id solo desempatan.
  - Los periodos se normalizan: `202510` queda igual, `2025` pasa a `202500` y `TBD` va al final o al principio según `SILABOS_TBD_POSITION`.
  - `aprobado_at` no interviene.
- **Los no vigentes** quedan con `superseded_at` y `superseded_by`.
  - Si una versión menor llega después (por un reintento o por la carga inicial), se guarda ya reemplazada y no toca los pivotes.
- **Al procesar un sílabo de silabos_v2:**
  - los PDF del mismo curso quedan reemplazados;
  - un PDF que se suba después para ese curso se guarda, pero no pisa sus pivotes.
- **El análisis con IA** (`curriculum:analyze-course`) usa solo sílabos vigentes y prefiere el enlazado por `course_id`.
- **Curso:** se busca por `courses.code` (= `curso.codigo`).
  - Si no existe, se toma un curso sin código con el mismo nombre normalizado y se le asigna el código.
  - Si tampoco hay uno así, se crea.
- **Carreras:** se vinculan solo las que figuran en `career_map`, sin quitar las existentes.

### Revisión manual

En `storage/logs/silabos-revision.log` quedan registrados:

- los empates de versión con contenido distinto (el hash se calcula sobre `contenido.secciones`);
- las versiones que bajan en un periodo posterior;
- los periodos con formato desconocido;
- las carreras sin mapeo.

### Tiempo real

Al terminar de procesarse un sílabo, de PDF o de silabos_v2, se emite `syllabus.processed` en el canal público `observatory`. La vista de sílabos lo escucha y actualiza la fila.

### Prueba manual

```sh
curl -X POST http://localhost:8000/api/silabos/ingest \
  -H "X-Api-Key: $SILABOS_INGEST_API_KEY" -H "Content-Type: application/json" \
  -d @payload.json
```
