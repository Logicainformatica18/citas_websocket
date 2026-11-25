<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Prompts y versiones (ai_prompts / ai_prompt_versions). kind: offers | trends. */
final class Prompts
{
    public const KINDS = ['offers', 'trends'];

    /** Versión activa: {id, prompt_id, version, body, model}. */
    public static function active(string $kind): object
    {
        $v = DB::table('ai_prompts as p')->join('ai_prompt_versions as v', 'v.id', '=', 'p.active_version_id')
            ->where('p.kind', $kind)->first(['v.id', 'v.prompt_id', 'v.version', 'v.body', 'v.model']);
        if (! $v) {
            throw new RuntimeException("No hay versión activa del prompt '{$kind}'");
        }

        return $v;
    }

    public static function version(int $id): object
    {
        $v = DB::table('ai_prompt_versions')->where('id', $id)->first(['id', 'prompt_id', 'version', 'body', 'model', 'status']);
        if (! $v) {
            throw new RuntimeException("No existe la versión {$id}");
        }

        return $v;
    }

    /** id de la versión con ese número para el tipo (para --reclassify-version=N). */
    public static function versionId(string $kind, int $number): int
    {
        $id = DB::table('ai_prompt_versions as v')->join('ai_prompts as p', 'p.id', '=', 'v.prompt_id')
            ->where('p.kind', $kind)->where('v.version', $number)->value('v.id');
        if (! $id) {
            throw new RuntimeException("El prompt '{$kind}' no tiene versión {$number}");
        }

        return (int) $id;
    }
}
