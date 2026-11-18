<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarrierRequest;
use App\Models\Carrier;

class CarrierController extends Controller
{
    public function index()
    {
        $carriers = Carrier::withCount('shipments')->orderBy('name')->paginate(10);

        return view('carriers.index', compact('carriers'));
    }

    public function create()
    {
        return view('carriers.create');
    }

    public function store(CarrierRequest $request)
    {
        Carrier::create($request->validated());

        return redirect()->route('carriers.index')->with('status', 'Carrier berhasil ditambahkan.');
    }

    public function edit(Carrier $carrier)
    {
        return view('carriers.edit', compact('carrier'));
    }

    public function update(CarrierRequest $request, Carrier $carrier)
    {
        $carrier->update($request->validated());

        return redirect()->route('carriers.index')->with('status', 'Carrier berhasil diperbarui.');
    }

    public function destroy(Carrier $carrier)
    {
        // Carrier yang sudah dipakai shipment tidak boleh dihapus (menjaga integritas data)
        if ($carrier->shipments()->exists()) {
            return back()->withErrors(['carrier' => 'Carrier masih dipakai oleh shipment, tidak bisa dihapus.']);
        }

        $carrier->delete();

        return redirect()->route('carriers.index')->with('status', 'Carrier berhasil dihapus.');
    }
}