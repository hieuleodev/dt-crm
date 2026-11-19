<?php

namespace App\Http\Controllers;

use App\Enums\Staff\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        abort_unless($currentUser && $currentUser->is_active && $currentUser->isManager(), 403);

        $allowedRoles = $currentUser->role === UserRole::Admin
            ? array_column(UserRole::cases(), 'value')
            : [UserRole::Sales->value, UserRole::Manager->value];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => ['required', 'string', 'min:8', Password::defaults()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã tạo tài khoản nhân viên thành công.');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        abort_unless($currentUser && $currentUser->is_active && $currentUser->isManager(), 403);

        // Quản lý không được sửa tài khoản Quản trị viên
        if ($currentUser->role !== UserRole::Admin && $user->role === UserRole::Admin) {
            abort(403, 'Bạn không có quyền sửa tài khoản Quản trị viên.');
        }

        $allowedRoles = $currentUser->role === UserRole::Admin
            ? array_column(UserRole::cases(), 'value')
            : [UserRole::Sales->value, UserRole::Manager->value];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in($allowedRoles)],
        ]);

        $user->update($validated);

        return back()->with('success', 'Đã cập nhật thông tin nhân viên.');
    }

    /**
     * Toggle active/inactive status of the user.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        abort_unless($currentUser && $currentUser->is_active && $currentUser->isManager(), 403);

        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Không thể khóa tài khoản của chính mình.');
        }

        if ($currentUser->role !== UserRole::Admin && $user->role === UserRole::Admin) {
            abort(403, 'Bạn không có quyền khóa tài khoản Quản trị viên.');
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        $message = $newStatus ? 'Đã mở khóa tài khoản nhân viên.' : 'Đã khóa tài khoản nhân viên.';

        return back()->with('success', $message);
    }

    /**
     * Reset password for the specified user.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        abort_unless($currentUser && $currentUser->is_active && $currentUser->isManager(), 403);

        if ($currentUser->role !== UserRole::Admin && $user->role === UserRole::Admin) {
            abort(403, 'Bạn không có quyền đặt lại mật khẩu cho Quản trị viên.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Đã đặt lại mật khẩu mới cho tài khoản {$user->name}.");
    }
}
