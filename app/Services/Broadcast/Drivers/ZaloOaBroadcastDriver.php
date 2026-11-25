<?php

namespace App\Services\Broadcast\Drivers;

use App\Models\BroadcastArticle;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Services\Broadcast\ZaloOaTokenService;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZaloOaBroadcastDriver implements BroadcastDriverInterface
{
    public function __construct(
        private ZaloOaTokenService $tokenService,
    ) {}

    /**
     * Send a Rich Media Promotion message to a Zalo OA follower via Zalo OA API v3.
     *
     * @return array{ok: bool, message_id: ?string, error: ?string}
     */
    public function send(BroadcastCampaign $campaign, BroadcastRecipient $recipient): array
    {
        if (! $recipient->recipient_address) {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'Thiếu Zalo OA User ID của người nhận.',
            ];
        }

        $sandbox = (bool) config('services.zalo_oa.sandbox', false);

        if ($sandbox) {
            return [
                'ok' => true,
                'message_id' => 'zalo-sandbox-'.uniqid(),
                'error' => null,
            ];
        }

        $accessToken = $this->tokenService->getValidAccessToken();

        if (! $accessToken) {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'Chưa kết nối Zalo OA OAuth hoặc chưa cấu hình ZALO_OA_ACCESS_TOKEN trong .env.',
            ];
        }

        $payload = $this->buildPromotionPayload($campaign, $recipient);
        $endpoint = rtrim((string) config('services.zalo_oa.api_base_url', 'https://openapi.zalo.me/v3.0/oa'), '/').'/message/promotion';

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'access_token' => $accessToken,
                ])
                ->acceptJson()
                ->post($endpoint, $payload);

            $body = $response->json();

            if ($response->successful() && is_array($body) && (int) ($body['error'] ?? -1) === 0) {
                return [
                    'ok' => true,
                    'message_id' => (string) ($body['data']['message_id'] ?? ('zalo-'.uniqid())),
                    'error' => null,
                ];
            }

            $errorMessage = is_array($body)
                ? (string) ($body['message'] ?? 'Zalo OA trả về lỗi không xác định.')
                : 'HTTP '.$response->status();

            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'Zalo OA: '.$errorMessage,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'Không kết nối được Zalo OA API: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Build Zalo OA v3 Promotion Rich Media payload from campaign articles.
     *
     * @return array<string, mixed>
     */
    public function buildPromotionPayload(BroadcastCampaign $campaign, BroadcastRecipient $recipient): array
    {
        $articles = $campaign->articles->values();
        /** @var BroadcastArticle|null $hero */
        $hero = $articles->first();

        $elements = [];

        if ($hero?->cover_image_url) {
            $elements[] = [
                'type' => 'banner',
                'image_url' => $hero->cover_image_url,
            ];
        }

        $elements[] = [
            'type' => 'header',
            'content' => mb_substr($hero?->title ?: $campaign->name, 0, 100),
            'align' => 'left',
        ];

        $greeting = $recipient->recipient_name
            ? "Kính gửi {$recipient->recipient_name},\n"
            : '';

        if ($hero?->summary) {
            $elements[] = [
                'type' => 'text',
                'align' => 'left',
                'content' => mb_substr($greeting.$hero->summary, 0, 1000),
            ];
        }

        $secondaryRows = [];
        foreach ($articles->slice(1) as $index => $article) {
            $secondaryRows[] = [
                'key' => 'Tin '.($index + 2),
                'value' => mb_substr($article->title.($article->summary ? ' — '.$article->summary : ''), 0, 100),
            ];
        }

        if ($secondaryRows !== []) {
            $elements[] = [
                'type' => 'table',
                'content' => $secondaryRows,
            ];
        }

        $buttons = [];
        foreach ($articles->take(4) as $article) {
            if ($article->cta_url) {
                $buttons[] = [
                    'title' => mb_substr($article->cta_label ?: $article->title, 0, 35),
                    'type' => 'oa.open.url',
                    'payload' => [
                        'url' => $article->cta_url,
                    ],
                ];
            }
        }

        $attachmentPayload = [
            'template_type' => 'promotion',
            'elements' => $elements,
        ];

        if ($buttons !== []) {
            $attachmentPayload['buttons'] = $buttons;
        }

        return [
            'recipient' => [
                'user_id' => $recipient->recipient_address,
            ],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => $attachmentPayload,
                ],
            ],
        ];
    }
}
