<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZaloOaWebhookController extends Controller
{
    /**
     * Handle incoming Zalo OA Webhook events (follow, unfollow, user_received_message, user_seen_message).
     */
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $payload = $request->json()->all();

        if (! $this->hasValidSignature($request, $rawBody, $payload)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 401);
        }

        $eventName = (string) ($payload['event_name'] ?? '');

        match ($eventName) {
            'follow' => $this->handleFollow($payload),
            'unfollow' => $this->handleUnfollow($payload),
            'user_received_message' => $this->handleMessageStatus($payload, 'delivered'),
            'user_seen_message' => $this->handleMessageStatus($payload, 'seen'),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify Zalo OA webhook signature: mac=sha256(appId + data + timestamp + oaSecretKey).
     *
     * @param  array<string, mixed>  $payload
     */
    private function hasValidSignature(Request $request, string $rawBody, array $payload): bool
    {
        $secret = trim((string) config('services.zalo_oa.webhook_secret', ''));

        if ($secret === '') {
            return false;
        }

        $header = (string) $request->header('X-ZEvent-Signature', '');
        if (! str_starts_with($header, 'mac=')) {
            return false;
        }

        $providedMac = substr($header, 4);
        $appId = (string) ($payload['app_id'] ?? config('services.zalo_oa.app_id', ''));
        $timestamp = (string) ($payload['timestamp'] ?? '');

        $expectedMac = hash('sha256', $appId.$rawBody.$timestamp.$secret);

        return hash_equals($expectedMac, $providedMac);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleFollow(array $payload): void
    {
        $zaloUserId = (string) data_get($payload, 'follower.id', '');
        if ($zaloUserId === '') {
            return;
        }

        $existing = Contact::query()->where('zalo_user_id', $zaloUserId)->first();
        if ($existing) {
            $existing->update(['zalo_followed_at' => now()]);

            return;
        }

        $phone = Customer::normalizePhone(data_get($payload, 'follower.phone'));
        if ($phone) {
            Contact::query()
                ->where(function ($query) use ($phone) {
                    $query->where('phone', $phone)->orWhere('zalo', $phone);
                })
                ->update([
                    'zalo_user_id' => $zaloUserId,
                    'zalo_followed_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleUnfollow(array $payload): void
    {
        $zaloUserId = (string) data_get($payload, 'follower.id', '');
        if ($zaloUserId === '') {
            return;
        }

        Contact::query()
            ->where('zalo_user_id', $zaloUserId)
            ->update(['zalo_followed_at' => null]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleMessageStatus(array $payload, string $targetStatus): void
    {
        $messageId = (string) (data_get($payload, 'message.msg_id') ?? data_get($payload, 'message.msg_ids.0', ''));
        if ($messageId === '') {
            return;
        }

        $recipient = BroadcastRecipient::query()
            ->where('channel', 'zalo')
            ->where('provider_message_id', $messageId)
            ->first();

        if (! $recipient) {
            return;
        }

        if ($targetStatus === 'delivered' && $recipient->status !== 'seen') {
            $recipient->update([
                'status' => 'delivered',
                'delivered_at' => $recipient->delivered_at ?? now(),
            ]);
        } elseif ($targetStatus === 'seen') {
            $recipient->update([
                'status' => 'seen',
                'delivered_at' => $recipient->delivered_at ?? now(),
                'seen_at' => now(),
            ]);
        }
    }
}
