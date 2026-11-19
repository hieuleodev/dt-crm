<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_and_manager_can_manage_accounts_but_sales_cannot(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->isManager());

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data')
                ->missing('workPermissions'));

        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
        $this->actingAs($manager)->get(route('users.index'))->assertOk();

        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $this->actingAs($sales)->get(route('users.index'))->assertForbidden();
    }

    public function test_manager_can_create_account_and_audit_entry_without_per_user_permissions(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);

        $this->actingAs($manager)
            ->post(route('users.store'), [
                'name' => 'Nguyễn Văn B',
                'email' => 'van-b@example.test',
                'phone' => '0901234567',
                'role' => UserRole::Sales->value,
                'is_active' => true,
                'password' => 'StrongPassword123',
                'password_confirmation' => 'StrongPassword123',
            ])
            ->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'van-b@example.test')->sole();
        $this->assertFalse(Schema::hasColumn('users', 'work_permissions'));
        $this->assertTrue(Hash::check('StrongPassword123', $created->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'auditable_type' => User::class,
            'auditable_id' => $created->id,
            'event' => 'created',
        ]);
    }

    public function test_status_change_requires_a_reason_and_cannot_lock_the_current_account(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $staff = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $payload = [
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone ?? '',
            'role' => UserRole::Sales->value,
            'is_active' => false,
            'password' => '',
            'password_confirmation' => '',
        ];

        $this->actingAs($manager)
            ->put(route('users.update', $staff), $payload)
            ->assertSessionHasErrors('status_reason');
        $this->assertTrue($staff->fresh()->is_active);

        $this->put(route('users.update', $staff), [
            ...$payload,
            'status_reason' => 'Tạm ngừng công tác',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($staff->fresh()->is_active);

        $this->put(route('users.update', $manager), [
            ...$payload,
            'email' => $manager->email,
            'status_reason' => 'Không được phép tự khóa',
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($manager->fresh()->is_active);
    }

    public function test_account_email_is_unique_without_regard_to_case_on_create_and_update(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
        $otherStaff = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $payload = [
            'name' => 'Nhân viên thử',
            'email' => '  Sales.A@Example.test  ',
            'role' => UserRole::Sales->value,
            'is_active' => true,
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
        ];

        $this->actingAs($manager)->post(route('users.store'), $payload)->assertSessionHasNoErrors();
        $created = User::query()->where('email', 'sales.a@example.test')->sole();

        $this->post(route('users.store'), [...$payload, 'email' => 'SALES.A@EXAMPLE.TEST'])
            ->assertSessionHasErrors(['email' => 'Email đăng nhập đã được sử dụng.']);
        $this->put(route('users.update', $otherStaff), $payload)->assertSessionHasErrors('email');
        $this->put(route('users.update', $created), [...$payload, 'name' => 'Đã sửa tên'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'name' => 'Đã sửa tên',
            'email' => 'sales.a@example.test',
        ]);
    }

    public function test_user_can_log_in_with_a_different_email_case(): void
    {
        $staff = User::factory()->create([
            'email' => 'Sales.A@Example.test',
            'password' => 'StrongPassword123',
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);

        $this->post('/login', ['email' => ' SALES.A@EXAMPLE.TEST ', 'password' => 'StrongPassword123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertSame('sales.a@example.test', $staff->fresh()->email);
    }

    public function test_sqlite_database_rejects_case_only_duplicates_outside_eloquent(): void
    {
        User::factory()->create(['email' => 'sales.a@example.test']);
        $otherStaff = User::factory()->create(['email' => 'sales.b@example.test']);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('users')->where('id', $otherStaff->id)->update(['email' => 'SALES.A@EXAMPLE.TEST']);
    }

    public function test_email_migration_normalizes_existing_login_addresses(): void
    {
        $staff = User::factory()->create(['email' => 'sales.a@example.test']);
        DB::table('users')->where('id', $staff->id)->update(['email' => ' Sales.A@Example.test ']);

        $migration = require database_path('migrations/2026_11_19_055441_normalize_login_emails_in_users_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('sales.a@example.test', $staff->fresh()->email);
    }

    public function test_module_access_is_not_configured_per_account_and_management_remains_role_restricted(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        $this->actingAs($staff)->get('/leads')->assertOk();
        $this->get('/opportunities')->assertOk();
        $this->get('/tasks')->assertOk();
        $this->get('/calendar')->assertOk();
        $this->get('/users')->assertForbidden();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
        $this->actingAs($admin)->get('/leads')->assertOk();
        $this->get('/opportunities')->assertOk();
    }
}
