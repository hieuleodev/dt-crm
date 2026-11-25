<?php

namespace App\Http\Controllers\Care;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Services\Care\UpcomingEventsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly UpcomingEventsService $eventsService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        $filters = $request->validate([
            'range' => ['nullable', Rule::in(['7', '14', '30', '90', '365'])],
            'category' => ['nullable', Rule::in(['all', 'company_anniversary', 'holiday'])],
            'q' => ['nullable', 'string', 'max:180'],
        ]);

        $noticeDays = (int) ($filters['range'] ?? 7);
        if ($noticeDays <= 0) {
            $noticeDays = 7;
        }

        $eventsData = $this->eventsService->getUpcomingEvents($user, null, $noticeDays);

        $category = $filters['category'] ?? 'all';
        if ($category !== 'all') {
            $eventsData['events'] = array_values(array_filter(
                $eventsData['events'],
                fn ($e) => $e['category'] === $category
            ));
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $lowerQ = mb_strtolower($q, 'UTF-8');
            $eventsData['events'] = array_values(array_filter(
                $eventsData['events'],
                fn ($e) => str_contains(mb_strtolower($e['title'], 'UTF-8'), $lowerQ)
                    || str_contains(mb_strtolower($e['subtitle'], 'UTF-8'), $lowerQ)
                    || str_contains(mb_strtolower($e['description'], 'UTF-8'), $lowerQ)
            ));
        }

        $assignees = $user->isManager()
            ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : User::query()->where('id', $user->id)->get(['id', 'name']);

        return Inertia::render('Events/Index', [
            'eventsData' => $eventsData,
            'filters' => [
                'range' => (string) $noticeDays,
                'category' => $category,
                'q' => $q,
            ],
            'options' => [
                'types' => Task::TYPES,
                'priorities' => Task::PRIORITIES,
                'statuses' => Task::STATUSES,
            ],
            'assignees' => $assignees,
        ]);
    }
}
