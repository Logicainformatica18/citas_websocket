-- =====================================================================
-- 02b_observatorio_asignar_codigos_por_nombre.sql
-- BASE: la del observatorio (en local, observatorio_2). Selecciónala como
-- predeterminada; el script no usa USE.
--
-- ALTERNATIVA a 02_observatorio_asignar_codigos.sql para cuando
-- 00_verificar_observatorio.sql muestra que en esta base los ids o los
-- nombres de los cursos no coinciden con los de local. Usa solo UNO de los
-- dos. Requiere 01_observatorio_aplicar.sql ya ejecutado.
--
-- Busca cada curso por su NOMBRE EXACTO (mayúsculas, tildes y espacios
-- incluidos; utf8mb4_bin), no por id. Mismos 103 códigos que el 02.
--
-- Cómo usarlo:
--   1. Ejecuta SOLO el bloque 1 (selecciónalo y Ctrl+Shift+Enter): lista
--      los cursos que este script NO podrá asignar, con el motivo.
--      Decide qué hacer con ellos antes de seguir (normalmente,
--      vincularlos después desde la página "Vinculación de cursos").
--   2. Ejecuta el archivo completo. Es seguro correrlo dos veces: cada
--      UPDATE solo cambia un curso sin código, con ese nombre exacto, y si
--      ningún otro curso tiene ya ese código.
--      Si un nombre está repetido, ese UPDATE falla por el índice único
--      (Duplicate entry) y no cambia nada: por eso el bloque 1 los muestra
--      antes. Con "Stop on error" activo, Workbench se detiene ahí.
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Cursos que NO se asignarán (revisar antes de seguir).
--    motivo:
--      NO ENCONTRADO      → ningún curso con ese nombre exacto
--      NOMBRE REPETIDO    → más de un curso con ese nombre (no se decide solo)
--      YA TIENE OTRO CÓDIGO / CÓDIGO EN OTRO CURSO → conflicto a revisar
--    Si todo está bien: ninguna fila (o solo los ya asignados en una segunda corrida).
-- ---------------------------------------------------------------------

SELECT l.code, l.name AS nombre_buscado, x.motivo, x.ids
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
JOIN (
    SELECT l2.code,
           CASE WHEN (SELECT COUNT(*) FROM courses c WHERE c.name COLLATE utf8mb4_bin = l2.name) = 0 THEN 'NO ENCONTRADO'
                WHEN (SELECT COUNT(*) FROM courses c WHERE c.name COLLATE utf8mb4_bin = l2.name) > 1 THEN 'NOMBRE REPETIDO'
                WHEN (SELECT c.code FROM courses c WHERE c.name COLLATE utf8mb4_bin = l2.name) = l2.code THEN 'YA ASIGNADO'
                WHEN (SELECT c.code FROM courses c WHERE c.name COLLATE utf8mb4_bin = l2.name) IS NOT NULL THEN 'YA TIENE OTRO CÓDIGO'
                WHEN EXISTS (SELECT 1 FROM courses o WHERE o.code = l2.code) THEN 'CÓDIGO EN OTRO CURSO'
           END AS motivo,
           (SELECT GROUP_CONCAT(c.id ORDER BY c.id) FROM courses c WHERE c.name COLLATE utf8mb4_bin = l2.name) AS ids
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
    ) l2
) x ON x.code = l.code
WHERE x.motivo IS NOT NULL
ORDER BY x.motivo, l.name;

SELECT COUNT(*) AS cursos_con_codigo_antes FROM courses WHERE code IS NOT NULL;


-- ---------------------------------------------------------------------
-- 2. Asignaciones por nombre exacto (una por curso).
--    El JOIN con `o` busca otro curso que ya tenga el código: si existe,
--    o.id no es NULL y el UPDATE no hace nada.
-- ---------------------------------------------------------------------

