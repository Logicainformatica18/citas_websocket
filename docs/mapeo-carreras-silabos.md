# Mapeo de carreras silabos_v2 → observatorio

Generado el 2026-10-28 a partir de `silabo_2.careers`, `silabo_2.sc_careers` y `observatorio_2.careers`, comparando el nombre normalizado (minúsculas, sin tildes ni signos).

- **Exacto**: el nombre normalizado coincide. Está activo en `config/silabos_v2.php` → `career_map`.
- **Candidato**: la carrera del observatorio más parecida (similitud ≥ 60 %). **No está activo**; hay que revisarlo y, si es correcto, copiarlo a `career_map`.
- Sin coincidencia: el sílabo se ingiere igual, pero no se vincula a ninguna carrera (queda registrado en `storage/logs/silabos-revision.log`).

## Carreras del observatorio

| id | nombre | slug |
|---|---|---|
| 1 | Arquitectura de Datos | `arquitectura-de-datos` |
| 2 | Ciberseguridad | `ciberseguridad` |
| 3 | Ciencia de Datos e Inteligencia Artificial | `ciencia-de-datos-e-inteligencia-artificial` |
| 4 | Computación en la Nube | `computacion-en-la-nube` |
| 5 | Desarrollo de Software | `desarrollo-de-software` |
| 8 | Redes y Comunicaciones | `redes-y-comunicaciones` |
| 9 | Sistemas de Información | `sistemas-de-informacion` |
| 10 | Ingeniería de sistemas | `ingenieria-de-sistemas` |
| 11 | Tecnologías de Información | `tecnologias-de-informacion` |

## Carreras de silabos_v2

| modelo | código | nombre | exacto | candidato (similitud) |
|---|---|---|---|---|
| isil | `CADM` | ADMINISTRACIÓN DE EMPRESAS |  |  |
| isil | `CFIN` | ADMINISTRACIÓN DE EMPRESAS CON MENCIÓN EN FINANZAS |  |  |
| isil | `CABC` | ADMINISTRACIÓN DE NEGOCIOS BANCARIOS Y FINANCIEROS |  |  |
| isil | `CNEG` | ADMINISTRACIÓN DE NEGOCIOS INTERNACIONALES |  |  |
| isil | `CTUR` | ADMINISTRACIÓN DE OPERACIONES TURÍSTICAS |  |  |
| isil | `CINR` | ADMINISTRACIÓN DE REDES Y COMUNICACIONES |  | `redes-y-comunicaciones` (71 %) |
| isil | `CHOT` | ADMINISTRACIÓN DE SERVICIOS DE HOSTELERÍA Y RESTAURANTES |  |  |
| isil | `BADM` | ADMINISTRACIÓN Y DIRECCIÓN DE NEGOCIOS |  |  |
| isil | `CAN3` | ANIMACIÓN DIGITAL Y DISEÑO 3D |  |  |
| isil | `CADT` | ARQUITECTURA DE DATOS | `arquitectura-de-datos` |  |
| isil | `BDIN` | ARQUITECTURA DE INTERIORES |  | `arquitectura-de-datos` (81 %) |
| isil | `CISI` | ARQUITECTURA DE PLATAFORMAS Y SERVICIOS DE TECNOLOGÍAS DE INFORMACIÓN |  |  |
| isil | `CSEG` | CIBERSEGURIDAD | `ciberseguridad` |  |
| isil | `CCDT` | CIENCIA DE DATOS |  |  |
| isil | `CNUB` | COMPUTACIÓN EN NUBE |  | `computacion-en-la-nube` (93 %) |
| isil | `CCOA` | COMUNICACIÓN AUDIOVISUAL |  |  |
| isil | `BCOM` | COMUNICACIÓN ESTRATÉGICA |  | `redes-y-comunicaciones` (61 %) |
| isil | `CCOM` | COMUNICACIÓN INTEGRAL |  | `redes-y-comunicaciones` (60 %) |
| isil | `CCON` | CONTABILIDAD |  |  |
| isil | `BCOA` | CREACIÓN Y DIRECCIÓN AUDIOVISUAL |  |  |
| isil | `CINS` | DESARROLLO DE SISTEMAS DE INFORMACIÓN |  | `sistemas-de-informacion` (77 %) |
| isil | `BNEG` | DIRECCIÓN DE NEGOCIOS GLOBALES |  |  |
| isil | `BTIN` | DIRECCION DE TECNOLOGIAS DE LA INFORMACIÓN |  | `tecnologias-de-informacion` (76 %) |
| isil | `BRHH` | DIRECCION DEL TALENTO HUMANO |  |  |
| isil | `BFIN` | DIRECCIÓN FINANCIERA Y CONTABLE |  |  |
| isil | `BHOT` | DIRECCIÓN HOTELERA |  |  |
| isil | `BPUB` | DIRECCIÓN PUBLICITARIA |  |  |
| isil | `BAN3` | DIRECCION Y GESTION DE PROYECTOS ANIMADOS |  |  |
| isil | `CDIN` | DISEÑO DE INTERIORES |  |  |
| isil | `CDMI` | DISEÑO DE MEDIOS INTERACTIVOS |  |  |
| isil | `BDIS` | DISEÑO ESTRATÉGICO E INNOVACIÓN |  |  |
| isil | `CDIS` | DISEÑO GRÁFICO |  |  |
| isil | `CIND` | DISEÑO Y DESARROLLO DE VIDEOJUEGOS |  |  |
| isil | `CFOT` | FOTOGRAFÍA Y PRODUCCIÓN DE IMAGEN |  |  |
| isil | `CGAS` | GASTRONOMÍA |  |  |
| isil | `CGCO` | GESTIÓN COMERCIAL |  |  |
| isil | `CLOG` | GESTIÓN DE LOGÍSTICA |  |  |
| isil | `CRHH` | GESTIÓN DE RECURSOS HUMANOS |  |  |
| isil | `CGDE` | GESTIÓN DEPORTIVA |  |  |
| isil | `CGPB` | GESTIÓN PÚBLICA |  |  |
| isil | `BISI` | INGENIERÍA DE SISTEMAS DE INFORMACIÓN |  | `sistemas-de-informacion` (77 %) |
| isil | `CMAR` | MARKETING |  |  |
| isil | `BMAR` | MARKETING E INNOVACIÓN |  |  |
| isil | `CMOD` | MARKETING Y GESTIÓN DE MODA |  |  |
| isil | `CPDE` | PERIODISMO DEPORTIVO |  |  |
| isil | `CSST` | PROCESOS EN SST |  |  |
| isil | `CPIN` | PRODUCCIÓN INDUSTRIAL |  |  |
| isil | `CPUB` | PUBLICIDAD Y MEDIOS DIGITALES |  |  |
| scl | `ACT` | Acompañamiento Terapéutico |  |  |
| scl | `ENFT` | Enfermería Técnica |  |  |
| scl | `FART` | Farmacia Técnica |  |  |
| scl | `LABC` | Laboratorio Clínico |  |  |
| scl | `TFR` | Terapia Física y Rehabilitación |  |  |
