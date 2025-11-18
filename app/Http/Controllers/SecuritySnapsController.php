<?php

namespace App\Http\Controllers;

use App\Models\EventPhoto;
use App\Models\IoTDevice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SecuritySnapsController extends Controller
{
    public function index(Request $request)
    {
        $device = $request->query('device', 'lab_key_box');
        $filter = $request->query('filter', 'all'); // all, today, recent
        $search = $request->query('search', '');

        // Get device status
        $deviceInfo = IoTDevice::where('terminal_name', $device)->first();

        // Build query
        $query = EventPhoto::query()
            ->with(['accessLog', 'keyTransaction'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($filter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($filter === 'recent') {
            $query->where('created_at', '>=', now()->subDays(7));
        }

        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                  ->orWhere('device', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('accessLog', function($q) use ($search) {
                      $q->where('user', 'like', "%{$search}%");
                  })
                  ->orWhereHas('keyTransaction', function($q) use ($search) {
                      $q->where('user_name', 'like', "%{$search}%");
                  });
            });
        }

        // Get photos
        $photos = $query->get()->map(function ($photo) {
            // Generate proper URL - photo_path is stored as 'photos/filename.jpg'
            // We need to create the full URL accessible via web
            $photoPath = $photo->photo_path;
            
            // If path doesn't start with 'storage/', prepend it
            if (!str_starts_with($photoPath, 'storage/')) {
                $photoUrl = asset('storage/' . ltrim($photoPath, '/'));
            } else {
                $photoUrl = asset($photoPath);
            }
            
            return [
                'id' => $photo->id,
                'url' => $photoUrl,
                'time' => $photo->created_at->format('Y-m-d H:i'),
                'time_ago' => $photo->created_at->diffForHumans(),
                'event_type' => $photo->event_type,
                'device' => $photo->device,
                'notes' => $photo->notes,
                'user' => $photo->accessLog?->user ?? $photo->keyTransaction?->user_name ?? 'Unknown',
            ];
        });

        // Get statistics
        $totalSnaps = EventPhoto::count();
        $todaySnaps = EventPhoto::whereDate('created_at', today())->count();
        $lastSnap = EventPhoto::latest('created_at')->first();

        return Inertia::render('security-snaps', [
            'photos' => $photos,
            'stats' => [
                'total' => $totalSnaps,
                'today' => $todaySnaps,
                'last_snap_time' => $lastSnap ? $lastSnap->created_at->format('Y-m-d H:i') : null,
                'last_snap_ago' => $lastSnap ? $lastSnap->created_at->diffForHumans() : null,
            ],
            'device' => [
                'status' => $deviceInfo->status ?? 'offline',
                'last_seen' => $deviceInfo->last_seen ? $deviceInfo->last_seen->diffForHumans() : null,
            ],
            'filters' => [
                'device' => $device,
                'filter' => $filter,
                'search' => $search,
            ],
        ]);
    }
}