UPDATE courses c LEFT JOIN courses o ON o.code = '30012' SET c.code = '30012', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'ANÁLISIS DE DATOS' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30015' SET c.code = '30015', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'ANÁLISIS DEL ENTORNO DE NEGOCIOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30047' SET c.code = '30047', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'COMUNICACIÓN ESCRITA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30153' SET c.code = '30153', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'CALCULO PARA CIENCIAS E INGENIERIA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30063' SET c.code = '30063', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Desarrollo de Resiliencia' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30092' SET c.code = '30092', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'DISEÑO Y PROGRAMACIÓN DE BASE DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30107' SET c.code = '30107', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Ética Profesional' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30135' SET c.code = '30135', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'GESTIÓN DE PROYECTOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30136' SET c.code = '30136', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'GESTIÓN DE SERVICIOS ITIL' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30199' SET c.code = '30199', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'PROGRAMACIÓN AVANZADA DE BASE DE DATOS' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30228' SET c.code = '30228', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Servidores y Almacenamiento' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30475' SET c.code = '30475', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'ANALISIS DE RENDIMIENTO Y ESCALABILIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30478' SET c.code = '30478', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'AUDITORIA DE CIBERSEGURIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30480' SET c.code = '30480', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'AUTOMATIZACION DE PROCESOS DE NEGOCIOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30041' SET c.code = '30041', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Competencias Intrapersonales' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30489' SET c.code = '30489', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'COMPLIANCE IT' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30492' SET c.code = '30492', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'DATA LAKES AND DATA WAREHOUSING' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30500' SET c.code = '30500', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'FUNDAMENTOS DE ARQUITECTURA DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30501' SET c.code = '30501', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'FUNDAMENTOS DE BIG DATA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30514' SET c.code = '30514', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'INTRODUCCIÓN AL MODELADO DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30176' SET c.code = '30176', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'MATEMÁTICA APLICADA' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30089' SET c.code = '30089', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'DISEÑO Y DESARROLLO WEB' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30521' SET c.code = '30521', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'SEGURIDAD EN LA NUBE' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30003' SET c.code = '30003', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'ADMINISTRACIÓN AVANZADA DE WINDOWS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30009' SET c.code = '30009', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'ADMINISTRACIÓN DE WINDOWS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30037' SET c.code = '30037', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'CCNA SECURITY' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30074' SET c.code = '30074', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Dirección de Personas' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30128' SET c.code = '30128', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'GESTIÓN DE DISPOSITIVOS TECNOLÓGICOS' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30223' SET c.code = '30223', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'SEGURIDAD EN SISTEMAS OPERATIVOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30474' SET c.code = '30474', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'ANALISIS DE AMENAZAS Y RIESGOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30715' SET c.code = '30715', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'CRIPTOGRAFIA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30499' SET c.code = '30499', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'ESTANDARES Y NORMATIVAS DE CIBERSEGURIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30502' SET c.code = '30502', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'FUNDAMENTOS DE CIBERSEGURIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30515' SET c.code = '30515', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'INVESTIGACION DE INCIDENTES' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30726' SET c.code = '30726', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'SEGURIDAD EN APLICACIONES Y BASES DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30245' SET c.code = '30245', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Técnicas de Hacking y Análisis Forense' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30020' SET c.code = '30020', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Analitica De Sistemas Empresariales' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30038' SET c.code = '30038', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Centro De Datos Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30356' SET c.code = '30356', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Transformacion Digital' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD45' SET c.code = 'TBD45', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Aprendizaje Por Refuerzo' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30482' SET c.code = '30482', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Big Data Analytics' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30537' SET c.code = '30537', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Deep Learning Y Lenguaje Natural' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30498' SET c.code = '30498', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Estadistica Avanzada' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30503' SET c.code = '30503', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Fundamentos De Ciencia De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30506' SET c.code = '30506', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Fundamentos De Machine Learning' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30512' SET c.code = '30512', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Introduccion A La Estadistica Y Analisis De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30518' SET c.code = '30518', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Optimizacion Y Modelado Matematico' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30535' SET c.code = '30535', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Sistemas Distribuidos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30523' SET c.code = '30523', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Storytelling Con Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30534' SET c.code = '30534', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Vision Computacional' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30017' SET c.code = '30017', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Analisis Funcional' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30157' SET c.code = '30157', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Integracion De Aplicaciones' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30231' SET c.code = '30231', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Sistemas De Control Y Gobierno De Ti' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30677' SET c.code = '30677', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Administracion De Infraestructura Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30476' SET c.code = '30476', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Analisis Y Monitoreo Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30477' SET c.code = '30477', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Arquitectura De Cloud Computing' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30714' SET c.code = '30714', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Automatizacion Y Gestion De Configuracion Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30504' SET c.code = '30504', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Fundamentos De Cloud Computing' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30032' SET c.code = '30032', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Calidad Y Pruebas De Software' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30181' SET c.code = '30181', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Modelos De Negocios Y Startups' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30376' SET c.code = '30376', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Soluciones Basadas En Internet De Las Cosas' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30024' SET c.code = '30024', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Aplicaciones Moviles Para Android' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30025' SET c.code = '30025', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Aplicaciones Moviles Para Iphone' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30224' SET c.code = '30224', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Seguridad Y Continuidad De Sistemas' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30002' SET c.code = '30002', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Administracion Avanzada De Linux' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30007' SET c.code = '30007', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Administracion De Linux' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30257' SET c.code = '30257', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Virtualizacion De Infraestructuras Tecnologicas' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30130' SET c.code = '30130', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Gestion De La Cadena De Suministros' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30134' SET c.code = '30134', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Gestion De Procesos, Simulacion Y Mejora Continua' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 27' SET c.code = 'TBD 27', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Analisis Y Diseno De Sistemas Avanzado' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30060' SET c.code = '30060', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Desarrollo De Aplicaciones Empresariales Avanzado' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30632' SET c.code = '30632', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Desarrollo De Soluciones Cloud Basico' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD70' SET c.code = 'TBD70', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Inteligencia Artificial Aplicada' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30626' SET c.code = '30626', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Programacion Orientada A Objetos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30690' SET c.code = '30690', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Programacion Web Avanzada' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30034' SET c.code = '30034', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Ccna Ii - Switching, Routing Y Wireless Essentials' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30035' SET c.code = '30035', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Ccna Iii - Enterprise Networking, Security And Automation' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30036' SET c.code = '30036', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Ccna Iv-Advanced Routing' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30702' SET c.code = '30702', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Desarrollo De Soluciones Cloud Avanzado' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30493' SET c.code = '30493', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Data Mining Basico' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30494' SET c.code = '30494', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Data Mining Avanzado' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30524' SET c.code = '30524', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Tendencias En Arquitectura De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 40' SET c.code = 'TBD 40', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Analisis Estadistico Y Data Mining' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 42' SET c.code = 'TBD 42', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Arquitectura Empresarial' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40001' SET c.code = '40001', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Cultura De Innovacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40064' SET c.code = '40064', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Customer Centricity En Tecnologias De La Informacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40081' SET c.code = '40081', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Direccion En Transformacion Digital' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40062' SET c.code = '40062', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Direccion Estrategica De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 41' SET c.code = 'TBD 41', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Diseno De Soluciones Con Inteligencia Artificial' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40004' SET c.code = '40004', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Fundamentos De Investigacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 43' SET c.code = 'TBD 43', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Gestion Presupuestaria De Ti' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40009' SET c.code = '40009', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Innovacion Y Creacion De Valor' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40038' SET c.code = '40038', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Inteligencia De Negocios' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40036' SET c.code = '40036', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Planeamiento Estrategico De Sistemas De Informacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40013' SET c.code = '40013', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Taller De Investigacion Aplicada E Innovacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40008' SET c.code = '40008', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Tecnicas Y Herramientas De Investigacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40039' SET c.code = '40039', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Tecnologias Emergentes' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40063' SET c.code = '40063', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Gestion De Proyectos Basados En Analytics' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40010' SET c.code = '40010', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Gestion Del Emprendimiento' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40028' SET c.code = '40028', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Herramientas Tecnologicas Para La Gestion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40035' SET c.code = '40035', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Integracion De Procesos Empresariales' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40034' SET c.code = '40034', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Metodologias Para La Gestion De Proyectos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40033' SET c.code = '40033', c.updated_at = NOW() WHERE c.name COLLATE utf8mb4_bin = 'Procesos Empresariales' AND c.code IS NULL AND o.id IS NULL;


-- ---------------------------------------------------------------------
-- 3. Después. Esperado: 103 más los que ya tuvieran código antes.
-- ---------------------------------------------------------------------

SELECT COUNT(*) AS cursos_con_codigo_despues FROM courses WHERE code IS NOT NULL;
