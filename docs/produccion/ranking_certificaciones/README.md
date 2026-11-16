# Ranking de Certificaciones: despliegue en producción

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

| # | Script | Qué hace | Tiempo (local) | Bloqueo |
|---|---|---|---|---|
| 0 | — | **Respaldo** de `certification_job`, `certifications`, `market_entities` | — | — |
| 1 | `01_ranking_certifications_tablas.sql` | 4 tablas de resumen (y `cache`, `cache_locks`, `jobs`, `failed_jobs` si faltaran) | < 1 s | Ninguno |
| 2 | `02_ranking_certifications_indices.sql` | Índice `certification_job (job_offer_id, market_entity_id)` | < 1 s | En línea |
| 5 | `05_ranking_certifications_entidades.sql` | Desactiva 12 duplicados; da de alta CompTIA A+; crea la fila de `certifications` de cada certificación activa sin ella (27) y completa `keywords` vacías (37); copia a `certification_job` las asociaciones de las tecnologías desactivadas CompTIA A+ y Cyberops (13 701) | < 2 s | Filas |
| 7 | `07_matcher_aliases_certificaciones.sql` | Reglas del matcher (333; crea `market_entity_aliases` si no existe) | < 1 s | Ninguno |
| 9 | `09_limpieza_preparar.sql` | Tablas `certification_job_limpieza` y `certification_job_respaldo_limpieza` | < 1 s | Ninguno |
| — | `php artisan entities:clean-associations --type=certifications` | Llena la tabla de trabajo (`--dry-run` para solo informar) | ~10 s | Ninguno |
| 10 | `10_limpieza_aplicar.sql` | Respalda y borra/reasigna por lotes. **Cliente `mysql` (DELIMITER)** | < 10 s | Filas, por lote |
| 4 | `04_ranking_certifications_verificacion.sql` | Solo lectura (después de recalcular) | < 1 s | Ninguno |

Después de desplegar el código:

```sh
php artisan optimize:clear
php artisan ranking:refresh-certifications
php artisan queue:restart
php artisan schedule:list   # 0 7 * * *  php artisan ranking:refresh-certifications
```

Reversiones: `03` (tablas e índice; desplegar antes el código anterior), `06` (entidades), `08` (reglas; no elimina la
tabla compartida), `11` (limpieza; restaura desde el respaldo). Todos probados en local: aplicar → re-aplicar →
revertir → aplicar.

## Resultado en local (2026-11-16)

- `certification_job`: 45 214 → 726 filas (44 488 borradas, 54 reasignadas de duplicados). Casi todas las asociaciones
  venían de buscar por keywords genéricas ("aws", "azure", "scrum") que asociaban cada oferta a todas las
  certificaciones del proveedor.
- 79 certificaciones activas; foto de la semana actual = ranking principal (79/79).
- El ranking se reconstruye a medida que corren los scrapers, que ahora solo asocian si el matcher confirma.

## Logs

`storage/logs/laravel.log`, prefijo `[CERTIFICATION_RANKING]`.
