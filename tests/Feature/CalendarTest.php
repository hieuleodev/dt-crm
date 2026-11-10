<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(UserRole $role): User
    {
        return User::create([
            'name' => $role->label().' '.uniqid(),
            'email' => uniqid().'@test.vn',
            'password' => 'Secret123!',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function task(User $assignee, array $overrides = []): Task
    {
        return Task::create([
            'title' => 'Nhiệm vụ kiểm thử '.uniqid(),
            'type' => 'call',
            'priority' => 'normal',
            'status' => 'pending',
            'assignee_id' => $assignee->id,
            'created_by' => $assignee->id,
            'due_at' => CarbonImmutable::now('UTC')->addHours(2),
            ...$overrides,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/calendar')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_calendar(): void
    {
        $user = $this->user(UserRole::Sales);
        $task = $this->task($user);

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Calendar/Index')
                ->has('tasks')
                ->has('summary')
                ->has('options')
                ->has('assignees')
                ->where('summary.total', 1)
                ->where('tasks.0.id', $task->id),
        );
    }

    public function test_sales_only_sees_own_tasks_on_calendar(): void
    {
        $sales1 = $this->user(UserRole::Sales);
        $sales2 = $this->user(UserRole::Sales);

        $myTask = $this->task($sales1, ['title' => 'Nhiệm vụ của tôi']);
        $otherTask = $this->task($sales2, ['title' => 'Nhiệm vụ của đồng nghiệp']);

        $response = $this->actingAs($sales1)->get('/calendar');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Calendar/Index')
                ->where('summary.total', 1)
                ->where('tasks.0.id', $myTask->id),
        );
    }

    public function test_manager_sees_all_tasks_and_can_filter_by_assignee(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales1 = $this->user(UserRole::Sales);
        $sales2 = $this->user(UserRole::Sales);

        $task1 = $this->task($sales1);
        $task2 = $this->task($sales2);

        $response = $this->actingAs($manager)->get('/calendar');
        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->where('summary.total', 2),
        );

        // Filter by sales1
        $filteredResponse = $this->actingAs($manager)->get('/calendar?assignee_id='.$sales1->id);
        $filteredResponse->assertOk();
        $filteredResponse->assertInertia(
            fn ($page) => $page
                ->where('summary.total', 1)
                ->where('tasks.0.id', $task1->id),
        );
    }

    public function test_calendar_filter_by_type_and_status(): void
    {
        $user = $this->user(UserRole::Sales);

        $callTask = $this->task($user, ['type' => 'call', 'status' => 'pending']);
        $meetingTask = $this->task($user, ['type' => 'meeting', 'status' => 'completed']);

        // Filter by type=call
        $response = $this->actingAs($user)->get('/calendar?type=call');
        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->where('summary.total', 1)
                ->where('tasks.0.id', $callTask->id),
        );

        // Filter by status=completed
        $response = $this->actingAs($user)->get('/calendar?status=completed');
        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->where('summary.total', 1)
                ->where('tasks.0.id', $meetingTask->id),
        );
    }

    public function test_calendar_returns_json_when_requested(): void
    {
        $user = $this->user(UserRole::Sales);
        $task = $this->task($user);

        $response = $this->actingAs($user)->getJson('/calendar');

        $response->assertOk();
        $response->assertJsonStructure([
            'tasks',
            'summary' => ['total', 'open', 'completed', 'overdue'],
            'filters',
        ]);
        $this->assertEquals($task->id, $response->json('tasks.0.id'));
    }
}
