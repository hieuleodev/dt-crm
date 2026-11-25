<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Care\SaveTask;
use App\Services\Customer\SaveCustomer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(1, 0));
    }

    private function user(UserRole $role = UserRole::Sales, bool $active = true): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => $active]);
    }

    private function payload(User $assignee, array $overrides = []): array
    {
        return [...[
            'title' => 'Chăm sóc khách hàng', 'description' => null,
            'type' => 'internal', 'priority' => 'normal',
            'assignee_id' => $assignee->id, 'reference' => null,
            'due_at' => now('UTC')->addHours(2)->toIso8601String(),
        ], ...$overrides];
    }

    private function task(User $assignee, array $overrides = []): Task
    {
        return (new SaveTask)->handle($this->payload($assignee, $overrides), $assignee);
    }

    public function test_guests_and_inactive_users_cannot_access_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->actingAs($this->user(UserRole::Admin, false))->get('/')->assertForbidden();
    }

    public function test_admin_sees_team_totals_and_can_filter_one_assignee(): void
    {
        $admin = $this->user(UserRole::Admin);
        $first = $this->user();
        $second = $this->user();
        $this->user(UserRole::Sales, false);
        $this->task($first, ['due_at' => now('UTC')->subHour()->toIso8601String()]);
        $this->task($second);
        $this->actingAs($admin)->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')->where('canAssign', true)
            ->where('summary.open', 2)->where('summary.overdue', 1)
            ->has('team', 2)->has('assignees', 2));
        $this->get('/?assignee_id='.$first->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.assignee_id', $first->id)->where('summary.open', 1)
            ->has('upcomingTasks', 1)->where('upcomingTasks.0.assignee_id', $first->id));
    }

    public function test_staff_cannot_read_other_staff_totals_even_with_a_forged_filter(): void
    {
        $first = $this->user();
        $second = $this->user();
        $ownTask = $this->task($first);
        $this->task($second);
        Customer::create(['code' => 'DASH01', 'name' => 'Own customer', 'owner_id' => $first->id, 'created_by' => $first->id, 'lifecycle_stage' => 'prospect', 'status' => 'active']);
        Customer::create(['code' => 'DASH02', 'name' => 'Other customer', 'owner_id' => $second->id, 'created_by' => $second->id, 'lifecycle_stage' => 'prospect', 'status' => 'active']);
        Lead::forceCreate(['owner_id' => $second->id, 'title' => 'Private lead', 'contact_name' => 'Private contact']);
        $ownOpportunity = Lead::forceCreate([
            'owner_id' => $first->id,
            'title' => 'Own opportunity',
            'contact_name' => 'Buyer',
            'kind' => 'opportunity',
            'stage' => 'proposal',
        ]);

        $this->actingAs($first)->get('/?assignee_id='.$second->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('canAssign', false)->where('filters.assignee_id', null)
            ->where('summary.open', 1)->where('summary.customers', 1)->where('summary.leadRecords', 1)
            ->where('leadListUrl', '/leads')
            ->has('team', 0)->has('assignees', 1)->where('assignees.0.id', $first->id)
            ->has('upcomingTasks', 1)->where('upcomingTasks.0.id', $ownTask->id));

        $this->get(route('leads.index', ['owner_id' => $second->id]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.owner_id', null)
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $ownOpportunity->id));
    }

    public function test_dashboard_opens_the_combined_list_when_only_opportunities_exist(): void
    {
        $admin = $this->user(UserRole::Admin);
        $staff = $this->user();
        $opportunity = Lead::forceCreate([
            'owner_id' => $staff->id,
            'title' => 'Closed opportunity',
            'contact_name' => 'Buyer',
            'kind' => 'opportunity',
            'stage' => 'lost',
        ]);

        $dashboard = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.leadRecords', 1)
            ->where('leadListUrl', '/leads'));

        $this->get($dashboard->inertiaProps('leadListUrl'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Leads/Index')
            ->where('filters.kind', '')
            ->where('summary.total', 1)
            ->where('summary.leads', 0)
            ->where('summary.opportunities', 1)
            ->where('leads.data.0.id', $opportunity->id));
    }

    public function test_dashboard_passes_the_selected_owner_to_the_combined_list(): void
    {
        $admin = $this->user(UserRole::Admin);
        $first = $this->user();
        $second = $this->user();
        Lead::forceCreate(['owner_id' => $first->id, 'title' => 'Assigned lead', 'contact_name' => 'Buyer']);
        Lead::forceCreate([
            'owner_id' => $first->id,
            'title' => 'Assigned opportunity',
            'contact_name' => 'Buyer',
            'kind' => 'opportunity',
            'stage' => 'proposal',
        ]);
        Lead::forceCreate(['owner_id' => $second->id, 'title' => 'Other lead', 'contact_name' => 'Buyer']);

        $dashboard = $this->actingAs($admin)
            ->get(route('dashboard', ['assignee_id' => $first->id]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.leadRecords', 2)
            ->where('leadListUrl', '/leads?owner_id='.$first->id));

        $this->get($dashboard->inertiaProps('leadListUrl'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.owner_id', $first->id)
            ->where('selectedOwnerName', $first->name)
            ->where('summary.total', 2)
            ->where('summary.leads', 1)
            ->where('summary.opportunities', 1)
            ->has('leads.data', 2)
            ->where('leads.data.0.owner_id', $first->id)
            ->where('leads.data.1.owner_id', $first->id));

        $this->get(route('leads.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.owner_id', null)
            ->where('summary.total', 3));
    }

    public function test_combined_list_rejects_an_invalid_owner_filter(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->get(route('leads.index', ['owner_id' => 'invalid']))
            ->assertSessionHasErrors('owner_id');
    }

    public function test_daily_totals_use_vietnam_midnight_and_exclude_finished_tasks(): void
    {
        $staff = $this->user();
        $this->task($staff, ['due_at' => '2026-11-09T17:00:00Z']);
        $this->task($staff, ['due_at' => '2026-11-10T16:59:59Z']);
        $this->task($staff, ['due_at' => '2026-11-10T17:00:00Z']);
        $done = $this->task($staff);
        $done->update(['status' => 'completed', 'completed_at' => '2026-11-09 17:00:00']);
        $previous = $this->task($staff);
        $previous->update(['status' => 'completed', 'completed_at' => '2026-11-09 16:59:59']);
        $cancelled = $this->task($staff);
        $cancelled->update(['status' => 'cancelled']);
        $this->actingAs($staff)->get('/')->assertInertia(fn ($page) => $page
            ->where('summary.today', 1)->where('summary.overdue', 1)
            ->where('summary.open', 3)->where('summary.completedToday', 1)
            ->where('statusCounts.2.total', 2)->where('statusCounts.3.total', 1));
    }

    // Kiểm tra nhiệm vụ chuyển nhóm ngay sau giờ hạn, không chỉ theo ngày.
    public function test_task_today_and_overdue_buckets_follow_the_exact_due_time(): void
    {
        $staff = $this->user();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00:00', 'Asia/Ho_Chi_Minh'));

        $pastDue = $this->task($staff, ['due_at' => '2026-11-10T02:59:59Z']);
        $dueNow = $this->task($staff, ['due_at' => '2026-11-10T03:00:00Z']);
        $dueLater = $this->task($staff, ['due_at' => '2026-11-10T03:00:01Z']);
        $this->task($staff, ['due_at' => '2026-11-10T17:00:00Z']);

        $this->actingAs($staff)->get('/tasks?bucket=today')->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.today', 2)->where('summary.overdue', 1)
            ->has('tasks.data', 2)
            ->where('tasks.data.0.id', $dueNow->id)
            ->where('tasks.data.1.id', $dueLater->id));
        $this->get('/tasks?bucket=overdue')->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.today', 2)->where('summary.overdue', 1)
            ->has('tasks.data', 1)->where('tasks.data.0.id', $pastDue->id));

        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00:01', 'Asia/Ho_Chi_Minh'));
        $this->get('/tasks?bucket=today')->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.today', 1)->where('summary.overdue', 2)
            ->has('tasks.data', 1)->where('tasks.data.0.id', $dueLater->id));
        $this->get('/tasks?bucket=overdue')->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.today', 1)->where('summary.overdue', 2)
            ->has('tasks.data', 2)
            ->where('tasks.data.0.id', $pastDue->id)
            ->where('tasks.data.1.id', $dueNow->id));

    }

    public function test_staff_dashboard_prioritizes_overdue_and_today_tasks_with_their_customer_reference(): void
    {
        $staff = $this->user();
        $customer = Customer::create([
            'code' => 'DASH04',
            'name' => 'Khách cần tư vấn',
            'owner_id' => $staff->id,
            'created_by' => $staff->id,
            'lifecycle_stage' => 'prospect',
            'status' => 'active',
        ]);
        $overdue = $this->task($staff, [
            'due_at' => now('UTC')->subHour()->toIso8601String(),
            'priority' => 'urgent',
            'reference' => 'customer:'.$customer->id,
        ]);
        $today = $this->task($staff, [
            'due_at' => now('UTC')->addHours(2)->toIso8601String(),
            'priority' => 'high',
        ]);
        $upcoming = $this->task($staff, [
            'due_at' => now('UTC')->addDay()->toIso8601String(),
        ]);

        $this->actingAs($staff)->get('/')->assertInertia(fn ($page) => $page
            ->where('summary.overdue', 1)
            ->where('summary.today', 1)
            ->where('upcomingTasks.0.id', $overdue->id)
            ->where('upcomingTasks.0.reference.label', 'DASH04 · Khách cần tư vấn')
            ->where('upcomingTasks.1.id', $today->id)
            ->where('upcomingTasks.2.id', $upcoming->id));
    }

    public function test_admin_can_assign_and_reassign_a_task_to_specific_staff(): void
    {
        $admin = $this->user(UserRole::Admin);
        $first = $this->user();
        $second = $this->user();
        $this->actingAs($admin)->from('/')->post('/tasks', $this->payload($first))->assertSessionHasNoErrors()->assertRedirect('/');
        $task = Task::firstOrFail();
        $this->assertSame($first->id, $task->assignee_id);
        $this->assertSame($admin->id, $task->created_by);
        $this->actingAs($first)->get('/')->assertInertia(fn ($page) => $page->where('summary.open', 1));
        $this->actingAs($admin)->from('/')->put('/tasks/'.$task->id, $this->payload($second, ['version' => $task->version]))->assertSessionHasNoErrors();
        $this->assertSame($second->id, $task->fresh()->assignee_id);
        $this->actingAs($first)->get('/')->assertInertia(fn ($page) => $page->where('summary.open', 0));
        $this->actingAs($second)->get('/')->assertInertia(fn ($page) => $page->where('summary.open', 1));
    }

    public function test_staff_cannot_create_tasks_or_edit_other_staff_tasks(): void
    {
        $first = $this->user();
        $second = $this->user();
        $ownTask = $this->task($first);
        $otherTask = $this->task($second);

        $this->actingAs($first)->post('/tasks', $this->payload($first))->assertForbidden();
        $this->post('/tasks', $this->payload($second))->assertForbidden();
        $this->assertDatabaseCount('care_tasks', 2);
        $this->put('/tasks/'.$ownTask->id, $this->payload($first, ['version' => 0]))->assertForbidden();
        $this->put('/tasks/'.$otherTask->id, $this->payload($first, ['version' => 0]))->assertForbidden();
        $this->patch('/tasks/'.$otherTask->id.'/status', ['status' => 'completed', 'note' => 'Done', 'version' => 0])->assertNotFound();
        $this->assertSame('pending', $otherTask->fresh()->status);
    }

    public function test_assignment_rejects_inactive_staff_and_inaccessible_customer_records(): void
    {
        $admin = $this->user(UserRole::Admin);
        $first = $this->user();
        $second = $this->user();
        $inactive = $this->user(UserRole::Sales, false);
        $this->actingAs($admin)->post('/tasks', $this->payload($inactive))->assertSessionHasErrors('assignee_id');
        $customer = Customer::create(['code' => 'DASH03', 'name' => 'Restricted customer', 'owner_id' => $second->id, 'created_by' => $admin->id, 'lifecycle_stage' => 'prospect', 'status' => 'active']);
        $this->post('/tasks', $this->payload($first, ['reference' => 'customer:'.$customer->id]))->assertSessionHasErrors('assignee_id');
        $this->assertDatabaseCount('care_tasks', 0);
    }

    public function test_admin_can_assign_a_linked_lead_task_to_the_current_customer_owner(): void
    {
        $admin = $this->user(UserRole::Admin);
        $previousOwner = $this->user();
        $newOwner = $this->user();
        $customer = app(SaveCustomer::class)->handle([
            'name' => 'Existing customer',
            'email' => 'buyer@example.test',
        ], $previousOwner);
        $leadData = [
            'title' => 'Product follow-up',
            'contact_name' => $customer->name,
            'email' => $customer->email,
            'stage' => 'new',
        ];

        $this->actingAs($admin)->post(route('leads.store'), $leadData)->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $reference = ['reference' => 'lead:'.$lead->id];
        $this->post(route('tasks.store'), $this->payload($previousOwner, $reference))
            ->assertSessionHasErrors('assignee_id');

        $this->put(route('leads.update', $lead), [...$leadData, 'customer_id' => $customer->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id, 'owner_id' => $admin->id, 'customer_id' => $customer->id,
        ]);

        $this->post(route('customers.reassign', $customer), ['owner_id' => $newOwner->id])
            ->assertSessionHasNoErrors();
        $this->get(route('dashboard', ['assignee_id' => $newOwner->id]))->assertInertia(fn ($page) => $page
            ->where('summary.leadRecords', 1));
        $this->post(route('tasks.store'), $this->payload($previousOwner, $reference))
            ->assertSessionHasErrors('assignee_id');
        $this->post(route('tasks.store'), $this->payload($newOwner, $reference))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('care_tasks', 1);
        $task = Task::sole();

        $this->actingAs($newOwner)->get(route('leads.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $lead->id)
            ->where('leads.data.0.customer.id', $customer->id)
            ->where('leads.data.0.customer.owner.id', $newOwner->id));
        $this->get(route('tasks.index', ['lead_id' => $lead->id]))->assertOk()->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)->where('tasks.data.0.id', $task->id));
        $this->put(route('leads.update', $lead), [...$leadData, 'stage' => 'contacted'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'stage' => 'contacted', 'customer_id' => $customer->id]);
        $this->actingAs($previousOwner)->put(route('leads.update', $lead), $leadData)->assertForbidden();
    }

    public function test_sales_cannot_change_the_linked_customer_of_a_lead(): void
    {
        $sales = $this->user();
        $originalOwner = $this->user();
        $customer = app(SaveCustomer::class)->handle(['name' => 'Assigned customer'], $sales);
        $lead = Lead::forceCreate([
            'owner_id' => $originalOwner->id,
            'customer_id' => $customer->id,
            'title' => 'Linked lead',
            'contact_name' => 'Buyer',
            'email' => 'buyer@example.test',
        ]);

        $this->actingAs($sales)->put(route('leads.update', $lead), [
            ...$lead->only('title', 'contact_name', 'email'),
            'stage' => 'contacted',
            'customer_id' => null,
        ])->assertSessionHasErrors('customer_id');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'stage' => 'new', 'customer_id' => $customer->id]);

        $this->actingAs($originalOwner)->get(route('leads.index'))->assertInertia(fn ($page) => $page
            ->has('leads.data', 0));
    }

    public function test_staff_can_complete_their_assigned_task_and_update_dashboard_totals(): void
    {
        $staff = $this->user();
        $task = $this->task($staff);
        $this->actingAs($staff)->from('/tasks')->patch('/tasks/'.$task->id.'/status', ['status' => 'completed', 'note' => 'Đã hoàn tất', 'version' => 0])->assertSessionHasNoErrors();
        $this->get('/')->assertInertia(fn ($page) => $page->where('summary.open', 0)->where('summary.completedToday', 1));
    }

    public function test_manager_keeps_existing_assignment_permissions_and_invalid_filters_are_rejected(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user();
        $this->actingAs($manager)->get('/')->assertInertia(fn ($page) => $page->where('canAssign', true));
        $this->post('/tasks', $this->payload($staff))->assertSessionHasNoErrors();
        $this->get('/?assignee_id=999999')->assertSessionHasErrors('assignee_id');
    }

    public function test_admin_and_manager_cannot_assign_tasks_to_management_accounts(): void
    {
        $admin = $this->user(UserRole::Admin);
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($admin)
            ->post('/tasks', $this->payload($admin))
            ->assertSessionHasErrors('assignee_id');

        $this->actingAs($manager)
            ->post('/tasks', $this->payload($manager))
            ->assertSessionHasErrors('assignee_id');

        $this->assertDatabaseCount('care_tasks', 0);
    }

    public function test_dashboard_counts_modules_without_per_account_permission_assignments(): void
    {
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);
        Customer::create([
            'code' => 'PERM01',
            'name' => 'Restricted customer',
            'owner_id' => $sales->id,
            'created_by' => $sales->id,
            'lifecycle_stage' => 'prospect',
            'status' => 'active',
        ]);
        Lead::forceCreate([
            'owner_id' => $sales->id,
            'title' => 'Restricted lead',
            'contact_name' => 'Buyer',
        ]);
        $this->task($sales);

        $this->actingAs($sales)->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.customers', 1)
            ->where('summary.leadRecords', 1)
            ->where('summary.open', 1)
            ->has('upcomingTasks', 1));
    }
}
