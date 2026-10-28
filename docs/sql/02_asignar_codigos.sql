-- =====================================================================
-- Observatorio · 02_asignar_codigos.sql
-- Asigna courses.code (código del curso en silabos_v2) a los cursos con
-- correspondencia exacta. Ejecutar DESPUÉS de 01_integracion_silabos_up.sql.
--
-- Origen: docs/correspondencia-cursos.csv, solo filas tipo "exacto" sin
-- aviso en "observacion" (103 de 122 cursos). El resto se vincula desde la
-- página "Vinculación de cursos".
--
-- Seguro de correr dos veces. Cada UPDATE solo cambia el curso si:
--   - el id y el nombre coinciden (protege si los ids difieren entre bases);
--   - el curso todavía no tiene código;
--   - ningún otro curso tiene ya ese código.
-- Si no se cumple, el UPDATE afecta 0 filas y no da error.
--
-- No reprocesa sílabos pendientes de vinculación: en el primer despliegue
-- no hay. Si los hubiera, usar `php artisan silabos:asignar-codigos`,
-- que además los envía a procesar.
--
-- Ejecutar:
--   mysql -h HOST -P PUERTO -u USUARIO -p BASE < 02_asignar_codigos.sql
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 0. Antes: cursos con código y cursos que este script tocaría.
-- ---------------------------------------------------------------------

SELECT COUNT(*) AS cursos_con_codigo_antes FROM `courses` WHERE `code` IS NOT NULL;


-- ---------------------------------------------------------------------
-- 1. Asignaciones, una por curso (ordenadas por id).
--    El nombre es el del curso en el observatorio, que coincide con el de
--    silabos_v2 (correspondencia exacta). Los marcados "solo en sílabos sin
--    carrera del observatorio" se vinculan igual: esos sílabos se procesan
--    si el curso ya pertenece a una carrera mapeada (condición b).
--
--    El JOIN con `o` busca otro curso que ya tenga el código: si existe,
--    o.id no es NULL y el UPDATE no hace nada.
-- ---------------------------------------------------------------------

