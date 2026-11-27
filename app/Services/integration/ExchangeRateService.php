<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExchangeRateService
{
    use MakesIntegrationRequests;

    /** Kurs 1 satuan mata uang tertentu ke rupiah. */
    public function toIdr(string $currency): array
    {
        $currency = strtoupper($currency);

        if ($currency === 'IDR') {
            return ['ok' => true, 'data' => [
                'currency' => 'IDR', 'rate' => 1.0, 'date' => now()->toDateString(),
                'source'   => 'Tanpa konversi', 'cached' => false,
            ]];
        }

        $key = "fx:{$currency}:IDR";

        if ($cached = Cache::get($key)) {
            return ['ok' => true, 'data' => $cached + ['cached' => true]];
        }

        try {
            $response = $this->http(config('integrations.fx.base_url'))->get('latest', [
                'base'    => $currency,
                'symbols' => 'IDR',
            ]);
        } catch (Throwable $e) {
            Log::warning('FX API tidak dapat dihubungi', ['error' => $e->getMessage()]);

            return $this->fail('Layanan kurs tidak dapat dihubungi. Isi kurs secara manual.');
        }

        if (in_array($response->status(), [404, 422], true)) {
            return $this->fail("Mata uang {$currency} tidak didukung oleh penyedia kurs. Isi kurs secara manual.", 404);
        }

        if ($response->failed()) {
            Log::warning('FX API mengembalikan error', ['status' => $response->status()]);

            return $this->fail('Layanan kurs mengembalikan error (' . $response->status() . ').');
        }

        $rate = $response->json('rates.IDR');

        if (! is_numeric($rate) || (float) $rate <= 0) {
            return $this->fail("Kurs {$currency} ke IDR kosong. Isi kurs secara manual.", 404);
        }

        $data = [
            'currency' => $currency,
            'rate'     => (float) $rate,
            'date'     => $response->json('date'),
            'source'   => 'Frankfurter (kurs referensi ECB)',
            'cached'   => false,
        ];

        Cache::put($key, $data, now()->addHours(6));

        return ['ok' => true, 'data' => $data];
    }
}