<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // daftar dibatasi per role di controller
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->isStaff() || (int) $shipment->customer_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->isStaff() && $shipment->status !== 'DELIVERED';
    }

    public function changeStatus(User $user, Shipment $shipment): bool
    {
        return $user->isStaff() && $shipment->status !== 'DELIVERED';
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin() && $shipment->status === 'CREATED';
    }
}