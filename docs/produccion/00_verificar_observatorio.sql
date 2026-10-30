-- =====================================================================
-- 00_verificar_observatorio.sql · SOLO LECTURA
-- BASE: la del observatorio (citas_websocket). En local se llama
-- observatorio_2; en producción puede llamarse distinto.
--
-- Selecciona esa base como predeterminada antes de ejecutar (en MySQL
-- Workbench: doble clic en el esquema, queda en negrita) y ejecuta el
-- archivo completo con Ctrl+Shift+Enter. No usa USE ni modifica nada:
-- no crea tablas (ni temporales), no inserta ni actualiza.
--
-- Comprueba, ANTES de aplicar 01_observatorio_aplicar.sql:
--   - que la estructura de las tablas que toca la integración es la esperada;
--   - que nada de lo que se va a crear existe ya;
--   - que las 7 carreras mapeadas y la categoría 'other' existen;
--   - para los 103 cursos de 02_observatorio_asignar_codigos.sql, si existe
--     un curso con ese id y ese nombre exactos (y, si no, si el nombre
--     existe con otro id, para usar la alternativa por nombre).
--
-- Resultados (una pestaña por consulta en Workbench):
--   1. Tablas que faltan (vacío = bien).
--   2. Detalle de los 103 cursos.
--   3. RESUMEN (la última): una fila por comprobación, con OK,
--      NO COINCIDE (no seguir sin revisarlo) o AVISO (revisar, no bloquea).
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Tablas requeridas que no existen. Esperado: ninguna fila.
-- ---------------------------------------------------------------------

SELECT req.t AS tabla_que_falta
FROM (SELECT 'syllabus' AS t UNION ALL SELECT 'courses' UNION ALL SELECT 'careers' UNION ALL SELECT 'career_course'
      UNION ALL SELECT 'languages' UNION ALL SELECT 'technologies' UNION ALL SELECT 'methodologies'
      UNION ALL SELECT 'technology_categories' UNION ALL SELECT 'course_language' UNION ALL SELECT 'course_technology'
      UNION ALL SELECT 'course_methodology' UNION ALL SELECT 'jobs' UNION ALL SELECT 'failed_jobs'
      UNION ALL SELECT 'migrations' UNION ALL SELECT 'market_entities' UNION ALL SELECT 'competency_course'
      UNION ALL SELECT 'pe_alignment_competency_results') req
LEFT JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = req.t
WHERE t.TABLE_NAME IS NULL;


-- ---------------------------------------------------------------------
-- 2. Detalle de los 103 cursos de 02_observatorio_asignar_codigos.sql.
--    situacion:
--      OK                    → id y nombre exactos (sirve 02_observatorio_asignar_codigos.sql)
--      NOMBRE DISTINTO       → el id existe con otro nombre (o con otras mayúsculas o tildes)
--      ID NO EXISTE          → no hay curso con ese id
--    cursos_con_ese_nombre: cuántos cursos tienen exactamente ese nombre;
--      1 → la alternativa por nombre (02b) lo encuentra;
--      0 → 02b no lo encuentra; >1 → 02b no puede decidir (nombre repetido).
-- ---------------------------------------------------------------------

SELECT l.id, l.code, l.name AS nombre_esperado,
       c.name AS nombre_en_esta_base,
       CASE WHEN c.id IS NULL THEN 'ID NO EXISTE'
            WHEN c.name COLLATE utf8mb4_bin = l.name THEN 'OK'
            ELSE 'NOMBRE DISTINTO' END AS situacion,
       (SELECT COUNT(*) FROM courses x WHERE x.name COLLATE utf8mb4_bin = l.name) AS cursos_con_ese_nombre,
       (SELECT GROUP_CONCAT(x.id ORDER BY x.id) FROM courses x WHERE x.name COLLATE utf8mb4_bin = l.name) AS ids_con_ese_nombre
