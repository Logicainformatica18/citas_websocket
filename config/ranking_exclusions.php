<?php

/*
|--------------------------------------------------------------------------
| Exclusión de asociaciones oferta↔entidad en los rankings (sin borrar datos)
|--------------------------------------------------------------------------
| `php artisan entities:exclude-associations` marca en entity_association_exclusions los pares
| (oferta, entidad) que el matcher no confirma en el texto de la oferta. EntityRankingService
| los ignora al calcular los rankings y la evolución de Lenguajes, Tecnologías y Certificaciones.
| language_job, technology_job y certification_job NO se modifican.
|
| enabled: false vuelve al cálculo sin filtro sin tocar la base (después: ranking:refresh-*).
|          Si la tabla no existe, el filtro se omite solo.
|
| trust_since: fecha (AAAA-MM-DD o AAAA-MM-DD HH:MM:SS) desde la que los scrapers ya confirman con el
|          matcher al asociar (la del despliegue). Las filas del pivote creadas desde entonces no se
|          vuelven a evaluar: fueron confirmadas con el texto completo de la API, que varios scrapers no
|          guardan (o guardan recortado), y re-evaluarlas con el texto guardado las excluiría por error.
|          Vacío = se evalúa todo.
*/

return [
    'enabled' => (bool) env('RANKING_EXCLUSIONS_ENABLED', true),
    'table' => 'entity_association_exclusions',
    'trust_since' => env('RANKING_EXCLUSIONS_TRUST_SINCE'),
];
