# Mapeo de carreras silabos_v2 → observatorio

El observatorio usa **solo 9 carreras**. Un sílabo de silabos_v2 se envía únicamente si su curso pertenece al menos a una de ellas. El payload lleva solo esas carreras.

Si aun así llega un sílabo sin ninguna carrera mapeada, el observatorio lo guarda como `ignored` y no lo procesa.

El mapeo debe ser el mismo en los dos lados:

- **Observatorio:** `config/silabos_v2.php` → `career_map`.
- **silabos_v2:** `config/services.php` → `observatorio.carreras`.

## Cómo se propuso

Se tomó cada carrera del observatorio con sus cursos (`career_course`). Se contó cuántos de esos cursos, por nombre normalizado, son sílabos aprobados (estados 5, 6, 18 y 19) de cada carrera de silabos_v2. El análisis se hizo el 2026-10-28 sobre `observatorio_2` y `silabo_2`.

Hay muchos cursos compartidos entre carreras, así que una carrera de silabos_v2 suele coincidir en parte con varias del observatorio. Aquí se muestran la mejor y la segunda.

| Carrera del observatorio | Código silabos_v2 | Nombre en silabos_v2 | Cursos en común | Segunda opción | Estado |
|---|---|---|---|---|---|
| Arquitectura de Datos | `CADT` | ARQUITECTURA DE DATOS | 25 / 31 | BTIN, CNUB, CISI (15) | Activo |
| Ciberseguridad | `CSEG` | CIBERSEGURIDAD | 25 / 31 | CINR (16) | Activo |
| Ciencia de Datos e Inteligencia Artificial | `CCDT` | CIENCIA DE DATOS | 26 / 31 | CADT (14) | Activo |
| Computación en la Nube | `CNUB` | COMPUTACIÓN EN NUBE | 28 / 31 | BTIN, CISI (16) | Activo |
| Redes y Comunicaciones | `CINR` | ADMINISTRACIÓN DE REDES Y COMUNICACIONES | 23 / 34 | CSEG (15) | Activo |
| Ingeniería de sistemas | `BISI` | INGENIERÍA DE SISTEMAS DE INFORMACIÓN | 38 / 48 | BTIN (33) | Activo |
| Tecnologías de Información | `BTIN` | DIRECCION DE TECNOLOGIAS DE LA INFORMACIÓN | 43 / 53 | BISI (33) | Activo |
| Desarrollo de Software | `CINS` | DESARROLLO DE SISTEMAS DE INFORMACIÓN | 23 / 34 | BTIN (19) | **Pendiente**: el nombre no coincide |
| Sistemas de Información | `CISI` | ARQUITECTURA DE PLATAFORMAS Y SERVICIOS DE TECNOLOGÍAS DE INFORMACIÓN | 25 / 34 | BTIN (24) | **Pendiente**: el nombre no coincide y la segunda opción está a 1 curso |

Santa Catalina (`sc_careers`: Acompañamiento Terapéutico, Enfermería Técnica, Farmacia Técnica, Laboratorio Clínico, Terapia Física y Rehabilitación) no tiene ninguna equivalente.

## Familias que se enviarían en la carga inicial

Resultado de `observatorio:carga-inicial --dry-run` con alcance `isil:1,isil:2`.

- Una familia cuenta en cada carrera a la que pertenece su sílabo vigente, así que la suma por carrera es mayor que el total.
- Con las 7 activas se enviarían **101 familias**: 93 de Pregrado y 8 de CT.
- Con las 9 se enviarían **105**: CINS y CISI aportan solo 4 familias nuevas, porque comparten casi todos sus cursos con las demás.
- Educación Ejecutiva y Santa Catalina no aportan ninguna, porque no tienen sílabos en estas carreras.
