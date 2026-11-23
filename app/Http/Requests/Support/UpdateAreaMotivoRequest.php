<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

/** PUT /support-details/{id}/area-motivo. La autorización se hace en la ruta. */
class UpdateAreaMotivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', 'exists:areas,id_area'],
            'id_motivos_cita' => ['nullable', 'integer', 'exists:motivos_cita,id_motivos_cita'],
            'internal_state_id' => ['nullable', 'integer', 'exists:internal_states,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'area_id.required' => 'Seleccione un área.',
            'area_id.exists' => 'El área seleccionada no existe.',
        ];
    }
}
