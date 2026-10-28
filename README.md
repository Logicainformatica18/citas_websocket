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

1. Aplicar los cambios de esquema. **En la base real se aplican con los scripts SQL de `docs/sql/`, no con migraciones** (ver "Scripts SQL para la base real" más abajo). Las migraciones equivalentes se mantienen para los tests y el desarrollo local; ahí, solo con `--path`, porque hay migraciones antiguas pendientes cuyas tablas ya existen:

   ```sh
   php artisan migrate --path=database/migrations/2026_10_28_000001_add_integration_fields_to_syllabus_table.php
   php artisan migrate --path=database/migrations/2026_10_28_000002_add_code_to_courses_table.php
   ```

2. Configurar el `.env` (ver `.env.example`):

   | Variable | Descripción |
   |---|---|
   | `SILABOS_INGEST_API_KEY` | Clave compartida con silabos_v2, que la envía en `X-Api-Key`. Si está vacía, el endpoint rechaza todo. Generar con `php -r "echo bin2hex(random_bytes(32));"`. |
   | `SILABOS_TBD_POSITION` | `first` (por defecto) o `last`: dónde se ordenan los periodos `TBD`/`TBD_EE` al desempatar versiones. `first`: se tratan como anteriores a cualquier periodo. |
   | `SILABOS_ANALYZE_AFTER_INGEST` | `true` para lanzar `AnalyzeCourseJob` (análisis con IA, con costo en OpenAI) tras cada ingesta. Por defecto `false`. |

3. Revisar el mapeo de carreras en `config/silabos_v2.php` → `career_map`.
   - Tiene solo las 9 carreras que usa el observatorio; 2 están pendientes de confirmar.
   - Es la única lista: silabos_v2 envía todos los sílabos aprobados con todas sus carreras y el observatorio decide.
   - La propuesta, con los cursos en común, está en `docs/mapeo-carreras-silabos.md`.
4. Asignar los códigos de curso (`courses.code` = código del curso en silabos_v2). En la base real, con `docs/sql/02_asignar_codigos.sql`. Equivale a:
   ```sh
   php artisan silabos:asignar-codigos --dry-run   # ver qué haría
   php artisan silabos:asignar-codigos             # aplicar
   ```
   - Toma `docs/correspondencia-cursos.csv` y solo las filas `exacto` sin aviso en `observacion` (103 cursos).
   - Nunca sobrescribe un código ni repite uno ya asignado.
   - El comando, además, envía a procesar los sílabos que estuvieran pendientes con esos códigos; el script SQL no.
   - El resto se vincula a mano en **Académico → Vinculación de cursos**, o con el campo "Código en silabos_v2" del formulario de cursos.

### Scripts SQL para la base real

Están en `docs/sql/` (MySQL 8). Cada script está dividido en bloques comentados: primero verificaciones de solo lectura con el resultado esperado, luego los cambios. Ejecutar los bloques en orden y detenerse ante el primer error o ante una verificación que no dé lo esperado. Cada `ALTER TABLE` es atómico en MySQL 8: si falla, no aplica nada de esa sentencia.

| Orden | Script | Qué hace |
|---|---|---|
| 1 | `01_integracion_silabos_up.sql` | Verifica que existan `syllabus`, `courses`, `jobs`, `failed_jobs`, `careers`, `career_course` y `migrations` (no las crea). Agrega a `syllabus` las columnas y los índices de la integración, y a `courses` la columna `code` única. No crea claves foráneas: al borrar un curso, la aplicación deja `syllabus.course_id` en NULL. Registra las dos migraciones en `migrations` con el batch siguiente al máximo, para que `php artisan migrate` no las repita. |
| 2 | `02_asignar_codigos.sql` | 103 `UPDATE` explícitos de `courses.code`, revisables uno por uno. Seguro de correr dos veces: solo toca cursos sin código, con el mismo id y nombre, y si el código no lo tiene otro curso. |
| 3 | — | Desplegar el código, configurar el `.env` y reiniciar el worker de la cola. |

Para revertir: `docs/sql/01_integracion_silabos_down.sql`. Deshace exactamente el paso 1 y borra sus filas de `migrations`. **No borra sílabos:** su primera verificación debe dar 0; si hay sílabos recibidos de silabos_v2, no se sigue (no caben en el esquema anterior). Al quitar `courses.code` se pierden los códigos asignados; el script los lista y se pueden volver a asignar con `02_asignar_codigos.sql`.

En silabos_v2 hay que aplicar su propio `docs/sql/01_integracion_silabos_up.sql` (ver su README). Conviene aplicar primero el del observatorio y activar el envío (`OBSERVATORIO_ENABLED=true`) al final.

### `POST /api/silabos/ingest`

**Cabeceras:** `X-Api-Key: <SILABOS_INGEST_API_KEY>` y `Content-Type: application/json`. El límite es de 120 peticiones por minuto.

| Respuesta | Significado |
|---|---|
| `202 {"id", "status": "queued"}` | Guardado y encolado para extracción. |
| `200 {"id", "status": "unchanged"}` | Ya existía con el mismo contenido, nombre, carreras, versión y periodo. No se reprocesa. |
| `200 {"id", "status": "superseded", "superseded_by"}` | Guardado, pero ya hay una versión vigente mayor. No se procesa ni toca los pivotes. |
| `200 {"id", "status": "ignored"}` | Guardado, pero no cumple la condición (a) ni la (b) (ver "Qué sílabos se procesan"). No se procesa ni llama a OpenAI, y no cuenta en su familia. |
| `200 {"id", "status": "pending_link"}` | Guardado y vigente, pero ningún curso tiene su código. No se procesa ni llama a OpenAI hasta vincularlo. |
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
  - Los periodos se normalizan: `202510` queda igual, `2025` pasa a `202500` y `TBD` va al principio (o al final, según `SILABOS_TBD_POSITION`).
  - `aprobado_at` no interviene.
