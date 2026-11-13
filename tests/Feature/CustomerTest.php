<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_sales_cannot_create_task_reminders_from_customer_notes(): void
    {
        $sale = $this->user(UserRole::Sales);
        $customer = $this->customer($sale);

        $this->actingAs($sale)->post("/customers/{$customer->id}/notes", [
            'create_reminder' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('customer_notes', 0);
        $this->assertDatabaseCount('care_tasks', 0);
    }

    public function test_sales_can_still_add_customer_notes_without_reminders(): void
    {
        $sale = $this->user(UserRole::Sales);
        $customer = $this->customer($sale);

        $this->actingAs($sale)->post("/customers/{$customer->id}/notes", [
            'request_id' => (string) Str::uuid(),
            'body' => 'Đã trao đổi về nhu cầu tư vấn.',
            'create_reminder' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'created_by' => $sale->id,
            'body' => 'Đã trao đổi về nhu cầu tư vấn.',
            'task_id' => null,
        ]);
        $this->assertDatabaseCount('care_tasks', 0);
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
            'name' => 'Công ty TNHH Điện tử ABC Việt Nam',
            'email' => 'CONTACT@EXAMPLE.COM',
            'phone' => '(024) 3333-4444',
            'address' => 'Hà Nội',
            'company_type' => 'domestic',
            'status' => 'active',
            'notes' => 'Khách hàng mới',

            'owner_id' => $other->id,
            'tax_code' => '0101234567',
            'tags' => ['FDI'],
            'lifecycle_stage' => 'loyal',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $customer = Customer::firstOrFail();

        $this->assertSame('KH00001', $customer->code);
        $this->assertSame('contact@example.com', $customer->email);
        $this->assertSame('02433334444', $customer->phone);
        $this->assertSame('dien tu abc', $customer->name_key);
        $this->assertSame('Hà Nội', $customer->address);
        $this->assertSame('Khách hàng mới', $customer->notes);

        $this->assertSame($sale->id, $customer->owner_id);
        $this->assertSame('prospect', $customer->lifecycle_stage->value);

        $this->assertNull($customer->tax_code);
        $this->assertCount(0, $customer->tags);

        $this->assertDatabaseHas('customer_owner_histories', [
            'customer_id' => $customer->id,
            'to_user_id' => $sale->id,
        ]);
    }

    public function test_basic_update_preserves_existing_customer_data(): void
    {
        $sale = $this->user(UserRole::Sales);

        $customer = $this->customer($sale, [
            'tax_code' => '0101234567',
            'lifecycle_stage' => 'contacted',
            'marketing_consent' => true,
            'tags' => ['FDI'],
        ]);

        $originalCode = $customer->code;

        $this->actingAs($sale)
            ->put("/customers/{$customer->id}", [
                'name' => 'Tên khách hàng đã cập nhật',
                'email' => 'updated@example.com',
                'phone' => '0912345678',
                'address' => 'Địa chỉ mới',
                'company_type' => 'domestic',
                'status' => 'active',
                'notes' => 'Mô tả mới',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('Tên khách hàng đã cập nhật', $customer->name);
        $this->assertSame('updated@example.com', $customer->email);
        $this->assertSame('Địa chỉ mới', $customer->address);

        $this->assertSame($originalCode, $customer->code);
        $this->assertSame('0101234567', $customer->tax_code);
        $this->assertSame('contacted', $customer->lifecycle_stage->value);
        $this->assertSame($sale->id, $customer->owner_id);
        $this->assertTrue($customer->marketing_consent);
        $this->assertSame(['FDI'], $customer->tags->pluck('name')->all());
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
        $customer = $this->customer($sale);

        $before = $customer->fresh()->data_completeness;

        $this->actingAs($sale)
            ->post("/customers/{$customer->id}/contacts", [
                'full_name' => 'Lê Hà',
                'buying_role' => 'purchasing',
                'phone' => '0912000001',
                'is_primary' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'full_name' => 'Lê Hà',
        ]);

        // Điểm hồ sơ cơ bản không còn phụ thuộc vào Contact.
        $this->assertSame(
            $before,
            $customer->fresh()->data_completeness,
        );

        $this->actingAs($sale)
            ->put("/customers/{$customer->id}", [
                'name' => 'Tên mới',
                'email' => 'customer@example.com',
                'phone' => '0912345678',
                'address' => 'Hà Nội',
                'company_type' => 'domestic',
                'status' => 'active',
                'notes' => 'Đã cập nhật thông tin liên hệ',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertGreaterThan(
            $before,
            $customer->fresh()->data_completeness,
        );

        $log = AuditLog::query()
            ->where('auditable_type', Customer::class)
            ->where('auditable_id', $customer->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Tên mới', $log->new_values['name']);
        $this->assertSame($sale->id, $log->user_id);
    }
}
