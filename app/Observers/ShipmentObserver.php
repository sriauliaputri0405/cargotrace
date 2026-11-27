<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Services\ShipmentNotifier;

class ShipmentObserver
{
    public function __construct(private ShipmentNotifier $notifier)
    {
    }

    public function updated(Shipment $shipment): void
    {
        if ($shipment->wasChanged('status')) {
            $this->notifier->send(
                $shipment,
                'Status shipment berubah',
                "Status {$shipment->shipment_number} sekarang " . str_replace('_', ' ', $shipment->status) . '.',
            );
        }

        if ($shipment->wasChanged(['is_delayed', 'delay_severity'])) {
            if ($shipment->is_delayed) {
                $this->notifier->send(
                    $shipment,
                    'Shipment terlambat',
                    "{$shipment->shipment_number} terlambat {$shipment->delay_hours} jam (severity {$shipment->delay_severity}).",
                    $shipment->delay_severity === 'HIGH' ? 'danger' : 'warning',
                    includeStaff: true,
                );
            } else {
                $this->notifier->send(
                    $shipment,
                    'Keterlambatan teratasi',
                    "{$shipment->shipment_number} tidak lagi ditandai terlambat.",
                );
            }
        }
    }
}