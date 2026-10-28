<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Contrato v1 del payload de silabos_v2. Documentado en el README. */
class IngestSyllabusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autenticación la hace VerifyIngestKey
    }

    public function rules(): array
    {
        return [
            'schema_version' => ['required', 'integer', 'in:1'],
            'origen' => ['required', 'string', 'in:silabos_v2'],
            'modelo' => ['required', 'string', 'in:isil,scl'],
            'silabo_id' => ['required', 'integer', 'min:1'],
            'codigo_familia' => ['required', 'string', 'max:50'],
            'version' => ['required', 'integer', 'min:0'],

            'curso' => ['required', 'array'],
            'curso.codigo' => ['required', 'string', 'max:50'],
            'curso.nombre' => ['required', 'string', 'max:255'],
            'curso.periodo' => ['required', 'string', 'max:20'],
            'curso.tipo_plantilla' => ['required', 'integer', 'min:1'],
            'curso.creditos' => ['nullable', 'numeric'],

            'carreras' => ['present', 'array'],
            'carreras.*.codigo' => ['required', 'string', 'max:50'],
            'carreras.*.nombre' => ['nullable', 'string', 'max:255'],

            'aprobado_at' => ['required', 'date'],
            'aprobado_at_fuente' => ['required', 'string', 'in:finalizar_revision,log_17,log_20,updated_at'],

            'contenido' => ['required', 'array'],
            'contenido.secciones' => ['present', 'array'],
            'contenido.texto_plano' => ['required', 'string', 'min:50', 'max:200000'],
        ];
    }

    /** Siempre JSON, aunque el cliente no mande Accept: application/json. */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Payload inválido',
            'errors' => $validator->errors(),
        ], 422));
    }
}
