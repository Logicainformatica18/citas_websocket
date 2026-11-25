# Clasificación con IA (ofertas y tendencias)

- `ANALISIS.md`: análisis previo (volúmenes, integración, diseño, consumo, piloto 1).
- `piloto1/`: muestra (150 ofertas + 150 tendencias), prompts v1, resultados y scripts.
- `piloto2/`: prompts v2 (borrador A y final), resultados y script.
- Prompts vigentes (fuente de la versión 1 en la base): `database/data/ai_prompts/ofertas_v2.txt` y `tendencias_v2.txt`.
- Diseño, decisiones y operación: `docs/produccion/PLAN_RANKINGS.md`, Bloque 10.

## Piloto 2 (2026-11-25, prompts v2, gpt-4.1-mini)

Mismas 150 ofertas del piloto 1 y 150 tendencias (las 95 de Tavily/search-gpt del piloto 1 + 55 nuevas; gpt-search ya es
ruido por regla). Se ejecutó dos veces sobre los mismos 300 elementos: borrador A y, tras corregir dos defectos, el final.

**Costo**: borrador A 0,0293 USD; final 0,0250 USD (incluye la comparación con gpt-4.1 de la segunda pasada: 0,0082 y
0,0040 USD). Total del piloto 2: **0,054 USD**.

### Ofertas
- Pasada 1 (por título, títulos específicos, sin descripción): 145 ofertas, 3 llamadas, 0,0098 USD.
- Títulos genéricos (`TitleKey::isGeneric`): 5 de 150. Con `d = 1` o dudosos en la pasada 1: 4.
- Pasada 2 (con la descripción, hasta 2 000 caracteres): solo las que tienen descripción. Sin descripción no se llama:
  genérico o dudoso → `dudosa` (revisión manual); `d = 1` con clase decidida → se queda la clase del título.
- Resultado final: válidas 67 ti / 49 no_ti / 4 dudosas; excluidas por keyword: 27 no_ti / 3 ti (los mismos falsos
  positivos del piloto 1).
- Cambios frente a v1 por las decisiones: Technical Account Manager, Sales Engineer y Content Designer (EA) → no_ti;
  Product/Project Manager genéricos → dudosa.
- Defecto del borrador A: `d = 1` en títulos claros ("Científico de Datos") y, sin descripción, pasaban a dudosa.
  Corregido (ejemplos de d = 0 en el prompt; sin descripción se conserva la clase del título).
- Defecto pendiente en el piloto (corregido en el clasificador): "Engineering Lead (Python & Django)" es genérico sin el
  paréntesis y quedaba dudoso; el clasificador envía a la pasada 2 los genéricos con información entre paréntesis.
- gpt-4.1 en la pasada 2: mismas clases que gpt-4.1-mini en 8 de 9 (borrador A) y 2 de 2 (final), a 5-6 veces el costo
  → se usa gpt-4.1-mini en las dos pasadas.

### Tendencias
| Origen / tipo | Borrador A | Final |
|---|---|---|
| tavily / tecnología (75) | 6 relevante, 21 dudosa, 43 ruido, 5 regla | 8 relevante, 1 dudosa, 61 ruido, 5 regla |
| search-gpt / tecnología (25) | 15 / 7 / 3 | 14 / 0 / 11 |
| tavily / certificación (35) | 1 / 13 / 9 / 12 regla | 2 / 0 / 21 / 12 regla |
| tavily / lenguaje (15) | 0 / 9 / 6 | 1 / 0 / 14 |

- Borrador A: "dudosa" para simples menciones de paso (las habría mandado a revisión manual: ~28 % de Tavily). El final
  exige coherencia (dudosa solo con cat = categoria; una lista donde la entidad es una más = ruido).
- Precisión de "relevante" (revisión manual): piloto 1 ≈ 53 %, borrador A ≈ 77 %, final ≈ 78 % (19-20 de 25). Errores
  vistos: "Best Enterprise AI Search Reviews" para GCP, "Amazon EC2 Features" (página de producto), "STP" en un artículo
  de aguas residuales (homónimo), certificaciones de Microsoft en general para Azure Developer; "top networking trends"
  para Wi-Fi debería ser dudosa. Ruido: ≈ 95 % de acuerdo.
- Reglas sin IA (12 + 5 de 150): perfiles de LinkedIn, Udemy/Coursera/LinkedIn Learning, portadas, sitemaps, empleo.

## Operación

- GLPI: `GLPI_CLASIFICACION_IA.md`. Diseño y decisiones: `docs/produccion/PLAN_RANKINGS.md`, Bloque 10.
- Efecto del filtro de tendencias en los rankings (apagado): `efecto_tendencias/` (`actual` = con las reglas aplicadas;
  `proyeccion` = descontando las pendientes con la tasa de ruido del piloto 2). Se vuelve a medir con
  `php83 docs/produccion/herramientas/efecto_filtro_tendencias.php 2026 s2 actual`.
