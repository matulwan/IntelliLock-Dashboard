<?php

namespace App\Http\Controllers;

use App\Models\AuthorizedUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class AuthorizedUserController extends Controller
{
    public function index()
    {
        $users = AuthorizedUser::with('creator')
                              ->orderBy('name')
                              ->get();

        $stats = [
            'total_users' => $users->count(),
            'active_users' => $users->where('is_active', true)->count(),
            'inactive_users' => $users->where('is_active', false)->count(),
            'rfid_users' => $users->whereNotNull('rfid_uid')->count(),
            'fingerprint_users' => $users->whereNotNull('fingerprint_id')->count(),
        ];

        return Inertia::render('authorized-users', [
            'users' => $users,
            'stats' => $stats
        ]);
    }

    public function create()
    {
        return Inertia::render('authorized-users/create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'rfid_uid' => 'nullable|string|unique:authorized_users,rfid_uid',
            'fingerprint_id' => 'nullable|integer|unique:authorized_users,fingerprint_id',
            'role' => 'required|in:admin,user,guest',
            'notes' => 'nullable|string|max:1000'
        ]);

        AuthorizedUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'rfid_uid' => $request->rfid_uid ? strtoupper($request->rfid_uid) : null,
            'fingerprint_id' => $request->fingerprint_id,
            'role' => $request->role,
            'is_active' => true,
            'created_by' => Auth::id(),
            'notes' => $request->notes
        ]);

        return redirect()->route('authorized-users.index')
                        ->with('success', 'Authorized user created successfully.');
    }

    public function show(AuthorizedUser $authorizedUser)
    {
        $authorizedUser->load('creator', 'accessLogs');
        
        return Inertia::render('authorized-users/show', [
            'user' => $authorizedUser
        ]);
    }

    public function edit(AuthorizedUser $authorizedUser)
    {
        return Inertia::render('authorized-users/edit', [
            'user' => $authorizedUser
        ]);
    }

    public function update(Request $request, AuthorizedUser $authorizedUser)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'rfid_uid' => 'nullable|string|unique:authorized_users,rfid_uid,' . $authorizedUser->id,
            'fingerprint_id' => 'nullable|integer|unique:authorized_users,fingerprint_id,' . $authorizedUser->id,
            'role' => 'required|in:admin,user,guest',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000'
        ]);

        $authorizedUser->update([
            'name' => $request->name,
            'email' => $request->email,
            'rfid_uid' => $request->rfid_uid ? strtoupper($request->rfid_uid) : null,
            'fingerprint_id' => $request->fingerprint_id,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active'),
            'notes' => $request->notes
        ]);

        return redirect()->route('authorized-users.index')
                        ->with('success', 'Authorized user updated successfully.');
    }

    public function destroy(AuthorizedUser $authorizedUser)
    {
        $authorizedUser->delete();

        return redirect()->route('authorized-users.index')
                        ->with('success', 'Authorized user deleted successfully.');
    }

    public function toggleStatus(AuthorizedUser $authorizedUser)
    {
        $authorizedUser->update([
            'is_active' => !$authorizedUser->is_active
        ]);

        $status = $authorizedUser->is_active ? 'activated' : 'deactivated';
        
        return response()->json([
            'status' => 'success',
            'message' => "User {$status} successfully.",
            'is_active' => $authorizedUser->is_active
        ]);
    }
}
