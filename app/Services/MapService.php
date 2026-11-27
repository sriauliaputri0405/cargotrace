<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;

class MapService
{
    public function forShipment(Shipment $shipment): array
    {
        $shipment->loadMissing(['origin', 'destination']);

        $events = $shipment->trackingEvents()
            ->with('location')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $status = 'Status: ' . str_replace('_', ' ', $shipment->status);
        $markers = [];

        $markers[] = $this->marker('origin', $shipment->origin, 'Asal: ' . $shipment->origin->name, [
            $status,
            'Berangkat (rencana): ' . $shipment->expected_departure_at->format('d M Y H:i'),
            'Lokasi: ' . $this->place($shipment->origin),
        ]);

        $markers[] = $this->marker('destination', $shipment->destination, 'Tujuan: ' . $shipment->destination->name, [
            $status,
            'Tiba (rencana): ' . $shipment->expected_arrival_at->format('d M Y H:i'),
            'Lokasi: ' . $this->place($shipment->destination),
        ]);

        $track = [];
        $lastIndex = $events->count() - 1;

        foreach ($events as $i => $event) {
            $lines = ['Waktu: ' . $event->occurred_at->format('d M Y H:i')];
            if ($event->location) {
                $lines[] = 'Lokasi: ' . $this->place($event->location);
            }
            if ($event->description) {
                $lines[] = $event->description;
            }

            $markers[] = [
                'type'  => $i === $lastIndex ? 'current' : 'tracking',
                'lat'   => (float) $event->latitude,
                'lng'   => (float) $event->longitude,
                'title' => str_replace('_', ' ', $event->event_type),
                'lines' => $lines,
            ];

            $track[] = [(float) $event->latitude, (float) $event->longitude];
        }

        $origin = [(float) $shipment->origin->latitude, (float) $shipment->origin->longitude];
        $destination = [(float) $shipment->destination->latitude, (float) $shipment->destination->longitude];

        $lines = [['style' => 'planned', 'points' => [$origin, $destination]]];

        if ($track) {
            $lines[] = ['style' => 'actual', 'points' => array_merge([$origin], $track)];
        }

        return ['markers' => $markers, 'lines' => $lines];
    }

    /** Peta dashboard: posisi terkini shipment yang masih aktif. */
    public function forDashboard(User $user, int $limit = 50): array
    {
        $shipments = Shipment::query()
            ->with(['origin', 'destination'])
            ->whereIn('status', ['BOOKED', 'PICKED_UP', 'IN_TRANSIT'])
            ->when($user->isCustomer(), fn ($q) => $q->where('customer_id', $user->id))
            ->latest()
            ->limit($limit)
            ->get();

        // Satu query untuk semua titik tracking, lalu ambil yang terbaru per shipment
        $latest = TrackingEvent::query()
            ->with('location')
            ->whereIn('shipment_id', $shipments->pluck('id'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy('shipment_id')
            ->map->last();

        $markers = [];
        $lines = [];

        foreach ($shipments as $s) {
            $event = $latest->get($s->id);

            $lat = $event ? (float) $event->latitude : (float) $s->origin->latitude;
            $lng = $event ? (float) $event->longitude : (float) $s->origin->longitude;

            $markers[] = [
                'type'  => $s->is_delayed ? 'delayed' : 'shipment',
                'lat'   => $lat,
                'lng'   => $lng,
                'title' => $s->shipment_number,
                'lines' => array_filter([
                    'Status: ' . str_replace('_', ' ', $s->status) . ($s->is_delayed ? ' (terlambat)' : ''),
                    'Rute: ' . $s->origin->city . ' → ' . $s->destination->city,
                    'ETA: ' . ($s->estimated_arrival_at ?? $s->expected_arrival_at)->format('d M Y H:i'),
                    $event ? 'Update terakhir: ' . $event->occurred_at->format('d M Y H:i') : 'Belum ada titik tracking',
                    $event?->location ? 'Lokasi: ' . $this->place($event->location) : null,
                ]),
            ];

            $lines[] = ['style' => 'planned', 'points' => [
                [(float) $s->origin->latitude, (float) $s->origin->longitude],
                [(float) $s->destination->latitude, (float) $s->destination->longitude],
            ]];
        }

        return ['markers' => $markers, 'lines' => $lines];
    }

    /** Posisi terakhir shipment (dipakai untuk cuaca): titik tracking terbaru, kalau tidak ada pakai asal. */
    public function latestPosition(Shipment $shipment): ?array
    {
        $event = $shipment->trackingEvents()
            ->with('location')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        if ($event) {
            return [
                'lat'   => (float) $event->latitude,
                'lng'   => (float) $event->longitude,
                'label' => $event->location ? $this->place($event->location) : 'Titik tracking terakhir',
            ];
        }

        $origin = $shipment->origin;

        return $origin ? [
            'lat'   => (float) $origin->latitude,
            'lng'   => (float) $origin->longitude,
            'label' => 'Asal: ' . $this->place($origin) . ' (belum ada titik tracking)',
        ] : null;
    }

    private function marker(string $type, Location $location, string $title, array $lines): array
    {
        return [
            'type'  => $type,
            'lat'   => (float) $location->latitude,
            'lng'   => (float) $location->longitude,
            'title' => $title,
            'lines' => $lines,
        ];
    }

    private function place(Location $location): string
    {
        return $location->name . ', ' . $location->city . ' (' . $location->country . ')';
    }
}