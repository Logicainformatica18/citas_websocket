# MAPEO FUNCIONAL — Observatorio Tecnológico

> Insumo para redactar el **manual de usuario**. No es documentación técnica.
> Generado el 2026-10-30 a partir del código de la rama `feature/integracion-silabos` (commit `7e94e45`).

**Convenciones**

- Los textos entre comillas («…» o "…") son **textos exactos** que ve el usuario, tal como están en el código. El proyecto no tiene archivos de traducción para estas pantallas: los textos están escritos directamente en los componentes React (`resources/js/pages/...`) y en los controladores.
- Entre paréntesis va el **archivo fuente** de cada dato. Abreviaturas usadas en algunas subsecciones: `P/` = `resources/js/pages/`, `C/` = `app/Http/Controllers/`, `web.php` = `routes/web.php`, `sidebar` = `resources/js/components/app-sidebar.tsx`.
- **[POR CONFIRMAR]**: no se puede afirmar solo leyendo el código, o parece un defecto que hay que validar en el sistema en ejecución.
- **[PENDIENTE]** / **[PENDIENTE / OCULTO]**: funcionalidad comentada, sin enlace en el menú, incompleta o que apunta a código inexistente.
- Los mensajes de validación estándar de Laravel salen **en inglés**, porque `.env.example` trae `APP_LOCALE=en` (.env.example) **[POR CONFIRMAR el valor en producción]**.

---

## 1. RESUMEN GENERAL

### 1.1 Qué hace el módulo

El Observatorio Tecnológico recolecta **ofertas de empleo** de portales nacionales e internacionales, además de **reportes de tendencias** tecnológicas (buscadores e IA). Con esos datos mide qué tecnologías, lenguajes, certificaciones, niveles profesionales, empresas, ciudades y modalidades de trabajo pide el mercado (README.md; resources/js/components/app-sidebar.tsx). También procesa los **sílabos** de los cursos ISIL, ya sea por carga de PDF o porque llegan automáticamente desde silabos_v2. De cada sílabo extrae con IA los lenguajes, tecnologías y metodologías que enseña el curso, y calcula la **alineación de cada curso con el mercado laboral** (README.md). Incluye el asistente conversacional **VERA**, que responde preguntas y arma gráficos en el "Dashboard Vera" (app/Http/Controllers/AI/DashboardAIController.php).

### 1.2 Ruta de acceso y opciones del menú

Se entra con la pantalla de inicio de sesión: usuario externo con captcha, o usuario ISIL por SAML (resources/js/pages/auth/login.tsx; routes/web.php). Todas las opciones están en el **menú lateral** (resources/js/components/app-sidebar.tsx).

- El menú **no usa prefijos** del tipo [M] o [R].
- Las secciones se agrupan como desplegables que arrancan **abiertos** (`useState(true)`), aunque el comentario del código dice "CONTRAÍDO POR DEFECTO" (app-sidebar.tsx).

| Menú lateral | Subgrupo | Opción | URL | Visible para |
|---|---|---|---|---|
| Dashboard Vera | — | Dashboard Vera | `/dashboard` | Todos |
| Indicadores | Mercado y Demanda | Tecnologías | `/dashboard/ranking/technologies` | Todos |
| Indicadores | Mercado y Demanda | Lenguajes | `/dashboard/ranking/languages` | Todos |
| Indicadores | Mercado y Demanda | Certificaciones | `/dashboard/ranking-certificaciones` | Todos |
| Indicadores | Mercado y Demanda | Nivel Profesional | `/dashboard/indicators/seniority` | Todos |
| Indicadores | Mercado y Demanda | Empresas | `/dashboard/indicators/companies` | Todos |
| Indicadores | Mercado y Demanda | Demanda Ciudad | `/dashboard/indicators/job-demand-geo` | Todos |
| Indicadores | Mercado y Demanda | Modalidad Laboral | `/dashboard/indicadores/modalidad-laboral` | Todos |
| Indicadores | Tendencias | Tendencias Macro | `/dashboard/indicators/macro-trends` | Todos |
| Indicadores | Origen de Datos | Comandos Fuentes | `/sources` | Visible para todos; solo funciona con `administrar` (los demás reciben 403) |
| Indicadores | Alineación Académica | Alineación Cursos | `/dashboard/indicators/course-alignment` | Todos |
| Indicadores | Alineación Académica | ~~Alineación Competencias~~ | `/dashboard/indicators/pe-alignment` | **Comentada en el menú** [PENDIENTE / OCULTO] |
| Académico | — | Silabos | `/silabos` | Solo `administrar` |
| Académico | — | Carreras | `/careers` | Solo `administrar` |
| Académico | — | Cursos ISIL | `/courses` | Solo `administrar` |
| Académico | — | Vinculación de cursos (con contador naranja de pendientes) | `/course-linking` | Solo `administrar` |
| Habilidades | — | Lenguajes | `/languages` | Solo `administrar` |
| Habilidades | — | Tecnologías | `/technologies` | Solo `administrar` |
| Habilidades | — | Metodologías | `/methodologies` | Solo `administrar` |
| Habilidades | — | Competencias | `/competencies` | Solo `administrar` |
| Habilidades | — | Roles Tech | `/tech-positions` | Solo `administrar` |
| Habilidades | — | Entidades | `/market-entities` | Solo `administrar` |
| Datos | — | Empleos | `/job-offers` | Solo `administrar` |
| Datos | — | Topics IA | `/topics-ia` | Solo `administrar` |
| IA VERA | — | Entrenamiento IA | `/admin/ai-trainings` | Solo `administrar` |
| Administración | — | Usuarios | `/users` | Solo `administrar` |
| Administración | — | Roles | `/roles` | Solo `administrar` |
| (menú de usuario, pie del menú) | — | Configuración: Perfil / Contraseña / Apariencia; Cerrar sesión | `/settings/...` | Todos |

**Pantallas con URL pero sin opción en el menú** (solo se llega escribiendo la dirección):

- `/dashboard/ranking-carreras`: "Demanda laboral por carrera".
- `/entity-trends`: Entity Trends.
- `/dashboard/indicators/pe-alignment`: Alineación del Perfil de Egreso.
- `/scrapings`: Scrapings, con sus Campos y Backups.
- `/scraping/{source}/results`: Resultados Web.

Ver el detalle en la sección 2.

---

## 2. PANTALLAS

Las pantallas siguen el orden lógico de uso de un administrador:

1. Acceso.
2. Gestión de usuarios.
3. Catálogos.
4. Datos académicos.
5. Datos de mercado y fuentes.
6. Consulta de indicadores.
7. Dashboard y asistente VERA.

Cada subsección conserva la numeración interna del levantamiento (A.x, B.x…), que sirve de referencia cruzada.

### 2.1 Acceso al sistema y configuración personal

#### A.1. Acceso al sistema

##### A.1.1 Pantalla de inicio de sesión
- **URL:** `/login` (routes/auth.php). Si entras a `/` te redirige a `/dashboard` (routes/web.php). Si no tienes sesión, te redirige al login.
- **Título de la pestaña:** "Observatorio Tecnológico". Encabezado: "Observatorio Tecnológico", subtítulo "Accede a tu plataforma de monitoreo" (resources/js/pages/auth/login.tsx).
- Arriba aparece el logo de ISIL y la pantalla ofrece dos opciones (resources/js/pages/auth/login.tsx):

| Botón | Qué hace |
|---|---|
| "Ingresar con usuario ISIL" | Te lleva a `/login/saml`, el inicio de sesión institucional (SSO de ISIL, WSO2/Banner) (resources/js/pages/auth/login.tsx, routes/auth.php). |
| "Usuario externo" | Muestra el formulario de correo y contraseña (resources/js/pages/auth/login.tsx). |

###### Formulario "Usuario externo"
| Campo | Tipo | Obligatorio | Placeholder / notas |
|---|---|---|---|
| "Email" | correo | Sí | "correo@ejemplo.com" (resources/js/pages/auth/login.tsx); la regla del servidor es `required, email` (app/Http/Requests/Auth/LoginRequest.php) |
| "Contraseña" | contraseña | Sí | "Tu contraseña" (resources/js/pages/auth/login.tsx) |
| "Recordarme" | casilla | No | Mantiene la sesión abierta (resources/js/pages/auth/login.tsx, LoginRequest.php) |
| reCAPTCHA de Google | captcha | Sí (en pantalla) | El botón "Ingresar" se queda desactivado hasta resolver el captcha. Si intentas enviar sin resolverlo, aparece la alerta "Por favor verifica el captcha" (resources/js/pages/auth/login.tsx) |

- Enlace "¿Olvidaste tu contraseña?": lleva a `/forgot-password` (resources/js/pages/auth/login.tsx, routes/auth.php).
- Botones: "Ingresar" envía el formulario y "Volver" regresa a la selección del tipo de acceso (resources/js/pages/auth/login.tsx).
- Si el acceso es correcto, entras a `/dashboard` (app/Http/Controllers/Auth/AuthenticatedSessionController.php).
- **Errores:**
  - Si las credenciales son incorrectas, aparece bajo "Email" el mensaje estándar de Laravel `auth.failed`. El idioma configurado es `en` y el proyecto no tiene carpeta `lang`, así que el texto probablemente salga en inglés, "These credentials do not match our records." **[POR CONFIRMAR]** (app/Http/Requests/Auth/LoginRequest.php, config/app.php, .env).
  - Después de **5 intentos fallidos** con el mismo correo e IP, el acceso se bloquea temporalmente con el mensaje `auth.throttle` (app/Http/Requests/Auth/LoginRequest.php).
  - Además, la ruta de login admite como máximo **15 envíos por cada 1440 minutos (24 h)** (`throttle:15,1440`) (routes/auth.php).
- **Nota:** el servidor no valida el token del captcha. La verificación solo ocurre en el navegador (app/Http/Requests/Auth/LoginRequest.php no lo revisa).

##### A.1.2 Inicio de sesión con usuario ISIL (SAML)
- Al pulsar "Ingresar con usuario ISIL" se te redirige al proveedor de identidad institucional. Al volver, el sistema recibe tus datos en `app/saml2/callback` (routes/auth.php, routes/web.php).
- **Regla de dominio:** solo se aceptan correos que terminan en **`@isil.pe`**. Si el correo es de otro dominio, se cierra la sesión y se te redirige al cierre de sesión institucional (`/app/logout`) (AuthenticatedSessionController.php).
- **Alta automática:** si es tu primer ingreso, el sistema crea tu usuario con el DNI (la parte del identificador antes de "@"), el correo, el nombre y el apellido, y marca el correo como verificado (AuthenticatedSessionController.php).
- **El usuario nuevo se crea sin rol.** Por eso solo ve el menú básico (sección 6.3) hasta que un administrador le asigne un rol (AuthenticatedSessionController.php: no llama a `assignRole`).
- **Errores posibles:** "Error al procesar autenticación SAML" y "Respuesta SAML inválida" (AuthenticatedSessionController.php). La pantalla de login solo muestra los errores de los campos `email` y `password`, así que estos dos mensajes probablemente no se vean **[POR CONFIRMAR]** (resources/js/pages/auth/login.tsx).

##### A.1.3 Cerrar sesión
- Abre el menú de usuario (al pie de la barra lateral) y elige "Cerrar sesión". Esto abre `/logout` (resources/js/components/user-menu-content.tsx, routes/web.php).
- Si entraste con usuario ISIL, también se cierra la sesión institucional (`/app/logout`). Si entraste como usuario externo, vuelves a `/` (AuthenticatedSessionController.php `logout`).

##### A.1.4 Página `/unauthorized`
- Muestra solo el texto "Acceso no autorizado. Contacta con sistemas." y no exige sesión (routes/web.php). Ningún flujo del código redirige a esta página **[POR CONFIRMAR]**.
- Si un usuario sin permiso abre una pantalla protegida (por ejemplo `/users`), ve el error **403** estándar del paquete de permisos (Spatie), no esta página (bootstrap/app.php, sin manejo personalizado).

---

#### A.2. Menú de usuario y Configuración personal

##### A.2.1 Menú de usuario
Está al pie de la barra lateral (resources/js/components/app-sidebar.tsx `NavUser`). Contiene (resources/js/components/user-menu-content.tsx):

| Opción | Qué hace |
|---|---|
| Nombre y correo | Solo informativo |
| "Cambiar a tema claro" / "Usar tema del sistema" / "Cambiar a tema oscuro" | Alterna el tema en ciclo oscuro → claro → sistema. El texto muestra el siguiente modo. La preferencia se guarda en el navegador y en una cookie por 1 año |
| "Configuración" | Abre `/settings/profile` |
| "Cerrar sesión" | Ver 1.3 |

##### A.2.2 Configuración (`/settings`)
`/settings` redirige a `/settings/profile` (routes/settings.php). El menú lateral interno tiene las opciones "Perfil", "Contraseña" y "Apariencia". La cabecera dice "Settings" / "Manage your profile and account settings", en inglés (resources/js/layouts/settings/layout.tsx).

###### a) Perfil – `/settings/profile`
- Título: "Configuración del perfil". Bloque: "Información del perfil" / "Actualiza tu nombre y dirección de correo" (resources/js/pages/settings/profile.tsx).

| Campo | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| "Nombre" | texto | Sí | máx. 255 (app/Http/Requests/Settings/ProfileUpdateRequest.php) |
| "Correo electrónico" | correo | Sí | en minúsculas, formato email, máx. 255, único entre usuarios (ProfileUpdateRequest.php) |

- Botón "Guardar". Si se guarda bien, aparece "Guardado" (profile.tsx).
- Si cambias el correo, este queda como **no verificado**. Aparece "Tu correo aún no ha sido verificado." con el enlace "Haz clic aquí para reenviar el correo de verificación." y, tras el envío, "Se ha enviado un nuevo enlace de verificación a tu correo." (ProfileController.php, profile.tsx).
- **Observación [POR CONFIRMAR]:** el usuario del sistema no tiene un campo `name`. Usa `firstname`, `lastname` y `names`, y `name` no figura entre los campos que se pueden guardar (app/Models/User.php). Por eso el campo "Nombre" probablemente aparezca vacío y su cambio no se guarde.
- **Eliminar cuenta:** bloque "Eliminar cuenta" / "Elimina tu cuenta y todos sus recursos asociados", con "⚠️ Advertencia" y "Esta acción es irreversible. Procede con precaución." El botón "Eliminar cuenta" abre "¿Estás seguro de que deseas eliminar tu cuenta?", que pide la "Contraseña" actual y se confirma con "Confirmar eliminación" (resources/js/components/delete-user.tsx). El servidor exige la contraseña actual correcta (`current_password`), luego cierra la sesión y borra el usuario (ProfileController.php `destroy`). Los usuarios que entran con SAML tienen una contraseña aleatoria que no conocen, así que en la práctica no pueden usar esta opción **[POR CONFIRMAR]** (AuthenticatedSessionController.php).

###### b) Contraseña – `/settings/password`
- Bloque "Actualizar contraseña" / "Asegúrate de usar una contraseña segura y difícil de adivinar" (resources/js/pages/settings/password.tsx).

| Campo | Obligatorio | Regla |
|---|---|---|
| "Contraseña actual" | Sí | debe coincidir con la actual (`current_password`) |
| "Nueva contraseña" | Sí | `Password::defaults()` (por defecto de Laravel, mínimo 8 caracteres **[POR CONFIRMAR]**) y debe coincidir con la confirmación |
| "Confirmar nueva contraseña" | Sí | igual a la nueva |

(app/Http/Controllers/Settings/PasswordController.php). Botón "Guardar contraseña". Si se guarda bien, aparece "Guardado" (password.tsx).

###### c) Apariencia – `/settings/appearance`
- Título "Configuración de apariencia" / "Actualiza la apariencia de tu cuenta". Hay tres pestañas: "Light", "Dark" y "System" (resources/js/pages/settings/appearance.tsx, resources/js/components/appearance-tabs.tsx).

---


### 2.2 Administración › Usuarios y Roles

#### A.5. Administración

##### A.5.1 Usuarios (`/users`)
- **Menú:** "Administración" > "Usuarios". Requiere el permiso `administrar` (app-sidebar.tsx, routes/web.php).
- Título "Listado de Usuarios". Muestra 10 por página, del más reciente al más antiguo (resources/js/pages/users/index.tsx, app/Http/Controllers/UserController.php). No tiene búsqueda ni filtros.

| Columna | Contenido |
|---|---|
| "Acciones" | "Editar" y "Eliminar" |
| "Gestionar" | "Roles" (tooltip "Gestionar roles") |
| "ID" | identificador |
| "Nombres" | nombre completo (nombre + apellido) |
| "Email" | correo |
| "Roles" | roles asignados, separados por coma |
| "Foto" | enlace "Descargar" si hay foto. Si no, "Sin foto" |

**Formulario "Nuevo usuario" / "Editar usuario"** (botón "Nuevo Usuario" o "Editar") (users/modal.tsx, UserController.php)

| Campo | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| "Nombre *" | texto | Sí | máx. 255 |
| "Apellido *" | texto | Sí | máx. 255 |
| "Correo *" | correo | Sí | formato email, **único** (no puede haber dos usuarios con el mismo correo) |
| "Contraseña *" | texto (visible por defecto, con un ojo para ocultarla) | Sí al crear, opcional al editar | mín. 6. **Al crear viene precargada con `isil2026`**. Al editar viene vacía y, si se deja vacía, se conserva la contraseña actual |

- El nombre completo se arma automáticamente con nombre + apellido (UserController.php).
- **[PENDIENTE]** Los campos DNI, Celular y Sexo están comentados en el formulario. El servidor los acepta: DNI máx. 100, celular máx. 20, sexo 1 carácter (users/modal.tsx, UserController.php).
- Botones: "Limpiar" (vacía el formulario y repone la contraseña `isil2026`) y "Guardar" / "Actualizar".
- Mensajes: "Usuario creado", "Usuario actualizado", "Error al guardar". Los errores de validación, como un correo repetido, no se detallan: solo aparece "Error al guardar" (users/modal.tsx).
- **Eliminar:** pide "¿Eliminar usuario <nombre>?". Si falla: "Error al eliminar". No hay ninguna protección contra eliminarse a uno mismo o al último administrador (UserController.php `destroy`).

**Asignar roles** (botón "Roles") (users/UserRolesModal.tsx)
- Diálogo "Asignar Roles" con una casilla por cada rol existente. Botones "Cancelar" y "Guardar Cambios".
- Reemplaza todos los roles del usuario por los marcados. Si no marcas ninguno, el usuario queda sin roles (UserController.php `syncRoles`).
- Mensajes: "Roles actualizados correctamente ✅" y "Error al sincronizar roles".

##### A.5.2 Roles (`/roles`)
- **Menú:** "Administración" > "Roles". Requiere el permiso `administrar` (app-sidebar.tsx, routes/web.php).
- Título "Listado de Roles", subtítulo "Administra los roles y permisos del sistema.". Muestra 10 por página (resources/js/pages/roles/index.tsx, app/Http/Controllers/RoleController.php).
- Columnas: "ID", "Nombre", "Permisos" (separados por coma) y "Acciones" ("Editar", "Eliminar").
- **Formulario "Nuevo Rol" / "Editar Rol"** (roles/modal.tsx, RoleController.php):

| Campo | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| "Nombre del rol" | texto, placeholder "Ej: administrador" | Sí | **único** |
| "Permisos" | casillas, una por permiso existente (orden alfabético) | No | deben ser permisos existentes |

- Botones "Cancelar" y "Crear" / "Actualizar". Mensajes: "Rol creado", "Rol actualizado", "Error al guardar el rol". Si falla la carga del rol: "Error al obtener el rol".
- **Eliminar:** pide "¿Eliminar rol "<nombre>"?". Si falla: "Error al eliminar". No hay ninguna protección para el rol Administrador.
- **[POR CONFIRMAR]** La paginación (página 2 en adelante) llama a `/roles/fetch`, pero el método `fetchPaginated` no existe en RoleController.php, así que probablemente falle si hay más de 10 roles.

---


### 2.3 Habilidades (catálogos)

#### F.0 Acceso y requisitos comunes

- Todas las pantallas de esta parte exigen iniciar sesión (están dentro del grupo `Route::middleware(['auth'])`) (web.php).
- Las secciones de menú **Habilidades** y **Datos** solo se muestran al usuario con el permiso `administrar` (`isAdmin = has("administrar")`, bloque `{isAdmin && (...)}`) (sidebar).
- Las rutas de Habilidades, Empleos, Entidades, Entity Trends y Topics IA llevan además `middleware('permission:administrar')`; si un usuario sin ese permiso escribe la URL, el servidor la rechaza (web.php).
- Excepciones sin `permission:administrar` (basta con iniciar sesión): `PATCH /technologies/{id}/toggle`, `POST /job-offers/preview`, el segundo `POST /job-offers/import` y todas las rutas de `/scrapings` (web.php).

##### Menú lateral

| Sección | Opción | URL | Fuente |
|---|---|---|---|
| Habilidades | Lenguajes | `/languages` | sidebar |
| Habilidades | Tecnologías | `/technologies` | sidebar |
| Habilidades | Metodologías | `/methodologies` | sidebar |
| Habilidades | Competencias | `/competencies` | sidebar |
| Habilidades | Roles Tech | `/tech-positions` | sidebar |
| Habilidades | Entidades | `/market-entities` | sidebar |
| Datos | Empleos | `/job-offers` | sidebar |
| Datos | Topics IA | `/topics-ia` | sidebar |

---

#### F.1 Patrón común de los catálogos (Habilidades)

Casi todas las pantallas de Habilidades siguen el mismo patrón. Abajo se describe una vez, y en cada pantalla solo se indican las diferencias.

**Encabezado.** Muestra el título de la pantalla y, a la derecha, un botón «Nuevo/Nueva …» que abre el formulario en blanco (P/languages/Index.tsx).

**Buscador.** Es una caja con lupa. La lista se filtra sola unos 400 ms después de que el usuario deja de escribir (*debounce*); no hay botón Buscar. Si se vacía la caja, vuelve el listado completo (P/languages/Index.tsx).

**Tabla.**
- La primera columna es **Acciones**, con dos enlaces apilados: «Editar» (ícono lápiz) y «Eliminar» (ícono papelera). No hay menú de tres puntos (P/languages/Index.tsx).
- Mientras carga, la tabla muestra «Cargando…» (P/languages/Index.tsx).
- La columna **Creado** muestra la fecha con formato peruano, por ejemplo «5 oct. 2026». Si no hay fecha, muestra «-» (P/languages/Index.tsx).

**Paginación.**
- Muestra 10 registros por página (C/LanguageController.php).
- Los botones numerados aparecen solo si hay más de una página. Se ven la página actual ±2, la primera, la última y «…» entre ellas (P/languages/Index.tsx).

**Eliminar.**
- Pide confirmación con un diálogo: título «¿Eliminar "<nombre>"?», texto «Esta acción no se puede deshacer.», botones «Sí, eliminar» y «Cancelar» (P/languages/Index.tsx).
- La eliminación es **física**: el registro se borra, no se archiva (C/LanguageController.php `destroy`).
- No hay ninguna regla que impida eliminar un registro que tenga datos relacionados (C/LanguageController.php, C/TechnologyController.php, C/MethodologyController.php, C/CompetencyController.php). Si la base de datos lo bloquea por clave foránea, el usuario solo verá el mensaje genérico de error **[POR CONFIRMAR]**.

**Formulario (modal).**
- El título es «Nuevo …» o «Editar …» (P/languages/languageModal.tsx).
- Botones: «Cancelar» y «Guardar» (al crear) o «Actualizar» (al editar). Mientras se envía, el botón muestra «Guardando...» (P/languages/languageModal.tsx).
- El modal se cierra con la «X» o al hacer clic fuera de él (P/languages/languageModal.tsx).

**Contexto semántico.**
- En varios catálogos hay un combo «Contexto semántico». Su primera opción es «Sin contexto» y las demás son los contextos de búsqueda registrados (C/LanguageController.php `SemanticContext`).
- En la tabla, el contexto se muestra como una etiqueta celeste, o «—» si no tiene (P/languages/Index.tsx).

**Nombre y slug.**
- El nombre es obligatorio y admite hasta 255 caracteres (C/LanguageController.php `validate`).
- El sistema genera el *slug*, es decir, la versión del nombre apta para URL, sin intervención del usuario (`Str::slug`) (C/LanguageController.php).
- **No se valida que el nombre sea único** en ningún catálogo de esta sección (reglas `validate` de los controladores citados).

**Estado Activo/Inactivo (interruptor).**
- Aparece en Lenguajes, Tecnologías y Metodologías.
- El cambio se guarda en el acto, sin confirmación, y no muestra mensaje de éxito. Si falla, aparece un diálogo de error (P/languages/Index.tsx, P/technologies/Index.tsx, P/methodologies/Index.tsx).
- Todo registro nuevo nace **activo** (`enabled = 1`) (C/LanguageController.php, C/MethodologyController.php, C/TechnologyController.php).
- Efecto del estado: algunos procesos de búsqueda de empleos solo consultan las tecnologías activas, por ejemplo `Technology::where('enabled', 1)` (app/Console/Commands/GreenhouseByTechnologiesCommand.php). Otros recolectores no aplican este filtro y crean registros nuevos con `firstOrCreate` (app/Console/Commands/AdzunaByLanguagesCommand.php). El efecto exacto de desactivar un registro varía según la fuente **[POR CONFIRMAR]**.

---

#### F.2 Lenguajes — `/languages`

- **Título:** «Lenguajes»; migas: «Lenguajes» (P/languages/Index.tsx).
- **Botón:** «Nuevo Lenguaje» (P/languages/Index.tsx).
- **Buscador:** placeholder «Buscar lenguaje...»; busca por nombre (P/languages/Index.tsx; C/LanguageController.php).
- **Orden:** del más reciente al más antiguo (por `id` descendente) (C/LanguageController.php).
- **Tabla vacía:** «No hay lenguajes registrados.» (P/languages/Index.tsx).

**Columnas** (P/languages/Index.tsx):

| Columna | Significado |
|---|---|
| Acciones | «Editar» / «Eliminar» |
| Nombre | Nombre del lenguaje |
| Contexto | Contexto semántico (etiqueta) o «—» |
| Estado | Interruptor activo/inactivo (sin texto) |
| Creado | Fecha de alta |

**Formulario «Nuevo Lenguaje» / «Editar Lenguaje»** (P/languages/languageModal.tsx; C/LanguageController.php):

| Campo | Tipo | Oblig. | Valores / límites |
|---|---|---|---|
| Nombre * | texto | Sí | máx. 255; placeholder «Ej: Python, JavaScript...» |
| Contexto semántico | combo | No | «Sin contexto» o un contexto existente |

**Mensajes** (P/languages/languageModal.tsx; P/languages/Index.tsx):
- Si el nombre está vacío: «Campo obligatorio» / «El nombre es requerido.».
- Al guardar: «Creado» / «El nuevo lenguaje fue registrado.»; «Actualizado» / «El lenguaje fue modificado.».
- Si falla: «Error» / «No se pudo guardar el lenguaje.».
- Al eliminar: «Eliminado» / «Lenguaje eliminado correctamente.»; si falla: «No se pudo eliminar el lenguaje.».
- Si falla el cambio de estado: «No se pudo actualizar el estado.».

**Detalle visual.**
- Las opciones del combo de contexto se muestran como « (<contexto>)», con un espacio antes del paréntesis. El formulario intenta mostrar también `role_name`, pero el servidor no envía ese dato (P/languages/languageModal.tsx; C/LanguageController.php `select('id','search_context')`).

---

#### F.3 Tecnologías — `/technologies`

- **Título:** «Tecnologías» (P/technologies/Index.tsx).
- **Botón:** «Nueva Tecnología» (P/technologies/Index.tsx).
- **Buscador:** placeholder «Buscar tecnología...»; busca por nombre (P/technologies/Index.tsx; C/TechnologyController.php).
- **Orden:** alfabético por nombre (C/TechnologyController.php).
- **Tabla vacía:** «No hay tecnologías registradas.» (P/technologies/Index.tsx).

**Columnas** (P/technologies/Index.tsx):

| Columna | Significado |
|---|---|
| Acciones | «Editar» / «Eliminar» |
| Nombre | Nombre de la tecnología |
| Categoría | Categoría tecnológica (etiqueta) o «—» |
| Contexto | Contexto semántico o «—» |
| Estado | Interruptor y, debajo, el texto «Activo» o «Inactivo» |
| Creado | Fecha de alta |

**Formulario «Nueva Tecnología» / «Editar Tecnología»** (P/technologies/TechnologyModal.tsx; C/TechnologyController.php):

| Campo | Tipo | Oblig. | Valores |
|---|---|---|---|
| Nombre * | texto | Sí | máx. 255; placeholder «Ej: React, Laravel, Docker...» |
| Categoría | combo | No | «Sin categoría» o una de las categorías de tecnología |
| Contexto semántico | combo | No | «Sin contexto» o un contexto |

**Mensajes** (P/technologies/TechnologyModal.tsx; P/technologies/Index.tsx):
- Al guardar: «Creado» / «La nueva tecnología se registró correctamente.»; «Actualizado» / «La tecnología fue modificada correctamente.».
- Si falla el guardado: «No se pudo guardar la tecnología.».
- Al eliminar: «Eliminado» / «Tecnología eliminada correctamente.»; si falla: «No se pudo eliminar la tecnología.».
- Si falla el cambio de estado: «No se pudo cambiar el estado.».

**Diferencias con el patrón común.**
- El interruptor no envía un valor: el servidor invierte el estado actual (C/TechnologyController.php `toggle`).
- La ruta del interruptor no exige el permiso `administrar` (web.php).

---

#### F.4 Metodologías — `/methodologies`

- **Título:** «Metodologías» (P/methodologies/Index.tsx).
- **Botón:** «Nueva Metodología» (P/methodologies/Index.tsx).
- **Buscador:** placeholder «Buscar metodología...». Busca **por nombre o por texto del contexto semántico** (C/MethodologyController.php).
- **Orden:** del más reciente al más antiguo (C/MethodologyController.php).
- **Tabla vacía:** «No hay metodologías registradas.» (P/methodologies/Index.tsx).
- **Columnas:** Acciones · Nombre · Contexto · Estado (interruptor con el texto «Activo» o «Inactivo») · Creado (P/methodologies/Index.tsx).

**Formulario** (P/methodologies/MethodologyModal.tsx):
- «Nombre *» (placeholder «Ej: Scrum, Kanban, Agile»).
- «Contexto semántico».

**Mensajes** (P/methodologies/MethodologyModal.tsx; P/methodologies/Index.tsx):
- Si el nombre está vacío: «Campo obligatorio» / «El nombre es requerido.».
- Al guardar se muestran avisos que se cierran solos a los 2 s: «Metodología creada» / «La nueva metodología se registró correctamente.»; «Metodología actualizada» / «La metodología se modificó correctamente.».
- Si falla: «No se pudo guardar la metodología.».
- Al eliminar: «Metodología eliminada correctamente.»; si falla: «No se pudo eliminar la metodología.».
- Si falla el cambio de estado: «No se pudo cambiar el estado.».

---

#### F.5 Competencias — `/competencies`

- **Título:** «Competencias» (P/competencies/Index.tsx).
- **Botón:** «Nueva Competencia» (P/competencies/Index.tsx).
- **Buscador:** placeholder «Buscar competencia...». Busca en el nombre, la descripción en español y la categoría (C/CompetencyController.php).
- **Filtro de carrera:** combo cuya primera opción es «Todas las carreras» (P/competencies/Index.tsx).
  - **Advertencia:** elegir una carrera **no actualiza la tabla por sí solo**. El filtro solo se aplica al pasar a otra página con los botones de paginación (P/competencies/Index.tsx: `careerFilter` solo se usa en la URL de paginación) **[POR CONFIRMAR: defecto]**.
  - El servidor también acepta un filtro por `category`, pero la pantalla no lo ofrece (C/CompetencyController.php).
- **Tabla vacía:** «No hay competencias registradas.» (P/competencies/Index.tsx).

**Columnas** (P/competencies/Index.tsx):

| Columna | Significado |
|---|---|
| Acciones | «Editar» / «Eliminar» |
| Nombre | Nombre de la competencia |
| Categoría | Texto libre o «-» |
| Carrera | Carrera asociada (etiqueta) o «—» |
| Peso | Valor de 0 a 1 (si no tiene, muestra «0») |

**Formulario «Nueva Competencia» / «Editar Competencia»** (P/competencies/CompetencyModal.tsx; C/CompetencyController.php):

| Campo | Tipo | Oblig. | Valores / límites |
|---|---|---|---|
| Nombre * | texto | Sí | máx. 255; «Ej: Pensamiento crítico, Comunicación efectiva» |
| Categoría | texto | No | máx. 255; «Ej: Técnica, Blanda, Digital» |
| Carrera asociada | combo | No | «Ninguna» o una carrera |
| Peso (0 a 1) | número | No | mín. 0, máx. 1, paso 0.01; inicial 0 |
| Descripción (ES) | área de texto | No | — |
| Descripción (EN) | área de texto | No | — |

**Mensajes** (P/competencies/CompetencyModal.tsx; P/competencies/Index.tsx):
- Si el nombre está vacío: «Campo requerido» / «El nombre es obligatorio.».
- Al guardar se muestran avisos: «Competencia creada» / «La nueva competencia fue registrada.»; «Competencia actualizada» / «Cambios guardados correctamente.».
- Si falla: «No se pudo guardar la competencia.».
- Al eliminar: «Competencia eliminada correctamente.»; si falla: «No se pudo eliminar la competencia.».

**Estado.**
- Existe la ruta `PATCH /competencies/{id}/toggle` (web.php).
- El controlador la describe como un interruptor «si deseas añadir un switch futuro»; la pantalla **no muestra** interruptor de estado (C/CompetencyController.php; P/competencies/Index.tsx). **Pendiente.**

---

#### F.6 Roles Tech — `/tech-positions`

- **Título:** «Roles Tecnológicos»; migas: «Roles Tecnológicos» (P/techpositions/index.tsx).
- **Botón:** «Nuevo Rol Tecnológico» (P/techpositions/index.tsx).
- **Buscador:** placeholder «Buscar rol tecnológico...», con una espera de unos 600 ms. Busca en el nombre, el nombre en inglés y la categoría (P/techpositions/index.tsx; C/TechPositionController.php).
- **Paginación:** 10 por página; muestra todos los números de página (P/techpositions/index.tsx).

**Columnas** (P/techpositions/index.tsx):

| Columna | Significado |
|---|---|
| Acciones | «✏️ Editar» / «Eliminar» |
| Rol | Nombre del rol |
| Categoría | Texto o «-» |
| Carreras asociadas | Etiquetas de carrera, o «Sin carrera asignada» |

**Formulario «Nuevo Rol Tecnológico» / «Editar Rol Tecnológico»** (P/techpositions/modal.tsx; C/TechPositionController.php):

| Campo | Tipo | Oblig. | Límite |
|---|---|---|---|
| Nombre * | texto | Sí | máx. 255 |
| Nombre (Inglés) | texto | No | máx. 255 |
| Categoría | texto | No | máx. 255 |
| Subcategoría | texto | No | máx. 255 |
| Descripción | área de texto | No | — |
| Carreras impactadas por este rol | etiquetas seleccionables (varias); las seleccionadas se ordenan primero | No | carreras **activas** |

