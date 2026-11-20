<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentService
{
    public const STATUSES = ['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'];

    // Aturan bisnis: status hanya boleh maju satu langkah, tidak boleh loncat atau mundur
    private const TRANSITIONS = [
        'CREATED'    => ['BOOKED'],
        'BOOKED'     => ['PICKED_UP'],
        'PICKED_UP'  => ['IN_TRANSIT'],
        'IN_TRANSIT' => ['DELIVERED'],
        'DELIVERED'  => [],
    ];

    // Kolom yang dicatat ke audit log
    private const AUDITED = [
        'shipment_number', 'customer_id', 'carrier_id', 'origin_id', 'destination_id',
        'cargo_type', 'weight_kg', 'volume_cbm', 'freight_cost', 'currency_code',
        'exchange_rate', 'freight_cost_idr', 'expected_departure_at',
        'expected_arrival_at', 'status',
    ];

    public function allowedNext(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->allowedNext($from), true);
    }

    public function create(array $data, array $items, User $actor): Shipment
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            // Nomor sementara dulu; nomor final dibuat dari id supaya pasti unik
            $shipment = Shipment::create(array_merge($this->prepare($data), [
                'shipment_number' => 'TMP-' . Str::random(20),
                'status'          => 'CREATED',
                'created_by'      => $actor->id,
            ]));

            $shipment->update([
                'shipment_number' => sprintf('SHP-%s-%06d', now()->format('Y'), $shipment->id),
            ]);

            $shipment->items()->createMany(array_values($items));

            // Event pertama di timeline
            $shipment->trackingEvents()->create([
                'location_id' => $shipment->origin_id,
                'event_type'  => 'CREATED',
                'occurred_at' => now(),
                'description' => 'Shipment dibuat',
                'created_by'  => $actor->id,
            ]);

            $this->audit($actor, 'shipment.created', $shipment, null, $shipment->only(self::AUDITED));

            return $shipment;
        });
    }

    public function update(Shipment $shipment, array $data, array $items, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $items, $actor) {
            $old = $shipment->only(self::AUDITED);

            $shipment->update($this->prepare($data));
            $shipment->items()->delete();
            $shipment->items()->createMany(array_values($items));

            $this->audit($actor, 'shipment.updated', $shipment, $old, $shipment->only(self::AUDITED));

            return $shipment;
        });
    }

    public function changeStatus(
        Shipment $shipment,
        string $new,
        User $actor,
        ?int $locationId = null,
        ?string $note = null
    ): Shipment {
        return DB::transaction(function () use ($shipment, $new, $actor, $locationId, $note) {
            // Kunci baris supaya dua operator tidak mengubah status bersamaan
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $old = $locked->status;

            if (! $this->canTransition($old, $new)) {
                throw new DomainException("Status tidak bisa berubah dari {$old} ke {$new}.");
            }

            $locked->update(['status' => $new]);

            // Riwayat bersifat historis: hanya ditambah, tidak pernah menimpa
            $locked->trackingEvents()->create([
                'location_id' => $locationId,
                'event_type'  => $new,
                'occurred_at' => now(),
                'description' => $note ?: 'Status diubah menjadi ' . $new,
                'created_by'  => $actor->id,
            ]);

            $this->audit($actor, 'shipment.status_changed', $locked, ['status' => $old], ['status' => $new]);

            return $locked;
        });
    }

    public function delete(Shipment $shipment, User $actor): void
    {
        DB::transaction(function () use ($shipment, $actor) {
            $this->audit($actor, 'shipment.deleted', $shipment, $shipment->only(self::AUDITED), null);
            $shipment->delete();
        });
    }

    // Menyiapkan data biaya: mata uang huruf besar dan konversi ke rupiah
    private function prepare(array $data): array
    {
        if (isset($data['currency_code'])) {
            $data['currency_code'] = strtoupper($data['currency_code']);
        }

        if (($data['currency_code'] ?? null) === 'IDR') {
            $data['exchange_rate'] = 1;
        }

        $cost = $data['freight_cost'] ?? null;
        $rate = $data['exchange_rate'] ?? null;

        $data['freight_cost_idr'] = ($cost !== null && $rate !== null)
            ? round((float) $cost * (float) $rate, 2)
            : null;

        return $data;
    }

    private function audit(User $actor, string $action, Shipment $shipment, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'     => $actor->id,
            'action'      => $action,
            'entity_type' => 'shipment',
            'entity_id'   => $shipment->id,
            'old_values'  => $old,
            'new_values'  => $new,
        ]);
    }
}