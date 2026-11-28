<?php

return [
    // Batas keterlambatan (jam). Ikuti parameter kelompokmu.
    'delay' => [
        'low_max'    => (int) env('DELAY_LOW_MAX_HOURS', 6),
        'medium_max' => (int) env('DELAY_MEDIUM_MAX_HOURS', 24),
    ],

    // KPI utama di dashboard: on_time_rate | avg_delay | exception_resolution
    'primary_kpi' => env('KPI_PRIMARY', 'on_time_rate'),

    // Password akun seeder. Wajib diisi (selain "password") kalau seeding di production.
    'seed_password' => env('SEED_PASSWORD'),

    'event_types' => [
        'LOCATION_UPDATE' => 'Pembaruan lokasi',
        'ARRIVED'         => 'Tiba di lokasi',
        'DEPARTED'        => 'Berangkat dari lokasi',
        'CUSTOMS_CLEARED' => 'Lolos bea cukai',
        'NOTE'            => 'Catatan',
    ],

    'exception_types' => [
        'CUSTOMS_HOLD', 'WEATHER_DELAY', 'PORT_CONGESTION',
        'DAMAGED', 'MISROUTED', 'DOCUMENTATION_ISSUE',
    ],
];