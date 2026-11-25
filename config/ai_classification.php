<?php

/*
|--------------------------------------------------------------------------
| Clasificación con IA de ofertas (TI / no TI) y tendencias (relevante / ruido)
|--------------------------------------------------------------------------
| Los prompts viven en la base (ai_prompts / ai_prompt_versions, editables en el módulo). Aquí solo hay
| valores fijos. Ver docs/produccion/PLAN_RANKINGS.md, Bloque 10.
*/

return [

    // Precios en USD por millón de tokens (entrada, entrada en caché, salida). Batch API: −50 %.
    'prices' => [
        'gpt-4.1-mini' => [0.40, 0.10, 1.60],
        'gpt-4.1' => [2.00, 0.50, 8.00],
        'gpt-4.1-nano' => [0.10, 0.025, 0.40],
    ],
    'batch_discount' => 0.5,

    // Elementos por llamada: títulos y tendencias 50; ofertas con descripción 25
    'per_request' => ['title' => 50, 'offer' => 25, 'trend' => 50],

    // Caracteres de descripción enviados en la pasada por oferta; menos de min_description = "sin descripción"
    'description_chars' => 2000,
    'min_description' => 50,

    // ai:classify (lo nuevo, en jobs:run-all): tope de elementos por corrida y por tipo (corte de costo)
    'sync_max_items' => 5000,

    // Batch API: peticiones por archivo (la API admite 50 000; lotes más chicos fallan menos por el límite de
    // tokens en cola de la cuenta)
    'batch_max_requests' => 2000,

    'http_timeout' => 90,

    // Tendencias: ruido por regla, sin IA
    'trend_rule_sources' => ['gpt-search', 'gpt-cert-gap', 'gpt-tech-gap'],   // generadas por GPT sin búsqueda web
    'trend_url_rules' => [
        'curso' => '#udemy\.com|coursera\.org/(learn|courses|specializations|professional-certificates|projects)|linkedin\.com/learning|pluralsight\.com#i',
        'perfil' => '#linkedin\.com/in/#i',
        'empleo' => '#/jobs?/|indeed\.|glassdoor\.#i',
        'portada' => '#^https?://[^/]+/?$#i',
        'sitemap' => '#\.xml$#i',
    ],
];
