<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi oleh Policy dan middleware role
    }

    public function rules(): array
    {
        return [
            'event_type'  => ['required', Rule::in(array_keys(config('cargotrace.event_types')))],
            'location_id' => ['nullable', 'exists:locations,id'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}