<?php

namespace App\Observers;

use App\Models\ShipmentException;
use App\Services\ShipmentNotifier;

class ShipmentExceptionObserver
{
    public function __construct(private ShipmentNotifier $notifier)
    {
    }

    public function created(ShipmentException $exception): void
    {
        $exception->loadMissing('shipment.customer');

        $this->notifier->send(
            $exception->shipment,
            'Exception baru',
            "{$exception->shipment->shipment_number}: " . str_replace('_', ' ', $exception->type) . " (severity {$exception->severity}).",
            $exception->severity === 'HIGH' ? 'danger' : 'warning',
            includeStaff: true,
        );
    }

    public function updated(ShipmentException $exception): void
    {
        if (! $exception->wasChanged('status')) {
            return;
        }

        $exception->loadMissing('shipment.customer');

        $this->notifier->send(
            $exception->shipment,
            'Status exception berubah',
            "{$exception->shipment->shipment_number}: " . str_replace('_', ' ', $exception->type) . " menjadi {$exception->status}.",
        );
    }
}