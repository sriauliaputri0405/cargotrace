<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait MakesIntegrationRequests
{
    protected function http(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout((int) config('integrations.timeout', 5))
            // Hanya gagal koneksi yang diulang (maksimal 2 kali); respons error HTTP tidak diulang
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    protected function fail(string $message, int $status = 502): array
    {
        return ['ok' => false, 'error' => $message, 'status' => $status];
    }
}