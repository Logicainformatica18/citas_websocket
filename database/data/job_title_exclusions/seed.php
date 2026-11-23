<?php

/*
|--------------------------------------------------------------------------
| Semilla de job_title_exclusion_keywords
|--------------------------------------------------------------------------
| Fuente única: la usan la migración 2026_11_23_000001 y el generador del SQL para el DBA
| (docs/produccion/herramientas/generar_exclusion_titulos_sql.php).
|
| Origen: PATRONES_EXCLUIR de docs/produccion/herramientas/exportar_periodos_ti.py, en el mismo
| orden y con la misma categoría que sus comentarios. Se comparan con el título en minúsculas y
| sin tildes, igual que el script.
|
| Adaptación al formato de la tabla (mismo resultado que la expresión original):
|   - literal sin metacaracteres        → match_mode 'text', whole_word 0  (subcadena)
|   - \bliteral\b                       → match_mode 'text', whole_word 1  (palabra completa)
|   - cualquier otra expresión          → match_mode 'regex', tal cual
|
| [pattern, match_mode, whole_word, category]
*/

return [
    // ventas y comercial
    ['comercial', 'text', 0, 'ventas'],
    ['commercial', 'text', 0, 'ventas'],
    ['\bsales?\b', 'regex', 0, 'ventas'],
    ['pre-?sales', 'regex', 0, 'ventas'],
    ['\bventas?\b', 'regex', 0, 'ventas'],
    ['vendedor', 'text', 0, 'ventas'],
    ['preventa', 'text', 0, 'ventas'],
    ['account executive', 'text', 0, 'ventas'],
    ['account manager', 'text', 0, 'ventas'],
    ['ejecutivo de cuentas?', 'regex', 0, 'ventas'],
    ['key account', 'text', 0, 'ventas'],
    ['business development', 'text', 0, 'ventas'],
    ['desarrollo de negocios?', 'regex', 0, 'ventas'],
    // marketing
    ['marketing', 'text', 0, 'marketing'],
    ['mercadeo', 'text', 0, 'marketing'],
    ['community manager', 'text', 0, 'marketing'],
    // recursos humanos
    ['recruit', 'text', 0, 'recursos humanos'],
    ['reclutador', 'text', 0, 'recursos humanos'],
    ['talent acquisition', 'text', 0, 'recursos humanos'],
    ['recursos humanos', 'text', 0, 'recursos humanos'],
    ['human resources', 'text', 0, 'recursos humanos'],
    // contabilidad y atención al cliente
    ['contador', 'text', 0, 'contabilidad'],
    ['contab', 'text', 0, 'contabilidad'],
    ['accountant', 'text', 0, 'contabilidad'],
    ['accounting', 'text', 0, 'contabilidad'],
    ['customer service', 'text', 0, 'atención al cliente'],
    ['atencion al cliente', 'text', 0, 'atención al cliente'],
    ['call center', 'text', 0, 'atención al cliente'],
    // digitación (contiene "data"/"datos" pero no es TI)
    ['data entry', 'text', 0, 'digitación'],
    ['captura de datos', 'text', 0, 'digitación'],
    ['digitador', 'text', 0, 'digitación'],
    // finanzas, legal y administración
    ['finanzas', 'text', 1, 'finanzas'],
    ['tesorer', 'text', 0, 'finanzas'],
    ['auditor contable', 'text', 0, 'finanzas'],
    ['abogad', 'text', 0, 'legal'],
    ['lawyer', 'text', 0, 'legal'],
    ['attorney', 'text', 0, 'legal'],
    ['paralegal', 'text', 0, 'legal'],
    ['legal counsel', 'text', 0, 'legal'],
    ['asistente administrativ', 'text', 0, 'administración'],
    ['administrative assistant', 'text', 0, 'administración'],
    ['receptionist', 'text', 0, 'administración'],
    ['recepcionista', 'text', 0, 'administración'],
    ['secretari', 'text', 0, 'administración'],
    // salud
    ['nurse', 'text', 0, 'salud'],
    ['enfermer', 'text', 0, 'salud'],
    ['clinical', 'text', 0, 'salud'],
    ['clinico', 'text', 0, 'salud'],
    ['medic(o|a)\b', 'regex', 0, 'salud'],
    ['physician', 'text', 0, 'salud'],
    ['pharmac', 'text', 0, 'salud'],
    ['farmac', 'text', 0, 'salud'],
    ['dentist', 'text', 0, 'salud'],
    ['odontolog', 'text', 0, 'salud'],
    ['therapist', 'text', 0, 'salud'],
    ['terapeuta', 'text', 0, 'salud'],
    ['caregiver', 'text', 0, 'salud'],
    ['cuidador', 'text', 0, 'salud'],
    // educación
    ['teacher', 'text', 0, 'educación'],
    ['docente', 'text', 0, 'educación'],
    ['profesor', 'text', 0, 'educación'],
    ['tutor\b', 'regex', 0, 'educación'],
    ['infant', 'text', 0, 'educación'],
    ['toddler', 'text', 0, 'educación'],
    ['preschool', 'text', 0, 'educación'],
    ['maestr(o|a)\b', 'regex', 0, 'educación'],
    // transporte y logística
    ['(truck|delivery|bus|van|cdl) driver', 'regex', 0, 'transporte y logística'],
    ['chofer', 'text', 0, 'transporte y logística'],
    ['conductor', 'text', 1, 'transporte y logística'],
    ['warehouse', 'text', 0, 'transporte y logística'],
    ['almacen', 'text', 0, 'transporte y logística'],
    ['bodeg', 'text', 0, 'transporte y logística'],
    ['forklift', 'text', 0, 'transporte y logística'],
    ['montacarg', 'text', 0, 'transporte y logística'],
    // operarios y oficios
    ['operario', 'text', 0, 'operarios y oficios'],
    ['machine operator', 'text', 0, 'operarios y oficios'],
    ['technician mechanic', 'text', 0, 'operarios y oficios'],
    ['soldador', 'text', 0, 'operarios y oficios'],
    ['welder', 'text', 0, 'operarios y oficios'],
    ['plumber', 'text', 0, 'operarios y oficios'],
    ['fontaner', 'text', 0, 'operarios y oficios'],
    ['carpinter', 'text', 0, 'operarios y oficios'],
    // comercio, hostelería y servicios
    ['cashier', 'text', 0, 'comercio y servicios'],
    ['cajer', 'text', 0, 'comercio y servicios'],
    ['retail', 'text', 0, 'comercio y servicios'],
    ['store manager', 'text', 0, 'comercio y servicios'],
    ['chef', 'text', 1, 'comercio y servicios'],
    ['cook', 'text', 1, 'comercio y servicios'],
    ['cociner', 'text', 0, 'comercio y servicios'],
    ['mesero', 'text', 0, 'comercio y servicios'],
    ['waiter', 'text', 0, 'comercio y servicios'],
    ['barista', 'text', 0, 'comercio y servicios'],
    ['housekeep', 'text', 0, 'comercio y servicios'],
    ['limpieza', 'text', 0, 'comercio y servicios'],
    ['cleaner', 'text', 0, 'comercio y servicios'],
    ['security guard', 'text', 0, 'comercio y servicios'],
    ['guardia', 'text', 0, 'comercio y servicios'],
    ['vigilante', 'text', 0, 'comercio y servicios'],
    // otras ingenierías
    ['mechanical', 'text', 0, 'otras ingenierías'],
    ['mecanic', 'text', 0, 'otras ingenierías'],
    ['civil', 'text', 1, 'otras ingenierías'],
    ['structural', 'text', 0, 'otras ingenierías'],
    ['estructural', 'text', 0, 'otras ingenierías'],
    ['electrical engineer', 'text', 0, 'otras ingenierías'],
    ['ingeniero electric', 'text', 0, 'otras ingenierías'],
    ['electricista', 'text', 0, 'otras ingenierías'],
    ['construction', 'text', 0, 'otras ingenierías'],
    ['construccion', 'text', 0, 'otras ingenierías'],
    ['powertrain', 'text', 0, 'otras ingenierías'],
    ['automotive engineer', 'text', 0, 'otras ingenierías'],
    ['chemical engineer', 'text', 0, 'otras ingenierías'],
    ['ingeniero quimic', 'text', 0, 'otras ingenierías'],
    ['process engineer', 'text', 0, 'otras ingenierías'],
    ['ingeniero de procesos', 'text', 0, 'otras ingenierías'],
    ['manufactur', 'text', 0, 'otras ingenierías'],
    ['produccion', 'text', 0, 'otras ingenierías'],
];
