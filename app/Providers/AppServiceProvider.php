<?php

namespace App\Providers;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Observers\ShipmentExceptionObserver;
use App\Observers\ShipmentObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        // API: 60 request per menit per pengguna (atau per IP kalau belum login)
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Di production semua link dibuat dengan https
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}