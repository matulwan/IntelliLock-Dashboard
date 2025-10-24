<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AccessLogController extends Controller
{
    public function index()
    {
        // Get raw access logs for statistics calculation
        $rawAccessLogs = AccessLog::orderBy('timestamp', 'desc')->get();
        
        // Calculate statistics from raw data
        $totalLogs = $rawAccessLogs->count();
        $successfulAccess = $rawAccessLogs->where('status', 'success')->count();
        $failedAccess = $rawAccessLogs->where('status', 'denied')->count();
        $successRate = $totalLogs > 0 ? round(($successfulAccess / $totalLogs) * 100, 1) : 0;
        
        // Calculate today's logs from raw data
        $todayLogs = $rawAccessLogs->filter(function ($log) {
            return $log->timestamp->isToday();
        })->count();

        // Format access logs for frontend
        $accessLogs = AccessLog::with('labKey')
                               ->orderBy('timestamp', 'desc')
                               ->get()
                               ->map(function ($log) {
                                   return [
                                       'id' => $log->id,
                                       'user' => $log->user,
                                       'type' => $log->type,
                                       'timestamp' => $log->timestamp->toISOString(),
                                       'status' => $log->status,
                                       'role' => $log->role,
                                       'device' => $log->device,
                                       'key_name' => $log->key_name ?? ($log->labKey ? $log->labKey->key_name : null),
                                       'accessed_item' => $log->key_name ?? $log->device ?? 'Key Box Access',
                                       'formatted_time' => $log->timestamp->format('M j, Y H:i'),
                                       'time_ago' => $log->timestamp->diffForHumans(),
                                   ];
                               });

        $stats = [
            'totalLogs' => $totalLogs,
            'successfulAccess' => $successfulAccess,
            'failedAccess' => $failedAccess,
            'successRate' => $successRate,
            'todayLogs' => $todayLogs,
        ];

        return Inertia::render('access-logs', [
            'accessLogs' => $accessLogs,
            'stats' => $stats,
        ]);
    }
}
