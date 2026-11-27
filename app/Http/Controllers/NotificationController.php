<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Hanya notifikasi milik pengguna yang sedang login
        $notifications = $request->user()->notifications()->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $shipmentId = $notification->data['shipment_id'] ?? null;

        // Alamat tujuan dibangun di server dari id shipment, bukan diambil dari data tersimpan
        if ($shipmentId && Shipment::whereKey($shipmentId)->exists()) {
            return redirect()->route('shipments.show', $shipmentId);
        }

        return redirect()->route('notifications.index');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}