- Botones: «Cancelar» y «Guardar» («Guardando...» mientras se envía) (P/techpositions/modal.tsx).
- Si el nombre está vacío, aparece una alerta del navegador: «El nombre del rol es obligatorio» (P/techpositions/modal.tsx).
- Si falla el guardado: «No se pudo guardar el rol.» (P/techpositions/modal.tsx).
- Eliminar usa la confirmación nativa del navegador, «¿Eliminar el rol "<nombre>"?». Si falla: «No se pudo eliminar.». No hay mensaje de éxito (P/techpositions/index.tsx).
- Todo rol nuevo se crea con `active = true` (C/TechPositionController.php).

**Defectos probables [POR CONFIRMAR]:**
1. «Editar» primero pide el rol con `GET /tech-positions/{id}`, pero **esa ruta no existe** (web.php solo define index, fetch, store, update, destroy y toggle). Lo esperable es la alerta «No se pudo cargar el rol.» (P/techpositions/index.tsx).
2. Al editar, el formulario envía `PATCH /tech-positions/{id}`, pero la ruta está definida como `PUT` (P/techpositions/modal.tsx; web.php). Lo esperable es «No se pudo guardar el rol.».
3. La ruta `PATCH /tech-positions/{id}/toggle` existe, pero el controlador **no tiene** método `toggle`, y la pantalla tampoco ofrece interruptor (web.php; C/TechPositionController.php). **Pendiente.**

---

#### F.7 Entidades (Market Entities) — `/market-entities`

- **Menú y título:** el menú dice «Entidades», pero el título y las migas dicen «Market Entities» (sidebar; P/market-entities/Index.tsx).
- **Botón:** «+ Nueva Entidad» (P/market-entities/Index.tsx).
- **Buscador:** placeholder «Buscar entidad...»; busca por nombre (P/market-entities/Index.tsx; C/MarketEntityController.php).
  - El servidor también acepta filtros por `entity_type` y `career_id`, pero la pantalla no los ofrece (C/MarketEntityController.php).
- **Paginación:** 15 por página. Si hay 7 páginas o menos se muestran todas; si hay más, la actual ±1, la primera, la última y «…» (C/MarketEntityController.php; P/market-entities/Index.tsx).
- **Tabla vacía:** «No hay entidades registradas.» (P/market-entities/Index.tsx).

**Columnas** (P/market-entities/Index.tsx):

| Columna | Significado |
|---|---|
| Acciones | «Editar» / «Eliminar» |
| Nombre | Nombre de la entidad |
| Tipo | `certification`, `technology` o `language` |
| Categoría | Texto o «—» |
| Carreras | Etiquetas de carreras asociadas o «—» |
| Trend | Casilla que marca si la entidad «tiene tendencia» (`has_trend`) |

**Casilla Trend (toggle-trend).**
- Al marcarla o desmarcarla se llama a `PATCH /market-entities/{id}/toggle-trend`, que invierte `has_trend`. No pide confirmación (P/market-entities/Index.tsx; C/MarketEntityController.php `toggleTrend`).
- Si falla: «No se pudo cambiar estado.» (P/market-entities/Index.tsx).
- Los procesos automáticos de descubrimiento de brechas y tendencias también ponen `has_trend = 1` (app/Console/Commands/getTrends/DiscoverTechnologyGapsCommand.php, …/DiscoverCertificationGapsCommand.php, …/DiscoverLanguageGapsCommand.php).

**Formulario «Nueva Entidad» / «Editar Entidad»** (P/market-entities/MarketEntityModal.tsx; C/MarketEntityController.php). Está organizado en secciones:

| Sección | Campo | Tipo | Oblig. | Valores / límites |
|---|---|---|---|---|
| Información General | Nombre | texto | Sí (servidor) | máx. 255 |
| Información General | Tipo de Entidad | combo | Sí (servidor) | «Seleccione», «Certification», «Technology», «Language»; máx. 100 |
| Metadatos | Origen | texto | No | máx. 100 |
| Metadatos | Categoría | texto | No | máx. 100 |
| Metadatos | Vendor | texto | No | máx. 100 |
| Metadatos | Nivel | texto | No | máx. 100 |
| Carreras Asociadas | Lista de casillas (varias) | casilla | No | si no se marca ninguna: «⚠️ Se recomienda asociar al menos una carrera.» |
| Configuración | Tiene ISIL | casilla | No | por defecto no marcada |
| Configuración | Tiene Trend | casilla | No | por defecto no marcada |

- Botones: «Cancelar» y «Guardar». El modal se cierra con Esc o al hacer clic fuera (P/market-entities/MarketEntityModal.tsx).
- Al guardar: «Éxito» / «Entidad guardada correctamente.» (P/market-entities/MarketEntityModal.tsx).
- Si falla: «Error» seguido del mensaje del servidor; por ejemplo, la validación de Laravel cuando falta el nombre o el tipo. Si no hay mensaje: «No se pudo guardar.» (P/market-entities/MarketEntityModal.tsx).
- Al eliminar: confirmación estándar y luego «Entidad eliminada correctamente.»; si falla: «No se pudo eliminar.» (P/market-entities/Index.tsx).

**Regla de negocio (eliminar en cascada).**
- Eliminar una entidad **quita también sus vínculos con carreras y borra las certificaciones relacionadas** (`$entity->certifications()->delete()`) (C/MarketEntityController.php `destroy`). Hay que advertirlo al usuario: no hay aviso específico en pantalla.

---

#### F.8 Entity Trends — `/entity-trends` (sin menú)

**Cómo se llega.**
- No aparece en el menú lateral (sidebar).
- Ninguna pantalla enlaza a ella (búsqueda de `entity-trends` en `resources/js`: solo aparece en sus propios archivos).
- Solo se accede **escribiendo la URL** `/entity-trends`, con el permiso `administrar` (web.php).

**Pantalla.**
- **Título y migas:** «Entity Trends» (P/entity-trends/Index.tsx).
- **Botón:** «Nuevo Trend» (P/entity-trends/Index.tsx).
- **Buscador:** placeholder «Buscar trend...»; busca por nombre del trend (P/entity-trends/Index.tsx; C/EntityTrendController.php). El servidor también acepta filtros por `year` y `quarter`, que la pantalla no ofrece (C/EntityTrendController.php).
- **Orden:** año, trimestre y puntaje, de mayor a menor; 15 por página (C/EntityTrendController.php).
- **Tabla vacía:** «No hay trends registrados.» (P/entity-trends/Index.tsx).

**Columnas** (P/entity-trends/Index.tsx):

| Columna | Significado |
|---|---|
| Acciones | «Editar» / «Eliminar» |
| Trend | Nombre del trend |
| Entidad | Entidad de mercado vinculada o «—» |
| Score | Puntaje de tendencia o «-» |
| Confianza | Puntaje de confianza o «-» |
| Periodo | «<año> Q<trimestre>» |
| Creado | Fecha de alta |

**Formulario «Nuevo Trend» / «Editar Trend»** (P/entity-trends/EntityTrendModal.tsx; C/EntityTrendController.php):

| Sección | Campo | Tipo | Oblig. (servidor) | Límite |
|---|---|---|---|---|
| Información General | Nombre del Trend | texto | Sí | máx. 255 |
| Información General | Trend Score | número | No | numérico |
| Periodo | Año | número | Sí | entero |
| Periodo | Quarter | número | Sí | entero (sin rango validado) |
| Clasificación | Tipo de Match | texto | No | máx. 100 |
| Clasificación | Confidence Score | número | No | numérico |
| Fuente | Título de la Fuente | texto | No | máx. 255 |
| Fuente | URL de la Fuente | texto | No | máx. 255 |

- Mensajes: «Éxito» / «Trend guardado correctamente.»; «Error» / «No se pudo guardar el trend.»; al eliminar, «Trend eliminado correctamente.» o «No se pudo eliminar el trend.» (P/entity-trends/EntityTrendModal.tsx; P/entity-trends/Index.tsx).
- El formulario **no permite elegir la entidad** (`market_entity_id`), aunque el servidor lo acepta. Los trends creados a mano quedan sin entidad (columna «—») (P/entity-trends/EntityTrendModal.tsx; C/EntityTrendController.php) **[POR CONFIRMAR]**.

---


### 2.4 Académico: Silabos, Carreras, Cursos ISIL y Vinculación de cursos

#### E.0 Acceso al menú

- El grupo **«Académico»** del menú lateral solo aparece si el usuario tiene el permiso `administrar` (`isAdmin && (...)`) (resources/js/components/app-sidebar.tsx).
- Opciones, en este orden (resources/js/components/app-sidebar.tsx):

| Opción del menú | URL | Pantalla (título visible) |
|---|---|---|
| Silabos | `/silabos` | «Gestión de Sílabos» |
| Carreras | `/careers` | «Carreras Profesionales» |
| Cursos ISIL | `/courses` | «Gestión de Cursos» |
| Vinculación de cursos | `/course-linking` | «Vinculación de cursos» |

- Todas las rutas exigen el permiso `administrar` (middleware `permission:administrar`) (routes/web.php).
- **Contador (badge) en «Vinculación de cursos»:** muestra un número cuando hay sílabos en estado «pendiente de vinculación». Al pasar el mouse dice «`N` pendiente(s)». No se muestra si es 0 (resources/js/components/app-sidebar.tsx). Se calcula como el total de sílabos con estado `pending_link` (app/Http/Middleware/HandleInertiaRequests.php).
  - Nota: el contador cuenta todos los `pending_link`; la página solo lista los vigentes (no reemplazados). Normalmente coinciden, porque un sílabo reemplazado deja de estar `pending_link` (app/Services/Syllabus/SyllabusIngestService.php). Si alguna vez difieren: **[POR CONFIRMAR]**.

---

#### E.1 Sílabos — «Gestión de Sílabos» (`/silabos`)

**Para qué sirve:** ver todos los sílabos del observatorio (los PDF subidos a mano y los que llegan automáticamente desde silabos_v2), subir PDF nuevos y eliminar sílabos (resources/js/pages/syllabus/index.tsx; README.md).

- Miga de pan: «Sílabos» (resources/js/pages/syllabus/index.tsx).
- Orden: del más reciente al más antiguo; 10 por página (app/Http/Controllers/SyllabusController.php).

##### Cabecera
| Elemento | Texto | Qué hace |
|---|---|---|
| Buscador | placeholder «Buscar curso...» | Filtra mientras se escribe. Busca **solo** en el nombre de curso detectado/extraído (`structured_data.curso`), sin distinguir mayúsculas. No busca por nombre de archivo ni por código (app/Http/Controllers/SyllabusController.php). |
| Botón | «Subir Sílabos» | Abre el modal «Carga Masiva de Sílabos» (resources/js/pages/syllabus/index.tsx). |
| Botón (aparece al marcar filas) | «Eliminar Seleccionados (N)» | Eliminación en lote (ver abajo) (resources/js/pages/syllabus/index.tsx). |

##### Tabla (resources/js/pages/syllabus/index.tsx)
| Columna | Contenido |
|---|---|
| (casilla) | Selección de fila; la casilla del encabezado marca/desmarca todas las de la página. |
| Acciones | Botón «Eliminar». |
| Ver | Enlace «Ver PDF» si el sílabo tiene archivo (PDF subido). Si vino de silabos_v2 (sin archivo), muestra el texto gris «silabos_v2». |
| Archivo | Nombre del PDF. Si no hay archivo (silabos_v2): «`Nombre del curso` (v`versión`)». Si el sílabo fue reemplazado por una versión más nueva, agrega la marca «reemplazado». |
| Estado | Chip de color con el estado (ver tabla de estados). El estado `pending_link` se muestra como enlace «pendiente de vinculación» (tooltip «Vincular en Vinculación de cursos») que lleva a `/course-linking`. Los demás estados se muestran con su nombre técnico en inglés (p. ej. `processed`). |
| Curso | Nombre del curso (para PDF: el que detectó la IA; para silabos_v2: el nombre oficial que manda silabos_v2) (app/Services/Syllabus/SyllabusExtractionService.php). «-» si aún no hay. |
| Lenguajes | Lista separada por comas. |
| Tecnologías | Desplegable agrupado por categoría (la categoría real del catálogo; si la tecnología no tiene, «Sin categoría») (app/Http/Controllers/SyllabusController.php). Es solo de consulta. |
| Metodologías | Lista separada por comas. |

- Sin resultados: «No hay sílabos para mostrar.» (resources/js/pages/syllabus/index.tsx).
- Paginación numérica con «…» cuando hay más de 7 páginas (resources/js/pages/syllabus/index.tsx).
- **Actualización en vivo:** cuando un sílabo termina de procesarse, su fila (estado, curso, etc.) se actualiza sola, sin recargar la página (evento `syllabus.processed`, canal `observatory`) (resources/js/pages/syllabus/index.tsx; app/Events/SyllabusProcessed.php). Requiere que el servicio de tiempo real (Reverb) esté activo; si no, hay que recargar la página (README.md; docs/produccion/PASOS_PRODUCCION.md). Solo se actualizan filas ya visibles; un sílabo **nuevo** recibido de silabos_v2 aparece al recargar (resources/js/pages/syllabus/index.tsx).

##### Estados de un sílabo
| Estado (valor visible) | Color | Significado | Qué lo produce |
|---|---|---|---|
| `pending` | ámbar | En cola, esperando procesarse. | Al subir un PDF; al recibir uno de silabos_v2 que hay que procesar; al vincularlo (app/Http/Controllers/SyllabusController.php; app/Services/Syllabus/SyllabusIngestService.php; app/Services/Syllabus/CourseLinkService.php). |
| `processing` | azul | Se está leyendo (OCR, solo PDF) y analizando con IA. | El trabajo en segundo plano lo toma (app/Jobs/ProcessSyllabusJob.php; app/Jobs/ProcessIngestedSyllabusJob.php). |
| `processed` | verde | Terminado: lenguajes/tecnologías/metodologías extraídos y aplicados al curso. | Fin correcto del análisis (app/Services/Syllabus/SyllabusExtractionService.php). |
| `failed` | rojo | Falló (OCR, IA o error). | Excepción en el proceso. Los de silabos_v2 se reintentan hasta 3 veces (esperas de 60 s y 300 s) antes de quedar `failed` (app/Jobs/ProcessIngestedSyllabusJob.php). Los PDF fallan sin reintento automático (app/Jobs/ProcessSyllabusJob.php). |
| `superseded` | gris | Reemplazado por una versión más nueva de silabos_v2; no cuenta. | Llegó una versión mayor de la misma familia, o el sílabo llegó ya siendo versión menor (app/Services/Syllabus/SyllabusIngestService.php). |
| `ignored` | gris | Recibido de silabos_v2 pero no se procesa. | No tiene carrera del observatorio ni su curso pertenece a una (automático), o un administrador eligió «No vincular» (app/Services/Syllabus/SyllabusIngestService.php; app/Services/Syllabus/CourseLinkService.php). |
| `pending_link` → «pendiente de vinculación» | naranja | Recibido de silabos_v2, pero ningún curso del observatorio tiene su código. Espera vinculación manual. | Ingesta sin curso con ese código (app/Services/Syllabus/SyllabusIngestService.php). |

- Un **PDF** subido para un curso que ya tiene un sílabo vigente de silabos_v2 se guarda como `processed` pero marcado «reemplazado»: no modifica el curso (app/Services/Syllabus/SyllabusExtractionService.php).
- Cuando se procesa un sílabo de silabos_v2, los PDF del mismo curso (por id de curso o mismo nombre) pasan a «reemplazado» y dejan de contar para la alineación; se conservan (app/Services/Syllabus/SyllabusExtractionService.php; README.md).

##### Modal «Carga Masiva de Sílabos» (resources/js/pages/syllabus/SyllabusModal.tsx)
- Zona de arrastre: «Arrastra y suelta aquí tus archivos PDF» / «o haz clic para seleccionarlos». Admite varios archivos a la vez.
- Cada archivo aparece con su nombre, una barra de progreso y un botón «✕» para quitarlo. Si falla: «Error al subir».
- Botones: «Cerrar» y «Subir Archivos» (deshabilitado sin archivos). Los archivos se suben uno por uno; al terminar, la lista se refresca y el modal se cierra solo (~0,6 s).
- **Reglas del archivo:**

| Regla | Valor | Mensaje |
|---|---|---|
| Tipo (en el navegador) | Tipo `application/pdf` **y** extensión `.pdf` | alerta «Estos archivos no son PDF:» + lista de nombres (SyllabusModal.tsx) |
| Obligatorio | sí | mensaje de validación de Laravel (en inglés, idioma por defecto `en`) (app/Http/Controllers/SyllabusController.php; .env.example) |
| Tipo (servidor) | `pdf`, MIME `application/pdf` | «Solo se permiten archivos PDF válidos» (SyllabusController.php) |
| Firma del archivo | debe empezar por `%PDF` | «El archivo no es un PDF válido» (SyllabusController.php) |
| Tamaño máximo | 10 240 KB (10 MB) | mensaje de Laravel (SyllabusController.php) |

  En el modal, cualquier error del servidor se ve solo como «Error al subir» (no muestra el texto del mensaje) (SyllabusModal.tsx).
- No hay control de duplicados: subir el mismo PDF dos veces crea dos registros (app/Http/Controllers/SyllabusController.php).

##### Qué pasa al subir un PDF (proceso automático)
1. Se guarda el archivo y se crea el sílabo en estado `pending` (SyllabusController.php).
2. En segundo plano: estado `processing`; el PDF se sube a Google Cloud y se le aplica OCR (lectura de texto), esperando hasta ~2 minutos (app/Jobs/ProcessSyllabusJob.php).
3. La IA (OpenAI, modelo `gpt-4o-mini`) extrae curso, lenguajes, tecnologías (con su tipo) y metodologías de desarrollo de software, usando los nombres del catálogo existente (app/Services/Syllabus/SyllabusExtractionService.php).
4. Se busca el curso **por nombre** (sin tildes ni mayúsculas); **si no existe, se crea un curso nuevo** con ese nombre (SyllabusExtractionService.php).
5. Se **reemplazan por completo** los lenguajes, tecnologías y metodologías del curso por los del sílabo (salvo el caso «reemplazado» descrito arriba) y el estado pasa a `processed` (SyllabusExtractionService.php).
- Requiere que el «worker» de colas esté corriendo; si no, el sílabo se queda en `pending` (README.md).

##### Eliminar
- Uno: botón «Eliminar» → confirmación «¿Eliminar el sílabo "`archivo`"?» (si no tiene archivo, muestra el id). Error: «No se pudo eliminar.» (resources/js/pages/syllabus/index.tsx).
- En lote: marcar filas → «Eliminar Seleccionados (N)» → «¿Eliminar N sílabo(s)?». Error: «No se pudo eliminar en lote.» (index.tsx).
- Borra el registro y el PDF guardado. **No** deshace los lenguajes/tecnologías/metodologías que ya se aplicaron al curso (SyllabusController.php — no hay código que lo revierta).
- Eliminar un sílabo de silabos_v2 no impide que vuelva a llegar en la próxima ingesta o reenvío **[POR CONFIRMAR]** (la ingesta crea la fila si no existe: app/Services/Syllabus/SyllabusIngestService.php).

##### Pendientes / observaciones
- Enlace «Ver PDF»: apunta a `/<ruta del archivo>` (p. ej. `/syllabus/xxxx.pdf`), mientras que el visor previsto es la ruta `/syllabus/{id}`, que devuelve «Archivo no encontrado» (404) si falta el archivo. Si el enlace abre realmente el PDF depende de la configuración del servidor **[POR CONFIRMAR]** (resources/js/pages/syllabus/index.tsx; routes/web.php; app/Http/Controllers/SyllabusController.php).
- Al cambiar de página con el buscador lleno, se mantiene el término de búsqueda (resources/js/pages/syllabus/index.tsx).
- El texto de miga de pan apunta a `/syllabus` (ruta que no existe como listado) (resources/js/pages/syllabus/index.tsx; routes/web.php) **[POR CONFIRMAR]** efecto al hacer clic.

---

#### E.2 Carreras — «Carreras Profesionales» (`/careers`)

- Miga de pan: «Carreras Profesionales»; 10 por página, más recientes primero (resources/js/pages/careers/index.tsx; app/Http/Controllers/CareerController.php).

##### Cabecera
| Botón | Qué hace |
|---|---|
| «Nueva Carrera» | Abre el modal «Nueva Carrera». |
| «Eliminar Seleccionadas (N)» | Aparece al marcar filas. Ver observación: no funciona. |

No hay buscador ni filtros (resources/js/pages/careers/index.tsx).

##### Tabla
| Columna | Contenido |
|---|---|
| (casilla) | Selección. |
| Acciones | «Sincronizar» (abre el modal de cursos) y «Eliminar». |
| Nombre | Nombre de la carrera. |
| Facultad | o «-». |
| Título | Título que otorga, o «-». |
| Duración | Años, o «-». |
| Cursos | Cantidad de cursos asociados. |
| Estado | «Activa» (verde) / «Inactiva» (gris). |
| Creada | Fecha de creación (formato es-PE, p. ej. «5 oct 2025»). |

Sin datos: «No hay carreras registradas.» (resources/js/pages/careers/index.tsx).

##### Modal «Nueva Carrera» (resources/js/pages/careers/CareerModal.tsx; validación en app/Http/Controllers/CareerController.php)
| Campo | Tipo | Obligatorio | Límites | Por defecto |
|---|---|---|---|---|
| Nombre * | texto | sí | máx. 255 | vacío |
| Facultad | texto | no | máx. 255 | vacío |
| Título | texto | no | máx. 255 | vacío |
| Duración (años) | número | no | entero 1–10 | vacío |
| Descripción | texto largo | no | — | vacío |
| Activa | casilla | — | — | marcada |

- Botones «Cancelar» y «Guardar» («Guardando...» mientras guarda). Error: «No se pudo crear la carrera» (CareerModal.tsx).
- El sistema genera automáticamente un identificador interno (slug) a partir del nombre (CareerController.php).
- El campo «detalle» (`detail`) existe en el servidor pero no en el formulario (CareerController.php; CareerModal.tsx).
- **No hay opción para editar una carrera** desde la pantalla, aunque el servidor lo permite (ruta `PUT /careers/{id}`) (routes/web.php; resources/js/pages/careers/index.tsx).
- ⚠️ Si se cambiara el nombre de una carrera (por otra vía), cambia su slug y dejaría de coincidir con el mapeo de carreras de silabos_v2 (CareerController.php; config/silabos_v2.php). Las carreras mapeadas se identifican por slug (ver E.5).

##### Eliminar carrera
- «Eliminar» → «¿Eliminar la carrera "`nombre`"?». Error: «No se pudo eliminar la carrera.» (index.tsx).
- Borra la carrera y sus asociaciones con cursos (los cursos se conservan) (database/migrations/2025_10_05_112300_create_career_courses_table.php — borrado en cascada).
- No hay bloqueo para carreras mapeadas a silabos_v2; borrar una de ellas haría que sus sílabos se registren como errores de mapeo en el log de revisión (app/Services/Syllabus/SyllabusExtractionService.php).
- **Eliminación en lote: no funciona.** El botón llama a `/careers/bulk-delete`, ruta que no existe; siempre mostrará «No se pudo eliminar en lote.» (index.tsx; routes/web.php) — pendiente.

##### Modal «Sincronizar cursos con carrera» (botón «Sincronizar») (resources/js/pages/careers/CareerCoursesModal.tsx)
1. **Semestre de los cursos a agregar** — texto, placeholder «Ej: 1er ciclo», ayuda «Opcional. Se aplica a todos los seleccionados.»; máx. 50 caracteres (app/Http/Controllers/CareerController.php).
2. Botón «Agregar seleccionados» / «Agregar N seleccionado(s)» (deshabilitado si no hay selección). Los cursos se agregan como **obligatorios** (`is_mandatory = true`); no hay forma de marcarlos como electivos en pantalla. Error: «Error al asociar los cursos».
3. Buscador «Buscar curso por nombre o código...» (sin tildes ni mayúsculas; busca en nombre y en el código de silabos_v2).
4. Tabla **cursos disponibles**: «✓», «Código», «Curso disponible». Se marca haciendo clic en la fila o la casilla. Lista **todos** los cursos (también los ya asociados) (app/Http/Controllers/CourseController.php `listAll`). Sin resultados: «No se encontraron cursos.»
5. Tabla **«Cursos asociados»**: «Código», «Curso», «Semestre», «Obligatorio» (✅ / —), «Acción» (icono papelera, «Quitar `curso`»). Sin datos: «No hay cursos asociados.»
6. Columna «Código»: muestra el código de silabos_v2 como etiqueta, o «Sin código» en gris.
7. Quitar: confirmación «¿Quitar este curso de la carrera?».
8. Botón «Cerrar».

Reglas:
- Agregar un curso ya asociado no lo duplica; actualiza su semestre (y lo deja obligatorio) (CareerController.php `attachCourse` con `syncWithoutDetaching`).
- Sincronizar solo cambia la relación carrera↔curso; no toca lenguajes/tecnologías/metodologías del curso (README.md).
- Efecto en silabos_v2 (README.md):
  - si se quita un curso de una carrera mapeada, la siguiente llegada de su sílabo lo vuelve a vincular (si el sílabo trae esa carrera); la ingesta nunca desvincula;
  - quitar un curso de su única carrera mapeada puede hacer que sus sílabos se ignoren en la próxima ingesta.
- Existe además `POST /careers/{id}/sync-courses`, que **reemplaza** todos los cursos de la carrera; la pantalla no lo usa (routes/web.php; README.md).
- Existe un recurso `career-courses` (listar/editar semestre y obligatoriedad/borrar relaciones), sin pantalla que lo use (routes/web.php; app/Http/Controllers/CareerCourseController.php; búsqueda sin resultados en resources/js) — no visible al usuario.

---

#### E.3 Cursos ISIL — «Gestión de Cursos» (`/courses`)

- Miga de pan: «Cursos»; 10 por página, más recientes primero (sin búsqueda) o alfabético (con búsqueda) (resources/js/pages/courses/index.tsx; app/Http/Controllers/CourseController.php).

##### Cabecera, búsqueda y filtro
| Elemento | Texto | Qué hace |
|---|---|---|
| Botón | «Mantenimiento Cursos ISIL» | Abre el modal «Nuevo Curso». |
| Botón (con selección) | «Eliminar Seleccionados (N)» | Ver observación: no funciona. |
| Buscador | «Buscar curso por nombre o código...» | Busca en nombre **y** código de silabos_v2 (coincidencia parcial). Espera 0,6 s tras dejar de escribir. |
| Filtro | «Todos los cursos» / «Con código» / «Sin código» | Muestra todos, solo los que tienen código de silabos_v2, o solo los que no. Se combina con el buscador. |

(resources/js/pages/courses/index.tsx; app/Http/Controllers/CourseController.php)

##### Tabla
| Columna | Contenido |
|---|---|
| (casilla) | Selección. |
| Acciones | «Editar», «Eliminar». |
| ID | Id interno. |
| Código | Código de silabos_v2. Si no tiene: etiqueta «Sin código» que es un enlace a `/course-linking` (tooltip «Vincular en la página Vinculación de cursos»). |
| Nombre | Nombre del curso. |
| Lenguajes / Tecnologías / Metodologías | Etiquetas; «-» si no hay. |
| Certificaciones | Etiquetas moradas; «-» si no hay. |

Sin datos: «No hay cursos para mostrar.» (resources/js/pages/courses/index.tsx).

##### Modal «Nuevo Curso» / «Editar Curso» (resources/js/pages/courses/modal.tsx; validación en app/Http/Controllers/CourseController.php)
| Campo | Tipo | Obligatorio | Límites / valores |
|---|---|---|---|
| Nombre del Curso * | texto | sí | máx. 255. Vacío → alerta «El nombre es obligatorio». |
| Código en silabos_v2 | texto (monoespaciado) | no | máx. 50; **único** (no puede repetirse en otro curso). Placeholder «Ej. 30653 (vacío si no tiene)». Ayuda: «Debe ser exactamente el código del curso en silabos_v2. Los sílabos aprobados se enlazan solo por este código.» Se quitan espacios al inicio/fin; vacío = sin código. |
| Lenguajes | botones seleccionables (verde = elegido) | no | del catálogo; los elegidos primero, luego alfabético. |
| Tecnologías | botones (azul) | no | ídem. |
| Metodologías | botones (morado) | no | ídem. |
| Certificaciones | casilla por certificación + nivel («Intro», «Intermedio», «Avanzado»; por defecto Intermedio) + peso (número 0–1, paso 0,1; por defecto 1) | no | |

- Avisos dinámicos:
  - Si el curso tiene código: «Este curso recibe sílabos aprobados de silabos_v2. Los lenguajes, tecnologías y metodologías que guardes aquí se sobrescribirán cuando llegue la próxima versión aprobada de su sílabo.»
  - Si se edita un código existente: texto rojo «Cambiar el código desvincula este curso de los sílabos con el código `X`.» y, al guardar, confirmación «¿Cambiar el código `X` por `Y` (o «(ninguno)»)? Los sílabos de silabos_v2 con el código `X` dejarán de enlazarse a este curso.»
- Botones «Cancelar» y «Guardar» («Guardando...»). Errores: se muestra el primer mensaje de validación del servidor (en inglés por defecto, p. ej. código repetido) o «No se pudo guardar el curso.» (modal.tsx; .env.example `APP_LOCALE=en`).
- Al editar sin marcar ninguna certificación, se borran las certificaciones del curso (CourseController.php).
- Asignar un código aquí **no** procesa automáticamente los sílabos que estaban «pendientes de vinculación» con ese código: se procesarán cuando ese sílabo vuelva a llegar desde silabos_v2, o ejecutando el comando `silabos:procesar-vinculados` (TI). Para vincular y procesar al instante, usar la página «Vinculación de cursos» (app/Http/Controllers/CourseController.php; app/Services/Syllabus/CourseLinkService.php; app/Console/Commands/Silabos/SilabosProcesarVinculados.php).

##### Eliminar curso
- «Eliminar» → «¿Eliminar el curso "`nombre`"?». Error: «No se pudo eliminar.» (index.tsx).
- Los sílabos enlazados al curso **no se borran**: quedan sin curso (CourseController.php `destroy`). Las asociaciones con carreras se borran (database/migrations/2025_10_05_112300_create_career_courses_table.php).
- Si el curso tenía código de silabos_v2, el próximo sílabo con ese código quedará «pendiente de vinculación» (app/Services/Syllabus/SyllabusIngestService.php).
- **Eliminación en lote: no funciona.** Llama a `/courses/bulk-delete`, ruta inexistente → «No se pudo eliminar en lote.» (index.tsx; routes/web.php) — pendiente.

---

#### E.4 Vinculación de cursos (`/course-linking`)

**Para qué sirve:** resolver los sílabos aprobados en silabos_v2 cuyo código no corresponde a ningún curso del observatorio (estado «pendiente de vinculación»).

- Encabezado: «Vinculación de cursos» y a la derecha «`N` pendiente(s) · `M` curso(s) sin código».
- Texto: «Sílabos aprobados en silabos_v2 cuyo código no corresponde a ningún curso del observatorio. Al vincular, el código se guarda en el curso elegido y el sílabo se procesa.»
(resources/js/pages/course-linking/index.tsx)

##### Tabla (ordenada por nombre del curso de silabos_v2) (app/Http/Controllers/CourseLinkController.php; resources/js/pages/course-linking/index.tsx)
| Columna | Contenido |
|---|---|
| Código | Código del curso en silabos_v2. |
| Sílabo (silabos_v2) | Nombre del curso y debajo «`ISIL/SCL` · id `n` · v`versión` · `periodo`». |
| Carreras (silabos_v2) | Etiquetas «`código` `nombre`». Resaltadas (celeste) = «Carrera del observatorio» (mapeada); grises = «Carrera que el observatorio no sigue» (tooltips). Si no trae: «Sin carreras». |
| Curso del observatorio | Selector con buscador (ver abajo). |
| Acción | Botón «Guardar» («Guardando…»). |

Sin datos: «No hay sílabos pendientes de vinculación.»

##### Selector «Curso del observatorio»
- Texto inicial: «Elegir curso…». Buscador: «Buscar curso para «`nombre`»…» (sin tildes ni mayúsculas).
- Primera opción: «No vincular (marcar como ignorado)».
- Solo lista cursos **sin código**; debajo de cada uno, sus carreras. Sin coincidencias: «No hay cursos sin código con ese nombre.»
- **Sugerencia automática:** se preselecciona el curso con nombre más parecido, marcado «sugerido · `NN`%», solo si el parecido es ≥ 60 %. Si dos sílabos sugieren el mismo curso, solo se preselecciona en el de mayor parecido (app/Support/CourseNameSimilarity.php; index.tsx).
- Un curso elegido en una fila desaparece de las demás filas (un código → un curso) (index.tsx).

##### Guardar
| Elección | Resultado | Mensaje |
|---|---|---|
| Nada | No guarda | «Elige un curso o la opción "No vincular".» |
| Un curso | Se guarda el código en ese curso y el sílabo (y cualquier otro pendiente con el mismo código) pasa a `pending` y se procesa en segundo plano | Aviso verde: «Código `X` asignado a «`curso`». Sílabo enviado a procesar.» |
| No vincular | Confirmación «¿No vincular el sílabo `X` — `nombre`? Quedará como ignorado.»; el sílabo pasa a `ignored` y se registra en el log de revisión | «El sílabo `X` quedó como ignorado.» |

Errores posibles (se muestran en rojo bajo el selector) (app/Services/Syllabus/CourseLinkService.php; app/Http/Controllers/CourseLinkController.php):
- «El sílabo `X` ya no está pendiente de vinculación.»
- «El curso «`curso`» ya tiene el código `Y`.»
- «El código `X` ya está asignado al curso «`curso`».»
- «El curso elegido no existe.»
- Genérico: «No se pudo guardar.»

Tras guardar, se recargan la lista, los cursos sin código y el contador del menú (index.tsx).
- Un sílabo marcado «No vincular» no se reevalúa automáticamente; si llega una **versión nueva** del mismo código, vuelve a quedar pendiente (README.md). No hay botón para deshacer un «No vincular» **[POR CONFIRMAR]** alternativa.
- No se pueden crear cursos desde esta pantalla; si el curso no existe, créelo primero en «Cursos ISIL» sin código y luego vincúlelo aquí (deducido de CourseLinkController.php: solo lista cursos existentes sin código).

---


### 2.5 Datos › Empleos y Topics IA

#### F.9 Datos › Empleos — `/job-offers`

- **Título y migas:** «Ofertas de Empleo» (P/job_offers/index.tsx).
- **Orden y paginación:** de la más reciente a la más antigua; 10 por página. La paginación conserva los filtros aplicados (C/JobOfferController.php `index`; P/job_offers/index.tsx `goToPage`).

##### Filtros — panel «Filtros Avanzados»

El panel está arriba de la tabla (P/job_offers/JobOfferFilters.tsx).

**Selectores múltiples.**
- Cada uno muestra las opciones elegidas como etiquetas que se quitan con «x». Si no hay ninguna, muestra «Sin selección».
- Para abrir la lista se pulsa «Seleccionar <campo>». La lista trae su propio buscador, «Buscar <campo>...» (P/job_offers/JobOfferFilters.tsx).
- Las opciones salen de los valores distintos que existen en las ofertas (C/JobOfferController.php `$comboData`).

| Filtro | Campo de la oferta | Fuente |
|---|---|---|
| Empresa | company | P/job_offers/JobOfferFilters.tsx; C/JobOfferController.php |
| País | country | ídem |
| Ciudad | city | ídem |
| Fuente | source | ídem |
| Modalidad | modality | ídem |
| Tipo de Trabajo | job_type | ídem |
| Tipo Remoto | remote_type | ídem |
| Carga Laboral | workload | ídem |
| Publicado Desde / Publicado Hasta | published_at (fecha) | ídem |
| Registrado Desde / Registrado Hasta | created_at (fecha) | ídem |

