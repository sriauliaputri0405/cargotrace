<?php

namespace App\Providers;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Observers\ShipmentExceptionObserver;
use App\Observers\ShipmentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Shipment::observe(ShipmentObserver::class);
        ShipmentException::observe(ShipmentExceptionObserver::class);
    }
}