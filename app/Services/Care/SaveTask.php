<?php

namespace App\Services\Care;

use App\Enums\Staff\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTask
{
    public function handle(
        array $data,
        User $actor,
        ?int $taskId = null,
    ): Task {
        return DB::transaction(function () use ($data, $actor, $taskId) {
            $task = $taskId
                ? Task::query()
                    ->visibleTo($actor)
                    ->lockForUpdate()
                    ->findOrFail($taskId)
                : new Task;

            if ($task->exists) {
                if (! $task->isOpen()) {
                    throw ValidationException::withMessages([
                        'title' => 'Nhiệm vụ đã kết thúc. Manager cần mở lại trước khi chỉnh sửa.',
                    ]);
                }

                if ($task->version !== (int) $data['version']) {
                    throw ValidationException::withMessages([
                        'title' => 'Nhiệm vụ đã được cập nhật. Hãy đóng form và tải lại danh sách.',
                    ]);
                }
            }

            // Kiểm tra lại người nhận tại lúc lưu để áp dụng quy tắc Sales cho mọi luồng tạo nhiệm vụ.
            $assignee = User::query()->find($data['assignee_id']);

            if (! $assignee || ! $assignee->is_active || $assignee->role !== UserRole::Sales) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'Chỉ có thể giao việc cho nhân viên kinh doanh đang hoạt động.',
                ]);
            }

            if (! $actor->isManager() && $assignee->id !== $actor->id) {
                abort(403);
            }

            $customerId = null;
            $leadId = null;

            if (filled($data['reference'] ?? null)) {
                [$kind, $id] = explode(':', $data['reference'], 2);

                $query = $kind === 'customer'
                    ? Customer::query()
                    : Lead::query();

                $target = $query
                    ->visibleTo($actor)
                    ->lockForUpdate()
                    ->find($id);

                if (! $target) {
                    throw ValidationException::withMessages([
                        'reference' => 'Hồ sơ không còn tồn tại hoặc bạn không được phép truy cập.',
                    ]);
                }

                // Dùng cùng phạm vi quyền với danh sách hồ sơ để người nhận mở được hồ sơ từ nhiệm vụ.
                if (! $target->newQuery()->visibleTo($assignee)->whereKey($target->id)->exists()) {
                    throw ValidationException::withMessages([
                        'assignee_id' => $target instanceof Lead && $target->kind === 'lead'
                            ? 'Hãy chọn người phụ trách hiển thị trên Lead. Nếu Lead thuộc khách hàng đã có hồ sơ, hãy liên kết hồ sơ đó trước khi giao việc.'
                            : 'Người thực hiện phải có quyền xem hồ sơ liên quan.',
                    ]);
                }

                if ($kind === 'customer') {
                    $customerId = $target->id;
                } else {
                    $leadId = $target->id;
                }
            }
            if (
                $task->exists
                && $task->customerNote()->exists()
                && (
                    $task->customer_id !== $customerId
                    || $leadId !== null
                )
            ) {
                throw ValidationException::withMessages([
                    'reference' => 'Nhiệm vụ này gắn với ghi chú khách hàng, không thể đổi hồ sơ liên quan.',
                ]);
            }
            $task->fill([
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'priority' => $data['priority'],
                'assignee_id' => $assignee->id,
                'customer_id' => $customerId,
                'lead_id' => $leadId,
                'due_at' => CarbonImmutable::parse($data['due_at'])->utc(),
            ]);

            if (! $task->exists) {
                $task->created_by = $actor->id;
                $task->status = 'pending';
                $task->version = 0;
            } else {
                $task->version++;
            }

            $task->save();

            return $task;
        });
    }
}
