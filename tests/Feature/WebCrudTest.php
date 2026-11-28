<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCargoFixtures;
use Tests\TestCase;

class WebCrudTest extends TestCase
{
    use RefreshDatabase, CreatesCargoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createFixtures();
        config(['cargotrace.delay' => ['low_max' => 6, 'medium_max' => 24]]);
    }

    private function createShipmentAs(User $creator, array $override = []): Shipment
    {
        $this->actingAs($creator)
            ->post('/shipments', $this->shipmentPayload($override))
            ->assertSessionHasNoErrors();

        return Shipment::latest('id')->firstOrFail();
    }

    // ---------- Carrier ----------

    public function test_admin_manages_carriers_with_validation(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->post('/carriers', ['name' => 'New Carrier', 'code' => 'NC1', 'is_active' => 1])
            ->assertRedirect('/carriers');
        $this->assertDatabaseHas('carriers', ['code' => 'NC1']);

        $this->actingAs($admin)->post('/carriers', [])->assertSessionHasErrors(['name', 'code']);
        $this->actingAs($admin)->post('/carriers', ['name' => 'Dup', 'code' => 'NC1'])->assertSessionHasErrors('code');
        $this->actingAs($admin)
            ->post('/carriers', ['name' => 'Bad', 'code' => 'BD', 'contact_email' => 'not-an-email'])
            ->assertSessionHasErrors('contact_email');
    }

    public function test_customer_cannot_create_a_carrier(): void
    {
        $this->actingAs($this->makeUser('customer'))
            ->post('/carriers', ['name' => 'X', 'code' => 'XX'])
            ->assertForbidden();
    }

    public function test_carrier_in_use_cannot_be_deleted_but_unused_one_can(): void
    {
        $admin = $this->makeUser('admin');
        $this->createShipmentAs($this->makeUser('customer'));
        $unused = Carrier::create(['name' => 'Unused', 'code' => 'UN', 'is_active' => true]);

        $this->actingAs($admin)->delete("/carriers/{$this->carrier->id}")->assertSessionHasErrors('carrier');
        $this->assertDatabaseHas('carriers', ['id' => $this->carrier->id]);

        $this->actingAs($admin)->delete("/carriers/{$unused->id}");
        $this->assertDatabaseMissing('carriers', ['id' => $unused->id]);
    }

    // ---------- Location ----------

    public function test_location_validation_and_country_normalisation(): void
    {
        $admin = $this->makeUser('admin');
        $valid = [
            'name' => 'Some Port', 'city' => 'Some City', 'country' => 'sg',
            'type' => 'port', 'latitude' => 1.5, 'longitude' => 103.8,
        ];

        $this->actingAs($admin)->post('/locations', array_merge($valid, ['country' => 'IDN']))->assertSessionHasErrors('country');
        $this->actingAs($admin)->post('/locations', array_merge($valid, ['latitude' => 100]))->assertSessionHasErrors('latitude');
        $this->actingAs($admin)->post('/locations', array_merge($valid, ['longitude' => 181]))->assertSessionHasErrors('longitude');
        $this->actingAs($admin)->post('/locations', array_merge($valid, ['type' => 'rail']))->assertSessionHasErrors('type');

        $this->actingAs($admin)->post('/locations', $valid)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('locations', ['name' => 'Some Port', 'country' => 'SG']);
    }

    public function test_location_used_by_a_shipment_cannot_be_deleted(): void
    {
        $this->createShipmentAs($this->makeUser('customer'));

        $this->actingAs($this->makeUser('admin'))
            ->delete("/locations/{$this->origin->id}")
            ->assertSessionHasErrors('location');

        $this->assertDatabaseHas('locations', ['id' => $this->origin->id]);
    }

    // ---------- Shipment ----------

    public function test_customer_creates_a_shipment_for_themselves(): void
    {
        $customer = $this->makeUser('customer');

        $shipment = $this->createShipmentAs($customer);

        $this->assertSame('CREATED', $shipment->status);
        $this->assertSame($customer->id, $shipment->customer_id);
        $this->assertMatchesRegularExpression('/^SHP-\d{4}-\d{6}$/', $shipment->shipment_number);
        $this->assertCount(1, $shipment->items);
        $this->assertCount(1, $shipment->trackingEvents); // event CREATED otomatis
    }

    public function test_shipment_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->makeUser('customer'))->post('/shipments', $this->shipmentPayload([
            'destination_id'      => $this->origin->id,                  // sama dengan asal
            'expected_arrival_at' => now()->format('Y-m-d\TH:i'),        // lebih awal dari berangkat
            'items'               => [],
            'weight_kg'           => 0,
            'currency_code'       => 'ABC',                              // bukan kode mata uang
        ]))->assertSessionHasErrors(['destination_id', 'expected_arrival_at', 'items', 'weight_kg', 'currency_code']);

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_operator_creates_a_shipment_for_a_customer_and_converts_the_cost(): void
    {
        $customer = $this->makeUser('customer');

        $shipment = $this->createShipmentAs($this->makeUser('operator'), [
            'customer_id'   => $customer->id,
            'freight_cost'  => 100,
            'currency_code' => 'USD',
            'exchange_rate' => 16000,
        ]);

        $this->assertSame($customer->id, $shipment->customer_id);
        $this->assertEquals(1600000, $shipment->freight_cost_idr);
    }

    public function test_customers_only_see_their_own_shipments_in_the_list(): void
    {
        $a = $this->makeUser('customer');
        $b = $this->makeUser('customer');
        $mine = $this->createShipmentAs($a);
        $theirs = $this->createShipmentAs($b);

        // Buang pesan flash dari pembuatan shipment tadi supaya tidak ikut terbaca di halaman daftar
        $this->flushSession();

        $this->actingAs($a)->get('/shipments')
            ->assertOk()
            ->assertSee($mine->shipment_number)
            ->assertDontSee($theirs->shipment_number);
    }

    public function test_customer_cannot_touch_someone_elses_shipment(): void
    {
        $owner = $this->makeUser('customer');
        $intruder = $this->makeUser('customer');
        $shipment = $this->createShipmentAs($owner);

        $this->actingAs($intruder);
        $this->get("/shipments/{$shipment->id}")->assertForbidden();
        $this->get("/shipments/{$shipment->id}/edit")->assertForbidden();
        $this->put("/shipments/{$shipment->id}", $this->shipmentPayload())->assertForbidden();
        $this->patch("/shipments/{$shipment->id}/status", ['status' => 'BOOKED'])->assertForbidden();
        $this->delete("/shipments/{$shipment->id}")->assertForbidden();

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'CREATED']);
    }

    public function test_owner_customer_cannot_edit_change_status_or_delete(): void
    {
        $customer = $this->makeUser('customer');
        $shipment = $this->createShipmentAs($customer);

        $this->actingAs($customer);
        $this->get("/shipments/{$shipment->id}")->assertOk();
        $this->get("/shipments/{$shipment->id}/edit")->assertForbidden();
        $this->patch("/shipments/{$shipment->id}/status", ['status' => 'BOOKED'])->assertForbidden();
        $this->delete("/shipments/{$shipment->id}")->assertForbidden();
    }

    public function test_operator_updates_shipment_data(): void
    {
        $customer = $this->makeUser('customer');
        $shipment = $this->createShipmentAs($customer);

        $this->actingAs($this->makeUser('operator'))
            ->put("/shipments/{$shipment->id}", $this->shipmentPayload([
                'customer_id' => $customer->id,
                'cargo_type'  => 'Refrigerated cargo',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Refrigerated cargo', $shipment->fresh()->cargo_type);
    }

    public function test_only_admin_can_delete_and_only_while_created(): void
    {
        $customer = $this->makeUser('customer');
        $first = $this->createShipmentAs($customer);
        $second = $this->createShipmentAs($customer);

        $this->actingAs($this->makeUser('operator'))->delete("/shipments/{$first->id}")->assertForbidden();

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->delete("/shipments/{$first->id}");
        $this->assertDatabaseMissing('shipments', ['id' => $first->id]);

        $this->actingAs($this->makeUser('operator'))->patch("/shipments/{$second->id}/status", ['status' => 'BOOKED']);
        $this->actingAs($admin)->delete("/shipments/{$second->id}")->assertForbidden();
        $this->assertDatabaseHas('shipments', ['id' => $second->id]);
    }

    // ---------- Status, tracking, ETA, exception ----------

    public function test_status_follows_the_workflow_and_rejects_skipping(): void
    {
        $operator = $this->makeUser('operator');
        $shipment = $this->createShipmentAs($this->makeUser('customer'));

        $this->actingAs($operator)
            ->patch("/shipments/{$shipment->id}/status", ['status' => 'DELIVERED'])
            ->assertSessionHasErrors('status');
        $this->assertSame('CREATED', $shipment->fresh()->status);

        foreach (['BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'] as $status) {
            $this->actingAs($operator)
                ->patch("/shipments/{$shipment->id}/status", ['status' => $status])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('DELIVERED', $shipment->fresh()->status);
        $this->assertCount(5, $shipment->trackingEvents); // CREATED + 4 perubahan status

        // Sudah DELIVERED: tidak bisa diubah lagi
        $this->actingAs($operator)->get("/shipments/{$shipment->id}/edit")->assertForbidden();
        $this->actingAs($operator)
            ->patch("/shipments/{$shipment->id}/status", ['status' => 'BOOKED'])
            ->assertForbidden();
    }

    public function test_tracking_events_are_validated_and_ordered_in_time(): void
    {
        $operator = $this->makeUser('operator');
        $shipment = $this->createShipmentAs($this->makeUser('customer'));
        $url = "/shipments/{$shipment->id}/tracking";

        $this->actingAs($operator)->post($url, [
            'event_type'  => 'NOTE',
            'location_id' => $this->origin->id,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $event = $shipment->trackingEvents()->where('event_type', 'NOTE')->firstOrFail();
        $this->assertEquals(1, $event->latitude); // koordinat diambil dari lokasi

        // di masa depan
        $this->actingAs($operator)->post($url, [
            'event_type' => 'NOTE', 'occurred_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('occurred_at');

        // lebih awal dari event terakhir
        $this->actingAs($operator)->post($url, [
            'event_type' => 'NOTE', 'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('tracking');

        // jenis event tidak dikenal
        $this->actingAs($operator)->post($url, [
            'event_type' => 'FLYING_CARPET', 'occurred_at' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('event_type');

        $this->assertCount(2, $shipment->fresh()->trackingEvents);
    }

    public function test_customer_cannot_add_tracking_or_update_eta_or_report_exceptions(): void
    {
        $customer = $this->makeUser('customer');
        $shipment = $this->createShipmentAs($customer);

        $this->actingAs($customer);
        $this->post("/shipments/{$shipment->id}/tracking", [
            'event_type' => 'NOTE', 'occurred_at' => now()->format('Y-m-d\TH:i'),
        ])->assertForbidden();
        $this->patch("/shipments/{$shipment->id}/eta", [
            'estimated_arrival_at' => now()->addDays(9)->format('Y-m-d\TH:i'),
        ])->assertForbidden();
        $this->post("/shipments/{$shipment->id}/exceptions", [
            'type' => 'CUSTOMS_HOLD', 'severity' => 'LOW',
        ])->assertForbidden();
    }

    public function test_eta_update_applies_the_delay_rule(): void
    {
        $operator = $this->makeUser('operator');
        $shipment = $this->createShipmentAs($this->makeUser('customer'));
        $url = "/shipments/{$shipment->id}/eta";
        $expected = $shipment->expected_arrival_at;

        $cases = [
            [3, 'LOW'],
            [10, 'MEDIUM'],
            [30, 'HIGH'],
        ];

        foreach ($cases as [$hours, $severity]) {
            $this->actingAs($operator)->patch($url, [
                'estimated_arrival_at' => $expected->copy()->addHours($hours)->format('Y-m-d\TH:i'),
            ])->assertSessionHasNoErrors();

            $fresh = $shipment->fresh();
            $this->assertTrue((bool) $fresh->is_delayed, "{$hours} jam harus terlambat");
            $this->assertSame($hours, (int) $fresh->delay_hours);
            $this->assertSame($severity, $fresh->delay_severity);
        }

        // ETA kembali sebelum jadwal: tidak lagi terlambat
        $this->actingAs($operator)->patch($url, [
            'estimated_arrival_at' => $expected->copy()->subHour()->format('Y-m-d\TH:i'),
        ]);

        $fresh = $shipment->fresh();
        $this->assertFalse((bool) $fresh->is_delayed);
        $this->assertNull($fresh->delay_severity);
    }

    public function test_exceptions_follow_their_own_workflow(): void
    {
        $operator = $this->makeUser('operator');
        $shipment = $this->createShipmentAs($this->makeUser('customer'));

        $this->actingAs($operator)
            ->post("/shipments/{$shipment->id}/exceptions", ['type' => 'NOT_A_TYPE', 'severity' => 'LOW'])
            ->assertSessionHasErrors('type');

        $this->actingAs($operator)
            ->post("/shipments/{$shipment->id}/exceptions", ['type' => 'CUSTOMS_HOLD', 'severity' => 'MEDIUM'])
            ->assertSessionHasNoErrors();

        $exception = $shipment->exceptions()->firstOrFail();
        $this->assertSame('OPEN', $exception->status);

        $this->actingAs($operator)->patch("/exceptions/{$exception->id}", ['status' => 'RESOLVED']);
        $this->assertSame('RESOLVED', $exception->fresh()->status);
        $this->assertNotNull($exception->fresh()->resolved_at);

        // RESOLVED bersifat final
        $this->actingAs($operator)
            ->patch("/exceptions/{$exception->id}", ['status' => 'INVESTIGATING'])
            ->assertSessionHasErrors('status');
    }
}