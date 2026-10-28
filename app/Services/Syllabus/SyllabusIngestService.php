<?php

namespace App\Services\Syllabus;

use App\Models\Syllabus;
use App\Support\PeriodoOrden;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Guarda un sílabo aprobado recibido desde silabos_v2 y decide si es el vigente de su familia.
 *
 * - Idempotencia: la fila se identifica por (source, external_model, external_id). Si llega
 *   otra vez con el mismo contenido, versión y periodo, no se hace nada.
 * - Familia de versiones: (external_model, codigo_familia). Un sílabo ISIL nunca reemplaza a uno SCL.
 * - Vigente: el mayor por (external_version, periodo_orden, external_id). El periodo y el id
 *   solo desempatan; aprobado_at es informativo y no interviene.
 * - Si el sílabo que llega no es el vigente se guarda ya como 'superseded' y no se procesa.
 */
class SyllabusIngestService
{
    public const RESULT_QUEUED = 'queued';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_SUPERSEDED = 'superseded';

    /**
     * @return array{0: Syllabus, 1: string, 2: ?int}  la fila, uno de los RESULT_* y el id
     *         del sílabo vigente que hay que procesar (null si no hay que procesar nada)
     */
    public function ingest(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $attributes = $this->attributesFrom($payload);

            // Bloquea la familia para que dos ingestas simultáneas no elijan vigentes distintos.
            $family = Syllabus::where('source', Syllabus::SOURCE_SILABOS_V2)
                ->where('external_model', $attributes['external_model'])
                ->where('codigo_familia', $attributes['codigo_familia'])
                ->lockForUpdate()
                ->get();

            $syllabus = Syllabus::where('source', Syllabus::SOURCE_SILABOS_V2)
                ->where('external_model', $attributes['external_model'])
                ->where('external_id', $attributes['external_id'])
                ->lockForUpdate()
                ->first();

            if ($syllabus && $this->isUnchanged($syllabus, $attributes)) {
                return [$syllabus, self::RESULT_UNCHANGED, null];
            }

            $syllabus ??= new Syllabus(['source' => Syllabus::SOURCE_SILABOS_V2]);
            $syllabus->fill($attributes + [
                'status' => 'pending',
                'superseded_at' => null,
                'superseded_by' => null,
            ])->save();

            $family = $family->reject(fn ($s) => $s->id === $syllabus->id)->push($syllabus);

            $this->logReviewCases($syllabus, $family);

            $current = $this->currentOf($family);

            Syllabus::whereIn('id', $family->where('id', '!=', $current->id)->whereNull('superseded_at')->pluck('id'))
                ->update(['superseded_at' => now(), 'superseded_by' => $current->id]);

            if ($current->id !== $syllabus->id) {
                // Una versión menor (reintento, carga inicial): se guarda reemplazada y no se procesa.
                $syllabus->update(['status' => 'superseded', 'superseded_at' => now(), 'superseded_by' => $current->id]);

                // Si el entrante era el vigente y bajó de versión, el ganador vuelve a ser vigente
                // y debe regenerar los pivotes del curso.
                $reactivated = null;
                if ($current->superseded_at) {
                    $current->update(['superseded_at' => null, 'superseded_by' => null, 'status' => 'pending']);
                    $reactivated = $current->id;
                }

                return [$syllabus, self::RESULT_SUPERSEDED, $reactivated];
            }

            return [$syllabus, self::RESULT_QUEUED, $syllabus->id];
        });
    }

    /** El vigente es el mayor por (versión, periodo, id externo). */
    public function currentOf(Collection $family): Syllabus
    {
        return $family->sort(fn ($a, $b) => $this->orderKey($b) <=> $this->orderKey($a))->first();
    }

    public function orderKey(Syllabus $s): array
    {
        return [(int) $s->external_version, (int) $s->periodo_orden, (int) $s->external_id];
    }

    /**
     * Hash de contenido.secciones calculado aquí, con las claves ordenadas, para no depender
     * del emisor. No incluye texto_plano (repite el nombre del curso): un cambio solo de nombre
     * no es "contenido distinto", aunque sí se reprocesa (ver isUnchanged).
     */
    public function contentHash(array $content): string
    {
        return hash('sha256', json_encode($this->sortKeys($content), JSON_UNESCAPED_UNICODE));
    }

    private function attributesFrom(array $p): array
    {
        $content = $p['contenido'];

        return [
            'external_model' => $p['modelo'],
            'external_id' => $p['silabo_id'],
            'codigo_familia' => $p['codigo_familia'],
            'external_version' => $p['version'],
            'periodo' => $p['curso']['periodo'],
            'periodo_orden' => PeriodoOrden::from($p['curso']['periodo']),
            'tipo_plantilla' => $p['curso']['tipo_plantilla'],
            'course_code' => $p['curso']['codigo'],
            'course_name' => $p['curso']['nombre'],
            'careers' => array_values($p['carreras'] ?? []),
            'approved_at' => Carbon::parse($p['aprobado_at']),
            'approved_at_source' => $p['aprobado_at_fuente'],
            'content' => $content,
            'content_hash' => $this->contentHash($content['secciones'] ?? []),
            'raw_text' => $content['texto_plano'],
        ];
    }

    /** Mismos datos y misma posición en la familia: no hay nada que reprocesar (salvo que haya fallado). */
    private function isUnchanged(Syllabus $existing, array $attributes): bool
    {
        return $existing->status !== 'failed'
            && $existing->content_hash === $attributes['content_hash']
            && $existing->raw_text === $attributes['raw_text']
            && $existing->course_code === $attributes['course_code']
            && $existing->course_name === $attributes['course_name']
            && $existing->careers == $attributes['careers']
            && (int) $existing->external_version === (int) $attributes['external_version']
            && $existing->periodo === $attributes['periodo']
            && $existing->codigo_familia === $attributes['codigo_familia'];
    }

    /**
     * Casos para revisión manual (storage/logs/silabos-revision.log):
     *  - misma versión que otro sílabo de la familia pero con contenido distinto;
     *  - versión que baja respecto de un periodo anterior (la versión debería acumularse);
     *  - periodo con un formato que no se reconoce.
     */
    private function logReviewCases(Syllabus $incoming, Collection $family): void
    {
        $log = Log::channel('silabos_revision');
        $context = fn (Syllabus $other) => [
            'modelo' => $incoming->external_model,
            'codigo_familia' => $incoming->codigo_familia,
            'entrante' => $this->describe($incoming),
            'otro' => $this->describe($other),
        ];

        foreach ($family as $other) {
            if ($other->id === $incoming->id) continue;

            if ((int) $other->external_version === (int) $incoming->external_version
                && $other->content_hash !== $incoming->content_hash) {
                $log->warning('Empate de versión con contenido distinto', $context($other));
            }

            $periodCmp = (int) $incoming->periodo_orden <=> (int) $other->periodo_orden;
            $versionCmp = (int) $incoming->external_version <=> (int) $other->external_version;
            if ($periodCmp !== 0 && $versionCmp !== 0 && $periodCmp !== $versionCmp) {
                $log->warning('Versión que baja en un periodo posterior', $context($other));
            }
        }

        if (!PeriodoOrden::isKnownFormat($incoming->periodo)) {
            $log->warning('Periodo con formato desconocido', ['entrante' => $this->describe($incoming)]);
        }
    }

    private function describe(Syllabus $s): array
    {
        return [
            'syllabus_id' => $s->id,
            'silabo_id' => $s->external_id,
            'version' => $s->external_version,
            'periodo' => $s->periodo,
            'content_hash' => $s->content_hash,
        ];
    }

    private function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        return array_map(fn ($v) => $this->sortKeys($v), $value);
    }
}
