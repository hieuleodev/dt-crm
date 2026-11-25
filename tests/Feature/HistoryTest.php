<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Care\SaveTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_dashboard_separates_closed_leads_opportunities_and_tasks(): void
    {
        $this->withoutVite();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);
        $disqualifiedLead = $this->lead($manager, 'lead', 'disqualified', 'Disqualified lead');
        $this->lead($manager, 'lead', 'qualified', 'Qualified lead');
        $this->lead($manager, 'opportunity', 'won', 'Won opportunity');
        $this->lead($manager, 'opportunity', 'lost', 'Lost opportunity');
        $this->closeTask($this->task($sales, $manager), 'completed');
        $this->closeTask($this->task($sales, $manager), 'cancelled');
        $this->task($sales, $manager);

        $this->actingAs($manager)->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('History/Index')
                ->where('filters.type', 'lead')
                ->where('filters.from', null)
                ->where('filters.to', null)
                ->where('summary.successful', 2)
                ->where('summary.failed', 3)
                ->where('summary.breakdown.lead.total', 1)
                ->where('summary.breakdown.lead.failed', 1)
                ->where('summary.breakdown.opportunity.successful', 1)
                ->where('summary.breakdown.opportunity.failed', 1)
                ->where('summary.breakdown.task.successful', 1)
                ->where('summary.breakdown.task.failed', 1)
                ->has('records.data', 1)
                ->where('records.data.0.id', $disqualifiedLead->id)
                ->where('records.data.0.status', 'disqualified'));

        $this->get(route('history.index', ['type' => 'opportunity']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.type', 'opportunity')
                ->has('records.data', 2)
                ->where('records.data.0.status', 'lost')
                ->where('records.data.1.status', 'won'));

        $this->get(route('history.index', ['type' => 'task']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.type', 'task')
                ->has('records.data', 2)
                ->where('records.data.0.status', 'cancelled')
                ->where('records.data.1.status', 'completed'));
    }

    public function test_date_range_filters_closed_records_using_vietnam_calendar_days(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
        $included = $this->lead($manager, 'opportunity', 'won', 'Won during selected day');
        $excluded = $this->lead($manager, 'opportunity', 'lost', 'Won before selected day');
        DB::table('leads')->where('id', $included->id)->update([
            'updated_at' => '2026-11-10 03:00:00',
        ]);
        DB::table('leads')->where('id', $excluded->id)->update([
            'updated_at' => '2026-11-09 16:59:59',
        ]);

        $this->actingAs($manager)
            ->get(route('history.index', [
                'type' => 'opportunity',
                'from' => '2026-11-10',
                'to' => '2026-11-10',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.from', '2026-11-10')
                ->where('filters.to', '2026-11-10')
                ->where('summary.successful', 1)
                ->where('summary.failed', 0)
                ->has('records.data', 1)
                ->where('records.data.0.id', $included->id));
    }

    public function test_sales_history_is_limited_to_their_leads_opportunities_and_tasks(): void
    {
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);
        $otherSales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);
        $this->lead($sales, 'lead', 'disqualified', 'Own disqualified lead');
        $this->lead($otherSales, 'lead', 'disqualified', 'Other disqualified lead');
        $this->lead($otherSales, 'opportunity', 'won', 'Other won opportunity');
        $this->closeTask($this->task($sales), 'completed');
        $this->closeTask($this->task($otherSales), 'completed');

        $this->actingAs($sales)->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.successful', 1)
                ->where('summary.failed', 1)
                ->where('summary.breakdown.lead.total', 1)
                ->where('summary.breakdown.task.total', 1)
                ->has('records.data', 1)
                ->where('records.data.0.title', 'Own disqualified lead'));
    }

    public function test_history_rejects_a_start_date_after_the_end_date(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->from(route('history.index'))
            ->get(route('history.index', [
                'from' => '2026-11-11',
                'to' => '2026-11-10',
            ]))
            ->assertRedirect(route('history.index'))
            ->assertSessionHasErrors('to');
    }

    public function test_admin_sees_the_actual_actor_separately_from_the_task_assignee_in_the_activity_log(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $this->actingAs($admin);
        $task = $this->task($sales, $admin);

        $this->actingAs($sales)->patch(route('tasks.status', $task), [
            'status' => 'completed', 'version' => 0, 'note' => 'Đã chăm sóc khách hàng',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('history.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('activity.total', 2)
            ->where('activity.data.0.actor_id', $sales->id)
            ->where('activity.data.0.actor', $sales->name)
            ->where('activity.data.0.action', 'Cập nhật')
            ->where('activity.data.0.subject', $task->title)
            ->where('activity.data.0.subject_type', 'Nhiệm vụ / Lịch chăm sóc')
            ->where('activity.data.0.changed_fields.0', 'Trạng thái')
            ->where('activity.data.1.actor_id', $admin->id)
            ->where('activity.data.1.action', 'Tạo mới'));
    }

    public function test_management_can_filter_and_paginate_activity_by_actor_and_local_date(): void
    {
        $this->withoutVite();
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $other = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $this->freezeTime();
        foreach (range(1, 16) as $number) {
            AuditLog::forceCreate([
                'user_id' => $sales->id, 'event' => 'created', 'auditable_type' => Lead::class,
                'auditable_id' => $number, 'new_values' => ['title' => "Lead {$number}"],
                'created_at' => '2026-11-10 03:00:00',
            ]);
        }
        AuditLog::forceCreate([
            'user_id' => $other->id, 'event' => 'created', 'auditable_type' => Lead::class,
            'auditable_id' => 100, 'new_values' => ['title' => 'Nhân viên khác'],
            'created_at' => '2026-11-10 03:00:00',
        ]);
        AuditLog::forceCreate([
            'user_id' => $sales->id, 'event' => 'created', 'auditable_type' => Lead::class,
            'auditable_id' => 101, 'new_values' => ['title' => 'Ngoài khoảng ngày'],
            'created_at' => '2026-11-09 16:59:59',
        ]);

        $this->actingAs($manager)->get(route('history.index', [
            'actor_id' => $sales->id, 'from' => '2026-11-10', 'to' => '2026-11-10', 'activity_page' => 2,
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.actor_id', $sales->id)
            ->where('activity.total', 16)->where('activity.current_page', 2)
            ->has('activity.data', 1)->where('activity.data.0.subject', 'Lead 1')
            ->where('activity.data.0.actor_id', $sales->id));
    }

    public function test_sales_cannot_read_the_team_activity_log_or_request_another_actor(): void
    {
        $this->withoutVite();
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->actingAs($sales)->get(route('history.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('activity', null)->has('actors', 0));
        $this->get(route('history.index', ['actor_id' => $admin->id]))->assertForbidden();
    }

    public function test_activity_preserves_the_name_of_a_deleted_employee(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $this->actingAs($sales);
        $this->lead($sales, 'lead', 'new', 'Lead của nhân viên đã nghỉ');
        $sales->delete();

        $this->actingAs($admin)->get(route('history.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('activity.data.0.actor', $sales->name));
    }

    private function lead(User $owner, string $kind, string $stage, string $title): Lead
    {
        return Lead::forceCreate([
            'owner_id' => $owner->id,
            'title' => $title,
            'contact_name' => 'Nguyễn An',
            'company' => 'Công ty Mẫu',
            'kind' => $kind,
            'stage' => $stage,
            'estimated_value' => 1000000,
        ]);
    }

    private function task(User $assignee, ?User $actor = null): Task
    {
        return (new SaveTask)->handle([
            'title' => 'Sample history task',
            'description' => null,
            'type' => 'internal',
            'priority' => 'normal',
            'assignee_id' => $assignee->id,
            'reference' => null,
            'due_at' => now('UTC')->addHour()->toIso8601String(),
        ], $actor ?? $assignee);
    }

    private function closeTask(Task $task, string $status): void
    {
        DB::table('care_tasks')->where('id', $task->id)->update([
            'status' => $status,
            'result' => $status === 'completed' ? 'Task completed' : 'Task cancelled',
            'completed_at' => $status === 'completed' ? now('UTC')->toDateTimeString() : null,
            'updated_at' => now('UTC')->toDateTimeString(),
        ]);
    }
}
