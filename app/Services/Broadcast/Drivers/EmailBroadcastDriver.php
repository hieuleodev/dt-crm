<?php

namespace App\Services\Broadcast\Drivers;

use App\Mail\RichMediaBroadcastMail;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailBroadcastDriver implements BroadcastDriverInterface
{
    /**
     * @return array{ok: bool, message_id: ?string, error: ?string}
     */
    public function send(BroadcastCampaign $campaign, BroadcastRecipient $recipient): array
    {
        if (! $recipient->recipient_address) {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'Thiếu địa chỉ email người nhận.',
            ];
        }

        try {
            $sentMessage = Mail::to($recipient->recipient_address, $recipient->recipient_name)
                ->send(new RichMediaBroadcastMail($campaign, $recipient));

            return [
                'ok' => true,
                'message_id' => $sentMessage?->getMessageId() ?? ('mail-'.uniqid()),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'Lỗi gửi Email: '.$e->getMessage(),
            ];
        }
    }
}
