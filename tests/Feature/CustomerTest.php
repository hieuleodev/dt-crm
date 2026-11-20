<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Care\SaveTask;
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

    public function test_customer_purchase_tags_follow_successful_opportunities_without_counting_tasks_or_repeated_updates(): void
    {
        $sale = $this->user(UserRole::Sales);
        $customer = $this->customer($sale, ['founded_date' => '2015-06-12']);
        $first = new Lead(['owner_id' => $sale->id, 'customer_id' => $customer->id, 'title' => 'Đơn đầu tiên', 'contact_name' => 'An', 'stage' => 'proposal']);
        $first->kind = 'opportunity';
        $first->save();
        $second = $first->replicate();
        $second->title = 'Đơn thứ hai';
        $second->save();
        $lead = $first->replicate();
        $lead->kind = 'lead';
        $lead->stage = 'won';
        $lead->save();

        $this->actingAs($sale)->get('/customers')->assertOk()->assertInertia(fn ($page) => $page
            ->where('customers.data.0.founded_date', '2015-06-12')
            ->where('customers.data.0.successful_orders_count', 0)
            ->where('customers.data.0.purchase_classification.label', 'Chưa phát sinh đơn'));

        $this->patch(route('opportunities.stage', $first), ['stage' => 'won'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('customers.show', $customer))->assertOk()->assertInertia(fn ($page) => $page
            ->where('customer.founded_date', '2015-06-12')
            ->where('customer.successful_orders_count', 1)
            ->where('customer.purchase_classification.label', 'Khách hàng tiềm năng'));

        $this->patch(route('opportunities.stage', $first), ['stage' => 'won'])->assertRedirect();
        $this->patch(route('opportunities.stage', $second), ['stage' => 'won'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/customers')->assertOk()->assertInertia(fn ($page) => $page
            ->where('customers.data.0.successful_orders_count', 2)
            ->where('customers.data.0.purchase_classification.label', 'Khách hàng cũ'));

        $this->patch(route('opportunities.stage', $second), ['stage' => 'proposal'])->assertRedirect();
        $this->get(route('customers.show', $customer))->assertOk()->assertInertia(fn ($page) => $page
            ->where('customer.successful_orders_count', 1)
            ->where('customer.purchase_classification.value', 'potential'));
    }

    public function test_customer_transaction_history_includes_closed_direct_and_opportunity_tasks_only_with_existing_visibility(): void
    {
        $sale = $this->user(UserRole::Sales);
        $other = $this->user(UserRole::Sales);
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($sale);
        $opportunity = new Lead(['owner_id' => $sale->id, 'customer_id' => $customer->id, 'title' => 'Đơn găng tay', 'contact_name' => 'An', 'stage' => 'won']);
        $opportunity->kind = 'opportunity';
        $opportunity->save();
        $base = ['title' => 'Gọi xác nhận', 'type' => 'call', 'priority' => 'normal', 'created_by' => $manager->id, 'assignee_id' => $sale->id, 'due_at' => now(), 'status' => 'completed', 'completed_at' => now(), 'result' => 'Đã xác nhận'];
        $direct = Task::create([...$base, 'customer_id' => $customer->id]);
        $linked = Task::create([...$base, 'lead_id' => $opportunity->id, 'status' => 'cancelled', 'completed_at' => null, 'result' => 'Khách hẹn lại']);
        Task::create([...$base, 'customer_id' => $customer->id, 'status' => 'pending', 'completed_at' => null]);
        Task::create($base);
        Task::create([...$base, 'customer_id' => $customer->id, 'assignee_id' => $other->id]);

        $this->actingAs($sale)->get(route('customers.show', $customer))->assertOk()->assertInertia(fn ($page) => $page
            ->where('taskHistory.total', 2)
            ->where('taskHistory.data.0.id', $linked->id)
            ->where('taskHistory.data.0.opportunity', 'Đơn găng tay')
            ->where('taskHistory.data.0.status', 'cancelled')
            ->where('taskHistory.data.0.result', 'Khách hẹn lại')
            ->where('taskHistory.data.1.id', $direct->id)
            ->where('customer.successful_orders_count', 1));
        $this->actingAs($manager)->get(route('customers.show', $customer))->assertOk()->assertInertia(fn ($page) => $page
            ->where('taskHistory.total', 3));
        $this->actingAs($other)->get(route('customers.show', $customer))->assertForbidden();
    }

    public function test_customer_task_history_has_a_second_page_and_handles_missing_founded_date(): void
    {
        $sale = $this->user(UserRole::Sales);
        $customer = $this->customer($sale);
        foreach (range(1, 16) as $number) {
            Task::create(['title' => "Chăm sóc {$number}", 'type' => 'call', 'priority' => 'normal', 'created_by' => $sale->id, 'assignee_id' => $sale->id, 'customer_id' => $customer->id, 'due_at' => now(), 'status' => 'completed', 'completed_at' => now()]);
        }

        $this->actingAs($sale)->get(route('customers.show', [$customer, 'task_history_page' => 2]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('customer.founded_date', null)
            ->where('customer.successful_orders_count', 0)
            ->where('taskHistory.current_page', 2)
            ->where('taskHistory.total', 16)
            ->has('taskHistory.data', 1)
            ->where('taskHistory.data.0.title', 'Chăm sóc 1'));
    }

    public function test_purchase_classification_filter_returns_each_group_without_exposing_other_sales_customers(): void
    {
        $sale = $this->user(UserRole::Sales);
        $noOrders = $this->customer($sale, ['name' => 'Chưa mua']);
        $potential = $this->customer($sale, ['name' => 'Mua một lần']);
        $returning = $this->customer($sale, ['name' => 'Mua nhiều lần']);
        $other = $this->customer($this->user(UserRole::Sales), ['name' => 'Khách nhân viên khác']);
        foreach ([$potential, $returning, $returning, $other] as $customer) {
            $opportunity = new Lead(['owner_id' => $customer->owner_id, 'customer_id' => $customer->id, 'title' => 'Đơn thành công', 'contact_name' => 'An', 'stage' => 'won']);
            $opportunity->kind = 'opportunity';
            $opportunity->save();
        }

        $this->actingAs($sale)->get('/customers?purchase_classification=no_orders')->assertOk()->assertInertia(fn ($page) => $page
            ->where('customers.total', 1)->where('customers.data.0.id', $noOrders->id));
        $this->get('/customers?purchase_classification=potential')->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.purchase_classification', 'potential')
            ->where('customers.total', 1)->where('customers.data.0.id', $potential->id));
        $this->get('/customers?purchase_classification=returning')->assertOk()->assertInertia(fn ($page) => $page
            ->where('customers.total', 1)->where('customers.data.0.id', $returning->id));
        $this->get('/customers?purchase_classification=potential&q=KhongTonTai')->assertOk()->assertInertia(fn ($page) => $page
            ->where('customers.total', 0));
        $this->get('/customers')->assertOk()->assertInertia(fn ($page) => $page->where('customers.total', 3));
        $this->get('/customers?purchase_classification=invalid')->assertRedirect()->assertSessionHasErrors('purchase_classification');
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

    public function test_completed_customer_task_is_added_to_the_change_log(): void
    {
        $sale = $this->user(UserRole::Sales);
        $customer = $this->customer($sale);
        $saveTask = new SaveTask;
        $completedTask = $saveTask->handle([
            'title' => 'Gọi khách xác nhận giao dịch',
            'description' => null,
            'type' => 'call',
            'priority' => 'normal',
            'assignee_id' => $sale->id,
            'reference' => "customer:{$customer->id}",
            'due_at' => now('UTC')->addDay()->toIso8601String(),
        ], $sale);
        $cancelledTask = $saveTask->handle([
            'title' => 'Nhiệm vụ bị hủy',
            'description' => null,
            'type' => 'call',
            'priority' => 'normal',
            'assignee_id' => $sale->id,
            'reference' => "customer:{$customer->id}",
            'due_at' => now('UTC')->addDay()->toIso8601String(),
        ], $sale);

        $this->actingAs($sale)
            ->patch(route('tasks.status', $completedTask), [
                'status' => 'completed',
                'version' => $completedTask->version,
                'note' => 'Khách đã xác nhận đơn hàng.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->patch(route('tasks.status', $cancelledTask), [
            'status' => 'cancelled',
            'version' => $cancelledTask->version,
            'note' => 'Khách dời lịch.',
        ])->assertRedirect();

        $this->get(route('customers.show', $customer))
            ->assertInertia(fn ($page) => $page
                ->has('auditLogs', 2)
                ->where('auditLogs.0.event', 'task_completed')
                ->where('auditLogs.0.subject', 'Task')
                ->where('auditLogs.0.task_title', 'Gọi khách xác nhận giao dịch')
                ->where('auditLogs.0.old.status', 'pending')
                ->where('auditLogs.0.new.status', 'completed')
                ->where('auditLogs.0.new.result', 'Khách đã xác nhận đơn hàng.'));

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Task::class,
            'auditable_id' => $completedTask->id,
            'event' => 'updated',
        ]);
    }
}
