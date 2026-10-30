# Pasos para producción: integración silabos_v2 → observatorio

Guía para aplicar la integración en producción desde cero. Refleja lo que se probó en local el 2026-10-29 (bases `observatorio_2` y `silabo_2`, MySQL 8.0.46):

- los scripts de esquema de las dos bases;
- la aprobación real de un sílabo en silabos_v2 (sílabo 3570), con su envío, la extracción con OpenAI y la alineación del curso;
- el `--dry-run` de la carga inicial y un **lote de 10**.

**El resto de la carga (634 familias) no se ejecutó en local.** Las cifras de esta guía que no vienen del lote salen de una predicción de solo lectura sobre los datos de local.

Todos los archivos están en `citas_websocket/docs/produccion/`. Ningún script usa `USE`: en producción las bases pueden llamarse distinto. Cada uno indica al inicio en qué base va.

| Archivo | Base | Qué hace |
|---|---|---|
| `00_verificar_observatorio.sql` | observatorio | Solo lectura. Comprueba la estructura, que nada exista ya, las carreras, la categoría `other` y los 103 cursos. |
| `00_verificar_silabos.sql` | silabos_v2 | Solo lectura. Comprueba tablas y columnas, que nada exista ya, los jobs en cola y las familias aprobadas. |
| `01_observatorio_aplicar.sql` | observatorio | Cambios de esquema (18 columnas en `syllabus`, `courses.code` e índices) y su registro en `migrations`. |
| `01_observatorio_revertir.sql` | observatorio | Deshace el anterior. |
| `01_silabos_aplicar.sql` | silabos_v2 | Crea `observatorio_envios` y la registra en `migrations`. |
| `01_silabos_revertir.sql` | silabos_v2 | Deshace el anterior. |
| `02_observatorio_asignar_codigos.sql` | observatorio | 103 códigos de curso, buscando cada curso por **id y nombre**. |
| `02b_observatorio_asignar_codigos_por_nombre.sql` | observatorio | Alternativa al anterior: busca por **nombre exacto**. Antes lista los cursos que no encuentra. |
| `03_observatorio_respaldo_antes_de_carga.sql` | observatorio | Copia en tablas `respaldo_carga_*` lo que la carga cambia. |
| `03_observatorio_revertir_carga.sql` | observatorio | Deshace la carga usando ese respaldo. |

---

## 0. Antes de empezar

1. **Respaldo completo con `mysqldump` de las dos bases. Es obligatorio en producción: no se sigue sin él.** Guarda los archivos fuera del servidor de la base y comprueba que no estén vacíos y que terminen en `-- Dump completed`. Es la única forma de volver atrás si fallan los demás niveles de reversión (paso 11, nivel D). Los scripts `03_*` lo complementan, no lo reemplazan.
   ```sh
   mysqldump --single-transaction --routines --triggers --no-tablespaces -u <usuario> -p <base_observatorio> > observatorio-AAAAMMDD.sql
   mysqldump --single-transaction --routines --triggers --no-tablespaces -u <usuario> -p <base_silabos> > silabos-AAAAMMDD.sql
   ```
2. Anota los nombres reales de las dos bases.
3. **MySQL Workbench.** En local, dos ejecuciones no quedaron aplicadas: una vez solo se ejecutó el `USE` de cada bloque y otra faltó el `COMMIT`. Por eso:
   - Selecciona la base como predeterminada: doble clic en el esquema, que queda en negrita.
   - Activa **auto-commit**: menú *Query → Auto-Commit Transactions*, marcado.
   - Activa **detenerse ante errores**: el botón *"Toggle whether execution of SQL script should continue after failed statements"* debe estar en "stop".
   - Ejecuta **el archivo completo con Ctrl+Shift+Enter**, sin seleccionar nada. *Ctrl+Enter* ejecuta solo la sentencia bajo el cursor.
   - Cada consulta deja su resultado en una pestaña. Revisa siempre la última (resumen o comprobación final).
   - Después de cada script, confirma el cambio con su comprobación final. En local, un `UPDATE` que el cliente mostraba como hecho no estaba guardado.