- **Los no vigentes** quedan con `superseded_at` y `superseded_by`.
  - Si una versión menor llega después (por un reintento o por la carga inicial), se guarda ya reemplazada y no toca los pivotes.
- **Al procesar un sílabo de silabos_v2:**
  - los PDF del mismo curso quedan reemplazados;
  - un PDF que se suba después para ese curso se guarda, pero no pisa sus pivotes.
- **El análisis con IA** (`curriculum:analyze-course`) usa solo sílabos vigentes y prefiere el enlazado por `course_id`.
- **Curso:** se busca solo por `courses.code` (= `curso.codigo`, comparado exactamente). Nunca por nombre y nunca se crean cursos.
  - Si ningún curso tiene ese código, el sílabo queda `pending_link` (pendiente de vinculación), sin llamar a OpenAI.
  - Si vuelve a llegar cuando el código ya tiene curso, se procesa normalmente.
  - Los PDF subidos a mano siguen enlazándose por nombre, como antes.
- **Vinculación de cursos** (menú Académico, permiso `administrar`, con un contador de pendientes):
  - lista los sílabos `pending_link` con su código, nombre y carreras de silabos_v2 (las mapeadas resaltadas);
  - para cada uno, un select con buscador de los cursos que aún no tienen código, con la sugerencia preseleccionada y su porcentaje de similitud (el mismo cálculo del reporte de correspondencia; desde 60 %);
  - **Guardar** asigna el código al curso y procesa el sílabo. Si el código ya está en otro curso, o el curso ya tiene otro código, lo impide con un mensaje;
  - **No vincular** deja el sílabo como `ignored`. Si llega una versión nueva del mismo código, vuelve a quedar pendiente.
- **Qué sílabos se procesan.** Uno que cumpla al menos una condición:
  - **(a)** trae al menos una carrera de `career_map`;
  - **(b)** no trae ninguna, pero su código corresponde a un curso que ya pertenece a una carrera mapeada.

  Si no cumple ninguna se guarda como `ignored`. Si era el vigente de su familia, el siguiente vuelve a serlo y se procesa. Si se reenvía después de que su curso entró a una carrera mapeada, se vuelve a evaluar. Los marcados con "No vincular" no se reevalúan.
- **Carreras:** el curso se vincula solo a las carreras del sílabo que figuran en `career_map`, sin quitar las existentes. Con la condición (b) no se agrega ninguna: el curso conserva las que ya tenía.
- **Reporte por carrera:** `php artisan silabos:reporte-carreras [--modelo=isil]` muestra, por carrera, los sílabos vigentes procesados por la condición (a) y por la (b), los ignorados y los pendientes de vinculación. Los de la (b) cuentan en las carreras mapeadas de su curso. Aparte, las carreras de silabos_v2 sin mapeo.
- **Formulario de cursos** (`/courses`): tiene el campo "Código en silabos_v2", único y opcional; vacío se guarda como sin código.
  - Si el curso tiene código, avisa que los lenguajes, tecnologías y metodologías que se guarden ahí se sobrescribirán con la próxima versión aprobada de su sílabo.
  - Al cambiar un código existente pide confirmación, porque el curso deja de enlazarse a los sílabos del código anterior.
- **Botón "Sincronizar" de la página de carreras:** solo agrega o quita filas de `career_course` (curso ↔ carrera). No toca los cursos ni sus pivotes de lenguajes, tecnologías y metodologías.
  - Si se quita un curso de una carrera mapeada, la próxima ingesta de ese sílabo lo vuelve a vincular.
  - La ingesta nunca desvincula carreras.
  - Quitar un curso de su única carrera mapeada puede hacer que sus sílabos sin carrera mapeada dejen de cumplir la condición (b) y se ignoren en la próxima ingesta.
- **`POST /careers/{id}/sync-courses`** (permiso `administrar`): **reemplaza** todos los cursos de la carrera por la lista enviada; los que no vienen quedan desvinculados. El modal "Sincronizar" no la usa (usa `attach-course` y `detach-course`), pero la ruta existe.
  - Quitar así cursos de una carrera mapeada se revierte en parte con la próxima ingesta: se vuelven a vincular los cursos cuyos sílabos traen esa carrera (condición a).
  - Los que entraban solo por la condición (b) no se vuelven a vincular, porque la (b) depende justamente de esa relación.
  - Usarla con cuidado en las 9 carreras mapeadas.

### Revisión manual

En `storage/logs/silabos-revision.log` quedan registrados:

- los empates de versión con contenido distinto (el hash se calcula sobre `contenido.secciones`);
- las versiones que bajan en un periodo posterior;
- los periodos con formato desconocido;
- las carreras mapeadas a un slug que no existe;
- los sílabos ignorados por no tener ninguna carrera mapeada;
- los sílabos marcados como "No vincular".

### Tiempo real

Al terminar de procesarse un sílabo, de PDF o de silabos_v2, se emite `syllabus.processed` en el canal público `observatory`. La vista de sílabos lo escucha y actualiza la fila.

### Prueba manual

```sh
curl -X POST http://localhost:8000/api/silabos/ingest \
  -H "X-Api-Key: $SILABOS_INGEST_API_KEY" -H "Content-Type: application/json" \
  -d @payload.json
```
