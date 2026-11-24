<?php

namespace App\Services\Broadcast;

use App\Models\AuditLog;
use App\Models\BroadcastArticle;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\Broadcast\Drivers\BroadcastDriverInterface;
use App\Services\Broadcast\Drivers\EmailBroadcastDriver;
use App\Services\Broadcast\Drivers\ZaloOaBroadcastDriver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendBroadcastCampaign
{
    public function __construct(
        private EmailBroadcastDriver $emailDriver,
        private ZaloOaBroadcastDriver $zaloDriver,
    ) {}

    /**
     * Create and immediately dispatch a multi-article broadcast campaign.
     *
     * @param  array{
     *     name: string,
     *     purpose: string,
     *     channels: list<string>,
     *     scheduled_at?: ?string,
     *     articles: list<array{
     *         title: string,
     *         summary?: ?string,
     *         cover_image_url?: ?string,
     *         cta_label?: ?string,
     *         cta_url?: ?string
     *     }>
     * }  $payload
     * @param  Collection<int, Lead>  $opportunities
     */
    public function handle(User $actor, Collection $opportunities, array $payload): BroadcastCampaign
    {
        $campaign = $this->createCampaign($actor, $opportunities, $payload);

        return $this->dispatchCampaign($campaign);
    }

    /**
     * Persist a broadcast campaign, its articles, and resolved recipients (queued or scheduled).
     *
     * @param  array{
     *     name: string,
     *     purpose: string,
     *     channels: list<string>,
     *     scheduled_at?: ?string,
     *     articles: list<array{
     *         title: string,
     *         summary?: ?string,
     *         cover_image_url?: ?string,
     *         cta_label?: ?string,
     *         cta_url?: ?string
     *     }>
     * }  $payload
     * @param  Collection<int, Lead>  $opportunities
     */
    public function createCampaign(User $actor, Collection $opportunities, array $payload): BroadcastCampaign
    {
        $channels = array_values(array_unique($payload['channels']));
        $scheduledAt = ! empty($payload['scheduled_at'])
            ? CarbonImmutable::parse($payload['scheduled_at'])->utc()
            : null;
        $initialStatus = $scheduledAt && $scheduledAt->isFuture() ? 'scheduled' : 'queued';

        return DB::transaction(function () use ($actor, $opportunities, $payload, $channels, $scheduledAt, $initialStatus): BroadcastCampaign {
            $campaign = BroadcastCampaign::create([
                'name' => trim($payload['name']),
                'purpose' => $payload['purpose'],
                'channels' => $channels,
                'status' => $initialStatus,
                'scheduled_at' => $scheduledAt,
                'created_by' => $actor->id,
                'total_recipients' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
            ]);

            foreach (array_values($payload['articles']) as $index => $article) {
                BroadcastArticle::create([
                    'campaign_id' => $campaign->id,
                    'sort_order' => $index,
                    'title' => trim($article['title']),
                    'summary' => isset($article['summary']) && trim((string) $article['summary']) !== ''
                        ? trim((string) $article['summary'])
                        : null,
                    'cover_image_url' => isset($article['cover_image_url']) && trim((string) $article['cover_image_url']) !== ''
                        ? trim((string) $article['cover_image_url'])
                        : null,
                    'cta_label' => isset($article['cta_label']) && trim((string) $article['cta_label']) !== ''
                        ? trim((string) $article['cta_label'])
                        : null,
                    'cta_url' => isset($article['cta_url']) && trim((string) $article['cta_url']) !== ''
                        ? trim((string) $article['cta_url'])
                        : null,
                ]);
            }

            $total = 0;
            $skipped = 0;

            foreach ($opportunities as $opp) {
                foreach ($channels as $channel) {
                    $resolved = $this->resolveRecipient($opp, $channel);
                    $isSkipped = $resolved['skip_reason'] !== null;

                    BroadcastRecipient::create([
                        'campaign_id' => $campaign->id,
                        'lead_id' => $opp->id,
                        'customer_id' => $opp->customer_id,
                        'contact_id' => $resolved['contact_id'],
                        'channel' => $channel,
                        'recipient_name' => $resolved['name'],
                        'recipient_address' => $resolved['address'],
                        'status' => $isSkipped ? 'skipped' : 'pending',
                        'error_message' => $resolved['skip_reason'],
                    ]);

                    $total++;
                    if ($isSkipped) {
                        $skipped++;
                    }
                }
            }

            $campaign->update([
                'total_recipients' => $total,
                'skipped_count' => $skipped,
            ]);

            return $campaign;
        });
    }

    /**
     * Dispatch all pending recipients of a persisted campaign via their channel drivers.
     */
    public function dispatchCampaign(BroadcastCampaign $campaign): BroadcastCampaign
    {
        $campaign->update(['status' => 'sending']);
        $campaign->load(['articles', 'recipients.customer']);

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $touchedCustomerIds = [];

        foreach ($campaign->recipients as $recipient) {
            if ($recipient->status === 'skipped') {
                $skippedCount++;

                continue;
            }

            if (in_array($recipient->status, ['sent', 'delivered', 'seen'], true)) {
                $sentCount++;

                continue;
            }

            $driver = $this->driverFor($recipient->channel);
            $result = $driver->send($campaign, $recipient);

            if ($result['ok']) {
                $recipient->update([
                    'status' => 'sent',
                    'provider_message_id' => $result['message_id'],
                    'error_message' => null,
                    'sent_at' => now(),
                ]);
                $sentCount++;

                if ($recipient->customer_id) {
                    $touchedCustomerIds[$recipient->customer_id] = true;
                }
            } else {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $result['error'],
                ]);
                $failedCount++;
            }
        }

        $total = $campaign->recipients->count();
        $status = match (true) {
            $sentCount > 0 && $failedCount === 0 && $skippedCount === 0 => 'completed',
            $sentCount > 0 => 'partial',
            $failedCount > 0 => 'failed',
            default => 'completed',
        };

        $campaign->update([
            'status' => $status,
            'total_recipients' => $total,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'skipped_count' => $skippedCount,
            'sent_at' => now(),
        ]);

        if ($touchedCustomerIds !== []) {
            $customerIds = array_keys($touchedCustomerIds);
            Customer::query()->whereIn('id', $customerIds)->update(['last_contacted_at' => now()]);

            foreach ($customerIds as $customerId) {
                AuditLog::create([
                    'user_id' => $campaign->created_by,
                    'event' => 'broadcast_sent',
                    'auditable_type' => Customer::class,
                    'auditable_id' => $customerId,
                    'old_values' => null,
                    'new_values' => [
                        'campaign_id' => $campaign->id,
                        'campaign_name' => $campaign->name,
                        'purpose' => $campaign->purpose,
                        'channels' => $campaign->channels ?? [],
                    ],
                ]);
            }
        }

        return $campaign->fresh(['articles', 'recipients']);
    }

    /**
     * Resolve recipient address and opt-out status for a specific channel.
     *
     * @return array{contact_id: ?int, name: string, address: ?string, skip_reason: ?string}
     */
    private function resolveRecipient(Lead $opportunity, string $channel): array
    {
        $customer = $opportunity->customer;
        $contacts = $customer?->contacts ?? collect();

        /** @var Contact|null $activePrimary */
        $activePrimary = $contacts
            ->filter(fn (Contact $c) => $c->left_at === null)
            ->sortByDesc('is_primary')
            ->first();

        $recipientName = $activePrimary?->full_name
            ?: $opportunity->contact_name
            ?: $customer?->name
            ?: $opportunity->company
            ?: 'Quý khách hàng';

        if ($channel === 'email') {
            $emailContact = $contacts
                ->filter(fn (Contact $c) => $c->left_at === null && ! empty($c->email))
                ->sortByDesc('is_primary')
                ->first();

            if ($emailContact?->marketing_opt_out) {
                return [
                    'contact_id' => $emailContact->id,
                    'name' => $emailContact->full_name,
                    'address' => $emailContact->email,
                    'skip_reason' => 'Liên hệ đã từ chối nhận tin quảng bá (marketing_opt_out).',
                ];
            }

            $email = $emailContact?->email ?: ($opportunity->email ?: $customer?->email);

            if (! $email) {
                return [
                    'contact_id' => $activePrimary?->id,
                    'name' => $recipientName,
                    'address' => null,
                    'skip_reason' => 'Khách hàng chưa có địa chỉ Email.',
                ];
            }

            return [
                'contact_id' => $emailContact?->id ?? $activePrimary?->id,
                'name' => $emailContact?->full_name ?: $recipientName,
                'address' => $email,
                'skip_reason' => null,
            ];
        }

        // Kênh Zalo OA
        $zaloContact = $contacts
            ->filter(fn (Contact $c) => $c->left_at === null && ! empty($c->zalo_user_id))
            ->sortByDesc('is_primary')
            ->first();

        if ($zaloContact?->marketing_opt_out) {
            return [
                'contact_id' => $zaloContact->id,
                'name' => $zaloContact->full_name,
                'address' => $zaloContact->zalo_user_id,
                'skip_reason' => 'Liên hệ đã từ chối nhận tin quảng bá (marketing_opt_out).',
            ];
        }

        if (! $zaloContact) {
            return [
                'contact_id' => $activePrimary?->id,
                'name' => $recipientName,
                'address' => null,
                'skip_reason' => 'Khách hàng chưa có Zalo OA User ID (chưa quan tâm OA hoặc chưa liên kết).',
            ];
        }

        return [
            'contact_id' => $zaloContact->id,
            'name' => $zaloContact->full_name,
            'address' => $zaloContact->zalo_user_id,
            'skip_reason' => null,
        ];
    }

    private function driverFor(string $channel): BroadcastDriverInterface
    {
        return match ($channel) {
            'zalo' => $this->zaloDriver,
            default => $this->emailDriver,
        };
    }
}