**Botones del panel** (P/job_offers/JobOfferFilters.tsx):
- «Aplicar Filtros» recarga la página con los filtros elegidos y muestra «Aplicando...» mientras tanto.
- «Limpiar Todo» recarga la página y quita los filtros.

##### Exportación

- **Botón:** «📊 Exportar Excel (Hasta 4 mil Registros)». Mientras trabaja muestra «Generando...» (P/job_offers/index.tsx).
- Descarga `job_offers.xlsx` con los filtros activos, hasta **4 000 filas** (C/JobOfferController.php `exportExcel`, `limit(4000)`).
- **Columnas del Excel:** ID, Titulo, Empresa, Pais, Region, State Code, Ciudad, Ciudad ASCII, Location, ZIP, Modalidad, Job Type, Remote Type, Workload, Experiencia, Educación Nivel, Educación Campo, Certificaciones, Requisitos, Skills, Descripción, Beneficios, Latitud, Longitud, Salario Min, Salario Max, Moneda, Tipo Pago, Fuente, Search Query, External ID, URL, Application URL, Application Type, Publicado, Expira, Creado, Actualizado (C/JobOfferController.php).
- Si no hay datos, el servidor responde «⚠ No hay datos para exportar con los filtros aplicados.». La pantalla no muestra ese mensaje: descarga lo que reciba (C/JobOfferController.php; P/job_offers/index.tsx).
- **[POR CONFIRMAR: posible defecto]** La ruta `GET /job-offers/{id}` está registrada **antes** que `GET /job-offers/export-excel` y `GET /job-offers/fetch`, y no restringe `{id}` a números (web.php). Si Laravel resuelve las rutas en ese orden, la exportación entraría en la ruta de detalle y el archivo descargado no sería un Excel válido. Hay que verificarlo en el servidor.

##### Encabezado y acciones masivas (P/job_offers/index.tsx)

- «Eliminar Seleccionadas (N)» solo aparece si hay filas marcadas. Pide confirmación nativa, «¿Eliminar N oferta(s)?», y borra físicamente las ofertas (C/JobOfferController.php `bulkDelete`). Si falla: «No se pudo eliminar en lote.».
- «+ Importar CSV» abre el asistente de CSV (ver más abajo).
- «+ Importar Automático» abre la importación desde portales (ver más abajo).

##### Tabla (P/job_offers/index.tsx)

| Columna | Significado |
|---|---|
| (casilla) | Selección; la del encabezado marca o desmarca toda la página |
| Acciones | «Eliminar» (confirmación «¿Eliminar la oferta "<título>"?»; si falla: «No se pudo eliminar la oferta.») |
| Título | Enlace que abre el detalle |
| Empresa / País / Ciudad / Modalidad | Datos de la oferta o «-» |
| Salario | «<mín> - <máx> <moneda>» o «N/A» |
| Fuente | Portal de origen (etiqueta) |
| Publicado | Fecha de publicación |
| Registrado | Fecha de alta en el sistema |

- **Tabla vacía:** «No hay ofertas registradas.».
- **No hay opción para editar** una oferta en la pantalla, aunque existe la ruta `PUT /job-offers/{id}` (P/job_offers/index.tsx; web.php). Tampoco hay alta manual, aunque existe `POST /job-offers` (ídem). **Pendiente.**

##### Modal «Detalle de Oferta» (P/job_offers/JobOfferDetailModal.tsx)

- Se abre al hacer clic en el título.
- Muestra solo los campos que tienen valor: Título, Empresa, País, Región, Ciudad, Modalidad, Carga laboral, Nivel experiencia, Educación requerida, Certificaciones, Requerimientos, Habilidades, Descripción, Beneficios, Salario mínimo, Salario máximo, Moneda, Tipo de compensación, Fuente, Query de búsqueda, Publicado, Registrado y URL.
- Si la oferta tiene URL, muestra el botón «Ir a la oferta original».
- Si falla la carga: «No se pudo cargar el detalle.» (P/job_offers/index.tsx).

##### Modal «Importar Ofertas por busqueda» (botón Importar Automático)

Fuente: P/job_offers/JobOfferModal.tsx; C/JobOfferController.php.

**Campos.**
- Combo de fuente: «GetOnBoard» (por defecto) o «Adzuna (US)».
- Caja de búsqueda, placeholder «Buscar...», con valor inicial «programador».

**Botones.**
- «Obtener» llama a `POST /job-offers/preview` y muestra una vista previa de **10 ofertas** (`per_page=10` / `results_per_page=10`). Mientras trabaja muestra «Cargando...».
  - Columnas de la vista previa: Título (enlace a la oferta), Empresa, País, Ciudad, Modalidad, Salario, Publicado.
- «Guardar en BD» llama a `POST /job-offers/import`, guarda esas ofertas y recarga la lista.
  - Si una oferta ya existe con la **misma URL**, la actualiza en lugar de duplicarla (`updateOrCreate(['url' => …])`).
  - Si la oferta no trae ciudad, el sistema intenta deducirla del texto, comparándolo con la tabla de ciudades.
- «Cancelar» cierra el modal.

**Errores.**
- «No se pudo obtener datos de la API.»
- «No se pudo guardar en la base de datos.»
- El servidor puede responder «Error al consultar Adzuna», «Error al consultar GetOnBoard», «Fuente no soportada», «Error interno en preview» o «Error interno al importar.».
- **Detalle:** el mensaje de éxito del servidor, «✅ Se importaron N ofertas correctamente.», no se muestra en pantalla.

##### Modal de importación CSV (botón Importar CSV)

Fuente: P/job_offers/JobOfferCsvModal.tsx; C/JobOfferImportController.php.

**Paso 1 — «Subir archivo CSV».**
- Campo «Selecciona un archivo CSV»; solo acepta `.csv`, y el servidor admite `csv` y `txt`.
- Botón «Detectar columnas»; mientras trabaja muestra «Analizando CSV...».
- Si no se eligió archivo: «Selecciona un archivo CSV». Si el archivo no se puede leer: «❌ Error al analizar CSV. Verifica el archivo.».

**Paso 2 — «Mapeo de columnas».**
- Bajo el título «Mapeo de columnas CSV → Campos del sistema», cada columna del CSV tiene un combo con estas opciones: «— Selecciona campo —», Título, Empresa, Ciudad, País, Modalidad, Fecha, URL, Latitud, Longitud. El sistema preselecciona la opción cuando el encabezado coincide con el nombre técnico del campo.
- Campo «Fuente de datos», con valor inicial «LocalCSV» y sugerencias GetOnBoard, Computrabajo, Adzuna y LocalCSV.
- Botón «Importar a Base de Datos»; mientras trabaja muestra «Importando...».

**Importante para el usuario [POR CONFIRMAR].**
- El mapeo elegido **no se usa**: el servidor carga el CSV con un orden fijo de columnas: `title, company, city, country, modality, fecha (AAAA-MM-DD HH:MM:SS), url, latitude, longitude` (C/JobOfferImportController.php `process`).
- Cuando la URL ya existe, actualiza la oferta (`ON DUPLICATE KEY UPDATE`).
- La carga depende de una carpeta fija del servidor de base de datos, `C:/Program Files/MariaDB 12.0/data/`, por lo que solo funciona en un servidor con esa instalación.
- Al terminar aparece «✅ Importación completa: Insertados: 0 Saltados: 0»: el servidor no devuelve esos contadores, así que siempre muestra 0.
- Si falla: «Error al procesar CSV».

---

#### F.10 Datos › Topics IA — `/topics-ia`

**Qué es.** Un *topic* es una búsqueda temática; por ejemplo, «certificaciones de Machine Learning 2025». Al ejecutarlo, la IA (OpenAI) busca tendencias y fuentes y las guarda (app/Services/Trends/RunTrendTopicService.php). Lo que guarda:
- las tendencias, en la tabla de tendencias tecnológicas (`TechnologyTrend::updateOrCreate` por nombre, año y trimestre);
- las fuentes usadas, como fuentes registradas (`ScrapingSource::firstOrCreate`).

**Pantalla.**
- **Título y migas:** «Topics IA» (P/topicsIA/Index.tsx).
- **Botón:** «Nuevo Topic IA» (P/topicsIA/Index.tsx).
- **Buscador:** placeholder «Buscar topic…», con una espera de 500 ms. Busca en el nombre y en el Search Query; la carga inicial busca también en la categoría (C/TopicsIAController.php). El servidor acepta un filtro `intent` que la pantalla no ofrece.
- **Orden:** primero los activos (C/TopicsIAController.php).
- **Tabla vacía:** «No hay topics registrados.» (P/topicsIA/Index.tsx).
- **Paginación:** 10 por página; muestra todos los números (P/topicsIA/Index.tsx).

**Columnas** (P/topicsIA/Index.tsx):

| Columna | Significado |
|---|---|
| Acciones | «Ejecutar», «Editar», «Eliminar» y, si corresponde, «Reactivar» |
| Topic | Nombre |
| Intent | Tipo de análisis (`certification`, `technology_trend`, `skill`, `workforce`, `mixed`) |
| Estado IA | Estado de la última ejecución: `idle`, `queued`, `running`, `success`, `failed` |
| Activo | Casilla activar/desactivar |
| Fallos | Número de ejecuciones fallidas (en rojo) |
| Éxitos | Número de ejecuciones exitosas (en verde) |

**Acciones.**
- **Ejecutar** (P/topicsIA/Index.tsx; C/TopicsIAController.php `run`; app/Jobs/RunTrendTopicJob.php):
  - Muestra el aviso «Ejecución iniciada» / «La IA está procesando las tendencias…».
  - El topic pasa a `queued` con el mensaje «Encolado para ejecución» y se envía a la cola de trabajos en segundo plano.
  - La pantalla consulta el estado cada 3 s (`/topics-ia/{id}/status`) y deja de hacerlo cuando el estado ya no es `running`.
  - Mientras corre, el botón muestra un ícono giratorio y queda deshabilitado.
  - Si el topic ya está en `running` desde hace menos de 5 minutos, no se vuelve a encolar (`already_running`). Pasados 5 minutos se considera colgado y se permite relanzarlo.
  - El trabajo en segundo plano pone el estado `running` («Procesando…»). Al terminar pone `success` («Procesado correctamente») y suma un éxito. Si falla, pone `failed` con el mensaje de error, suma un fallo y reintenta una vez (2 intentos como máximo, 5 min de límite).
  - Para los topics de tipo `certification`, no encontrar resultados no se considera error. Para los demás tipos falla con «No se generaron resultados para el topic.» (app/Services/Trends/RunTrendTopicService.php).
  - Si la ejecución no puede iniciarse: «Error» / «No se pudo ejecutar el topic.» o el mensaje del servidor (P/topicsIA/Index.tsx).
  - **Requiere un trabajador de colas activo** (ver F.12). Sin él, el topic se queda en `queued`.
- **Editar:** abre el formulario (ver más abajo).
- **Eliminar:** confirmación «¿Eliminar "<nombre>"?» con el botón «Eliminar». La eliminación es física y no muestra mensaje de éxito (P/topicsIA/Index.tsx; C/TopicsIAController.php).
- **Activo (casilla):** cambia `active` en el acto, sin mensaje (P/topicsIA/Index.tsx; C/TopicsIAController.php `toggle`).
- **Reactivar:** solo aparece si el topic está **inactivo y tiene 3 o más fallos**. Lo deja activo, con 0 fallos, sin motivo de desactivación y con el estado `idle` (P/topicsIA/Index.tsx; C/TopicsIAController.php `reactivate`).
  - En el código revisado ningún proceso desactiva un topic automáticamente: el método `disable()` del modelo no se llama desde ningún sitio (app/Models/TrendTopic.php). La regla de los 3 fallos solo controla cuándo se ve el botón **[POR CONFIRMAR]**.

**Formulario «Nuevo Topic IA» / «Editar Topic IA»** (P/topicsIA/TopicModal.tsx; C/TopicsIAController.php):

| Campo | Tipo | Oblig. | Valores / límites |
|---|---|---|---|
| Nombre del Topic * | texto | Sí | máx. 255; «Ej: Certificaciones de Machine Learning 2025» |
| ¿Qué tipo de análisis hará la IA? | combo | Sí | Certificaciones · Tendencias tecnológicas · Skills / Habilidades · Workforce / Mercado laboral · Mixto (exploratorio) (por defecto); ayuda: «Define qué tipo de información la IA está autorizada a devolver.» |
| Search Query * | área de texto | Sí | «Ej: "machine learning certifications demand 2025"» |
| Categoría específica | combo | No | depende del tipo (por ejemplo, Certificaciones: «Cloud Certifications», «AI & Machine Learning Certifications», …); opción vacía «— Seleccionar categoría —»; ayuda: «Depende del tipo de análisis seleccionado.»; máx. 150 |
| Subcategoría (opcional) | texto | No | «Ej: Machine Learning Engineer»; máx. 150 |
| Peso del Topic (1–10) | número | No | 1 a 10 |
| Resultados mínimos | número | No | inicial 3; el servidor admite de 1 a 10 |

- Botones: «Cancelar» y «Crear» o «Actualizar» («Guardando…» mientras se envía).
- Mensajes de validación: «Campo requerido» / «Ingrese el nombre del Topic.» y «Ingrese el Search Query.».
- Mensajes de resultado: «Creado» / «Topic creado correctamente.»; «Actualizado» / «Topic actualizado correctamente.»; «Error» / «No se pudo guardar el Topic.» (P/topicsIA/TopicModal.tsx).
- Todo topic nuevo nace activo, en modo `manual` y con el estado `idle` (C/TopicsIAController.php `store`).
- **Detalle [POR CONFIRMAR]:** al editar, el formulario no precarga el «Peso del Topic». Si no se vuelve a escribir, el servidor lo guarda como 1 (P/topicsIA/TopicModal.tsx; C/TopicsIAController.php `update`).

---


### 2.6 Indicadores › Origen de Datos › Comandos Fuentes

#### D.2 Indicadores › Origen de Datos › **Comandos Fuentes**

| Dato | Valor |
|---|---|
| Menú | Indicadores › Origen de Datos › "Comandos Fuentes". El menú se muestra a todos los usuarios (app-sidebar.tsx) |
| URL | `/sources` |
| Permiso | `administrar`. Sin este permiso la pantalla responde "acceso denegado" (routes/web.php, `middleware('permission:administrar')`) |
| Breadcrumb | Fuentes (pages/sources/index.tsx) |
| Título | **"Fuentes (APIs)"**. Subtítulo: "Gestiona las fuentes de datos para scraping y APIs." (index.tsx) |

##### Qué es una "fuente"
Cada fila es un registro de la tabla `source_status` (app/Models/SourceStatus.php). Cada comando de extracción de ofertas crea o actualiza automáticamente su propia fila al ejecutarse. El nombre de la fila combina el portal y el tipo de búsqueda, por ejemplo `remotive_technologies` o `adzuna_languages` (app/Console/Commands/RemotiveByTechnologiesCommand.php; AdzunaByLanguagesCommand.php; app/Services/SourceStatusService.php). También se pueden crear fuentes a mano con "+ Nueva fuente".

##### Tarjetas superiores
| Tarjeta | Cálculo |
|---|---|
| "Total fuentes" | Cantidad de filas en `source_status` (SourceStatusController.php `getData`) |
| "Registros totales" | **Muestra el número fijo "31,655"**, escrito directamente en la pantalla. No se calcula [PENDIENTE]. El backend sí calcula `registros_totales` (total de `job_offers`), pero la pantalla no lo usa (index.tsx; SourceStatusController.php) |

El backend también calcula `activas` (fuentes con último estado "success"), `con_errores` (último estado "failed") y `uptime` (porcentaje global de corridas exitosas en `scraper_runs`). **Ninguno se muestra** (SourceStatusController.php).

##### Tabla "Listado de fuentes"
- Bajo el título aparece "{n} registros", que es la cantidad de filas de la página actual. Se muestran 13 por página, de la más reciente a la más antigua (index.tsx; SourceStatusController.php `paginate(13)`).
- El buscador "Buscar fuente..." **no hace nada**: no tiene lógica conectada [PENDIENTE] (index.tsx).

| Columna | Significado |
|---|---|
| Acciones | 👁 **Ver detalle**, ✏️ **Editar** y 🗑 **Eliminar**. El botón ▶ "Ejecutar" está **comentado** [PENDIENTE / OCULTO], y la ruta `/sources/{id}/run` no está registrada (index.tsx) |
| Nombre | Nombre de la fuente |
| URL | URL de la API, o "—" si no tiene |
| Última ejecución | Fecha de creación de la oferta más reciente de `job_offers` cuya columna `source` coincide **exactamente** con el nombre de la fuente. Si no hay coincidencias, usa la última finalización registrada por el comando. Formato DD/MM/YYYY HH:mm; si no hay ninguna fecha, muestra "Nunca" (SourceStatusController.php; index.tsx) |
| Registros | Cantidad de ofertas de `job_offers` con ese mismo nombre de fuente. Si no hay, muestra el acumulado de insertados del comando (`total_records_inserted`), o 0 |

> Nota: los comandos guardan las ofertas con fuente "Remotive", "Adzuna", etc., pero registran el estado como `remotive_technologies`, `adzuna_languages`, etc. Por eso, en las fuentes creadas por comandos las dos columnas usan normalmente el valor de respaldo (RemotiveByTechnologiesCommand.php líneas 37 y 166). [POR CONFIRMAR con datos reales]

##### Acción 👁 "Ver detalle" (modal)
Consulta `GET /sources/{id}/details`. Si falla, aparece la alerta "Error cargando detalles" (index.tsx; SourceStatusController.php `details`). Contenido (pages/sources/SourceDetailsModal.tsx):
- Encabezado: nombre de la fuente y "Source #{id}".
- **Estado:** "Último estado:" (running / success / failed), "Conexión:" (unknown / ok / failed) y "Uptime:" {x}%. El uptime se calcula como corridas exitosas ÷ (exitosas + fallidas) × 100, con 2 decimales; si no hay corridas, vale 0 (SourceStatusController.php).
- **API:** "URL:", "App ID:" y "API Key:". La clave se muestra oculta como "••••••••••".
- **Métricas:**
  - "Total encontrados": ofertas encontradas, acumulado histórico.
  - "Total insertados": ofertas nuevas guardadas, acumulado.
  - "Última corrida": insertadas en la última ejecución.
  - "Skipped": descartadas o repetidas, acumulado.
  - Los acumulados suman cada corrida exitosa (app/Services/SourceStatusService.php `success`).
- **Fechas:** "Creado:", "Último inicio:", "Última finalización:", "Último éxito:" y "Último fallo:".
- Bloque rojo "Último error" con el mensaje de error, solo si existe (hasta 2000 caracteres).
- Botón "Cerrar". También se cierra con ✕ o haciendo clic fuera.

##### "+ Nueva fuente" y ✏️ "Editar" (modal)
Título del modal: "Nueva fuente" o "Editar fuente" (pages/sources/modal.tsx).

| Campo | Tipo | Obligatorio | Valor por defecto / límites |
|---|---|---|---|
| "Nombre *" | texto (placeholder "LinkedIn Jobs") | Sí | Máximo 100 caracteres. Debe ser **único** (modal.tsx; SourceStatusController.php `store`/`update`) |
| "API URL" | texto | No | Viene prellenado con `https://www.arbeitnow.com/api/job-board-api`. Máximo 500 |
| "Configuración avanzada" (se despliega con + / −) › "App ID" | texto | No | Máximo 255 |
| … › "API Key" | texto (visible en claro al editar) | No | Máximo 500 |

**Flujo de guardado** (modal.tsx):
1. Si falta el nombre, aparece el aviso "Campo requerido" / "El nombre es obligatorio".
2. Se pide confirmación: "¿Crear fuente?" o "¿Actualizar fuente?", con los botones "Sí" y "Cancelar".
3. Mientras se guarda aparece "Guardando...".
4. Si todo sale bien: "Guardado correctamente". El modal se cierra y la tabla se recarga.
5. Si hay un error: aparece "Error" con el mensaje del servidor. Un nombre repetido se informa como **"Error al crear"** o **"Error al actualizar"**, porque el servidor captura la validación como un error genérico (SourceStatusController.php).

Una fuente nueva queda con estado de conexión "unknown", sin último estado y con contadores en 0 (SourceStatusController.php `store`).

Al editar, dejar un campo vacío **no lo borra**: se conserva el valor anterior (SourceStatusController.php `update`, operador `??`).

**Botón "Test API"** (el texto cambia a "Probando..." mientras corre). Envía `POST /sources/test-api` con la URL, la App ID y la API Key escritas en el modal. **No guarda nada** (modal.tsx; SourceStatusController.php `testApi`).
- Sin URL: aviso "API URL requerida".
- Forma de autenticación según los campos completados:
  - App ID y API Key: se envían como parámetros `app_id` y `app_key`, como pide Adzuna.
  - Solo API Key: se envía como token "Bearer".
  - Ningún dato: se trata como API pública.
  - El tiempo de espera es de 15 s.
- Si funciona: aparece "API funcionando" / "Se encontraron N registros", un ✔ verde y la tabla **"Datos encontrados"** con las columnas Título, Empresa y Ubicación (máximo 10 filas). Interpreta respuestas con el formato de Arbeitnow (`data`) o de Remotive (`jobs`), o una lista simple.
- Si falla: aparece "Conexión fallida", una ✖ roja y uno de estos mensajes: "API privada o requiere autenticación" (HTTP 401/403), "La API respondió con error", "No se pudo conectar a la API" o "Error interno".

##### 🗑 "Eliminar"
Pide confirmación con "¿Eliminar {nombre}?". Si se acepta, borra la fila y recarga la tabla. No muestra mensaje de éxito (index.tsx; SourceStatusController.php `destroy`).

> Si un comando vuelve a ejecutarse después, la fila se recrea sola (`updateOrCreate` en SourceStatusService.php `start`).

##### Estados de una fuente
| Campo | Valores y transición |
|---|---|
| Último estado | Vacío; la tabla lo trata como "pending". Pasa a **running** al empezar un comando y termina en **success** o **failed** (SourceStatusService.php `start`/`success`/`failed`) |
| Conexión | **unknown** al iniciar una corrida. Pasa a **ok** o **failed** según el comando (`connectionOk`/`connectionFailed`) |

**Observaciones importantes:**
- Cada vez que un comando arranca, **sobrescribe la "API URL" con su URL fija y la "App ID" con el nombre de la fuente** (SourceStatusService.php `start`).
- Los comandos revisados usan URL **fijas en su código**, no la URL configurada en esta pantalla. Por lo tanto, editar la URL **no cambia** lo que consulta el comando (RemotiveByTechnologiesCommand.php líneas 38-43). [POR CONFIRMAR para todos los comandos]

##### Rutas sin pantalla o rotas [PENDIENTE]
- `POST /sources/{id}/test-connection` prueba la URL guardada (espera de 5 s) y actualiza el estado de conexión. **No hay botón que la use** (SourceStatusController.php `testConnection`).
- `GET /sources/fetch` devuelve los datos en formato JSON. La pantalla lo usa para paginar y recargar (index.tsx).
- `GET /sources/{id}` apunta a un método `show` que **no existe**.
- `POST /sources/test-data` apunta a un método `testApiData` que **no existe**.
- `GET /sources/failed` está declarada **después** de `/sources/{id}`, así que la ruta anterior la captura y nunca llega a ejecutarse (routes/web.php; SourceStatusController.php).
- `/sources/test-api` y `/sources/{id}/details` **no exigen** el permiso `administrar`, solo sesión iniciada (routes/web.php).

##### Fuentes de datos externas (en lenguaje simple)

**A) Portales y API de empleo.** Los comandos traen ofertas laborales reales que mencionan tecnologías, lenguajes, metodologías o certificaciones del catálogo. Las guardan en `job_offers` y las vinculan al catálogo. Los nombres van como `portal:tipo`, donde tipo es languages, technologies, methodologies o certifications. Fuente de la tabla: el `$signature` y la URL de cada archivo en app/Console/Commands.

| Portal | Qué es | Comandos | URL de consulta |
|---|---|---|---|
| Adzuna | Agregador internacional de empleos (usa App ID + clave) | `adzuna:*` | api.adzuna.com/v1/api/jobs |
| Arbeitnow | Bolsa de empleo europea con API pública | `arbeitnow:*` | arbeitnow.com/api/job-board-api |
| Computrabajo | Portal latinoamericano (se lee la página web) | `computrabajo:*` | computrabajo.com |
| GetOnBoard | Portal tech latinoamericano | `getonboard:*`, `getonboard:import`, `joboffers:import` | getonbrd.com/api |
| Greenhouse | Bolsas de empleo de empresas que usan Greenhouse | `greenhouse:*` | boards-api.greenhouse.io |
| Jobicy | Empleos remotos | `jobicy:*` | jobicy.com/api/v2/remote-jobs |
| Jooble | Agregador internacional | `jooble:*` | jooble.org/api |
| Reed | Portal del Reino Unido | `reed:*` | reed.co.uk/api/1.0/search |
| RemoteOK | Empleos remotos | `remoteok:*` | remoteok.com/api |
| Remotive | Empleos remotos | `remotive:*` | remotive.com/api/remote-jobs |
| USAJOBS | Empleo público de EE. UU. | `usajobs:*` | data.usajobs.gov/api/search |
| Wantedly | Portal japonés / asiático | `wantedly:*` | wantedly.com/api/v1/projects |
| Selecta | [POR CONFIRMAR] | `selecta:languages` | (app/Console/Commands/SelectaByLanguagesCommand.php) |

