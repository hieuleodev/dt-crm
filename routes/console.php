<?php

use App\Jobs\DispatchBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\ZaloOaConnection;
use App\Services\Broadcast\ZaloOaTokenService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('broadcast:dispatch-due', function () {
    $dueCampaigns = BroadcastCampaign::query()
        ->where('status', 'scheduled')
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->orderBy('scheduled_at')
        ->get();

    $dispatched = 0;

    foreach ($dueCampaigns as $campaign) {
        $updated = BroadcastCampaign::query()
            ->where('id', $campaign->id)
            ->where('status', 'scheduled')
            ->update(['status' => 'queued']);

        if ($updated > 0) {
            DispatchBroadcastCampaignJob::dispatch($campaign->id);
            $dispatched++;
        }
    }

    $this->info("Đã đẩy {$dispatched} chiến dịch Broadcast đến hạn vào hàng đợi.");
})->purpose('Đẩy các chiến dịch Broadcast đã hẹn lịch đến giờ vào Queue');

Artisan::command('zalo:refresh-token', function (ZaloOaTokenService $tokenService) {
    $connection = ZaloOaConnection::current();

    if (! $connection) {
        $this->info('Chưa có kết nối Zalo OA OAuth để làm mới token.');

        return;
    }

    if (! $connection->isExpiringSoon(120)) {
        $this->info('Zalo OA token vẫn còn hạn, chưa cần làm mới.');

        return;
    }

    $refreshed = $tokenService->refresh($connection);

    if ($refreshed) {
        $this->info("Đã làm mới Zalo OA token thành công (hết hạn mới: {$refreshed->expires_at?->toDateTimeString()}).");
    } else {
        $this->error('Không thể làm mới Zalo OA token. Kiểm tra lại ZALO_OA_APP_ID, ZALO_OA_APP_SECRET hoặc kết nối lại OAuth.');
    }
})->purpose('Tự động làm mới Zalo OA access token trước khi hết hạn');

Schedule::command('broadcast:dispatch-due')->everyMinute()->withoutOverlapping();
Schedule::command('zalo:refresh-token')->hourly()->withoutOverlapping();
