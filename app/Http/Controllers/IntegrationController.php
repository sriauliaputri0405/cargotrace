<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\Integrations\ExchangeRateService;
use App\Services\Integrations\GeocodingService;
use App\Services\Integrations\WeatherService;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function geocode(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:150']]);

        return $this->respond($geocoding->search($data['q']));
    }

    public function fxRate(Request $request, ExchangeRateService $fx): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
        ]);

        return $this->respond($fx->toIdr($data['currency']));
    }

    public function weather(Shipment $shipment, MapService $maps, WeatherService $weather): JsonResponse
    {
        Gate::authorize('view', $shipment);

        $position = $maps->latestPosition($shipment);

        if (! $position) {
            return response()->json(['ok' => false, 'message' => 'Belum ada koordinat untuk shipment ini.']);
        }

        $result = $weather->current($position['lat'], $position['lng']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['error']], $result['status']);
        }

        return response()->json(['ok' => true, 'place' => $position['label']] + $result['data']);
    }

    private function respond(array $result): JsonResponse
    {
        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['error']], $result['status']);
        }

        return response()->json(['ok' => true] + $result['data']);
    }
}