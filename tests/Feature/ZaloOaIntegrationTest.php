<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Jobs\DispatchBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Models\ZaloOaConnection;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ZaloOaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(UserRole $role = UserRole::Manager): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function customer(User $owner, string $name = 'Công ty Thiết bị Nhà máy'): Customer
    {
        return (new SaveCustomer)->handle([
            'name' => $name,
            'lifecycle_stage' => 'new_customer',
            'status' => 'active',
            'owner_id' => $owner->id,
        ], $owner);
    }

    private function opportunity(User $owner, Customer $customer): Lead
    {
        $opp = new Lead([
            'customer_id' => $customer->id,
            'title' => 'Đơn hàng mũ bảo hộ',
            'company' => $customer->name,
            'contact_name' => 'Nguyễn Thu Mua',
            'phone' => '0988777666',
            'email' => 'thumua@nhamay.vn',
            'stage' => 'won',
            'estimated_value' => 25000000,
            'probability' => 100,
        ]);
        $opp->owner_id = $owner->id;
        $opp->kind = 'opportunity';
        $opp->save();

        return $opp;
    }

    public function test_immediate_broadcast_dispatches_queue_job(): void
    {
        Queue::fake();

        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $opp = $this->opportunity($sales, $customer);

        $response = $this->actingAs($sales)->post('/repurchase-reminders/broadcast', [
            'name' => 'Chiến dịch chạy qua Queue',
            'purpose' => 'repurchase_reminder',
            'channels' => ['email'],
            'opportunity_ids' => [$opp->id],
            'articles' => [
                ['title' => 'Nhắc bổ sung mũ bảo hộ'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $campaign = BroadcastCampaign::query()->firstOrFail();
        $this->assertSame('queued', $campaign->status);

        Queue::assertPushed(DispatchBroadcastCampaignJob::class, function (DispatchBroadcastCampaignJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id;
        });
    }

    public function test_scheduled_broadcast_waits_until_due_and_dispatches_via_artisan_command(): void
    {
        Queue::fake();
        Mail::fake();

        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $opp = $this->opportunity($sales, $customer);

        $scheduledAt = now()->addHours(2);

        $response = $this->actingAs($sales)->post('/repurchase-reminders/broadcast', [
            'name' => 'Chiến dịch hẹn lịch 2 giờ tới',
            'purpose' => 'promotion',
            'channels' => ['email'],
            'opportunity_ids' => [$opp->id],
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'articles' => [
                ['title' => 'Khuyến mãi cuối quý'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $campaign = BroadcastCampaign::query()->firstOrFail();
        $this->assertSame('scheduled', $campaign->status);
        Queue::assertNothingPushed();

        // Chưa đến giờ -> lệnh không đẩy vào queue
        Artisan::call('broadcast:dispatch-due');
        Queue::assertNothingPushed();

        // Giả lập đã đến giờ hẹn -> lệnh đẩy vào queue
        $campaign->update(['scheduled_at' => now()->subMinute()]);
        Artisan::call('broadcast:dispatch-due');

        Queue::assertPushed(DispatchBroadcastCampaignJob::class, function (DispatchBroadcastCampaignJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id;
        });
        $this->assertSame('queued', $campaign->fresh()->status);
    }

    public function test_zalo_oa_oauth_pkce_flow_and_token_refresh(): void
    {
        config()->set('services.zalo_oa.app_id', 'app-123');
        config()->set('services.zalo_oa.app_secret', 'secret-xyz');
        config()->set('services.zalo_oa.redirect_uri', 'https://crm.example.com/zalo-oa/oauth/callback');

        Http::fake([
            'https://oauth.zaloapp.com/v4/oa/access_token' => Http::sequence()
                ->push([
                    'access_token' => 'zalo-access-initial',
                    'refresh_token' => 'zalo-refresh-initial',
                    'expires_in' => 90000,
                ], 200)
                ->push([
                    'access_token' => 'zalo-access-refreshed',
                    'refresh_token' => 'zalo-refresh-next',
                    'expires_in' => 90000,
                ], 200),
            'https://openapi.zalo.me/v2.0/oa/getoa' => Http::response([
                'error' => 0,
                'data' => [
                    'oa_id' => 'oa-999888',
                    'name' => 'Bảo Hộ D&T Official',
                ],
            ], 200),
        ]);

        $manager = $this->user(UserRole::Manager);

        // 1. Bắt đầu OAuth install
        $installResponse = $this->actingAs($manager)->get('/zalo-oa/oauth/install');
        $installResponse->assertRedirect();
        $redirectUrl = (string) $installResponse->headers->get('Location');
        $this->assertStringStartsWith('https://oauth.zaloapp.com/v4/oa/permission?', $redirectUrl);

        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertSame('app-123', $query['app_id'] ?? null);
        $this->assertNotEmpty($query['code_challenge'] ?? null);
        $this->assertNotEmpty($query['state'] ?? null);

        // 2. Callback đổi authorization code lấy token mã hóa
        $callbackResponse = $this->actingAs($manager)->get('/zalo-oa/oauth/callback?'.http_build_query([
            'code' => 'auth-code-001',
            'state' => $query['state'],
            'oa_id' => 'oa-999888',
        ]));

        $callbackResponse->assertRedirect(route('repurchase-reminders.index'));
        $callbackResponse->assertSessionHas('success');

        $connection = ZaloOaConnection::current();
        $this->assertNotNull($connection);
        $this->assertSame('oa-999888', $connection->oa_id);
        $this->assertSame('Bảo Hộ D&T Official', $connection->oa_name);
        $this->assertSame('zalo-access-initial', $connection->access_token);
        $this->assertSame('zalo-refresh-initial', $connection->refresh_token);

        // 3. Giả lập token sắp hết hạn -> lệnh zalo:refresh-token tự động làm mới
        $connection->update(['expires_at' => now()->addMinutes(20)]);
        Artisan::call('zalo:refresh-token');

        $connection->refresh();
        $this->assertSame('zalo-access-refreshed', $connection->access_token);
        $this->assertSame('zalo-refresh-next', $connection->refresh_token);
    }

    public function test_zalo_oa_webhook_verifies_signature_and_updates_contact_and_delivery_status(): void
    {
        config()->set('services.zalo_oa.app_id', 'app-123');
        config()->set('services.zalo_oa.webhook_secret', 'webhook-secret-abc');

        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);
        $contact = $customer->contacts()->create([
            'full_name' => 'Nguyễn Thu Mua',
            'phone' => '0988777666',
            'is_primary' => true,
        ]);

        $campaign = BroadcastCampaign::create([
            'name' => 'Test Webhook Delivery',
            'purpose' => 'repurchase_reminder',
            'channels' => ['zalo'],
            'status' => 'completed',
            'created_by' => $manager->id,
            'total_recipients' => 1,
            'sent_count' => 1,
        ]);

        $recipient = BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'channel' => 'zalo',
            'recipient_name' => $contact->full_name,
            'recipient_address' => 'zalo-follower-777',
            'status' => 'sent',
            'provider_message_id' => 'zalo-msg-webhook-01',
            'sent_at' => now(),
        ]);

        // 1. Chữ ký sai -> 401
        $this->postJson('/webhooks/zalo-oa', [
            'app_id' => 'app-123',
            'event_name' => 'follow',
            'timestamp' => '1732450000000',
            'follower' => ['id' => 'zalo-follower-777'],
        ], ['X-ZEvent-Signature' => 'mac=invalid'])
            ->assertStatus(401);

        // 2. Sự kiện follow khớp SĐT -> tự gán zalo_user_id và zalo_followed_at
        $followPayload = json_encode([
            'app_id' => 'app-123',
            'event_name' => 'follow',
            'timestamp' => '1732450000000',
            'follower' => [
                'id' => 'zalo-follower-777',
                'phone' => '0988777666',
            ],
        ], JSON_THROW_ON_ERROR);
        $followSig = 'mac='.hash('sha256', 'app-123'.$followPayload.'1732450000000'.'webhook-secret-abc');

        $this->call('POST', '/webhooks/zalo-oa', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ZEVENT_SIGNATURE' => $followSig,
        ], $followPayload)->assertOk();

        $contact->refresh();
        $this->assertSame('zalo-follower-777', $contact->zalo_user_id);
        $this->assertNotNull($contact->zalo_followed_at);

        // 3. Sự kiện user_seen_message -> cập nhật trạng thái 'seen'
        $seenPayload = json_encode([
            'app_id' => 'app-123',
            'event_name' => 'user_seen_message',
            'timestamp' => '1732450005000',
            'message' => ['msg_id' => 'zalo-msg-webhook-01'],
        ], JSON_THROW_ON_ERROR);
        $seenSig = 'mac='.hash('sha256', 'app-123'.$seenPayload.'1732450005000'.'webhook-secret-abc');

        $this->call('POST', '/webhooks/zalo-oa', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ZEVENT_SIGNATURE' => $seenSig,
        ], $seenPayload)->assertOk();

        $recipient->refresh();
        $this->assertSame('seen', $recipient->status);
        $this->assertNotNull($recipient->delivered_at);
        $this->assertNotNull($recipient->seen_at);
    }

    public function test_can_save_zalo_user_id_on_customer_contact(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);

        $this->actingAs($manager)
            ->post("/customers/{$customer->id}/contacts", [
                'full_name' => 'Lê Văn Quản Lý',
                'buying_role' => 'purchasing',
                'phone' => '0911222333',
                'zalo' => '0911222333',
                'zalo_user_id' => 'zalo-oa-uid-manual-01',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'full_name' => 'Lê Văn Quản Lý',
            'zalo_user_id' => 'zalo-oa-uid-manual-01',
        ]);
    }
}