4. **Código a desplegar:** la rama `feature/integracion-silabos` de los dos repositorios. El observatorio usa PHP 8.3 (Laravel 12) y silabos_v2 PHP 8.1 (Laravel 10).
5. **Nunca** ejecutes `php artisan migrate` contra estas bases. Las migraciones de la integración existen solo para los tests; el esquema se aplica con estos SQL.

---

## 1. Verificación (solo lectura)

| Paso | Base | Archivo |
|---|---|---|
| 1.1 | observatorio | `00_verificar_observatorio.sql` |
| 1.2 | silabos_v2 | `00_verificar_silabos.sql` |

La última pestaña de cada uno es el **resumen**: una fila por comprobación, con `OK`, `NO COINCIDE` (no seguir hasta resolverlo) o `AVISO` (revisar, no bloquea).

**Observatorio, qué mirar:**
- Las comprobaciones 5 a 12 describen el estado **antes** de aplicar: columnas sin cambios y nada creado todavía. Si alguna da NO COINCIDE, la base no es la esperada o ya se aplicó algo.
- **17 (103 cursos con id y nombre exactos):**
  - `OK` → usa `02_observatorio_asignar_codigos.sql`.
  - Si no → mira la consulta 2 (detalle por curso) y la comprobación **18**. Si la 18 da `OK`, usa `02b_observatorio_asignar_codigos_por_nombre.sql`. Si tampoco, ejecuta igual el `02b`: su bloque 1 lista los cursos que no encontrará, y esos se vinculan después desde la página.
- 16: deben existir las 7 carreras mapeadas (por slug).
- 15: la categoría `other` debe existir.
- 19 y la comprobación de jobs: deben estar en 0, o debes saber qué son esos jobs.

**silabos_v2, qué mirar:**
- 6 y 7: `observatorio_envios` y su migración no deben existir todavía.
- 8 y la consulta 2: **jobs pendientes en la cola.** Un worker los ejecutará al levantarse. En local había 2 `AnalizarRepeticiones` de septiembre, que generan embeddings con OpenAI. Decide antes del paso 5 qué hacer con los que haya.
- 9: familias aprobadas que entrarían en la carga. En local, 644.

---

## 2. Scripts SQL

En este orden, cada uno con su base seleccionada:

| Paso | Base | Archivo | Resultado esperado |
|---|---|---|---|
| 2.1 | observatorio | `01_observatorio_aplicar.sql` | Comprobación final: 2 filas en `migrations` con el mismo batch; las filas de `syllabus` con `source = upload` y los mismos estados que antes; 5 índices nuevos. |
| 2.2 | observatorio | `02_observatorio_asignar_codigos.sql` **o** `02b_observatorio_asignar_codigos_por_nombre.sql` | `cursos_con_codigo_despues` = 103 (más los que ya tuvieran código). La última consulta del `02` debe venir vacía. |
| 2.3 | silabos_v2 | `01_silabos_aplicar.sql` | 1 fila en `migrations`; 3 índices en `observatorio_envios`. |

Para el `02b`, ejecuta primero **solo su bloque 1**: selecciónalo y pulsa Ctrl+Shift+Enter. Revisa los cursos que lista y después ejecuta el archivo completo.

Si un script falla a la mitad, cada `ALTER TABLE` es atómico: la sentencia que falló no aplicó nada. Su script `*_revertir.sql` deshace las anteriores.

---

## 3. Despliegue del código

Con el envío **desactivado** (`OBSERVATORIO_ENABLED=false`, paso 4), así que todavía no viaja nada.

