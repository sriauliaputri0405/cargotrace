<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ShipmentNotification;
use Illuminate\Support\Facades\Notification;

class ShipmentNotifier
{
    public function send(
        Shipment $shipment,
        string $title,
        string $message,
        string $level = 'info',
        bool $includeStaff = false
    ): void {
        $actorId = auth()->id();

        $recipients = collect([$shipment->customer ?? User::find($shipment->customer_id)]);

        if ($includeStaff) {
            $recipients = $recipients->merge(
                User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERATOR])->get()
            );
        }

        // Pelaku perubahan tidak perlu diberi tahu tentang aksinya sendiri
        $recipients = $recipients->filter()->unique('id')->reject(fn (User $u) => $u->id === $actorId);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ShipmentNotification(
            $shipment->id, $shipment->shipment_number, $title, $message, $level
        ));
    }
}