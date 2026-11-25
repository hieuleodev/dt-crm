<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Broadcast\ZaloOaTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ZaloOaOAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'zalo_oa_oauth_install';

    private const MAX_CALLBACK_AGE_SECONDS = 600;

    /**
     * Redirect Manager/Admin to Zalo OA OAuth v4 permission screen with PKCE.
     */
    public function install(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->isManager(), 403);

        $appId = trim((string) config('services.zalo_oa.app_id'));
        $appSecret = trim((string) config('services.zalo_oa.app_secret'));
        $redirectUri = trim((string) config('services.zalo_oa.redirect_uri'));
        $redirectParts = parse_url($redirectUri);

        if (
            $appId === ''
            || $appSecret === ''
            || ! is_array($redirectParts)
            || ! in_array($redirectParts['scheme'] ?? null, ['http', 'https'], true)
            || blank($redirectParts['host'] ?? null)
        ) {
            return redirect()->route('repurchase-reminders.index')->with(
                'error',
                'Hãy cấu hình ZALO_OA_APP_ID, ZALO_OA_APP_SECRET và ZALO_OA_REDIRECT_URI trong .env trước khi kết nối Zalo OA.',
            );
        }

        $state = Str::random(40);
        $codeVerifier = Str::random(43);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $request->session()->put(self::STATE_SESSION_KEY, [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'user_id' => $user->id,
            'started_at' => now()->timestamp,
        ]);

        $oauthBase = rtrim((string) config('services.zalo_oa.oauth_base_url', 'https://oauth.zaloapp.com/v4/oa'), '/');
        $permissionUrl = "{$oauthBase}/permission?".http_build_query([
            'app_id' => $appId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($permissionUrl);
    }

    /**
     * Verify one-time state & exchange Zalo OA authorization code for encrypted tokens.
     */
    public function callback(Request $request, ZaloOaTokenService $tokenService): RedirectResponse
    {
        $install = $request->session()->pull(self::STATE_SESSION_KEY);
        $state = $request->query('state');
        $code = $request->query('code');
        $oaId = $request->query('oa_id');

        if (
            ! is_array($install)
            || ! is_string($install['state'] ?? null)
            || ! is_string($install['code_verifier'] ?? null)
            || ! is_int($install['user_id'] ?? null)
            || ! is_int($install['started_at'] ?? null)
            || abs(now()->timestamp - $install['started_at']) > self::MAX_CALLBACK_AGE_SECONDS
            || ! is_string($state)
            || ! hash_equals($install['state'], $state)
            || ! is_string($code)
            || $code === ''
        ) {
            return redirect()->route('repurchase-reminders.index')->with(
                'error',
                'Phản hồi OAuth Zalo OA không hợp lệ hoặc đã hết hạn. Vui lòng thử kết nối lại.',
            );
        }

        $user = User::query()->find($install['user_id']);
        if (! $user || ! $user->is_active || ! $user->isManager()) {
            return redirect()->route('repurchase-reminders.index')->with(
                'error',
                'Tài khoản khởi tạo kết nối Zalo OA không còn quyền quản lý.',
            );
        }

        try {
            $connection = $tokenService->exchangeCode(
                $code,
                $install['code_verifier'],
                $user,
                is_string($oaId) && $oaId !== '' ? $oaId : null,
            );
        } catch (Throwable $e) {
            return redirect()->route('repurchase-reminders.index')->with('error', $e->getMessage());
        }

        $label = $connection->oa_name ?: $connection->oa_id;

        return redirect()->route('repurchase-reminders.index')->with(
            'success',
            "Đã kết nối Zalo OA ({$label}) thành công. Access/Refresh token đã được lưu mã hóa.",
        );
    }
}