FROM (
    SELECT 5 AS id, '30012' AS code, 'ANÁLISIS DE DATOS' COLLATE utf8mb4_bin AS name
    UNION ALL SELECT 6, '30015', 'ANÁLISIS DEL ENTORNO DE NEGOCIOS'
    UNION ALL SELECT 7, '30047', 'COMUNICACIÓN ESCRITA'
    UNION ALL SELECT 9, '30153', 'CALCULO PARA CIENCIAS E INGENIERIA'
    UNION ALL SELECT 10, '30063', 'Desarrollo de Resiliencia'
    UNION ALL SELECT 12, '30092', 'DISEÑO Y PROGRAMACIÓN DE BASE DE DATOS'
    UNION ALL SELECT 13, '30107', 'Ética Profesional'
    UNION ALL SELECT 15, '30135', 'GESTIÓN DE PROYECTOS'
    UNION ALL SELECT 16, '30136', 'GESTIÓN DE SERVICIOS ITIL'
    UNION ALL SELECT 18, '30199', 'PROGRAMACIÓN AVANZADA DE BASE DE DATOS'
    UNION ALL SELECT 19, '30228', 'Servidores y Almacenamiento'
    UNION ALL SELECT 20, '30475', 'ANALISIS DE RENDIMIENTO Y ESCALABILIDAD'
    UNION ALL SELECT 21, '30478', 'AUDITORIA DE CIBERSEGURIDAD'
    UNION ALL SELECT 22, '30480', 'AUTOMATIZACION DE PROCESOS DE NEGOCIOS'
    UNION ALL SELECT 23, '30041', 'Competencias Intrapersonales'
    UNION ALL SELECT 24, '30489', 'COMPLIANCE IT'
    UNION ALL SELECT 25, '30492', 'DATA LAKES AND DATA WAREHOUSING'
    UNION ALL SELECT 26, '30500', 'FUNDAMENTOS DE ARQUITECTURA DE DATOS'
    UNION ALL SELECT 27, '30501', 'FUNDAMENTOS DE BIG DATA'
    UNION ALL SELECT 28, '30514', 'INTRODUCCIÓN AL MODELADO DE DATOS'
    UNION ALL SELECT 29, '30176', 'MATEMÁTICA APLICADA'
    UNION ALL SELECT 30, '30089', 'DISEÑO Y DESARROLLO WEB'
    UNION ALL SELECT 32, '30521', 'SEGURIDAD EN LA NUBE'
    UNION ALL SELECT 33, '30003', 'ADMINISTRACIÓN AVANZADA DE WINDOWS'
    UNION ALL SELECT 34, '30009', 'ADMINISTRACIÓN DE WINDOWS'
    UNION ALL SELECT 39, '30037', 'CCNA SECURITY'
    UNION ALL SELECT 40, '30074', 'Dirección de Personas'
    UNION ALL SELECT 42, '30128', 'GESTIÓN DE DISPOSITIVOS TECNOLÓGICOS'
    UNION ALL SELECT 43, '30223', 'SEGURIDAD EN SISTEMAS OPERATIVOS'
    UNION ALL SELECT 44, '30474', 'ANALISIS DE AMENAZAS Y RIESGOS'
    UNION ALL SELECT 45, '30715', 'CRIPTOGRAFIA'
    UNION ALL SELECT 46, '30499', 'ESTANDARES Y NORMATIVAS DE CIBERSEGURIDAD'
    UNION ALL SELECT 47, '30502', 'FUNDAMENTOS DE CIBERSEGURIDAD'
    UNION ALL SELECT 48, '30515', 'INVESTIGACION DE INCIDENTES'
    UNION ALL SELECT 49, '30726', 'SEGURIDAD EN APLICACIONES Y BASES DE DATOS'
    UNION ALL SELECT 50, '30245', 'Técnicas de Hacking y Análisis Forense'
    UNION ALL SELECT 51, '30020', 'Analitica De Sistemas Empresariales'
    UNION ALL SELECT 52, '30038', 'Centro De Datos Cloud'
    UNION ALL SELECT 53, '30356', 'Transformacion Digital'
    UNION ALL SELECT 54, 'TBD45', 'Aprendizaje Por Refuerzo'
    UNION ALL SELECT 55, '30482', 'Big Data Analytics'
    UNION ALL SELECT 58, '30537', 'Deep Learning Y Lenguaje Natural'
    UNION ALL SELECT 59, '30498', 'Estadistica Avanzada'
    UNION ALL SELECT 60, '30503', 'Fundamentos De Ciencia De Datos'
    UNION ALL SELECT 61, '30506', 'Fundamentos De Machine Learning'
    UNION ALL SELECT 62, '30512', 'Introduccion A La Estadistica Y Analisis De Datos'
    UNION ALL SELECT 63, '30518', 'Optimizacion Y Modelado Matematico'
    UNION ALL SELECT 64, '30535', 'Sistemas Distribuidos'
    UNION ALL SELECT 65, '30523', 'Storytelling Con Datos'
    UNION ALL SELECT 66, '30534', 'Vision Computacional'
    UNION ALL SELECT 84, '30017', 'Analisis Funcional'
    UNION ALL SELECT 86, '30157', 'Integracion De Aplicaciones'
    UNION ALL SELECT 87, '30231', 'Sistemas De Control Y Gobierno De Ti'
    UNION ALL SELECT 88, '30677', 'Administracion De Infraestructura Cloud'
    UNION ALL SELECT 89, '30476', 'Analisis Y Monitoreo Cloud'
    UNION ALL SELECT 90, '30477', 'Arquitectura De Cloud Computing'
    UNION ALL SELECT 91, '30714', 'Automatizacion Y Gestion De Configuracion Cloud'
    UNION ALL SELECT 93, '30504', 'Fundamentos De Cloud Computing'
    UNION ALL SELECT 95, '30032', 'Calidad Y Pruebas De Software'
    UNION ALL SELECT 101, '30181', 'Modelos De Negocios Y Startups'
    UNION ALL SELECT 104, '30376', 'Soluciones Basadas En Internet De Las Cosas'
    UNION ALL SELECT 105, '30024', 'Aplicaciones Moviles Para Android'
    UNION ALL SELECT 106, '30025', 'Aplicaciones Moviles Para Iphone'
    UNION ALL SELECT 108, '30224', 'Seguridad Y Continuidad De Sistemas'
    UNION ALL SELECT 150, '30002', 'Administracion Avanzada De Linux'
    UNION ALL SELECT 151, '30007', 'Administracion De Linux'
    UNION ALL SELECT 153, '30257', 'Virtualizacion De Infraestructuras Tecnologicas'
    UNION ALL SELECT 155, '30130', 'Gestion De La Cadena De Suministros'
    UNION ALL SELECT 157, '30134', 'Gestion De Procesos, Simulacion Y Mejora Continua'
    UNION ALL SELECT 158, 'TBD 27', 'Analisis Y Diseno De Sistemas Avanzado'
    UNION ALL SELECT 162, '30060', 'Desarrollo De Aplicaciones Empresariales Avanzado'
    UNION ALL SELECT 164, '30632', 'Desarrollo De Soluciones Cloud Basico'
    UNION ALL SELECT 165, 'TBD70', 'Inteligencia Artificial Aplicada'
    UNION ALL SELECT 166, '30626', 'Programacion Orientada A Objetos'
    UNION ALL SELECT 167, '30690', 'Programacion Web Avanzada'
    UNION ALL SELECT 170, '30034', 'Ccna Ii - Switching, Routing Y Wireless Essentials'
    UNION ALL SELECT 171, '30035', 'Ccna Iii - Enterprise Networking, Security And Automation'
    UNION ALL SELECT 172, '30036', 'Ccna Iv-Advanced Routing'
    UNION ALL SELECT 174, '30702', 'Desarrollo De Soluciones Cloud Avanzado'
    UNION ALL SELECT 176, '30493', 'Data Mining Basico'
    UNION ALL SELECT 177, '30494', 'Data Mining Avanzado'
    UNION ALL SELECT 179, '30524', 'Tendencias En Arquitectura De Datos'
    UNION ALL SELECT 180, 'TBD 40', 'Analisis Estadistico Y Data Mining'
    UNION ALL SELECT 181, 'TBD 42', 'Arquitectura Empresarial'
    UNION ALL SELECT 182, '40001', 'Cultura De Innovacion'
    UNION ALL SELECT 183, '40064', 'Customer Centricity En Tecnologias De La Informacion'
    UNION ALL SELECT 184, '40081', 'Direccion En Transformacion Digital'
    UNION ALL SELECT 185, '40062', 'Direccion Estrategica De Datos'
    UNION ALL SELECT 186, 'TBD 41', 'Diseno De Soluciones Con Inteligencia Artificial'
    UNION ALL SELECT 187, '40004', 'Fundamentos De Investigacion'
    UNION ALL SELECT 188, 'TBD 43', 'Gestion Presupuestaria De Ti'
    UNION ALL SELECT 191, '40009', 'Innovacion Y Creacion De Valor'
    UNION ALL SELECT 192, '40038', 'Inteligencia De Negocios'
    UNION ALL SELECT 193, '40036', 'Planeamiento Estrategico De Sistemas De Informacion'
    UNION ALL SELECT 194, '40013', 'Taller De Investigacion Aplicada E Innovacion'
    UNION ALL SELECT 195, '40008', 'Tecnicas Y Herramientas De Investigacion'
    UNION ALL SELECT 196, '40039', 'Tecnologias Emergentes'
    UNION ALL SELECT 197, '40063', 'Gestion De Proyectos Basados En Analytics'
    UNION ALL SELECT 198, '40010', 'Gestion Del Emprendimiento'
    UNION ALL SELECT 199, '40028', 'Herramientas Tecnologicas Para La Gestion'
    UNION ALL SELECT 200, '40035', 'Integracion De Procesos Empresariales'
    UNION ALL SELECT 201, '40034', 'Metodologias Para La Gestion De Proyectos'
    UNION ALL SELECT 202, '40033', 'Procesos Empresariales'
) l
LEFT JOIN courses c ON c.id = l.id
ORDER BY (c.name COLLATE utf8mb4_bin = l.name) IS TRUE, l.id;


-- ---------------------------------------------------------------------
-- 3. RESUMEN. resultado: OK / NO COINCIDE / AVISO.
-- ---------------------------------------------------------------------

SELECT r.n, r.comprobacion, r.esperado, r.encontrado,
       CASE WHEN r.ok THEN 'OK' WHEN r.bloquea THEN 'NO COINCIDE' ELSE 'AVISO' END AS resultado
