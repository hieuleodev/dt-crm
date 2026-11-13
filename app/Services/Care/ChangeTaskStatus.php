<?php

namespace App\Services\Care;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeTaskStatus
{
    public function handle(int $id, array $data, User $actor): void
    {
        DB::transaction(function () use ($id, $data, $actor) {
            $task = Task::query()
                ->visibleTo($actor)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($task->version !== (int) $data['version']) {
                throw ValidationException::withMessages([
                    'note' => 'Nhiệm vụ đã thay đổi. Hãy đóng hộp thoại và tải lại danh sách.',
                ]);
            }

            $next = $data['status'];
            $note = trim($data['note'] ?? '');

            if ($task->status === $next) {
                return;
            }

            if (! $task->isOpen()) {
                if (! $actor->isManager() || $next !== 'pending') {
                    abort(403);
                }

                if ($note === '') {
                    throw ValidationException::withMessages([
                        'note' => 'Vui lòng ghi lý do mở lại nhiệm vụ.',
                    ]);
                }

                $task->completed_at = null;
                $task->completed_by = null;
                $task->result = null;
            } else {
                // Nhân viên chỉ đổi trạng thái việc đang mở; việc kết thúc phải có ghi chú.
                $allowed = [
                    'pending' => ['in_progress', 'completed', 'cancelled'],
                    'in_progress' => ['pending', 'completed', 'cancelled'],
                ];

                if (! in_array($next, $allowed[$task->status], true)) {
                    throw ValidationException::withMessages([
                        'note' => 'Chuyển trạng thái không hợp lệ.',
                    ]);
                }

                if (
                    in_array($next, ['completed', 'cancelled'], true)
                    && $note === ''
                ) {
                    throw ValidationException::withMessages([
                        'note' => $next === 'completed'
                            ? 'Vui lòng ghi kết quả thực hiện.'
                            : 'Vui lòng ghi lý do hủy.',
                    ]);
                }

                if ($next === 'completed') {
                    $task->completed_at = now('UTC');
                    $task->completed_by = $actor->id;
                    $task->result = $note;
                }

                if ($next === 'cancelled') {
                    $task->result = $note;
                }
            }

            $task->status = $next;
            $task->change_note = $note ?: null;
            $task->version++;
            $task->save();
        });
    }
}
