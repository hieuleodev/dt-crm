<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Care\SaveTask;
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
            ->has('team', 3)->has('assignees', 3));
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
        $this->actingAs($first)->get('/?assignee_id='.$second->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('canAssign', false)->where('filters.assignee_id', null)
            ->where('summary.open', 1)->where('summary.customers', 1)->where('summary.leads', 0)
            ->has('team', 0)->has('assignees', 1)->where('assignees.0.id', $first->id)
            ->has('upcomingTasks', 1)->where('upcomingTasks.0.id', $ownTask->id));
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
}
