<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\SapoConnection;
use App\Models\User;
use App\Services\Sapo\SapoProductCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SapoProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'services.sapo.auth_mode' => 'oauth',
            'services.sapo.store_domain' => 'test-shop.mysapo.net',
            'services.sapo.access_token' => 'test-access-token',
        ]);
    }

    public function test_manager_syncs_product_variants_and_employees_can_search_the_local_catalog(): void
    {
        Http::fake([
            'https://test-shop.mysapo.net/admin/products.json*' => Http::response([
                'products' => [[
                    'id' => 100,
                    'name' => 'Mũ bảo hộ',
                    'alias' => 'mu-bao-ho',
                    'vendor' => 'D&T',
                    'product_type' => 'Bảo vệ đầu',
                    'body_html' => '<p>Chống va đập &amp; bảo vệ đầu</p><script>alert(1)</script>',
                    'status' => 'active',
                    'tags' => 'công trình, bảo hộ',
                    'images' => [[
                        'src' => 'https://cdn.example.test/mu-bao-ho.jpg',
                    ]],
                    'variants' => [[
                        'id' => 200,
                        'name' => 'Màu trắng',
                        'sku' => 'MU-TRANG',
                        'price' => '125000.00',
                    ]],
                ]],
            ]),
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post('/sapo/products/sync')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Đã xếp lượt đồng bộ Sapo vào hàng đợi.');

        Http::assertNothingSent();
        $this->getJson(route('sapo.products.sync-status'))->assertJsonPath('state', 'queued');
        $this->post(route('sapo.products.sync'))
            ->assertSessionHas('success', 'Đã có lượt đồng bộ Sapo đang chờ hoặc đang chạy.');
        $this->assertDatabaseCount('jobs', 1);
        $this->getJson(route('sapo.products.index', ['q' => 'mu bao ho']))->assertOk();

        $this->artisan('queue:work', ['connection' => 'sapo', '--once' => true, '--sleep' => 0])
            ->assertSuccessful();
        $this->getJson(route('sapo.products.sync-status'))->assertExactJson([
            'state' => 'succeeded',
            'message' => 'Đã đồng bộ 1 phiên bản sản phẩm từ Sapo.',
        ]);

        $this->assertDatabaseHas('sapo_products', [
            'sapo_product_id' => 100,
            'sapo_variant_id' => 200,
            'product_name' => 'Mũ bảo hộ',
            'variant_name' => 'Màu trắng',
            'sku' => 'MU-TRANG',
            'unit_price' => '125000.00',
            'vendor' => 'D&T',
            'product_type' => 'Bảo vệ đầu',
            'description' => 'Chống va đập & bảo vệ đầu',
            'image_url' => 'https://cdn.example.test/mu-bao-ho.jpg',
            'product_status' => 'active',
        ]);
        Http::assertSent(fn (Request $request) => $request->hasHeader(
            'X-Sapo-Access-Token',
            'test-access-token',
        ));

        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);
        $this->actingAs($sales)
            ->getJson('/sapo/products?q=MU-TRANG')
            ->assertOk()
            ->assertJsonPath('data.0.sapo_variant_id', '200')
            ->assertJsonPath('data.0.name', 'Mũ bảo hộ · Màu trắng')
            ->assertJsonPath('data.0.unit_price', '125000.00')
            ->assertJsonPath('data.0.label', 'Mũ bảo hộ · Màu trắng · SKU MU-TRANG')
            ->assertJsonPath('data.0.product_type', 'Bảo vệ đầu')
            ->assertJsonPath('data.0.vendor', 'D&T')
            ->assertJsonPath('data.0.description', 'Chống va đập & bảo vệ đầu')
            ->assertJsonPath('data.0.image_url', 'https://cdn.example.test/mu-bao-ho.jpg')
            ->assertJsonPath('data.0.tags.0', 'công trình')
            ->assertJsonPath('data.0.tags.1', 'bảo hộ');

        foreach (['MŨ BẢO HỘ', 'mu bao ho', 'trang mu', 'trang'] as $keyword) {
            $this->getJson(route('sapo.products.index', ['q' => $keyword]))
                ->assertOk()
                ->assertJsonPath('data.0.sapo_variant_id', '200');
        }

        $this->getJson(route('sapo.products.index', ['q' => 'mu kinh']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Http::assertSentCount(1);
    }

    public function test_manager_syncs_through_private_app_basic_auth_with_an_env_token_present(): void
    {
        config([
            'services.sapo.auth_mode' => 'private_app',
            'services.sapo.client_id' => 'private-key',
            'services.sapo.client_secret' => 'private-secret',
        ]);
        Http::preventStrayRequests()->fake([
            'https://test-shop.mysapo.net/admin/products.json*' => Http::response([
                'products' => [],
            ]),
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('sapo.products.sync'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Đã xếp lượt đồng bộ Sapo vào hàng đợi.');
        $this->artisan('queue:work', ['connection' => 'sapo', '--once' => true, '--sleep' => 0])
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('private-key:private-secret'),
        ) && ! $request->hasHeader('X-Sapo-Access-Token'));
    }

    public function test_private_app_sync_requires_a_secret_even_when_an_env_token_exists(): void
    {
        config([
            'services.sapo.auth_mode' => 'private_app',
            'services.sapo.client_id' => 'private-key',
            'services.sapo.client_secret' => null,
        ]);
        Http::preventStrayRequests();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('sapo.products.sync'))
            ->assertSessionHasErrors('sapo');

        Http::assertNothingSent();
    }

    public function test_catalog_prefers_the_encrypted_oauth_token_for_the_configured_store(): void
    {
        Http::fake([
            'https://test-shop.mysapo.net/admin/products.json*' => Http::response([
                'products' => [],
            ]),
        ]);
        SapoConnection::query()->create([
            'store_domain' => 'test-shop.mysapo.net',
            'access_token' => 'encrypted-oauth-token',
            'connected_at' => now(),
        ]);

        $this->assertSame(0, app(SapoProductCatalog::class)->sync());
        Http::assertSent(fn (Request $request) => $request->hasHeader(
            'X-Sapo-Access-Token',
            'encrypted-oauth-token',
        ));
    }

    public function test_sync_requires_credentials_and_admin_or_manager_role(): void
    {
        config([
            'services.sapo.store_domain' => null,
            'services.sapo.access_token' => null,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from('/leads')
            ->post('/sapo/products/sync')
            ->assertRedirect('/leads')
            ->assertSessionHasErrors('sapo');
        Http::assertNothingSent();

        config([
            'services.sapo.store_domain' => 'test-shop.mysapo.net',
            'services.sapo.access_token' => 'test-access-token',
        ]);
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);

        $this->actingAs($sales)->post('/sapo/products/sync')->assertForbidden();
        $this->getJson(route('sapo.products.sync-status'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_sapo_permission_error_is_reported_without_hiding_the_failure(): void
    {
        Http::fake([
            'https://test-shop.mysapo.net/admin/products.json*' => Http::response([], 403),
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from('/leads')
            ->post('/sapo/products/sync')
            ->assertRedirect('/leads')
            ->assertSessionHasNoErrors();

        $this->artisan('queue:work', ['connection' => 'sapo', '--once' => true, '--sleep' => 0])
            ->assertSuccessful();
        $this->getJson(route('sapo.products.sync-status'))->assertExactJson([
            'state' => 'failed',
            'message' => 'Ứng dụng Sapo chưa được cấp quyền read_products.',
        ]);
        $this->post(route('sapo.products.sync'))
            ->assertSessionHas('success', 'Đã xếp lượt đồng bộ Sapo vào hàng đợi.');
    }
}
