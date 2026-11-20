<?php

namespace App\Http\Controllers;

use App\Models\SapoConnection;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SapoOAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'sapo_oauth_install';

    private const MAX_CALLBACK_AGE_SECONDS = 600;

    public function install(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->isManager(), 403);

        $storeDomain = strtolower(trim((string) config('services.sapo.store_domain')));
        $clientId = (string) config('services.sapo.client_id');
        $redirectUri = (string) config('services.sapo.redirect_uri');
        $redirectParts = parse_url($redirectUri);

        if (
            ! filled($clientId)
            || ! filled(config('services.sapo.client_secret'))
            || preg_match('/^[a-z0-9][a-z0-9.-]*\.mysapo\.net$/', $storeDomain) !== 1
            || ! is_array($redirectParts)
            || ! in_array($redirectParts['scheme'] ?? null, ['http', 'https'], true)
            || blank($redirectParts['host'] ?? null)
        ) {
            return redirect()->route('leads.index')->with(
                'error',
                'Hãy cấu hình SAPO_STORE_DOMAIN, SAPO_CLIENT_ID, SAPO_CLIENT_SECRET và SAPO_REDIRECT_URI trong .env.',
            );
        }

        $state = Str::random(48);
        $request->session()->put(self::STATE_SESSION_KEY, [
            'state' => $state,
            'store_domain' => $storeDomain,
            'user_id' => $user->id,
            'started_at' => now()->timestamp,
        ]);

        $authorizationUrl = "https://{$storeDomain}/admin/oauth/authorize?".http_build_query([
            'client_id' => $clientId,
            'scope' => 'read_products',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($authorizationUrl);
    }

    /**
     * Xác minh phiên khởi tạo, chữ ký và cửa hàng trước khi đổi mã OAuth lấy token.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Lấy và xóa state khỏi phiên để callback chỉ dùng được một lần.
        $install = $request->session()->pull(self::STATE_SESSION_KEY);
        $query = $request->query();

        if (
            ! is_array($install)
            || ! is_string($install['state'] ?? null)
            || ! is_string($install['store_domain'] ?? null)
            || ! is_int($install['user_id'] ?? null)
            || ! is_int($install['started_at'] ?? null)
            || abs(now()->timestamp - $install['started_at']) > self::MAX_CALLBACK_AGE_SECONDS
            || ! $this->hasValidHmac($query)
            || ! is_string($query['state'] ?? null)
            || ! hash_equals($install['state'], $query['state'])
            || ! is_string($query['store'] ?? null)
            || ! hash_equals($install['store_domain'], strtolower($query['store']))
            || ! is_string($query['timestamp'] ?? null)
            || ! ctype_digit($query['timestamp'])
            || abs(now()->timestamp - (int) $query['timestamp']) > self::MAX_CALLBACK_AGE_SECONDS
            || ! is_string($query['code'] ?? null)
            || $query['code'] === ''
        ) {
            return $this->callbackError('Phản hồi OAuth không hợp lệ hoặc đã hết hạn. Hãy bắt đầu kết nối lại.');
        }

        $user = User::query()->find($install['user_id']);
        if (! $user || ! $user->is_active || ! $user->isManager()) {
            return $this->callbackError('Tài khoản khởi tạo kết nối không còn quyền quản lý.');
        }

        $clientId = (string) config('services.sapo.client_id');
        $clientSecret = (string) config('services.sapo.client_secret');

        try {
            // Exchange the short-lived authorization code server-side; the secret never reaches the browser.
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post("https://{$install['store_domain']}/admin/oauth/access_token", [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $query['code'],
                ]);
        } catch (ConnectionException) {
            return $this->callbackError('Không kết nối được Sapo để hoàn tất OAuth. Hãy thử lại.');
        }

        $accessToken = $response->json('access_token');
        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            return $this->callbackError('Sapo không cấp access token. Hãy kiểm tra cấu hình ứng dụng và quyền read_products.');
        }

        SapoConnection::query()->updateOrCreate(
            ['store_domain' => $install['store_domain']],
            [
                'access_token' => $accessToken,
                'connected_at' => now(),
            ],
        );

        return redirect()->route('leads.index')->with('success', 'Đã kết nối Sapo. Access token được lưu mã hóa trên máy chủ.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function hasValidHmac(array $query): bool
    {
        $providedHmac = $query['hmac'] ?? null;
        $secret = (string) config('services.sapo.client_secret');

        if (! is_string($providedHmac) || $providedHmac === '' || $secret === '') {
            return false;
        }

        foreach ($query as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                return false;
            }
        }

        unset($query['hmac']);
        ksort($query, SORT_STRING);

        $message = implode('&', array_map(
            fn (string $key, string $value): string => "{$key}={$value}",
            array_keys($query),
            array_values($query),
        ));
        $expectedHmac = base64_encode(hash_hmac('sha256', $message, $secret, true));

        return hash_equals($expectedHmac, $providedHmac);
    }

    private function callbackError(string $message): RedirectResponse
    {
        return redirect()->route('leads.index')->with('error', $message);
    }
}
