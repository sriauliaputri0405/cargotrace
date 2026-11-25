<?php

namespace Tests\Feature\Api;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    private Carrier $carrier;
    private Location $origin;
    private Location $destination;

    protected function setUp(): void
    {
        parent::setUp();

        // Data fixture khusus test, tidak masuk database aplikasi
        $this->carrier = Carrier::create(['name' => 'Test Carrier', 'code' => 'TC1', 'is_active' => true]);
        $this->origin = Location::create([
            'name' => 'Origin', 'city' => 'City A', 'country' => 'ID',
            'type' => 'port', 'latitude' => 1, 'longitude' => 1,
        ]);
        $this->destination = Location::create([
            'name' => 'Destination', 'city' => 'City B', 'country' => 'SG',
            'type' => 'port', 'latitude' => 2, 'longitude' => 2,
        ]);
    }

    private function makeUser(string $role = 'customer'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'carrier_id'            => $this->carrier->id,
            'origin_id'             => $this->origin->id,
            'destination_id'        => $this->destination->id,
            'cargo_type'            => 'General cargo',
            'weight_kg'             => 100,
            'freight_cost'          => 500,
            'currency_code'         => 'USD',
            'expected_departure_at' => now()->addDay()->toDateTimeString(),
            'expected_arrival_at'   => now()->addDays(5)->toDateTimeString(),
            'items'                 => [['description' => 'Box', 'quantity' => 2]],
        ], $override);
    }

    public function test_register_login_and_logout(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New User', 'email' => 'new@example.test', 'password' => 'secret-pass-1',
        ])->assertCreated()->assertJsonPath('user.role', 'customer');

        $token = $this->postJson('/api/login', [
            'email' => 'new@example.test', 'password' => 'secret-pass-1',
        ])->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1); // sisa token dari register
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertStatus(401);
    }

    public function test_registration_validates_input(): void
    {
        $this->postJson('/api/register', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_guest_cannot_access_shipments(): void
    {
        $this->getJson('/api/shipments')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_unknown_shipment_returns_404(): void
    {
        Sanctum::actingAs($this->makeUser('operator'));

        $this->getJson('/api/shipments/999999')->assertNotFound();
    }

    public function test_customer_only_sees_own_shipments(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        Sanctum::actingAs($a);
        $this->postJson('/api/shipments', $this->payload())->assertCreated();

        Sanctum::actingAs($b);
        $otherId = $this->postJson('/api/shipments', $this->payload())->assertCreated()->json('data.id');

        Sanctum::actingAs($a);
        $this->getJson('/api/shipments')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/shipments/{$otherId}")->assertForbidden();
    }

    public function test_customer_cannot_create_shipment_for_someone_else(): void
    {
        $customer = $this->makeUser();
        $other = $this->makeUser();

        Sanctum::actingAs($customer);
        $this->postJson('/api/shipments', $this->payload(['customer_id' => $other->id]))
            ->assertCreated()
            ->assertJsonPath('data.customer.id', $customer->id);
    }

    public function test_shipment_validation_rejects_bad_input(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/shipments', $this->payload([
            'destination_id' => $this->origin->id, // sama dengan asal
            'items' => [],
        ]))->assertStatus(422)->assertJsonValidationErrors(['destination_id', 'items']);
    }

    public function test_operator_follows_status_workflow(): void
    {
        $customer = $this->makeUser();
        Sanctum::actingAs($customer);
        $id = $this->postJson('/api/shipments', $this->payload())->json('data.id');

        Sanctum::actingAs($this->makeUser('operator'));

        $this->patchJson("/api/shipments/{$id}/status", ['status' => 'DELIVERED'])->assertStatus(422);
        $this->patchJson("/api/shipments/{$id}/status", ['status' => 'BOOKED'])
            ->assertOk()->assertJsonPath('data.status', 'BOOKED');
    }

    public function test_customer_cannot_change_status_or_create_carrier(): void
    {
        Sanctum::actingAs($this->makeUser());
        $id = $this->postJson('/api/shipments', $this->payload())->json('data.id');

        $this->patchJson("/api/shipments/{$id}/status", ['status' => 'BOOKED'])->assertForbidden();
        $this->postJson('/api/carriers', ['name' => 'X', 'code' => 'XX'])->assertForbidden();
    }

    public function test_only_admin_can_delete_a_created_shipment(): void
    {
        Sanctum::actingAs($this->makeUser());
        $id = $this->postJson('/api/shipments', $this->payload())->json('data.id');

        Sanctum::actingAs($this->makeUser('operator'));
        $this->deleteJson("/api/shipments/{$id}")->assertForbidden();

        Sanctum::actingAs($this->makeUser('admin'));
        $this->deleteJson("/api/shipments/{$id}")->assertNoContent();
    }

    public function test_tracking_event_cannot_be_in_the_future(): void
    {
        Sanctum::actingAs($this->makeUser());
        $id = $this->postJson('/api/shipments', $this->payload())->json('data.id');

        Sanctum::actingAs($this->makeUser('operator'));
        $this->postJson("/api/shipments/{$id}/tracking", [
            'event_type'  => 'NOTE',
            'occurred_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_exception_workflow(): void
    {
        Sanctum::actingAs($this->makeUser());
        $id = $this->postJson('/api/shipments', $this->payload())->json('data.id');

        Sanctum::actingAs($this->makeUser('operator'));
        $exceptionId = $this->postJson("/api/shipments/{$id}/exceptions", [
            'type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/exceptions/{$exceptionId}", ['status' => 'RESOLVED'])->assertOk();
        // RESOLVED bersifat final
        $this->patchJson("/api/exceptions/{$exceptionId}", ['status' => 'INVESTIGATING'])->assertStatus(422);
    }
}