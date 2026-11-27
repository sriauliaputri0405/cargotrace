<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Carrier $carrier;
    private Location $origin;
    private Location $destination;

    protected function setUp(): void
    {
        parent::setUp();

        // Data fixture khusus test
        $this->customer = User::factory()->create();
        $this->carrier = Carrier::create(['name' => 'Carrier A', 'code' => 'CA', 'is_active' => true]);
        $this->origin = Location::create(['name' => 'O', 'city' => 'C1', 'country' => 'ID', 'type' => 'port', 'latitude' => 1, 'longitude' => 1]);
        $this->destination = Location::create(['name' => 'D', 'city' => 'C2', 'country' => 'SG', 'type' => 'port', 'latitude' => 2, 'longitude' => 2]);
    }

    private function shipment(array $override = []): Shipment
    {
        return Shipment::create(array_merge([
            'shipment_number'       => 'T-' . uniqid(),
            'customer_id'           => $this->customer->id,
            'carrier_id'            => $this->carrier->id,
            'origin_id'             => $this->origin->id,
            'destination_id'        => $this->destination->id,
            'cargo_type'            => 'x',
            'weight_kg'             => 1,
            'expected_departure_at' => now(),
            'expected_arrival_at'   => now()->addDay(),
            'status'                => 'CREATED',
            'created_by'            => $this->customer->id,
        ], $override));
    }

    private function exception(Shipment $s, string $detected, ?string $resolved, string $status): ShipmentException
    {
        return ShipmentException::create([
            'shipment_id' => $s->id, 'type' => 'CUSTOMS_HOLD', 'severity' => 'LOW',
            'status' => $status, 'detected_at' => $detected, 'resolved_at' => $resolved,
            'created_by' => $this->customer->id,
        ]);
    }

    public function test_kpis_are_null_when_there_is_no_data(): void
    {
        $kpis = app(AnalyticsService::class)->kpis();

        $this->assertNull($kpis['on_time_rate']);
        $this->assertNull($kpis['avg_delay']);
        $this->assertNull($kpis['exception_resolution']);
    }

    public function test_on_time_delivery_rate(): void
    {
        $this->shipment(['status' => 'DELIVERED', 'is_delayed' => false]);
        $this->shipment(['status' => 'DELIVERED', 'is_delayed' => false]);
        $this->shipment(['status' => 'DELIVERED', 'is_delayed' => true, 'delay_hours' => 5]);
        $this->shipment(['status' => 'IN_TRANSIT']); // tidak dihitung

        $this->assertSame(66.7, app(AnalyticsService::class)->kpis()['on_time_rate']);
    }

    public function test_average_delay_duration_counts_only_delayed_shipments(): void
    {
        $this->shipment(['is_delayed' => true, 'delay_hours' => 4]);
        $this->shipment(['is_delayed' => true, 'delay_hours' => 10]);
        $this->shipment(['is_delayed' => false, 'delay_hours' => 0]);

        $this->assertSame(7.0, app(AnalyticsService::class)->kpis()['avg_delay']);
    }

    public function test_exception_resolution_time_uses_resolved_exceptions_only(): void
    {
        $s = $this->shipment();
        $this->exception($s, '2026-01-01 08:00:00', '2026-01-01 10:00:00', 'RESOLVED'); // 2 jam
        $this->exception($s, '2026-01-02 08:00:00', '2026-01-02 12:00:00', 'RESOLVED'); // 4 jam
        $this->exception($s, '2026-01-03 08:00:00', null, 'OPEN');                       // tidak dihitung

        $this->assertSame(3.0, app(AnalyticsService::class)->kpis()['exception_resolution']);
    }

    public function test_carrier_delay_rate(): void
    {
        $this->shipment(['is_delayed' => true]);
        $this->shipment(['is_delayed' => false]);
        $this->shipment(['is_delayed' => false]);
        $this->shipment(['is_delayed' => false]);

        $row = app(AnalyticsService::class)->carrierDelay()->first();

        $this->assertSame(4, $row['total']);
        $this->assertSame(1, $row['delayed']);
        $this->assertSame(25.0, $row['rate']);
    }

    public function test_customer_scope_excludes_other_customers(): void
    {
        $other = User::factory()->create();
        $this->shipment();
        $this->shipment(['customer_id' => $other->id]);

        $summary = app(AnalyticsService::class)->summary(['customer_id' => $this->customer->id]);

        $this->assertSame(1, $summary['total']);
    }

    public function test_status_counts_always_include_every_status(): void
    {
        $this->shipment(['status' => 'BOOKED']);

        $counts = app(AnalyticsService::class)->statusCounts();

        $this->assertSame(1, $counts['BOOKED']);
        $this->assertSame(0, $counts['DELIVERED']);
        $this->assertCount(5, $counts);
    }

    public function test_csv_values_that_look_like_formulas_are_neutralised(): void
    {
        $this->assertSame("'=SUM(A1)", Csv::safe('=SUM(A1)'));
        $this->assertSame("'+1", Csv::safe('+1'));
        $this->assertSame("'@cmd", Csv::safe('@cmd'));
        $this->assertSame('Normal text', Csv::safe('Normal text'));
        $this->assertSame(12.5, Csv::safe(12.5));
        $this->assertSame('yes', Csv::safe(true));
    }
}