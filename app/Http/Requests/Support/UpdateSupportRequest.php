<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\Support\Concerns\ValidatesSupportDetails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PUT /supports/{id}. La autorización se hace en la ruta (permission:...). */
class UpdateSupportRequest extends FormRequest
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
            'status_global' => ['nullable', Rule::in(['Sí', 'No', 'Incompleto'])],
            'details' => ['nullable', 'array'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            ...$this->detailRules(),
        ];
    }
}