**Observatorio (citas_websocket):**
```sh
git fetch && git checkout feature/integracion-silabos && git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # página "Vinculación de cursos" y menú
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**silabos_v2:**
```sh
git fetch && git checkout feature/integracion-silabos && git pull
composer install --no-dev --optimize-autoloader
php artisan config:cache
```

No ejecutes `php artisan migrate` en ninguno de los dos.

---

## 4. Variables de entorno

Genera una clave compartida (la misma en los dos proyectos):

```sh
php -r "echo bin2hex(random_bytes(32));"
```

**Observatorio (`.env`):**

| Variable | Valor | Nota |
|---|---|---|
| `SILABOS_INGEST_API_KEY` | la clave generada | Vacía = el endpoint rechaza todo. |
| `SILABOS_TBD_POSITION` | `first` | Debe ser igual a `OBSERVATORIO_TBD_POSITION` de silabos_v2. |
| `SILABOS_ANALYZE_AFTER_INGEST` | `false` | `true` lanza un análisis con IA por curso (con costo en OpenAI). |
| `CATALOGO_PROMPT_MAX_TECNOLOGIAS` | `0` | Todas las tecnologías del catálogo en el prompt (~2 000 tokens). |
| `CATALOGO_PROMPT_CACHE_SEGUNDOS` | `300` | |
| `OPENAI_API_KEY` | (la que ya usa el observatorio) | La extracción usa `gpt-4o-mini`. |
| `QUEUE_CONNECTION` | `database` | |
| `BROADCAST_CONNECTION` | `reverb` si Reverb corre en producción; si no, `log` | Cada sílabo procesado emite un aviso en tiempo real. Si Reverb no está corriendo, ese aviso termina en `failed_jobs` (no afecta al sílabo). En local se usó `log` solo para el worker. |

**silabos_v2 (`.env`):**

| Variable | Valor | Nota |
|---|---|---|
| `OBSERVATORIO_ENABLED` | **`false` al principio** | Se cambia a `true` en el paso 6. |
| `OBSERVATORIO_URL` | URL pública del observatorio, sin `/` final | Debe ser alcanzable **desde el servidor de silabos_v2** (ver la prueba más abajo). |
| `OBSERVATORIO_API_KEY` | la clave generada | = `SILABOS_INGEST_API_KEY` del observatorio. |
| `OBSERVATORIO_TIMEOUT` | `15` | Segundos por envío. |
| `OBSERVATORIO_ALCANCE` | `isil:1,isil:2` | Modelos y tipos de plantilla que se envían. |
| `OBSERVATORIO_TBD_POSITION` | `first` | Igual que en el observatorio. |
| `QUEUE_CONNECTION` | `database` | En `.env.example` viene vacío: sin cola, el envío no sale. |

Después de cambiar un `.env`, en ese proyecto:
```sh
php artisan config:cache     # si la configuración está cacheada
php artisan queue:restart    # los workers releen la configuración
```

**Prueba de conexión, desde el servidor de silabos_v2.** En local, el sitio del observatorio no respondía (Herd detenido) y los envíos habrían fallado:
```sh
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$OBSERVATORIO_URL/api/silabos/ingest" -H "Accept: application/json"
# Esperado: 401 (sin clave). 000 = no hay conexión; revisar URL, DNS, firewall o HTTPS.
curl -s -w " [%{http_code}]\n" -X POST "$OBSERVATORIO_URL/api/silabos/ingest" \
     -H "Accept: application/json" -H "Content-Type: application/json" -H "X-Api-Key: <clave>" -d '{}'
