<?php

namespace App\Services\Broadcast;

use App\Models\User;
use App\Models\ZaloOaConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZaloOaTokenService
{
    /**
     * Resolve a valid Zalo OA access token (OAuth DB connection first with auto-refresh, then .env fallback).
     */
    public function getValidAccessToken(): ?string
    {
        $connection = ZaloOaConnection::current();

        if ($connection) {
            if ($connection->isExpiringSoon(30) && ! empty($connection->refresh_token)) {
                $refreshed = $this->refresh($connection);
                if ($refreshed) {
                    return $refreshed->access_token;
                }
            }

            if (! empty($connection->access_token)) {
                return $connection->access_token;
            }
        }

        $envToken = trim((string) config('services.zalo_oa.access_token', ''));

        return $envToken !== '' ? $envToken : null;
    }

    /**
     * Exchange OAuth v4 authorization code (PKCE) for OA access_token & refresh_token.
     */
    public function exchangeCode(string $code, string $codeVerifier, ?User $actor = null, ?string $oaId = null): ZaloOaConnection
    {
        $appId = (string) config('services.zalo_oa.app_id');
        $appSecret = (string) config('services.zalo_oa.app_secret');
        $oauthBase = rtrim((string) config('services.zalo_oa.oauth_base_url', 'https://oauth.zaloapp.com/v4/oa'), '/');

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('Chưa cấu hình ZALO_OA_APP_ID và ZALO_OA_APP_SECRET trong .env.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->withHeaders(['secret_key' => $appSecret])
            ->post("{$oauthBase}/access_token", [
                'code' => $code,
                'app_id' => $appId,
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ]);

        $body = $response->json();
        $accessToken = is_array($body) ? ($body['access_token'] ?? null) : null;
        $refreshToken = is_array($body) ? ($body['refresh_token'] ?? null) : null;
        $expiresIn = is_array($body) ? (int) ($body['expires_in'] ?? 90000) : 90000;

        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            $reason = is_array($body) ? (string) ($body['error_description'] ?? $body['error_name'] ?? 'Zalo không cấp token.') : 'HTTP '.$response->status();
            throw new RuntimeException('Đổi mã OAuth Zalo OA thất bại: '.$reason);
        }

        $oaProfile = $this->fetchOaProfile($accessToken);
        $resolvedOaId = $oaProfile['oa_id'] ?: ($oaId ?: ((string) config('services.zalo_oa.oa_id') ?: 'default-oa'));

        return ZaloOaConnection::query()->updateOrCreate(
            ['oa_id' => $resolvedOaId],
            [
                'oa_name' => $oaProfile['oa_name'],
                'access_token' => $accessToken,
                'refresh_token' => is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
                'expires_at' => now()->addSeconds(max(60, $expiresIn)),
                'connected_by' => $actor?->id,
                'connected_at' => now(),
            ],
        );
    }

    /**
     * Refresh an existing Zalo OA OAuth connection using its refresh_token.
     */
    public function refresh(ZaloOaConnection $connection): ?ZaloOaConnection
    {
        $appId = (string) config('services.zalo_oa.app_id');
        $appSecret = (string) config('services.zalo_oa.app_secret');
        $oauthBase = rtrim((string) config('services.zalo_oa.oauth_base_url', 'https://oauth.zaloapp.com/v4/oa'), '/');

        if ($appId === '' || $appSecret === '' || empty($connection->refresh_token)) {
            return null;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->withHeaders(['secret_key' => $appSecret])
            ->post("{$oauthBase}/access_token", [
                'refresh_token' => $connection->refresh_token,
                'app_id' => $appId,
                'grant_type' => 'refresh_token',
            ]);

        $body = $response->json();
        $accessToken = is_array($body) ? ($body['access_token'] ?? null) : null;

        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            return null;
        }

        $refreshToken = is_array($body) ? ($body['refresh_token'] ?? null) : null;
        $expiresIn = is_array($body) ? (int) ($body['expires_in'] ?? 90000) : 90000;

        $connection->update([
            'access_token' => $accessToken,
            'refresh_token' => is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : $connection->refresh_token,
            'expires_at' => now()->addSeconds(max(60, $expiresIn)),
        ]);

        return $connection->fresh();
    }

    /**
     * @return array{oa_id: ?string, oa_name: ?string}
     */
    private function fetchOaProfile(string $accessToken): array
    {
        $apiBase = rtrim((string) config('services.zalo_oa.api_base_url', 'https://openapi.zalo.me/v3.0/oa'), '/');
        // Zalo OA info endpoint lives on v2.0/oa/getoa
        $infoUrl = str_replace('/v3.0/oa', '/v2.0/oa', $apiBase).'/getoa';

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['access_token' => $accessToken])
                ->get($infoUrl);

            $data = $response->json('data');

            if ($response->successful() && is_array($data)) {
                return [
                    'oa_id' => isset($data['oa_id']) ? (string) $data['oa_id'] : null,
                    'oa_name' => isset($data['name']) ? (string) $data['name'] : null,
                ];
            }
        } catch (\Throwable) {
            // Không chặn quy trình lưu token nếu API getoa tạm thời không phản hồi.
        }

        return ['oa_id' => null, 'oa_name' => null];
    }
}
