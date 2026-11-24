<?php

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Services\Broadcast\SendBroadcastCampaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DispatchBroadcastCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(SendBroadcastCampaign $sender): void
    {
        $campaign = BroadcastCampaign::query()->find($this->campaignId);

        if (! $campaign) {
            return;
        }

        $sender->dispatchCampaign($campaign);
    }

    public function failed(Throwable $exception): void
    {
        BroadcastCampaign::query()
            ->where('id', $this->campaignId)
            ->whereIn('status', ['queued', 'sending'])
            ->update(['status' => 'failed']);
    }
}
