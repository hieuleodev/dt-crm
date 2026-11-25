<?php

namespace App\Http\Controllers;

use App\Enums\Staff\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cho phép Admin và Manager đang hoạt động xem, tạo và cập nhật tài khoản nhân viên.
 */
class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor && $actor->is_active && $actor->isManager(), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $search = trim($filters['q'] ?? '');

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%");
            }))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(
                ($filters['status'] ?? null) !== null,
                fn ($query) => $query->where('is_active', $filters['status'] === 'active'),
            )
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => (object) [
                'q' => $search,
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->all(),
        ]);
    }

    /**
     * Tạo tài khoản, dùng cast của User để băm mật khẩu và ghi nhật ký thao tác.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->is_active && $actor->isManager(), 403);

        $data = $this->validatedData($request, true);
        $user = new User($data);
        $this->saveUser($user);

        $this->writeAudit($user, 'created', [], $this->auditValues($user));

        return back()->with('success', 'Đã tạo tài khoản nhân viên thành công.');
    }

    public function update(Request $request, User $managedUser): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->is_active && $actor->isManager(), 403);

        if ($actor->role !== UserRole::Admin && $managedUser->role === UserRole::Admin) {
            abort(403, 'Bạn không có quyền sửa tài khoản Quản trị viên.');
        }

        $data = $this->validatedData($request, false, $managedUser);
        $statusChanged = $managedUser->is_active !== $data['is_active'];
        // Khi khóa hoặc mở khóa tài khoản, lý do thay đổi được lưu trong nhật ký thao tác.
        if ($statusChanged && blank($data['status_reason'] ?? null)) {
            return back()->withErrors([
                'status_reason' => 'Vui lòng nhập lý do thay đổi trạng thái tài khoản.',
            ]);
        }

        // Không để quản lý tự khóa hoặc tự đổi vai trò, tránh mất quyền quản trị ngoài ý muốn.
        if ($actor->is($managedUser)) {
            if (! $data['is_active']) {
                return back()->withErrors([
                    'is_active' => 'Bạn không thể tự khóa tài khoản đang đăng nhập.',
                ]);
            }

            if ($managedUser->role->value !== $data['role']) {
                return back()->withErrors([
                    'role' => 'Bạn không thể tự thay đổi vai trò tài khoản đang đăng nhập.',
                ]);
            }
        }

        $oldValues = $this->auditValues($managedUser);
        $roleChanged = $managedUser->role->value !== $data['role'];
        $password = $data['password'] ?? null;
        $statusReason = $data['status_reason'] ?? null;
        unset($data['password'], $data['status_reason']);
        if ($password !== null && $password !== '') {
            $data['password'] = $password;
        }

        $this->saveUser($managedUser->fill($data));
        $newValues = $this->auditValues($managedUser);
        if ($statusReason !== null) {
            $newValues['status_reason'] = trim($statusReason);
        }
        $this->writeAudit($managedUser, 'updated', $oldValues, $newValues);

        // Thu hồi các phiên đăng nhập lưu trong DB để quyền mới có hiệu lực khi đăng nhập lại.
        if (($statusChanged || $roleChanged) && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $managedUser->id)
                ->delete();
        }

        return back()->with('success', 'Đã cập nhật thông tin nhân viên.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, bool $creating, ?User $managedUser = null): array
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => User::normalizeLoginEmail($email)]);
        }

        if (! $request->has('is_active')) {
            $request->merge(['is_active' => $managedUser ? $managedUser->is_active : true]);
        }

        $actor = $request->user();
        $allowedRoles = $actor?->role === UserRole::Admin
            ? array_column(UserRole::cases(), 'value')
            : [UserRole::Sales->value, UserRole::Manager->value];

        $needsConfirmation = $request->has('password_confirmation');
        $passwordRules = $creating
            ? array_values(array_filter(['required', 'string', 'min:8', $needsConfirmation ? 'confirmed' : null]))
            : array_values(array_filter(['nullable', 'string', 'min:8', $needsConfirmation ? 'confirmed' : null]));

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'max:255', 'email', Rule::unique('users', 'email')->ignore($request->route('managedUser'))],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in($allowedRoles)],
            'is_active' => ['required', 'boolean'],
            'password' => $passwordRules,
            'password_confirmation' => [$creating && $needsConfirmation ? 'required' : 'nullable', 'string'],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'email.unique' => 'Email đăng nhập đã được sử dụng.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);
    }

    private function saveUser(User $user): void
    {
        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            // Nếu hai yêu cầu cùng vượt qua validation, chỉ mục vẫn chặn và form nhận lỗi đúng trường.
            throw ValidationException::withMessages(['email' => 'Email đăng nhập đã được sử dụng.']);
        }
    }

    /**
     * Lấy các trường hồ sơ, vai trò và trạng thái để so sánh trước và sau khi cập nhật.
     *
     * @return array<string, mixed>
     */
    private function auditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function writeAudit(User $user, string $event, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