# Esperado: 422 "Payload inválido" (clave aceptada; no guarda nada).
```

---

## 5. Workers de colas

Hace falta un worker permanente en **cada** proyecto (supervisor, systemd o el mecanismo que use el servidor):

| Proyecto | Comando | Procesa |
|---|---|---|
| Observatorio | `php artisan queue:work --sleep=3 --tries=3 --timeout=170` | La extracción de cada sílabo (`ProcessIngestedSyllabusJob`, una llamada a OpenAI de 4 a 6 s en local), los PDF subidos y los avisos en tiempo real. |
| silabos_v2 | `php artisan queue:work --queue=default,observatorio --sleep=3 --timeout=90` | Cola `default`: los jobs de siempre de silabos_v2 (`AnalizarRepeticiones`). Cola `observatorio`: solo los envíos al observatorio (`EnviarSilaboObservatorio`). |

**silabos_v2 necesita `--queue=default,observatorio`.** Los envíos van en su propia cola `observatorio`. Si el worker que ya existe se queda con `php artisan queue:work` a secas, solo atiende `default` y los envíos se acumulan sin salir. Cambia su comando (supervisor o lo que se use) al desplegar. El orden da prioridad a `default`: durante la carga inicial, los jobs de siempre no esperan detrás de los ~634 envíos.

Para pausar la integración **no hace falta detener este worker**: con `OBSERVATORIO_ENABLED=false` los envíos que ya estén en la cola terminan sin enviar nada (paso 11, nivel A), y los demás jobs siguen funcionando.

Antes de levantar el de silabos_v2, revisa la consulta 2 de `00_verificar_silabos.sql`. Si hay jobs antiguos en `default`, el worker los ejecutará; decide si eso está bien.

Tras cada despliegue o cambio de `.env`: `php artisan queue:restart`.

---

## 6. Respaldo y activación del envío

1. **Observatorio:** ejecuta `03_observatorio_respaldo_antes_de_carga.sql`. Su comprobación final debe mostrar las mismas filas en cada tabla y en su respaldo. Sin este paso no se puede revertir la carga (paso 11).
2. **silabos_v2:** `OBSERVATORIO_ENABLED=true`, luego `php artisan config:cache` y `php artisan queue:restart`.

Desde este momento, cada sílabo que se apruebe en silabos_v2 (estado 5) se envía al observatorio.

---

## 7. Carga inicial

Todo en el servidor de **silabos_v2**.

### 7.1 Dry-run (no envía ni registra nada)
```sh
php artisan observatorio:carga-inicial --dry-run -v
```
En local dio:
- 644 familias: 583 de `tipo_plantilla` 1 y 61 de tipo 2.
- 1 empate de versión con contenido distinto: la familia 30349.
- 1 familia con versión que baja: la 30044.

Antes del ajuste de TBD salían además 100 falsos positivos de "versión que baja", todos con un TBD como vigente. Ya no se marcan.

Lo que se espera en el observatorio con los datos de local (predicción, no medido):

| Resultado | Sílabos | OpenAI |
|---|---|---|
| Procesados (a): el sílabo trae una carrera mapeada | 88 | sí |
| Procesados (b): su curso ya está en una carrera mapeada | 12 | sí |
| Ignorados: sin carrera mapeada ni curso | 531 | no |
| Pendientes de vinculación: código sin curso | 13 | no |

- **Cursos:** se actualizan 100 de los 103 con código. 30024, 30025 y 30690 no tienen sílabo aprobado en el alcance.
- **PDF:** ~363 de 532 quedan marcados como reemplazados; se conservan, pero dejan de contar.
- **Costo:** ~100 llamadas a OpenAI, unos US$0.10 (gpt-4o-mini a US$0.15 y US$0.60 por millón de tokens).

### 7.2 Lote pequeño
```sh
php artisan observatorio:carga-inicial --limit=10
```
Toma las 10 primeras familias por tipo y código. En local fueron 4 procesados (30002, 30003, 30007 y 30009) y 6 ignorados, y cada extracción tardó de 4 a 6 s.

Revisa:
```sql
-- silabos_v2: los 10 en 'enviado', http 202 (queued) o 200 (ignored).
SELECT estado, respuesta, COUNT(*) FROM observatorio_envios GROUP BY estado, respuesta;
-- observatorio: estado de lo recibido.
SELECT status, COUNT(*) FROM syllabus WHERE source = 'silabos_v2' GROUP BY status;
```
```sh
# observatorio
php artisan silabos:reporte-carreras      # procesados (a)/(b), ignorados y pendientes por carrera
php artisan catalogo:nuevas               # entidades del catálogo creadas: revisar antes de seguir
```

**Revisión de `catalogo:nuevas`.** En el lote local se crearon 6 tecnologías:
- **DNS, SUDO, Microsoft Entra ID, Azure Information Protection y Windows** no tenían equivalente en el catálogo ni en el mercado.
- **"Windows Deployment Services (WDS)"** duplicaba a "WDS", que ya existe y está ligada al mercado.
- El comando sugirió "Microsoft Power BI" como parecida a "Microsoft Entra ID". Es un falso positivo por el prefijo común.

Si conviene, agrega alias en `config/catalogo.php`, commitea y despliega **antes de seguir**: así el resto de la carga ya los usa.

### 7.3 Resto
```sh
php artisan observatorio:carga-inicial
```
Se salta las familias ya enviadas. Encola las demás separadas 5 s entre sí (`--intervalo`), así que ~634 envíos tardan unos 53 minutos en salir.

Seguimiento:
```sql
-- silabos_v2
SELECT estado, COUNT(*) FROM observatorio_envios GROUP BY estado;
-- observatorio
SELECT status, COUNT(*) FROM syllabus WHERE source = 'silabos_v2' GROUP BY status;
SELECT COUNT(*) FROM failed_jobs;
```
- **Logs de revisión:**
  - observatorio: `storage/logs/silabos-revision.log` (empates, versiones que bajan, "No vincular") y `storage/logs/catalogo.log` (entidades creadas, categorías asignadas a `other`);
  - silabos_v2: `storage/logs/observatorio-revision.log`.
- **Envíos con error:** `php artisan observatorio:reenviar-pendientes --errores` (silabos_v2).
- **Al terminar:** `php artisan catalogo:nuevas` completo.
  - Si sugiere `market:fix-null-entities`, ten en cuenta que **escribe**: vincula por nombre exacto las tecnologías nuevas con su entidad de mercado y completa el `market_entity_id` vacío de las ofertas. Ya lo ejecuta `RunAllJobs`.

---

## 8. Vinculación de los pendientes

Los sílabos cuyo código no tiene curso quedan en **pendiente de vinculación**. En local serían 13, entre ellos 30078 Diseño de Infraestructura de Redes, 30160 Introducción a la Programación y 30653 Administración de Base de Datos.

**Opción A: directamente en producción.** Desde *Académico → Vinculación de cursos* (el menú muestra cuántos hay):
- *Vincular* asigna el código al curso elegido y procesa el sílabo.
- *No vincular* lo deja ignorado.

**Opción B: replicar lo decidido en local.**
1. En local, con la carga completa, vincula desde la página.
2. Exporta (solo lee la base y escribe un archivo):
   ```sh
   php artisan silabos:exportar-vinculaciones                 # busca los cursos por id y nombre
   php artisan silabos:exportar-vinculaciones --por-nombre    # si en producción los ids no coinciden (paso 1)
   ```
   Exporta:
   - los códigos asignados fuera de la lista de `02_*`;
   - los "No vincular", identificados por modelo y `silabo_id` de silabos_v2, que es el mismo en las dos bases.
3. En producción, **después de la carga**, ejecuta ese archivo en la base del observatorio. Su última consulta debe venir vacía.
4. En el servidor del observatorio:
   ```sh
   php artisan silabos:procesar-vinculados --dry-run   # qué se enviaría
   php artisan silabos:procesar-vinculados             # envía a procesar los que ya tienen curso
   ```
   Este paso es necesario porque asignar el código por SQL no procesa el sílabo; la página sí lo hace.

---

## 9. Alineación (`pe:recalculate`)

La alineación por curso se recalcula sola al procesar cada sílabo. En local, el curso 184 pasó de 0 (Crítica) a 70 (Media). El **tablero PE Alignment**, en cambio, lee resultados materializados que solo cambian al recalcular:

```sql
-- observatorio: ids de las carreras mapeadas
SELECT id, slug FROM careers WHERE slug IN ('arquitectura-de-datos', 'ciberseguridad',
  'ciencia-de-datos-e-inteligencia-artificial', 'computacion-en-la-nube', 'redes-y-comunicaciones',
  'ingenieria-de-sistemas', 'tecnologias-de-informacion');
