# Ranking de Certificaciones: despliegue en producción

> **Producción:** al DBA se le entrega solo `docs/produccion/DESPLIEGUE_RANKINGS_PRODUCCION.sql`, que consolida los
> scripts necesarios de esta carpeta (ver `PLAN_RANKINGS.md`, sección 6). Los scripts de aquí siguen siendo la fuente
> del consolidado y sirven para aplicar o revertir partes sueltas. **La limpieza de asociaciones queda fuera del
> despliegue.**

La página `/dashboard/ranking-certificaciones` ya no calcula el ranking en vivo: lee de las tablas de resumen
`certification_rankings`, `certification_ranking_meta`, `certification_ranking_weekly` y
`certification_ranking_weekly_meta`, que llena `php artisan ranking:refresh-certifications`
(`App\Services\CertificationRankingService`, sobre el servicio genérico `EntityRankingService`, igual que Lenguajes
y Tecnologías). En producción no se usan migraciones: el DBA aplica estos scripts.

## Cambios de criterio respecto del cálculo anterior

- Tendencias con la misma fórmula que los otros indicadores: `LOG(reportes+1)/LOG(max+1)*100` (antes
  `menciones / total de reportes`). Semestres semiabiertos (antes se perdían el 30-jun y el 31-dic).
- Solo certificaciones activas (`market_entities.is_active`); "eliminar" en la administración desactiva.
- Evolución = foto del ranking semestral en cada corte (modal y export compartidos con Lenguajes/Tecnologías).
- Botón "Recalcular ranking" (solo `administrar`), recálculo al final de `jobs:run-all` y a las 07:00 (America/Lima).
- `certifications:take-snapshot` y `certifications:backfill-all` retirados (sin `--legacy` no hacen nada).

## Scripts para el DBA (en este orden)

> **Producción: la limpieza de asociaciones NO forma parte del despliegue** (decisión del 2026-11-16).
> No se ejecutan `entities:clean-associations` ni los scripts `09`, `10` y `11`. Se conservan en el
> repositorio por si se decide aplicarla más adelante (probados en local, con respaldo y reversión).
> Consecuencia: las asociaciones históricas no confirmadas siguen contando en el ranking; desde el despliegue,
> los scrapers solo agregan asociaciones que el matcher confirma.

| # | Script | Qué hace | Tiempo (local) | Bloqueo |
|---|---|---|---|---|
| 0 | — | **Respaldo** de `certification_job`, `certifications`, `market_entities` | — | — |
| 1 | `01_ranking_certifications_tablas.sql` | 4 tablas de resumen (y `cache`, `cache_locks`, `jobs`, `failed_jobs` si faltaran) | < 1 s | Ninguno |
| 2 | `02_ranking_certifications_indices.sql` | Índice `certification_job (job_offer_id, market_entity_id)` | < 1 s | En línea |
| 5 | `05_ranking_certifications_entidades.sql` | Desactiva 12 duplicados; da de alta CompTIA A+; crea la fila de `certifications` de cada certificación activa sin ella (27) y completa `keywords` vacías (37). La copia de las asociaciones de las tecnologías CompTIA A+ y Cyberops está **desactivada por defecto** (`@copiar_desde_tecnologias = 0`): sin limpieza metería ~13 700 asociaciones falsas | < 2 s | Filas |
| 7 | `07_matcher_aliases_certificaciones.sql` | Reglas del matcher (333; crea `market_entity_aliases` si no existe) | < 1 s | Ninguno |
| 12 | `12_keywords_busqueda_certificaciones.sql` | Términos de búsqueda en `certifications.keywords` (JSON): sigla o código y nombre oficial ("CISSP", "AZ-400", "AWS Certified DevOps Engineer"); respaldo para `13` | < 1 s | Filas |
| 4 | `04_ranking_certifications_verificacion.sql` | Solo lectura (después de recalcular) | < 1 s | Ninguno |

Después de desplegar el código:

```sh
php artisan optimize:clear
php artisan ranking:refresh-certifications
php artisan queue:restart
php artisan schedule:list   # 0 7 * * *  php artisan ranking:refresh-certifications
```

Reversiones: `03` (tablas e índice; desplegar antes el código anterior), `06` (entidades), `08` (reglas; no elimina la
tabla compartida), `13` (keywords) y `11` (limpieza, si alguna vez se aplica). Todos probados en local: aplicar → re-aplicar →
revertir → aplicar.

## Resultado en local (2026-11-16)

- `certification_job`: 45 214 → 726 filas (44 488 borradas, 54 reasignadas de duplicados). Casi todas las asociaciones
  venían de buscar por keywords genéricas ("aws", "azure", "scrum") que asociaban cada oferta a todas las
  certificaciones del proveedor.
- 79 certificaciones activas; foto de la semana actual = ranking principal (79/79).
- El ranking se reconstruye a medida que corren los scrapers, que ahora solo asocian si el matcher confirma.

## Logs

`storage/logs/laravel.log`, prefijo `[CERTIFICATION_RANKING]`.
