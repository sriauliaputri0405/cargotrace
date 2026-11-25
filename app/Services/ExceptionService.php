<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ExceptionService
{
    private const TRANSITIONS = [
        'OPEN'          => ['INVESTIGATING', 'RESOLVED'],
        'INVESTIGATING' => ['RESOLVED'],
        'RESOLVED'      => [],
    ];

    public function report(Shipment $shipment, array $data, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            $exception = $shipment->exceptions()->create([
                'type'        => $data['type'],
                'severity'    => $data['severity'],
                'status'      => 'OPEN',
                'description' => $data['description'] ?? null,
                'detected_at' => now(),
                'created_by'  => $actor->id,
            ]);

            // Muncul di timeline sebagai event exception
            $shipment->trackingEvents()->create([
                'event_type'   => 'EXCEPTION_' . $exception->type,
                'occurred_at'  => now(),
                'description'  => $exception->description ?: 'Exception dilaporkan: ' . $exception->type,
                'is_exception' => true,
                'created_by'   => $actor->id,
            ]);

            $this->audit($actor, 'exception.reported', $exception, null, $exception->only(['shipment_id', 'type', 'severity', 'status']));

            return $exception;
        });
    }

    public function transition(ShipmentException $exception, string $new, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($exception, $new, $actor) {
            $old = $exception->status;

            if (! in_array($new, self::TRANSITIONS[$old] ?? [], true)) {
                throw new DomainException("Status exception tidak bisa berubah dari {$old} ke {$new}.");
            }

            $exception->update([
                'status'      => $new,
                'resolved_at' => $new === 'RESOLVED' ? now() : null,
            ]);

            $exception->shipment->trackingEvents()->create([
                'event_type'   => $new === 'RESOLVED' ? 'EXCEPTION_RESOLVED' : 'EXCEPTION_INVESTIGATING',
                'occurred_at'  => now(),
                'description'  => "Exception {$exception->type}: {$old} menjadi {$new}",
                'is_exception' => $new !== 'RESOLVED',
                'created_by'   => $actor->id,
            ]);

            $this->audit($actor, 'exception.status_changed', $exception, ['status' => $old], ['status' => $new]);

            return $exception;
        });
    }

    private function audit(User $actor, string $action, ShipmentException $exception, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'     => $actor->id,
            'action'      => $action,
            'entity_type' => 'shipment_exception',
            'entity_id'   => $exception->id,
            'old_values'  => $old,
            'new_values'  => $new,
        ]);
    }
}