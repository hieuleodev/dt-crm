<?php

namespace App\Http\Controllers;

use App\Enums\Customer\LifecycleStage;
use App\Models\AuditLog;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Models\ZaloOaConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RepurchaseReminderController extends Controller
{
    /**
     * Build the shared notification summary for the top header bell & sidebar badge.
     *
     * @return array{due_count: int, overdue_count: int, scheduled_due_count: int, total_count: int, items: list<array<string, mixed>>}
     */
    public static function notificationSummary(User $user): array
    {
        if (! $user->is_active) {
            return [
                'due_count' => 0,
                'overdue_count' => 0,
                'scheduled_due_count' => 0,
                'total_count' => 0,
                'items' => [],
            ];
        }

        $opportunities = Lead::query()
            ->visibleTo($user)
            ->where('kind', 'opportunity')
            ->whereIn('stage', ['won', 'proposal'])
            ->select(['id', 'title', 'company', 'customer_id', 'stage', 'converted_at', 'created_at'])
            ->with([
                'customer:id,code,name',
                'tasks' => fn ($query) => $query
                    ->select(['id', 'lead_id', 'status', 'due_at'])
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('id')
            ->get();

        if ($opportunities->isEmpty()) {
            return [
                'due_count' => 0,
                'overdue_count' => 0,
                'scheduled_due_count' => 0,
                'total_count' => 0,
                'items' => [],
            ];
        }

        $stageTimestamps = self::resolveStageTimestampsStatic($opportunities->pluck('id')->all());
        $now = CarbonImmutable::now('Asia/Ho_Chi_Minh');
        $today = $now->startOfDay();

        $dueCount = 0;
        $overdueCount = 0;
        $scheduledDueCount = 0;
        $actionableItems = [];

        foreach ($opportunities as $opp) {
            $rawTimestamp = $stageTimestamps[$opp->id][$opp->stage]
                ?? $opp->converted_at
                ?? $opp->created_at
                ?? now();

            $dealAt = CarbonImmutable::parse($rawTimestamp)->setTimezone('Asia/Ho_Chi_Minh');
            $daysElapsed = max(0, (int) $dealAt->startOfDay()->diffInDays($today, false));

            $openTask = $opp->tasks->first(fn (Task $t) => in_array($t->status, Task::OPEN_STATUSES, true));
            $completedTask = $opp->tasks->first(fn (Task $t) => $t->status === 'completed');

            $status = null;

            if ($openTask) {
                if ($openTask->due_at && $openTask->due_at->lessThanOrEqualTo($now->utc())) {
                    $status = 'scheduled_due';
                    $scheduledDueCount++;
                }
            } elseif (! $completedTask) {
                if ($daysElapsed >= 15 && $daysElapsed <= 30) {
                    $status = 'due';
                    $dueCount++;
                } elseif ($daysElapsed > 30) {
                    $status = 'overdue';
                    $overdueCount++;
                }
            }

            if ($status !== null) {
                $actionableItems[] = [
                    'id' => $opp->id,
                    'title' => $opp->title,
                    'company' => $opp->customer?->name ?? $opp->company ?? 'Chưa rõ doanh nghiệp',
                    'stage' => $opp->stage,
                    'stage_label' => $opp->stage === 'won' ? 'Mua bán thành công' : 'Đợt báo giá',
                    'days_elapsed' => $daysElapsed,
                    'status' => $status,
                ];
            }
        }

        usort($actionableItems, fn (array $a, array $b) => $b['days_elapsed'] <=> $a['days_elapsed']);

        return [
            'due_count' => $dueCount,
            'overdue_count' => $overdueCount,
            'scheduled_due_count' => $scheduledDueCount,
            'total_count' => $dueCount + $overdueCount + $scheduledDueCount,
            'items' => array_slice($actionableItems, 0, 6),
        ];
    }

    /**
     * Display the repurchase reminders dashboard (15-30 days cycle from proposals & won deals).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'stage' => ['nullable', 'string', Rule::in(['all', 'won', 'proposal'])],
            'bucket' => ['nullable', 'string', Rule::in(['all', 'due', 'overdue', 'upcoming', 'scheduled', 'completed'])],
        ]);

        $q = trim((string) ($filters['q'] ?? ''));
        $stageFilter = $filters['stage'] ?? 'all';
        $bucketFilter = $filters['bucket'] ?? 'all';

        $opportunities = Lead::query()
            ->visibleTo($user)
            ->where('kind', 'opportunity')
            ->whereIn('stage', ['won', 'proposal'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->whereLike('title', "%{$q}%")
                        ->orWhereLike('company', "%{$q}%")
                        ->orWhereLike('contact_name', "%{$q}%")
                        ->orWhereLike('phone', "%{$q}%")
                        ->orWhereLike('email', "%{$q}%");
                });
            })
            ->with([
                'owner:id,name',
                'customer' => fn ($query) => $query
                    ->select(['id', 'code', 'name', 'phone', 'email', 'lifecycle_stage'])
                    ->with('contacts:id,customer_id,full_name,email,zalo,zalo_user_id,is_primary,left_at,marketing_opt_out'),
                'tasks' => fn ($query) => $query
                    ->with('assignee:id,name')
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $stageTimestamps = self::resolveStageTimestampsStatic($opportunities->pluck('id')->all());
        $latestBroadcastByLead = BroadcastRecipient::query()
            ->whereIn('lead_id', $opportunities->pluck('id')->all())
            ->where('status', 'sent')
            ->with('campaign:id,name,purpose')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get()
            ->unique('lead_id')
            ->keyBy('lead_id');
        $now = CarbonImmutable::now('Asia/Ho_Chi_Minh');
        $today = $now->startOfDay();

        $allItems = $opportunities->map(function (Lead $opp) use ($stageTimestamps, $latestBroadcastByLead, $today): array {
            $rawTimestamp = $stageTimestamps[$opp->id][$opp->stage]
                ?? $opp->converted_at
                ?? $opp->created_at
                ?? now();

            $dealAt = CarbonImmutable::parse($rawTimestamp)->setTimezone('Asia/Ho_Chi_Minh');
            $daysElapsed = max(0, (int) $dealAt->startOfDay()->diffInDays($today, false));

            $openTask = $opp->tasks->first(fn (Task $t) => in_array($t->status, Task::OPEN_STATUSES, true));
            $completedTask = $opp->tasks->first(fn (Task $t) => $t->status === 'completed');

            if ($openTask) {
                $cycleStatus = 'scheduled';
            } elseif ($completedTask) {
                $cycleStatus = 'completed';
            } elseif ($daysElapsed >= 15 && $daysElapsed <= 30) {
                $cycleStatus = 'due';
            } elseif ($daysElapsed > 30) {
                $cycleStatus = 'overdue';
            } else {
                $cycleStatus = 'upcoming';
            }

            $activeContacts = ($opp->customer?->contacts ?? collect())
                ->filter(fn (Contact $c) => $c->left_at === null)
                ->sortByDesc('is_primary');

            /** @var Contact|null $emailContact */
            $emailContact = $activeContacts->first(fn (Contact $c) => ! empty($c->email));
            /** @var Contact|null $zaloContact */
            $zaloContact = $activeContacts->first(fn (Contact $c) => ! empty($c->zalo_user_id));

            $resolvedEmail = $emailContact?->email ?: ($opp->email ?: $opp->customer?->email);
            $resolvedZaloUserId = $zaloContact?->zalo_user_id;
            $emailOptedOut = (bool) ($emailContact?->marketing_opt_out ?? false);
            $zaloOptedOut = (bool) ($zaloContact?->marketing_opt_out ?? false);

            /** @var BroadcastRecipient|null $lastBroadcast */
            $lastBroadcast = $latestBroadcastByLead->get($opp->id);

            return [
                'id' => $opp->id,
                'title' => $opp->title,
                'stage' => $opp->stage,
                'stage_label' => $opp->stage === 'won' ? 'Mua bán thành công' : 'Đợt báo giá',
                'company' => $opp->customer?->name ?? $opp->company ?? 'Chưa rõ doanh nghiệp',
                'customer_id' => $opp->customer_id,
                'customer_code' => $opp->customer?->code,
                'contact_name' => $opp->contact_name,
                'phone' => $opp->phone ?: $opp->customer?->phone,
                'email' => $resolvedEmail,
                'zalo_user_id' => $resolvedZaloUserId,
                'can_email_broadcast' => ! empty($resolvedEmail) && ! $emailOptedOut,
                'can_zalo_broadcast' => ! empty($resolvedZaloUserId) && ! $zaloOptedOut,
                'last_broadcast' => $lastBroadcast ? [
                    'campaign_name' => $lastBroadcast->campaign?->name ?? 'Chiến dịch Broadcast',
                    'channel' => $lastBroadcast->channel,
                    'sent_at' => $lastBroadcast->sent_at?->toIso8601String(),
                ] : null,
                'estimated_value' => (string) ($opp->estimated_value ?? 0),
                'owner_id' => $opp->owner_id,
                'owner_name' => $opp->owner?->name ?? 'Chưa phân công',
                'deal_at' => $dealAt->toIso8601String(),
                'days_elapsed' => $daysElapsed,
                'window_start_at' => $dealAt->addDays(15)->format('d/m/Y'),
                'window_end_at' => $dealAt->addDays(30)->format('d/m/Y'),
                'days_until_window' => max(0, 15 - $daysElapsed),
                'cycle_status' => $cycleStatus,
                'is_in_golden_window' => $daysElapsed >= 15 && $daysElapsed <= 30,
                'open_task' => $openTask ? [
                    'id' => $openTask->id,
                    'title' => $openTask->title,
                    'type' => $openTask->type,
                    'type_label' => Task::TYPES[$openTask->type] ?? $openTask->type,
                    'status' => $openTask->status,
                    'status_label' => Task::STATUSES[$openTask->status] ?? $openTask->status,
                    'due_at' => $openTask->due_at?->toIso8601String(),
                    'assignee' => $openTask->assignee?->name,
                ] : null,
                'completed_task' => $completedTask ? [
                    'id' => $completedTask->id,
                    'title' => $completedTask->title,
                    'result' => $completedTask->result,
                    'completed_at' => ($completedTask->completed_at ?? $completedTask->updated_at)?->toIso8601String(),
                    'assignee' => $completedTask->assignee?->name,
                ] : null,
                'notes' => $opp->notes,
            ];
        });

        $summary = [
            'total' => $allItems->count(),
            'due_15_30' => $allItems->where('cycle_status', 'due')->count(),
            'overdue_30' => $allItems->where('cycle_status', 'overdue')->count(),
            'upcoming_under_15' => $allItems->where('cycle_status', 'upcoming')->count(),
            'scheduled' => $allItems->where('cycle_status', 'scheduled')->count(),
            'completed' => $allItems->where('cycle_status', 'completed')->count(),
            'won_count' => $allItems->where('stage', 'won')->count(),
            'proposal_count' => $allItems->where('stage', 'proposal')->count(),
        ];

        $filteredItems = $allItems
            ->when($stageFilter !== 'all', fn ($col) => $col->where('stage', $stageFilter))
            ->when($bucketFilter !== 'all', fn ($col) => $col->where('cycle_status', $bucketFilter))
            ->sortBy([
                fn (array $a, array $b) => $this->bucketPriority($a['cycle_status']) <=> $this->bucketPriority($b['cycle_status']),
                fn (array $a, array $b) => $b['days_elapsed'] <=> $a['days_elapsed'],
            ])
            ->values()
            ->all();

        $assignees = $user->isManager()
            ? User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : [$user->only('id', 'name')];

        $recentBroadcasts = BroadcastCampaign::query()
            ->when(! $user->isManager(), fn ($query) => $query->where('created_by', $user->id))
            ->with([
                'creator:id,name',
                'articles:id,campaign_id,sort_order,title',
                'recipients:id,campaign_id,channel,recipient_name,recipient_address,status,error_message,sent_at',
            ])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (BroadcastCampaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'purpose' => $campaign->purpose,
                'purpose_label' => BroadcastCampaign::PURPOSES[$campaign->purpose] ?? $campaign->purpose,
                'channels' => $campaign->channels ?? [],
                'status' => $campaign->status,
                'status_label' => BroadcastCampaign::STATUSES[$campaign->status] ?? $campaign->status,
                'creator_name' => $campaign->creator?->name ?? 'Hệ thống',
                'articles_count' => $campaign->articles->count(),
                'first_article_title' => $campaign->articles->first()?->title,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'skipped_count' => $campaign->skipped_count,
                'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                'sent_at' => $campaign->sent_at?->toIso8601String(),
                'recipients' => $campaign->recipients->map(fn (BroadcastRecipient $r): array => [
                    'id' => $r->id,
                    'channel' => $r->channel,
                    'recipient_name' => $r->recipient_name,
                    'recipient_address' => $r->recipient_address,
                    'status' => $r->status,
                    'status_label' => BroadcastRecipient::STATUSES[$r->status] ?? $r->status,
                    'error_message' => $r->error_message,
                    'sent_at' => $r->sent_at?->toIso8601String(),
                ])->all(),
            ])
            ->all();

        $zaloConnection = ZaloOaConnection::current();

        return Inertia::render('RepurchaseReminders/Index', [
            'reminders' => $filteredItems,
            'summary' => $summary,
            'filters' => [
                'q' => $q,
                'stage' => $stageFilter,
                'bucket' => $bucketFilter,
            ],
            'assignees' => $assignees,
            'taskOptions' => [
                'types' => Task::TYPES,
                'priorities' => Task::PRIORITIES,
            ],
            'broadcastConfig' => [
                'purposes' => BroadcastCampaign::PURPOSES,
                'channels' => BroadcastCampaign::CHANNELS,
                'zalo_configured' => $zaloConnection !== null || ! empty(config('services.zalo_oa.access_token')),
                'zalo_sandbox' => (bool) config('services.zalo_oa.sandbox', false),
                'zalo_oauth_connected' => $zaloConnection !== null,
                'zalo_oa_name' => $zaloConnection?->oa_name ?: $zaloConnection?->oa_id,
                'zalo_token_expires_at' => $zaloConnection?->expires_at?->toIso8601String(),
                'zalo_oauth_install_url' => route('zalo-oa.oauth.install'),
                'zalo_webhook_url' => route('webhooks.zalo-oa'),
            ],
            'recentBroadcasts' => $recentBroadcasts,
        ]);
    }

    /**
     * Schedule a repurchase reminder task for an opportunity.
     */
    public function schedule(Request $request, Lead $opportunity): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
                && $user->is_active
                && $opportunity->kind === 'opportunity'
                && ($user->isManager() || $opportunity->owner_id === $user->id),
            403,
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_keys(Task::TYPES))],
            'priority' => ['required', 'string', Rule::in(array_keys(Task::PRIORITIES))],
            'due_at' => ['required', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $assigneeId = ($user->isManager() && ! empty($validated['assignee_id']))
            ? (int) $validated['assignee_id']
            : ($opportunity->owner_id ?: $user->id);

        Task::create([
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'status' => 'pending',
            'assignee_id' => $assigneeId,
            'created_by' => $user->id,
            'customer_id' => $opportunity->customer_id,
            'lead_id' => $opportunity->id,
            'due_at' => CarbonImmutable::parse($validated['due_at'])->utc(),
            'version' => 0,
        ]);

        return back()->with('success', 'Đã lưu lịch nhắc nhở mua lại và đồng bộ sang mục Nhiệm vụ.');
    }

    /**
     * Snooze / postpone a repurchase reminder by +N days when the customer still has stock.
     */
    public function snooze(Request $request, Lead $opportunity): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
                && $user->is_active
                && $opportunity->kind === 'opportunity'
                && ($user->isManager() || $opportunity->owner_id === $user->id),
            403,
        );

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $days = (int) $validated['days'];
        $note = trim((string) ($validated['note'] ?? ''));
        $nextDueAt = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addDays($days)->setTime(9, 0)->utc();

        $openTask = Task::query()
            ->where('lead_id', $opportunity->id)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if ($openTask) {
            $openTask->due_at = $nextDueAt;
            if ($note !== '') {
                $openTask->description = trim(($openTask->description ? $openTask->description."\n" : '')."[Hẹn lại +{$days} ngày]: {$note}");
            }
            $openTask->version++;
            $openTask->save();
        } else {
            $companyName = $opportunity->customer?->name ?? $opportunity->company ?? $opportunity->title;
            Task::create([
                'title' => "Hẹn gọi lại nhắc mua (+{$days} ngày): {$companyName}",
                'description' => $note !== '' ? $note : "Khách hàng chưa hết vật tư, hẹn gọi lại sau {$days} ngày cho đợt \"{$opportunity->title}\".",
                'type' => 'follow_up',
                'priority' => 'normal',
                'status' => 'pending',
                'assignee_id' => $opportunity->owner_id ?: $user->id,
                'created_by' => $user->id,
                'customer_id' => $opportunity->customer_id,
                'lead_id' => $opportunity->id,
                'due_at' => $nextDueAt,
                'version' => 0,
            ]);
        }

        return back()->with('success', "Đã dời lịch hẹn nhắc mua lại sang {$days} ngày tới.");
    }

    /**
     * Complete a repurchase reminder and record it directly into the History & Customer Audit Trail.
     */
    public function complete(Request $request, Lead $opportunity): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
                && $user->is_active
                && $opportunity->kind === 'opportunity'
                && ($user->isManager() || $opportunity->owner_id === $user->id),
            403,
        );

        $validated = $request->validate([
            'result' => ['required', 'string', 'max:1000'],
            'create_reorder' => ['nullable', 'boolean'],
            'reorder_value' => ['nullable', 'numeric', 'min:0'],
            'snooze_enabled' => ['nullable', 'boolean'],
            'snooze_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ], [
            'result.required' => 'Vui lòng nhập kết quả liên hệ nhắc mua lại.',
        ]);

        $resultNote = trim($validated['result']);
        $createReorder = (bool) ($validated['create_reorder'] ?? false);
        $snoozeEnabled = (bool) ($validated['snooze_enabled'] ?? false);
        $snoozeDays = $snoozeEnabled ? (int) ($validated['snooze_days'] ?? 7) : 0;

        DB::transaction(function () use ($opportunity, $user, $resultNote, $createReorder, $snoozeDays, $validated) {
            $companyName = $opportunity->customer?->name ?? $opportunity->company ?? $opportunity->title;

            $openTask = Task::query()
                ->where('lead_id', $opportunity->id)
                ->whereIn('status', Task::OPEN_STATUSES)
                ->latest('id')
                ->first();

            if (! $openTask) {
                $openTask = Task::create([
                    'title' => "Nhắc mua lại (15-30 ngày): {$companyName}",
                    'description' => "Chăm sóc nhắc mua lại từ đợt {$opportunity->title}",
                    'type' => 'follow_up',
                    'priority' => 'high',
                    'status' => 'pending',
                    'assignee_id' => $opportunity->owner_id ?: $user->id,
                    'created_by' => $user->id,
                    'customer_id' => $opportunity->customer_id,
                    'lead_id' => $opportunity->id,
                    'due_at' => now('UTC'),
                    'version' => 0,
                ]);
            }

            // Chuyển trạng thái qua update để Auditable ghi nhận vào Lịch sử (/history) và Nhật ký khách hàng
            $openTask->status = 'completed';
            $openTask->completed_at = now('UTC');
            $openTask->completed_by = $user->id;
            $openTask->result = $resultNote;
            $openTask->change_note = $resultNote;
            $openTask->version++;
            $openTask->save();

            if ($opportunity->customer) {
                $customerUpdates = ['last_contacted_at' => now()];
                if ($createReorder) {
                    $customerUpdates['lifecycle_stage'] = LifecycleStage::Repeat;
                }
                $opportunity->customer->update($customerUpdates);
            }

            if ($createReorder) {
                $newOpp = new Lead([
                    'customer_id' => $opportunity->customer_id,
                    'title' => "Đơn mua lại: {$opportunity->title}",
                    'requested_product' => $opportunity->requested_product,
                    'sapo_product_id' => $opportunity->sapo_product_id,
                    'sapo_variant_id' => $opportunity->sapo_variant_id,
                    'requested_quantity' => $opportunity->requested_quantity,
                    'requested_unit_price' => $opportunity->requested_unit_price,
                    'product_lines' => $opportunity->product_lines,
                    'contact_name' => $opportunity->contact_name,
                    'company' => $opportunity->customer?->name ?? $opportunity->company,
                    'email' => $opportunity->email,
                    'phone' => $opportunity->phone,
                    'stage' => 'proposal',
                    'estimated_value' => $validated['reorder_value'] ?? $opportunity->estimated_value,
                    'probability' => Lead::STAGE_PROBABILITIES['proposal'] ?? 30,
                    'expected_close_date' => now()->addDays(15)->format('Y-m-d'),
                    'notes' => "Tạo từ đợt nhắc mua lại #{$opportunity->id}. Ghi chú: {$resultNote}",
                ]);
                $newOpp->owner_id = $opportunity->owner_id ?: $user->id;
                $newOpp->kind = 'opportunity';
                $newOpp->converted_at = now();
                $newOpp->save();
            }

            if ($snoozeDays > 0) {
                Task::create([
                    'title' => "Hẹn gọi lại nhắc mua (+{$snoozeDays} ngày): {$companyName}",
                    'description' => "Tự động đặt lịch hẹn gọi lại sau {$snoozeDays} ngày. Kết quả lần gọi trước: {$resultNote}",
                    'type' => 'follow_up',
                    'priority' => 'high',
                    'status' => 'pending',
                    'assignee_id' => $opportunity->owner_id ?: $user->id,
                    'created_by' => $user->id,
                    'customer_id' => $opportunity->customer_id,
                    'lead_id' => $opportunity->id,
                    'due_at' => CarbonImmutable::now('Asia/Ho_Chi_Minh')->addDays($snoozeDays)->setTime(9, 0)->utc(),
                    'version' => 0,
                ]);
            }
        });

        if ($createReorder) {
            $msg = 'Đã lưu lịch sử nhắc mua lại và tạo Cơ hội báo giá mua lại mới.';
        } elseif ($snoozeDays > 0) {
            $msg = "Đã lưu lịch sử lần gọi hôm nay và tự động hẹn gọi lại sau {$snoozeDays} ngày.";
        } else {
            $msg = 'Đã ghi nhận kết quả nhắc mua lại vào Lịch sử hệ thống.';
        }

        return back()->with('success', $msg);
    }

    /**
     * @param  list<int>  $opportunityIds
     * @return array<int, array<string, string>>
     */
    private static function resolveStageTimestampsStatic(array $opportunityIds): array
    {
        if ($opportunityIds === []) {
            return [];
        }

        $logs = AuditLog::query()
            ->whereIn('auditable_type', AuditLog::typesFor(Lead::class))
            ->whereIn('auditable_id', $opportunityIds)
            ->whereIn('event', ['created', 'updated'])
            ->whereIn('new_values->stage', ['won', 'proposal'])
            ->orderBy('id')
            ->get(['auditable_id', 'new_values', 'created_at']);

        $timestamps = [];
        foreach ($logs as $log) {
            $stage = $log->new_values['stage'] ?? null;
            if (! $stage || ! $log->created_at) {
                continue;
            }

            // Giữ mốc đầu tiên khi vào giai đoạn Báo giá để thao tác chuyển qua/lại không làm reset chu kỳ 15-30 ngày.
            if ($stage === 'proposal' && isset($timestamps[$log->auditable_id][$stage])) {
                continue;
            }

            $timestamps[$log->auditable_id][$stage] = $log->created_at->toIso8601String();
        }

        return $timestamps;
    }

    private function bucketPriority(string $cycleStatus): int
    {
        return match ($cycleStatus) {
            'due' => 1,
            'overdue' => 2,
            'scheduled' => 3,
            'upcoming' => 4,
            'completed' => 5,
            default => 6,
        };
    }
}
