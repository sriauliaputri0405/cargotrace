<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsAndAdminTest extends TestCase
{
    use RefreshDatabase;

    private Carrier $carrier;
    private Location $origin;
    private Location $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Data fixture khusus test
        $this->carrier = Carrier::create(['name' => 'Carrier A', 'code' => 'CA', 'is_active' => true]);
        $this->origin = Location::create(['name' => 'O', 'city' => 'C1', 'country' => 'ID', 'type' => 'port', 'latitude' => 1, 'longitude' => 1]);
        $this->destination = Location::create(['name' => 'D', 'city' => 'C2', 'country' => 'SG', 'type' => 'port', 'latitude' => 2, 'longitude' => 2]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function shipment(User $customer): Shipment
    {
        return Shipment::create([
            'shipment_number'       => 'T-' . uniqid(),
            'customer_id'           => $customer->id,
            'carrier_id'            => $this->carrier->id,
            'origin_id'             => $this->origin->id,
            'destination_id'        => $this->destination->id,
            'cargo_type'            => 'x',
            'weight_kg'             => 1,
            'expected_departure_at' => now(),
            'expected_arrival_at'   => now()->addDay(),
            'status'                => 'CREATED',
            'created_by'            => $customer->id,
        ]);
    }

    public function test_status_change_notifies_the_customer_but_not_the_actor(): void
    {
        $customer = $this->makeUser('customer');
        $operator = $this->makeUser('operator');
        $shipment = $this->shipment($customer);

        $this->actingAs($operator);
        $shipment->update(['status' => 'BOOKED']);

        $this->assertCount(1, $customer->notifications);
        $this->assertCount(0, $operator->notifications);
    }

    public function test_changing_unrelated_fields_sends_no_notification(): void
    {
        $customer = $this->makeUser('customer');
        $shipment = $this->shipment($customer);

        $this->actingAs($this->makeUser('operator'));
        $shipment->update(['shipment_number' => 'NEW-1']);

        $this->assertCount(0, $customer->notifications);
    }

    public function test_delay_notifies_customer_and_staff_except_actor(): void
    {
        $customer = $this->makeUser('customer');
        $operator = $this->makeUser('operator');
        $admin = $this->makeUser('admin');
        $shipment = $this->shipment($customer);

        $this->actingAs($operator);
        $shipment->update(['is_delayed' => true, 'delay_hours' => 8, 'delay_severity' => 'MEDIUM']);

        $this->assertCount(1, $customer->notifications);
        $this->assertCount(1, $admin->notifications);
        $this->assertCount(0, $operator->notifications);
        $this->assertSame('warning', $customer->notifications->first()->data['level']);
    }

    public function test_new_exception_notifies_customer_and_staff(): void
    {
        $customer = $this->makeUser('customer');
        $operator = $this->makeUser('operator');
        $admin = $this->makeUser('admin');
        $shipment = $this->shipment($customer);

        $this->actingAs($operator);
        ShipmentException::create([
            'shipment_id' => $shipment->id, 'type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH',
            'status' => 'OPEN', 'detected_at' => now(), 'created_by' => $operator->id,
        ]);

        $this->assertCount(1, $customer->notifications);
        $this->assertCount(1, $admin->notifications);
        $this->assertSame('danger', $customer->notifications->first()->data['level']);
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $customer = $this->makeUser('customer');
        $shipment = $this->shipment($customer);

        $this->actingAs($this->makeUser('operator'));
        $shipment->update(['status' => 'BOOKED']);

        $notification = $customer->fresh()->notifications->first();

        $this->actingAs($customer)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('shipments.show', $shipment));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_open_someone_elses_notification(): void
    {
        $customer = $this->makeUser('customer');
        $other = $this->makeUser('customer');
        $shipment = $this->shipment($customer);

        $this->actingAs($this->makeUser('operator'));
        $shipment->update(['status' => 'BOOKED']);

        $notification = $customer->fresh()->notifications->first();

        $this->actingAs($other)
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();
    }

    public function test_only_admin_can_view_audit_log_and_users(): void
    {
        $this->actingAs($this->makeUser('admin'))->get(route('audit-logs.index'))->assertOk();
        $this->actingAs($this->makeUser('admin'))->get(route('users.index'))->assertOk();

        $this->actingAs($this->makeUser('operator'))->get(route('audit-logs.index'))->assertForbidden();
        $this->actingAs($this->makeUser('customer'))->get(route('users.index'))->assertForbidden();
    }

    public function test_admin_can_change_another_users_role_and_it_is_audited(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('customer');

        $this->actingAs($admin)
            ->patch(route('users.role', $target), ['role' => 'operator'])
            ->assertRedirect();

        $this->assertSame('operator', $target->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.role_changed', 'entity_id' => $target->id, 'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->patch(route('users.role', $admin), ['role' => 'customer'])
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_operator_cannot_change_roles(): void
    {
        $target = $this->makeUser('customer');

        $this->actingAs($this->makeUser('operator'))
            ->patch(route('users.role', $target), ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame('customer', $target->fresh()->role);
    }

    public function test_admin_can_create_an_operator_account(): void
    {
        $this->actingAs($this->makeUser('admin'))->post(route('users.store'), [
            'name' => 'New Operator', 'email' => 'op@example.test',
            'password' => 'secret-pass-1', 'role' => 'operator',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('operator', User::where('email', 'op@example.test')->first()->role);
    }

    public function test_role_cannot_be_set_through_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-pass-1', 'role' => 'admin',
        ])->refresh();

        $this->assertSame('customer', $user->role);
    }
}