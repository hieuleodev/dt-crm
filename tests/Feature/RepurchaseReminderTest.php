<?php

namespace Tests\Feature;

use App\Enums\Customer\LifecycleStage;
use App\Enums\Staff\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepurchaseReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(UserRole $role = UserRole::Sales): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function customer(User $owner, string $name = 'Công ty Điện tử Bắc Ninh'): Customer
    {
        return (new SaveCustomer)->handle([
            'name' => $name,
            'lifecycle_stage' => 'new_customer',
            'status' => 'active',
            'owner_id' => $owner->id,
        ], $owner);
    }

    private function opportunity(User $owner, Customer $customer, string $stage, int $daysAgo, string $title = 'Đơn hàng Giày bảo hộ ESD'): Lead
    {
        $timestamp = now()->subDays($daysAgo);

        $opp = new Lead([
            'customer_id' => $customer->id,
            'title' => $title,
            'company' => $customer->name,
            'contact_name' => 'Nguyễn Văn Mua',
            'phone' => '0912345678',
            'stage' => $stage,
            'estimated_value' => 45000000,
            'probability' => Lead::STAGE_PROBABILITIES[$stage] ?? 100,
        ]);
        $opp->owner_id = $owner->id;
        $opp->kind = 'opportunity';
        $opp->created_at = $timestamp;
        $opp->updated_at = $timestamp;
        $opp->timestamps = false;
        $opp->save();

        // Chỉnh ngày log tạo cơ hội về đúng số ngày trước để khớp chu kỳ 15-30 ngày
        AuditLog::query()
            ->where('auditable_id', $opp->id)
            ->update(['created_at' => $timestamp]);

        return $opp;
    }

    public function test_guests_cannot_access_repurchase_reminders(): void
    {
        $this->get('/repurchase-reminders')->assertRedirect('/login');
    }

    public function test_categorizes_proposals_and_won_deals_by_15_to_30_day_window(): void
    {
        $sales = $this->user(UserRole::Sales);
        $otherSales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);

        // 1. Đợt mua thành công đã qua 20 ngày -> Đến hạn nhắc (15-30 ngày: 'due')
        $dueWon = $this->opportunity($sales, $customer, 'won', 20, 'Đơn thành công 20 ngày trước');

        // 2. Đợt báo giá đã qua 35 ngày -> Quá hạn (>30 ngày: 'overdue')
        $overdueProposal = $this->opportunity($sales, $customer, 'proposal', 35, 'Báo giá 35 ngày trước');

        // 3. Đợt mua thành công mới 5 ngày -> Chưa tới hạn (<15 ngày: 'upcoming')
        $upcomingWon = $this->opportunity($sales, $customer, 'won', 5, 'Đơn mới 5 ngày trước');

        // 4. Đợt của nhân viên khác -> Sales hiện tại không được thấy
        $this->opportunity($otherSales, $this->customer($otherSales, 'Khách khác'), 'won', 18, 'Đơn của người khác');

        $response = $this->actingAs($sales)->get('/repurchase-reminders');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('RepurchaseReminders/Index')
            ->where('summary.total', 3)
            ->where('summary.due_15_30', 1)
            ->where('summary.overdue_30', 1)
            ->where('summary.upcoming_under_15', 1)
            ->where('summary.won_count', 2)
            ->where('summary.proposal_count', 1)
            ->has('reminders', 3)
            ->where('reminders.0.id', $dueWon->id)
            ->where('reminders.0.cycle_status', 'due')
            ->where('reminders.1.id', $overdueProposal->id)
            ->where('reminders.1.cycle_status', 'overdue')
            ->where('reminders.2.id', $upcomingWon->id)
            ->where('reminders.2.cycle_status', 'upcoming')
        );
    }

    public function test_can_schedule_repurchase_reminder_task(): void
    {
        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $opp = $this->opportunity($sales, $customer, 'won', 18);

        $response = $this->actingAs($sales)->post("/repurchase-reminders/{$opp->id}/schedule", [
            'title' => 'Nhắc mua lại găng tay chống tĩnh điện',
            'type' => 'follow_up',
            'priority' => 'high',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'description' => 'Gọi phòng thu mua kiểm tra tồn kho sau 18 ngày.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('care_tasks', [
            'lead_id' => $opp->id,
            'customer_id' => $customer->id,
            'assignee_id' => $sales->id,
            'title' => 'Nhắc mua lại găng tay chống tĩnh điện',
            'status' => 'pending',
        ]);
    }

    public function test_completing_repurchase_reminder_syncs_with_history_and_creates_repeat_opportunity(): void
    {
        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $opp = $this->opportunity($sales, $customer, 'won', 22, 'Đơn găng tay PU đợt 1');

        $response = $this->actingAs($sales)->post("/repurchase-reminders/{$opp->id}/complete", [
            'result' => 'Khách xác nhận đặt tiếp 1.000 đôi găng tay PU, yêu cầu gửi báo giá mới.',
            'create_reorder' => true,
            'reorder_value' => 52000000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 1. Task nhắc mua lại đã được lưu ở trạng thái completed
        $completedTask = Task::query()->where('lead_id', $opp->id)->firstOrFail();
        $this->assertSame('completed', $completedTask->status);
        $this->assertSame('Khách xác nhận đặt tiếp 1.000 đôi găng tay PU, yêu cầu gửi báo giá mới.', $completedTask->result);

        // 2. Khách hàng được chuyển sang trạng thái Mua lặp lại (repeat)
        $customer->refresh();
        $this->assertSame(LifecycleStage::Repeat, $customer->lifecycle_stage);

        // 3. Đã tạo Cơ hội báo giá mua lại mới
        $this->assertDatabaseHas('leads', [
            'customer_id' => $customer->id,
            'kind' => 'opportunity',
            'stage' => 'proposal',
            'title' => 'Đơn mua lại: Đơn găng tay PU đợt 1',
        ]);

        // 4. Kiểm tra kết nối trực tiếp với trang Lịch sử (/history?type=task) từ nhánh feature/history-task-audit
        $this->actingAs($sales)->get('/history?type=task')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('History/Index')
                ->where('summary.breakdown.task.successful', 1)
                ->where('records.data.0.id', $completedTask->id)
                ->where('records.data.0.result', 'Khách xác nhận đặt tiếp 1.000 đôi găng tay PU, yêu cầu gửi báo giá mới.')
            );

        // 5. Kiểm tra kết nối với Nhật ký thay đổi của Khách hàng (Customers/Show)
        $this->actingAs($sales)->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Show')
                ->where('auditLogs', fn ($logs) => collect($logs)->contains('event', 'task_completed'))
            );
    }

    public function test_header_bell_receives_repurchase_notifications_count_and_items(): void
    {
        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);

        $this->opportunity($sales, $customer, 'won', 18, 'Đơn đến hạn 18 ngày');
        $this->opportunity($sales, $customer, 'proposal', 35, 'Báo giá quá hạn 35 ngày');
        $this->opportunity($sales, $customer, 'won', 5, 'Đơn mới 5 ngày');

        $this->actingAs($sales)->get('/repurchase-reminders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('repurchaseNotifications.due_count', 1)
                ->where('repurchaseNotifications.overdue_count', 1)
                ->where('repurchaseNotifications.total_count', 2)
                ->has('repurchaseNotifications.items', 2)
            );
    }

    public function test_marking_opportunity_as_won_automatically_schedules_15_day_repurchase_task(): void
    {
        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $opp = $this->opportunity($sales, $customer, 'negotiation', 2, 'Đơn giày bảo hộ Jogger');

        $this->actingAs($sales)
            ->patch(route('opportunities.stage', $opp), ['stage' => 'won'])
            ->assertRedirect();

        $this->assertDatabaseHas('care_tasks', [
            'lead_id' => $opp->id,
            'customer_id' => $customer->id,
            'assignee_id' => $sales->id,
            'status' => 'pending',
            'type' => 'follow_up',
        ]);
    }

    public function test_can_snooze_reminder_and_complete_with_follow_up_snooze(): void
    {
        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $opp = $this->opportunity($sales, $customer, 'won', 17, 'Đơn khẩu trang than hoạt tính');

        // 1. Dời nhanh lịch hẹn +7 ngày
        $this->actingAs($sales)->post("/repurchase-reminders/{$opp->id}/snooze", [
            'days' => 7,
            'note' => 'Khách báo còn nửa kho, tuần sau gọi lại.',
        ])->assertRedirect();

        $this->assertDatabaseHas('care_tasks', [
            'lead_id' => $opp->id,
            'status' => 'pending',
        ]);

        // 2. Ghi nhận đã gọi hôm nay kèm tùy chọn hẹn gọi lại sau +14 ngày
        $this->actingAs($sales)->post("/repurchase-reminders/{$opp->id}/complete", [
            'result' => 'Đã gọi lần 1, khách hẹn 2 tuần nữa kiểm tra lại định mức.',
            'snooze_enabled' => true,
            'snooze_days' => 14,
        ])->assertRedirect();

        // Có 1 task completed (lưu lịch sử) và 1 task pending mới (+14 ngày)
        $this->assertSame(1, Task::query()->where('lead_id', $opp->id)->where('status', 'completed')->count());
        $this->assertSame(1, Task::query()->where('lead_id', $opp->id)->where('status', 'pending')->count());
    }

    public function test_updating_or_moving_another_opportunity_does_not_drop_repurchase_notification_count(): void
    {
        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);

        // 1 cơ hội cũ đến hạn nhắc (20 ngày trước), xóa log để kiểm tra fallback không dùng updated_at
        $dueOpp = $this->opportunity($sales, $customer, 'proposal', 20, 'Báo giá 20 ngày trước');
        AuditLog::query()->where('auditable_id', $dueOpp->id)->delete();

        // 1 cơ hội đang ở giai đoạn Đàm phán
        $negOpp = $this->opportunity($sales, $customer, 'negotiation', 2, 'Thương vụ đang đàm phán');

        // Ban đầu có 1 thông báo đến hạn
        $this->actingAs($sales)->get('/repurchase-reminders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('repurchaseNotifications.due_count', 1)
                ->where('repurchaseNotifications.total_count', 1)
            );

        // Chỉnh sửa ghi chú của $dueOpp (làm updated_at nhảy về hiện tại)
        $dueOpp->timestamps = true;
        $dueOpp->update(['notes' => 'Cập nhật ghi chú không đổi giai đoạn']);

        // Chuyển $negOpp từ Đàm phán sang Báo giá
        $this->actingAs($sales)
            ->patch(route('opportunities.stage', $negOpp), ['stage' => 'proposal'])
            ->assertRedirect();

        // Thông báo nhắc nhở vẫn phải giữ nguyên là 1 (không bị trừ đi 1)
        $this->actingAs($sales)->get('/repurchase-reminders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('repurchaseNotifications.due_count', 1)
                ->where('repurchaseNotifications.total_count', 1)
            );
    }
}
