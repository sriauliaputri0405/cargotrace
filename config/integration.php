<?php

return [
    // Batas waktu tunggu semua layanan eksternal (detik)
    'timeout' => (int) env('INTEGRATION_TIMEOUT', 5),

    'weather' => [
        'base_url' => env('WEATHER_BASE_URL', 'https://api.open-meteo.com/v1'),
    ],

    'geocoding' => [
        'base_url'   => env('GEOCODING_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('GEOCODING_USER_AGENT'),
    ],

    'fx' => [
        'base_url' => env('FX_BASE_URL', 'https://api.frankfurter.dev/v1'),
    ],
];