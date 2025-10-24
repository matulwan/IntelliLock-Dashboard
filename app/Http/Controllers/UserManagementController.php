<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role', 'last_login as lastLogin', 'avatar')
            ->orderBy('created_at', 'desc')
            ->get();

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
            'phone' => 'required|string|max:20',
            'matrix_number' => 'nullable|string|max:50',
            'role' => 'required|in:Student,Lecturer',
            'rfid_uid' => 'required|string',
            'biometric_id' => 'required|string',
        ]);

        User::create($request->only([
            'name', 'phone', 'matrix_number', 'role', 'rfid_uid', 'biometric_id'
        ]));

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