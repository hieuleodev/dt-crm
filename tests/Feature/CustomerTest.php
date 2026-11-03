<?php

namespace Tests\Feature;

use App\Domain\Customer\Actions\SaveCustomer;
use App\Domain\Customer\Models\Customer;
use App\Domain\Shared\Models\AuditLog;
use App\Domain\Staff\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); 
    }

    private function user(UserRole $role): User
    {
        return User::create(['name' => $role->label().' '.uniqid(), 'email' => uniqid().'@test.vn', 'password' => 'Secret123!', 'role' => $role, 'is_active' => true]);
    }

    private function customer(User $owner, array $data = []): Customer
    {
        return (new SaveCustomer)->handle(['name' => 'Công ty TNHH Thử Nghiệm', 'lifecycle_stage' => 'prospect', 'status' => 'active', 'owner_id' => $owner->id, ...$data], $owner);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_sales_only_sees_and_opens_own_customers(): void
    {
        $sale1 = $this->user(UserRole::Sales);
        $sale2 = $this->user(UserRole::Sales);
        $mine = $this->customer($sale1, ['name' => 'Khách của tôi']);
        $other = $this->customer($sale2, ['name' => 'Khách người khác']);

        $this->actingAs($sale1)->get('/customers')
            ->assertInertia(fn ($page) => $page->component('Customers/Index')->where('customers.total', 1)->where('customers.data.0.id', $mine->id));
        $this->actingAs($sale1)->get("/customers/{$other->id}")->assertForbidden();
        $this->actingAs($sale1)->put("/customers/{$other->id}", ['name' => 'Đổi tên', 'lifecycle_stage' => 'prospect', 'status' => 'active'])->assertForbidden();
        $this->actingAs($sale1)->delete("/customers/{$mine->id}")->assertForbidden(); // nhân viên không được xóa
    }

    public function test_manager_sees_everything(): void
    {
        $manager = $this->user(UserRole::Manager);
        $this->customer($this->user(UserRole::Sales));
        $this->customer($this->user(UserRole::Sales), ['name' => 'Khác']);

        $this->actingAs($manager)->get('/customers')->assertInertia(fn ($page) => $page->where('customers.total', 2));
    }

    public function test_create_normalizes_data_and_generates_code(): void
    {
        $sale = $this->user(UserRole::Sales);
        $other = $this->user(UserRole::Sales);

        $this->actingAs($sale)->post('/customers', [
            'name' => 'Công ty TNHH Điện tử ABC Việt Nam', 'tax_code' => '0101 234 567', 'phone' => '(024) 3333-4444',
            'lifecycle_stage' => 'prospect', 'status' => 'active', 'owner_id' => $other->id, 'tags' => ['FDI', 'FDI'],
        ])->assertRedirect();

        $c = Customer::firstOrFail();
        $this->assertSame('KH00001', $c->code);
        $this->assertSame('0101234567', $c->tax_code);
        $this->assertSame('02433334444', $c->phone);
        $this->assertSame('dien tu abc', $c->name_key);
        $this->assertSame($sale->id, $c->owner_id, 'Nhân viên tạo khách thì khách thuộc về chính mình');
        $this->assertCount(1, $c->tags);
        $this->assertDatabaseHas('customer_owner_histories', ['customer_id' => $c->id, 'to_user_id' => $sale->id]);
    }

    public function test_duplicate_tax_code_is_rejected_in_vietnamese(): void
    {
        $sale = $this->user(UserRole::Sales);
        $this->customer($sale, ['tax_code' => '0101234567']);

        $this->actingAs($sale)->post('/customers', ['name' => 'Trùng', 'tax_code' => '0101234567', 'lifecycle_stage' => 'prospect', 'status' => 'active'])
            ->assertSessionHasErrors(['tax_code' => 'Mã số thuế đã có trong hệ thống – có thể khách đã được tạo, hãy tìm trước khi thêm mới.']);
        $this->actingAs($sale)->post('/customers', ['name' => 'Sai', 'tax_code' => '12345', 'lifecycle_stage' => 'prospect', 'status' => 'active'])
            ->assertSessionHasErrors('tax_code');
    }

    public function test_duplicate_check_finds_similar_names_and_phones(): void
    {
        $sale = $this->user(UserRole::Sales);
        $c = $this->customer($sale, ['name' => 'Công ty TNHH Điện tử ABC Việt Nam']);
        $c->contacts()->create(['full_name' => 'Hà', 'buying_role' => 'purchasing', 'phone' => '0912.345.678']);

        $this->actingAs($sale)->getJson('/customers/check-duplicates?name=Cty+Điện+Tử+ABC')
            ->assertJsonPath('matches.0.reasons.0', 'Tên gần giống');
        $this->actingAs($sale)->getJson('/customers/check-duplicates?phone=0912345678')
            ->assertJsonPath('matches.0.id', $c->id);
    }

    public function test_reassign_is_manager_only_and_keeps_history(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sale1 = $this->user(UserRole::Sales);
        $sale2 = $this->user(UserRole::Sales);
        $c = $this->customer($sale1);

        $this->actingAs($sale1)->post("/customers/{$c->id}/reassign", ['owner_id' => $sale2->id])->assertForbidden();
        $this->actingAs($manager)->post("/customers/{$c->id}/reassign", ['owner_id' => $sale2->id, 'reason' => 'Nghỉ việc'])->assertRedirect();

        $this->assertSame($sale2->id, $c->fresh()->owner_id);
        $this->assertDatabaseHas('customer_owner_histories', ['customer_id' => $c->id, 'from_user_id' => $sale1->id, 'to_user_id' => $sale2->id, 'reason' => 'Nghỉ việc']);
    }

    public function test_changes_are_audited_and_completeness_updates(): void
    {
        $sale = $this->user(UserRole::Sales);
        $c = $this->customer($sale);
        $before = $c->fresh()->data_completeness;

        $this->actingAs($sale)->post("/customers/{$c->id}/contacts", ['full_name' => 'Lê Hà', 'buying_role' => 'purchasing', 'phone' => '0912000001', 'is_primary' => true])->assertRedirect();
        $this->actingAs($sale)->put("/customers/{$c->id}", ['name' => 'Tên mới', 'lifecycle_stage' => 'contacted', 'status' => 'active'])->assertRedirect();

        $this->assertGreaterThan($before, $c->fresh()->data_completeness);
        $log = AuditLog::where('auditable_type', Customer::class)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('Tên mới', $log->new_values['name']);
        $this->assertSame($sale->id, $log->user_id);
    }
}