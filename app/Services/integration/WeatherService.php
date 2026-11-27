<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeatherService
{
    use MakesIntegrationRequests;

    public function current(float $latitude, float $longitude): array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return $this->fail('Koordinat tidak valid.', 422);
        }

        // Dibulatkan 2 desimal (sekitar 1 km) supaya titik yang berdekatan memakai cache yang sama
        $key = sprintf('weather:%.2f:%.2f', $latitude, $longitude);

        if ($cached = Cache::get($key)) {
            return ['ok' => true, 'data' => $cached + ['cached' => true]];
        }

        try {
            $response = $this->http(config('integrations.weather.base_url'))->get('forecast', [
                'latitude'  => $latitude,
                'longitude' => $longitude,
                'current'   => 'temperature_2m,wind_speed_10m,weather_code',
                'timezone'  => 'auto',
            ]);
        } catch (Throwable $e) {
            Log::warning('Weather API tidak dapat dihubungi', ['error' => $e->getMessage()]);

            return $this->fail('Layanan cuaca tidak dapat dihubungi. Coba lagi nanti.');
        }

        if ($response->failed()) {
            Log::warning('Weather API mengembalikan error', ['status' => $response->status()]);

            return $this->fail('Layanan cuaca mengembalikan error (' . $response->status() . ').');
        }

        $current = $response->json('current');

        if (! is_array($current) || ! isset($current['temperature_2m'])) {
            return $this->fail('Data cuaca kosong untuk lokasi ini.', 404);
        }

        $data = [
            'temperature'      => (float) $current['temperature_2m'],
            'temperature_unit' => $response->json('current_units.temperature_2m', '°C'),
            'wind_speed'       => (float) ($current['wind_speed_10m'] ?? 0),
            'wind_unit'        => $response->json('current_units.wind_speed_10m', 'km/h'),
            'weather_code'     => (int) ($current['weather_code'] ?? -1),
            'description'      => $this->describe((int) ($current['weather_code'] ?? -1)),
            'time'             => $current['time'] ?? null,
            'cached'           => false,
        ];

        Cache::put($key, $data, now()->addMinutes(15));

        return ['ok' => true, 'data' => $data];
    }

    /** Kode cuaca WMO yang dipakai Open-Meteo. */
    public function describe(int $code): string
    {
        return match (true) {
            $code === 0                   => 'Cerah',
            $code === 1                   => 'Cerah berawan',
            $code === 2                   => 'Berawan sebagian',
            $code === 3                   => 'Mendung',
            in_array($code, [45, 48])     => 'Berkabut',
            in_array($code, [51, 53, 55]) => 'Gerimis',
            in_array($code, [56, 57])     => 'Gerimis beku',
            in_array($code, [61, 63, 65]) => 'Hujan',
            in_array($code, [66, 67])     => 'Hujan beku',
            in_array($code, [71, 73, 75, 77]) => 'Salju',
            in_array($code, [80, 81, 82]) => 'Hujan lebat sesaat',
            in_array($code, [85, 86])     => 'Hujan salju sesaat',
            $code === 95                  => 'Badai petir',
            in_array($code, [96, 99])     => 'Badai petir disertai hujan es',
            default                       => 'Tidak diketahui',
        };
    }
}