<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // "id" dan "ID" dianggap sama: ubah ke huruf besar sebelum divalidasi
        if ($this->has('country')) {
            $this->merge(['country' => strtoupper((string) $this->input('country'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'city'      => ['required', 'string', 'max:255'],
            'country'   => ['required', 'string', 'size:2'],
            'type'      => ['required', Rule::in(['port', 'warehouse', 'airport'])],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}