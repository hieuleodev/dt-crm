<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\Broadcast\SendBroadcastCampaign;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadcastController extends Controller
{
    /**
     * Create and queue (or schedule) a multi-article Rich Media Broadcast campaign from Repurchase Reminders.
     */
    public function store(Request $request, SendBroadcastCampaign $sender): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', Rule::in(array_keys(BroadcastCampaign::PURPOSES))],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(array_keys(BroadcastCampaign::CHANNELS))],
            'opportunity_ids' => ['required', 'array', 'min:1', 'max:200'],
            'opportunity_ids.*' => ['required', 'integer'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'articles' => ['required', 'array', 'min:1', 'max:5'],
            'articles.*.title' => ['required', 'string', 'max:200'],
            'articles.*.summary' => ['nullable', 'string', 'max:1000'],
            'articles.*.cover_image_url' => ['nullable', 'url', 'max:500'],
            'articles.*.cta_label' => ['nullable', 'string', 'max:80'],
            'articles.*.cta_url' => ['nullable', 'url', 'max:500'],
        ], [
            'name.required' => 'Vui lòng nhập tên chiến dịch Broadcast.',
            'channels.required' => 'Vui lòng chọn ít nhất 1 kênh gửi (Zalo OA hoặc Email).',
            'opportunity_ids.required' => 'Vui lòng chọn ít nhất 1 khách hàng / đợt giao dịch.',
            'scheduled_at.after' => 'Thời điểm hẹn lịch gửi phải ở tương lai.',
            'articles.required' => 'Vui lòng soạn ít nhất 1 bài viết cho tin nhắn Broadcast.',
            'articles.*.title.required' => 'Mỗi bài viết cần có tiêu đề.',
            'articles.*.cover_image_url.url' => 'Đường dẫn ảnh bìa phải là URL hợp lệ (http/https).',
            'articles.*.cta_url.url' => 'Đường dẫn nút CTA phải là URL hợp lệ (http/https).',
        ]);

        $opportunities = Lead::query()
            ->visibleTo($user)
            ->where('kind', 'opportunity')
            ->whereIn('id', $validated['opportunity_ids'])
            ->with([
                'customer' => fn ($query) => $query->with([
                    'contacts:id,customer_id,full_name,email,zalo,zalo_user_id,is_primary,left_at,marketing_opt_out',
                ]),
            ])
            ->get();

        if ($opportunities->isEmpty()) {
            return back()->with('error', 'Không tìm thấy đợt giao dịch hợp lệ thuộc phạm vi quản lý của bạn.');
        }

        $campaign = $sender->createCampaign($user, $opportunities, [
            'name' => $validated['name'],
            'purpose' => $validated['purpose'],
            'channels' => $validated['channels'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'articles' => $validated['articles'],
        ]);

        if ($campaign->status === 'scheduled' && $campaign->scheduled_at) {
            $localTime = CarbonImmutable::parse($campaign->scheduled_at)
                ->setTimezone('Asia/Ho_Chi_Minh')
                ->format('H:i d/m/Y');

            return back()->with(
                'success',
                "Đã hẹn lịch gửi chiến dịch \"{$campaign->name}\" vào lúc {$localTime} ({$campaign->total_recipients} lượt nhận).",
            );
        }

        DispatchBroadcastCampaignJob::dispatch($campaign->id);

        $campaign->refresh();

        if ($campaign->status === 'queued') {
            return back()->with(
                'success',
                "Đã đưa chiến dịch \"{$campaign->name}\" ({$campaign->total_recipients} lượt nhận) vào hàng đợi Queue để gửi.",
            );
        }

        $summaryParts = [];
        if ($campaign->sent_count > 0) {
            $summaryParts[] = "{$campaign->sent_count} lượt gửi thành công";
        }
        if ($campaign->failed_count > 0) {
            $summaryParts[] = "{$campaign->failed_count} lượt thất bại";
        }
        if ($campaign->skipped_count > 0) {
            $summaryParts[] = "{$campaign->skipped_count} lượt bỏ qua (thiếu Email/Zalo OA ID hoặc từ chối nhận tin)";
        }

        $detail = $summaryParts !== [] ? implode(', ', $summaryParts) : 'không có lượt gửi nào';
        $flashKey = $campaign->sent_count > 0 ? 'success' : 'error';

        return back()->with($flashKey, "Chiến dịch \"{$campaign->name}\": {$detail}.");
    }
}