**B) Fuentes de tendencias tecnológicas.** Miden la popularidad o las menciones de una tecnología. Cada fila resume el `$description` del comando correspondiente (app/Console/Commands/TrendsTechnologies/*.php).

| Fuente | Comando | Qué mide |
|---|---|---|
| GitHub | `github:trends` | Repositorios, lenguajes y temas populares; los guarda en `global_trends` |
| PyPI (pypistats) | `pypi:trends` | Descargas de paquetes Python |
| StackOverflow | `stackoverflow:trends`, `stackoverflow:languages` | Etiquetas más usadas (tecnologías y lenguajes) |
| npm | `npm:trends` | Descargas de paquetes JavaScript |
| Docker Hub | `dockerhub:trends` | Descargas (pulls) de imágenes |
| Hugging Face | `huggingface:trends` | Modelos de IA más descargados |
| arXiv | `arxiv:trends` | Artículos científicos recientes por categoría |
| Hacker News | `hackernews:trends` | Noticias destacadas |
| Reddit (PullPush) | `reddit:trends` | Comentarios y puntaje |
| GDELT | `gdelt:trends` | Menciones en noticias |
| Product Hunt | `producthunt:trends` | Productos tech más votados |
| Libraries.io | `libraries:trends` | Popularidad de librerías |
| ConferenceIndex | `conferenceindex:tech-events` | Eventos de IA y tecnología |

**C) Tendencias con IA:**
- Tendencias por tecnología, lenguaje, metodología o certificación, que alimentan `entity_trends` y la columna "Tendencias" de Alineación Cursos:
  - `trends:discover-technologies`, `trends:discover-languages`, `trends:discover-methodologies` y `trends:discover-from-market` usan OpenAI (app/Console/Commands/Trends/*.php).
  - `trends:discover-*-tavily` usan Tavily (app/Console/Commands/Trends/Tavily/*.php).
- Macrotendencias: `trends:discover-global` y `trends:discover-exa` (ver D.1).

**D) World Bank.** El comando `worldbank:import` importa indicadores laborales y tecnológicos por país desde api.worldbank.org/v2 (app/Console/Commands/WorldBankImportCommand.php).

##### Cómo se ejecutan las fuentes
1. **Manual por consola**, por ejemplo `php artisan remotive:technologies`. También existe el comando `jobs:run-all`, que corre en secuencia (con una pausa configurable, `--sleep`) los comandos de Arbeitnow, GetOnBoard, Reed, USAJOBS, Selecta, las certificaciones y luego las normalizaciones geográficas (app/Console/Commands/RunAllJobs.php).
2. **Botón en pantalla:** en "Comandos Fuentes" **no hay** botón de ejecución, porque está comentado (index.tsx). Otras pantallas fuera de esta parte sí lanzan comandos. Por ejemplo, "Demanda Ciudad" encola normalizaciones con `POST /dashboard/indicators/job-demand/rebuild-alignment` (JobDemandGeoIndicatorController.php `rebuildAlignment`).
3. **Programador de tareas:** app/Console/Kernel.php define:
   - `joboffers:import` cada 30 min;
   - `getonboard:import` cada 6 h;
   - `worldbank:import` los lunes a las 03:00 (indicadores de desempleo, empleo en industria y servicios, uso de internet, exportaciones TIC y gasto en I+D, 2020-2025);
   - `scrape:computrabajo --pages=5` todos los días a las 03:00.

   Sin embargo, según docs/pendientes-observatorio.md, este programador **probablemente no se ejecuta**: `bootstrap/app.php` no usa `withSchedule` y `routes/console.php` no programa nada. Además, el comando `scrape:computrabajo` no existe. **[PENDIENTE]**
4. **Cola de trabajos:** la conexión es `database` (.env.example `QUEUE_CONNECTION=database`). Las tareas encoladas, como el análisis IA de cursos, necesitan un proceso permanente `php artisan queue:work` en el servidor (docs/produccion/PASOS_PRODUCCION.md).

---


### 2.7 Indicadores › Mercado y Demanda: Tecnologías, Lenguajes y Certificaciones

#### B.0 Conceptos comunes de las tres pantallas

##### B.0.1 Acceso
- En la barra lateral, abra **Indicadores** y busque el subtítulo **"Mercado y Demanda"**. Ahí están las opciones **"Tecnologías"**, **"Lenguajes"** y **"Certificaciones"** (`resources/js/components/app-sidebar.tsx`).
- Solo se pide haber iniciado sesión. Las rutas están dentro del grupo `auth` y no requieren un permiso específico (`routes/web.php`, `Route::middleware(['auth'])`, líneas 119 y 475‑625).

##### B.0.2 De dónde salen los números (explicado en lenguaje sencillo)
| Dato | Origen | Fuente |
|---|---|---|
| **Vacantes / ofertas laborales** | Ofertas de empleo que el sistema recolecta automáticamente de bolsas de trabajo (*scraping*) y guarda en la tabla de ofertas. Cada oferta queda vinculada a las tecnologías, lenguajes o certificaciones que menciona. | `job_offers`, `technology_job`, `language_job`, `certification_job` (`RankingTecnologiasController.php`, `RankingLenguajesController.php`, `RankingCertificacionesController.php`) |
| **Reportes de tendencia** | Evidencias externas que asocian una tecnología, lenguaje o certificación con una tendencia global. Las encuentra el buscador **Tavily** o las propone la IA (**GPT**). | tabla `entity_trends`, campo `discovered_by` (mismos controladores) |
| **Catálogo de elementos** | Tecnologías, lenguajes y certificaciones registrados en el catálogo de entidades de mercado. | `market_entities` filtrado por `entity_type` = `technology` / `language` / `certification` (mismos controladores) |
| **Ponderaciones** | Peso de la demanda laboral frente al de las tendencias. Se guarda un registro activo para cada contexto (`technologies`, `languages`, `certifications`). | tabla `ranking_weights` (`app/Models/Prueba.php`) |
| **Evolución (semanal/quincenal/mensual)** | "Fotos" históricas del ranking que guardan unos comandos programados en tablas de caché. | `technology_evolution_cache`, `language_evolution_cache`, `certification_evolution_cache` (`app/Console/Commands/Evolution/Take*SnapshotCommand.php`) |

Proceso exacto que vincula cada oferta con sus tecnologías, lenguajes o certificaciones: **[POR CONFIRMAR]**. El repositorio no muestra con claridad qué proceso llena `technology_job`, `language_job` y `certification_job`.

##### B.0.3 Período de análisis (Año + Semestre)
- **Año**: lista desplegable con las opciones **2025** y **2026**. Si no se elige otro, se usa **2026** (`components/Header/RankingHeader.tsx` de las tres páginas, `[2025, 2026]`; controladores, `$request->get('year', 2026)`).
- **Semestre**: dos botones, **"Ene – Jun"** (`s1`, opción por defecto) y **"Jul – Dic"** (`s2`) (`RankingHeader.tsx`; controladores, `get('period','s1')`).
- Rango de fechas: S1 va del `AAAA-01-01` al `AAAA-06-30` y S2 del `AAAA-07-01` al `AAAA-12-31` (`getPeriodRange()` en los tres controladores).
- Al cambiar el año o el semestre se recarga el ranking, se vuelve a la página 1 y se mantienen los demás filtros (`RankingHeader.tsx`, `onChange`).
- Texto de la tarjeta de metodología: **"Los cálculos se realizan únicamente con datos del Periodo seleccionado."** (`RankingHeader.tsx`)

##### B.0.4 Fórmulas de cálculo (iguales en las tres pantallas, con las excepciones indicadas)
Para cada elemento (tecnología, lenguaje o certificación) se calcula:

1. **Ofertas (vacantes)**: número de ofertas **distintas** vinculadas al elemento cuya **fecha de publicación** (`published_at`) cae dentro del semestre elegido (`laborSub` en los tres controladores).
2. **Reportes de tendencia**: número de registros de `entity_trends` del elemento dentro del período.
   - Tecnologías y Lenguajes: se cuentan los registros del **año** elegido y de los **trimestres** 1‑2 (S1) o 3‑4 (S2) (`RankingTecnologiasController::index`, `trendSub`; `RankingLenguajesController::index`, `reportsSub`).
   - Certificaciones: se cuentan los registros cuya **fecha de creación** (`created_at`) cae dentro del rango del semestre (`RankingCertificacionesController::getDirectCertificationTrendsSubquery`).
3. **Score laboral** (0‑100, escala logarítmica): `ROUND( LOG(ofertas+1) / LOG(máx_ofertas+1) × 100 , 1)`. *máx_ofertas* es la mayor cantidad de ofertas que tiene cualquier elemento en el período (como mínimo 1). El elemento con más ofertas obtiene 100 (`labor_score` en los tres controladores).
4. **Score de tendencias** (0‑100, escala logarítmica): `ROUND( LOG(reportes+1) / LOG(máx_reportes+1) × 100 , 1)` (`trend_score` en los tres controladores).
5. **Resultado final** (`final_score`):
   - Tecnologías y Lenguajes: `Score laboral × peso_laboral + Score tendencias × peso_tendencias` (`final_score` en `RankingTecnologiasController` y `RankingLenguajesController`).
   - **Certificaciones (diferencia)**: la parte de tendencias **no** usa el score logarítmico que se ve en la tarjeta. Usa la **proporción lineal** `reportes_del_elemento / total_de_reportes_de_certificaciones_del_período × 100`. Por eso el resultado final de una certificación puede no coincidir con lo que da aplicar la fórmula a los dos scores visibles (`RankingCertificacionesController::getCertificationsRanking`, `final_score`).
6. Los pesos por defecto son **70 % laboral y 30 % tendencias** cuando no hay ponderación guardada (`?? 0.7` / `?? 0.3` en los tres controladores).
7. El ranking se ordena de **mayor a menor resultado final** (`orderByDesc('final_score')` / `sortByDesc`).

**Etiqueta "Proyección"** de cada tarjeta: **Alta** si el resultado final es ≥ 70, **Media** si es ≥ 40 y **Baja** en los demás casos (`TechnologyCard.tsx`, `LanguageCard.tsx`, `CertificationCard.tsx`, `scoreLabel`).

##### B.0.5 Insignias informativas del encabezado
| Insignia | Cálculo | Fuente |
|---|---|---|
| "**N** vacantes analizadas" (en Certificaciones: "**N** vacantes") | Ofertas distintas publicadas en el semestre que tienen **al menos un** elemento del tipo vinculado. | `meta.vacantes_analizadas` en los 3 controladores |
| "**N** reportes analizados" (en Certificaciones: "**N** reportes") | Tecnologías: todos los registros de `entity_trends` del año y trimestres del semestre, **de cualquier tipo de entidad**. Lenguajes: solo los de lenguajes, y se muestra **mínimo 1** aunque no haya ninguno. Certificaciones: los de certificaciones creados en el rango de fechas. | `meta.reportes_analizados` (`RankingTecnologiasController` l.265‑273; `RankingLenguajesController` l.199‑214; `RankingCertificacionesController::getTrendReportsCountByRange`) |

##### B.0.6 Modal "Datos Generales" (idéntico en las tres pantallas)
Se abre con el botón **"Datos Generales"**, que en Tecnologías y Lenguajes va acompañado del texto **"ver detalle"** (`RankingHeader.tsx`; el componente es el mismo en los tres casos: `JobMarketStatusModal.tsx`).

- Título: **"Datos Generales"**. Debajo aparece el estado de la recolección automática (*scraping*) de la entidad (`technologies` / `languages` / `certifications`), que se lee de la tabla `scraper_runs` (`app/Services/ScrapingStatusService.php`):
  | Texto | Condición |
  |---|---|
  | "Scraping en ejecución" | la última ejecución tiene estado `running` |
  | "Última ejecución fallida" | la última ejecución tiene estado `failed` |
  | "Datos desactualizados" | la última ejecución terminada fue hace **más de 24 h** |
  | "Sistema actualizado" | en cualquier otro caso |
  | "Sin información" | no hay datos de estado |
  Junto al estado se muestra "· Última ejecución *hace X*" (`JobMarketStatusModal.tsx`; `ScrapingStatusService::getByEntity`).
- **"Última ejecución del scraping"**: fecha y hora de fin, más la insignia **"Fuente: …"**. Tooltip: *"Fecha y hora exacta en la que el sistema ejecutó por última vez la recolección automática de ofertas laborales indicando la bolsa de trabajo."*
- **"Métricas globales"**:
  - **"Total histórico de ofertas"**: total de filas de `job_offers`. Pie: "Histórico de *X*", calculado como el tiempo transcurrido desde la primera oferta registrada. Tooltip: *"Cantidad total de ofertas laborales recolectadas históricamente por el sistema sin importar el Periodo e indicador seleccionado."* (`JobMarketStatusController::get`)
  - **"Nuevas ofertas del mes vigente"**: ofertas **registradas** (`created_at`) desde el primer día del mes actual, con el formato "+N". Tooltip: *"Número de ofertas nuevas incorporadas desde el inicio del mes actual sin importar el Periodo e indicador seleccionado."*
- **"Periodo analizado"**:
  - **"Ofertas analizadas"**: **todas** las ofertas **registradas en el sistema** (`created_at`, no la fecha de publicación) dentro del semestre. Pie: "desde → hasta". Tooltip: *"Cantidad de ofertas laborales consideradas dentro del Periodo seleccionado sin importar el Periodo e indicador seleccionado."* Atención: esta cifra **no coincide** con la insignia "vacantes analizadas", porque esa insignia usa la fecha de publicación y solo cuenta ofertas vinculadas (`JobMarketStatusController::get`).
  - **"Promedio diario"**: "N ofertas/día", que resulta de dividir las ofertas analizadas entre **todos los días del semestre** (181‑184), incluidos los días que aún no han transcurrido. Pie: "N días cubiertos". Tooltip: *"Promedio de ofertas publicadas por día dentro del Periodo analizado sin importar el Periodo e indicador seleccionado."*
- Pie del modal: *"Datos del mercado laboral y estado operativo del sistema."*

##### B.0.7 Tarjeta "Metodología de cálculo" y modal de ponderaciones
- En la columna derecha del encabezado hay una tarjeta con el título **"Metodología de cálculo"**. Muestra "**X%** Demanda laboral", "**Y%** Tendencias tecnológicas", la fórmula "Score = (0.x × Laboral) + (0.y × Tendencias)" y el enlace **"Haz clic para editar ponderaciones"** (`RankingHeader.tsx`).
- Al hacer clic se abre el modal de ponderaciones, que tiene dos **deslizadores** (0‑100, de 1 en 1) **complementarios**: al mover uno, el otro se ajusta para que ambos sumen 100. El modal también incluye una **vista previa** de ejemplo y los botones **"Restablecer"** (vuelve a 70/30) y **"Aplicar metodología"** (en Certificaciones, "**Aplicar ponderaciones**") (`WeightConfigModal.tsx` de cada página).
- Al aplicar se muestra *"Aplicando metodología"* con un indicador de carga. Si todo sale bien aparece **"Metodología actualizada"** y el ranking se recalcula (`Ranking*Page.tsx`, `handleSaveWeights`).
- Validaciones:
  - En el navegador, si la suma no es 100 aparece el error: **"Las ponderaciones deben sumar 100%"** (`Ranking*Page.tsx`).
  - En el servidor, cada peso debe ser un número entre 0 y 1 y la suma debe ser 1,00. Si no, responde **"Las ponderaciones deben sumar 1.00"** (`storeWeights` en los tres controladores).
- Regla de negocio: al guardar, la ponderación activa anterior se desactiva y se crea una nueva con la fecha de aplicación y el usuario que la cambió. Así queda un **historial** de ponderaciones por contexto (`storeWeights`; tabla `ranking_weights`). El cambio afecta a **todos los usuarios**.

##### B.0.8 Botón "Detectar nuevos … con IA"
- Ejecuta un comando de IA (OpenAI, modelo `gpt-4.1-mini`). El comando recibe la lista de carreras y propone hasta **10** elementos nuevos que todavía no estén en el catálogo; el botón envía `limit: 10, sleep: 2` (`RankingHeader.tsx`; `app/Console/Commands/getTrends/Discover*GapsCommand.php`).
- Cada elemento nuevo se crea en el catálogo con origen `ai_generated`, estado `candidate` y marca de tendencia (`has_trend = 1`). Además se asocia a las carreras que sugiere la IA (en `market_entity_career`, con una relevancia de 0,80 para tecnologías y 0,85 para certificaciones). El comando descarta duplicados por nombre normalizado o *slug* (`Discover*GapsCommand.php`).
- Mientras se ejecuta, el botón queda deshabilitado con el texto "Detectando …". Al terminar aparece **"IA ejecutada"** y la página se recarga. Si ocurre un error aparece **"Error"** con el mensaje del servidor o *"No se pudo ejecutar el descubrimiento."* (`RankingHeader.tsx`).
- Nota: si la IA devuelve una respuesta no válida, el comando termina sin error técnico y la pantalla igual muestra "IA ejecutada" (`Discover*GapsCommand.php` devuelve `FAILURE` sin lanzar una excepción; el controlador responde `success: true`). Los elementos nuevos aparecen enseguida en el ranking, porque este no filtra por estado `candidate` (`index` / `getCertificationsRanking`, sin filtro de `status`).

##### B.0.9 Modal de evolución ("Ver evolución ›")
- Botón **"Ver evolución ›"** del encabezado (`RankingHeader.tsx`).
- Título: **"Evolución semanal"**, **"Evolución quincenal"** o **"Evolución mensual"**, según el selector **"Vista"**, que ofrece **Semanal** (opción por defecto), **Quincenal** y **Mensual** (`WeeklyEvolution*Modal.tsx`).
- Cada período se muestra como una tarjeta con su etiqueta (por ejemplo "Semana N - AAAA", "1ra Quincena *mes* - AAAA" o "*mes* - AAAA"), el rango de fechas, **"Ranking Top 5"** con "N resultados" y 5 fichas "**Rank #n**" (la primera resaltada con un trofeo). Cada ficha muestra el puntaje final y las filas **Vacantes**, **Laboral** (en Certificaciones, "**Demanda**"), **Tendencia** y **Final**. Se muestran **5 períodos por página**, con los botones "← Anterior", "Página X de Y" y "Siguiente →" (`WeeklyEvolution*Modal.tsx`; `weeklyScores` en los controladores).
- Mensajes: "Cargando..." y "No hay datos para este período".
- Datos: vienen de las tablas de caché de evolución del **año** que esté seleccionado en la página. Las genera el comando `*:take-snapshot`, que los domingos guarda la foto semanal, el día 15 y el último día del mes guarda la quincenal y el último día del mes guarda también la mensual. Cada foto contiene el **acumulado del semestre hasta ese día** con la misma fórmula que el ranking (`TakeTechnologySnapshotCommand.php`, `TakeLanguageSnapshotCommand.php`, `TakeCertificationSnapshotCommand.php`).
- Programación automática de esas fotos: **[POR CONFIRMAR]**. El repositorio no programa ninguna tarea (`routes/console.php` está vacío; `docs/pendientes-observatorio.md` línea 19).
- **"Exportar Excel"**: descarga un .xlsx con **todas** las filas del año y del tipo de vista elegido (ver las columnas en cada pantalla).

##### B.0.10 Puntos comunes a revisar (pendientes)
- Las tarjetas KPI (cuadrícula superior) **no se muestran**, porque el servidor no envía el dato `kpis` (`Ranking*Page.tsx` lee `kpis`, que no está en `Inertia::render` de ningún controlador). Títulos previstos en el código: Lenguajes, "Lenguaje más demandado", "Vacantes analizadas" y "Lenguajes analizados" (`DashboardRankingLanguages/components/KPIs/KpiGrid.tsx`); Certificaciones, "Certificación Top", "Alta Demanda", "Alta Proyección" y "Área Destacada" (`DashboardRankingCertificaciones/components/KPIs/KpiGrid.tsx`). **[Pendiente]**
- El listado de ofertas que se abre desde una tarjeta (modal laboral) muestra **todas las ofertas históricas** del elemento, sin filtrar por el semestre. Por eso su total puede ser mayor que el número de vacantes de la tarjeta (`jobsByTechnology`, `jobsByLanguage`, `jobsByCertification`, sin filtro de fecha).

---

#### B.1 Tecnologías

**Título visible:** "Ranking de Tecnologías". Sobre el título aparece "Observatorio Tecnológico ISIL" (`DashboardRankingTechnologies/components/Header/RankingHeader.tsx`).
**Pestaña del navegador:** "Ranking de Tecnologías | Observatorio ISIL". **Migas de pan:** Dashboard › Ranking de Tecnologías (`RankingTecnologiasPage.tsx`).
**URL:** `/dashboard/ranking/technologies` (`routes/web.php` l.536‑539).
**Acceso:** Indicadores › Mercado y Demanda › **Tecnologías** (`app-sidebar.tsx`).
**Descripción en pantalla:** *"Análisis automatizado de tecnologías más demandadas en el mercado laboral, integrando ofertas reales y reportes de tendencias tecnológicas globales."* (`RankingHeader.tsx`)

##### B.1.1 Encabezado
Contiene el **Año**, el **Semestre**, el botón **"Ver evolución"**, las insignias de vacantes y reportes, el botón **"Datos Generales"**, la tarjeta **"Metodología de cálculo"** y el botón **"Detectar nuevas tecnologías con IA"**, que durante la ejecución muestra "Detectando tecnologías...". Al terminar aparece *"Nuevas tecnologías estratégicas detectadas."* (`RankingHeader.tsx`). Ver B.0.

##### B.1.2 Sección "Ranking General de Tecnologías"
Texto: *"Clasificación unificada que integra tecnologías ISIL y tecnologías en tendencia global, ordenadas según demanda laboral y presencia en reportes especializados."* (`RankingTecnologiasPage.tsx`)

**Filtros** (`components/Filters/RankingFilters.tsx`):
| Filtro | Tipo | Valores | Por defecto | Comportamiento |
|---|---|---|---|---|
| Buscador | texto, placeholder **"Buscar tecnología..."** | texto libre | vacío | Filtra por nombre que **contenga** el texto y se aplica mientras se escribe (`RankingTecnologiasController::index`, `LIKE %texto%`) |
| **"Carrera ISIL"** | lista con casillas, selección múltiple | carreras **activas**, ordenadas por nombre | ninguna | Muestra solo las tecnologías asociadas a alguna de las carreras marcadas. El botón indica "N seleccionadas" (`index`, filtro `whereExists` sobre `career_market_entity`) |
| Chips | etiquetas con el nombre de cada carrera y una "x" para quitarla | — | — | El enlace **"Eliminar filtros"** borra todos los filtros |

Observación: este filtro consulta la tabla `career_market_entity`, mientras que la IA guarda las asociaciones en `market_entity_career`. Si las tecnologías creadas por la IA aparecen o no al filtrar por carrera: **[POR CONFIRMAR]** (`RankingTecnologiasController.php` l.190; `DiscoverTechnologyGapsCommand.php` l.114).

**Tarjetas del ranking** (2 columnas, **10 por página**; paginador con flechas ←/→, números y "…") (`index`, `paginate(10)`; `TechnologyCard.tsx`; `Pagination/Paginator.tsx`):
| Elemento | Significado |
|---|---|
| **#N** | Posición global. Los puestos 1, 2 y 3 llevan los colores oro, plata y bronce |
| Nombre | Nombre de la tecnología (en mayúsculas). Debajo, el subtítulo fijo "Tecnología" |
| **"Resultado ponderado"** + valor + "Proyección Alta/Media/Baja" + barra | Resultado final (B.0.4) |
| Bloque **"Laboral"** | "N vacante(s)" del semestre, barra verde y "Score laboral: X". **Clic**: abre las ofertas. Queda deshabilitado (atenuado) si tiene 0 vacantes |
| Bloque **"Tendencias"** | Barra morada, "Score: X" y "N reporte(s)". **Clic**: abre la evidencia de tendencias. Queda deshabilitado si tiene 0 reportes |
| Número grande + **"Resultado final"** | Resultado final |
| Insignia **"ISIL"** / **"Trend"** | "ISIL" si la tecnología está marcada como parte de la oferta ISIL (`has_isil`). En cualquier otro caso muestra "Trend" (`TechnologyCard.tsx`; `classification` en `index`) |

##### B.1.3 Modal "Ofertas laborales" (clic en "Laboral")
- Título **"Ofertas laborales"** y, debajo, el nombre de la tecnología (`TechnologyJobsModal.tsx`).
- Lista de 10 ofertas por página, de la más reciente a la más antigua. Cada oferta muestra "n. Título", "Empresa · País · Modalidad", "Fuente: … · fecha de publicación" y el enlace **"Ver oferta"**, que abre la oferta original en otra pestaña (`TechnologyJobsModal.tsx`; `jobsByTechnology`).
- Mensajes: "Cargando ofertas laborales…" y "No se encontraron ofertas laborales.". Paginación: "← Anterior", "Página X de Y" y "Siguiente →".

##### B.1.4 Modal "Evidencia de tendencias tecnológicas" (clic en "Tendencias")
- Encabezado: "Insights · Ranking", título **"Evidencia de tendencias tecnológicas"**, subtítulo *"Reportes externos y descubrimientos generados por IA utilizados para construir el ranking tecnológico."* y una etiqueta con el nombre de la tecnología (`components/Ranking/TrendDetailModal.tsx`).
- Leyenda con contadores (`reportsByTechnology`, `stats`):
  - **"Tavily Search"**: número de reportes cuyo campo de origen contiene "tavily". Texto: *"Tendencias identificadas mediante fuentes externas predefinidas y verificadas."*
  - **"GPT Discovery"**: número de reportes cuyo origen contiene "gpt". Texto: *"Tendencias propuestas automáticamente por IA como señales emergentes del mercado."*
- Lista de reportes del año y semestre elegidos, **ordenados por puntaje de tendencia** de mayor a menor, 10 por página. Cada reporte muestra el número, el título del reporte (o "Reporte de tendencia"), que es un enlace a la fuente original. También muestra la descripción según el origen (*"Fuente externa verificada que respalda esta tendencia."*, *"Tendencia propuesta automáticamente por IA como posible comportamiento emergente."* u *"Origen no identificado."*) y estas etiquetas: 📄 tipo de fuente, 📅 fecha de registro, 🧠 "Fuente: …" y "🔎 Origen: Tavily", "🤖 Origen: GPT" o "⚪ Origen desconocido". A la derecha aparece un recuadro **"Score"** con el puntaje del reporte (`TrendDetailModal.tsx`; `reportsByTechnology`).
- Mensaje si no hay reportes: *"No se encontraron reportes asociados a esta tecnología."*
- Pie: "N fuentes · Haz clic en el título para abrir la fuente original", "Página X de Y", los botones "← Anterior", números y "Siguiente →", y el botón **"Cerrar"**.

##### B.1.5 Evolución y exportación
- Modal de evolución (B.0.9). Subtítulo: *"Top tecnologías por demanda laboral en el sector IT"* (`WeeklyEvolutionModal.tsx`).
- **Excel** `technology_evolution_{vista}.xlsx` (`RankingTecnologiasController::exportTechnologyEvolution`; `app/Exports/TechnologyEvolutionExport.php`), ordenado por fecha de corte y posición. Columnas:
  | Columna | Contenido |
  |---|---|
  | Periodo | etiqueta del corte |
  | Fecha Inicio / Fecha Fin | fecha del corte |
  | Total Vacantes Reales | suma de vacantes de **todas** las tecnologías en ese corte. Una misma oferta con varias tecnologías se cuenta varias veces |
  | Tecnología | nombre |
  | Vacantes Tecnología | ofertas de la tecnología acumuladas en el semestre hasta el corte |
  | Reportes Tendencia | reportes del semestre |
  | Puntaje Laboral / Puntaje Tendencia / Puntaje Final | scores |
  | Posición Ranking | puesto en ese corte |

##### B.1.6 Rutas complementarias y pendientes (Tecnologías)
- `/dashboard/ranking/technologies/trend/{trendId}` y `/trend/{trendId}/jobs` devuelven el detalle de una tendencia y sus ofertas, a partir de `technology_trends` / `technology_trend_job` (`RankingTecnologiasController::technologyTrendDetail`, `jobsByTechnologyTrend`). El modal que las consume (`TrendJobsModal.tsx`, con el título "Ofertas laborales – …" o "Ofertas laborales asociadas a la tendencia") existe en la página, pero **ninguna acción lo abre** **[Pendiente]** (`RankingTecnologiasPage.tsx`: nunca se usa `openJobsModal("trend", …)`).
- Al abrir el modal de ponderaciones, el peso de tendencias puede aparecer en **30 %** aunque la ponderación guardada sea otra. El servidor envía `trendWeight` y el modal lee `trendsWeight`. En ese caso, si se pulsa "Aplicar metodología" sin mover los deslizadores, aparece el error "Las ponderaciones deben sumar 100%" (`RankingTecnologiasController::index`, `weights`; `WeightConfigModal.tsx`). **[POR CONFIRMAR]**
- El filtro "Tipo de ranking" (`ranking_type`: `all` / `technology` / `trend`) llega al servidor, pero **no tiene control en pantalla ni efecto en el resultado** **[Pendiente]** (`index`).
- El componente `MethodologyCard.tsx` ("Metodología de Cálculo") existe, pero no se usa en la página **[Pendiente]**.

##### B.1.7 Prefijo `/trends` (relacionado)
- `GET /trends/{trendId}` devuelve el detalle de una tendencia de `technology_trends`: título, puntaje, período "AAAA-Qn", regiones, resumen, factores clave, fuentes, tecnologías asociadas con su confianza y total de ofertas (`app/Http/Controllers/Trends/TrendTechnologyController.php::show`).
- `GET /trends/{trendId}/jobs` devuelve las ofertas de esa tendencia, paginadas, con el tipo de coincidencia y la confianza. Si la tendencia no existe responde *"Invalid trend id"* (`TrendTechnologyController::jobs`).
- Ninguna de las tres pantallas de esta parte lo usa en la navegación habitual. Solo lo invocan los componentes `TrendJobsTab.tsx` / `TrendOverviewTab.tsx` de Certificaciones, que no están conectados a la página (ver B.3.6) **[Pendiente]**.

---

#### B.2 Lenguajes

**Título visible:** "Ranking de Lenguajes" (`DashboardRankingLanguages/components/Header/RankingHeader.tsx`).
**Pestaña:** "Ranking de Lenguajes | Observatorio ISIL". **Migas:** Dashboard › Ranking de Lenguajes (`RankingLenguajesPage.tsx`).
**URL:** `/dashboard/ranking/languages` (`routes/web.php` l.586‑589).
**Acceso:** Indicadores › Mercado y Demanda › **Lenguajes**.
**Descripción:** *"Ranking que integra demanda laboral real y evidencia de tendencias tecnológicas relacionadas con lenguajes de programación."* (`RankingHeader.tsx`)

##### B.2.1 Encabezado
Es igual al de Tecnologías (B.0 y B.1.1). El botón de IA dice **"Detectar nuevos lenguajes con IA"** y, mientras se ejecuta, "Detectando brechas...". Al terminar aparece *"Nuevos lenguajes estratégicos detectados."* (`RankingHeader.tsx`). La IA solo propone **lenguajes de programación reales**: excluye frameworks, bases de datos, herramientas DevOps y certificaciones (`DiscoverLanguageGapsCommand.php`, prompt).

Modal de ponderaciones: título **"Metodología de Cálculo"** y descripción *"El ranking de lenguajes combina demanda laboral y evidencia en reportes de tendencias tecnológicas globales."* Bajo cada deslizador: *"Basado en ofertas laborales reales del Periodo seleccionado."* y *"Evidencia externa proveniente de reportes especializados."* Incluye el ejemplo **"🧮 Ejemplo de cálculo (Lenguaje: Python)"** y los botones "Restablecer" y "Aplicar metodología" (`components/Header/WeightConfigModal.tsx`).

##### B.2.2 Filtros (`components/Filters/RankingFilters.tsx`)
| Filtro | Tipo | Valores | Por defecto | Comportamiento |
|---|---|---|---|---|
| Buscador | texto, placeholder **"Buscar lenguaje..."** | libre | vacío | El nombre debe contener el texto (`RankingLenguajesController::index`) |
| **"Carrera ISIL"** | casillas, selección múltiple ("N seleccionados") | carreras activas | ninguna | Muestra los lenguajes asociados a las carreras marcadas (`market_entity_career`) (`index`) |
| Chips | "Carrera: *nombre*" con "x" | — | — | Quita esa carrera del filtro |

##### B.2.3 Tarjetas (2 columnas, **10 por página**) (`LanguageCard.tsx`; `index`, `paginate(10)`)
- Muestra la posición **#N**, el nombre, "Resultado ponderado" con la proyección y su barra, y los bloques **"Laboral"** ("N vacantes", "Score laboral: X") y **"Tendencias"** ("N reportes", "Score tendencias: X"). Cada bloque se puede pulsar solo si su valor es mayor que 0. A la derecha aparece "Resultado final".
- Insignia **"ISIL"**: aparece cuando el lenguaje tiene **al menos 1 vacante en el semestre**. Si no tiene vacantes muestra **"Trend"**. Esta regla es distinta a la de Tecnologías y Certificaciones, que usan la marca ISIL del catálogo (`LanguageCard.tsx`, `isISIL = data.is_isil === 1 || (entity_type === "language" && total_jobs > 0)`; el servidor envía `has_isil` y no `is_isil`).

##### B.2.4 Modal "Ofertas laborales"
- Título "Ofertas laborales" con el nombre del lenguaje debajo. Lista de 10 ofertas por página con "n. Título", "Empresa · País · Modalidad", "Fuente: … · fecha" y "Ver oferta". Mensajes: "Cargando ofertas laborales…" y "No se encontraron ofertas laborales para este lenguaje." (`LanguageJobsModal.tsx`; `RankingLenguajesController::jobsByLanguage`).

##### B.2.5 Modal "Evidencia de tendencias"
- Encabezado: "Insights · Ranking", **"Evidencia de tendencias"** y *"Reportes externos y descubrimientos generados por IA utilizados para construir el ranking."* (`LanguageTrendModal.tsx`).
- Leyenda: "Tavily Search" (*"Tendencias identificadas mediante fuentes externas verificadas."*) y "GPT Discovery" (*"Tendencias propuestas automáticamente por IA."*), con sus contadores.
- Cada reporte muestra el título con enlace, la descripción (*"Fuente externa verificada."*, *"Tendencia propuesta por IA."* u *"Origen no identificado."*) y las etiquetas 📄 tipo, 📅 fecha, 🧠 "Fuente:" y "🔎 Tavily", "🤖 GPT" o "⚪ Desconocido". También muestra "Score". Si no hay reportes: *"No se encontraron tendencias."* Pie: "N fuentes", "Página X de Y", la paginación y el botón "Cerrar".
- **Observación importante:** este modal pide los reportes **sin enviar el año ni el semestre**, así que siempre muestra los de **2026 – S1**, aunque la página tenga otro período seleccionado (`RankingLenguajesPage.tsx`, `openTrendModal` solo envía `page`; `trendsByLanguage` toma por defecto `year=2026` y `period=s1`). **[POR CONFIRMAR / pendiente]**

##### B.2.6 Evolución y exportación
- Modal de evolución (B.0.9). Subtítulo: *"Top lenguajes por demanda laboral en el sector IT"* (`WeeklyEvolutionLanguagesModal.tsx`).
- **Excel** `languages_evolution_{vista}.xlsx` (`RankingLenguajesController::exportEvolution`; `app/Exports/LanguagesEvolutionExport.php`). Columnas: **Periodo**, **Fecha Inicio**, **Fecha Fin**, **Vacantes Únicas Reales** (suma de las menciones de todos los lenguajes del corte), **Lenguaje**, **Menciones Lenguaje** (vacantes del lenguaje), **Reportes Tendencia**, **Puntaje Laboral**, **Puntaje Tendencia**, **Puntaje Final** y **Posición Ranking**.

##### B.2.7 Pendientes (Lenguajes)
- Las rutas `/{language}/reports`, `/trend/{trendId}` y `/trend/{trend}/jobs` están declaradas, pero sus métodos (`reportsByLanguage`, `languageTrendDetail`, `jobsByTrendLanguage`) **no existen** en el controlador. `reportsByLanguage` está comentado (`routes/web.php` l.607‑624; `RankingLenguajesController.php` l.90‑119). **[Pendiente]**
- Los componentes `LanguageTrendJobsModal.tsx`, `TrendDetailModal.tsx` y `MethologyCard.tsx` no se usan en la página **[Pendiente]**.

---

#### B.3 Certificaciones

**Título visible:** "Ranking de Certificaciones" (`DashboardRankingCertificaciones/components/Header/RankingHeader.tsx`).
**Pestaña:** "Ranking de Certificaciones | Observatorio ISIL". **Migas:** Dashboard › Ranking de Certificaciones (`RankingCertificacionesPage.tsx`).
**URL:** `/dashboard/ranking-certificaciones` (`routes/web.php` l.508‑511).
**Acceso:** Indicadores › Mercado y Demanda › **Certificaciones**.
**Descripción:** *"Análisis automatizado de certificaciones tecnológicas más demandadas, basado en ofertas laborales reales y datos verificados."* (`RankingHeader.tsx`)

##### B.3.1 Encabezado
- Contiene el Año, el Semestre, "Ver evolución", las insignias "**N** vacantes" y "**N** reportes" y el botón "Datos Generales", que aquí no lleva el texto "ver detalle" (`RankingHeader.tsx`).
- La tarjeta "Metodología de cálculo" muestra la fórmula con los pesos en forma decimal, por ejemplo "Score = (0.7 × Laboral) + (0.3 × Tendencias)". Ver la diferencia real de cálculo en B.0.4‑5.
- Botón **"Detectar nuevas certificaciones con IA"**. Durante la ejecución muestra "Detectando certificaciones..." y al terminar *"Nuevas certificaciones estratégicas detectadas."* La IA recibe las certificaciones existentes y las carreras, y propone certificaciones que faltan con su nombre oficial, proveedor y nivel (Foundational, Associate, Professional, Expert o Specialty). Cada una se registra también en el catálogo de certificaciones (`DiscoverCertificationGapsCommand.php`).
- Modal de ponderaciones: título **"Configuración de Ponderaciones"** y texto *"Ajuste los pesos de cada criterio. Ambos deben sumar 100%."* Deslizadores "📊 Demanda Laboral" y "📈 Tendencias Tecnológicas", la vista previa "🧮 Preview del Cálculo (AWS Solutions Architect Associate)" y los botones "Restablecer" y **"Aplicar ponderaciones"** (`components/Header/WeightConfigModal.tsx`). Al aplicar aparece *"Aplicando metodología" – "Actualizando ranking…"* (`RankingCertificacionesPage.tsx`).

##### B.3.2 Sección "Ranking General de Certificaciones"
Texto: *"Clasificación unificada que integra certificaciones ISIL y certificaciones en tendencia global."* (`RankingCertificacionesPage.tsx`)

**Filtros** (`components/Filters/RankingFilters.tsx`; `RankingCertificacionesController`):
| Filtro | Tipo | Valores | Por defecto | Comportamiento |
|---|---|---|---|---|
| Buscador | texto, placeholder **"Buscar certificación..."** | libre | vacío | Busca, sin distinguir mayúsculas, en el **nombre, proveedor, nivel o categoría** (`getCertificationsRanking`, bloque BUSCADOR) |
| **"Carrera ISIL"** | casillas, selección múltiple | carreras activas | ninguna | Muestra las certificaciones asociadas a las carreras marcadas (`market_entity_career`) |
| **"Área tecnológica"** | casillas, selección múltiple | categorías distintas de las certificaciones del catálogo | ninguna | Muestra las certificaciones cuya categoría coincide (`whereIn('me.category', …)`) |
| Chips | "Área: …", "Carrera: …" y "Tipo: …" con "x" | — | — | Quitan el filtro correspondiente |

Pendiente: el código define tipos de ranking ("Ranking general", "Solo certificaciones" y "Solo tendencias") y una lista **"Todas las categorías"** para tendencias que solo se vería en el modo "Solo tendencias". Sin embargo, **no hay ningún control para elegirlos** y el servidor **no aplica** `ranking_type` ni `trend_category` al cálculo (`RankingFilters.tsx`, `RANKING_TYPES`; `getCertificationsRanking`). **[Pendiente]**

**Tarjetas** (2 columnas, **4 por página**) (`CertificationCard.tsx`; `index`, `paginate($merged, 4)`):
| Elemento | Significado |
|---|---|
| **#N** + nombre | Posición y nombre |
| "*Proveedor* · NIVEL *nivel*" | Solo en certificaciones ISIL que tienen proveedor registrado |
| Categoría | Si existe |
| "Resultado ponderado" + "Proyección …" + barra | Resultado final (B.0.4, con la fórmula propia de Certificaciones) |
| **"Laboral"** | "N vacantes" (solo si es mayor que 0), barra y "Score laboral: X". Se puede pulsar si hay vacantes |
| **"Tendencias"** | Barra, "Score tendencias: X" y "N reporte(s)". Tooltip: "Ver reportes de tendencia" o "No hay reportes de tendencia". Se puede pulsar **siempre**: aunque no haya reportes, abre el modal vacío (`CertificationCard.tsx`, el `onClick` no valida) |
| "Resultado final" | Número grande |
| Insignia **"ISIL"** / **"Trend"** | "ISIL" si `has_isil` = 1. En cualquier otro caso, "Trend" |

##### B.3.3 Modal de ofertas
- Título **"Ofertas laborales – *certificación*"** y el total "**N** ofertas laborales encontradas". Cada oferta muestra el título, "Empresa · ubicación" y "Ver oferta". Botones "Anterior", "Página X de Y", "Siguiente" y "✕". Si no hay ofertas: *"No se encontraron ofertas laborales."* (`CertificationJobsModal.tsx`; `jobsByCertification`, 10 por página, de la más reciente a la más antigua y sin filtro de período).
- La ubicación puede aparecer vacía, porque el servidor envía `city` y el modal muestra `location` **[POR CONFIRMAR]** (`jobsByCertification`; `CertificationJobsModal.tsx`).

##### B.3.4 Modal "Evidencia de tendencias"
Es igual al de Lenguajes (B.2.5): título, leyenda Tavily/GPT, reportes con "Score", "No se encontraron tendencias." y "Cerrar". En este caso **sí** se envían el año y el semestre de la página (`CertificationTrendModal.tsx`; `RankingCertificacionesPage.tsx`, `loadCertificationTrends`; `RankingCertificacionesController::trendDetail`, que filtra por año y trimestres).
Nota: el modal filtra por año y trimestre, mientras que el conteo de reportes de la tarjeta usa la fecha de creación. Las cantidades pueden diferir (`trendDetail` frente a `getDirectCertificationTrendsSubquery`).

##### B.3.5 Evolución y exportación
- Modal de evolución (B.0.9). Subtítulo: *"Top certificaciones más solicitadas en el sector IT"*. Las fichas muestran Vacantes, **Demanda**, Tendencia y Final. El bloque "Score Promedio / Basado en N vacantes activas" está **comentado (oculto)** **[Pendiente]** (`WeeklyEvolutionCertificationsModal.tsx`).
- **Excel** `certifications_weekly.xlsx`, con el mismo nombre para cualquier vista (`RankingCertificacionesController::exportWeekly`; `app/Exports/CertificationsWeeklyExport.php`). Columnas: **Periodo**, **Fecha Inicio**, **Fecha Fin**, **Total Vacantes Reales** (suma de las vacantes de todas las certificaciones del corte), **Certificación**, **Vacantes con Certificación**, **Reportes Tendencia**, **Puntaje Laboral**, **Puntaje Tendencia**, **Puntaje Final** y **Posición Ranking**.

##### B.3.6 Pendientes (Certificaciones)
- Las rutas `/trend/{trend}/jobs` y `/trending` apuntan a métodos que **no existen** (`jobsByTechnologyTrend`, `trendingCertifications`). La ruta de ofertas por certificación de la línea 74 está comentada (`routes/web.php` l.74‑76, 489‑500). **[Pendiente]**
- `TrendDetailModal.tsx`, junto con sus pestañas `TrendOverviewTab.tsx` (que llama a `/trends/{id}/detail`, una ruta inexistente) y `TrendJobsTab.tsx`, solo se abriría con elementos de tipo "trend", y el ranking nunca los genera ("entity_trends NO se mezclan como cards") (`RankingCertificacionesPage.tsx`; `RankingCertificacionesController::index`, comentario l.115‑120). Tampoco se usan `TrendingCertificationCard.tsx`, `CertificationReportsModal.tsx` ni `PeriodSelector.tsx`. **[Pendiente]**
- La insignia "emergente" (`is_emergent_with_market`) aparece referenciada en la tarjeta, pero no se muestra **[Pendiente]** (`CertificationCard.tsx`).

---

#### B.4 Vistas Blade de exportación
Las vistas `resources/views/exports/technologies_alignment_report.blade.php` y `languages_alignment_report.blade.php` **no** pertenecen a estas pantallas. Las usan los controladores de alineamiento de carreras con IA (`app/Http/Controllers/AI/Metrics/CareerTechnologyAlignmentAIController.php`, `CareerLanguageAlignmentAIController.php`). Las tres pantallas de esta parte solo exportan **Excel** con las clases de `app/Exports` indicadas arriba.

### 2.8 Indicadores › Mercado y Demanda: Nivel Profesional, Empresas, Demanda Ciudad y Modalidad Laboral

#### C.0. Aspectos comunes

| Tema | Detalle |
|---|---|
| Acceso al menú | Barra lateral › sección **"Indicadores"** › subtítulo **"Mercado y Demanda"**. Esta sección no está restringida a administradores, a diferencia de otras secciones del menú (resources/js/components/app-sidebar.tsx). |
| Orden de las opciones del submenú | Tecnologías, Lenguajes, Certificaciones, **Nivel Profesional**, **Empresas**, **Demanda Ciudad**, **Modalidad Laboral** (app-sidebar.tsx). Las tres primeras pertenecen a otra parte del manual. |
| Permisos | Todas las rutas de esta parte están dentro del grupo `auth`: se necesita una sesión iniciada, pero ninguna exige un permiso específico. Por eso los botones de recálculo ("Recalcular seniority", "Actualizar alineación laboral", "Actualizar datos laborales") están disponibles para cualquier usuario autenticado (routes/web.php). |
| Fuente de datos | Todas las pantallas usan las **ofertas laborales** (tabla `job_offers`) que el sistema recolecta de portales de empleo. Las importaciones programadas son `joboffers:import` (cada 30 min), `getonboard:import` (cada 6 h) y `scrape:computrabajo --pages=5` (diario a las 03:00) (app/Console/Kernel.php). |
| Periodo | Casi todas las pantallas usan **Año** + **Semestre**: "Ene – Jun" (s1 = 01-ene a 30-jun) o "Jul – Dic" (s2 = 01-jul a 31-dic) (controladores respectivos). |
| Relación vacante ↔ carrera | Una vacante se asocia a una carrera cuando una tecnología de la vacante está vinculada a un curso de esa carrera (tablas technology_job → course_technology → career_course). Por eso **una misma vacante puede contar para varias carreras** (SeniorityIndicatorController.php, JobDemandGeoIndicatorController.php). |
| Normalización de región (Empresas y Demanda Ciudad) | Los valores de región se agrupan en: África, Asia, Europa, Latinoamérica, Norteamérica, Oceanía, Global, Remoto. Las regiones vacías o no reconocidas pasan a "Desconocido" y **se excluyen** de los cálculos (CompanyIndicatorController.php, JobDemandGeoIndicatorController.php). |

##### C.0.1 Modal "Datos Generales" (presente en las 4 pantallas)
Se abre con el botón **"Datos Generales"** del encabezado. Hay dos variantes: la de Nivel Profesional (DashboardSeniority/components/Header/JobMarketStatusModal.tsx) y la de Empresas, Demanda Ciudad y Modalidad, que comparten un archivo idéntico (DashboardCompanies/…/JobMarketStatusModal.tsx).

| Elemento | Texto visible | Cálculo |
|---|---|---|
| Título | "Datos Generales" | — |
| Estado | "Sistema actualizado" / "Actualización en ejecución" / "Última actualización fallida" / "Sin información". La variante de Nivel Profesional añade "Datos desactualizados", que aparece cuando la última ejecución terminó hace más de 24 h. A continuación se muestra "· Actualizado {hace X}". | En Nivel Profesional sale del último registro de `scraper_runs` con entidad `jobs` (ScrapingStatusService.php). En las otras tres pantallas sale de la oferta más reciente por `created_at` y de su fuente (JobMarketStatusBuilder.php, modo `market`). |
| "Última actualización del mercado" | Muestra la fecha y la hora, más la insignia "Fuente: {fuente}". Tooltip (Empresas, Demanda Ciudad y Modalidad): "Fecha y hora de la última actualización de datos del mercado laboral, independientemente del indicador." Tooltip (Nivel Profesional): "Fecha y hora de la última ejecución del sistema." | Ídem. |
| Bloque "Métricas globales" › "Total histórico de ofertas" | Valor más la nota "Histórico de {antigüedad}". Tooltip: "Cantidad total de ofertas laborales recolectadas históricamente por el sistema." | Total de ofertas. La antigüedad se mide desde la oferta más antigua (JobMarketStatusBuilder.php / SeniorityIndicatorController.php). |
| "Nuevas ofertas del mes vigente" (en Nivel Profesional: "Nuevas ofertas del mes") | "+N". Tooltip: "Número de ofertas nuevas incorporadas desde el inicio del mes actual." | Ofertas con `created_at` ≥ día 1 del mes actual. |
| Bloque "Periodo analizado" › "Ofertas analizadas" | Valor más la nota "{desde} → {hasta}". Tooltip: "Cantidad de ofertas laborales consideradas dentro del Periodo seleccionado." | Empresas, Demanda Ciudad y Modalidad: todas las ofertas cuya fecha de publicación (o, si no la tiene, de creación) cae en el semestre. Nivel Profesional: solo las vacantes con nivel junior/mid/senior y, si hay carreras seleccionadas, solo las alineadas a ellas. |
| "Promedio diario" | "{n} ofertas/día" más la nota "{d} días cubiertos" ("{d} días" en Nivel Profesional). Tooltip: "Promedio de ofertas publicadas por día dentro del Periodo analizado." | Ofertas analizadas ÷ días del semestre (181 a 184), redondeado. |
| Pie | "Datos del mercado laboral y estado operativo del sistema." (en Nivel Profesional: "Datos del mercado laboral y estado del sistema.") | — |

En Nivel Profesional, los valores en 0 se muestran como "Sin datos"; en las otras pantallas se muestran como "—".
**[POR CONFIRMAR]** Si el scraping de `jobs` nunca se ejecutó, la variante de Nivel Profesional muestra igualmente "Sistema actualizado", porque el servicio no envía indicadores de estado en ese caso (ScrapingStatusService.php). En las otras pantallas el estado nunca puede ser "en ejecución" ni "fallida", porque el modo `market` no envía esos indicadores (JobMarketStatusBuilder.php).

---

#### C.1. Nivel Profesional

- **URL:** `/dashboard/indicators/seniority`. Pestaña del navegador: "Demanda por Nivel Profesional". Migas: Dashboard › Demanda por Nivel Profesional (DashboardSeniority/Index.tsx).
- **Título visible:** "Observatorio Tecnológico ISIL" / **"Distribución de Nivel Profesional"** (SeniorityHeader.tsx).
- **Cómo llegar:** Indicadores › Mercado y Demanda › **Nivel Profesional** (app-sidebar.tsx).
- **Descripción:** sin carrera seleccionada se lee "Análisis del nivel de experiencia solicitado por el mercado laboral, a nivel general."; con alguna carrera seleccionada, "…alineado a la carrera seleccionada." (SeniorityHeader.tsx).

##### C.1.1 Filtros
| Filtro | Tipo | Valores | Por defecto |
|---|---|---|---|
| "Año" | lista | 2025, 2026 | 2026 (SeniorityIndicatorController.php `index`) |
| "Semestre" | botones | "Ene – Jun" (s1), "Jul – Dic" (s2) | s1 |
| "Filtrar por carrera" | insignias de selección múltiple (cada clic activa o desactiva una carrera) | Todas las carreras con `active = 1`, en orden alfabético | ninguna (equivale a "mercado general") |
| Enlace "Limpiar" | Solo aparece si hay carreras seleccionadas; quita la selección | — | — |

Cada cambio recarga la página y conserva los demás filtros (SeniorityFilters.tsx, SeniorityHeader.tsx).
Debajo de los controles se muestra "Periodo activo: Semestre 1 – Enero a Junio {año}" o "Semestre 2 – Julio a Diciembre {año}" (SeniorityIndicatorController.php).

##### C.1.2 Insignias del encabezado
- "{N} vacantes del mercado" o, con carreras seleccionadas, "{N} vacantes alineadas". N es el número de vacantes del semestre con nivel junior/mid/senior. Se toma una vacante cuando su fecha de publicación cae en el semestre o, si no tiene fecha de publicación, cuando su fecha de creación cae en él. Con carreras seleccionadas, N se limita a las vacantes que tienen al menos una tecnología vinculada a un curso de esas carreras (SeniorityIndicatorController.php `baseCareerQuery`).
- "Distribución por nivel": insignia informativa fija.
- Botón **"Datos Generales"**: abre el modal descrito en §0.1.

##### C.1.3 Tarjetas KPI (Junior / Mid / Senior)
| Tarjeta | Valor | Subtexto | Cálculo |
|---|---|---|---|
| "Junior" | % con 1 decimal | "{n} vacantes" | vacantes junior ÷ (junior + mid + senior) × 100, sobre el mismo universo de la insignia anterior (SeniorityKpiGrid.tsx, SeniorityIndicatorController.php) |
| "Mid" | idem | idem | idem |
| "Senior" | idem | idem | idem |

Estado vacío: "No hay vacantes con seniority identificado en el Periodo seleccionado." (SeniorityKpiGrid.tsx). Mientras carga se muestra "Cargando indicador…" (Index.tsx).

##### C.1.4 Gráfico "Distribución de Nivel Profesional"
- Subtítulos: "Distribución de niveles de experiencia por área profesional" y "Un mismo job puede estar asociado a múltiples carreras académicas." Leyenda: Junior (azul oscuro), Mid (celeste), Senior (amarillo) (SeniorityBarChart.tsx).
- Muestra una barra apilada por carrera: todas las carreras activas, o solo las seleccionadas. Cada tramo lleva su cantidad y a la derecha se muestra el total de la carrera. Las carreras se ordenan de mayor a menor total, y las barras se dibujan en proporción a la carrera con más vacantes (SeniorityBarChart.tsx).
- Por cada carrera se cuentan las vacantes **distintas** del semestre que tienen nivel junior/mid/senior y alguna tecnología vinculada a un curso de esa carrera (SeniorityIndicatorController.php `distributionByCareer`).
- Tarjetas resumen: "Total Junior", "Total Mid" y "Total Senior", que suman los valores de todas las carreras. "Total Senior" tiene este tooltip: "Puede incluir duplicaciones porque una oferta puede pertenecer a múltiples carreras" (SeniorityBarChart.tsx).

##### C.1.5 Recuadro "Metodología de cálculo" y botón "Recalcular seniority"
- Viñetas: "Detección del seniority solicitado en cada vacante.", "Normalización de experiencia (junior / mid / senior).", "Cobertura del mercado laboral general (sin filtro por carrera)." (con carreras seleccionadas: "Asociación directa a la carrera seleccionada mediante tech positions.") y "Cálculo porcentual por carrera.". Fórmula mostrada: "% nivel = (vacantes del nivel ÷ total vacantes de la carrera) × 100". Nota: "Peso del indicador: 100%" (SeniorityHeader.tsx).
- Botón **"Recalcular seniority"**; mientras trabaja muestra "Actualizando seniority…". Nota debajo: "Aplica solo a vacantes sin seniority definido". Al terminar recarga la página **sin mensaje de confirmación**; si falla, el error solo queda en la consola del navegador (SeniorityHeader.tsx).
- Regla de negocio: solo se procesan las vacantes con nivel vacío o `unspecified`, y se les asigna el primer nivel cuya condición se cumpla (SeniorityIndicatorController.php `updateSeniority`):

| Orden | Condición | Nivel |
|---|---|---|
| 1 | El título contiene "junior" o " jr " | junior |
| 2 | El título contiene "mid" o "semi senior" | mid |
| 3 | El título contiene "senior", " sr ", "lead" o "principal" | senior |
| 4 | El nivel de experiencia es junior, jr, entry level o internship | junior |
| 5 | El nivel de experiencia es mid, associate o mid-senior level | mid |
| 6 | El nivel de experiencia es senior, sr, lead, principal o director | senior |
| — | Ninguna de las anteriores | unspecified (queda fuera del indicador) |

##### C.1.6 Botón "Ver evolución ▼" (menú desplegable)
Opciones: **"📊 Evolución general"** y **"🎓 Evolución por carrera"** (SeniorityHeader.tsx).

**a) Modal "Evolución Nivel Profesional"** (SeniorityEvolutionModal.tsx)
- Descripción: "Distribución de niveles profesionales en base a muestras estrictas por periodo".
- Lista de granularidad: "Semanal" (por defecto), "Quincenal" y "Mensual".
- Cada tarjeta de periodo muestra:
  - Etiqueta. Semanal: "Semana N - AAAA". Quincenal: "Primera/Segunda quincena de {mes}". Mensual: "{mes} {año}" (SeniorityIndicatorController.php `evolution`).
  - "Rango de corte: {inicio} al {fin}".
  - **"Muestra Absoluta"**: total de vacantes.
  - Tres recuadros JUNIOR / MID / SENIOR, cada uno con "%" y "{n} vacantes".
- Paginación de 6 periodos por página, del más reciente al más antiguo: "← Anterior", "Página X de Y", "Siguiente →".
- Estados: "Cargando métricas de evolución..."; si no hay datos, "No se registran vacantes ni variaciones en este tramo para la carrera seleccionada".
- Cálculo: los datos **no se calculan en vivo**; se leen de una caché (`seniority_evolution_cache`) que llena el comando `seniority:take-snapshot`. Los valores son **acumulados desde el 1 de enero del año en curso** hasta cada periodo, y solo cuentan las vacantes con fecha de publicación y nivel junior/mid/senior. Los porcentajes se ajustan para que sumen 100 %; la diferencia por redondeo se carga en Senior (TakeSenioritySnapshotCommand.php).
- El modal muestra todos los periodos del año seleccionado, sin filtrar por semestre (SeniorityIndicatorController.php `evolution`).
- Botón **"📥 Exportar Vista Actual"**: descarga el Excel `seniority_evolution_{carrera}_{semestre}_{año}.xlsx` con los periodos del semestre y la granularidad elegidos, del universo global. Columnas: ID, Año, Tipo de Periodo, Etiqueta de Periodo, Fecha de Inicio, Fecha de Fin, Total Vacantes (Acumulado), Cantidad Junior, Cantidad Mid, Cantidad Senior, Porcentaje Junior, Porcentaje Mid, Porcentaje Senior, Fecha de Creación, Última Actualización (SeniorityEvolutionExport.php).
- Botón "📊 Reporte Consolidado": solo se mostraría si hubiera una carrera distinta de "global". **[POR CONFIRMAR]** El modal busca `filters.career_slug`, pero la página envía `filters.career`, así que siempre trabaja con "global" y este botón nunca aparece. Además, la caché solo se llena para "global" (SeniorityEvolutionModal.tsx, TakeSenioritySnapshotCommand.php).

**b) Modal "Evolución por Carrera"** (SeniorityEvolutionCareerModal.tsx)
- Descripción: "Evolución de vacantes tecnológicas agrupadas por carrera".
- Lista de granularidad: Semanal, Quincenal o Mensual.
- Cada tarjeta de periodo muestra la etiqueta, "{inicio} → {fin}" y "Total" (vacantes del mercado). Debajo hay un recuadro por carrera con "{n} vacantes" y "%".
- Estados: "Cargando..."; si no hay datos, "No hay datos disponibles".
- Cálculo: se lee de la caché `career_evolution_cache`, que llena el comando `careers:take-snapshot`:
  - Cada registro es una "foto" que se toma el domingo (semanal), el día 15 o el último día del mes (quincenal) y el último día del mes (mensual).
  - "Total" = todas las ofertas del semestre hasta el día de la foto.
  - Vacantes de cada carrera = ofertas distintas alineadas a esa carrera por tecnología.
  - % = vacantes de la carrera ÷ Total × 100. Los porcentajes de las carreras pueden sumar más de 100 % porque una vacante puede pertenecer a varias carreras.

  Solo se muestran las fotos tomadas dentro del semestre seleccionado (TakeCareerSnapshotCommand.php, SeniorityIndicatorController.php `evolutionCareers`).
- Botón **"📥 Exportar Excel"**: descarga `evolution_careers.xlsx`. Columnas: Año, Tipo de Periodo, Etiqueta, Fecha Inicio, Fecha Fin, ID Carrera, Carrera, Slug Carrera, Vacantes por Carrera, Mercado Global Total, Porcentaje (%) (SeniorityEvolutionCareerExport.php). **[POR CONFIRMAR]** El servidor ignora la granularidad elegida y siempre exporta la semanal (SeniorityIndicatorController.php `exportEvolutionCareers`).

**c) Botón "📊 Por carrera"** (solo visible con carreras seleccionadas): abre en una pestaña nueva la misma exportación `evolution_careers.xlsx` (semanal). **[POR CONFIRMAR]** No se limita a las carreras seleccionadas, porque el servidor no recibe ese filtro (SeniorityHeader.tsx, SeniorityIndicatorController.php).

**[POR CONFIRMAR] Actualización de las cachés:** los comandos `seniority:take-snapshot`, `careers:take-snapshot` y `companies:take-snapshot` no figuran en la programación de Laravel (app/Console/Kernel.php, routes/console.php). Si nadie los ejecuta (a mano o por cron del servidor), los modales de evolución no tendrán datos nuevos.

##### C.1.7 Elementos ocultos o pendientes
- **[PENDIENTE]** Gráfico de modalidad por nivel (`SeniorityModalityPieChart`), comentado en la página. Sus datos se siguen pidiendo a `/dashboard/indicators/seniority/modality`, pero no se muestran (Index.tsx).
- **[PENDIENTE]** La tarjeta `SeniorityMethodologyCard` no se usa (SeniorityMethodologyCard.tsx).

---

#### C.2. Empresas

- **URL:** `/dashboard/indicators/companies`. Pestaña: "Indicador | Ranking de Empresas | Observatorio ISIL". Migas: Dashboard › Empresas (DashboardCompanies/Index.tsx).
- **Título:** "Observatorio Tecnológico ISIL" / **"Ranking de Empresas"**. Descripción: "Identificación y ranking de las empresas que concentran mayor número de vacantes tecnológicas publicadas en portales de empleo durante el Periodo seleccionado." (CompanyIndicatorHeader.tsx).
- **Cómo llegar:** Indicadores › Mercado y Demanda › **Empresas**.

##### C.2.1 Universo de datos
Se toman las ofertas que cumplen todo lo siguiente (CompanyIndicatorController.php `index`):
- tienen empresa;
- su **fecha de publicación** cae en el semestre (aquí no se usa la fecha de creación como respaldo);
- su región normalizada no es "Desconocido";
- respetan los filtros de región y país que se hayan aplicado.

Las empresas se agrupan por nombre en MAYÚSCULAS y sin espacios sobrantes, de modo que "Acme" y "ACME " cuentan como una sola.

##### C.2.2 Controles y filtros
| Control | Tipo | Valores | Por defecto |
|---|---|---|---|
| "Año" | lista | 2025, 2026 | 2026 |
| "Semestre" | botones | "Ene – Jun" / "Jul – Dic" | s1 |
| "Región" | lista | "Todas" + regiones normalizadas existentes | Todas |
| "País" | buscador con autocompletado. Placeholder: "Buscar país…" | Empieza a buscar con **2 caracteres** y muestra hasta **10** países, limitados a la región elegida. La "X" quita el país. | vacío |
| "Resultados" | lista | "7 por página", "10…", "20…", "50…" | 7 |
| "Eliminar todos" | botón (solo con región o país aplicados) | Quita la región y el país | — |
| Chips | "Región: X" / "País: Y" con ✕ | Quitan cada filtro | — |

Fuente: CompanyFilters.tsx y CompanyIndicatorController.php `searchCountries`.
**[POR CONFIRMAR]** La lista "Resultados" envía `perPage`, pero el servidor lee `per_page`, así que la página sigue mostrando 7 filas.
Debajo de los controles se muestra "Periodo activo: Ene - Jun" o "Jul - Dic".

##### C.2.3 Insignias y KPIs
| Elemento | Texto | Cálculo (CompanyIndicatorController.php) |
|---|---|---|
| Insignia | "{N} vacantes" | Suma de vacantes de todas las empresas del universo |
| Insignia | "{N} empresas" | Número de empresas distintas |
| Botón | "Datos Generales" con la nota "ver detalle" | Modal de §0.1 |
| KPI "Empresas" | valor; subtexto "Con al menos una vacante" | Número de empresas distintas |
| KPI "Empresa líder" | nombre; subtexto "{n} vacantes" (o "Sin datos") | La empresa con más vacantes |
| KPI "Vacantes líder" | número; subtexto "Publicadas por la empresa líder" | Vacantes de la empresa líder |
| KPI "Concentración Top 3" | "%"; subtexto "Del total de vacantes" | (vacantes de las 3 primeras ÷ total de vacantes) × 100, redondeado a entero |

Fuente de los textos: CompanyKpiGrid.tsx.

##### C.2.4 Tabla "Ranking de Empresas por Vacantes Tecnológicas"
- Texto introductorio: "Clasificación de empresas que concentran el mayor número de vacantes tecnológicas publicadas en portales de empleo, según el Periodo, región y país seleccionados." (Index.tsx).
- Columnas: **#** (posición), **Empresa** (se puede hacer clic) y **Vacantes** (número de ofertas). Las filas van de mayor a menor (CompanyRankingList.tsx).
- **[POR CONFIRMAR]** La columna "#" vuelve a empezar en 1 en cada página.
- Paginación: "Mostrando {desde} – {hasta} de {total} empresas" y botones de página. Se oculta si solo hay una página (CompanyPagination.tsx).

##### C.2.5 Modal "Empleos en {empresa}" (clic en el nombre de una empresa)
- Subtítulo: "{n} vacantes encontradas". Columnas: **Puesto**, **País**, **Publicado** (fecha) y **Oferta** (enlace **"Ver"**, que abre la oferta original en otra pestaña, o "—" si no hay enlace) (CompanyJobsModal.tsx).
- Muestra 5 vacantes por página, de la más reciente a la más antigua, con los botones "Anterior", "Página X de Y" y "Siguiente". Mientras carga se ve "Cargando empleos..."; si no hay resultados, "No hay empleos registrados".
- Filtra por empresa, año, semestre y país. **No aplica el filtro de región**, así que el número puede diferir del de la tabla si solo hay una región seleccionada (CompanyIndicatorController.php `companyJobs`).

##### C.2.6 Botón "Ver evolución ›": modal de evolución de empresas
- El título depende de la granularidad: "Evolución semanal", "Evolución quincenal" o "Evolución mensual". Descripción: "Distribución de empresas por demanda laboral" (CompanyEvolutionModal.tsx).
- Pestañas: **"Nacional"** (ofertas con país = "Peru") e **"Internacional"** (resto de países) (TakeCompanySnapshotCommand.php).
- Lista de granularidad: Semanal, Quincenal o Mensual. A la derecha se muestra "{n} periodos".
- Cada tarjeta de periodo lleva la etiqueta y las fechas "{dd-mmm} → {dd-mmm}". Dentro hay un recuadro por empresa con su posición "#n", el nombre y "{n} vacantes".
- Paginación de 5 periodos por página ("← Anterior", "Página X de Y", "Siguiente →"). Estados: "Cargando evolución..."; si no hay datos, "No hay datos disponibles".
- Cálculo: se lee de la caché `company_evolution_cache`, que llena `companies:take-snapshot`. Cada periodo cuenta las ofertas con empresa publicadas (o, sin fecha de publicación, creadas) entre el inicio y el fin de ese periodo. Las empresas se ordenan por vacantes (TakeCompanySnapshotCommand.php).
- **[PENDIENTE]** El "Total" del periodo y el "%" por empresa están comentados en la interfaz.
- **[POR CONFIRMAR]** El servidor filtra los periodos buscando el nombre de un mes del semestre dentro de la etiqueta, pero las etiquetas semanales ("Semana N - AAAA") no contienen ningún mes, así que la vista "Semanal" podría quedar vacía (CompanyIndicatorController.php `evolutionCompanies`).
- Botón **"Exportar Excel"**: descarga `companies_{national|international}_evolution.xlsx` según la pestaña activa. Columnas: ID, Año, Tipo Periodo, Mercado, Periodo, Fecha Inicio, Fecha Fin, Empresa Normalizada, Empresa Original, Vacantes, Puesto Ranking, Total Mercado, Porcentaje (%) (CompanyEvolutionExport.php). Porcentaje = vacantes ÷ total del mercado del periodo × 100, con 1 decimal.

##### C.2.7 Recuadro "Metodología"
Viñetas: "Identificación de empresa por vacante", "Conteo total de vacantes publicadas" y "Ordenamiento de mayor a menor". Fuente indicada: "Fuente: Portales de empleo (100%)". Nota: "El ranking refleja volumen de publicación, no calidad ni seniority de las vacantes." (CompanyIndicatorHeader.tsx).
**[PENDIENTE]** La tarjeta `CompanyMethodologyCard` está comentada (Index.tsx).

---

#### C.3. Demanda Ciudad

- **URL:** `/dashboard/indicators/job-demand-geo`. Pestaña: "Demanda laboral por ciudad". Migas: Dashboard › "Demanda laboral (Geo + Carrera)", esta última sin enlace (DashboardJobDemandGeo/Index.tsx).
- **Título:** "Observatorio Tecnológico ISIL" / **"Demanda laboral por ciudad"**. Descripción: "Análisis geográfico de la demanda laboral, basado en el volumen real de vacantes publicadas por ciudad y su nivel de concentración territorial." (JobDemandGeoHeader.tsx).
- **Cómo llegar:** Indicadores › Mercado y Demanda › **Demanda Ciudad**.

##### C.3.1 Universo de datos (JobDemandGeoIndicatorController.php `buildIndex`)
Se toman las ofertas que cumplen todo lo siguiente:
- su **fecha de publicación** cae en el semestre;
- tienen ciudad, y la ciudad no es "Desconocido";
- su región normalizada no es "Desconocido";
- están **alineadas a alguna carrera**. La tabla de alineación relaciona vacante y carrera por tecnologías, lenguajes o metodologías vinculados a cursos de la carrera (BuildJobOfferAlignment.php).

**[POR CONFIRMAR]** Sin el filtro de carrera, cada oferta se cuenta **una vez por cada carrera a la que está alineada**, porque el conteo no usa vacantes distintas. Esto infla el total de "vacantes" y las cifras por ciudad.

##### C.3.2 Controles y filtros
| Control | Tipo | Valores | Por defecto |
|---|---|---|---|
| "Año de análisis" | lista | 2024, 2025, 2026 | 2026 |
| "Semestre" | botones | "Ene – Jun" / "Jul – Dic" | s1 |
| "Carrera" | lista + "X" | "Todas" + las carreras con demanda en el semestre, ordenadas por demanda | Todas |
| "Región" | lista + "X" | "Todas" + regiones normalizadas. Al cambiarla se borra el país. | Todas |
| "País" | buscador. Placeholder: "Seleccionar país" | **Solo busca si hay una región elegida.** Muestra hasta 15 países de esa región, con una espera de 300 ms al escribir. | vacío |

Fuente: JobDemandGeoFilters.tsx y `searchCountries`.

##### C.3.3 Insignias y KPIs
| Elemento | Texto | Cálculo |
|---|---|---|
| Insignia | "{N} vacantes" | Suma de ofertas del universo filtrado (ver la advertencia de §3.1) |
| Insignia | "{N} ciudades" | Número de ciudades distintas |
| Botón | "Datos Generales" | Modal de §0.1 |
| KPI "Carrera líder" | nombre ▼ | La carrera con más ofertas **distintas** del semestre, alineadas por tecnología. **No le afectan** los filtros de región, país ni carrera (`careerDemand`). |
| KPI "Ciudad líder" | nombre | Ciudad con más ofertas, sumando todas sus filas de región o país, con los filtros aplicados |
| KPI "Concentración Top 5" | "%" con 1 decimal | (ofertas de las 5 ciudades principales ÷ total) × 100 |

Fuente de los textos: CityDemandKpiGrid.tsx.
- Al hacer clic en "Carrera líder" se despliega la lista "Todas las carreras" + "#n {carrera}", con la etiqueta "TOP" en la primera.
- **[POR CONFIRMAR]** Al elegir una carrera en esa lista se navega a `/dashboard/job-demand-geo`, ruta que no existe (probable error 404). Para filtrar por carrera hay que usar la lista "Carrera" de los filtros (CityDemandKpiGrid.tsx, routes/web.php).

##### C.3.4 Mapa "Mapa de calor – Demanda laboral"
- Mapa base Esri en gris oscuro, centrado en Lima (zoom inicial 5), con zoom mediante la rueda del ratón. Escala de color: celeste → amarillo → naranja → rojo (CityDemandMap.tsx).
- Cada punto es la combinación país + ciudad, situada en la latitud y longitud promedio de sus ofertas. La intensidad es total ÷ máximo, con un mínimo de 0,15 (`buildHeatmapData`).
- Respeta el año, el semestre, la región y el país. **No aplica el filtro de carrera** ni exige alineación. Solo entran las ofertas con coordenadas.
- Mensajes: "Cargando mapa…". Si falla, "No se pudo cargar el mapa de calor." con el botón **"Reintentar"**. La petición tiene un límite de 15 s.

##### C.3.5 Tabla "Ranking de ciudades por demanda"
- Columnas: **Ciudad**, **Región** (valor original de la base, sin normalizar), **País**, **Ofertas** y **%**, donde % = ofertas de la fila ÷ total × 100, con 1 decimal (JobDemandCityTable.tsx, controlador).
- Muestra 10 filas por página y no hay selector. Pie de tabla: "Mostrando X – Y de Z" y botones de página.

##### C.3.6 Recuadro "Metodología de cálculo" y botón "Actualizar alineación laboral"
- Viñetas: "Conteo total de vacantes publicadas por ciudad", "Agrupación jerárquica ciudad → región → país", "Ranking por volumen absoluto de ofertas" y "**Concentración Top 5**: porcentaje de ofertas acumuladas en las 5 ciudades con mayor demanda". Nota: "Los cálculos se realizan únicamente con datos del Periodo seleccionado." (JobDemandGeoHeader.tsx).
- Botón **"Actualizar alineación laboral"**. Texto de ayuda: "Reconstruye la relación entre vacantes y carreras usando los datos más recientes." Al pulsarlo:
  1. Aparece un diálogo de confirmación con el título "Actualizar alineación laboral", el texto "Se reconstruirá la relación entre vacantes y carreras con los datos más recientes." y los botones "Actualizar" / "Cancelar".
  2. Mientras se procesa se muestra "Actualizando alineación..." con el texto "Esto puede tardar unos minutos".
  3. Si todo va bien aparece "Alineación actualizada", con el texto "Los indicadores se recalcularon correctamente.", y la página se recarga.
  4. Si falla aparece "Error", con el texto "No se pudo reconstruir la alineación.".
- Qué hace por dentro: envía **a la cola** varios procesos de normalización geográfica (Asia, Europa, regiones, Norteamérica, Oceanía, Perú y entidades nulas) y luego reconstruye por completo la alineación vacante–carrera. También invalida la caché del indicador (`rebuildAlignment`).
- **[POR CONFIRMAR]** El mensaje de éxito aparece cuando los trabajos quedan en cola, no cuando terminan, así que los datos pueden tardar en actualizarse.

##### C.3.7 Caché
Los resultados de esta pantalla (ciudades, carreras, regiones, mapa y Datos Generales) se guardan en caché **1 hora** para cada combinación de filtros. La caché se invalida al pulsar "Actualizar alineación laboral" (JobDemandGeoIndicatorController.php, constante `CACHE_TTL`).

##### C.3.8 Endpoints /ai/city-demand (get-data y export)
- `/ai/city-demand/get-data` y `/ai/city-demand/export` pertenecen al componente antiguo `pages/dashboards/components/CityDemandMap/CityDemandMap.tsx`, que **ninguna pantalla importa**. La página "Demanda Ciudad" **no los usa** (búsqueda en resources/js).
- **[PENDIENTE]** Ese componente ofrecía estas exportaciones:
  - **"Exportar PDF (solo 1 país)"**, con la alerta "⚠️ Para generar el PDF, selecciona exactamente un país.";
  - **"Exportar Excel"**.

  El archivo generado (`city-demand-{año}.pdf` / `.xlsx`) lleva el encabezado "Observatorio de Empleabilidad ISIL", "Reporte: Demanda laboral por ciudad", Año, Fuentes, Modalidades y Países. Sus columnas son País, Ciudad, Empresa, Título, Modalidad, Fuente y Fecha Publicación. Admite filtros de año, fuentes, países, modalidades, trimestre (Q1–Q4) y rango de fechas, e incluye las ofertas remotas (CityDemandAIController.php, resources/views/exports/city-demand.blade.php, CityDemandExport.php).
- **[PENDIENTE]** La ruta `/dashboard/heatmap` apunta a un método `heatmap` que no existe en el controlador. La tarjeta `JobDemandGeoMethodologyCard` está comentada (routes/web.php, Index.tsx).

---

#### C.4. Modalidad Laboral

- **URL:** `/dashboard/indicadores/modalidad-laboral`. Pestaña: "Indicador de Modalidad Laboral | Observatorio ISIL". Migas: Dashboard › Indicadores › Modalidad laboral (JobModalityIndicatorPage.tsx).
- **[POR CONFIRMAR]** La miga "Indicadores" enlaza a `/dashboard/indicadores`, que no está definida en routes/web.php.
- **Título:** "Observatorio Tecnológico ISIL" / **"Modalidad laboral de las vacantes"**. Descripción: "Distribución porcentual de vacantes según modalidad de trabajo, basada en ofertas laborales reales de portales de empleo." (JobModalityIndicatorHeader.tsx).
- **Cómo llegar:** Indicadores › Mercado y Demanda › **Modalidad Laboral**.

##### C.4.1 Clasificación de modalidad (JobModalityIndicatorController.php `index`)
| Valor en la base | Modalidad mostrada |
|---|---|
| remote, fully_remote, remote_local | remoto |
| hybrid | híbrido |
| no_remote | presencial |
| no_precisa, vacío u otros | se excluyen |

Universo: ofertas cuya **fecha de publicación** cae en el semestre y cuya región (valor original) es distinta de "Desconocido", con los filtros geográficos aplicados.

##### C.4.2 Controles y filtros
| Control | Tipo | Comportamiento | Por defecto |
|---|---|---|---|
| "Año de análisis" | lista | 2025, 2026 | 2026 |
| "Semestre" | botones | "Ene – Jun" / "Jul – Dic" | s1 |
| Bloque "Filtros geográficos" › "Región" | buscador con autocompletado | Muestra hasta 15 regiones (valores originales, sin "Desconocido"), con una espera de 250 ms. Al elegir una región se borran el país y la ciudad. | vacío |
| "País" | buscador | **Deshabilitado hasta elegir una región.** Muestra hasta 15 países de la región. | vacío |
| "Ciudad" | buscador | **Deshabilitado hasta elegir un país.** Muestra hasta 15 ciudades del país. | vacío |
| "Limpiar filtros" | botón (con algún filtro aplicado) | Deja solo el año y el semestre | — |
| Chips | "Región: X", "País: Y", "Ciudad: Z" con ✕ | Al quitar la región se borran también el país y la ciudad; al quitar el país se borra también la ciudad | — |

Fuente: GeographicFilters.tsx, FilterChip.tsx y JobModalityIndicatorPage.tsx.
Debajo de los controles se muestra "Periodo activo: Semestre 1 – Enero a Junio {año}" o "Semestre 2 – Julio a Diciembre {año}".

##### C.4.3 Insignias y KPIs
- Insignia "{N} vacantes analizadas": número de ofertas clasificadas en las 3 modalidades. Botón "Datos Generales" (§0.1).
- KPI **"Trabajo remoto"**, **"Modalidad híbrida"** y **"Trabajo presencial"**: cada uno es un % con 1 decimal, igual a las vacantes de la modalidad ÷ las vacantes analizadas × 100 (ModalityKpiGrid.tsx, controlador).

##### C.4.4 Gráficos y tabla
- **"Distribución de vacantes por modalidad"**: gráfico de dona con las porciones remoto (celeste), híbrido (verde) y presencial (naranja), y leyenda abajo. Tooltip: "{modalidad}: {vacantes} ({%}%)" (ModalityDoughnutChart.tsx).
- **"Evolución temporal"**, con el subtítulo "Tendencia de los últimos 6 meses": líneas Remoto, Híbrido (discontinua) y Presencial sobre un eje de 0 a 100 %, con una etiqueta por mes. Si no hay datos, el gráfico no se muestra (ModalityTrendChart.tsx).
  - Cálculo: % de cada modalidad sobre todas las ofertas del mes que tienen modalidad (sin contar "no_precisa") y región distinta de "Desconocido" (controlador `trendData`).
  - **[POR CONFIRMAR]** Este gráfico **no aplica los filtros geográficos**. Además, en realidad muestra los meses del semestre elegido, no "los últimos 6 meses".
- Tabla resumen: columnas **Modalidad**, **Vacantes** y **%** (con 2 decimales) (ModalitySummaryTable.tsx).

##### C.4.5 Recuadros de conclusiones (insights)
(useModalityInsights.ts)
- "Trabajo remoto en auge": "El {x}% de las vacantes tech ofrece modalidad remota, reflejando la transformación digital del mercado laboral." **[POR CONFIRMAR]** No se muestra nunca, porque busca la modalidad "remote" y el servidor devuelve "remoto".
- "Híbrido en crecimiento": "Con +{x}% de crecimiento, el modelo híbrido se consolida como la opción preferida por empresas." Aparece si cambia el % híbrido entre el primer y el último mes. **[POR CONFIRMAR]** Si el cambio es negativo, se muestra "+-x%".

##### C.4.6 Metodología (recuadro derecho, "Metodología de análisis")
(ModalityMethodologyCard.tsx)
- "1. Identificación – Análisis del texto de cada vacante para detectar palabras clave relacionadas con la modalidad laboral."
- "2. Clasificación – Cada vacante se clasifica como remota, híbrida o presencial según la modalidad predominante detectada."
- "3. Cálculo – Porcentaje = (Vacantes por modalidad / Total de vacantes) × 100".
- **[PENDIENTE]** El pie "100% basado en demanda laboral real de portales de empleo." está comentado.

##### C.4.7 Elementos pendientes
- **[PENDIENTE]** El servidor admite el filtro `source` (portal), pero no hay control en pantalla. Existe un componente sin usar, `JobModalityFilters`, con los campos "País" (placeholder "Ej. Perú, México"), "Portal de empleo" (Todos / Adzuna / GetOnBoard / Computrabajo) y "Publicado desde" (fecha). El servidor no procesa el filtro "Publicado desde" (JobModalityFilters.tsx, controlador).
- Esta pantalla no tiene exportación.

---

#### C.5. Demanda laboral por carrera (`/dashboard/ranking-carreras`): no está en el menú

- **Accesibilidad:** **no aparece en la barra lateral** y ningún otro componente enlaza a ella. Solo se llega escribiendo la URL (búsqueda en resources/js; app-sidebar.tsx). Requiere una sesión iniciada.
- **Pestaña:** "Demanda laboral por carrera | Observatorio ISIL". Migas: Dashboard › Demanda laboral por carrera. **Título:** "Demanda laboral por carrera". Descripción: "Análisis de la demanda laboral por carrera académica, basado exclusivamente en la identificación de roles tecnológicos dentro de ofertas laborales reales." (RankingCarrerasHeader.tsx).
- **Filtros:**
  - "Año de análisis": 2025 o 2026; **por defecto 2025**.
  - "Semestre": botones "Ene – Jun" / "Jul – Dic"; **por defecto s2**. El activo lleva un ícono de check.
  - "Filtrar por carrera": botones de selección múltiple, con "Limpiar" y el pie "{n} carrera(s) seleccionada(s)".

  **[PENDIENTE]** El servidor admite el filtro `country`, pero no hay control en pantalla (RankingCarrerasController.php, RankingCarrerasFilters.tsx).
- **Insignias:** "{N} vacantes analizadas" y "Peso laboral: 100%". Debajo se muestra "Periodo activo: Semestre 1/2 – …".
- **KPIs** (CareerKpiGrid.tsx):
  - "Vacantes analizadas", con la ayuda "Ofertas laborales únicas consideradas": número de ofertas distintas del semestre que tienen un rol tecnológico asociado a alguna carrera. **No le afecta** el filtro de carrera.
  - "Carrera líder", con la ayuda "Mayor demanda laboral en el Periodo" (o "Sin datos suficientes"): la primera fila del ranking.
- **Tabla:** columnas **Carrera** y **Vacantes**. Vacantes = ofertas distintas publicadas en el semestre cuyo rol tecnológico (tech position) está vinculado a la carrera.
  - **[POR CONFIRMAR]** El servidor pagina de 6 en 6, pero la pantalla no tiene controles de paginación: solo se ven las 6 primeras carreras (CareerRankingTable.tsx, controlador).
- **Botón "Actualizar datos laborales":**
  - Asocia roles a ofertas: recorre las ofertas publicadas después de la última procesada y busca el nombre de cada rol tecnológico activo dentro del título de la oferta, sin distinguir mayúsculas (SyncCareerRolesService.php).
  - Al terminar recarga la página. Si hay un error de red muestra "No se pudo actualizar los datos laborales".
  - **[POR CONFIRMAR]** La petición se envía sin el token CSRF, por lo que podría ser rechazada (error 419) y aun así la página se recargaría sin avisar (RankingCarrerasHeader.tsx).
- **Metodología:** "1. Identificación de roles en ofertas laborales", "2. Asociación de roles a carreras académicas", "3. Conteo de vacantes únicas por carrera" y la nota "Los cálculos se realizan únicamente con datos del Periodo seleccionado.".
- **[PENDIENTE]** El gráfico `CareerBarChart.tsx` es un archivo vacío.

### 2.9 Indicadores › Tendencias › Tendencias Macro

#### D.1 Indicadores › Tendencias › **Tendencias Macro**

| Dato | Valor |
|---|---|
| Menú | Indicadores › Tendencias › "Tendencias Macro" (resources/js/components/app-sidebar.tsx) |
| URL | `/dashboard/indicators/macro-trends` (routes/web.php) |
| Título de la pestaña del navegador | "Macro-Tendencias \| Observatorio ISIL" (pages/DashboardMacroTrends/MacroTrendsIndicatorPage.tsx) |
| Breadcrumb | Dashboard › Macro-Tendencias. El enlace del breadcrumb apunta a `/dashboard/macro-trends`, que no es la URL real de la pantalla [POR CONFIRMAR si funciona] (MacroTrendsIndicatorPage.tsx) |
| Permiso | Solo sesión iniciada. La ruta no exige ningún permiso adicional (routes/web.php) |

##### Qué muestra
Lista de "macrotendencias" tecnológicas globales, como IA generativa o ciberseguridad. Cada una viene de un artículo o reporte de internet que la IA analizó y resumió. Los datos se guardan en la tabla `macro_trend_raw` (MacroTrendsIndicatorController.php).

##### Encabezado
- Sobretítulo: "Observatorio Tecnológico ISIL". Título: **"Macro Tendencias Estratégicas"** (components/Header/MacroTrendsHeader.tsx).
- **Selector de año:** opciones fijas 2025 y 2026. Por defecto toma el año actual del servidor (MacroTrendsHeader.tsx; `now()->year` en MacroTrendsIndicatorController.php).
- **Selector de semestre:** botones "Ene – Jun" (`s1`, por defecto) y "Jul – Dic" (`s2`). Cambiar el año o el semestre recarga la pantalla en la página 1 y conserva la búsqueda (MacroTrendsHeader.tsx).
- Pie del encabezado:
  - "Periodo activo:" seguido de "Semestre 1 – Enero a Junio {año}" o "Semestre 2 – Julio a Diciembre {año}" (MacroTrendsIndicatorController.php).
  - "Última actualización: {fecha-hora}". Esta fecha es la hora del servidor al cargar la página, **no** la fecha del último dato cargado (`now()` en MacroTrendsIndicatorController.php).

##### KPIs (3 tarjetas)
| Tarjeta | Cálculo exacto |
|---|---|
| "Reportes semestre" | Cantidad de filas de `macro_trend_raw` con `year` = año elegido y `created_at` entre el 01-01 y el 30-06 (s1) o entre el 01-07 y el 31-12 (s2). **También respeta la búsqueda.** El filtro usa la fecha en que el registro se guardó en el sistema, no la fecha del reporte. Las fechas se comparan sin hora, así que los registros creados el último día del semestre después de las 00:00 podrían quedar fuera [POR CONFIRMAR] (MacroTrendsIndicatorController.php `index`, `getPeriodRange`) |
| "Reportes del año" | Cantidad de filas con `year` = año elegido. Sin búsqueda ni semestre (ídem) |
| "Histórico total" | Total de filas de `macro_trend_raw` (ídem) |

##### Buscador
- Placeholder: "Buscar tendencia, fuente, descripción...". Busca automáticamente 400 ms después de dejar de escribir. La "X" limpia el texto. Mientras hay texto se muestra `Buscando: "{texto}"` (components/MacroTrendSearch.tsx).
- Coincidencia parcial en: nombre de la tendencia, descripción, nombre de la fuente, título de la fuente y tipo de fuente (MacroTrendsIndicatorController.php).

##### Tarjetas de tendencia (4 por página, de la más reciente a la más antigua)
Datos de orden y paginación: (MacroTrendsIndicatorController.php `paginate(4)`, `orderByDesc('created_at')`).
- **Título:** nombre de la tendencia, o "Sin título" si falta. Al lado siempre aparecen **4 estrellas fijas**: no son una calificación (components/Cards/MacroTrendCard.tsx, `stars = 4`).
- **Descripción:** se muestra recortada a 2 líneas y se expande al pasar el mouse (MacroTrendCard.tsx).
- **Etiqueta** con el nombre de la fuente, o "Fuente" si falta. Botón **"Ver análisis"**, que abre el modal de detalle. Abajo a la derecha: "{año} · Q{trimestre}" (MacroTrendCard.tsx).
- El enlace "Ver reporte" está **comentado** [PENDIENTE / OCULTO] (MacroTrendCard.tsx).

##### Modal "Ver análisis"
Contenido (components/Detail/MacroTrendDetailModal.tsx):
- Título. El modal lee `trend.name`, pero el dato se llama `trend_name`, así que **el título probablemente sale vacío** [POR CONFIRMAR].
- 4 estrellas fijas y la descripción completa.
- Recuadro de la fuente: "{tipo, o 'Reporte'} • {fuente}", el título de la fuente y el enlace **"Ver fuente original"**, que abre la URL en una pestaña nueva.
- Pie: "Registrado: {fecha}".
- Se cierra con la X o haciendo clic fuera del modal.

##### Paginación
Botones ←, números con "..." y →. Al cambiar de página, la búsqueda **no se envía**: se pierde el filtro de texto [POR CONFIRMAR comportamiento] (MacroTrendsIndicatorPage.tsx).

##### Cómo se cargan los datos (procesos en segundo plano)
| Proceso | Qué hace, en palabras simples |
|---|---|
| Comando `trends:discover-global` (opciones `--year`, `--quarter`, `--limit=30`) | 1) Busca artículos en internet con la API **Tavily**. Usa 5 consultas fijas: "technology trends {año}", "AI trends {año}", "future of work technology {año}", "cybersecurity trends report {año}" y "digital transformation trends {año}". 2) Envía hasta 60 títulos y enlaces a **OpenAI (modelo `gpt-5`)**. Le pide de 8 a 12 macrotendencias, cada una respaldada por al menos 2 artículos. 3) Comprueba que la URL responda (código HTTP 200–399). 4) Descarta las URL ya guardadas para ese año. 5) Guarda el resultado en `macro_trend_raw` (app/Console/Commands/Trends/DiscoverGlobalMacroTrendsCommand.php) |
| Comando `trends:discover-exa` (`--limit=40`) | Hace lo mismo, pero busca los artículos con la API **Exa** y los analiza con `gpt-5` (app/Console/Commands/Trends/DiscoverMacroTrendsExaCommand.php) |
| Botón "Ejecutar descubrimiento" (`POST /dashboard/indicators/macro-trends/run`) | **[PENDIENTE / OCULTO]** El encabezado tiene la función `handleRunDiscover`, pero no hay ningún botón que la llame (MacroTrendsHeader.tsx). Además, la ruta despacha `App\Jobs\DiscoverMacroTrendsJob`, que **no existe** en `app/Jobs`. Si se llamara, fallaría. El mensaje previsto era "Descubrimiento iniciado en segundo plano." y convertía `s1` en el trimestre 1 y `s2` en el trimestre 3 (MacroTrendsIndicatorController.php `runDiscover`) |

En la práctica, estos comandos se ejecutan **a mano por consola**. No figuran en el programador de tareas (app/Console/Kernel.php).

##### Rutas sin uso o sin implementar [PENDIENTE / OCULTO]
- `POST /dashboard/indicators/macro-trends/weights` guarda ponderaciones laboral/tendencia. Las dos deben sumar 1.00; si no, devuelve "Las ponderaciones deben sumar 1.00". Se guardan en `ranking_weights` con el contexto `macro_trends`. **No hay pantalla que la use** (MacroTrendsIndicatorController.php `storeWeights`).
- `GET …/macro-trends/{id}/detail`, `…/{id}/reports` y `…/{id}/jobs` llaman a métodos (`detail`, `getReports`, `getJobs`) que **no existen** en el controlador (routes/web.php; MacroTrendsIndicatorController.php).
- Componentes que existen pero ninguna pantalla usa: `MacroTrendsFilters`, `MacroTrendsKpiGrid`, `MacroTrendsRankingTable`, `ScoreBar` y `WeightConfigModal` (carpeta pages/DashboardMacroTrends/components).

---


### 2.10 Indicadores › Alineación Académica › Alineación Cursos (y pantallas ocultas relacionadas)

#### D.3 Indicadores › Alineación Académica › **Alineación Cursos**

| Dato | Valor |
|---|---|
| Menú | Indicadores › Alineación Académica › "Alineación Cursos" (app-sidebar.tsx) |
| URL | `/dashboard/indicators/course-alignment` (routes/web.php) |
| Pestaña del navegador | "Alineación Curricular \| Observatorio ISIL". Breadcrumb: Dashboard › Alineación Curricular (pages/DashboardCourseAlignment/CourseAlignmentIndicatorPage.tsx) |
| Permiso | Solo sesión iniciada (routes/web.php) |

##### Encabezado
Fuente: (components/Header/CourseAlignmentHeader.tsx)
- Sobretítulo: "Observatorio Tecnológico ISIL".
- Título: **"Conexión Curso–Tendencia (CCTC)"**.
- Descripción: "Indicador estratégico que mide la alineación entre cursos académicos y señales reales del mercado laboral."
- Insignias:
  - **"{N} vacantes"**: ofertas distintas de `job_offers` vinculadas a cualquier lenguaje, tecnología o metodología de **los cursos de la carrera elegida**, publicadas dentro del semestre (s1: 01-01 a 30-06; s2: 01-07 a 31-12 del año). Sin carrera elegida vale 0 (CourseCCTCIndicatorController.php `getGlobalMeta`).
  - **"{N} tendencias"**: registros de `entity_trends` del año para esas mismas entidades (ídem).
- Tarjeta "Enfoque estratégico" con 3 líneas: "✔ Detecta brechas entre cursos y mercado", "✔ Prioriza actualización curricular" y "✔ Integra datos laborales + tendencias". Debajo: "Basado en señales reales consolidadas del observatorio."
- Los botones "Cursos" / "Competencias" para cambiar de vista están **comentados** [PENDIENTE / OCULTO].

##### Filtros ("Filtro estratégico")
Fuente: (components/Filters/CourseAlignmentFilter.tsx; CourseCCTCIndicatorController.php `resolveParams`)
- **Carrera:** lista desplegable con la opción inicial "Seleccione una carrera" y luego las carreras **activas** en orden alfabético (`careers.active = 1`).
- **Buscador** "Buscar curso...": busca 500 ms después de dejar de escribir, sin distinguir mayúsculas ni tildes, por coincidencia parcial con el nombre del curso.
  - El buscador **solo filtra la tabla**. Los KPIs siempre se calculan sobre todos los cursos de la carrera (CourseCCTCIndicatorController.php `index`).
- **Año y semestre:** no hay controles en pantalla. Por defecto se usan **año 2026** (valor fijo en el código) y `s1`. Solo se pueden cambiar escribiendo `?year=` / `?period=` en la URL.

##### Mensajes de estado vacío
Fuente: (CourseAlignmentIndicatorPage.tsx)
- Sin carrera: "Selecciona una carrera para analizar la alineación curricular."
- Carrera sin cursos: "No se encontraron cursos asociados a esta carrera."

##### Cómo se calcula el estado de cada curso (CCTC)
Fuente: (app/Services/CCTCService.php `getCourses`). Para cada curso vinculado a la carrera (tabla `career_course`):

1. **Conexiones estructurales (0 a 3).** Se suma 1 por cada tipo de vínculo que tenga el curso en el catálogo:
   - al menos un **lenguaje** (`course_language`),
   - al menos una **tecnología** (`course_technology`),
   - al menos una **metodología** (`course_methodology`).

   | Conexiones | Estado |
   |---|---|
   | 0 | "No alineado" |
   | 1 | "Alineado" |
   | 2 | "Altamente alineado" |
   | 3 | "Estratégicamente alineado" |

   El estado depende **solo de cuántos tipos de vínculo tiene el curso**. No depende del mercado.
2. **Demanda.** Muestra "Demanda activa" si alguno de sus lenguajes, tecnologías o metodologías aparece en al menos una oferta publicada entre el 01-01 y el 31-12 del año. Si no, muestra "Sin demanda".
3. **Tendencias.** Muestra "Detectado" si alguna de sus entidades (mediante `market_entity_id`) tiene un registro en `entity_trends` de ese año. Si no, muestra "No detectado".
4. **Comp.** Cantidad de competencias de esa carrera vinculadas al curso (`competency_course`).

##### KPIs: cálculo exacto
Fuente: (CourseCCTCIndicatorController.php `index`; components/KPIs/CourseAlignmentKPI.tsx). T = total de cursos de la carrera, sin aplicar la búsqueda.

| KPI en pantalla | Fórmula |
|---|---|
| **"Demanda Laboral" {x}%** (texto inferior: "{A} de {T} cursos") | A = cursos con estado "Alineado", "Altamente alineado" o "Estratégicamente alineado", es decir, con **≥1 conexión estructural**. `market_rate = A / T × 100`. **Ojo:** a pesar del nombre, **no usa** la columna "Demanda" (ofertas reales); usa los vínculos del catálogo |
| **"Tendencias" {y}%** (texto inferior: "Alineación prospectiva") | B = cursos que tienen al menos una entidad (lenguaje, tecnología o metodología con `market_entity_id`) con **cualquier** registro en `entity_trends`, **de cualquier año**. `trend_rate = B / T × 100`. Puede diferir de la columna "Tendencias", que sí filtra por año |
| **Indicador circular {z}%** | `final_index = (market_rate + trend_rate) / 2`, redondeado a 1 decimal en el servidor y a entero en pantalla. Etiqueta: ≥75 "Excelente alineación" (verde); ≥50 "Alineación moderada" (ámbar); <50 "Baja alineación" (rojo). Texto inferior: "{A} de {T} cursos" |
| `gap_total` = T − A | Se calcula, pero **no se muestra** |

Con T = 0, todos los porcentajes valen 0.

##### Tabla de cursos
Fuente: (components/Table/CourseAlignmentTable.tsx)

| Columna | Contenido |
|---|---|
| Curso | Nombre en mayúsculas |
| Estado | Insignia: "Estratégicamente alineado" (verde), "Altamente alineado" (verde azulado), "Alineado" (azul) o "No alineado" (rojo) |
| Demanda | "Demanda activa" / "Sin demanda" |
| Tendencias | "Detectado" / "No detectado" |
| Comp. | Número de competencias vinculadas |
| IA | Botón **"IA"** (cambia a "Analizando..." mientras se envía) |

- La columna "Gap" está **comentada** [PENDIENTE / OCULTO].
- Hacer clic en la fila abre el panel lateral de detalle.

**Botón "IA"** (`POST /dashboard/courses/{id}/analyze-ai`):
- Encola el trabajo `AnalyzeCourseJob` y la pantalla muestra de inmediato "Análisis IA ejecutado correctamente". **Ese mensaje significa "encolado", no "terminado"**. Si el envío falla, aparece "Error ejecutando IA" (CourseAlignmentTable.tsx; CourseCCTCIndicatorController.php `analyzeWithAI`).
- El trabajo ejecuta el comando `curriculum:analyze-course` con búsqueda de versiones en línea activada. Tiene un límite de 600 s y 2 intentos (app/Jobs/AnalyzeCourseJob.php). El comando:
  - lee el **sílabo procesado** del curso, recortado a 20 000 caracteres; si tiene menos de 200, cancela con "El curso no tiene un sílabo procesado utilizable. Análisis cancelado.";
  - consulta **OpenAI**: `gpt-4o-mini` para el análisis y `gpt-5` para buscar en línea las versiones de los marcos (frameworks) que declara el sílabo;
  - guarda el resultado en `course_ai_recommendations` (app/Console/Commands/Curriculum/AnalyzeCourseMarketAlignment.php).
- Requiere que el proceso de cola (`queue:work`) esté corriendo.

##### Panel lateral del curso
Fuente: (components/Drawer/CourseDetailDrawer.tsx)
- Encabezado: nombre del curso y "Estado estratégico: {estado}".
- Se cierra con X, la tecla Esc o un clic fuera.
- Pestañas: **IA** (abre por defecto), **Empleo** y **Tendencias**. La pestaña "Brechas" existe en el código, pero **no tiene botón** [PENDIENTE / OCULTO].

**Pestaña IA** (`GET /indicators/course/{id}/ai`; tabs/CourseAITab.tsx). Muestra el **último** análisis guardado del curso.
- Mientras carga: "Cargando IA...". Sin análisis: "No existe análisis IA.".
- Botón **"Descargar PDF"**: imprime o guarda como PDF con el nombre `diagnostico-{curso}`.
- Contenido:
  - Insignias "Alineación:", "Tipo:", "Rol:" y "Obsolescencia:".
  - "Brechas críticas".
  - "Evaluación tecnológica": Relevancia, Pros / Contras, Uso en mercado.
  - "Recomendación estratégica": Qué, Dónde, Cómo.
  - "Análisis por sesiones": tabla con Sesión, Problema, Brecha, Recomendación y Prioridad (Alta / Media / Baja).
  - "Impacto esperado": Empleabilidad, Alineación, Valor.
  - Un recuadro rojo con el riesgo.
  - "Aplicabilidad real": Roles, Tareas.
  - Etiquetas "Tecnologías sugeridas", "Certificaciones" y "Metodologías".

**Pestaña Empleo** (`GET /indicators/course/{id}/recent-jobs`; tabs/CourseEmploymentTab.tsx; CourseCCTCIndicatorController.php `getRecentJobsByCourse`):
- **"Conexiones del curso"**: chips con los Lenguajes, Tecnologías y Metodologías vinculados al curso.
- **"Ofertas recientes"**: las 10 ofertas más recientes por fecha de publicación, de **cualquier año**, que mencionan alguna de esas entidades. Cada tarjeta muestra título, fecha, "empresa • ciudad, país", modalidad, tipo de empleo y salario ("💰 moneda mín - máx") si existe. Al hacer clic se abre la oferta original.
- Mensajes: "Cargando ofertas..." y "No hay ofertas recientes asociadas.".

**Pestaña Tendencias** (`GET /indicators/course/{id}/trends`; tabs/CourseTrendsTab.tsx; `getCourseTrends`):
- **"Tendencias detectadas"**: hasta 10 registros de `entity_trends` de las entidades del curso, del más reciente al más antiguo. Cada uno muestra nombre de la tendencia, entidad, "{año} Q{trimestre}", título de la fuente y una insignia de tipo (news, report, blog, academic…). Al hacer clic se abre la fuente.
- Mensajes: "Cargando tendencias..." y "No se detectaron tendencias recientes.".

**Pestaña Brechas (oculta)** (`GET /indicators/course/{id}/gaps`; tabs/CourseGapsTab.tsx):
- Mostraría "Análisis de brechas" con "Total entidades", "Con señal de mercado", "Brechas detectadas" y la lista "Entidades sin señal de mercado".
- El cálculo de "conectadas" mezcla posiciones de lista con identificadores, así que el resultado no es fiable [POR CONFIRMAR] (CourseCCTCIndicatorController.php `getCourseGaps`).

##### Vista "Competencias" de la misma pantalla [PENDIENTE / OCULTO]
Solo se llega escribiendo `?view=competencies` en la URL, porque el botón está comentado. Además, al cambiar la carrera en el filtro se vuelve a la vista de cursos, porque el filtro no conserva `view` (CourseAlignmentFilter.tsx).
- Título: "Alineación Estratégica por Competencia".
- Tabla con las columnas Competencia, Estado, Cursos asociados e Interpretación (components/Table/CompetenciesAlignmentTable.tsx).
- Regla de estado (CCTCService.php `getCompetencies`):
  - "Estratégicamente alineado" si algún curso de la competencia tiene **Demanda activa** y alguno tiene **Tendencia detectada**;
  - "Alineado" si tiene solo una de las dos;
  - "No alineado" en otro caso.
- La columna "Cursos asociados" mostrará siempre "Sin cursos asociados", porque el servicio no envía la lista de cursos (CCTCService.php).

##### Rutas declaradas sin implementar [PENDIENTE]
Las rutas `…/course-alignment/summary`, `/course/{id}/detail`, `/critical`, `/course/{id}/history`, `/career/{id}/competency-impact` y `POST /course/{id}/analyze` apuntan a métodos que **no existen** en CourseCCTCIndicatorController.php (routes/web.php). Además, el método `getCourseRecommendation` no tiene ruta.

---

#### D.4 "Alineación Competencias" (Perfil de Egreso) [PENDIENTE / OCULTO]

- La opción de menú está **comentada** en app-sidebar.tsx. La pantalla sigue accesible por URL: `/dashboard/indicators/pe-alignment` (routes/web.php; pages/DashboardAlignCompetence/PeAlignmentIndicatorPage.tsx).
- Pestaña del navegador: "Alineación del Perfil de Egreso | Observatorio ISIL". Breadcrumb "Alineación Perfil de Egreso".
- Título del encabezado: "Alineación de Programa de estudios con tendencias del sector" (components/Header/PeAlignmentHeader.tsx).
- Controles:
  - "Año de análisis" y "Semestre" ("Ene – Jun" / "Jul – Dic").
  - Selector "Carrera" con las opciones "Selecciona una carrera" y "Todas las carreras" (components/Filters/CareerFilter.tsx).
  - Botón **"Actualizar datos"** (texto en curso: "Actualizando..."; ayuda: "Ejecuta el recálculo de mercado y tendencias"). Ejecuta `pe:recalculate` para la carrera y el **año actual** (PeAlignmentIndicatorController.php `refreshData`).
  - Botón **"Obtener recomendación con IA"** (texto en curso: "Ejecutando IA..."). Ejecuta `career:recommend`; sin carrera elegida, responde "Carrera requerida" (`analyzeCareerWithAI`). Alertas: "Selecciona una carrera primero", "Análisis IA ejecutado correctamente", "Error al ejecutar IA" y "Error al actualizar datos".
- **Ponderaciones** ("Haz clic para editar ponderaciones" abre el modal "Metodología de cálculo"):
  - Campos "Demanda laboral" y "Tendencias y prospectiva"; botones "Cancelar" y "Aplicar metodología".
  - Las dos deben sumar 100%; si no, aparece "Las ponderaciones deben sumar 100%".
  - Por defecto valen 70% laboral y 30% tendencias (PeAlignmentIndicatorController.php `getWeights`/`updateWeights`).
- **Cálculo** (app/Console/Commands/Curriculum/RecalculatePeAlignment.php; PeAlignmentIndicatorController.php):
  1. Por cada competencia se cuentan las ofertas del año vinculadas a sus cursos (`job_count`) y las tendencias (`trend_count`).
  2. Las competencias se ordenan en **cuartiles** (NTILE 4). El puntaje de mercado y el de tendencia valen: Q1 = 1, Q2 = 0.75, Q3 = 0.5, Q4 = 0.25, o 0 si no hay datos.
  3. `final = peso_laboral × mercado + peso_tendencia × tendencia`.
  4. Nivel: ≥80 "Fuerte", ≥60 "Media", ≥40 "Débil", menos de 40 "Baja". Si ambos puntajes son 0, el nivel es "Crítica".
  5. KPIs: "Intensidad promedio — Demanda Laboral" y "Intensidad promedio — Tendencias" son promedios de los puntajes multiplicados por 100. El indicador "Intensidad de alineación" es el índice ponderado. Las etiquetas van de "Alta intensidad de alineación" a "Baja intensidad de alineación" (components/KPIs/PeAlignmentKpis.tsx).
- Tabla con las columnas "Competencias - Perfil de egreso", "Nivel de alineación" y "Recomendación IA" (components/Table/CompetencyTable.tsx).
  - El análisis IA por competencia (`curriculum:analyze-competency`) muestra "Analizando competencia" / "VERA IA está generando la recomendación..." y luego "Análisis generado" o "Error en análisis".
- Rutas de apoyo: `/pe-alignment/competencies/{careerId}`, `/competency/{id}/jobs`, `/competency/{id}/trends` y `/competency/{id}/courses` (routes/web.php). Las tres primeras apuntan a métodos que **no existen** (`competenciesByCareer`, `jobsByCompetency`, `trendsByCompetency`) [PENDIENTE]; solo `getCompetencyCourses` está implementado (PeAlignmentIndicatorController.php)

---

#### D.5 Otras rutas `dashboard/*` (routes/web.php, líneas 169-300)

| Ruta | Estado / función |
|---|---|
| `GET /dashboard/job-market/status` | Apunta a `JobMarketStatusController@index`, pero ese método **no existe**: la ruta falla [PENDIENTE]. La clase solo tiene `get()`, que las pantallas de Ranking de Tecnologías, Lenguajes y Certificaciones usan internamente para el bloque "estado del mercado laboral". Ese bloque incluye: total de ofertas, ofertas nuevas del mes (`created_at` ≥ día 1 del mes), antigüedad del histórico (desde la primera oferta), última inserción y última publicación (en texto relativo), ofertas del semestre por `created_at`, días cubiertos, promedio por día (ofertas ÷ días, redondeado) y desglose por fuente (total y última inserción) (app/Http/Controllers/Dashboard/JobMarketStatusController.php; RankingTecnologiasController.php) |
| `POST /dashboard/courses/{course}/analyze-ai` | Botón "IA" de Alineación Cursos (ver D.3) |
| `GET /indicators/course/{id}/trends`, `/gaps`, `/ai`, `/recent-jobs` | Pestañas del panel lateral de Alineación Cursos (ver D.3). Nota: estas rutas no llevan el prefijo `/dashboard` |
| `POST /dashboard/indicators/job-demand/rebuild-alignment` | Pertenece a "Demanda Ciudad" (otra parte del manual). Encola 8 normalizaciones geográficas y `jobs:build-alignment --truncate` (JobDemandGeoIndicatorController.php) |
| `/dashboard/indicators/pe-alignment/*` | Ver D.4 |
| `/dashboard/indicators/macro-trends/*` | Ver D.1 |

### 2.11 Dashboard Vera y asistente VERA IA

#### A.3. Dashboard Vera

##### A.3.1 Acceso y estructura
- **Menú:** "Dashboard Vera", el primer ítem de la barra lateral, visible para **todos** los usuarios con sesión (resources/js/components/app-sidebar.tsx).
- **URLs:**
  - `/dashboard` (o `/dashboard/`) abre el dashboard marcado como principal ("default"). Si no hay ninguno marcado, abre el primero creado. Si no existe ningún dashboard, muestra un error 404 "No hay dashboards" (app/Http/Controllers/DashboardController.php `index`).
  - `/dashboard/{slug}` abre un dashboard concreto. El *slug* es el nombre convertido a URL, por ejemplo "Mi Panel" pasa a `mi-panel` (DashboardController.php `show`).
  - `/dashboard_vera` carga la misma página pero **sin datos**. Probablemente falle al mostrarse porque la página necesita un dashboard activo **[POR CONFIRMAR]** (routes/web.php, resources/js/pages/dashboardLovable/DashboardLovable.tsx).
- **Ruta de navegación (breadcrumb):** "Dashboard". El título de la pestaña es el nombre del dashboard activo (DashboardLovable.tsx).
- **Diseño en dos columnas** (DashboardLovable.tsx):
  - **Izquierda:** la cabecera del dashboard y la cuadrícula de gráficos (widgets).
  - **Derecha (420 px):** el panel del asistente **VERA IA**. Tiene un ícono de ayuda que dice: "Este es el asistente VERA IA. Puedes escribir consultas como 'Top certificaciones en Perú 2025' y generará gráficos automáticamente."

##### A.3.2 Cabecera del dashboard
(resources/js/pages/dashboardLovable/components/DashboardHeader.tsx)

| Elemento | Qué hace / texto |
|---|---|
| Título (nombre del dashboard) | Al hacer clic se puede editar. Ayuda: "Este es el nombre del dashboard. Puedes hacer clic para editarlo." En edición: "Presiona Enter para guardar o Escape para cancelar." También se guarda al salir del campo. Si el nombre queda vacío o igual al actual, no se hace nada |
| Subtítulo | "Gráficos generados por el asistente VERA IA". Ayuda: "VERA analiza datos del mercado laboral y genera visualizaciones inteligentes según tus consultas." |
| Menú ⋮ (tooltip "Opciones del dashboard: renombrar o eliminar.") | Abre un diálogo con el nombre del dashboard y los botones "Renombrar", "Eliminar" y "Cancelar" |
| Pestañas de dashboards | Muestran un botón por cada dashboard, ordenados por fecha de creación. El activo aparece resaltado. Ayuda: "Puedes crear múltiples dashboards para organizar diferentes análisis y métricas." |
| "+ Nuevo" | Pide el nombre con la ventana del navegador "Nombre del nuevo dashboard" y crea el dashboard. Ayuda: "Crea un nuevo dashboard personalizado con gráficos generados por inteligencia artificial." |
| Botón ⟳ (tooltip "Actualiza todos los gráficos y vuelve a consultar los datos más recientes.") | Vuelve a ejecutar la consulta de **cada** gráfico (ver 3.5) |

**Crear y renombrar dashboards: reglas** (DashboardController.php `store`/`update`)
- El nombre es obligatorio, en texto, con un máximo de 255 caracteres.
- Puede haber nombres repetidos. Si el *slug* ya existe, el sistema agrega `-1`, `-2`, etc.
- Al crear o renombrar, se abre el dashboard en su nueva URL.

**Eliminar un dashboard** (DashboardHeader.tsx, DashboardController.php `destroy`)
- Se pide confirmación: "¿Eliminar dashboard?" / "Esta acción no se puede deshacer", con los botones "Sí, eliminar" y "Cancelar".
- **El dashboard principal (default) no se puede eliminar.** Si eliges "Eliminar" sobre él, la pantalla no hace nada. El servidor, además, responde "No se puede eliminar el dashboard principal".
- Al eliminar un dashboard se borran también todos sus gráficos. Después se abre el dashboard principal o, si no hay, el primero.

##### A.3.3 Cuadrícula de gráficos (widgets)
(resources/js/pages/dashboardLovable/components/DashboardLovableWidgets.tsx)
- La cuadrícula tiene 12 columnas. Mientras carga se ve "Cargando…".
- **Mover:** arrastra desde el ícono de puntos en la esquina inferior izquierda del gráfico (tooltip "Mover widget").
- **Redimensionar:** arrastra la esquina inferior derecha.
- La posición y el tamaño se guardan solos al soltar (DashboardWidgetController.php `update`).
- Un gráfico nuevo aparece arriba a la izquierda con tamaño 4×3 (DashboardWidgetController.php `storeFromTraining`).
- **Secciones (bloques de título):** son franjas de ancho completo con el título centrado, un control para moverlas ("Mover sección"), ✏️ para editar y 🗑️ para eliminar.
  - Editar: "Editar sección", con "Guardar" y "Cancelar". Valida "El título no puede estar vacío". Al guardar muestra "✅ Actualizado". Si falla: "No se pudo editar la sección".
  - Eliminar: "¿Eliminar sección?" / "Esta acción no se puede deshacer", con "Eliminar" y "Cancelar". Al terminar muestra "🗑️ Eliminada". Si falla: "No se pudo eliminar la sección".
  - **[PENDIENTE]** El diálogo de alta "Nueva Sección" (campo "Título del bloque", placeholder "Ej. Tecnologías más demandadas", validación "Debes ingresar un título") existe en el código, pero **no hay ningún botón que lo abra**: la cabecera no usa `onAddSection` (DashboardLovableWidgets.tsx, DashboardHeader.tsx).

##### A.3.4 Tarjeta de gráfico (widget)
(resources/js/pages/dashboardLovable/components/DashboardAI/WidgetCard.tsx)
- **Contenido:**
  - **Título**, en mayúsculas: es el texto de la consulta (prompt) del entrenamiento que originó el gráfico (DashboardWidgetController.php).
  - **Resumen breve** generado por IA: una frase de hasta 12 palabras (AITrainingController.php `finalizeTraining`).
  - **Gráfico** de tipo Barras, Líneas o Circular.
- **Cómo se arma el gráfico:** los datos son el resultado de una consulta a la base de datos del Observatorio (el SQL guardado en el entrenamiento).
  - **Categoría:** la primera columna llamada `name`, `modality`, `language`, `technology`, `methodology`, `career_name`, `category`, `country`, `region`, `city`, `company` o `workload`. Si no hay ninguna, se usa la primera columna de texto.
  - **Valores:** las columnas numéricas. Las categorías vacías se muestran como "Sin nombre".
  - **Gráfico circular:** el porcentaje de cada porción es su valor dividido entre la suma de la primera columna numérica de las categorías visibles (WidgetCard.tsx).
- **Menú ⚙️** (tooltip "Opciones del widget"):

| Opción | Qué hace |
|---|---|
| "📊 Exportar Excel" | Descarga `<título>.xlsx` con todas las filas, en una hoja "Datos". Si no hay datos: "No hay datos para exportar" |
| "📄 Exportar PDF" | Descarga `<título>.pdf` (A4) con el título y una tabla de todas las columnas. Si no hay datos: "No hay datos para exportar." |
| "🔁 Cambiar tipo" | Abre "Cambiar tipo de gráfico" con las opciones "Barras", "Líneas" y "Circular", y los botones "Guardar" y "Cancelar". Si sale bien: "Gráfico actualizado" / "Tipo de gráfico cambiado correctamente.". Si falla: "No se pudo actualizar el gráfico" |
| "🗑️ Eliminar" | Pide "¿Eliminar tarjeta?" / "Esta acción no se puede deshacer.", con "Sí, eliminar" y "Cancelar". Si sale bien muestra "Eliminada" / "La tarjeta fue eliminada correctamente" y recarga la página. Si falla: "No se pudo eliminar la tarjeta" |
| "🎛️ Filtrar" | Muestra "Seleccionados: X / Y", los botones "✔ Todos" y "✖ Ninguno", y una casilla por categoría. Con "Aplicar" el filtro se aplica en pantalla y luego se pregunta "Filtro aplicado" / "¿Deseas guardar el filtro para este widget?". "Guardar" lo deja fijo para el gráfico (mensaje "Guardado" / "Filtro persistente aplicado.") y "Solo aplicar" lo usa solo en esta vista (DashboardWidgetController.php `saveFilters`) |

- **[PENDIENTE]** Hay opciones comentadas en el código: "🎨 Editar colores" (Fondo, Texto, Borde, Gráfico), "🧠 Segmentar datos" y un control de color. El servidor sí admite cambiar colores (`updateColor`, campos `primary`, `bg`, `text`, `border`), pero la pantalla no lo ofrece (WidgetCard.tsx, DashboardWidgetController.php).
- **Al eliminar una sección de tipo "heading"** se eliminan también los gráficos agrupados en ella (DashboardWidgetController.php `destroy`).

##### A.3.5 Actualizar datos (botón ⟳)
(resources/js/pages/dashboards/DashboardContext.tsx, DashboardWidgetController.php `refresh`)
- El sistema vuelve a ejecutar la consulta SQL de cada gráfico, **de a 3 gráficos a la vez**, y guarda los resultados nuevos. Si un gráfico falla, los demás se actualizan igual.
- Los gráficos se recalculan solo al pulsar este botón. Al abrir el dashboard se muestran los últimos datos guardados.
- Mensajes del servidor: "Widget no encontrado en este dashboard", "Este widget no tiene SQL para recalcular" y "No se pudo resolver el SQL del widget". La pantalla no los muestra, solo los registra en la consola (DashboardContext.tsx).

##### A.3.6 Asistente VERA IA (panel derecho)
(resources/js/pages/dashboards/components/AiChat/AiChatView.tsx, useAiChatLogic.tsx)
- **Encabezado:** "🤖 VERA | Observatorio ISIL". Ayuda: "VERA es el asistente de inteligencia del Observatorio. Puede generar análisis, consultas SQL y gráficos a partir de tus preguntas."
- **Saludo inicial:** "👋 Hola, soy **VERA**, tu analista del Observatorio ISIL. ¿Qué información deseas analizar hoy?". Si hay conversación previa en la sesión, se muestra "🧠 Historial cargado desde el Observatorio IA." seguido de los mensajes.
- **Selector de modo**: un botón que alterna entre dos modos. Ayuda: "Modo Conversar responde preguntas generales. Modo Consultar datos ejecuta consultas estructuradas sobre la base de datos del observatorio." El modo elegido se recuerda en el navegador.
  - "💬 Estás en modo Conversando". Placeholder: "Escribe tu mensaje…".
  - "🧠 Estás en modo Consultando Datos". El panel se ve en verde. Placeholder: "Describe la consulta que quieres enseñar a VERA…".
- **Enviar:** pulsa Enter o el botón "Enviar". Shift+Enter hace un salto de línea. Mientras espera, se muestra "Pensando...".
- **Sugerencias automáticas:** al escribir 3 caracteres o más aparece una lista de hasta 8 consultas guardadas. Solo se sugieren entrenamientos activos, finalizados y en etapa "final", que coincidan por prompt, descripción o tema. Al elegir una, el texto se copia en el cuadro, pero se envía como un mensaje normal, no como una ejecución del entrenamiento guardado (AiChatView.tsx, DashboardAIController.php `suggestions`).
- **Botón "+" (Acciones)**, tooltip "Acciones adicionales: activar voz, grabar audio o adjuntar archivos.":
  - "Voz" (🔊) activa o desactiva la lectura en voz alta de las respuestas. **Viene activado por defecto** (useAiChatLogic.tsx).
  - "Grabar" (🎤/⏹) graba tu voz, la transcribe con IA ("🎙️ Transcribiendo tu audio...") y la envía como mensaje. Errores posibles: "🎙️ Tu navegador no permite grabar audio." y "⚠️ No se detectó voz.".
  - **[PENDIENTE]** "Adjuntar archivo" está comentado. Si se habilitara, el límite sería de 10 MB ("⚠️ El archivo supera los 10 MB."). Los selectores "Forzar nueva" y "⏸️ Pausar/⏹️ Detener" de audio también están comentados (AiChatView.tsx).

###### a) Modo Conversando
(DashboardAIController.php `chat`)
- VERA responde con IA (OpenAI `gpt-4o-mini`). Solo trata temas de empleabilidad, educación superior, mercado laboral, tendencias tecnológicas, roles demandados, lenguajes, tecnologías y metodologías.
- Si la pregunta está fuera de esos temas, VERA responde que no está dentro del alcance del Observatorio.
- Las respuestas son breves, de unos 400 *tokens* como máximo.
- VERA tiene en cuenta los **últimos 5 intercambios** de la sesión, y el sistema conserva solo los 5 más recientes.
- Errores: "⚠️ No fue posible procesar la consulta. Intenta de nuevo.", "⚠️ Error en modo contextual.", "💥 Error al conectar con la IA.".

###### b) Modo Consultando Datos ("enseñar" una consulta a VERA)
Este flujo crea los gráficos del dashboard (useAiChatLogic.tsx, AITrainingController.php):
1. **Generación de la consulta:** VERA convierte tu pregunta en una consulta SQL (solo SELECT) sobre las tablas de carreras, cursos, ofertas laborales, ciudades, entidades de mercado (lenguajes, tecnologías, certificaciones, metodologías) y tendencias. Usa OpenAI `gpt-4o-mini`.
   - Reglas de interpretación: "este año" o "actualmente" se toma como el año de publicación actual de las ofertas. El país se filtra por el texto exacto del país de la oferta y la ciudad por coincidencia parcial. Si no se menciona año, no se filtra por fecha.
   - Mensaje: "🧩 Consulta generada automáticamente con contexto real e historial.". Si VERA no puede generar la consulta: "⚠️ No se pudo generar una consulta válida con la estructura actual.".
2. **Validación automática:** el sistema ejecuta la consulta.
   - Si funciona: "✅ Consulta válida (N filas)".
   - Si falla: "⚠️ Error validando SQL.". Antes de ejecutar se verifica que las tablas existan.
3. **Resumen:** la IA redacta una frase de hasta 12 palabras sin cifras largas, porcentajes ni fechas. Si "Voz" está activa, se agrega "🔊 [Escuchar resumen](…)". Además se genera un Excel en el servidor, pero el enlace de descarga está oculto **[PENDIENTE]**.
   - Rechazos posibles: "SQL no validada o inexistente.", "SQL inválido o no es SELECT.", "❌ Operación SQL no permitida." (si contiene INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, EXEC, CREATE, REPLACE o MERGE), "La consulta no devolvió resultados.", "Error al generar resumen IA".
4. VERA pregunta "💾 ¿Deseas guardar este entrenamiento?" con el botón "💾 Guardar entrenamiento", que muestra "Guardando..." mientras trabaja.
   - Si se guarda bien: "💾 Entrenamiento guardado correctamente. Ahora puedes generar un gráfico.".
   - Si falla: "💥 Error al guardar el entrenamiento.".
5. Aparecen los botones **BAR, LINE, PIE, RADAR**. Al pulsar uno se muestra "📊 Generando gráfico con los datos del entrenamiento...", se crea el gráfico **en el dashboard abierto** y aparece "📊 Widget creado correctamente". La lista de gráficos se recarga sin recalcular los demás.
   - Errores: "SQL no validada", "Entrenamiento inválido", "Error inesperado", "💥 Error al generar gráfico.".
   - **Observación:** la tarjeta solo dibuja barras, líneas y circular. Un gráfico creado como **RADAR** probablemente se vea vacío **[POR CONFIRMAR]** (WidgetCard.tsx no contempla "radar").

- **Registros que crea este flujo** (AITrainingController.php):
  - Cada consulta enviada en este modo crea un entrenamiento borrador con tema "Entrenamientos SQL Automáticos" y etapa `draft`.
  - Al pulsar "Guardar entrenamiento" se crea otro registro con tema "Análisis Observatorio" y etapa `final`. Solo los de etapa `final` aparecen como sugerencias.
- **[PENDIENTE]** Existe un selector de gráfico antiguo, "📊 Elige el tipo de gráfico" / "Selecciona cómo deseas visualizar los datos obtenidos" / "— Selecciona tipo de gráfico —" / "Crear gráfico", con el error "Error creando el gráfico. Revisa la consola.". Envía a una dirección que no existe (`/dashboard/widgets/from-training`) y en la práctica no se muestra (ChartSelector.tsx, routes/web.php).

---


### 2.12 IA VERA › Entrenamiento IA

#### A.4. IA VERA > Entrenamiento IA

- **Menú:** "IA VERA" > "Entrenamiento IA". Solo es visible y accesible con el permiso `administrar` (app-sidebar.tsx, routes/web.php).
- **URL:** `/admin/ai-trainings`. Breadcrumb "Entrenamiento IA". Título: "Entrenamientos de IA" (resources/js/pages/AITraining/AITrainingsIndex.tsx).
- **Qué es:** el catálogo de consultas, o "entrenamientos", de VERA. Incluye las que se crean desde el chat (sección 3.6 b).

##### A.4.1 Filtros
| Filtro | Tipo | Comportamiento |
|---|---|---|
| Búsqueda, placeholder "Buscar prompt o descripción..." | texto | Busca coincidencias parciales en prompt, descripción e intérprete (AITrainingController.php `index`) |
| Tema | lista | "Todos los temas" o uno de los temas existentes |
| "Buscar" | botón | Aplica los filtros. No se filtra al escribir |

##### A.4.2 Tabla
Muestra 10 por página, ordenados por tema y luego del más nuevo al más antiguo (AITrainingController.php). Abajo hay paginación numérica.

| Columna | Significado |
|---|---|
| "Acciones" | Editar (lápiz), Duplicar (copiar) y Eliminar (papelera) |
| "Tema" | Tema o categoría |
| "Prompt" | La pregunta o instrucción, recortada si es larga |
| "Componente" | Componente asociado, o "-" si no tiene |
| "Activo" | Botón "Activo"/"Inactivo": activa o desactiva el entrenamiento con un clic |
| "IA" | Botón "IA ON"/"IA OFF": activa o desactiva la respuesta con IA con un clic |
| "Creado" | Fecha en formato dd/mm/aaaa hh:mm (app/Models/AITraining.php) |

- Si la tabla está vacía: "No hay entrenamientos configurados.".
- **Duplicar:** crea una copia con el prompt "[Copia] …" y la deja **inactiva**. Mensaje: "✅ Copia creada correctamente." (AITrainingController.php `duplicate`, AITrainingsIndex.tsx).
- **Eliminar:** pide "¿Eliminar este entrenamiento?". Si falla: "No se pudo eliminar.". Los gráficos ya creados a partir de él no se borran **[POR CONFIRMAR]**.
- Errores: "Error al cambiar estado activo.", "Error al cambiar estado de IA.", "No se pudo duplicar.", "No se pudo cargar la página.".
- **Efecto del estado "Activo":** solo los entrenamientos activos, entrenados y en etapa `final` aparecen como sugerencias en el chat de VERA (DashboardAIController.php `suggestions`). El indicador "IA ON/OFF" no tiene un efecto visible en el chat actual **[POR CONFIRMAR]**.

##### A.4.3 Formulario "🧠 Nuevo Entrenamiento IA" / "✏️ Editar Entrenamiento IA"
Se abre con el botón "+ Nuevo Entrenamiento" o con el ícono de editar (AITrainingModal.tsx). Reglas del servidor en AITrainingController.php `store`/`update`.

| Campo | Tipo | Obligatorio | Límite / placeholder |
|---|---|---|---|
| "Tema" | texto | Sí | máx. 150, placeholder "Ejemplo: Tendencias tecnológicas" |
| "Prompt principal" | texto largo | Sí | "Instrucción o pregunta que entrenará la IA..." |
| "Intérprete (controlador/método)" | texto | Sí | "Ejemplo: aiTrainerController@analyzeTrends" |
| "Componente React asociado" | texto | No | máx. 150, "Ejemplo: TrendChart, SkillRadar" |
| "Descripción" | texto largo | No | "Breve descripción del entrenamiento..." |
| "Prompt de explicación (opcional)" | texto largo | No | "Prompt para que la IA explique los resultados o tendencias...". **Este campo no se guarda**: el servidor no lo incluye en la validación **[PENDIENTE]** |
| "Tags (separados por coma)" | texto | No | "ia, entrenamiento, tendencias" |
| "Activo" | casilla | – | marcado por defecto |
| "Usa IA" | casilla | – | marcado por defecto |

- Botones "Cancelar" y "Guardar" ("Guardando..." mientras trabaja).
- Si hay algún error, incluidos los de validación, solo aparece "❌ No se pudo guardar el entrenamiento IA." (AITrainingModal.tsx).
- Los entrenamientos creados desde aquí no tienen consulta SQL asociada, así que no sirven para generar gráficos desde el chat (DashboardWidgetController.php exige `sql_training_id` validado).

---


### 2.13 Pantallas con URL pero sin opción en el menú

#### F.11 Pantallas con ruta pero sin menú (ocultas o desvinculadas)

Ninguna aparece en el menú lateral (sidebar). Se documentan para que el administrador sepa que existen. **Su uso no forma parte del flujo normal.**

##### `/scrapings` — Scrapings (configurador de extracción web)

- **Cómo se llega:** solo escribiendo la URL.
- **Permisos:** basta con iniciar sesión; no exige `administrar` (web.php).
- **Migas:** «Scrapings» (P/Scrapings/Index.tsx).
- **Botón:** «+ Nuevo Scraping» (P/Scrapings/Index.tsx).
- **Columnas:** Acciones («Editar», «Eliminar») · Gestionar («Campos», «Backups») · ID · Nombre · URL base (P/Scrapings/Index.tsx).
- **Eliminar:** confirmación nativa «¿Eliminar scraping <nombre>?»; si falla: «Error al eliminar» (P/Scrapings/Index.tsx).

**Modal «Nuevo Scraping» / «Editar Scraping»** (P/Scrapings/ScrapingModal.tsx; C/ScrapingController.php):
- Campos:
  - «Nombre», obligatorio, máx. 255, «Ej: Cursos ISIL».
  - «URL base», obligatoria, debe ser una URL válida, máx. 255, «https://isil.pe».
- Los errores de validación de Laravel aparecen debajo de cada campo.
- Al editar aparecen más botones:
  - «Ejecutar Scraping» («Ejecutando...» mientras trabaja). Envía la configuración a un microservicio externo de *scraping* (variable `SCRAPER_URL`, por defecto `http://127.0.0.1:8000/scrape`).
    - Si el scraping no tiene campos configurados, responde «❌ No hay campos configurados para este scraping».
    - Si falla: «❌ Error al ejecutar scraping».
  - Con resultados en pantalla, aparecen además:
    - «Descargar Excel», que genera `<nombre>-resultados.xlsx` en el navegador.
    - «Guardar en Backup», que muestra «✅ Se guardaron N registros en Backup» o «❌ Error al guardar backups».
  - Sin resultados, esos botones avisan: «⚠️ No hay datos para exportar» o «⚠️ No hay resultados para guardar».

**`/scrapings/{id}/fields` — Campos del scraping** (P/ScrapingFields/Index.tsx; P/ScrapingFields/Modal.tsx; C/ScrapingFieldController.php):
- **Columnas:** Acciones · Nombre · Padre · Tipo · Valor · Atributo.
- **Modal «Nuevo Campo» / «Editar Campo»:**
  - «Nombre»: obligatorio, máx. 255.
  - «Tipo de Selector»: ID, Clase, Etiqueta (tag), Atributo, Texto o CSS Selector; obligatorio.
  - «Valor del Selector»: obligatorio, máx. 255.
  - «Atributo (opcional)»: máx. 50, «Ej: href, src, title».
  - «Ruta (Path)»: máx. 255.
  - «Campo Padre»: «-- Sin padre --» o un campo existente.
- **Eliminar:** confirmación «¿Eliminar campo <nombre>?».

**`/scrapings/{id}/backups` — Backups** (P/Backups/Index.tsx; C/BackupController.php):
- **Título:** «📦 Backups de <nombre>». Si no hay datos: «⚠️ No hay backups todavía».
- **Columnas:** Row ID, una columna por cada dato guardado y Revisado.
- La columna Revisado tiene un botón «Marcar» / «✅ Revisado» que alterna el estado. Si falla: «❌ No se pudo actualizar el estado».
- **Botón:** «Exportar Excel».
- **[POR CONFIRMAR: posible defecto]** Las rutas de alternar y eliminar reciben `{scraping}` y `{backup}`, pero el método solo declara `$id`, así que podría estar recibiendo el id del scraping en lugar del backup (web.php; C/BackupController.php).

**Archivo sin uso:** `P/Scrapings/Show.tsx` («Nombre del campo», «Selector CSS», «Path») no lo usa ningún controlador. **Pendiente.**

##### `/scraping/{source}/results` — Resultados Web

- **Cómo se llega:**
  - Solo escribiendo la URL con el id de una fuente (requiere `administrar`) (web.php).
  - Las rutas de «Fuentes» (`/scraping-sources`), que presumiblemente enlazaban aquí, están **comentadas** (web.php). Las migas apuntan a «Scraping» → `/scraping-sources`, que no responde (P/Scraping/WebResults/Index.tsx).
- **Título:** «Resultados Web – <fuente>»; 25 por página (P/Scraping/WebResults/Index.tsx; C/ScrapingWebResultController.php).
- **Botón superior:** «Procesar todos», con la confirmación «¿Procesar todos los enlaces pendientes?».
  - Envía a la cola cada enlace en estado `pending` (`ProcessWebResultJob`).
  - Mensajes: «Trabajo enviado. Procesando en background…» o «No se pudo iniciar el procesamiento».
- **Buscador:** «Buscar enlace...» (busca en la URL).
- **Columnas:** # · Enlace · Estado (pendiente, con ícono giratorio; `completed` en verde; `error` en rojo) · Fecha · Acciones (íconos: procesar uno, ver, editar, eliminar).
- **Tabla vacía:** «No hay resultados.».
- **Modales:**
  - «Detalle del enlace».
  - «Editar Resultado», con los campos URL (obligatorio), Categoría (máx. 255) y Estado (`pending`, `completed` o `error`), y los botones «Cancelar» y «Guardar».
- **Mensajes emergentes:** «Procesamiento iniciado», «No se pudo procesar», «Actualizado», «Error al actualizar», «Eliminado», «No se pudo eliminar».
- **Qué hace el procesamiento:** descarga la página del enlace y la clasifica con OpenAI en una categoría: lenguaje, framework, cloud, devops, ai, data, security, methodology u other (app/Jobs/ProcessWebResultJob.php).

---


---

## 3. ESTADOS Y FLUJOS

### 3.1 Estados de las entidades principales

| Entidad | Estados posibles | Qué los cambia | Detalle |
|---|---|---|---|
| **Sílabo** | `pending` (ámbar), `processing` (azul), `processed` (verde), `failed` (rojo), `superseded` (gris, «reemplazado»), `ignored` (gris), `pending_link` («pendiente de vinculación», naranja) | Subida de PDF, llegada desde silabos_v2, proceso en segundo plano, vinculación manual o «No vincular» | Tabla completa en 2.4 › «Estados de un sílabo» (app/Services/Syllabus/SyllabusIngestService.php; app/Jobs/ProcessSyllabusJob.php; app/Jobs/ProcessIngestedSyllabusJob.php) |
| **Curso (alineación CCTC)** | «No alineado», «Alineado», «Altamente alineado», «Estratégicamente alineado» | Cantidad de tipos de vínculo del curso (lenguaje, tecnología, metodología), que a su vez depende del sílabo vigente procesado | 2.10 › «Cómo se calcula el estado de cada curso (CCTC)» (app/Services/CCTCService.php) |
| **Lenguaje / Tecnología / Metodología** | Activo / Inactivo | Interruptor en su pantalla de catálogo. Todo registro nuevo nace activo | 2.3 › F.1 (C/LanguageController.php; C/TechnologyController.php; C/MethodologyController.php) |
| **Entidad de mercado** | Origen `ai_generated`, estado `candidate`, marca de tendencia `has_trend` | El botón «Detectar nuevos … con IA» crea candidatos. La marca de tendencia se cambia con el interruptor de Entidades | 2.7 › B.0.8; 2.3 › F.7 (app/Console/Commands/getTrends/Discover*GapsCommand.php; C/MarketEntityController.php) |
| **Fuente de datos (Comandos Fuentes)** | Último estado: (vacío = pending), running, success, failed. Conexión: unknown, ok, failed | Cada ejecución de un comando de recolección | 2.6 › «Estados de una fuente» (app/Services/SourceStatusService.php) |
| **Topic IA** | Activo / Inactivo. Estado de ejecución consultable («status») | Interruptor, «Reactivar», «Ejecutar» | 2.5 › F.10 (C/TopicsIAController.php; app/Models/TrendTopic.php) |
| **Entrenamiento IA (VERA)** | Activo / Inactivo; IA activada / desactivada | Interruptores de la tabla de Entrenamiento IA | 2.12 (C/AI/AITrainingController.php `toggleActive`, `toggleAI`) |
| **Ponderaciones de ranking** | Una ponderación activa por contexto y un historial de las anteriores | «Aplicar metodología» / «Aplicar ponderaciones» | 2.7 › B.0.7 (tabla `ranking_weights`) |
| **Estado del scraping (modal "Datos Generales")** | «Scraping en ejecución», «Última ejecución fallida», «Datos desactualizados» (más de 24 h), «Sistema actualizado», «Sin información» | Ejecuciones de los recolectores | 2.7 › B.0.6 (app/Services/ScrapingStatusService.php) |

### 3.2 Flujo de punta a punta

**Flujo A: del mercado laboral al indicador**

1. **TI** ejecuta los recolectores de ofertas: portales de empleo y APIs. Hoy se lanzan a mano por consola, porque el programador de tareas probablemente no corre (ver 5.3).
   - Cada ejecución actualiza su fila en **Comandos Fuentes** (2.6).
   - Las ofertas quedan en **Datos › Empleos** (2.5).
2. El **administrador** mantiene los catálogos en **Habilidades** (2.3): activa o desactiva lenguajes y tecnologías, revisa las entidades `candidate` creadas por la IA y gestiona los roles tech y las competencias.
3. **TI**, o el botón de cada indicador, ejecuta los recálculos:
   - «Recalcular seniority»;
   - «Actualizar alineación laboral»;
   - «Actualizar datos laborales»;
   - «Detectar nuevos … con IA».
4. **Cualquier usuario con sesión** consulta los **Indicadores** (2.7 a 2.10):
   - elige Año y Semestre;
   - ajusta las ponderaciones;
   - abre la evolución;
   - exporta a Excel.

**Flujo B: del sílabo a la alineación del curso**

1. Un sílabo llega por una de dos vías:
   - **(a)** se aprueba en silabos_v2 (estado 5) y llega solo al observatorio;
   - **(b)** el administrador sube el PDF en **Académico › Silabos**.
2. El observatorio decide si el sílabo le interesa y si es la versión vigente. Lo busca por el «Código en silabos_v2» del curso.
   - Si ningún curso tiene ese código, el sílabo queda «pendiente de vinculación» y aparece en el contador del menú.
3. El **administrador** resuelve los pendientes en **Vinculación de cursos** con «Curso del observatorio» o «No vincular». También puede asignar el código en **Cursos ISIL**.
4. En segundo plano, la IA extrae lenguajes, tecnologías y metodologías y actualiza el curso. La tabla de Silabos se refresca en vivo.
5. La **Alineación Cursos** se recalcula sola, porque se calcula al consultar.
   - La alineación por competencias (PE, oculta) requiere «Actualizar datos» o el comando `pe:recalculate`.

Detalle completo en 5.2 (README.md; docs/produccion/PASOS_PRODUCCION.md).

**Flujo C: dashboard con VERA**

1. El administrador crea consultas validadas en **IA VERA › Entrenamiento IA**.
2. Cualquier usuario crea dashboards en **Dashboard Vera** y agrega gráficos a partir de esos entrenamientos o del chat VERA en modo «Consultando Datos».
3. Desde cada gráfico puede filtrar, cambiar el tipo de gráfico, exportar a Excel o PDF y recalcular con ⟳ (2.11).

---

## 4. ROLES Y PERMISOS


### 4.1 Permisos existentes
Los crea el instalador inicial (database/seeders/PermissionsSeeder.php):

| Permiso | Uso real en el sistema |
|---|---|
| `administrar` | **Es el único permiso que el sistema verifica.** Habilita todos los menús de administración y sus pantallas (routes/web.php, app-sidebar.tsx) |
| `scrapings.ver` | Se crea, pero ninguna ruta ni menú lo verifica |
| `usuarios.ver` | Se crea, pero no se verifica |
| `roles.ver` | Se crea, pero no se verifica |

### 4.2 Roles iniciales
(PermissionsSeeder.php)
- **Administrador:** tiene todos los permisos.
- **Asistente:** solo tiene `scrapings.ver`, así que en la práctica ve lo mismo que un usuario sin rol.
- Usuarios de prueba creados por el instalador: `admin@gmail.com` (Administrador) y `asistente@gmail.com` (Asistente).

### 4.3 Qué ve cada usuario en el menú lateral
(app-sidebar.tsx; el servidor envía la lista de permisos del usuario en cada página, app/Http/Middleware/HandleInertiaRequests.php)

| Sección del menú | Sin `administrar` | Con `administrar` |
|---|---|---|
| "Dashboard Vera" | Sí | Sí |
| "Indicadores" (Tecnologías, Lenguajes, Certificaciones, Nivel Profesional, Empresas, Demanda Ciudad, Modalidad Laboral, Tendencias Macro, Comandos Fuentes, Alineación Cursos) | Sí, se muestra | Sí |
| "Académico", "Habilidades", "Datos", "IA VERA", "Administración" | No | Sí |

- "Comandos Fuentes" (`/sources`) aparece en el menú de todos, pero exige `administrar`, así que un usuario sin ese permiso recibe un error 403 (app-sidebar.tsx, routes/web.php).
- El contador naranja de "Vinculación de cursos" solo se calcula para quien tiene `administrar` (HandleInertiaRequests.php).

### 4.4 Funciones que NO exigen permiso (solo sesión iniciada)
Según routes/web.php y routes/api.php:
- **Todo Dashboard Vera:** ver, crear, renombrar y eliminar dashboards, y crear, mover, filtrar, recalcular y eliminar gráficos y secciones. Cualquier usuario con sesión puede hacerlo, incluso sin rol.
- **Indicadores:** las pantallas y datos de `/dashboard/indicators/...`, rankings de tecnologías, lenguajes y certificaciones, `/ranking-carreras`, macro-tendencias, empresas, demanda geográfica, nivel profesional y alineación de cursos.
- **Scrapings:** `/scrapings` y sus campos y respaldos no tienen el permiso `administrar` en la ruta.
- **Rutas sin ningún control de sesión:**
  - El chat y el entrenamiento de VERA (`/api/ai/chat`, `/api/ai/suggestions`, `/api/ai/training/start|test|finalize`, voz y archivos).
  - `/unauthorized`.
  - Una ruta de prueba `/__probe`.
- **Usuarios por API:** `/api/users...` (listar, crear, editar, eliminar y asignar roles) solo exige sesión, no el permiso `administrar` (routes/api/users.php).

---


**Dónde se administran los accesos**

- **Administración › Usuarios** (`/users`): crear usuarios y asignarles roles.
- **Administración › Roles** (`/roles`): crear roles y asignarles permisos.

Ambas pantallas exigen `administrar` (routes/web.php). Ver 2.2.

**Hallazgos de seguridad verificados en el código** (deben revisarse con TI antes de publicar el manual):

- Las rutas `/api/ai/chat`, `/api/ai/suggestions`, `/api/ai/training/start|test|finalize`, `/api/ai/voice/*`, `/api/ai/file/analyze` y `/api/courses/list` están **fuera** del grupo `auth:sanctum` y no tienen ningún middleware de autenticación (routes/api.php; bootstrap/app.php, grupo `api`). Quien conozca la URL puede usar el chat VERA, que consulta la base de datos y genera costo en OpenAI.
- `/api/users` (listar, crear, editar, eliminar y asignar roles) solo exige sesión (`auth:sanctum`), **no** el permiso `administrar` (routes/api.php; routes/api/users.php). Así, un usuario sin rol podría gestionar usuarios y roles por esa vía. Esto no se probó en ejecución **[POR CONFIRMAR]**.

---

## 5. REGLAS DE NEGOCIO (candidatas a "Lineamientos")

### 5.1 Restricciones

**Catálogos (Habilidades)**

- No se valida que el nombre sea único en ningún catálogo (2.3 › F.1).
- La eliminación es física y no se puede deshacer («Esta acción no se puede deshacer.») (P/languages/Index.tsx).
- No hay regla que impida eliminar registros con datos relacionados (2.3 › F.1).
- Al eliminar una **Entidad**, se borran también sus certificaciones (2.3 › F.7) **[POR CONFIRMAR]**.

**Cursos y sílabos**

- El «Código en silabos_v2» de un curso es **único**: dos cursos no pueden tener el mismo código (README.md; C/CourseController.php).
- Al eliminar un curso, sus sílabos se conservan sin curso (README.md; 2.4 › E.3).
- Un sílabo de silabos_v2 no se duplica: se identifica por modelo + id (2.4 › E.5).
- Entre versiones gana la mayor; un sílabo ISIL nunca reemplaza a uno SCL (2.4 › E.5).
- Un PDF subido para un curso que ya tiene sílabo vigente de silabos_v2 no modifica el curso (2.4 › «Estados de un sílabo»).
- Cambiar el **nombre** de una carrera cambia su *slug* y rompe el mapeo con silabos_v2 (2.4 › E.2) **[POR CONFIRMAR]**.

**Ponderaciones**

- Deben sumar 100 % («Las ponderaciones deben sumar 100%» / «Las ponderaciones deben sumar 1.00»).
- Al cambiarlas se aplican a **todos los usuarios** y queda el historial (2.7 › B.0.7).
- Por defecto valen 70 % laboral y 30 % tendencias.

**Período de análisis**

- Los indicadores usan semestres: «Ene – Jun» (1 ene a 30 jun) y «Jul – Dic» (1 jul a 31 dic). Los años disponibles en los selectores son 2025 y 2026 (2.7 › B.0.3).
- El «Promedio diario» divide entre todos los días del semestre, incluidos los que aún no transcurren (2.7 › B.0.6).

**Conteos**

- En Nivel Profesional y Demanda Ciudad, una misma vacante puede contar para varias carreras (2.8 › C.0).
- La insignia «vacantes analizadas» y el dato «Ofertas analizadas» del modal "Datos Generales" usan fechas distintas y no coinciden (2.7 › B.0.6).
- Alineación Cursos: el KPI «Demanda Laboral» **no usa ofertas reales**, sino los vínculos del curso con el catálogo (2.10 › KPIs).

**Usuarios**

- El formulario de usuario nuevo trae precargada la contraseña `isil2026` (2.2) **[POR CONFIRMAR]**.

### 5.2 Integración automática con silabos_v2


#### En lenguaje sencillo: ¿qué pasa cuando se aprueba un sílabo en silabos_v2?
1. silabos_v2 lo envía al observatorio (al aprobarse, estado 5), de servidor a servidor, con una clave secreta (README.md; app/Http/Middleware/VerifyIngestKey.php).
2. El observatorio lo guarda. Si ya lo tenía idéntico, no hace nada (no hay duplicados: un sílabo se identifica por modelo + id en silabos_v2) (app/Services/Syllabus/SyllabusIngestService.php).
3. Decide si es la **versión vigente** de su familia: gana la versión mayor; el periodo y el id solo desempatan; la fecha de aprobación no cuenta. Las versiones anteriores pasan a «reemplazado». Un sílabo ISIL nunca reemplaza a uno SCL (SyllabusIngestService.php; README.md).
4. Decide si **le interesa** al observatorio: se procesa si trae alguna de las carreras mapeadas **o** si su curso ya pertenece a una carrera mapeada. Si no, queda `ignored` (sin costo de IA) (SyllabusIngestService.php).
5. Busca el curso **solo por código exacto** («Código en silabos_v2»). Nunca por nombre y nunca crea cursos. Si no hay curso con ese código → «pendiente de vinculación» (aparece en el contador del menú) (SyllabusIngestService.php; SyllabusExtractionService.php).
6. Si hay curso: en segundo plano, la IA extrae lenguajes, tecnologías y metodologías del texto (sin OCR, el texto ya viene). El nombre del curso es el de silabos_v2, no el que adivina la IA (app/Jobs/ProcessIngestedSyllabusJob.php; SyllabusExtractionService.php).
7. Se **reemplazan** los lenguajes/tecnologías/metodologías del curso; los PDF del mismo curso pasan a «reemplazado»; el curso se agrega a las carreras mapeadas que trae el sílabo (sin quitar las que ya tenía); estado `processed` (SyllabusExtractionService.php).
8. La alineación del curso con el mercado se actualiza sola (se calcula al vuelo) y la tabla de Sílabos se refresca en vivo (README.md; app/Events/SyllabusProcessed.php). El tablero de alineación por competencias (PE) requiere recalcular («Actualizar datos» / `pe:recalculate`) (docs/produccion/PASOS_PRODUCCION.md).
9. Opcional: lanza el análisis de IA del curso (`AnalyzeCourseJob`) solo si `SILABOS_ANALYZE_AFTER_INGEST=true` (apagado por defecto, tiene costo) (config/silabos_v2.php).

#### Carreras mapeadas (config/silabos_v2.php; docs/mapeo-carreras-silabos.md)
| Código silabos_v2 | Carrera del observatorio (slug) | Estado |
|---|---|---|
| isil:CADT | arquitectura-de-datos | Activo |
| isil:CSEG | ciberseguridad | Activo |
| isil:CCDT | ciencia-de-datos-e-inteligencia-artificial | Activo |
| isil:CNUB | computacion-en-la-nube | Activo |
| isil:CINR | redes-y-comunicaciones | Activo |
| isil:BISI | ingenieria-de-sistemas | Activo |
| isil:BTIN | tecnologias-de-informacion | Activo |
| isil:CINS | desarrollo-de-software | **Pendiente de confirmar** (comentado) |
| isil:CISI | sistemas-de-informacion | **Pendiente de confirmar** (comentado) |

- Santa Catalina (SCL) no tiene ninguna carrera equivalente (docs/mapeo-carreras-silabos.md).
- Cambiar este mapeo lo hace TI (archivo de configuración); debe coincidir con el de silabos_v2 (docs/mapeo-carreras-silabos.md).
- Contradicción en documentación: docs/mapeo-carreras-silabos.md dice que silabos_v2 envía solo sílabos de esas carreras; config/silabos_v2.php y README.md dicen que envía **todos** y el observatorio filtra **[POR CONFIRMAR]**.

#### Parámetros (TI) (config/silabos_v2.php; .env.example; README.md)
| Variable | Por defecto | Efecto |
|---|---|---|
| `SILABOS_INGEST_API_KEY` | vacío | Clave compartida. Vacía = se rechaza todo envío. |
| `SILABOS_TBD_POSITION` | `first` | Periodos sin fecha (`TBD`, `TBD_EE`) se consideran los más antiguos (`first`) o los más nuevos (`last`) al desempatar. |
| `SILABOS_ANALYZE_AFTER_INGEST` | `false` | `true` = análisis de IA del curso tras cada ingesta (costo OpenAI). |
| `CATALOGO_PROMPT_MAX_TECNOLOGIAS` / `CATALOGO_PROMPT_CACHE_SEGUNDOS` | `0` / `300` | Tecnologías del catálogo que ve la IA / caché (README.md). |

#### Comandos de mantenimiento (los ejecuta TI) (app/Console/Commands/Silabos/*.php)
| Comando | Qué hace | Modo prueba |
|---|---|---|
| `silabos:asignar-codigos` | Asigna códigos a cursos desde `docs/correspondencia-cursos.csv` (solo coincidencias «exacto» sin observación). Nunca sobrescribe ni repite un código. Luego procesa los pendientes de esos códigos. | `--dry-run` |
| `silabos:procesar-vinculados` | Procesa los pendientes de vinculación cuyo código ya tiene curso (necesario si los códigos se asignaron por SQL o en «Cursos ISIL»). | `--dry-run` |
| `silabos:exportar-vinculaciones` | Solo lectura: genera un SQL con los códigos asignados y los «No vincular» para replicarlos en otra base. `--por-nombre` si los ids difieren. | — |
| `silabos:reporte-carreras` | Reporte por carrera: procesados (a)/(b), ignorados, pendientes de vinculación, otros (en cola/error). `--modelo=isil\|scl`. | (solo lectura) |

#### Revisión manual
El archivo `storage/logs/silabos-revision.log` registra: empates de versión con contenido distinto, versiones que bajan en un periodo posterior, periodos con formato desconocido, carreras mapeadas a un slug inexistente, sílabos ignorados automáticamente y los «No vincular» (README.md).

#### Qué corresponde al administrador vs. a TI (docs/produccion/PASOS_PRODUCCION.md)
- **Administrador:** resolver los pendientes en «Vinculación de cursos» (opción A del paso 8); revisar cursos y códigos en «Cursos ISIL»; asociar cursos a carreras; tras la carga, pulsar «Actualizar datos» del tablero PE o pedir `pe:recalculate`; comprobar que la carga por PDF (`/silabos`) sigue funcionando y que un sílabo aprobado en silabos_v2 llega solo.
- **TI:** respaldos, scripts SQL, variables de entorno, workers de colas, Reverb, carga inicial (`observatorio:carga-inicial`, en silabos_v2), revisión de `catalogo:nuevas`, reversión (pausar con `OBSERVATORIO_ENABLED=false`, etc.).
- Cifras previstas para la carga inicial (predicción con datos locales, no medido): 88 procesados (a), 12 (b), 531 ignorados, 13 pendientes de vinculación; ~363 de 532 PDF reemplazados (docs/produccion/PASOS_PRODUCCION.md).

#### Otros procesos relacionados
- `AnalyzeCourseJob`: análisis de IA del curso (hasta 3 llamadas, máx. 10 min, 1 reintento). Se lanza desde el tablero de alineación de cursos o tras la ingesta si la bandera está activa; usa solo sílabos vigentes (app/Jobs/AnalyzeCourseJob.php; app/Http/Controllers/Dashboard/CourseCCTCIndicatorController.php; README.md).
- `career:recommend` (GenerateCareerRecommendation): comando de consola «Genera recomendaciones estructurales por competencia»; no tiene pantalla en el menú Académico (app/Console/Commands/Curriculum/GenerateCareerRecommendation.php).


### 5.3 Procesos automáticos y en segundo plano


#### a) Tareas programadas (scheduler)

El archivo de programación define estas tareas (app/Console/Kernel.php):

| Tarea | Frecuencia | Qué hace | Estado |
|---|---|---|---|
| `joboffers:import` | cada 30 min | Importar ofertas de empleo | El comando existe (app/Console/Commands/ImportJobOffers.php) |
| `getonboard:import` | cada 6 h | Importar ofertas de GetOnBoard | Existe (app/Console/Commands/GetOnBoardImportCommand.php) |
| `worldbank:import` (indicadores laborales y TIC 2020–2025) | lunes 03:00 | Importar indicadores del Banco Mundial | Existe (app/Console/Commands/WorldBankImportCommand.php) |
| `scrape:computrabajo --pages=5` | diario 03:00 | Extraer ofertas de Computrabajo | **El comando no existe** (docs/pendientes-observatorio.md) |

**Aviso importante.**
- Según los pendientes del proyecto, este horario **probablemente no se ejecuta**: en Laravel 12, `bootstrap/app.php` no usa `withSchedule` y `routes/console.php` no programa nada (docs/pendientes-observatorio.md; routes/console.php).
- Mientras no se corrija, **las cargas automáticas de ofertas e indicadores deben lanzarse a mano** con comandos de consola; por ejemplo, `jobs:run-all` o `rss:run-all` (app/Console/Commands/RunAllJobs.php, app/Console/Commands/RunAllRss.php) **[POR CONFIRMAR con el equipo técnico]**.

#### b) Cola de trabajos (tareas que corren «por detrás»)

- Algunas acciones no se resuelven al instante: quedan en una lista de espera (la «cola») y un proceso aparte, el *worker*, las atiende.
- La cola está configurada en la base de datos (`QUEUE_CONNECTION=database`).
- Necesita un worker permanente: `php artisan queue:work --sleep=3 --tries=3 --timeout=170` (docs/produccion/PASOS_PRODUCCION.md).
- **Sin el worker, estas acciones quedan «encoladas» y nunca terminan.**

| Trabajo | Lo dispara | Para qué | Fuente |
|---|---|---|---|
| RunTrendTopicJob | «Ejecutar» en Topics IA | Buscar tendencias con IA | C/TopicsIAController.php |
| ProcessWebResultJob | «Procesar todos» o procesar uno en Resultados Web | Clasificar enlaces con IA | C/ScrapingWebResultController.php |
| ProcessIngestedSyllabusJob | Llegada de sílabos desde silabos_v2 / vinculación de cursos | Extraer datos del sílabo con OpenAI | C/SyllabusIngestController.php; app/Services/CourseLinkService.php |
| ProcessSyllabusJob, AnalyzeCourseJob | Módulo de sílabos / indicadores de curso | Análisis de sílabos y cursos | C/SyllabusController.php; C/CourseCCTCIndicatorController.php |
| PdfPart*Job (OCR, tablas, gráficos, resumen, subida a GCS) | Subida de partes de PDF | Procesar PDF por partes | C/PdfDocumentPartController.php; app/Jobs/PdfPartProcessJob.php |
| AnalyzeImageJob, ProcessTransactionBlockJob, ExtractLinksJob | Otros módulos | — | C/ImageAnalysisController.php; C/TransactionController.php; C/ScrapingSourceController.php |
| GeneratePdfSummaryJob, PdfOcrJob, PdfOcrChunkJob, ProcessPdfDocumentJob, ProcessScrapingSourceJob, ScrapeGithubEnrichedJob | Nadie (no se lanzan desde el código) | Sin uso aparente | búsqueda en `app/` **[POR CONFIRMAR]** |

#### c) Avisos en tiempo real (Reverb / broadcasting)

- La aplicación puede enviar avisos instantáneos al navegador a través de un servidor de WebSockets (Reverb).
- El evento propio del Observatorio es `SyllabusProcessed`: avisa por el canal `observatory` cuando se procesa un sílabo, y la pantalla de sílabos lo escucha (app/Events/SyllabusProcessed.php; P/syllabus/index.tsx).
- Si Reverb no está corriendo, conviene usar `BROADCAST_CONNECTION=log`. Si no se hace, los avisos acaban en `failed_jobs`, sin afectar al sílabo (docs/produccion/PASOS_PRODUCCION.md). En `.env.example` viene en `log` (.env.example).
- Otros canales (`analyses`, `transactions`, `chat.<id>`, `supports`) pertenecen a módulos heredados (P/bot/index.tsx, P/transactions/TransactionsTable.tsx, resources/js/components/ChatWidget.tsx).

#### d) Campana de notificaciones y correos

- **NotificationBell** escucha el canal `supports` y muestra «Nuevo soporte: …», «Notificaciones», «Sin notificaciones» y «Ver más» (resources/js/components/NotificationBell.tsx).
  - **Ningún archivo lo usa**: no se ve en la aplicación **[POR CONFIRMAR / heredado]** (búsqueda de `NotificationBell` en `resources/js`).
- **Correos** `PaymentNotificationMail` y `TransferNotification`, y la notificación `NewSupportAtcNotification`: los usan controladores de pagos, transferencias y soporte que **no tienen rutas** en `routes/`. En la práctica no se envían (C/PaymentsController.php; C/TransferController.php; C/SupportController.php; búsqueda en `routes/`).
- **Restablecer contraseña:** la única notificación activa es `CustomResetPasswordNotification` (app/Models/User.php).
  - El envío de correo está en modo `log` en `.env.example` (`MAIL_MAILER=log`): no se envía un correo real a menos que producción configure otro *mailer* **[POR CONFIRMAR]**.

#### e) Integraciones externas (en lenguaje simple)

| Servicio | Para qué | Fuente |
|---|---|---|
| OpenAI | Topics IA, clasificación de enlaces, extracción de sílabos | app/Services/Trends/RunTrendTopicService.php; app/Jobs/ProcessWebResultJob.php; docs/produccion/PASOS_PRODUCCION.md |
| Adzuna, GetOnBoard | Importación automática de ofertas desde Empleos | C/JobOfferController.php |
| Portales de empleo (Arbeitnow, Computrabajo, Greenhouse, Jobicy, Jooble, Reed, RemoteOK, Remotive, USAJOBS, Wantedly, JobsDataFeeds…) | Recolectores por lenguaje, tecnología, metodología, competencia o certificación (comandos de consola) | app/Console/Kernel.php `$commands` |
| Feeds RSS (Somos Digital, McKinsey, TechCrunch, The Verge, VentureBeat) | Noticias o tendencias | app/Console/Kernel.php |
| Banco Mundial | Indicadores macro | app/Console/Commands/WorldBankImportCommand.php |
| Microservicio de scraping (`SCRAPER_URL`) | Pantalla oculta Scrapings | C/ScrapingController.php |
| silabos_v2 | Envía sílabos al endpoint `/api/silabos/ingest` | docs/produccion/PASOS_PRODUCCION.md |

**Servicios de IA y otros del Dashboard Vera y la autenticación**


| Proceso | Qué hace | Fuente |
|---|---|---|
| OpenAI – chat (`gpt-4o-mini`) | Responde en modo Conversando, genera el SQL en modo Consultando Datos y redacta el resumen corto de cada gráfico | DashboardAIController.php, AITrainingController.php |
| OpenAI – voz | Convierte audio a texto (`gpt-4o-mini-transcribe`) y lee respuestas en voz alta (`gpt-4o-mini-tts`, voz "alloy") | DashboardAIController.php |
| OpenAI – análisis de archivos | Disponible en el servidor, pero el botón está oculto **[PENDIENTE]** | DashboardAIController.php, AiChatView.tsx |
| Excel de resultados | Cada consulta finalizada guarda un `.xlsx` en el almacenamiento público, en la carpeta `sql_results` | AITrainingController.php |
| Recalcular gráficos | Ocurre al pulsar ⟳, no es automático ni programado | DashboardContext.tsx |
| Historial del chat | Se guarda por sesión del navegador y conserva solo los 5 últimos intercambios | DashboardAIController.php |
| Login ISIL (SAML) | Proveedor de identidad institucional (WSO2/Banner) | AuthenticatedSessionController.php, routes/auth.php |

En estas pantallas no se usan colas, tareas programadas ni envío de correos. La única excepción es el correo de verificación del perfil (sección 2.2a).

---

**Otros procesos que lanza el usuario desde pantalla**

- Los botones de recálculo de los indicadores están disponibles para **cualquier usuario con sesión**:
  - «Recalcular seniority»;
  - «Actualizar alineación laboral», que encola 8 normalizaciones geográficas y la reconstrucción de la alineación;
  - «Actualizar datos laborales».

  Ver 2.8 y 2.10 (routes/web.php; JobDemandGeoIndicatorController.php).
- «Detectar nuevos … con IA» llama a OpenAI y crea entidades candidatas que aparecen enseguida en el ranking (2.7 › B.0.8).
- Las «fotos» de evolución de los rankings (semanal, quincenal y mensual) las guardan los comandos `*:take-snapshot`. **No están programados** en el repositorio (2.7 › B.0.9).
- Las macrotendencias se cargan con `trends:discover-global` / `trends:discover-exa`, a mano por consola: el botón de la pantalla no existe (2.9).

### 5.4 Modos especiales a vigilar

| Modo / ajuste | Efecto para el usuario | Fuente |
|---|---|---|
| `--dry-run` en los comandos `silabos:*` | Muestra qué haría sin aplicar cambios | app/Console/Commands/Silabos/*.php |
| `SILABOS_ANALYZE_AFTER_INGEST` (por defecto `false`) | Si está en `true`, cada sílabo recibido dispara un análisis IA del curso, que tiene costo | config/silabos_v2.php; .env.example |
| `SILABOS_INGEST_API_KEY` vacía | El observatorio **rechaza todos** los sílabos que envía silabos_v2 | README.md |
| `OBSERVATORIO_ENABLED` (en silabos_v2) | Activa o pausa el envío de sílabos desde silabos_v2 | README.md; docs/produccion/PASOS_PRODUCCION.md |
| Mapeo de carreras (`career_map`) | Solo se procesan los sílabos de las carreras mapeadas. Dos carreras (CINS, CISI) están comentadas, pendientes de confirmar | config/silabos_v2.php |
| `BROADCAST_CONNECTION=log` | Sin Reverb no hay refresco en vivo de la tabla de Silabos; hay que recargar la página | .env.example; docs/produccion/PASOS_PRODUCCION.md |
| `MAIL_MAILER=log` | No se envían correos reales, por ejemplo el de restablecer contraseña | .env.example **[POR CONFIRMAR en producción]** |
| `APP_LOCALE=en` | Mensajes de validación en inglés | .env.example |
| Worker de colas detenido | Sílabos, Topics IA y análisis IA quedan «en cola» y nunca terminan | docs/produccion/PASOS_PRODUCCION.md |
| Ambientes | Existe una guía de despliegue a producción con scripts de verificación, respaldo y reversión | docs/produccion/PASOS_PRODUCCION.md |
| Usuarios de prueba del instalador | `admin@gmail.com` y `asistente@gmail.com`: verificar que no existan en producción | database/seeders/PermissionsSeeder.php |

---

## 6. GLOSARIO (candidato a "Definiciones")

| Término | Definición |
|---|---|
| **Observatorio Tecnológico** | Sistema que analiza ofertas de empleo y tendencias tecnológicas y las compara con los cursos de ISIL (README.md). |
| **VERA / VERA IA** | Asistente de IA del observatorio. Conversa, consulta datos y arma gráficos en el Dashboard Vera (DashboardAIController.php). |
| **Dashboard** | Tablero personalizable con gráficos (widgets), organizado en pestañas (2.11). |
| **Widget / gráfico** | Cada bloque visual de un dashboard. Se genera a partir de una consulta de datos (2.11). |
| **Entrenamiento IA** | Consulta de datos validada por un administrador que VERA usa para responder y para crear gráficos (2.12). |
| **Oferta / vacante** | Anuncio de empleo recolectado de un portal externo (2.5 › F.9). |
| **Scraping / recolección automática** | Proceso que obtiene ofertas o datos de sitios y APIs externos (2.7 › B.0.6). |
| **Fuente** | Portal o servicio de donde se obtienen datos (Remotive, Jooble, GetOnBoard, etc.) (2.6). |
| **Reporte de tendencia** | Evidencia externa (artículo o hallazgo de IA) que asocia un elemento con una tendencia (2.7 › B.0.2). |
| **Macrotendencia** | Tendencia tecnológica global identificada por IA a partir de artículos (2.9). |
| **Topic IA** | Tema de búsqueda configurado para que la IA detecte tendencias (2.5 › F.10). |
| **Score laboral** | Puntaje de 0 a 100 según cuántas ofertas mencionan el elemento; el que más tiene vale 100 (2.7 › B.0.4). |
| **Score de tendencias** | Puntaje de 0 a 100 según cuántos reportes de tendencia tiene el elemento (2.7 › B.0.4). |
| **Resultado final** | Combinación ponderada del score laboral y el de tendencias (2.7 › B.0.4). |
| **Ponderación** | Porcentaje de peso de la demanda laboral frente a las tendencias en un ranking (2.7 › B.0.7). |
| **Proyección (Alta / Media / Baja)** | Etiqueta según el resultado final: ≥70 / ≥40 / menos (2.7 › B.0.4). |
| **Período / semestre** | «Ene – Jun» (S1) o «Jul – Dic» (S2) de un año (2.7 › B.0.3). |
| **Evolución** | Historial del Top 5 del ranking por semana, quincena o mes (2.7 › B.0.9). |
| **Nivel profesional (seniority)** | Clasificación de la oferta en Junior / Mid / Senior (2.8). |
| **Modalidad laboral** | Presencial, remoto o híbrido, según la oferta (2.8). |
| **Entidad de mercado** | Elemento del catálogo común (tecnología, lenguaje, certificación…) usado en los cálculos (2.3 › F.7). |
| **Candidato** | Entidad propuesta por la IA, aún sin revisar (2.7 › B.0.8). |
| **Contexto semántico** | Contexto de búsqueda asociado a un elemento del catálogo (2.3 › F.1). |
| **Rol Tech** | Puesto tecnológico (p. ej., desarrollador) que se busca en el título de las ofertas para asociarlas a carreras (2.3 › F.6; 2.8 › C.5). |
| **Competencia** | Competencia del perfil de egreso de una carrera, vinculada a cursos (2.3 › F.5). |
| **Sílabo** | Documento del curso (PDF o recibido de silabos_v2) del que se extrae lo que enseña (2.4). |
| **silabos_v2 / ISILabos** | Sistema institucional de sílabos que envía al observatorio los sílabos aprobados (README.md). |
| **Código en silabos_v2** | Código del curso en silabos_v2. Permite enlazar automáticamente un sílabo con su curso (2.4 › E.3). |
| **Vinculación de cursos** | Tarea manual de asociar un sílabo recibido a un curso del observatorio, o marcarlo «No vincular» (2.4 › E.4). |
| **Pendiente de vinculación** | Sílabo recibido cuyo código no corresponde a ningún curso (2.4). |
| **Reemplazado (superseded)** | Sílabo que dejó de contar porque llegó una versión más nueva (2.4). |
| **Ignorado** | Sílabo recibido que no pertenece a ninguna carrera del observatorio (2.4). |
| **Versión vigente** | La versión mayor de un sílabo; es la única que cuenta (5.2). |
| **CCTC / Alineación de cursos** | Indicador que clasifica cada curso según sus vínculos con lenguajes, tecnologías y metodologías, la demanda y las tendencias (2.10). |
| **Índice final de alineación** | Promedio del % «Demanda Laboral» y del % «Tendencias» de los cursos de una carrera (2.10). |
| **Perfil de egreso (PE)** | Conjunto de competencias de una carrera; tiene su tablero de alineación, oculto (2.10 › D.4). |
| **Cola / segundo plano** | Tareas que el sistema ejecuta después, sin que el usuario espere en pantalla (5.3). |
| **Permiso `administrar`** | Único permiso que el sistema verifica; da acceso a todas las pantallas de gestión (4). |

---

## 7. RESPONSABILIDADES SUGERIDAS

| Tarea | Administrador del observatorio | Área de TI |
|---|---|---|
| Crear usuarios, asignar roles y permisos | ✔ (2.2) | Soporte en problemas de login SAML |
| Mantener catálogos: lenguajes, tecnologías, metodologías, competencias, roles tech, entidades | ✔ (2.3) | — |
| Revisar las entidades «candidato» creadas por la IA | ✔ (2.7 › B.0.8) | — |
| Mantener carreras y cursos; asignar el «Código en silabos_v2» | ✔ (2.4) | Carga masiva de códigos por script o comando (`silabos:asignar-codigos`) |
| Resolver los «pendientes de vinculación» | ✔ (2.4 › E.4) | `silabos:procesar-vinculados` si los códigos se cargaron por SQL |
| Subir sílabos PDF | ✔ (2.4 › E.1) | — |
| Configurar ponderaciones de los rankings | ✔ (2.7 › B.0.7) | — |
| Lanzar recálculos desde pantalla y «Detectar con IA» | ✔ (2.7, 2.8) | — |
| Gestionar Topics IA y Entrenamientos IA | ✔ (2.5, 2.12) | — |
| Revisar Comandos Fuentes y avisar de fuentes fallidas | ✔ (2.6) | Corregir y ejecutar los recolectores |
| Ejecutar los recolectores de ofertas y tendencias, y programar las tareas automáticas | — | ✔ (5.3) |
| Mantener activos el worker de colas y Reverb | — | ✔ (5.3) |
| Variables de entorno: clave de ingesta, banderas, mapeo de carreras, correo, idioma | — | ✔ (5.4) |
| Despliegue, respaldos, scripts SQL y reversión | — | ✔ (docs/produccion/PASOS_PRODUCCION.md) |
| Revisar `storage/logs/silabos-revision.log` y `catalogo:nuevas` | Recibe el resumen | ✔ (5.2) |
| Costos de OpenAI, Tavily y Exa | Aprueba su uso | ✔ Monitorea las claves y el consumo |
| Corregir los defectos de la sección 8 | Los reporta | ✔ |

---

## 8. PENDIENTES

### 8.1 Seguridad (prioridad alta)

- APIs de IA sin autenticación (`/api/ai/*`, `/api/courses/list`) (routes/api.php). **Verificado en el código.**
- `/api/users` solo exige sesión, no el permiso `administrar` (routes/api/users.php). Validar en ejecución **[POR CONFIRMAR]**.
- Cualquier usuario con sesión puede crear o eliminar dashboards y gráficos, y lanzar los recálculos de los indicadores (2.11; 2.8 › C.0).
- `/scrapings` y algunas rutas de `/sources`, `/technologies/{id}/toggle` y `/job-offers/preview|import` no exigen `administrar` (2.3 › F.0; 2.6).
- Ruta de prueba `/__probe` expuesta (routes/web.php).
- Contraseña por defecto `isil2026` en el formulario de usuario (2.2) **[POR CONFIRMAR]**.
- Usuarios de prueba `admin@gmail.com` / `asistente@gmail.com` creados por el instalador (database/seeders/PermissionsSeeder.php).

### 8.2 Procesos automáticos

- El programador de tareas probablemente **no se ejecuta**: falta `withSchedule`, y `scrape:computrabajo` no existe (docs/pendientes-observatorio.md; app/Console/Kernel.php).
- Las fotos de evolución (`*:take-snapshot`) y las macrotendencias no están programadas (2.7 › B.0.9; 2.9).
- Los correos funcionan en modo `log`. La campana de notificaciones (NotificationBell) no se usa en ninguna pantalla (5.3).
- El refresco de gráficos (`DashboardWidgetController::refresh`) no tiene ruta web (docs/pendientes-observatorio.md).

### 8.3 Funcionalidades incompletas, ocultas o desactivadas

**Dashboard Vera e IA**

- Botón «Nueva Sección» inexistente.
- «Editar colores» y «Segmentar datos» comentados.
- En el chat: adjuntar archivo, forzar nueva consulta, controles de audio y descarga de Excel comentados.
- «Prompt de explicación» no se guarda.
- Gráficos RADAR vacíos **[POR CONFIRMAR]**.
- `/dashboard_vera` no envía datos **[POR CONFIRMAR]**.

(2.11; 2.12)

**Administración**

- Paginación de Roles falla **[POR CONFIRMAR]**.
- Campo «Nombre» del perfil sin respaldo **[POR CONFIRMAR]**.
- Campos DNI, Celular y Sexo comentados.
- Permisos `scrapings.ver`, `usuarios.ver` y `roles.ver` sin uso.

(2.1; 2.2; 4)

**Rankings (Tecnologías, Lenguajes, Certificaciones)**

- Tarjetas KPI nunca visibles: el servidor no envía `kpis`.
- Los modales de ofertas muestran todo el histórico, no solo el semestre elegido.
- El modal de ponderaciones de Tecnologías puede abrir en 30 % de tendencias **[POR CONFIRMAR]**.
- Filtro `ranking_type` sin control ni efecto.
- Modal de tendencias de Lenguajes fijo en 2026‑S1 **[POR CONFIRMAR]**.
- En Certificaciones, el resultado final usa una proporción lineal para la parte de tendencias.
- Rutas a métodos inexistentes y componentes sin usar.

(2.7 › B.0.10, B.1.6, B.2.7, B.3.6)

**Nivel Profesional, Empresas, Demanda Ciudad y Modalidad Laboral**

- Gráfico de modalidad por nivel comentado.
- En Empresas, el selector «Resultados» no cambia el número de filas **[POR CONFIRMAR]**.
- Los insights remotos de Modalidad nunca aparecen **[POR CONFIRMAR]**.
- En Demanda Ciudad, el conteo se infla con vacantes asociadas a varias carreras.
- Filtro `source` de Modalidad sin control en pantalla.
- Endpoints `/ai/city-demand` sin uso.

(2.8)

**Demanda laboral por carrera** (`/dashboard/ranking-carreras`)

- Fuera del menú.
- Solo muestra 6 carreras, sin paginación **[POR CONFIRMAR]**.
- «Actualizar datos laborales» sin token CSRF **[POR CONFIRMAR]**.
- «Carrera líder» lleva a una ruta inexistente **[POR CONFIRMAR]**.
- Gráfico vacío.

(2.8 › C.5)

**Tendencias Macro**

- Sin botón de «Ejecutar descubrimiento»; `DiscoverMacroTrendsJob` no existe.
- Estrellas fijas.
- Breadcrumb hacia una URL distinta de la pantalla.
- Rutas de detalle, reportes y ofertas inexistentes.

(2.9)

**Comandos Fuentes**

- «Registros totales» fijo en 31,655.
- Buscador sin efecto.
- La URL editada en pantalla no la usan los comandos, que la sobrescriben al ejecutarse.
- Sin botón de ejecución ni de prueba de conexión.
- Rutas `show`, `test-data` y `failed` rotas.

(2.6)

**Alineación Cursos y Alineación Competencias**

- Vista «Competencias» oculta.
- Varias rutas a métodos inexistentes.
- «Alineación Competencias» comentada en el menú, con rutas de apoyo inexistentes.
- `/dashboard/job-market/status` rota.

(2.10)

**Académico**

- La eliminación en lote de Carreras y Cursos llama a rutas inexistentes.
- No hay pantalla para editar carreras.
- Asignar un código en «Cursos ISIL» no procesa los pendientes de vinculación.
- Mapeo de las carreras CINS y CISI comentado.
- Enlace «Ver PDF» **[POR CONFIRMAR]**.
- Contradicción sobre qué sílabos envía silabos_v2 **[POR CONFIRMAR]**.

(2.4 › E.6)

**Habilidades y Datos**

- **Roles Tech:** «Editar» llama a una ruta inexistente, el método HTTP no coincide y la ruta de activar/desactivar no tiene método **[POR CONFIRMAR]**.
- **Competencias:** el filtro de carrera solo se aplica al cambiar de página.
- **Entity Trends:** no permite elegir la entidad.
- **Empleos, importar CSV:** el mapeo de columnas se ignora y el aviso siempre dice «Insertados: 0» **[POR CONFIRMAR]**.
- **Empleos, Excel:** puede fallar por el orden de las rutas **[POR CONFIRMAR]**.
- **Topics IA:** al editar no se precarga el «Peso del Topic» **[POR CONFIRMAR]**.
- **Scrapings y Backups:** pantallas ocultas, con posible defecto de ids **[POR CONFIRMAR]**.

(2.3; 2.5; 2.13)

**Datos (conocidos por el equipo)**

- Lenguajes asignados por búsqueda sin evidencia en el texto.
- Arbeitnow asigna todos los lenguajes a cada oferta.
- Ubicaciones de Computrabajo pendientes de reparar.
- Elementos del catálogo de lenguajes que no son lenguajes (IDC, OECD, JSON, XML…).

(docs/pendientes-observatorio.md)

### 8.4 Listado de marcas [POR CONFIRMAR]

Cada marca **[POR CONFIRMAR]** del documento está en su subsección, con el archivo fuente correspondiente. Antes de redactar cada paso del manual, conviene validarlas en el sistema en ejecución, con un usuario administrador y otro sin rol.

---

## ORDEN SUGERIDO PARA EL CAPÍTULO "7. Desarrollo" DEL MANUAL

| N.º | Subsección | Pasos principales | Capturas necesarias |
|---|---|---|---|
| 7.1 | Ingreso al sistema | Login externo con captcha / login ISIL (SAML); cerrar sesión | Pantalla de login; botón de login ISIL; menú de usuario abierto |
| 7.2 | Navegación y configuración personal | Menú lateral (qué ve un usuario sin rol frente a un administrador); Perfil, Contraseña, Apariencia | Menú lateral completo (administrador); menú lateral reducido (sin rol); las 3 pestañas de Configuración |
| 7.3 | Administración de usuarios y roles | Crear y editar usuarios, asignar roles; crear roles y asignar permisos | Lista de Usuarios; modal de usuario; lista de Roles; modal de rol con permisos |
| 7.4 | Catálogos de Habilidades | Patrón común (buscar, crear, editar, eliminar, activar/desactivar); particularidades de Competencias, Roles Tech y Entidades | Tabla de Lenguajes con el interruptor; modal «Nuevo …»; diálogo «¿Eliminar…?»; pantalla Entidades con el interruptor de tendencia |
| 7.5 | Carreras y Cursos ISIL | Crear carrera; «Sincronizar» cursos; crear o editar curso con «Código en silabos_v2»; buscar y filtrar | Lista de Carreras; modal Sincronizar; lista de Cursos con la columna de código; modal de curso |
| 7.6 | Sílabos | Subir PDF («Carga Masiva de Sílabos»); seguir los estados; ver detalle; eliminar | Modal de carga; tabla con estados en colores; detalle de un sílabo |
| 7.7 | Vinculación de cursos (silabos_v2) | Contador del menú; vincular a un curso; «No vincular» | Menú con el contador naranja; tabla de pendientes; selector «Curso del observatorio»; mensaje de éxito |
| 7.8 | Datos de mercado | Empleos (filtros, detalle, importar, exportar Excel); Topics IA (crear, ejecutar, estado); Comandos Fuentes (consultar el estado de las fuentes) | Panel «Filtros Avanzados»; modal «Detalle de Oferta»; tabla de Topics IA con «Ejecutar»; tabla «Listado de fuentes» con los estados |
| 7.9 | Indicadores de mercado: rankings | Elegir Año y Semestre; leer las tarjetas; «Datos Generales»; ponderaciones; «Ver evolución» y Excel; «Detectar con IA» | Ranking de Tecnologías completo; modal Datos Generales; modal de ponderaciones; modal de evolución; botón IA en ejecución |
| 7.10 | Indicadores de mercado: perfil de la demanda | Nivel Profesional, Empresas, Demanda Ciudad (mapa), Modalidad Laboral; botones de recálculo; exportaciones | Una captura general de cada pantalla; mapa de calor; modal de evolución de Nivel Profesional; modal «Empleos en {empresa}» |
| 7.11 | Tendencias Macro | Buscar; leer las tarjetas; «Ver análisis» | Pantalla con las tarjetas; modal «Ver análisis» |
| 7.12 | Alineación de cursos | Elegir carrera; leer los KPIs y el índice; tabla de cursos; panel lateral del curso; botón IA | Pantalla con los KPIs y el indicador circular; tabla de cursos con estados; panel lateral con pestañas |
| 7.13 | Entrenamiento IA (VERA) | Crear, probar, activar y duplicar entrenamientos | Tabla de entrenamientos; modal «🧠 Nuevo Entrenamiento IA» |
| 7.14 | Dashboard Vera y asistente VERA | Crear, renombrar y eliminar dashboards; agregar gráficos; menú ⚙️ (Excel, PDF, tipo, filtrar, eliminar); ⟳; chat en modos «Conversando» y «Consultando Datos»; voz | Dashboard con gráficos; menú ⚙️ abierto; panel VERA en cada modo; gráfico creado desde el chat |

**Nota:** antes de capturar las pantallas de 7.9, 7.10 y 7.11, conviene que TI ejecute los recolectores y las fotos de evolución, para que no salgan vacías. Las funciones marcadas en 8.3 no deberían documentarse como pasos del manual hasta que se corrijan.
