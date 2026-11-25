<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $float = fn ($v) => $v === null ? null : (float) $v;

        return [
            'id'              => $this->id,
            'shipment_number' => $this->shipment_number,
            'status'          => $this->status,
            'cargo_type'      => $this->cargo_type,
            'weight_kg'       => $float($this->weight_kg),
            'volume_cbm'      => $float($this->volume_cbm),

            'customer' => $this->whenLoaded('customer', fn () => new UserResource($this->customer)),
            'carrier'  => $this->whenLoaded('carrier', fn () => new CarrierResource($this->carrier)),
            'origin'   => $this->whenLoaded('origin', fn () => new LocationResource($this->origin)),
            'destination' => $this->whenLoaded('destination', fn () => new LocationResource($this->destination)),

            'freight' => [
                'amount'        => $float($this->freight_cost),
                'currency'      => $this->currency_code,
                'exchange_rate' => $float($this->exchange_rate),
                'amount_idr'    => $float($this->freight_cost_idr),
            ],

            'schedule' => [
                'expected_departure_at' => $this->expected_departure_at?->toIso8601String(),
                'expected_arrival_at'   => $this->expected_arrival_at?->toIso8601String(),
                'estimated_arrival_at'  => $this->estimated_arrival_at?->toIso8601String(),
            ],

            'delay' => [
                'is_delayed' => (bool) $this->is_delayed,
                'hours'      => (int) $this->delay_hours,
                'severity'   => $this->delay_severity,
            ],

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'          => $i->id,
                'description' => $i->description,
                'quantity'    => (int) $i->quantity,
                'weight_kg'   => $float($i->weight_kg),
            ])),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}