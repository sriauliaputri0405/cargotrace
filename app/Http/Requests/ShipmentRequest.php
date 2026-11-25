<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi dilakukan oleh Policy di controller
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency_code')) {
            $this->merge(['currency_code' => strtoupper((string) $this->input('currency_code'))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'carrier_id'            => ['required', 'exists:carriers,id'],
            'origin_id'             => ['required', 'exists:locations,id'],
            'destination_id'        => ['required', 'exists:locations,id', 'different:origin_id'],
            'cargo_type'            => ['required', 'string', 'max:50'],
            'weight_kg'             => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'volume_cbm'            => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'freight_cost'          => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency_code'         => ['required', Rule::in(array_keys(config('currencies')))],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at'   => ['required', 'date', 'after:expected_departure_at'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.description'   => ['required', 'string', 'max:255'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.weight_kg'     => ['nullable', 'numeric', 'gt:0'],
        ];

        // Hanya staff yang boleh memilih customer dan mengisi kurs
        if ($this->user()->isStaff()) {
            $rules['customer_id'] = ['required', Rule::exists('users', 'id')->where('role', 'customer')];
            $rules['exchange_rate'] = ['nullable', 'numeric', 'gt:0', 'max:9999999999.99999999'];
        }

        return $rules;
    }
}