<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Location;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::orderBy('country')->orderBy('city')->paginate(10);

        return view('locations.index', compact('locations'));
    }

    public function create()
    {
        return view('locations.create');
    }

    public function store(LocationRequest $request)
    {
        Location::create($request->validated());

        return redirect()->route('locations.index')->with('status', 'Lokasi berhasil ditambahkan.');
    }

    public function edit(Location $location)
    {
        return view('locations.edit', compact('location'));
    }

    public function update(LocationRequest $request, Location $location)
    {
        $location->update($request->validated());

        return redirect()->route('locations.index')->with('status', 'Lokasi berhasil diperbarui.');
    }

    public function destroy(Location $location)
    {
        // Lokasi yang dipakai sebagai asal ATAU tujuan shipment tidak boleh dihapus
        if ($location->originShipments()->exists() || $location->destinationShipments()->exists()) {
            return back()->withErrors(['location' => 'Lokasi masih dipakai oleh shipment, tidak bisa dihapus.']);
        }

        $location->delete();

        return redirect()->route('locations.index')->with('status', 'Lokasi berhasil dihapus.');
    }
}