UPDATE courses c LEFT JOIN courses o ON o.code = '30012' SET c.code = '30012', c.updated_at = NOW() WHERE c.id = 5 AND c.name = 'ANÁLISIS DE DATOS' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30015' SET c.code = '30015', c.updated_at = NOW() WHERE c.id = 6 AND c.name = 'ANÁLISIS DEL ENTORNO DE NEGOCIOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30047' SET c.code = '30047', c.updated_at = NOW() WHERE c.id = 7 AND c.name = 'COMUNICACIÓN ESCRITA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30153' SET c.code = '30153', c.updated_at = NOW() WHERE c.id = 9 AND c.name = 'CALCULO PARA CIENCIAS E INGENIERIA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30063' SET c.code = '30063', c.updated_at = NOW() WHERE c.id = 10 AND c.name = 'Desarrollo de Resiliencia' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30092' SET c.code = '30092', c.updated_at = NOW() WHERE c.id = 12 AND c.name = 'DISEÑO Y PROGRAMACIÓN DE BASE DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30107' SET c.code = '30107', c.updated_at = NOW() WHERE c.id = 13 AND c.name = 'Ética Profesional' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30135' SET c.code = '30135', c.updated_at = NOW() WHERE c.id = 15 AND c.name = 'GESTIÓN DE PROYECTOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30136' SET c.code = '30136', c.updated_at = NOW() WHERE c.id = 16 AND c.name = 'GESTIÓN DE SERVICIOS ITIL' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30199' SET c.code = '30199', c.updated_at = NOW() WHERE c.id = 18 AND c.name = 'PROGRAMACIÓN AVANZADA DE BASE DE DATOS' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30228' SET c.code = '30228', c.updated_at = NOW() WHERE c.id = 19 AND c.name = 'Servidores y Almacenamiento' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30475' SET c.code = '30475', c.updated_at = NOW() WHERE c.id = 20 AND c.name = 'ANALISIS DE RENDIMIENTO Y ESCALABILIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30478' SET c.code = '30478', c.updated_at = NOW() WHERE c.id = 21 AND c.name = 'AUDITORIA DE CIBERSEGURIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30480' SET c.code = '30480', c.updated_at = NOW() WHERE c.id = 22 AND c.name = 'AUTOMATIZACION DE PROCESOS DE NEGOCIOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30041' SET c.code = '30041', c.updated_at = NOW() WHERE c.id = 23 AND c.name = 'Competencias Intrapersonales' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30489' SET c.code = '30489', c.updated_at = NOW() WHERE c.id = 24 AND c.name = 'COMPLIANCE IT' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30492' SET c.code = '30492', c.updated_at = NOW() WHERE c.id = 25 AND c.name = 'DATA LAKES AND DATA WAREHOUSING' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30500' SET c.code = '30500', c.updated_at = NOW() WHERE c.id = 26 AND c.name = 'FUNDAMENTOS DE ARQUITECTURA DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30501' SET c.code = '30501', c.updated_at = NOW() WHERE c.id = 27 AND c.name = 'FUNDAMENTOS DE BIG DATA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30514' SET c.code = '30514', c.updated_at = NOW() WHERE c.id = 28 AND c.name = 'INTRODUCCIÓN AL MODELADO DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30176' SET c.code = '30176', c.updated_at = NOW() WHERE c.id = 29 AND c.name = 'MATEMÁTICA APLICADA' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30089' SET c.code = '30089', c.updated_at = NOW() WHERE c.id = 30 AND c.name = 'DISEÑO Y DESARROLLO WEB' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30521' SET c.code = '30521', c.updated_at = NOW() WHERE c.id = 32 AND c.name = 'SEGURIDAD EN LA NUBE' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30003' SET c.code = '30003', c.updated_at = NOW() WHERE c.id = 33 AND c.name = 'ADMINISTRACIÓN AVANZADA DE WINDOWS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30009' SET c.code = '30009', c.updated_at = NOW() WHERE c.id = 34 AND c.name = 'ADMINISTRACIÓN DE WINDOWS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30037' SET c.code = '30037', c.updated_at = NOW() WHERE c.id = 39 AND c.name = 'CCNA SECURITY' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30074' SET c.code = '30074', c.updated_at = NOW() WHERE c.id = 40 AND c.name = 'Dirección de Personas' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30128' SET c.code = '30128', c.updated_at = NOW() WHERE c.id = 42 AND c.name = 'GESTIÓN DE DISPOSITIVOS TECNOLÓGICOS' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30223' SET c.code = '30223', c.updated_at = NOW() WHERE c.id = 43 AND c.name = 'SEGURIDAD EN SISTEMAS OPERATIVOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30474' SET c.code = '30474', c.updated_at = NOW() WHERE c.id = 44 AND c.name = 'ANALISIS DE AMENAZAS Y RIESGOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30715' SET c.code = '30715', c.updated_at = NOW() WHERE c.id = 45 AND c.name = 'CRIPTOGRAFIA' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30499' SET c.code = '30499', c.updated_at = NOW() WHERE c.id = 46 AND c.name = 'ESTANDARES Y NORMATIVAS DE CIBERSEGURIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30502' SET c.code = '30502', c.updated_at = NOW() WHERE c.id = 47 AND c.name = 'FUNDAMENTOS DE CIBERSEGURIDAD' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30515' SET c.code = '30515', c.updated_at = NOW() WHERE c.id = 48 AND c.name = 'INVESTIGACION DE INCIDENTES' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30726' SET c.code = '30726', c.updated_at = NOW() WHERE c.id = 49 AND c.name = 'SEGURIDAD EN APLICACIONES Y BASES DE DATOS' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30245' SET c.code = '30245', c.updated_at = NOW() WHERE c.id = 50 AND c.name = 'Técnicas de Hacking y Análisis Forense' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30020' SET c.code = '30020', c.updated_at = NOW() WHERE c.id = 51 AND c.name = 'Analitica De Sistemas Empresariales' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30038' SET c.code = '30038', c.updated_at = NOW() WHERE c.id = 52 AND c.name = 'Centro De Datos Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30356' SET c.code = '30356', c.updated_at = NOW() WHERE c.id = 53 AND c.name = 'Transformacion Digital' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD45' SET c.code = 'TBD45', c.updated_at = NOW() WHERE c.id = 54 AND c.name = 'Aprendizaje Por Refuerzo' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30482' SET c.code = '30482', c.updated_at = NOW() WHERE c.id = 55 AND c.name = 'Big Data Analytics' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30537' SET c.code = '30537', c.updated_at = NOW() WHERE c.id = 58 AND c.name = 'Deep Learning Y Lenguaje Natural' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30498' SET c.code = '30498', c.updated_at = NOW() WHERE c.id = 59 AND c.name = 'Estadistica Avanzada' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30503' SET c.code = '30503', c.updated_at = NOW() WHERE c.id = 60 AND c.name = 'Fundamentos De Ciencia De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30506' SET c.code = '30506', c.updated_at = NOW() WHERE c.id = 61 AND c.name = 'Fundamentos De Machine Learning' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30512' SET c.code = '30512', c.updated_at = NOW() WHERE c.id = 62 AND c.name = 'Introduccion A La Estadistica Y Analisis De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30518' SET c.code = '30518', c.updated_at = NOW() WHERE c.id = 63 AND c.name = 'Optimizacion Y Modelado Matematico' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30535' SET c.code = '30535', c.updated_at = NOW() WHERE c.id = 64 AND c.name = 'Sistemas Distribuidos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30523' SET c.code = '30523', c.updated_at = NOW() WHERE c.id = 65 AND c.name = 'Storytelling Con Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30534' SET c.code = '30534', c.updated_at = NOW() WHERE c.id = 66 AND c.name = 'Vision Computacional' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30017' SET c.code = '30017', c.updated_at = NOW() WHERE c.id = 84 AND c.name = 'Analisis Funcional' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30157' SET c.code = '30157', c.updated_at = NOW() WHERE c.id = 86 AND c.name = 'Integracion De Aplicaciones' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30231' SET c.code = '30231', c.updated_at = NOW() WHERE c.id = 87 AND c.name = 'Sistemas De Control Y Gobierno De Ti' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30677' SET c.code = '30677', c.updated_at = NOW() WHERE c.id = 88 AND c.name = 'Administracion De Infraestructura Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30476' SET c.code = '30476', c.updated_at = NOW() WHERE c.id = 89 AND c.name = 'Analisis Y Monitoreo Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30477' SET c.code = '30477', c.updated_at = NOW() WHERE c.id = 90 AND c.name = 'Arquitectura De Cloud Computing' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30714' SET c.code = '30714', c.updated_at = NOW() WHERE c.id = 91 AND c.name = 'Automatizacion Y Gestion De Configuracion Cloud' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30504' SET c.code = '30504', c.updated_at = NOW() WHERE c.id = 93 AND c.name = 'Fundamentos De Cloud Computing' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30032' SET c.code = '30032', c.updated_at = NOW() WHERE c.id = 95 AND c.name = 'Calidad Y Pruebas De Software' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30181' SET c.code = '30181', c.updated_at = NOW() WHERE c.id = 101 AND c.name = 'Modelos De Negocios Y Startups' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30376' SET c.code = '30376', c.updated_at = NOW() WHERE c.id = 104 AND c.name = 'Soluciones Basadas En Internet De Las Cosas' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30024' SET c.code = '30024', c.updated_at = NOW() WHERE c.id = 105 AND c.name = 'Aplicaciones Moviles Para Android' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30025' SET c.code = '30025', c.updated_at = NOW() WHERE c.id = 106 AND c.name = 'Aplicaciones Moviles Para Iphone' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30224' SET c.code = '30224', c.updated_at = NOW() WHERE c.id = 108 AND c.name = 'Seguridad Y Continuidad De Sistemas' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30002' SET c.code = '30002', c.updated_at = NOW() WHERE c.id = 150 AND c.name = 'Administracion Avanzada De Linux' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30007' SET c.code = '30007', c.updated_at = NOW() WHERE c.id = 151 AND c.name = 'Administracion De Linux' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30257' SET c.code = '30257', c.updated_at = NOW() WHERE c.id = 153 AND c.name = 'Virtualizacion De Infraestructuras Tecnologicas' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30130' SET c.code = '30130', c.updated_at = NOW() WHERE c.id = 155 AND c.name = 'Gestion De La Cadena De Suministros' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30134' SET c.code = '30134', c.updated_at = NOW() WHERE c.id = 157 AND c.name = 'Gestion De Procesos, Simulacion Y Mejora Continua' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 27' SET c.code = 'TBD 27', c.updated_at = NOW() WHERE c.id = 158 AND c.name = 'Analisis Y Diseno De Sistemas Avanzado' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30060' SET c.code = '30060', c.updated_at = NOW() WHERE c.id = 162 AND c.name = 'Desarrollo De Aplicaciones Empresariales Avanzado' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30632' SET c.code = '30632', c.updated_at = NOW() WHERE c.id = 164 AND c.name = 'Desarrollo De Soluciones Cloud Basico' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD70' SET c.code = 'TBD70', c.updated_at = NOW() WHERE c.id = 165 AND c.name = 'Inteligencia Artificial Aplicada' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30626' SET c.code = '30626', c.updated_at = NOW() WHERE c.id = 166 AND c.name = 'Programacion Orientada A Objetos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30690' SET c.code = '30690', c.updated_at = NOW() WHERE c.id = 167 AND c.name = 'Programacion Web Avanzada' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30034' SET c.code = '30034', c.updated_at = NOW() WHERE c.id = 170 AND c.name = 'Ccna Ii - Switching, Routing Y Wireless Essentials' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30035' SET c.code = '30035', c.updated_at = NOW() WHERE c.id = 171 AND c.name = 'Ccna Iii - Enterprise Networking, Security And Automation' AND c.code IS NULL AND o.id IS NULL;  -- solo en sílabos sin carrera del observatorio
UPDATE courses c LEFT JOIN courses o ON o.code = '30036' SET c.code = '30036', c.updated_at = NOW() WHERE c.id = 172 AND c.name = 'Ccna Iv-Advanced Routing' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30702' SET c.code = '30702', c.updated_at = NOW() WHERE c.id = 174 AND c.name = 'Desarrollo De Soluciones Cloud Avanzado' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30493' SET c.code = '30493', c.updated_at = NOW() WHERE c.id = 176 AND c.name = 'Data Mining Basico' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30494' SET c.code = '30494', c.updated_at = NOW() WHERE c.id = 177 AND c.name = 'Data Mining Avanzado' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '30524' SET c.code = '30524', c.updated_at = NOW() WHERE c.id = 179 AND c.name = 'Tendencias En Arquitectura De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 40' SET c.code = 'TBD 40', c.updated_at = NOW() WHERE c.id = 180 AND c.name = 'Analisis Estadistico Y Data Mining' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 42' SET c.code = 'TBD 42', c.updated_at = NOW() WHERE c.id = 181 AND c.name = 'Arquitectura Empresarial' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40001' SET c.code = '40001', c.updated_at = NOW() WHERE c.id = 182 AND c.name = 'Cultura De Innovacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40064' SET c.code = '40064', c.updated_at = NOW() WHERE c.id = 183 AND c.name = 'Customer Centricity En Tecnologias De La Informacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40081' SET c.code = '40081', c.updated_at = NOW() WHERE c.id = 184 AND c.name = 'Direccion En Transformacion Digital' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40062' SET c.code = '40062', c.updated_at = NOW() WHERE c.id = 185 AND c.name = 'Direccion Estrategica De Datos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 41' SET c.code = 'TBD 41', c.updated_at = NOW() WHERE c.id = 186 AND c.name = 'Diseno De Soluciones Con Inteligencia Artificial' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40004' SET c.code = '40004', c.updated_at = NOW() WHERE c.id = 187 AND c.name = 'Fundamentos De Investigacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = 'TBD 43' SET c.code = 'TBD 43', c.updated_at = NOW() WHERE c.id = 188 AND c.name = 'Gestion Presupuestaria De Ti' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40009' SET c.code = '40009', c.updated_at = NOW() WHERE c.id = 191 AND c.name = 'Innovacion Y Creacion De Valor' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40038' SET c.code = '40038', c.updated_at = NOW() WHERE c.id = 192 AND c.name = 'Inteligencia De Negocios' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40036' SET c.code = '40036', c.updated_at = NOW() WHERE c.id = 193 AND c.name = 'Planeamiento Estrategico De Sistemas De Informacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40013' SET c.code = '40013', c.updated_at = NOW() WHERE c.id = 194 AND c.name = 'Taller De Investigacion Aplicada E Innovacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40008' SET c.code = '40008', c.updated_at = NOW() WHERE c.id = 195 AND c.name = 'Tecnicas Y Herramientas De Investigacion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40039' SET c.code = '40039', c.updated_at = NOW() WHERE c.id = 196 AND c.name = 'Tecnologias Emergentes' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40063' SET c.code = '40063', c.updated_at = NOW() WHERE c.id = 197 AND c.name = 'Gestion De Proyectos Basados En Analytics' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40010' SET c.code = '40010', c.updated_at = NOW() WHERE c.id = 198 AND c.name = 'Gestion Del Emprendimiento' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40028' SET c.code = '40028', c.updated_at = NOW() WHERE c.id = 199 AND c.name = 'Herramientas Tecnologicas Para La Gestion' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40035' SET c.code = '40035', c.updated_at = NOW() WHERE c.id = 200 AND c.name = 'Integracion De Procesos Empresariales' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40034' SET c.code = '40034', c.updated_at = NOW() WHERE c.id = 201 AND c.name = 'Metodologias Para La Gestion De Proyectos' AND c.code IS NULL AND o.id IS NULL;
UPDATE courses c LEFT JOIN courses o ON o.code = '40033' SET c.code = '40033', c.updated_at = NOW() WHERE c.id = 202 AND c.name = 'Procesos Empresariales' AND c.code IS NULL AND o.id IS NULL;

-- ---------------------------------------------------------------------
-- 2. Después: resumen y cursos de la lista que quedaron sin código
--    (nombre cambiado, id distinto o código ya usado por otro curso).
--    En una segunda corrida, todos aparecen como ya asignados.
-- ---------------------------------------------------------------------

SELECT COUNT(*) AS cursos_con_codigo_despues FROM `courses` WHERE `code` IS NOT NULL;

SELECT `id`, `name` AS curso_de_la_lista_sin_codigo
FROM `courses`
WHERE `id` IN (5, 6, 7, 9, 10, 12, 13, 15, 16, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 32, 33, 34, 39, 40, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 58, 59, 60, 61, 62, 63, 64, 65, 66, 84, 86, 87, 88, 89, 90, 91, 93, 95, 101, 104, 105, 106, 108, 150, 151, 153, 155, 157, 158, 162, 164, 165, 166, 167, 170, 171, 172, 174, 176, 177, 179, 180, 181, 182, 183, 184, 185, 186, 187, 188, 191, 192, 193, 194, 195, 196, 197, 198, 199, 200, 201, 202) AND `code` IS NULL
ORDER BY `id`;
