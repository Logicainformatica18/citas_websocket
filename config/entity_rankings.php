<?php

/*
|--------------------------------------------------------------------------
| Indicadores de ranking de entidades de mercado
|--------------------------------------------------------------------------
| Configuración de App\Services\Ranking\EntityRankingService por indicador.
|
|   entity_type          market_entities.entity_type
|   pivot_table          tabla oferta↔entidad con columnas job_offer_id y market_entity_id
|   tables               tablas resumen: ranking por semestre, su meta, y la evolución
|                        (fotos por semana/quincena/mes) con su meta
|   windows_count_column columna de *_weekly_meta con el número de entidades de la foto
|   weights_context      Prueba::getActive($context): ponderaciones laboral/tendencias
|   career_table         tabla entidad↔carrera para el filtro por carrera. Fuente única:
|                        market_entity_career (la escribe la administración de entidades).
|                        career_market_entity queda sin uso (no se borra todavía).
|   lock_name            Cache::lock que impide dos recálculos a la vez (y clave de estado)
|   log_prefix           prefijo de las líneas de log
|   label                nombre en español para mensajes
*/

return [

    'languages' => [
        'service' => App\Services\LanguageRankingService::class,
        'entity_type' => 'language',
        'pivot_table' => 'language_job',
        'tables' => [
            'rankings' => 'language_rankings',
            'meta' => 'language_ranking_meta',
            'windows' => 'language_ranking_weekly',
            'windows_meta' => 'language_ranking_weekly_meta',
        ],
        'windows_count_column' => 'languages_count',
        'weights_context' => 'languages',
        'career_table' => 'market_entity_career',
        'lock_name' => 'ranking-languages-refresh',
        'log_prefix' => '[LANGUAGE_RANKING]',
        'label' => 'lenguajes',
    ],

    'technologies' => [
        'service' => App\Services\TechnologyRankingService::class,
        'entity_type' => 'technology',
        'pivot_table' => 'technology_job',
        'tables' => [
            'rankings' => 'technology_rankings',
            'meta' => 'technology_ranking_meta',
            'windows' => 'technology_ranking_weekly',
            'windows_meta' => 'technology_ranking_weekly_meta',
        ],
        'windows_count_column' => 'entities_count',
        'weights_context' => 'technologies',
        // Fuente única desde 2026-11 (antes career_market_entity; sus pares de tecnologías se
        // copiaron con docs/produccion/ranking_tecnologias/08_ranking_technologies_carreras.sql)
        'career_table' => 'market_entity_career',
        'lock_name' => 'ranking-technologies-refresh',
        'log_prefix' => '[TECHNOLOGY_RANKING]',
        'label' => 'tecnologías',
    ],

];
