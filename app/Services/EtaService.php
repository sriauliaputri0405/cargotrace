<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EtaService
{
    public function __construct(private array $thresholds = [])
    {
    }

    public function thresholds(): array
    {
        return $this->thresholds ?: config('cargotrace.delay');
    }

    /**
     * Aturan deterministik:
     * - estimated <= expected  -> tidak terlambat
     * - selisih dibulatkan ke atas ke jam penuh, lalu dicocokkan dengan threshold
     */
    public function calculate(Carbon $expected, ?Carbon $estimated): array
    {
        if ($estimated === null || ! $estimated->greaterThan($expected)) {
            return ['delayed' => false, 'hours' => 0, 'severity' => null];
        }

        $hours = (int) ceil(abs($expected->diffInMinutes($estimated)) / 60);
        $t = $this->thresholds();

        $severity = match (true) {
            $hours <= $t['low_max']    => 'LOW',
            $hours <= $t['medium_max'] => 'MEDIUM',
            default                    => 'HIGH',
        };

        return ['delayed' => true, 'hours' => $hours, 'severity' => $severity];
    }

    /** Hitung ulang dan simpan hasilnya ke shipment. */
    public function evaluate(Shipment $shipment): array
    {
        $result = $this->calculate($shipment->expected_arrival_at, $shipment->estimated_arrival_at);

        $shipment->update([
            'is_delayed'     => $result['delayed'],
            'delay_hours'    => $result['hours'],
            'delay_severity' => $result['severity'],
        ]);

        return $result;
    }

    /** Alasan dan nilai yang dipakai, supaya hasilnya bisa dijelaskan (syarat brief). */
    public function explain(Shipment $shipment): array
    {
        $t = $this->thresholds();
        $format = 'd M Y H:i';
        $lines = ['Expected arrival: ' . $shipment->expected_arrival_at->format($format)];

        if ($shipment->estimated_arrival_at === null) {
            $lines[] = 'Estimated arrival: belum diisi.';
            $lines[] = 'Hasil: tidak ditandai terlambat.';
        } else {
            $lines[] = 'Estimated arrival: ' . $shipment->estimated_arrival_at->format($format);
            $result = $this->calculate($shipment->expected_arrival_at, $shipment->estimated_arrival_at);

            $lines[] = $result['delayed']
                ? "Hasil: terlambat {$result['hours']} jam, severity {$result['severity']}."
                : 'Hasil: estimated arrival tidak melewati expected arrival, tidak terlambat.';
        }

        $lines[] = "Threshold: LOW sampai {$t['low_max']} jam, MEDIUM sampai {$t['medium_max']} jam, HIGH di atas {$t['medium_max']} jam.";

        return $lines;
    }

    public function updateEstimate(Shipment $shipment, Carbon $estimated, User $actor, ?string $note = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $estimated, $actor, $note) {
            $old = $shipment->only(['estimated_arrival_at', 'is_delayed', 'delay_hours', 'delay_severity']);

            $shipment->update(['estimated_arrival_at' => $estimated]);
            $result = $this->evaluate($shipment);

            $text = $result['delayed']
                ? "ETA diperbarui: terlambat {$result['hours']} jam (severity {$result['severity']})."
                : 'ETA diperbarui: tidak terlambat.';

            $shipment->trackingEvents()->create([
                'event_type'   => 'ETA_UPDATED',
                'occurred_at'  => now(),
                'description'  => $note ? $text . ' ' . $note : $text,
                'is_exception' => $result['delayed'],
                'created_by'   => $actor->id,
            ]);

            AuditLog::create([
                'user_id'     => $actor->id,
                'action'      => 'shipment.eta_updated',
                'entity_type' => 'shipment',
                'entity_id'   => $shipment->id,
                'old_values'  => $old,
                'new_values'  => $shipment->only(['estimated_arrival_at', 'is_delayed', 'delay_hours', 'delay_severity']),
            ]);

            return $shipment;
        });
    }
}