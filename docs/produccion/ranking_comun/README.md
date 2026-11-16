# Exclusión de asociaciones sin borrar (común a los tres rankings)

> **Producción:** la tabla se crea con `docs/produccion/DESPLIEGUE_RANKINGS_PRODUCCION.sql` (última parte). Estos
> scripts son su fuente y sirven para aplicarla o revertirla por separado.

En producción no se borra nada de `language_job`, `technology_job` ni `certification_job`. Para que los rankings
igualmente no cuenten las asociaciones falsas (p. ej. Arbeitnow asociaba ~17 lenguajes y ~87 tecnologías por oferta):

1. `php artisan entities:exclude-associations` evalúa cada par (oferta, entidad activa) con el matcher sobre el texto
   guardado de la oferta y anota en `entity_association_exclusions` los que no confirma. Procesa en bloques.
2. `EntityRankingService` ignora esos pares (`NOT EXISTS` sobre la tabla) al calcular el ranking por semestre, la
   evolución y las vacantes únicas de los tres indicadores.

| Script | Qué hace |
|---|---|
| `01_exclusiones_tabla.sql` | Crea `entity_association_exclusions` (vacía). Idempotente |
| `02_exclusiones_tabla_rollback.sql` | La elimina (datos derivados; se regeneran con el comando) |

## Configuración (`config/ranking_exclusions.php`)

| Variable de entorno | Valor | Efecto |
|---|---|---|
| `RANKING_EXCLUSIONS_ENABLED` | `true` (por defecto) / `false` | `false` vuelve al cálculo sin filtro sin tocar la base (después `ranking:refresh-*`) |
| `RANKING_EXCLUSIONS_TRUST_SINCE` | fecha y hora del despliegue, p. ej. `"2026-11-20 08:00:00"` | Las filas del pivote creadas desde entonces no se re-evalúan: los scrapers ya las confirmaron con el texto completo de la API, que varios no guardan |

Si la aplicación usa `php artisan config:cache`, después de cambiar estas variables hay que volver a ejecutarlo.

## Comportamiento del comando

- Criterio estricto, igual que la limpieza: se excluye lo que el matcher no confirma, también si la oferta no tiene texto.
- No evalúa las filas de entidades inactivas ni las sin entidad (no cuentan en el ranking).
- Re-ejecutable: inserta o renueva exclusiones; las que dejan de aplicar pasan a `vigente = 0` (nunca borra filas).
- Volver a ejecutarlo cuando cambien las reglas del matcher o se reactive una entidad, y luego `ranking:refresh-*`.
- Tiempo en local (2,2 M filas en `technology_job`): 195-321 s para los tres indicadores, ~1 GB de memoria máxima.
- Recálculo con el filtro: Lenguajes ~5 s, Tecnologías ~25 s (sin filtro: ~3 s y ~13 s).

## Lo que la exclusión no hace (frente a la limpieza)

- No reasigna las ofertas de las entidades duplicadas a su principal (siguen en el duplicado, inactivo): p. ej.
  Microsoft Azure queda con 267 vacantes en 2026-s2 en lugar de 479.
- Los modales "ver ofertas" de cada entidad siguen listando las ofertas del pivote sin filtrar.
