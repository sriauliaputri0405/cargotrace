<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class GeocodingService
{
    use MakesIntegrationRequests;

    public function search(string $query): array
    {
        $query = trim((string) preg_replace('/\s+/', ' ', $query));

        if (mb_strlen($query) < 3) {
            return $this->fail('Kata kunci minimal 3 karakter.', 422);
        }

        $agent = config('integrations.geocoding.user_agent');

        if (! $agent) {
            return $this->fail('GEOCODING_USER_AGENT belum diatur di .env.', 503);
        }

        // Kebijakan Nominatim: hasil harus di-cache
        $key = 'geocode:' . sha1(mb_strtolower($query));

        if ($cached = Cache::get($key)) {
            return ['ok' => true, 'data' => $cached + ['cached' => true]];
        }

        // Kebijakan Nominatim: maksimal 1 request per detik untuk seluruh aplikasi
        $result = RateLimiter::attempt('nominatim', 1, fn () => $this->call($query, $agent), 1);

        if ($result === false) {
            return $this->fail('Layanan pencarian sedang sibuk. Coba lagi sebentar lagi.', 429);
        }

        if ($result['ok']) {
            Cache::put($key, $result['data'], now()->addDays(30));
        }

        return $result;
    }

    private function call(string $query, string $agent): array
    {
        try {
            $response = $this->http(config('integrations.geocoding.base_url'))
                ->withUserAgent($agent)
                ->get('search', [
                    'q'              => $query,
                    'format'         => 'jsonv2',
                    'limit'          => 1,
                    'addressdetails' => 1,
                ]);
        } catch (Throwable $e) {
            Log::warning('Geocoding API tidak dapat dihubungi', ['error' => $e->getMessage()]);

            return $this->fail('Layanan pencarian lokasi tidak dapat dihubungi. Isi koordinat manual.');
        }

        if ($response->failed()) {
            Log::warning('Geocoding API mengembalikan error', ['status' => $response->status()]);

            return $this->fail('Layanan pencarian lokasi mengembalikan error (' . $response->status() . ').');
        }

        $first = $response->json('0');

        if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
            return $this->fail('Lokasi tidak ditemukan. Coba kata kunci lain atau isi koordinat manual.', 404);
        }

        $address = $first['address'] ?? [];

        return ['ok' => true, 'data' => [
            'name'      => $first['display_name'] ?? $query,
            'latitude'  => round((float) $first['lat'], 7),
            'longitude' => round((float) $first['lon'], 7),
            'country'   => isset($address['country_code']) ? strtoupper($address['country_code']) : null,
            'city'      => $address['city'] ?? $address['town'] ?? $address['village']
                            ?? $address['municipality'] ?? $address['county'] ?? null,
            'cached'    => false,
        ]];
    }
}