```
```sh
php artisan pe:recalculate <career_id>      # uno por carrera; escribe pe_alignment_competency_results
```
Equivale al botón "Actualizar datos" del tablero.

---

## 10. Verificación final

```sql
-- silabos_v2: todo 'enviado'.
SELECT estado, COUNT(*) FROM observatorio_envios GROUP BY estado;
-- observatorio
SELECT status, COUNT(*) FROM syllabus WHERE source = 'silabos_v2' GROUP BY status;
SELECT COUNT(*) AS pdf_reemplazados FROM syllabus WHERE source = 'upload' AND superseded_by IS NOT NULL;
SELECT COUNT(*) AS cursos_con_codigo FROM courses WHERE code IS NOT NULL;
```
Además, prueba la carga por PDF (`/silabos`), que debe seguir funcionando igual, y aprueba un sílabo en silabos_v2 para ver que llega solo.

---

## 11. Cómo revertir si algo falla

De menor a mayor:

| Nivel | Qué deshace | Pasos |
|---|---|---|
| **A. Pausar** | Detiene todo envío al observatorio; nada se pierde. | silabos_v2: `OBSERVATORIO_ENABLED=false`, `config:cache` (si está cacheada) y `queue:restart`. Las aprobaciones nuevas ya no registran envíos, y los envíos que estaban en la cola `observatorio` terminan **sin enviar, sin error y sin reintento**: quedan en `observatorio_envios` como `pendiente`. El worker sigue corriendo y los demás jobs de silabos_v2 no se afectan. Para reanudar: `OBSERVATORIO_ENABLED=true`, `config:cache`, `queue:restart` y `php artisan observatorio:reenviar-pendientes --minutos=0`. |
| **B. Deshacer la carga** | Sílabos recibidos, pivotes, PDF reemplazados, carreras y categorías agregadas, y entidades nuevas sin uso. | A; detener el worker del observatorio; `03_observatorio_revertir_carga.sql`. Los pivotes vuelven **exactamente** al respaldo del paso 6. Para reenviar después: `observatorio:carga-inicial --forzar`. |
| **C. Quitar la integración** | Además, el esquema. | B; desplegar el código anterior del **observatorio** (funciona con el esquema nuevo); `01_observatorio_revertir.sql`; en silabos_v2, `01_silabos_revertir.sql` y luego el código anterior. Se pierden los códigos de curso; se pueden reasignar con `02_*`. |
| **D. Restaurar** | Todo. | El `mysqldump` del paso 0 (obligatorio). Se pierde además todo lo que entró a esas bases después del respaldo. |

---

## Ajustes que salieron de la prueba en local

- **Workbench:** ejecuta el archivo completo con auto-commit y confirma con la comprobación final de cada script (paso 0).
- **Versión que baja:** un TBD con la versión mayor ya no se marca, ni en la carga inicial ni en la ingesta. Eran 100 falsos positivos.
- **Catálogo:** la extracción pasa el catálogo a la IA y unifica nombres (`config/catalogo.php`). Hay alias para 7 pares duplicados: Azure, Adobe Photoshop, SQL Server, Power BI, NOSQL, Unity y Virtual Box. Revisa `catalogo:nuevas` después del lote pequeño.
- **Conectividad:** prueba la URL del observatorio desde el servidor de silabos_v2 antes de activar el envío (paso 4).
- **Cola de silabos_v2:** puede tener jobs antiguos que el worker ejecutará (paso 1).
- **Cola propia para los envíos:** `EnviarSilaboObservatorio` va en la cola `observatorio`; el worker de silabos_v2 atiende `--queue=default,observatorio` (paso 5). Con `OBSERVATORIO_ENABLED=false` el job no envía y deja el envío `pendiente`, así que para pausar ya no hay que detener el worker, que también procesa `AnalizarRepeticiones` (paso 11).
- **Tiempo real:** sin Reverb, los avisos van a `failed_jobs`; usa `BROADCAST_CONNECTION=log` o levanta Reverb (paso 4).
- **Vinculaciones por SQL:** después de importarlas, ejecuta `silabos:procesar-vinculados` (paso 8).
- **Reversión de la carga:** sin el respaldo `03_*` no se pueden recuperar los pivotes de los cursos (pasos 6 y 11).
- **Log del catálogo:** los tests ya no escriben en `storage/logs/catalogo.log`.
