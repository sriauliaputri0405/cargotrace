<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'event_type'   => $this->event_type,
            'occurred_at'  => $this->occurred_at?->toIso8601String(),
            'is_exception' => (bool) $this->is_exception,
            'description'  => $this->description,
            'latitude'     => $this->latitude === null ? null : (float) $this->latitude,
            'longitude'    => $this->longitude === null ? null : (float) $this->longitude,
            'location'     => $this->whenLoaded('location', fn () => $this->location ? new LocationResource($this->location) : null),
            'created_by'   => $this->whenLoaded('creator', fn () => $this->creator?->name),
        ];
    }
}