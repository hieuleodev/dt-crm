<?php

namespace App\Http\Controllers\Care;

use App\Domain\Care\Models\Task;
use App\Domain\Care\Queries\WorkbenchQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkbenchController extends Controller
{
    public function __invoke(
        Request $request,
        WorkbenchQuery $query,
    ): Response {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        $filters = $request->validate([
            'scope' => [
                'nullable',
                Rule::in($user->isManager() ? ['mine', 'all'] : ['mine']),
            ],

            'assignee_id' => [
                'nullable',
                $user->isManager() ? 'integer' : 'prohibited',
                'min:1',
            ],

            'bucket' => [
                'nullable',
                Rule::in(['overdue', 'today', 'upcoming', 'completed']),
            ],

            'q' => ['nullable', 'string', 'max:180'],

            'priority' => [
                'nullable',
                Rule::in(array_keys(Task::PRIORITIES)),
            ],

            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Workbench/Index', [
            ...$query->handle($user, $filters),

            'options' => [
                'types' => Task::TYPES,
                'priorities' => Task::PRIORITIES,
                'statuses' => Task::STATUSES,
            ],

            'assignees' => $user->isManager()
                ? User::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : [$user->only('id', 'name')],
        ]);
    }
}