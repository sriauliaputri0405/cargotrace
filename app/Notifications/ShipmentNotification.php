<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ShipmentNotification extends Notification
{
    public function __construct(
        public int $shipmentId,
        public string $shipmentNumber,
        public string $title,
        public string $message,
        public string $level = 'info', // info | warning | danger
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'shipment_id'     => $this->shipmentId,
            'shipment_number' => $this->shipmentNumber,
            'title'           => $this->title,
            'message'         => $this->message,
            'level'           => $this->level,
        ];
    }
}