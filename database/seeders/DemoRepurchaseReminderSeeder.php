<?php

namespace Database\Seeders;

use App\Enums\Staff\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoRepurchaseReminderSeeder extends Seeder
{
    /**
     * Run the database seeds for Repurchase Reminders (15-30 days cycle).
     */
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $this->call(DemoCustomerSeeder::class);

        $admin = User::where('role', UserRole::Admin)->firstOrFail();
        $sale1 = User::where('email', 'sale1@trangthietbi.vn')->first() ?? $admin;
        $sale2 = User::where('email', 'sale2@trangthietbi.vn')->first() ?? $admin;

        auth()->login($admin);

        $customers = Customer::query()->pluck('id', 'code');

        $demoDeals = [
            // 1. Nhóm ĐẾN HẠN 15-30 NGÀY (due) - 3 đợt
            [
                'title' => 'Đơn hàng 1.500 đôi Găng tay phòng sạch phủ PU & Khăn lau ESD',
                'customer_code' => 'DEMO01',
                'company' => 'Công ty TNHH Điện tử Minh Long (demo)',
                'contact_name' => 'Lê Thu Hà',
                'phone' => '0912000001',
                'email' => 'ha.le@minhlong.test',
                'stage' => 'won',
                'estimated_value' => 68500000,
                'days_ago' => 18,
                'owner' => $sale1,
                'notes' => 'Vật tư tiêu hao phòng sạch dùng theo chu kỳ 20 ngày/lần.',
                'task_mode' => 'none',
            ],
            [
                'title' => 'Đơn hàng 300 đôi Giày bảo hộ Safety Jogger & Nút tai chống ồn 3M',
                'customer_code' => 'DEMO03',
                'company' => 'Công ty CP Thép Hưng Phát (demo)',
                'contact_name' => 'Đỗ Mạnh Hùng',
                'phone' => '0912000004',
                'email' => 'lan.bui@hungphat.test',
                'stage' => 'won',
                'estimated_value' => 142000000,
                'days_ago' => 24,
                'owner' => $sale2,
                'notes' => 'Cấp phát bổ sung cho xưởng cán thép ca mới.',
                'task_mode' => 'none',
            ],
            [
                'title' => 'Báo giá Găng tay Nitrile không bột đạt chuẩn HACCP cho xưởng thực phẩm',
                'customer_code' => 'DEMO04',
                'company' => 'Công ty TNHH Thực phẩm Sao Mai (demo)',
                'contact_name' => 'Hoàng Ngọc Mai',
                'phone' => '0912000006',
                'email' => null,
                'stage' => 'proposal',
                'estimated_value' => 54000000,
                'days_ago' => 21,
                'owner' => $sale2,
                'notes' => 'Đã gửi mẫu thử size S/M/L từ 3 tuần trước, đến kỳ gọi nhắc chốt đơn.',
                'task_mode' => 'none',
            ],

            // 2. Nhóm QUÁ HẠN > 30 NGÀY (overdue) - 2 đợt
            [
                'title' => 'Đơn hàng Mặt nạ phòng độc 3M 6200 & Phin lọc hơi hữu cơ 6001',
                'customer_code' => 'DEMO07',
                'company' => 'Công ty TNHH Dầu khí Biển Xanh (demo)',
                'contact_name' => 'Trần Hải Đăng',
                'phone' => '0918889900',
                'email' => 'procurement@bienxanh.test',
                'stage' => 'won',
                'estimated_value' => 95000000,
                'days_ago' => 36,
                'owner' => $admin,
                'notes' => 'Phin lọc cần thay thế định kỳ sau mỗi 30 ngày vận hành.',
                'task_mode' => 'none',
            ],
            [
                'title' => 'Báo giá Băng keo công nghiệp Shurtape & Găng tay chống cắt cấp độ 5',
                'customer_code' => 'DEMO05',
                'company' => 'Công ty TNHH Kính Ánh Dương (demo)',
                'contact_name' => 'Nguyễn Văn Bình',
                'phone' => '0904112233',
                'email' => null,
                'stage' => 'proposal',
                'estimated_value' => 38500000,
                'days_ago' => 42,
                'owner' => $sale1,
                'notes' => 'Báo giá đã quá 30 ngày cần gọi hỏi lại kế hoạch mua sắm quý này.',
                'task_mode' => 'none',
            ],

            // 3. Nhóm ĐÃ LÊN LỊCH HẸN GỌI LẠI (scheduled - có ghi chú lần gọi trước) - 1 đợt
            [
                'title' => 'Đơn hàng Quần áo phòng sạch chống tĩnh điện & Giày ESD Hans',
                'customer_code' => 'DEMO02',
                'company' => 'Công ty TNHH Linh kiện Bắc Hà (demo)',
                'contact_name' => 'Ngô Thanh Tâm',
                'phone' => '0912000003',
                'email' => 'tam.ngo@bacha.test',
                'stage' => 'won',
                'estimated_value' => 115000000,
                'days_ago' => 19,
                'owner' => $sale1,
                'notes' => 'Khách hàng VIP Hạng A tại KCN Quang Châu.',
                'task_mode' => 'snoozed',
            ],

            // 4. Nhóm CHƯA TỚI HẠN < 15 NGÀY (upcoming) - 1 đợt
            [
                'title' => 'Đơn hàng Dây đai an toàn toàn thân & Mũ bảo hộ công trường',
                'customer_code' => 'DEMO06',
                'company' => 'Công ty CP Xây lắp Đông Á (demo)',
                'contact_name' => 'Phan Đình Khoa',
                'phone' => '0912000007',
                'email' => null,
                'stage' => 'won',
                'estimated_value' => 76000000,
                'days_ago' => 6,
                'owner' => $sale2,
                'notes' => 'Đơn mới giao tuần trước, còn 9 ngày nữa đến mốc 15 ngày.',
                'task_mode' => 'none',
            ],

            // 5. Nhóm ĐÃ NHẮC & LƯU LỊCH SỬ (completed) - 1 đợt
            [
                'title' => 'Đơn hàng Đá cắt, Đá mài & Găng tay da hàn chịu nhiệt Towa',
                'customer_code' => 'DEMO08',
                'company' => 'Công ty TNHH Cơ khí Tân Tiến (demo)',
                'contact_name' => 'Vũ Trọng Nghĩa',
                'phone' => '0977554433',
                'email' => 'nghia.vt@tantien.test',
                'stage' => 'won',
                'estimated_value' => 62000000,
                'days_ago' => 27,
                'owner' => $sale1,
                'notes' => 'Khách tiêu thụ đều đặn đá mài và găng tay hàn hàng tháng.',
                'task_mode' => 'completed',
            ],
        ];

        foreach ($demoDeals as $item) {
            if (Lead::query()->where('title', $item['title'])->exists()) {
                continue;
            }

            $dealTimestamp = now()->subDays($item['days_ago']);
            $customerId = $customers[$item['customer_code']] ?? null;

            $opp = new Lead([
                'customer_id' => $customerId,
                'title' => $item['title'],
                'company' => $item['company'],
                'contact_name' => $item['contact_name'],
                'phone' => $item['phone'],
                'email' => $item['email'],
                'stage' => $item['stage'],
                'estimated_value' => $item['estimated_value'],
                'probability' => Lead::STAGE_PROBABILITIES[$item['stage']] ?? 100,
                'expected_close_date' => $dealTimestamp->format('Y-m-d'),
                'notes' => $item['notes'],
            ]);
            $opp->owner_id = $item['owner']->id;
            $opp->kind = 'opportunity';
            $opp->converted_at = $dealTimestamp;
            $opp->created_at = $dealTimestamp;
            $opp->updated_at = $dealTimestamp;
            $opp->timestamps = false;
            $opp->save();

            // Cập nhật mốc thời gian trong AuditLog về đúng số ngày trước
            AuditLog::query()
                ->where('auditable_id', $opp->id)
                ->whereIn('auditable_type', AuditLog::typesFor(Lead::class))
                ->update(['created_at' => $dealTimestamp]);

            if ($item['task_mode'] === 'snoozed') {
                // Tạo 1 task lần gọi trước đã completed + 1 task hẹn gọi lại sau 7 ngày
                $prevTask = Task::create([
                    'title' => "Nhắc mua lại (15-30 ngày): {$item['company']}",
                    'description' => "Liên hệ kiểm tra tồn kho đợt 1 cho {$item['title']}",
                    'type' => 'follow_up',
                    'priority' => 'high',
                    'status' => 'pending',
                    'assignee_id' => $item['owner']->id,
                    'created_by' => $item['owner']->id,
                    'customer_id' => $customerId,
                    'lead_id' => $opp->id,
                    'due_at' => now()->subDays(2),
                    'version' => 0,
                ]);
                $prevTask->status = 'completed';
                $prevTask->completed_at = now()->subDay();
                $prevTask->completed_by = $item['owner']->id;
                $prevTask->result = 'Chị Tâm báo kho phòng sạch vẫn còn đủ dùng khoảng 1 tuần, hẹn tuần sau gọi lại báo giá.';
                $prevTask->change_note = $prevTask->result;
                $prevTask->version = 1;
                $prevTask->save();

                Task::create([
                    'title' => "Hẹn gọi lại nhắc mua (+7 ngày): {$item['company']}",
                    'description' => 'Tự động đặt lịch hẹn gọi lại sau 7 ngày theo yêu cầu của chị Tâm.',
                    'type' => 'follow_up',
                    'priority' => 'high',
                    'status' => 'pending',
                    'assignee_id' => $item['owner']->id,
                    'created_by' => $item['owner']->id,
                    'customer_id' => $customerId,
                    'lead_id' => $opp->id,
                    'due_at' => now()->addDays(6)->setTime(9, 0),
                    'version' => 0,
                ]);
            } elseif ($item['task_mode'] === 'completed') {
                $doneTask = Task::create([
                    'title' => "Nhắc mua lại (15-30 ngày): {$item['company']}",
                    'description' => "Chăm sóc nhắc mua lại từ đợt {$item['title']}",
                    'type' => 'follow_up',
                    'priority' => 'high',
                    'status' => 'pending',
                    'assignee_id' => $item['owner']->id,
                    'created_by' => $item['owner']->id,
                    'customer_id' => $customerId,
                    'lead_id' => $opp->id,
                    'due_at' => now()->subDay(),
                    'version' => 0,
                ]);
                $doneTask->status = 'completed';
                $doneTask->completed_at = now()->subHours(5);
                $doneTask->completed_by = $item['owner']->id;
                $doneTask->result = 'Đã liên hệ anh Nghĩa, khách đồng ý đặt tiếp 500 đôi găng tay hàn Towa và 20 thùng đá cắt trong tuần này.';
                $doneTask->change_note = $doneTask->result;
                $doneTask->version = 1;
                $doneTask->save();
            }
        }
    }
}
