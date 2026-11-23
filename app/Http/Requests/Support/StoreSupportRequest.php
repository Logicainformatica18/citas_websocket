<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\Support\Concerns\ValidatesSupportDetails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /supports. La autorización se hace en la ruta (permission:...). */
class StoreSupportRequest extends FormRequest
{
    use ValidatesSupportDetails;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDetails();
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clientes,id_cliente'],
            'status_global' => ['nullable', Rule::in(['Sí', 'No', 'Incompleto'])],
            'state' => ['nullable', 'string', 'max:255'],
            'details' => ['required', 'array', 'min:1'],
            // Datos de contacto que actualizan la ficha del cliente
            'dni' => ['nullable', 'string', 'max:20'],
            'cellphone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            ...$this->detailRules(),
        ];
    }
}
