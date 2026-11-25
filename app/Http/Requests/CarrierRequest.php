<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya admin yang boleh mengelola carrier
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // API: kalau is_active tidak dikirim, anggap aktif.
        // Form web: checkbox yang tidak dicentang tidak dikirim browser, jadi dianggap tidak aktif.
        $active = ($this->is('api/*') && ! $this->has('is_active'))
            ? true
            : $this->boolean('is_active');

        $this->merge(['is_active' => $active]);
    }

    public function rules(): array
    {
        $id = $this->route('carrier')?->id;

        return [
            'name'          => ['required', 'string', 'max:255'],
            'code'          => ['required', 'string', 'max:20', Rule::unique('carriers', 'code')->ignore($id)],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_active'     => ['boolean'],
        ];
    }
}