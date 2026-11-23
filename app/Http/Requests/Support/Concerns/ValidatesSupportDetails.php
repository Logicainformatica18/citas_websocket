<?php

namespace App\Http\Requests\Support\Concerns;

use App\Models\SupportDetail;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas para los detalles de una solicitud.
 *
 * El formulario envía "details" como JSON dentro de un FormData; aquí se
 * decodifica y se normalizan los '' a null (el middleware ConvertEmptyStringsToNull
 * no llega al interior del JSON).
 */
trait ValidatesSupportDetails
{
    protected function prepareDetails(): void
    {
        $details = $this->input('details');

        if (is_string($details)) {
            $decoded = json_decode($details, true);
            $details = json_last_error() === JSON_ERROR_NONE ? $decoded : $details;
        }

        if (is_array($details)) {
            $details = array_map(
                fn ($d) => is_array($d) ? array_map(fn ($v) => $v === '' ? null : $v, $d) : $d,
                $details
            );
        }

        $this->merge(['details' => $details]);
    }

    protected function detailRules(): array
    {
        return [
            'details.*' => ['array'],
            'details.*.subject' => ['required', 'string', 'max:255'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.priority' => ['nullable', Rule::in(SupportDetail::PRIORITIES)],
            'details.*.type' => ['nullable', 'string', 'max:50'],
            'details.*.status' => ['nullable', Rule::in(SupportDetail::STATUSES)],
            'details.*.reservation_time' => ['nullable', 'date'],
            'details.*.attended_at' => ['nullable', 'date'],
            'details.*.derived' => ['nullable', 'string', 'max:255'],
            'details.*.project_id' => ['nullable', 'integer', 'exists:proyecto,id_proyecto'],
            'details.*.area_id' => ['nullable', 'integer', 'exists:areas,id_area'],
            'details.*.id_motivos_cita' => ['nullable', 'integer', 'exists:motivos_cita,id_motivos_cita'],
            'details.*.id_tipo_cita' => ['nullable', 'integer', 'exists:tipos_cita,id_tipo_cita'],
            'details.*.id_dia_espera' => ['nullable', 'integer', 'exists:dias_espera,id_dias_espera'],
            'details.*.internal_state_id' => ['nullable', 'integer', 'exists:internal_states,id'],
            'details.*.external_state_id' => ['nullable', 'integer', 'exists:external_states,id'],
            'details.*.type_id' => ['nullable', 'integer', 'exists:types,id'],
            'details.*.Manzana' => ['nullable', 'string', 'max:255'],
            'details.*.comment' => ['nullable', 'string', 'max:255'], // columna VARCHAR(255)
            'details.*.channel' => ['nullable', 'string', 'max:50'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'details.required' => 'Agregue al menos un detalle a la solicitud.',
            'details.*.subject.required' => 'Seleccione la solicitud (asunto) en cada detalle.',
            'details.*.priority.in' => 'La prioridad debe ser Baja, Media o Alta.',
            'details.*.status.in' => 'El estado debe ser Pendiente, Atendido o Cerrado.',
            'details.*.comment.max' => 'El comentario no puede superar 255 caracteres.',
            'attachments.*.max' => 'Cada adjunto puede pesar como máximo 5 MB.',
            'client_id.required' => 'Seleccione un cliente.',
            'client_id.exists' => 'El cliente seleccionado no existe.',
        ];
    }
}
