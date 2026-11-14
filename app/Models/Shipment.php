<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'shipment_number', 'customer_id', 'carrier_id', 'origin_id', 'destination_id',
        'cargo_type', 'weight_kg', 'volume_cbm',
        'freight_cost', 'currency_code', 'exchange_rate', 'freight_cost_idr',
        'expected_departure_at', 'expected_arrival_at', 'estimated_arrival_at',
        'status', 'is_delayed', 'delay_hours', 'delay_severity', 'created_by',
    ];

    protected $casts = [
        'expected_departure_at' => 'datetime',
        'expected_arrival_at'   => 'datetime',
        'estimated_arrival_at'  => 'datetime',
        'is_delayed'            => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }
}