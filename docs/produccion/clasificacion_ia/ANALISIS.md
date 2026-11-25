# Clasificación con IA de ofertas y tendencias — análisis (2026-11-25)

Análisis previo al desarrollo (sin cambios de código ni de base). Piloto 1 en `piloto1/` (muestra, prompts v1,
resultados y scripts). Las decisiones de la usuaria sobre este análisis están en `PLAN_RANKINGS.md` (Bloque 10).

## A. Situación actual

**Ofertas (`job_offers`): 403 881, 59 512 excluidas por título.**

| Fuente | Ofertas | Excluidas | | Semestre | Ofertas | Excluidas |
|---|---|---|---|---|---|---|
| Adzuna | 157 956 | 10 933 | | 2024-s1 | 56 334 | 17 309 |
| LinkedIn | 93 887 | 28 380 | | 2024-s2 | 2 216 | 130 |
| reed | 64 619 | 9 451 | | 2025-s1 | 5 308 | 386 |
| Jooble | 38 718 | 4 594 | | 2025-s2 | 100 616 | 10 344 |
| Arbeitnow | 23 302 | 4 377 | | 2026-s1 | 106 069 | 9 862 |
| Computrabajo | 14 469 | 245 | | 2026-s2 | 94 079 | 10 338 |
| otras 7 | 10 930 | 1 532 | | sin fecha (LinkedIn) | 37 865 | 11 107 |

- LinkedIn solo tiene 2024-s1 o fecha nula: no entra en los semestres actuales.
- Títulos distintos (minúsculas, sin tildes): 202 045 (173 126 válidos, 28 926 excluidos). Sin paréntesis,
  "(m/w/d)" ni puntuación: **155 191 válidos**; 119 397 aparecen una sola vez; los 35 794 repetidos cubren el 65 %
  de las ofertas válidas. 2026-s2: 39 545 títulos válidos.
- La mayoría de las ofertas no guarda texto (LinkedIn, Computrabajo, casi todo reed, 105 mil de Adzuna): se
  clasifica sobre todo por título y empresa.

**Tendencias (`entity_trends`): 91 647 filas, 48 999 URL.** Tecnología 70 846, certificación 10 850, lenguaje 5 063,
metodología 4 816 (23 168 de entidades inactivas). Origen: `tavily-search` 57 971, `gpt-search` 30 048, `search-gpt`
3 607 (Serper + GPT: URL reales), `gpt-*-gap` 21. ~10 000 al mes.
- Solo se guarda título y URL (`raw_payload` vacío); Tavily devuelve un extracto (`content`) que se descartaba.
- `gpt-search` (`trends:discover-*`, en RunAllJobs) pide a `gpt-4.1-mini` tendencias **sin búsqueda web**: inventa
  títulos y URL (1 069 URL con identificadores de relleno como `1234567`; 4 345 con `/AAAA/MM/15/`; de 25 URL al
  azar 3 responden 200, 5 dan 404, 1 dominio no existe, 16 bloquean; control Tavily: 8 de 10 responden 200).

**Integración de IA**: OpenAI `gpt-4.1-mini` (chat completions, JSON). "Sugerir con IA" falló por timeout: una sola
llamada enorme (116 patrones + 400 títulos + 120 sospechosos, 30 sugerencias con motivo) superó `timeout(120)`, y el
Job pasaba el `retry_after = 90` de la cola. 50 elementos por llamada tardan 13-21 s. Batch API: −50 %, < 24 h;
conviene para el histórico, no para lo diario.

**Convivencia**: la exclusión por título sigue siendo el primer filtro gratuito (lo excluido no va a la IA); el
título ya clasificado se reutiliza sin llamada; el detector de asociaciones (`entity_association_exclusions`) es otro
eje y no cambia.

## B. Diseño (resumen; el detalle final está en PLAN_RANKINGS.md, Bloque 10)

Estado en `job_offers` y `entity_trends`, clave de título, prompts con versiones, historial de decisiones (una fila por
decisión), lotes, corridas y pruebas de versión. Lo no TI se guarda marcado; revertir = decisión manual nueva.

## C. Consumo (medido en el piloto 1, gpt-4.1-mini a 0,40 / 1,60 USD por millón)

| | Por elemento | Diario | Histórico |
|---|---|---|---|
| Ofertas | 0,000067 USD | ~260 títulos nuevos/día → 0,02 USD | 155-173 mil títulos: 10-12 USD directo, 5-6 USD Batch API |
| Tendencias | 0,000079 USD | ~330/día → 0,03 USD | 91 600 filas: 7 USD directo, 3,6 USD Batch API |

La salida es el 70 % del costo. 50 elementos por llamada.

## D. Piloto 1 (150 ofertas + 150 tendencias, 0,022 USD)

- Ofertas: válidas 70 ti / **47 no_ti** / 3 dudosas (no TI que las keywords no detectan: LinkedIn 19/25, Adzuna 5/30,
  reed 7/20, Jooble 5/15, Arbeitnow 5/10, Computrabajo 2/8, Greenhouse 2/3, usajobs 2/2, GetOnBoard 0/5). Excluidas:
  27/30 confirmadas; "Software Engineer, Construction Applications" y "Call Center Application Developer" son falsos
  positivos de las keywords. Precisión ≈ 99 % en casos claros, ≈ 94 % contando los discutibles.
- Tendencias (Tavily + search-gpt, 95): ruido 65 (97 % de acuerdo), relevante 15 (53 % de acuerdo), dudosa 15 (12
  deberían ser ruido). Tavily: 85-90 % de ruido estimado; certificaciones de Tavily: 25/25 ruido. gpt-search: el
  clasificador se apoyaba en el `trend_name` inventado.
