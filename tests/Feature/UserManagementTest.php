<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(UserRole $role = UserRole::Sales, bool $active = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_sales_cannot_manage_users(): void
    {
        $sales = $this->user(UserRole::Sales);

        $this->actingAs($sales)
            ->post('/users', [
                'name' => 'Test Sales',
                'email' => 'sales123@trangthietbi.vn',
                'role' => 'sales',
                'password' => 'password123',
            ])
            ->assertForbidden();
    }

    public function test_manager_can_create_sales_user(): void
    {
        $manager = $this->user(UserRole::Manager);

        $response = $this->actingAs($manager)->post('/users', [
            'name' => 'Nguyễn Nhân Viên',
            'email' => 'nhanvien.sales@trangthietbi.vn',
            'phone' => '0912345678',
            'role' => 'sales',
            'password' => 'Password@123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Đã tạo tài khoản nhân viên thành công.');

        $newUser = User::where('email', 'nhanvien.sales@trangthietbi.vn')->first();
        $this->assertNotNull($newUser);
        $this->assertSame('Nguyễn Nhân Viên', $newUser->name);
        $this->assertSame(UserRole::Sales, $newUser->role);
        $this->assertTrue($newUser->is_active);
        $this->assertTrue(Hash::check('Password@123', $newUser->password));
    }

    public function test_manager_cannot_create_admin_user(): void
    {
        $manager = $this->user(UserRole::Manager);

        $response = $this->actingAs($manager)->post('/users', [
            'name' => 'Hack Admin',
            'email' => 'fakeadmin@trangthietbi.vn',
            'role' => 'admin',
            'password' => 'Password@123',
        ]);

        $response->assertSessionHasErrors(['role']);
    }

    public function test_admin_can_create_admin_user(): void
    {
        $admin = $this->user(UserRole::Admin);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Phó Quản Trị',
            'email' => 'co-admin@trangthietbi.vn',
            'role' => 'admin',
            'password' => 'Password@123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'co-admin@trangthietbi.vn',
            'role' => 'admin',
        ]);
    }

    public function test_manager_can_update_user(): void
    {
        $manager = $this->user(UserRole::Manager);
        $target = $this->user(UserRole::Sales);

        $response = $this->actingAs($manager)->put("/users/{$target->id}", [
            'name' => 'Tên Mới Sau Khi Sửa',
            'email' => $target->email,
            'phone' => '0988776655',
            'role' => 'manager',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Đã cập nhật thông tin nhân viên.');

        $target->refresh();
        $this->assertSame('Tên Mới Sau Khi Sửa', $target->name);
        $this->assertSame('0988776655', $target->phone);
        $this->assertSame(UserRole::Manager, $target->role);
    }

    public function test_manager_cannot_update_admin_user(): void
    {
        $manager = $this->user(UserRole::Manager);
        $admin = $this->user(UserRole::Admin);

        $this->actingAs($manager)->put("/users/{$admin->id}", [
            'name' => 'Hacker Name',
            'email' => $admin->email,
            'role' => 'sales',
        ])->assertForbidden();
    }

    public function test_user_cannot_lock_themselves(): void
    {
        $manager = $this->user(UserRole::Manager);

        $response = $this->actingAs($manager)->patch("/users/{$manager->id}/status");

        $response->assertSessionHas('error', 'Không thể khóa tài khoản của chính mình.');
        $this->assertTrue($manager->fresh()->is_active);
    }

    public function test_manager_can_toggle_sales_status(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, true);

        // Khóa
        $response = $this->actingAs($manager)->patch("/users/{$sales->id}/status");
        $response->assertSessionHas('success', 'Đã khóa tài khoản nhân viên.');
        $this->assertFalse($sales->fresh()->is_active);

        // Mở khóa lại
        $response = $this->actingAs($manager)->patch("/users/{$sales->id}/status");
        $response->assertSessionHas('success', 'Đã mở khóa tài khoản nhân viên.');
        $this->assertTrue($sales->fresh()->is_active);
    }

    public function test_manager_can_reset_password(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales);

        $response = $this->actingAs($manager)->post("/users/{$sales->id}/reset-password", [
            'password' => 'NewResetPassword@123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('NewResetPassword@123', $sales->fresh()->password));
    }

    public function test_manager_sees_user_list_in_settings(): void
    {
        $manager = $this->user(UserRole::Manager);
        $this->user(UserRole::Sales);

        $response = $this->actingAs($manager)->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->has('users', 2)
        );
    }
}
