<?php

namespace Tests\Concerns;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\User;

trait CreatesCargoFixtures
{
    protected Carrier $carrier;
    protected Location $origin;
    protected Location $destination;

    protected function createFixtures(): void
    {
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

    protected function makeUser(string $role = 'customer'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    protected function shipmentPayload(array $override = []): array
    {
        return array_merge([
            'carrier_id'            => $this->carrier->id,
            'origin_id'             => $this->origin->id,
            'destination_id'        => $this->destination->id,
            'cargo_type'            => 'General cargo',
            'weight_kg'             => 100,
            'freight_cost'          => 500,
            'currency_code'         => 'USD',
            'expected_departure_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'expected_arrival_at'   => now()->addDays(5)->format('Y-m-d\TH:i'),
            'items'                 => [['description' => 'Box', 'quantity' => 2]],
        ], $override);
    }
}