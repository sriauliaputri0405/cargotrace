<?php

namespace Tests\Feature;

use App\Services\Integrations\ExchangeRateService;
use App\Services\Integrations\GeocodingService;
use App\Services\Integrations\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationServicesTest extends TestCase
{
    public function test_weather_returns_parsed_data(): void
    {
        Http::fake(['*' => Http::response([
            'current' => ['time' => '2026-01-01T12:00', 'temperature_2m' => 28.4, 'wind_speed_10m' => 12.1, 'weather_code' => 3],
            'current_units' => ['temperature_2m' => '°C', 'wind_speed_10m' => 'km/h'],
        ])]);

        $result = app(WeatherService::class)->current(1.0, 104.0);

        $this->assertTrue($result['ok']);
        $this->assertSame(28.4, $result['data']['temperature']);
        $this->assertSame('Mendung', $result['data']['description']);
    }

    public function test_weather_handles_server_error(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $result = app(WeatherService::class)->current(1.0, 104.0);

        $this->assertFalse($result['ok']);
        $this->assertSame(502, $result['status']);
    }

    public function test_weather_handles_empty_data(): void
    {
        Http::fake(['*' => Http::response(['current' => []])]);

        $result = app(WeatherService::class)->current(1.0, 104.0);

        $this->assertFalse($result['ok']);
        $this->assertSame(404, $result['status']);
    }

    public function test_weather_handles_connection_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $result = app(WeatherService::class)->current(1.0, 104.0);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('tidak dapat dihubungi', $result['error']);
    }

    public function test_weather_rejects_invalid_coordinates_without_calling_api(): void
    {
        Http::fake();

        $result = app(WeatherService::class)->current(120.0, 0.0);

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }

    public function test_geocoding_requires_user_agent(): void
    {
        config(['integrations.geocoding.user_agent' => null]);
        Http::fake();

        $result = app(GeocodingService::class)->search('Some Place');

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }

    public function test_geocoding_parses_and_caches_result(): void
    {
        config(['integrations.geocoding.user_agent' => 'Test/1.0 (test@example.test)']);
        Http::fake(['*' => Http::response([[
            'lat' => '1.2644', 'lon' => '103.8222', 'display_name' => 'Some Place',
            'address' => ['country_code' => 'sg', 'city' => 'Some City'],
        ]])]);

        $service = app(GeocodingService::class);
        $first = $service->search('Some Place');
        $second = $service->search('Some Place');

        $this->assertTrue($first['ok']);
        $this->assertSame(1.2644, $first['data']['latitude']);
        $this->assertSame('SG', $first['data']['country']);
        $this->assertTrue($second['data']['cached']);
        Http::assertSentCount(1);
    }

    public function test_geocoding_handles_no_result(): void
    {
        config(['integrations.geocoding.user_agent' => 'Test/1.0 (test@example.test)']);
        Http::fake(['*' => Http::response([])]);

        $result = app(GeocodingService::class)->search('Nowhere Special');

        $this->assertFalse($result['ok']);
        $this->assertSame(404, $result['status']);
    }

    public function test_geocoding_respects_one_request_per_second_limit(): void
    {
        config(['integrations.geocoding.user_agent' => 'Test/1.0 (test@example.test)']);
        Http::fake(['*' => Http::response([['lat' => '1', 'lon' => '2', 'display_name' => 'X']])]);

        $service = app(GeocodingService::class);
        $service->search('First Query');
        $second = $service->search('Second Query');

        $this->assertFalse($second['ok']);
        $this->assertSame(429, $second['status']);
    }

    public function test_exchange_rate_for_idr_needs_no_request(): void
    {
        Http::fake();

        $result = app(ExchangeRateService::class)->toIdr('idr');

        $this->assertTrue($result['ok']);
        $this->assertSame(1.0, $result['data']['rate']);
        Http::assertNothingSent();
    }

    public function test_exchange_rate_is_parsed(): void
    {
        Http::fake(['*' => Http::response(['base' => 'USD', 'date' => '2026-01-02', 'rates' => ['IDR' => 16250.5]])]);

        $result = app(ExchangeRateService::class)->toIdr('USD');

        $this->assertTrue($result['ok']);
        $this->assertSame(16250.5, $result['data']['rate']);
    }

    public function test_exchange_rate_reports_unsupported_currency(): void
    {
        Http::fake(['*' => Http::response(['message' => 'not found'], 404)]);

        $result = app(ExchangeRateService::class)->toIdr('XYZ');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('tidak didukung', $result['error']);
    }
}