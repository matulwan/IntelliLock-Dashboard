<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccessLogController extends Controller
{
    /**
     * Display a listing of recent access logs.
     */
    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->input('per_page', 50));

        $logs = AccessLog::query()
            ->orderByDesc('created_at')
            ->limit($perPage)
            ->get(['id', 'action', 'user', 'key_name', 'device', 'created_at'])
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user,
                    'key_name' => $log->key_name,
                    'device' => $log->device,
                    'created_at' => optional($log->created_at)->toISOString(),
                ];
            });

        return Inertia::render('access-logs', [
            'logs' => $logs,
        ]);
    }
}