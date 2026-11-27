<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $from = $this->parseDate($request->input('from'));
        $to = $this->parseDate($request->input('to'));

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->input('entity_type')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->input('user_id')))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs'    => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'types'   => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'users'   => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERATOR])->orderBy('name')->get(),
        ]);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}