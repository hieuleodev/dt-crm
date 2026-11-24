<?php

namespace App\Services\Broadcast\Drivers;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;

interface BroadcastDriverInterface
{
    /**
     * Send a rich media broadcast to a single recipient.
     *
     * @return array{ok: bool, message_id: ?string, error: ?string}
     */
    public function send(BroadcastCampaign $campaign, BroadcastRecipient $recipient): array;
}
