<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderByRaw(
            "CASE role WHEN 'admin' THEN 0 WHEN 'analyst' THEN 1 ELSE 2 END"
        )->orderBy('name')->get();

        return view('users.index', [
            'users' => $users,
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()->symbols()],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'theme' => ['nullable', Rule::in(['dark', 'light'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => ($data['email'] ?? null) ?: null,
            'password' => bcrypt($data['password']),
            'role' => $data['role'],
            'theme' => $data['theme'] ?? 'dark',
        ]);

        AuditLog::record('user.created', $user->handle(), ['role' => $user->role]);

        return back()->with('status', "User {$user->name} created.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['boolean'],
            'theme' => ['nullable', Rule::in(['dark', 'light'])],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()->symbols()],
        ]);

        // Prevent an administrator from locking themselves out.
        if ($user->id === auth()->id()) {
            if (! $request->boolean('is_active')) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }
            if ($data['role'] !== 'admin') {
                return back()->with('error', 'You cannot remove your own administrator role.');
            }
        }

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => ($data['email'] ?? null) ?: null,
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
            'theme' => $data['theme'] ?? $user->theme,
        ]);

        if (! empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();

        AuditLog::record('user.updated', $user->handle(), [
            'role' => $user->role,
            'active' => $user->is_active,
            'password_changed' => ! empty($data['password']),
        ]);

        return back()->with('status', "User {$user->name} updated.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin() && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'At least one active administrator must remain.');
        }

        $handle = $user->handle();
        $user->delete();

        AuditLog::record('user.deleted', $handle);

        return back()->with('status', "User {$handle} deleted.");
    }
}
