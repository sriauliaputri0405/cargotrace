<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(config('cargotrace.exception_types'))],
            'severity'    => ['required', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}