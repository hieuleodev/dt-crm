<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingTest extends TestCase
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
            'password' => Hash::make('old-password'),
        ]);
    }

    public function test_guests_cannot_access_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_inactive_users_cannot_access_settings(): void
    {
        $user = $this->user(UserRole::Sales, false);

        $this->actingAs($user)->get('/settings')->assertForbidden();
    }

    public function test_authenticated_users_can_view_settings(): void
    {
        $user = $this->user(UserRole::Sales);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
    }

    public function test_user_can_update_profile(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->put('/settings/profile', [
            'name' => 'Nguyễn Văn Test',
            'phone' => '0912345678',
            'email' => 'test_updated@trangthietbi.vn',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Đã cập nhật thông tin cá nhân.');

        $user->refresh();
        $this->assertSame('Nguyễn Văn Test', $user->name);
        $this->assertSame('0912345678', $user->phone);
        $this->assertSame('test_updated@trangthietbi.vn', $user->email);
    }

    public function test_user_cannot_update_profile_with_duplicate_email(): void
    {
        $this->user(UserRole::Sales, true);
        $otherUser = User::factory()->create([
            'email' => 'taken@trangthietbi.vn',
            'role' => UserRole::Sales,
        ]);

        $currentUser = $this->user();

        $response = $this->actingAs($currentUser)->put('/settings/profile', [
            'name' => 'Tên Mới',
            'phone' => '0912345678',
            'email' => 'taken@trangthietbi.vn',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'old-password',
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Đã đổi mật khẩu thành công.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-password-123', $user->password));
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'incorrect-old-password',
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
    }

    public function test_user_cannot_change_password_when_confirmation_does_not_match(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'old-password',
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'mismatched-password-456',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_manager_sees_master_data_in_settings(): void
    {
        $manager = $this->user(UserRole::Manager);

        $response = $this->actingAs($manager)->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->has('masterData')
            ->has('masterData.leadStages')
            ->has('masterData.opportunityStages')
            ->has('masterData.lostReasons')
            ->has('masterData.customerTiers')
            ->has('masterData.lifecycleStages')
            ->has('masterData.productCategories', 18)
            ->has('masterData.industries')
            ->has('masterData.sourceChannels')
        );
    }
}
