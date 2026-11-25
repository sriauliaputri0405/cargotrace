<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EtaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estimated_arrival_at' => ['required', 'date'],
            'note'                 => ['nullable', 'string', 'max:300'],
        ];
    }
}