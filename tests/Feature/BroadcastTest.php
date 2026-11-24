<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Mail\RichMediaBroadcastMail;
use App\Models\BroadcastCampaign;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(UserRole $role = UserRole::Sales): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function customer(User $owner, string $name = 'Công ty Bảo hộ An Toàn'): Customer
    {
        return (new SaveCustomer)->handle([
            'name' => $name,
            'lifecycle_stage' => 'new_customer',
            'status' => 'active',
            'owner_id' => $owner->id,
        ], $owner);
    }

    private function opportunity(User $owner, Customer $customer, ?string $email = 'muahang@antoan.vn'): Lead
    {
        $opp = new Lead([
            'customer_id' => $customer->id,
            'title' => 'Đơn hàng Găng tay chống cắt',
            'company' => $customer->name,
            'contact_name' => 'Trần Văn A',
            'phone' => '0912345678',
            'email' => $email,
            'stage' => 'won',
            'estimated_value' => 30000000,
            'probability' => 100,
        ]);
        $opp->owner_id = $owner->id;
        $opp->kind = 'opportunity';
        $opp->save();

        return $opp;
    }

    public function test_can_send_multi_article_broadcast_via_both_email_and_zalo_oa(): void
    {
        Mail::fake();
        config()->set('services.zalo_oa.access_token', 'test-zalo-token');
        config()->set('services.zalo_oa.sandbox', false);

        Http::fake([
            'https://openapi.zalo.me/v3.0/oa/message/promotion' => Http::response([
                'error' => 0,
                'message' => 'Success',
                'data' => ['message_id' => 'zalo-msg-12345'],
            ], 200),
        ]);

        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $customer->contacts()->create([
            'full_name' => 'Trần Văn A',
            'email' => 'trana@antoan.vn',
            'zalo' => '0912345678',
            'zalo_user_id' => 'zalo-uid-999',
            'is_primary' => true,
            'marketing_opt_out' => false,
        ]);
        $opp = $this->opportunity($sales, $customer, 'trana@antoan.vn');

        $response = $this->actingAs($sales)->post('/repurchase-reminders/broadcast', [
            'name' => 'Chiến dịch nhắc mua lại tháng 11',
            'purpose' => 'repurchase_reminder',
            'channels' => ['email', 'zalo'],
            'opportunity_ids' => [$opp->id],
            'articles' => [
                [
                    'title' => 'Ưu đãi bổ sung găng tay chống cắt định kỳ',
                    'summary' => 'Giảm 5% cho đơn đặt lại trong tuần này.',
                    'cover_image_url' => 'https://example.com/banner.jpg',
                    'cta_label' => 'Đặt hàng ngay',
                    'cta_url' => 'https://example.com/reorder',
                ],
                [
                    'title' => 'Sản phẩm mới: Giày bảo hộ siêu nhẹ ESD',
                    'summary' => 'Đạt chuẩn S3 SRC chống tĩnh điện.',
                    'cta_label' => 'Xem thông số',
                    'cta_url' => 'https://example.com/esd-shoes',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(RichMediaBroadcastMail::class, function (RichMediaBroadcastMail $mail): bool {
            return $mail->hasTo('trana@antoan.vn')
                && $mail->campaign->articles->count() === 2;
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://openapi.zalo.me/v3.0/oa/message/promotion'
                && $request->hasHeader('access_token', 'test-zalo-token')
                && data_get($request->data(), 'recipient.user_id') === 'zalo-uid-999'
                && data_get($request->data(), 'message.attachment.payload.template_type') === 'promotion';
        });

        $campaign = BroadcastCampaign::query()->with(['articles', 'recipients'])->firstOrFail();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame(0, $campaign->skipped_count);
        $this->assertCount(2, $campaign->articles);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'broadcast_sent',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_skips_missing_zalo_user_id_and_opted_out_contacts(): void
    {
        Mail::fake();

        $sales = $this->user(UserRole::Sales);

        // Khách 1: Có email nhưng chưa có zalo_user_id
        $customer1 = $this->customer($sales, 'Công ty Chỉ Có Email');
        $opp1 = $this->opportunity($sales, $customer1, 'buyer@onlyemail.vn');

        // Khách 2: Liên hệ đã marketing_opt_out = true
        $customer2 = $this->customer($sales, 'Công ty Từ Chối Nhận Tin');
        $customer2->contacts()->create([
            'full_name' => 'Lê Thị B',
            'email' => 'optout@company.vn',
            'zalo_user_id' => 'zalo-uid-optout',
            'is_primary' => true,
            'marketing_opt_out' => true,
        ]);
        $opp2 = $this->opportunity($sales, $customer2, 'optout@company.vn');

        $response = $this->actingAs($sales)->post('/repurchase-reminders/broadcast', [
            'name' => 'Quảng cáo sản phẩm mới',
            'purpose' => 'promotion',
            'channels' => ['email', 'zalo'],
            'opportunity_ids' => [$opp1->id, $opp2->id],
            'articles' => [
                [
                    'title' => 'Khuyến mãi cuối năm',
                    'summary' => 'Ưu đãi thiết bị bảo hộ cho nhà máy.',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(RichMediaBroadcastMail::class, 1);

        $campaign = BroadcastCampaign::query()->firstOrFail();
        $this->assertSame('partial', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame(3, $campaign->skipped_count);
    }

    public function test_sales_cannot_broadcast_to_other_sales_opportunities(): void
    {
        Mail::fake();

        $sales = $this->user(UserRole::Sales);
        $otherSales = $this->user(UserRole::Sales);
        $otherOpp = $this->opportunity($otherSales, $this->customer($otherSales, 'Khách của người khác'), 'other@company.vn');

        $response = $this->actingAs($sales)->post('/repurchase-reminders/broadcast', [
            'name' => 'Gửi nhầm khách',
            'purpose' => 'repurchase_reminder',
            'channels' => ['email'],
            'opportunity_ids' => [$otherOpp->id],
            'articles' => [
                ['title' => 'Bài viết nhắc mua'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, BroadcastCampaign::query()->count());
        Mail::assertNothingSent();
    }

    public function test_repurchase_reminders_page_exposes_broadcast_readiness_and_history(): void
    {
        Mail::fake();
        config()->set('services.zalo_oa.sandbox', true);

        $sales = $this->user(UserRole::Sales);
        $customer = $this->customer($sales);
        $customer->contacts()->create([
            'full_name' => 'Phạm Văn C',
            'email' => 'phamc@antoan.vn',
            'zalo_user_id' => 'zalo-uid-555',
            'is_primary' => true,
        ]);
        $opp = $this->opportunity($sales, $customer, 'phamc@antoan.vn');

        $this->actingAs($sales)->post('/repurchase-reminders/broadcast', [
            'name' => 'Test Sandbox Broadcast',
            'purpose' => 'repurchase_reminder',
            'channels' => ['email', 'zalo'],
            'opportunity_ids' => [$opp->id],
            'articles' => [
                ['title' => 'Nhắc bổ sung vật tư'],
            ],
        ])->assertRedirect();

        $this->actingAs($sales)->get('/repurchase-reminders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RepurchaseReminders/Index')
                ->where('broadcastConfig.zalo_sandbox', true)
                ->where('reminders.0.can_email_broadcast', true)
                ->where('reminders.0.can_zalo_broadcast', true)
                ->where('reminders.0.last_broadcast.campaign_name', 'Test Sandbox Broadcast')
                ->has('recentBroadcasts', 1)
                ->where('recentBroadcasts.0.sent_count', 2)
            );
    }
}
