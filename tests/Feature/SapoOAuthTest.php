<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\SapoConnection;
use App\Models\User;
use App\Services\Sapo\SapoProductCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SapoOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'services.sapo.auth_mode' => 'oauth',
            'services.sapo.store_domain' => 'test-shop.mysapo.net',
            'services.sapo.client_id' => 'test-client-id',
            'services.sapo.client_secret' => 'test-client-secret',
            'services.sapo.redirect_uri' => 'http://127.0.0.1:8000/sapo/oauth/callback',
            'services.sapo.access_token' => null,
        ]);
    }

    public function test_manager_completes_oauth_and_catalog_uses_the_encrypted_token(): void
    {
        Http::fake([
            'https://test-shop.mysapo.net/admin/oauth/access_token' => Http::response([
                'access_token' => 'private-oauth-token',
            ]),
            'https://test-shop.mysapo.net/admin/products.json*' => Http::response([
                'products' => [],
            ]),
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $installResponse = $this->actingAs($manager)
            ->get(route('sapo.oauth.install'))
            ->assertRedirect();

        $install = session('sapo_oauth_install');
        $this->assertIsArray($install);
        $this->assertSame('test-shop.mysapo.net', $install['store_domain']);
        $this->assertSame($manager->id, $install['user_id']);
        parse_str(parse_url($installResponse->headers->get('Location'), PHP_URL_QUERY), $authorizationQuery);
        $this->assertSame('test-client-id', $authorizationQuery['client_id']);
        $this->assertSame('read_products', $authorizationQuery['scope']);
        $this->assertSame(
            'http://127.0.0.1:8000/sapo/oauth/callback',
            $authorizationQuery['redirect_uri'],
        );
        $this->assertSame($install['state'], $authorizationQuery['state']);

        $callbackQuery = $this->signedCallbackQuery($install['state']);
        $this->get(route('sapo.oauth.callback').'?'.http_build_query(
            $callbackQuery,
            '',
            '&',
            PHP_QUERY_RFC3986,
        ))
            ->assertRedirect(route('leads.index'))
            ->assertSessionHas('success', 'Đã kết nối Sapo. Access token được lưu mã hóa trên máy chủ.')
            ->assertDontSee('private-oauth-token');

        $connection = SapoConnection::query()->sole();
        $this->assertSame('private-oauth-token', $connection->access_token);
        $this->assertNotSame(
            'private-oauth-token',
            DB::table('sapo_connections')->value('access_token'),
        );

        $this->assertSame(0, app(SapoProductCatalog::class)->sync());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://test-shop.mysapo.net/admin/oauth/access_token'
            && $request['client_secret'] === 'test-client-secret'
            && str_contains((string) $request->header('Content-Type')[0], 'application/x-www-form-urlencoded')
            && $request['code'] === 'test-authorization-code');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://test-shop.mysapo.net/admin/products.json?limit=50&page=1'
            && $request->hasHeader('X-Sapo-Access-Token', 'private-oauth-token'));
    }

    public function test_oauth_callback_rejects_bad_hmac_and_consumes_the_install_state(): void
    {
        Http::fake();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('sapo.oauth.install'))->assertRedirect();
        $install = session('sapo_oauth_install');
        $callbackQuery = $this->signedCallbackQuery($install['state']);
        $callbackQuery['hmac'] = 'invalid-hmac';

        $this->get(route('sapo.oauth.callback').'?'.http_build_query(
            $callbackQuery,
            '',
            '&',
            PHP_QUERY_RFC3986,
        ))
            ->assertRedirect(route('leads.index'))
            ->assertSessionHas('error', 'Phản hồi OAuth không hợp lệ hoặc đã hết hạn. Hãy bắt đầu kết nối lại.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('sapo_connections', 0);
        $this->assertNull(session('sapo_oauth_install'));
    }

    public function test_oauth_callback_rejects_a_signed_state_mismatch(): void
    {
        Http::fake();
        $manager = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('sapo.oauth.install'))->assertRedirect();
        $install = session('sapo_oauth_install');
        $callbackQuery = $this->signedCallbackQuery($install['state'], 'different-state');

        $this->get(route('sapo.oauth.callback').'?'.http_build_query(
            $callbackQuery,
            '',
            '&',
            PHP_QUERY_RFC3986,
        ))
            ->assertRedirect(route('leads.index'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('sapo_connections', 0);
    }

    public function test_oauth_callback_reports_token_exchange_failure_without_storing_a_connection(): void
    {
        Http::fake([
            'https://test-shop.mysapo.net/admin/oauth/access_token' => Http::response([
                'error' => 'invalid_grant',
            ], 401),
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('sapo.oauth.install'))->assertRedirect();
        $install = session('sapo_oauth_install');
        $callbackQuery = $this->signedCallbackQuery($install['state']);

        $this->get(route('sapo.oauth.callback').'?'.http_build_query(
            $callbackQuery,
            '',
            '&',
            PHP_QUERY_RFC3986,
        ))
            ->assertRedirect(route('leads.index'))
            ->assertSessionHas(
                'error',
                'Sapo không cấp access token. Hãy kiểm tra cấu hình ứng dụng và quyền read_products.',
            );

        $this->assertDatabaseCount('sapo_connections', 0);
    }

    public function test_oauth_install_requires_manager_and_complete_app_configuration(): void
    {
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);
        $this->actingAs($sales)->get(route('sapo.oauth.install'))->assertForbidden();

        $manager = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
        config(['services.sapo.client_secret' => null]);

        $this->actingAs($manager)
            ->get(route('sapo.oauth.install'))
            ->assertRedirect(route('leads.index'))
            ->assertSessionHas('error');
    }

    /**
     * @return array<string, string>
     */
    private function signedCallbackQuery(string $state, ?string $callbackState = null): array
    {
        $query = [
            'code' => 'test-authorization-code',
            'signature' => 'test-signature',
            'state' => $callbackState ?? $state,
            'store' => 'test-shop.mysapo.net',
            'timestamp' => (string) now()->timestamp,
        ];
        ksort($query, SORT_STRING);
        $message = implode('&', array_map(
            fn (string $key, string $value): string => "{$key}={$value}",
            array_keys($query),
            array_values($query),
        ));
        $query['hmac'] = base64_encode(hash_hmac(
            'sha256',
            $message,
            'test-client-secret',
            true,
        ));

        return $query;
    }
}