FROM (
    SELECT 1 AS n, 'Base seleccionada' AS comprobacion, 'una base' AS esperado,
           COALESCE(DATABASE(), '(ninguna: selecciónala y vuelve a ejecutar)') AS encontrado,
           DATABASE() IS NOT NULL AS ok, 1 AS bloquea
    UNION ALL
    SELECT 2, 'Servidor MySQL 8 (probado en 8.0.46)', 'MySQL 8.x', VERSION(),
           VERSION() NOT LIKE '%MariaDB%' AND CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED) >= 8, 0
    UNION ALL
    SELECT 3, 'sql_mode con STRICT_TRANS_TABLES', 'contiene STRICT_TRANS_TABLES', @@SESSION.sql_mode,
           FIND_IN_SET('STRICT_TRANS_TABLES', @@SESSION.sql_mode) > 0, 0
    UNION ALL
    SELECT 4, 'Tablas requeridas (detalle en la consulta 1)', '17 de 17',
           CONCAT((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('syllabus', 'courses', 'careers', 'career_course', 'languages', 'technologies', 'methodologies', 'technology_categories', 'course_language', 'course_technology', 'course_methodology', 'jobs', 'failed_jobs', 'migrations', 'market_entities', 'competency_course', 'pe_alignment_competency_results')), ' de 17'),
           (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('syllabus', 'courses', 'careers', 'career_course', 'languages', 'technologies', 'methodologies', 'technology_categories', 'course_language', 'course_technology', 'course_methodology', 'jobs', 'failed_jobs', 'migrations', 'market_entities', 'competency_course', 'pe_alignment_competency_results')) = 17, 1
    UNION ALL
    SELECT 5, 'syllabus.filename', 'varchar(255) NOT NULL',
           (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'YES', ' NULL', ' NOT NULL')) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus' AND COLUMN_NAME = 'filename'),
           (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'YES', ' NULL', ' NOT NULL')) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus' AND COLUMN_NAME = 'filename') <=> 'varchar(255) NOT NULL', 1
    UNION ALL
    SELECT 6, 'syllabus.path', 'varchar(255) NOT NULL',
           (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'YES', ' NULL', ' NOT NULL')) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus' AND COLUMN_NAME = 'path'),
           (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'YES', ' NULL', ' NOT NULL')) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus' AND COLUMN_NAME = 'path') <=> 'varchar(255) NOT NULL', 1
    UNION ALL
    SELECT 7, 'syllabus.status', "enum('pending','processing','processed','failed') NOT NULL",
           (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'YES', ' NULL', ' NOT NULL')) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus' AND COLUMN_NAME = 'status'),
           (SELECT CONCAT(COLUMN_TYPE, IF(IS_NULLABLE = 'YES', ' NULL', ' NOT NULL')) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus' AND COLUMN_NAME = 'status')
               <=> "enum('pending','processing','processed','failed') NOT NULL", 1
    UNION ALL
    SELECT 8, 'syllabus: columnas que usa la aplicación (id, raw_text, structured_data, created_at, updated_at)', '5 de 5',
           CONCAT((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus'
                   AND COLUMN_NAME IN ('id', 'raw_text', 'structured_data', 'created_at', 'updated_at')), ' de 5'),
           (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus'
            AND COLUMN_NAME IN ('id', 'raw_text', 'structured_data', 'created_at', 'updated_at')) = 5, 1
    UNION ALL
    SELECT 9, 'syllabus: las 18 columnas nuevas todavía no existen', '0',
           CAST((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus'
                 AND COLUMN_NAME IN ('source', 'external_model', 'external_id', 'codigo_familia', 'external_version', 'periodo', 'periodo_orden', 'tipo_plantilla', 'course_code', 'course_name', 'course_id', 'careers', 'approved_at', 'approved_at_source', 'content', 'content_hash', 'superseded_at', 'superseded_by')) AS CHAR),
           (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'syllabus'
            AND COLUMN_NAME IN ('source', 'external_model', 'external_id', 'codigo_familia', 'external_version', 'periodo', 'periodo_orden', 'tipo_plantilla', 'course_code', 'course_name', 'course_id', 'careers', 'approved_at', 'approved_at_source', 'content', 'content_hash', 'superseded_at', 'superseded_by')) = 0, 1
    UNION ALL
    SELECT 10, 'courses.code todavía no existe', '0',
           CAST((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'code') AS CHAR),
           (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'code') = 0, 1
    UNION ALL
    SELECT 11, 'Índices nuevos todavía no existen', '0',
           CAST((SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                 AND INDEX_NAME IN ('syllabus_external_unique', 'syllabus_family_index', 'syllabus_superseded_at_index',
                                    'syllabus_course_id_index', 'courses_code_unique')) AS CHAR),
           (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
            AND INDEX_NAME IN ('syllabus_external_unique', 'syllabus_family_index', 'syllabus_superseded_at_index',
                               'syllabus_course_id_index', 'courses_code_unique')) = 0, 1
    UNION ALL
    SELECT 12, 'Migraciones de la integración todavía no registradas', '0',
           CAST((SELECT COUNT(*) FROM migrations WHERE migration LIKE '2026\_10\_28\_%') AS CHAR),
           (SELECT COUNT(*) FROM migrations WHERE migration LIKE '2026\_10\_28\_%') = 0, 1
    UNION ALL
    SELECT 13, 'courses.name en utf8mb4 (los scripts comparan con utf8mb4_bin)', 'utf8mb4',
           (SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'name'),
           (SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'name') <=> 'utf8mb4', 1
    UNION ALL
    SELECT 14, 'technologies.category_id existe', '1',
           CAST((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'technologies' AND COLUMN_NAME = 'category_id') AS CHAR),
           (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'technologies' AND COLUMN_NAME = 'category_id') = 1, 1
    UNION ALL
    SELECT 15, 'Categoría de tecnología ''other'' (destino de categorías desconocidas)', '1',
           CAST((SELECT COUNT(*) FROM technology_categories WHERE name = 'other') AS CHAR),
           (SELECT COUNT(*) FROM technology_categories WHERE name = 'other') = 1, 1
    UNION ALL
    SELECT 16, 'Carreras mapeadas en config/silabos_v2.php (por slug)', '7 de 7',
           CONCAT((SELECT COUNT(DISTINCT slug) FROM careers WHERE slug IN ('arquitectura-de-datos', 'ciberseguridad', 'ciencia-de-datos-e-inteligencia-artificial', 'computacion-en-la-nube', 'redes-y-comunicaciones', 'ingenieria-de-sistemas', 'tecnologias-de-informacion')), ' de 7'),
           (SELECT COUNT(DISTINCT slug) FROM careers WHERE slug IN ('arquitectura-de-datos', 'ciberseguridad', 'ciencia-de-datos-e-inteligencia-artificial', 'computacion-en-la-nube', 'redes-y-comunicaciones', 'ingenieria-de-sistemas', 'tecnologias-de-informacion')) = 7, 1
    UNION ALL
    SELECT 17, 'Cursos de la lista con id y nombre exactos (02_observatorio_asignar_codigos.sql)', '103 de 103',
           CONCAT((SELECT COUNT(*) FROM (
    SELECT 5 AS id, '30012' AS code, 'ANÁLISIS DE DATOS' COLLATE utf8mb4_bin AS name
    UNION ALL SELECT 6, '30015', 'ANÁLISIS DEL ENTORNO DE NEGOCIOS'
    UNION ALL SELECT 7, '30047', 'COMUNICACIÓN ESCRITA'
    UNION ALL SELECT 9, '30153', 'CALCULO PARA CIENCIAS E INGENIERIA'
    UNION ALL SELECT 10, '30063', 'Desarrollo de Resiliencia'
    UNION ALL SELECT 12, '30092', 'DISEÑO Y PROGRAMACIÓN DE BASE DE DATOS'
    UNION ALL SELECT 13, '30107', 'Ética Profesional'
    UNION ALL SELECT 15, '30135', 'GESTIÓN DE PROYECTOS'
    UNION ALL SELECT 16, '30136', 'GESTIÓN DE SERVICIOS ITIL'
    UNION ALL SELECT 18, '30199', 'PROGRAMACIÓN AVANZADA DE BASE DE DATOS'
    UNION ALL SELECT 19, '30228', 'Servidores y Almacenamiento'
    UNION ALL SELECT 20, '30475', 'ANALISIS DE RENDIMIENTO Y ESCALABILIDAD'
    UNION ALL SELECT 21, '30478', 'AUDITORIA DE CIBERSEGURIDAD'
    UNION ALL SELECT 22, '30480', 'AUTOMATIZACION DE PROCESOS DE NEGOCIOS'
    UNION ALL SELECT 23, '30041', 'Competencias Intrapersonales'
    UNION ALL SELECT 24, '30489', 'COMPLIANCE IT'
    UNION ALL SELECT 25, '30492', 'DATA LAKES AND DATA WAREHOUSING'
    UNION ALL SELECT 26, '30500', 'FUNDAMENTOS DE ARQUITECTURA DE DATOS'
    UNION ALL SELECT 27, '30501', 'FUNDAMENTOS DE BIG DATA'
    UNION ALL SELECT 28, '30514', 'INTRODUCCIÓN AL MODELADO DE DATOS'
    UNION ALL SELECT 29, '30176', 'MATEMÁTICA APLICADA'
    UNION ALL SELECT 30, '30089', 'DISEÑO Y DESARROLLO WEB'
    UNION ALL SELECT 32, '30521', 'SEGURIDAD EN LA NUBE'
    UNION ALL SELECT 33, '30003', 'ADMINISTRACIÓN AVANZADA DE WINDOWS'
    UNION ALL SELECT 34, '30009', 'ADMINISTRACIÓN DE WINDOWS'
    UNION ALL SELECT 39, '30037', 'CCNA SECURITY'
    UNION ALL SELECT 40, '30074', 'Dirección de Personas'
    UNION ALL SELECT 42, '30128', 'GESTIÓN DE DISPOSITIVOS TECNOLÓGICOS'
    UNION ALL SELECT 43, '30223', 'SEGURIDAD EN SISTEMAS OPERATIVOS'
    UNION ALL SELECT 44, '30474', 'ANALISIS DE AMENAZAS Y RIESGOS'
    UNION ALL SELECT 45, '30715', 'CRIPTOGRAFIA'
    UNION ALL SELECT 46, '30499', 'ESTANDARES Y NORMATIVAS DE CIBERSEGURIDAD'
    UNION ALL SELECT 47, '30502', 'FUNDAMENTOS DE CIBERSEGURIDAD'
    UNION ALL SELECT 48, '30515', 'INVESTIGACION DE INCIDENTES'
    UNION ALL SELECT 49, '30726', 'SEGURIDAD EN APLICACIONES Y BASES DE DATOS'
    UNION ALL SELECT 50, '30245', 'Técnicas de Hacking y Análisis Forense'
    UNION ALL SELECT 51, '30020', 'Analitica De Sistemas Empresariales'
    UNION ALL SELECT 52, '30038', 'Centro De Datos Cloud'
    UNION ALL SELECT 53, '30356', 'Transformacion Digital'
    UNION ALL SELECT 54, 'TBD45', 'Aprendizaje Por Refuerzo'
    UNION ALL SELECT 55, '30482', 'Big Data Analytics'
    UNION ALL SELECT 58, '30537', 'Deep Learning Y Lenguaje Natural'
    UNION ALL SELECT 59, '30498', 'Estadistica Avanzada'
    UNION ALL SELECT 60, '30503', 'Fundamentos De Ciencia De Datos'
    UNION ALL SELECT 61, '30506', 'Fundamentos De Machine Learning'
    UNION ALL SELECT 62, '30512', 'Introduccion A La Estadistica Y Analisis De Datos'
    UNION ALL SELECT 63, '30518', 'Optimizacion Y Modelado Matematico'
    UNION ALL SELECT 64, '30535', 'Sistemas Distribuidos'
    UNION ALL SELECT 65, '30523', 'Storytelling Con Datos'
    UNION ALL SELECT 66, '30534', 'Vision Computacional'
    UNION ALL SELECT 84, '30017', 'Analisis Funcional'
    UNION ALL SELECT 86, '30157', 'Integracion De Aplicaciones'
    UNION ALL SELECT 87, '30231', 'Sistemas De Control Y Gobierno De Ti'
    UNION ALL SELECT 88, '30677', 'Administracion De Infraestructura Cloud'
    UNION ALL SELECT 89, '30476', 'Analisis Y Monitoreo Cloud'
    UNION ALL SELECT 90, '30477', 'Arquitectura De Cloud Computing'
    UNION ALL SELECT 91, '30714', 'Automatizacion Y Gestion De Configuracion Cloud'
    UNION ALL SELECT 93, '30504', 'Fundamentos De Cloud Computing'
    UNION ALL SELECT 95, '30032', 'Calidad Y Pruebas De Software'
    UNION ALL SELECT 101, '30181', 'Modelos De Negocios Y Startups'
    UNION ALL SELECT 104, '30376', 'Soluciones Basadas En Internet De Las Cosas'
    UNION ALL SELECT 105, '30024', 'Aplicaciones Moviles Para Android'
    UNION ALL SELECT 106, '30025', 'Aplicaciones Moviles Para Iphone'
    UNION ALL SELECT 108, '30224', 'Seguridad Y Continuidad De Sistemas'
    UNION ALL SELECT 150, '30002', 'Administracion Avanzada De Linux'
    UNION ALL SELECT 151, '30007', 'Administracion De Linux'
    UNION ALL SELECT 153, '30257', 'Virtualizacion De Infraestructuras Tecnologicas'
    UNION ALL SELECT 155, '30130', 'Gestion De La Cadena De Suministros'
    UNION ALL SELECT 157, '30134', 'Gestion De Procesos, Simulacion Y Mejora Continua'
    UNION ALL SELECT 158, 'TBD 27', 'Analisis Y Diseno De Sistemas Avanzado'
    UNION ALL SELECT 162, '30060', 'Desarrollo De Aplicaciones Empresariales Avanzado'
    UNION ALL SELECT 164, '30632', 'Desarrollo De Soluciones Cloud Basico'
    UNION ALL SELECT 165, 'TBD70', 'Inteligencia Artificial Aplicada'
    UNION ALL SELECT 166, '30626', 'Programacion Orientada A Objetos'
    UNION ALL SELECT 167, '30690', 'Programacion Web Avanzada'
    UNION ALL SELECT 170, '30034', 'Ccna Ii - Switching, Routing Y Wireless Essentials'
    UNION ALL SELECT 171, '30035', 'Ccna Iii - Enterprise Networking, Security And Automation'
    UNION ALL SELECT 172, '30036', 'Ccna Iv-Advanced Routing'
    UNION ALL SELECT 174, '30702', 'Desarrollo De Soluciones Cloud Avanzado'
    UNION ALL SELECT 176, '30493', 'Data Mining Basico'
    UNION ALL SELECT 177, '30494', 'Data Mining Avanzado'
    UNION ALL SELECT 179, '30524', 'Tendencias En Arquitectura De Datos'
    UNION ALL SELECT 180, 'TBD 40', 'Analisis Estadistico Y Data Mining'
    UNION ALL SELECT 181, 'TBD 42', 'Arquitectura Empresarial'
    UNION ALL SELECT 182, '40001', 'Cultura De Innovacion'
    UNION ALL SELECT 183, '40064', 'Customer Centricity En Tecnologias De La Informacion'
    UNION ALL SELECT 184, '40081', 'Direccion En Transformacion Digital'
    UNION ALL SELECT 185, '40062', 'Direccion Estrategica De Datos'
    UNION ALL SELECT 186, 'TBD 41', 'Diseno De Soluciones Con Inteligencia Artificial'
    UNION ALL SELECT 187, '40004', 'Fundamentos De Investigacion'
    UNION ALL SELECT 188, 'TBD 43', 'Gestion Presupuestaria De Ti'
    UNION ALL SELECT 191, '40009', 'Innovacion Y Creacion De Valor'
    UNION ALL SELECT 192, '40038', 'Inteligencia De Negocios'
    UNION ALL SELECT 193, '40036', 'Planeamiento Estrategico De Sistemas De Informacion'
    UNION ALL SELECT 194, '40013', 'Taller De Investigacion Aplicada E Innovacion'
    UNION ALL SELECT 195, '40008', 'Tecnicas Y Herramientas De Investigacion'
    UNION ALL SELECT 196, '40039', 'Tecnologias Emergentes'
    UNION ALL SELECT 197, '40063', 'Gestion De Proyectos Basados En Analytics'
    UNION ALL SELECT 198, '40010', 'Gestion Del Emprendimiento'
    UNION ALL SELECT 199, '40028', 'Herramientas Tecnologicas Para La Gestion'
    UNION ALL SELECT 200, '40035', 'Integracion De Procesos Empresariales'
    UNION ALL SELECT 201, '40034', 'Metodologias Para La Gestion De Proyectos'
    UNION ALL SELECT 202, '40033', 'Procesos Empresariales'
           ) l JOIN courses c ON c.id = l.id AND c.name COLLATE utf8mb4_bin = l.name), ' de 103'),
           (SELECT COUNT(*) FROM (
    SELECT 5 AS id, '30012' AS code, 'ANÁLISIS DE DATOS' COLLATE utf8mb4_bin AS name
    UNION ALL SELECT 6, '30015', 'ANÁLISIS DEL ENTORNO DE NEGOCIOS'
    UNION ALL SELECT 7, '30047', 'COMUNICACIÓN ESCRITA'
    UNION ALL SELECT 9, '30153', 'CALCULO PARA CIENCIAS E INGENIERIA'
    UNION ALL SELECT 10, '30063', 'Desarrollo de Resiliencia'
    UNION ALL SELECT 12, '30092', 'DISEÑO Y PROGRAMACIÓN DE BASE DE DATOS'
    UNION ALL SELECT 13, '30107', 'Ética Profesional'
    UNION ALL SELECT 15, '30135', 'GESTIÓN DE PROYECTOS'
    UNION ALL SELECT 16, '30136', 'GESTIÓN DE SERVICIOS ITIL'
    UNION ALL SELECT 18, '30199', 'PROGRAMACIÓN AVANZADA DE BASE DE DATOS'
    UNION ALL SELECT 19, '30228', 'Servidores y Almacenamiento'
    UNION ALL SELECT 20, '30475', 'ANALISIS DE RENDIMIENTO Y ESCALABILIDAD'
    UNION ALL SELECT 21, '30478', 'AUDITORIA DE CIBERSEGURIDAD'
    UNION ALL SELECT 22, '30480', 'AUTOMATIZACION DE PROCESOS DE NEGOCIOS'
    UNION ALL SELECT 23, '30041', 'Competencias Intrapersonales'
    UNION ALL SELECT 24, '30489', 'COMPLIANCE IT'
    UNION ALL SELECT 25, '30492', 'DATA LAKES AND DATA WAREHOUSING'
    UNION ALL SELECT 26, '30500', 'FUNDAMENTOS DE ARQUITECTURA DE DATOS'
    UNION ALL SELECT 27, '30501', 'FUNDAMENTOS DE BIG DATA'
    UNION ALL SELECT 28, '30514', 'INTRODUCCIÓN AL MODELADO DE DATOS'
    UNION ALL SELECT 29, '30176', 'MATEMÁTICA APLICADA'
    UNION ALL SELECT 30, '30089', 'DISEÑO Y DESARROLLO WEB'
    UNION ALL SELECT 32, '30521', 'SEGURIDAD EN LA NUBE'
    UNION ALL SELECT 33, '30003', 'ADMINISTRACIÓN AVANZADA DE WINDOWS'
    UNION ALL SELECT 34, '30009', 'ADMINISTRACIÓN DE WINDOWS'
    UNION ALL SELECT 39, '30037', 'CCNA SECURITY'
    UNION ALL SELECT 40, '30074', 'Dirección de Personas'
    UNION ALL SELECT 42, '30128', 'GESTIÓN DE DISPOSITIVOS TECNOLÓGICOS'
    UNION ALL SELECT 43, '30223', 'SEGURIDAD EN SISTEMAS OPERATIVOS'
    UNION ALL SELECT 44, '30474', 'ANALISIS DE AMENAZAS Y RIESGOS'
    UNION ALL SELECT 45, '30715', 'CRIPTOGRAFIA'
    UNION ALL SELECT 46, '30499', 'ESTANDARES Y NORMATIVAS DE CIBERSEGURIDAD'
    UNION ALL SELECT 47, '30502', 'FUNDAMENTOS DE CIBERSEGURIDAD'
    UNION ALL SELECT 48, '30515', 'INVESTIGACION DE INCIDENTES'
    UNION ALL SELECT 49, '30726', 'SEGURIDAD EN APLICACIONES Y BASES DE DATOS'
    UNION ALL SELECT 50, '30245', 'Técnicas de Hacking y Análisis Forense'
    UNION ALL SELECT 51, '30020', 'Analitica De Sistemas Empresariales'
    UNION ALL SELECT 52, '30038', 'Centro De Datos Cloud'
    UNION ALL SELECT 53, '30356', 'Transformacion Digital'
    UNION ALL SELECT 54, 'TBD45', 'Aprendizaje Por Refuerzo'
    UNION ALL SELECT 55, '30482', 'Big Data Analytics'
    UNION ALL SELECT 58, '30537', 'Deep Learning Y Lenguaje Natural'
    UNION ALL SELECT 59, '30498', 'Estadistica Avanzada'
    UNION ALL SELECT 60, '30503', 'Fundamentos De Ciencia De Datos'
    UNION ALL SELECT 61, '30506', 'Fundamentos De Machine Learning'
    UNION ALL SELECT 62, '30512', 'Introduccion A La Estadistica Y Analisis De Datos'
    UNION ALL SELECT 63, '30518', 'Optimizacion Y Modelado Matematico'
    UNION ALL SELECT 64, '30535', 'Sistemas Distribuidos'
    UNION ALL SELECT 65, '30523', 'Storytelling Con Datos'
    UNION ALL SELECT 66, '30534', 'Vision Computacional'
    UNION ALL SELECT 84, '30017', 'Analisis Funcional'
    UNION ALL SELECT 86, '30157', 'Integracion De Aplicaciones'
    UNION ALL SELECT 87, '30231', 'Sistemas De Control Y Gobierno De Ti'
    UNION ALL SELECT 88, '30677', 'Administracion De Infraestructura Cloud'
    UNION ALL SELECT 89, '30476', 'Analisis Y Monitoreo Cloud'
    UNION ALL SELECT 90, '30477', 'Arquitectura De Cloud Computing'
    UNION ALL SELECT 91, '30714', 'Automatizacion Y Gestion De Configuracion Cloud'
    UNION ALL SELECT 93, '30504', 'Fundamentos De Cloud Computing'
    UNION ALL SELECT 95, '30032', 'Calidad Y Pruebas De Software'
    UNION ALL SELECT 101, '30181', 'Modelos De Negocios Y Startups'
    UNION ALL SELECT 104, '30376', 'Soluciones Basadas En Internet De Las Cosas'
    UNION ALL SELECT 105, '30024', 'Aplicaciones Moviles Para Android'
    UNION ALL SELECT 106, '30025', 'Aplicaciones Moviles Para Iphone'
    UNION ALL SELECT 108, '30224', 'Seguridad Y Continuidad De Sistemas'
    UNION ALL SELECT 150, '30002', 'Administracion Avanzada De Linux'
    UNION ALL SELECT 151, '30007', 'Administracion De Linux'
    UNION ALL SELECT 153, '30257', 'Virtualizacion De Infraestructuras Tecnologicas'
    UNION ALL SELECT 155, '30130', 'Gestion De La Cadena De Suministros'
    UNION ALL SELECT 157, '30134', 'Gestion De Procesos, Simulacion Y Mejora Continua'
    UNION ALL SELECT 158, 'TBD 27', 'Analisis Y Diseno De Sistemas Avanzado'
    UNION ALL SELECT 162, '30060', 'Desarrollo De Aplicaciones Empresariales Avanzado'
    UNION ALL SELECT 164, '30632', 'Desarrollo De Soluciones Cloud Basico'
    UNION ALL SELECT 165, 'TBD70', 'Inteligencia Artificial Aplicada'
    UNION ALL SELECT 166, '30626', 'Programacion Orientada A Objetos'
    UNION ALL SELECT 167, '30690', 'Programacion Web Avanzada'
    UNION ALL SELECT 170, '30034', 'Ccna Ii - Switching, Routing Y Wireless Essentials'
    UNION ALL SELECT 171, '30035', 'Ccna Iii - Enterprise Networking, Security And Automation'
    UNION ALL SELECT 172, '30036', 'Ccna Iv-Advanced Routing'
    UNION ALL SELECT 174, '30702', 'Desarrollo De Soluciones Cloud Avanzado'
    UNION ALL SELECT 176, '30493', 'Data Mining Basico'
    UNION ALL SELECT 177, '30494', 'Data Mining Avanzado'
    UNION ALL SELECT 179, '30524', 'Tendencias En Arquitectura De Datos'
    UNION ALL SELECT 180, 'TBD 40', 'Analisis Estadistico Y Data Mining'
    UNION ALL SELECT 181, 'TBD 42', 'Arquitectura Empresarial'
    UNION ALL SELECT 182, '40001', 'Cultura De Innovacion'
    UNION ALL SELECT 183, '40064', 'Customer Centricity En Tecnologias De La Informacion'
    UNION ALL SELECT 184, '40081', 'Direccion En Transformacion Digital'
    UNION ALL SELECT 185, '40062', 'Direccion Estrategica De Datos'
    UNION ALL SELECT 186, 'TBD 41', 'Diseno De Soluciones Con Inteligencia Artificial'
    UNION ALL SELECT 187, '40004', 'Fundamentos De Investigacion'
    UNION ALL SELECT 188, 'TBD 43', 'Gestion Presupuestaria De Ti'
    UNION ALL SELECT 191, '40009', 'Innovacion Y Creacion De Valor'
    UNION ALL SELECT 192, '40038', 'Inteligencia De Negocios'
    UNION ALL SELECT 193, '40036', 'Planeamiento Estrategico De Sistemas De Informacion'
    UNION ALL SELECT 194, '40013', 'Taller De Investigacion Aplicada E Innovacion'
    UNION ALL SELECT 195, '40008', 'Tecnicas Y Herramientas De Investigacion'
    UNION ALL SELECT 196, '40039', 'Tecnologias Emergentes'
    UNION ALL SELECT 197, '40063', 'Gestion De Proyectos Basados En Analytics'
    UNION ALL SELECT 198, '40010', 'Gestion Del Emprendimiento'
    UNION ALL SELECT 199, '40028', 'Herramientas Tecnologicas Para La Gestion'
    UNION ALL SELECT 200, '40035', 'Integracion De Procesos Empresariales'
    UNION ALL SELECT 201, '40034', 'Metodologias Para La Gestion De Proyectos'
    UNION ALL SELECT 202, '40033', 'Procesos Empresariales'
           ) l JOIN courses c ON c.id = l.id AND c.name COLLATE utf8mb4_bin = l.name) = 103, 0
    UNION ALL
    SELECT 18, 'Cursos de la lista cuyo nombre exacto existe una sola vez (alternativa 02b)', '103 de 103',
           CONCAT((SELECT COUNT(*) FROM (
    SELECT 5 AS id, '30012' AS code, 'ANÁLISIS DE DATOS' COLLATE utf8mb4_bin AS name
    UNION ALL SELECT 6, '30015', 'ANÁLISIS DEL ENTORNO DE NEGOCIOS'
    UNION ALL SELECT 7, '30047', 'COMUNICACIÓN ESCRITA'
    UNION ALL SELECT 9, '30153', 'CALCULO PARA CIENCIAS E INGENIERIA'
    UNION ALL SELECT 10, '30063', 'Desarrollo de Resiliencia'
    UNION ALL SELECT 12, '30092', 'DISEÑO Y PROGRAMACIÓN DE BASE DE DATOS'
    UNION ALL SELECT 13, '30107', 'Ética Profesional'
    UNION ALL SELECT 15, '30135', 'GESTIÓN DE PROYECTOS'
    UNION ALL SELECT 16, '30136', 'GESTIÓN DE SERVICIOS ITIL'
    UNION ALL SELECT 18, '30199', 'PROGRAMACIÓN AVANZADA DE BASE DE DATOS'
    UNION ALL SELECT 19, '30228', 'Servidores y Almacenamiento'
    UNION ALL SELECT 20, '30475', 'ANALISIS DE RENDIMIENTO Y ESCALABILIDAD'
    UNION ALL SELECT 21, '30478', 'AUDITORIA DE CIBERSEGURIDAD'
    UNION ALL SELECT 22, '30480', 'AUTOMATIZACION DE PROCESOS DE NEGOCIOS'
    UNION ALL SELECT 23, '30041', 'Competencias Intrapersonales'
    UNION ALL SELECT 24, '30489', 'COMPLIANCE IT'
    UNION ALL SELECT 25, '30492', 'DATA LAKES AND DATA WAREHOUSING'
    UNION ALL SELECT 26, '30500', 'FUNDAMENTOS DE ARQUITECTURA DE DATOS'
    UNION ALL SELECT 27, '30501', 'FUNDAMENTOS DE BIG DATA'
    UNION ALL SELECT 28, '30514', 'INTRODUCCIÓN AL MODELADO DE DATOS'
    UNION ALL SELECT 29, '30176', 'MATEMÁTICA APLICADA'
    UNION ALL SELECT 30, '30089', 'DISEÑO Y DESARROLLO WEB'
    UNION ALL SELECT 32, '30521', 'SEGURIDAD EN LA NUBE'
    UNION ALL SELECT 33, '30003', 'ADMINISTRACIÓN AVANZADA DE WINDOWS'
    UNION ALL SELECT 34, '30009', 'ADMINISTRACIÓN DE WINDOWS'
    UNION ALL SELECT 39, '30037', 'CCNA SECURITY'
    UNION ALL SELECT 40, '30074', 'Dirección de Personas'
    UNION ALL SELECT 42, '30128', 'GESTIÓN DE DISPOSITIVOS TECNOLÓGICOS'
    UNION ALL SELECT 43, '30223', 'SEGURIDAD EN SISTEMAS OPERATIVOS'
    UNION ALL SELECT 44, '30474', 'ANALISIS DE AMENAZAS Y RIESGOS'
    UNION ALL SELECT 45, '30715', 'CRIPTOGRAFIA'
    UNION ALL SELECT 46, '30499', 'ESTANDARES Y NORMATIVAS DE CIBERSEGURIDAD'
    UNION ALL SELECT 47, '30502', 'FUNDAMENTOS DE CIBERSEGURIDAD'
    UNION ALL SELECT 48, '30515', 'INVESTIGACION DE INCIDENTES'
    UNION ALL SELECT 49, '30726', 'SEGURIDAD EN APLICACIONES Y BASES DE DATOS'
    UNION ALL SELECT 50, '30245', 'Técnicas de Hacking y Análisis Forense'
    UNION ALL SELECT 51, '30020', 'Analitica De Sistemas Empresariales'
    UNION ALL SELECT 52, '30038', 'Centro De Datos Cloud'
    UNION ALL SELECT 53, '30356', 'Transformacion Digital'
    UNION ALL SELECT 54, 'TBD45', 'Aprendizaje Por Refuerzo'
    UNION ALL SELECT 55, '30482', 'Big Data Analytics'
    UNION ALL SELECT 58, '30537', 'Deep Learning Y Lenguaje Natural'
    UNION ALL SELECT 59, '30498', 'Estadistica Avanzada'
    UNION ALL SELECT 60, '30503', 'Fundamentos De Ciencia De Datos'
    UNION ALL SELECT 61, '30506', 'Fundamentos De Machine Learning'
    UNION ALL SELECT 62, '30512', 'Introduccion A La Estadistica Y Analisis De Datos'
    UNION ALL SELECT 63, '30518', 'Optimizacion Y Modelado Matematico'
    UNION ALL SELECT 64, '30535', 'Sistemas Distribuidos'
    UNION ALL SELECT 65, '30523', 'Storytelling Con Datos'
    UNION ALL SELECT 66, '30534', 'Vision Computacional'
    UNION ALL SELECT 84, '30017', 'Analisis Funcional'
    UNION ALL SELECT 86, '30157', 'Integracion De Aplicaciones'
    UNION ALL SELECT 87, '30231', 'Sistemas De Control Y Gobierno De Ti'
    UNION ALL SELECT 88, '30677', 'Administracion De Infraestructura Cloud'
    UNION ALL SELECT 89, '30476', 'Analisis Y Monitoreo Cloud'
    UNION ALL SELECT 90, '30477', 'Arquitectura De Cloud Computing'
    UNION ALL SELECT 91, '30714', 'Automatizacion Y Gestion De Configuracion Cloud'
    UNION ALL SELECT 93, '30504', 'Fundamentos De Cloud Computing'
    UNION ALL SELECT 95, '30032', 'Calidad Y Pruebas De Software'
    UNION ALL SELECT 101, '30181', 'Modelos De Negocios Y Startups'
    UNION ALL SELECT 104, '30376', 'Soluciones Basadas En Internet De Las Cosas'
    UNION ALL SELECT 105, '30024', 'Aplicaciones Moviles Para Android'
    UNION ALL SELECT 106, '30025', 'Aplicaciones Moviles Para Iphone'
    UNION ALL SELECT 108, '30224', 'Seguridad Y Continuidad De Sistemas'
    UNION ALL SELECT 150, '30002', 'Administracion Avanzada De Linux'
    UNION ALL SELECT 151, '30007', 'Administracion De Linux'
    UNION ALL SELECT 153, '30257', 'Virtualizacion De Infraestructuras Tecnologicas'
    UNION ALL SELECT 155, '30130', 'Gestion De La Cadena De Suministros'
    UNION ALL SELECT 157, '30134', 'Gestion De Procesos, Simulacion Y Mejora Continua'
    UNION ALL SELECT 158, 'TBD 27', 'Analisis Y Diseno De Sistemas Avanzado'
    UNION ALL SELECT 162, '30060', 'Desarrollo De Aplicaciones Empresariales Avanzado'
    UNION ALL SELECT 164, '30632', 'Desarrollo De Soluciones Cloud Basico'
    UNION ALL SELECT 165, 'TBD70', 'Inteligencia Artificial Aplicada'
    UNION ALL SELECT 166, '30626', 'Programacion Orientada A Objetos'
    UNION ALL SELECT 167, '30690', 'Programacion Web Avanzada'
    UNION ALL SELECT 170, '30034', 'Ccna Ii - Switching, Routing Y Wireless Essentials'
    UNION ALL SELECT 171, '30035', 'Ccna Iii - Enterprise Networking, Security And Automation'
    UNION ALL SELECT 172, '30036', 'Ccna Iv-Advanced Routing'
    UNION ALL SELECT 174, '30702', 'Desarrollo De Soluciones Cloud Avanzado'
    UNION ALL SELECT 176, '30493', 'Data Mining Basico'
    UNION ALL SELECT 177, '30494', 'Data Mining Avanzado'
    UNION ALL SELECT 179, '30524', 'Tendencias En Arquitectura De Datos'
    UNION ALL SELECT 180, 'TBD 40', 'Analisis Estadistico Y Data Mining'
    UNION ALL SELECT 181, 'TBD 42', 'Arquitectura Empresarial'
    UNION ALL SELECT 182, '40001', 'Cultura De Innovacion'
    UNION ALL SELECT 183, '40064', 'Customer Centricity En Tecnologias De La Informacion'
    UNION ALL SELECT 184, '40081', 'Direccion En Transformacion Digital'
    UNION ALL SELECT 185, '40062', 'Direccion Estrategica De Datos'
    UNION ALL SELECT 186, 'TBD 41', 'Diseno De Soluciones Con Inteligencia Artificial'
    UNION ALL SELECT 187, '40004', 'Fundamentos De Investigacion'
    UNION ALL SELECT 188, 'TBD 43', 'Gestion Presupuestaria De Ti'
    UNION ALL SELECT 191, '40009', 'Innovacion Y Creacion De Valor'
    UNION ALL SELECT 192, '40038', 'Inteligencia De Negocios'
    UNION ALL SELECT 193, '40036', 'Planeamiento Estrategico De Sistemas De Informacion'
    UNION ALL SELECT 194, '40013', 'Taller De Investigacion Aplicada E Innovacion'
    UNION ALL SELECT 195, '40008', 'Tecnicas Y Herramientas De Investigacion'
    UNION ALL SELECT 196, '40039', 'Tecnologias Emergentes'
    UNION ALL SELECT 197, '40063', 'Gestion De Proyectos Basados En Analytics'
    UNION ALL SELECT 198, '40010', 'Gestion Del Emprendimiento'
    UNION ALL SELECT 199, '40028', 'Herramientas Tecnologicas Para La Gestion'
    UNION ALL SELECT 200, '40035', 'Integracion De Procesos Empresariales'
    UNION ALL SELECT 201, '40034', 'Metodologias Para La Gestion De Proyectos'
    UNION ALL SELECT 202, '40033', 'Procesos Empresariales'
           ) l WHERE (SELECT COUNT(*) FROM courses x WHERE x.name COLLATE utf8mb4_bin = l.name) = 1), ' de 103'),
           (SELECT COUNT(*) FROM (
    SELECT 5 AS id, '30012' AS code, 'ANÁLISIS DE DATOS' COLLATE utf8mb4_bin AS name
    UNION ALL SELECT 6, '30015', 'ANÁLISIS DEL ENTORNO DE NEGOCIOS'
    UNION ALL SELECT 7, '30047', 'COMUNICACIÓN ESCRITA'
    UNION ALL SELECT 9, '30153', 'CALCULO PARA CIENCIAS E INGENIERIA'
    UNION ALL SELECT 10, '30063', 'Desarrollo de Resiliencia'
    UNION ALL SELECT 12, '30092', 'DISEÑO Y PROGRAMACIÓN DE BASE DE DATOS'
    UNION ALL SELECT 13, '30107', 'Ética Profesional'
    UNION ALL SELECT 15, '30135', 'GESTIÓN DE PROYECTOS'
    UNION ALL SELECT 16, '30136', 'GESTIÓN DE SERVICIOS ITIL'
    UNION ALL SELECT 18, '30199', 'PROGRAMACIÓN AVANZADA DE BASE DE DATOS'
    UNION ALL SELECT 19, '30228', 'Servidores y Almacenamiento'
    UNION ALL SELECT 20, '30475', 'ANALISIS DE RENDIMIENTO Y ESCALABILIDAD'
    UNION ALL SELECT 21, '30478', 'AUDITORIA DE CIBERSEGURIDAD'
    UNION ALL SELECT 22, '30480', 'AUTOMATIZACION DE PROCESOS DE NEGOCIOS'
    UNION ALL SELECT 23, '30041', 'Competencias Intrapersonales'
    UNION ALL SELECT 24, '30489', 'COMPLIANCE IT'
    UNION ALL SELECT 25, '30492', 'DATA LAKES AND DATA WAREHOUSING'
    UNION ALL SELECT 26, '30500', 'FUNDAMENTOS DE ARQUITECTURA DE DATOS'
    UNION ALL SELECT 27, '30501', 'FUNDAMENTOS DE BIG DATA'
    UNION ALL SELECT 28, '30514', 'INTRODUCCIÓN AL MODELADO DE DATOS'
    UNION ALL SELECT 29, '30176', 'MATEMÁTICA APLICADA'
    UNION ALL SELECT 30, '30089', 'DISEÑO Y DESARROLLO WEB'
    UNION ALL SELECT 32, '30521', 'SEGURIDAD EN LA NUBE'
    UNION ALL SELECT 33, '30003', 'ADMINISTRACIÓN AVANZADA DE WINDOWS'
    UNION ALL SELECT 34, '30009', 'ADMINISTRACIÓN DE WINDOWS'
    UNION ALL SELECT 39, '30037', 'CCNA SECURITY'
    UNION ALL SELECT 40, '30074', 'Dirección de Personas'
    UNION ALL SELECT 42, '30128', 'GESTIÓN DE DISPOSITIVOS TECNOLÓGICOS'
    UNION ALL SELECT 43, '30223', 'SEGURIDAD EN SISTEMAS OPERATIVOS'
    UNION ALL SELECT 44, '30474', 'ANALISIS DE AMENAZAS Y RIESGOS'
    UNION ALL SELECT 45, '30715', 'CRIPTOGRAFIA'
    UNION ALL SELECT 46, '30499', 'ESTANDARES Y NORMATIVAS DE CIBERSEGURIDAD'
    UNION ALL SELECT 47, '30502', 'FUNDAMENTOS DE CIBERSEGURIDAD'
    UNION ALL SELECT 48, '30515', 'INVESTIGACION DE INCIDENTES'
    UNION ALL SELECT 49, '30726', 'SEGURIDAD EN APLICACIONES Y BASES DE DATOS'
    UNION ALL SELECT 50, '30245', 'Técnicas de Hacking y Análisis Forense'
    UNION ALL SELECT 51, '30020', 'Analitica De Sistemas Empresariales'
    UNION ALL SELECT 52, '30038', 'Centro De Datos Cloud'
    UNION ALL SELECT 53, '30356', 'Transformacion Digital'
    UNION ALL SELECT 54, 'TBD45', 'Aprendizaje Por Refuerzo'
    UNION ALL SELECT 55, '30482', 'Big Data Analytics'
    UNION ALL SELECT 58, '30537', 'Deep Learning Y Lenguaje Natural'
    UNION ALL SELECT 59, '30498', 'Estadistica Avanzada'
    UNION ALL SELECT 60, '30503', 'Fundamentos De Ciencia De Datos'
    UNION ALL SELECT 61, '30506', 'Fundamentos De Machine Learning'
    UNION ALL SELECT 62, '30512', 'Introduccion A La Estadistica Y Analisis De Datos'
    UNION ALL SELECT 63, '30518', 'Optimizacion Y Modelado Matematico'
    UNION ALL SELECT 64, '30535', 'Sistemas Distribuidos'
    UNION ALL SELECT 65, '30523', 'Storytelling Con Datos'
    UNION ALL SELECT 66, '30534', 'Vision Computacional'
    UNION ALL SELECT 84, '30017', 'Analisis Funcional'
    UNION ALL SELECT 86, '30157', 'Integracion De Aplicaciones'
    UNION ALL SELECT 87, '30231', 'Sistemas De Control Y Gobierno De Ti'
    UNION ALL SELECT 88, '30677', 'Administracion De Infraestructura Cloud'
    UNION ALL SELECT 89, '30476', 'Analisis Y Monitoreo Cloud'
    UNION ALL SELECT 90, '30477', 'Arquitectura De Cloud Computing'
    UNION ALL SELECT 91, '30714', 'Automatizacion Y Gestion De Configuracion Cloud'
    UNION ALL SELECT 93, '30504', 'Fundamentos De Cloud Computing'
    UNION ALL SELECT 95, '30032', 'Calidad Y Pruebas De Software'
    UNION ALL SELECT 101, '30181', 'Modelos De Negocios Y Startups'
    UNION ALL SELECT 104, '30376', 'Soluciones Basadas En Internet De Las Cosas'
    UNION ALL SELECT 105, '30024', 'Aplicaciones Moviles Para Android'
    UNION ALL SELECT 106, '30025', 'Aplicaciones Moviles Para Iphone'
    UNION ALL SELECT 108, '30224', 'Seguridad Y Continuidad De Sistemas'
    UNION ALL SELECT 150, '30002', 'Administracion Avanzada De Linux'
    UNION ALL SELECT 151, '30007', 'Administracion De Linux'
    UNION ALL SELECT 153, '30257', 'Virtualizacion De Infraestructuras Tecnologicas'
    UNION ALL SELECT 155, '30130', 'Gestion De La Cadena De Suministros'
    UNION ALL SELECT 157, '30134', 'Gestion De Procesos, Simulacion Y Mejora Continua'
    UNION ALL SELECT 158, 'TBD 27', 'Analisis Y Diseno De Sistemas Avanzado'
    UNION ALL SELECT 162, '30060', 'Desarrollo De Aplicaciones Empresariales Avanzado'
    UNION ALL SELECT 164, '30632', 'Desarrollo De Soluciones Cloud Basico'
    UNION ALL SELECT 165, 'TBD70', 'Inteligencia Artificial Aplicada'
    UNION ALL SELECT 166, '30626', 'Programacion Orientada A Objetos'
    UNION ALL SELECT 167, '30690', 'Programacion Web Avanzada'
    UNION ALL SELECT 170, '30034', 'Ccna Ii - Switching, Routing Y Wireless Essentials'
    UNION ALL SELECT 171, '30035', 'Ccna Iii - Enterprise Networking, Security And Automation'
    UNION ALL SELECT 172, '30036', 'Ccna Iv-Advanced Routing'
    UNION ALL SELECT 174, '30702', 'Desarrollo De Soluciones Cloud Avanzado'
    UNION ALL SELECT 176, '30493', 'Data Mining Basico'
    UNION ALL SELECT 177, '30494', 'Data Mining Avanzado'
    UNION ALL SELECT 179, '30524', 'Tendencias En Arquitectura De Datos'
    UNION ALL SELECT 180, 'TBD 40', 'Analisis Estadistico Y Data Mining'
    UNION ALL SELECT 181, 'TBD 42', 'Arquitectura Empresarial'
    UNION ALL SELECT 182, '40001', 'Cultura De Innovacion'
    UNION ALL SELECT 183, '40064', 'Customer Centricity En Tecnologias De La Informacion'
    UNION ALL SELECT 184, '40081', 'Direccion En Transformacion Digital'
    UNION ALL SELECT 185, '40062', 'Direccion Estrategica De Datos'
    UNION ALL SELECT 186, 'TBD 41', 'Diseno De Soluciones Con Inteligencia Artificial'
    UNION ALL SELECT 187, '40004', 'Fundamentos De Investigacion'
    UNION ALL SELECT 188, 'TBD 43', 'Gestion Presupuestaria De Ti'
    UNION ALL SELECT 191, '40009', 'Innovacion Y Creacion De Valor'
    UNION ALL SELECT 192, '40038', 'Inteligencia De Negocios'
    UNION ALL SELECT 193, '40036', 'Planeamiento Estrategico De Sistemas De Informacion'
    UNION ALL SELECT 194, '40013', 'Taller De Investigacion Aplicada E Innovacion'
    UNION ALL SELECT 195, '40008', 'Tecnicas Y Herramientas De Investigacion'
    UNION ALL SELECT 196, '40039', 'Tecnologias Emergentes'
    UNION ALL SELECT 197, '40063', 'Gestion De Proyectos Basados En Analytics'
    UNION ALL SELECT 198, '40010', 'Gestion Del Emprendimiento'
    UNION ALL SELECT 199, '40028', 'Herramientas Tecnologicas Para La Gestion'
    UNION ALL SELECT 200, '40035', 'Integracion De Procesos Empresariales'
    UNION ALL SELECT 201, '40034', 'Metodologias Para La Gestion De Proyectos'
    UNION ALL SELECT 202, '40033', 'Procesos Empresariales'
           ) l WHERE (SELECT COUNT(*) FROM courses x WHERE x.name COLLATE utf8mb4_bin = l.name) = 1) = 103, 0
    UNION ALL
    SELECT 19, 'Jobs pendientes en la cola del observatorio (un worker los ejecutará)', '0',
           CAST((SELECT COUNT(*) FROM jobs) AS CHAR), (SELECT COUNT(*) FROM jobs) = 0, 0
) r
ORDER BY r.n;
