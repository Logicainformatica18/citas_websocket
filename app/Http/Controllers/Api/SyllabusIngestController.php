<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestSyllabusRequest;
use App\Jobs\ProcessIngestedSyllabusJob;
use App\Services\Syllabus\SyllabusIngestService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/silabos/ingest — recibe un sílabo aprobado en silabos_v2.
 *
 *  202 queued      guardado y encolado para extracción
 *  200 unchanged   ya estaba con el mismo contenido; no se reprocesa
 *  200 superseded  guardado, pero hay una versión vigente mayor; no se procesa
 *  200 ignored     guardado, pero ninguna de sus carreras está mapeada; no se procesa
 *  200 pending_link guardado, pero ningún curso tiene su código; espera la vinculación manual
 */
class SyllabusIngestController extends Controller
{
    public function store(IngestSyllabusRequest $request, SyllabusIngestService $ingest): JsonResponse
    {
        [$syllabus, $result, $toProcess] = $ingest->ingest($request->validated());

        if ($toProcess) {
            ProcessIngestedSyllabusJob::dispatch($toProcess);
        }

        return response()->json([
            'id' => $syllabus->id,
            'status' => $result,
            'superseded_by' => $syllabus->superseded_by,
        ], $result === SyllabusIngestService::RESULT_QUEUED ? 202 : 200);
    }
}
