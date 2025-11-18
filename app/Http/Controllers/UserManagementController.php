<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LabKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role', 'phone', 'last_login', 'avatar')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'lastLogin' => $user->last_login ? $user->last_login->timezone(config('app.timezone'))->format('M j, Y g:i A') : 'Never',
                    'avatar' => $user->avatar,
                ];
            });

        $roles = [
            ['name' => 'Lecturer', 'count' => User::where('role', 'Lecturer')->count()],
            ['name' => 'Staff', 'count' => User::where('role', 'Staff')->count()],
            ['name' => 'Student', 'count' => User::where('role', 'Student')->count()],
        ];

        return Inertia::render('user-management', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        return \Inertia\Inertia::render('user-management-add');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'matrix_number' => 'nullable|string|max:50',
            'role' => 'required|in:Student,Lecturer,Staff,Key',
            'rfid_uid' => 'nullable|string',
            'fingerprint_id' => 'nullable|integer|unique:users,fingerprint_id',
            'email' => 'nullable|email|unique:users,email',
        ]);

        // If role is "Key", create a LabKey entry instead of a User
        if ($request->role === 'Key') {
            $request->validate([
                'rfid_uid' => 'required|string|unique:lab_keys,key_rfid_uid',
                'name' => 'required|string|max:255', // This becomes key_name
            ]);

            LabKey::create([
                'key_name' => $request->name,
                'key_rfid_uid' => strtoupper($request->rfid_uid),
                'description' => $request->matrix_number ?: ($request->phone ?: 'Lab Key'),
                'status' => 'available',
                'location' => 'key_box',
                'is_active' => true,
            ]);

            return redirect()->route('user-management')->with('success', 'Key registered successfully!');
        }

        // For regular users, validate phone is required and unique constraints
        $request->validate([
            'phone' => 'required|string|max:20',
            'rfid_uid' => 'nullable|string|unique:users,rfid_uid',
        ]);

        // Ensure required auth fields exist even if not supplied in the form
        $email = $request->email;
        if (!$email) {
            // Generate a unique placeholder email using matrix_number or phone/name
            $base = $request->matrix_number ?: ($request->phone ?: Str::slug($request->name));
            $email = strtolower($base).'@intellilock.local';
            
            // Ensure generated email is unique
            $counter = 1;
            $originalEmail = $email;
            while (User::where('email', $email)->exists()) {
                $email = str_replace('@intellilock.local', '_' . $counter . '@intellilock.local', $originalEmail);
                $counter++;
            }
        }

        $password = Str::random(12);

        User::create([
            'name' => $request->name,
            'email' => $email,
            'phone' => $request->phone,
            'matrix_number' => $request->matrix_number,
            'role' => $request->role,
            'rfid_uid' => $request->rfid_uid ?: null,
            'fingerprint_id' => $request->fingerprint_id ?: null,
            'iot_access' => true,
            'password' => Hash::make($password),
        ]);

        return redirect()->route('user-management')->with('success', 'User registered!');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:Lecturer,Staff,Student',
        ]);

        $user->update($request->only(['name', 'email', 'role']));

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->route('user-management')->with('success', 'User updated successfully');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('user-management')->with('success', 'User deleted successfully');
    }
}