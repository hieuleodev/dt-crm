<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Lead;
use App\Models\SapoProduct;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_view_opportunities_page_and_create_opportunity(): void
    {
        $this->withoutVite();
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $customer = (new SaveCustomer)->handle(['name' => 'Công ty TNHH Điện tử Bắc Ninh'], $sales);

        $this->actingAs($sales)
            ->post(route('opportunities.store'), [
                'customer_id' => $customer->id,
                'title' => 'Cung cấp găng tay phòng sạch Q4',
                'contact_name' => 'Nguyễn Văn An',
                'stage' => 'proposal',
                'estimated_value' => '150000000',
                'expected_close_date' => '2026-12-15',
                'notes' => 'Cần gửi 5 đôi mẫu thử size M',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'owner_id' => $sales->id,
            'customer_id' => $customer->id,
            'kind' => 'opportunity',
            'title' => 'Cung cấp găng tay phòng sạch Q4',
            'company' => 'Công ty TNHH Điện tử Bắc Ninh',
            'stage' => 'proposal',
            'probability' => 30,
        ]);

        $this->actingAs($sales)
            ->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Opportunities/Index')
                ->has('opportunities.data', 1)
                ->where('summary.open_count', 1)
                ->where('summary.open_value', '150000000')
                ->where('summary.weighted_value', '45000000'));
    }

    public function test_marking_opportunity_as_lost_requires_lost_reason(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        $opp = new Lead([
            'title' => 'Đơn hàng giày bảo hộ KCN Quế Võ',
            'contact_name' => 'Trần Thị B',
            'company' => 'Công ty Cơ khí X',
            'stage' => 'negotiation',
            'estimated_value' => '80000000',
            'probability' => 70,
        ]);
        $opp->owner_id = $sales->id;
        $opp->kind = 'opportunity';
        $opp->save();

        $this->actingAs($sales)
            ->put(route('opportunities.update', $opp), [
                'title' => $opp->title,
                'contact_name' => $opp->contact_name,
                'stage' => 'lost',
                'estimated_value' => '80000000',
                'lost_reason' => '',
            ])
            ->assertSessionHasErrors(['lost_reason']);

        $this->actingAs($sales)
            ->put(route('opportunities.update', $opp), [
                'title' => $opp->title,
                'contact_name' => $opp->contact_name,
                'stage' => 'lost',
                'estimated_value' => '80000000',
                'lost_reason' => 'Giá cao hơn đối thủ',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'id' => $opp->id,
            'stage' => 'lost',
            'probability' => 0,
            'lost_reason' => 'Giá cao hơn đối thủ',
        ]);
    }

    public function test_converting_qualified_lead_sets_default_opportunity_probability(): void
    {
        $this->withoutVite();
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        $lead = new Lead([
            'title' => 'Tư vấn mặt nạ phòng độc',
            'contact_name' => 'Lê Văn C',
            'stage' => 'qualified',
            'estimated_value' => '50000000',
        ]);
        $lead->owner_id = $sales->id;
        $lead->kind = 'lead';
        $lead->save();

        $this->actingAs($sales)
            ->post(route('leads.convert', $lead))
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'kind' => 'opportunity',
            'stage' => 'proposal',
            'probability' => 30,
        ]);

        $customer = $lead->fresh()->customer;
        $this->assertNotNull($customer);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Lê Văn C',
            'owner_id' => $sales->id,
            'lifecycle_stage' => 'quoting',
        ]);

        $this->actingAs($sales)
            ->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("stageHistory.{$lead->id}", 1)
                ->where("stageHistory.{$lead->id}.0.from", 'qualified')
                ->where("stageHistory.{$lead->id}.0.to", 'proposal')
                ->where("stageHistory.{$lead->id}.0.by", $sales->name));

        $this->post(route('leads.convert', $lead))->assertSessionHas('error');
        $this->assertDatabaseCount('customers', 1);
        $this->get(route('opportunities.index'))
            ->assertInertia(fn ($page) => $page->has("stageHistory.{$lead->id}", 1));
    }

    public function test_lead_validates_contact_details_and_creates_a_linked_customer_when_converted(): void
    {
        $this->withoutVite();
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $payload = [
            'title' => 'Báo giá găng tay',
            'contact_name' => 'Mai Anh',
            'company' => 'Công ty Kiểm thử',
            'stage' => 'qualified',
            'estimated_value' => '500000',
            'notes' => 'Trao đổi qua email',
        ];

        $this->actingAs($sales)->post(route('leads.store'), [
            ...$payload,
            'phone' => 'a0912345678',
            'email' => 'sai-dinh-dang',
        ])->assertSessionHasErrors(['phone', 'email']);
        $this->assertDatabaseCount('leads', 0);

        $this->post(route('leads.store'), [
            ...$payload,
            'phone' => '+84 (912) 345-678',
            'email' => 'Mai.Anh@example.test',
        ])->assertSessionHasNoErrors();
        $lead = Lead::query()->sole();

        $this->put(route('leads.update', $lead), [
            ...$payload,
            'phone' => '123',
            'email' => 'khong-hop-le',
        ])->assertSessionHasErrors(['phone', 'email']);

        $this->post(route('leads.convert', $lead))->assertSessionHasNoErrors();
        $customer = $lead->fresh()->customer;
        $this->assertNotNull($customer);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => $payload['company'],
            'phone' => '+84912345678',
            'email' => 'mai.anh@example.test',
            'owner_id' => $sales->id,
            'lifecycle_stage' => 'quoting',
            'ppe_budget_estimate' => 500000,
            'notes' => $payload['notes'],
        ]);
        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'full_name' => $payload['contact_name'],
            'phone' => '+84912345678',
            'email' => 'mai.anh@example.test',
            'is_primary' => true,
        ]);
        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('opportunities.0.id', $lead->id));
    }

    public function test_lead_form_requires_email_and_allows_an_empty_estimated_value(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $payload = [
            'title' => 'Lead chưa có giá dự kiến',
            'contact_name' => 'Nguyễn Mai',
            'stage' => 'new',
            'estimated_value' => '',
        ];

        $this->actingAs($sales)->post(route('leads.store'), $payload)
            ->assertSessionHasErrors(['email' => 'Vui lòng nhập email.'])
            ->assertSessionDoesntHaveErrors('estimated_value');
        $this->assertDatabaseCount('leads', 0);

        $this->post(route('leads.store'), [...$payload, 'email' => 'mai@example.test'])
            ->assertSessionHasNoErrors();
        $lead = Lead::query()->sole();
        $this->assertSame('0.00', $lead->estimated_value);

        $this->put(route('leads.update', $lead), [...$payload, 'email' => ''])
            ->assertSessionHasErrors('email');
        $this->assertSame('mai@example.test', $lead->fresh()->email);
    }

    public function test_conversion_requires_correction_of_legacy_invalid_contact_details(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $lead = Lead::forceCreate([
            'owner_id' => $sales->id,
            'title' => 'Lead dữ liệu cũ',
            'contact_name' => 'Người liên hệ',
            'stage' => 'qualified',
            'phone' => 'a',
            'email' => 'legacy',
        ]);

        $this->actingAs($sales)->post(route('leads.convert', $lead))
            ->assertSessionHasErrors(['email', 'phone']);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'kind' => 'lead', 'customer_id' => null]);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_conversion_reuses_matching_customer_owned_by_the_lead_owner(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $otherSales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
        $saveCustomer = app(SaveCustomer::class);
        $saveCustomer->handle(['name' => 'Công ty Kiểm thử', 'email' => 'mai@example.test'], $otherSales);
        $customer = $saveCustomer->handle(['name' => 'Công ty Kiểm thử', 'lifecycle_stage' => 'loyal'], $sales);
        $customer->contacts()->create(['full_name' => 'Mai Anh', 'phone' => '0912345678']);
        $lead = Lead::forceCreate([
            'owner_id' => $sales->id,
            'title' => 'Nhu cầu mới',
            'contact_name' => 'Mai Anh',
            'company' => 'Công ty Kiểm thử',
            'stage' => 'qualified',
            'phone' => '+84 912 345 678',
            'email' => 'mai@example.test',
        ]);

        $this->actingAs($manager)->post(route('leads.convert', $lead))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'kind' => 'opportunity', 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'owner_id' => $sales->id, 'lifecycle_stage' => 'loyal']);
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_conversion_keeps_lead_unchanged_when_multiple_customers_match(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $saveCustomer = app(SaveCustomer::class);
        foreach (['Công ty A', 'Công ty B'] as $name) {
            $saveCustomer->handle(['name' => $name, 'phone' => '0912345678'], $sales);
        }
        $lead = Lead::forceCreate([
            'owner_id' => $sales->id,
            'title' => 'Lead cần đối chiếu',
            'contact_name' => 'Khách kiểm thử',
            'stage' => 'qualified',
            'phone' => '0912345678',
        ]);

        $this->actingAs($sales)->post(route('leads.convert', $lead))
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'kind' => 'lead', 'customer_id' => null]);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_lead_product_quantity_and_unit_price_set_opportunity_value_on_conversion(): void
    {
        $this->withoutVite();
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        SapoProduct::create([
            'sapo_product_id' => 100,
            'sapo_variant_id' => 200,
            'product_name' => 'Giày bảo hộ S3',
            'variant_name' => 'Default Title',
            'sku' => 'S3-01',
            'unit_price' => '1200.10',
            'synced_at' => now(),
        ]);
        $this->actingAs($sales)->post(route('leads.store'), [
            'title' => 'Nhu cầu giày bảo hộ',
            'sapo_variant_id' => '200',
            'contact_name' => 'Nguyễn Văn An',
            'email' => 'an@example.test',
            'stage' => 'qualified',
            'estimated_value' => '0',
        ])->assertSessionHasNoErrors();

        $lead = Lead::query()->where('title', 'Nhu cầu giày bảo hộ')->sole();

        $this->post(route('leads.convert', $lead))
            ->assertSessionHasErrors(['quantity', 'unit_price']);

        $this->post(route('leads.convert', $lead), [
            'quantity' => '2.1234',
            'unit_price' => '1200.101',
        ])->assertSessionHasErrors(['quantity', 'unit_price']);

        $this->post(route('leads.convert', $lead), [
            'quantity' => '2.5',
            'unit_price' => '1200.10',
        ])->assertSessionHasErrors(['quantity' => 'Số lượng phải là số nguyên dương.']);

        $this->post(route('leads.convert', $lead), [
            'quantity' => '0',
            'unit_price' => '1200.10',
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'kind' => 'lead']);

        $this->post(route('leads.convert', $lead), [
            'quantity' => '3',
            'unit_price' => '1200.10',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'kind' => 'opportunity',
            'requested_product' => 'Giày bảo hộ S3',
            'sapo_product_id' => 100,
            'sapo_variant_id' => 200,
            'requested_quantity' => '3.000',
            'requested_unit_price' => '1200.10',
            'estimated_value' => '3600.30',
        ]);
    }

    public function test_editing_an_opportunity_from_leads_preserves_its_quoted_product_price(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        SapoProduct::create([
            'sapo_product_id' => 100,
            'sapo_variant_id' => 200,
            'product_name' => 'Tên sản phẩm mới trong danh mục',
            'unit_price' => '1200.10',
            'synced_at' => now(),
        ]);
        $opportunity = Lead::forceCreate([
            'owner_id' => $sales->id,
            'title' => 'Cơ hội ban đầu',
            'contact_name' => 'Khách kiểm thử',
            'kind' => 'opportunity',
            'stage' => 'proposal',
            'sapo_product_id' => 100,
            'sapo_variant_id' => 200,
            'requested_product' => 'Tên sản phẩm đã báo giá',
            'requested_quantity' => '2.500',
            'requested_unit_price' => '1500.00',
            'estimated_value' => '3750.00',
        ]);

        $this->actingAs($sales)->put(route('leads.update', $opportunity), [
            'title' => 'Cơ hội đã chỉnh sửa',
            'contact_name' => $opportunity->contact_name,
            'email' => 'buyer@example.test',
            'stage' => 'proposal',
            'sapo_variant_id' => '200',
            'estimated_value' => '3750.00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'id' => $opportunity->id,
            'title' => 'Cơ hội đã chỉnh sửa',
            'requested_product' => 'Tên sản phẩm đã báo giá',
            'requested_quantity' => '2.500',
            'requested_unit_price' => '1500.00',
            'estimated_value' => '3750.00',
        ]);
    }

    public function test_stage_history_records_times_and_actors_for_quick_and_form_updates(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-11 03:00:00', 'UTC'));
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $payload = ['title' => 'Stage history', 'contact_name' => 'Buyer', 'stage' => 'proposal', 'estimated_value' => '1000'];

        $this->actingAs($sales)->post(route('opportunities.store'), $payload)
            ->assertSessionHasNoErrors();
        $opportunity = Lead::query()->where('kind', 'opportunity')->sole();

        $this->travelTo(Carbon::parse('2026-11-11 04:00:00', 'UTC'));
        $this->patch(route('opportunities.stage', $opportunity), ['stage' => 'negotiation'])
            ->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-11-11 05:00:00', 'UTC'));
        $this->put(route('opportunities.update', $opportunity), [...$payload, 'stage' => 'won'])
            ->assertSessionHasNoErrors();
        $this->patch(route('opportunities.stage', $opportunity), ['stage' => 'won'])
            ->assertSessionHasNoErrors();
        $this->put(route('opportunities.update', $opportunity), [...$payload, 'title' => 'Renamed', 'stage' => 'won'])
            ->assertSessionHasNoErrors();
        $this->patch(route('opportunities.stage', $opportunity), ['stage' => 'lost'])
            ->assertSessionHasErrors('lost_reason');

        $this->assertDatabaseHas('leads', ['id' => $opportunity->id, 'stage' => 'won']);
        $this->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("stageHistory.{$opportunity->id}", 3)
                ->where("stageHistory.{$opportunity->id}.0.from", 'negotiation')
                ->where("stageHistory.{$opportunity->id}.0.to", 'won')
                ->where("stageHistory.{$opportunity->id}.0.by", $sales->name)
                ->where("stageHistory.{$opportunity->id}.0.at", '2026-11-11T05:00:00+00:00')
                ->where("stageHistory.{$opportunity->id}.1.from", 'proposal')
                ->where("stageHistory.{$opportunity->id}.1.to", 'negotiation')
                ->where("stageHistory.{$opportunity->id}.1.at", '2026-11-11T04:00:00+00:00')
                ->where("stageHistory.{$opportunity->id}.2.from", null)
                ->where("stageHistory.{$opportunity->id}.2.to", 'proposal')
                ->where("stageHistory.{$opportunity->id}.2.at", '2026-11-11T03:00:00+00:00'));

        $this->patch(route('opportunities.stage', $opportunity), ['stage' => 'lost', 'lost_reason' => 'Budget cancelled'])
            ->assertSessionHasNoErrors();
        $this->get(route('opportunities.index'))
            ->assertInertia(fn ($page) => $page
                ->has("stageHistory.{$opportunity->id}", 4)
                ->where("stageHistory.{$opportunity->id}.0.reason", 'Budget cancelled'));
    }

    public function test_sales_cannot_see_another_owners_stage_history(): void
    {
        $this->withoutVite();
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $otherSales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $manager = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->actingAs($otherSales)->post(route('opportunities.store'), [
            'title' => 'Private opportunity', 'contact_name' => 'Buyer', 'stage' => 'proposal', 'estimated_value' => '1000',
        ])->assertSessionHasNoErrors();
        $opportunity = Lead::query()->where('kind', 'opportunity')->sole();

        $this->actingAs($sales)->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('stageHistory', 0));
        $this->patch(route('opportunities.stage', $opportunity), ['stage' => 'won'])->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $opportunity->id, 'stage' => 'proposal']);

        $this->actingAs($manager)->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("stageHistory.{$opportunity->id}", 1)
                ->where("stageHistory.{$opportunity->id}.0.by", $otherSales->name));
    }

    public function test_kanban_stage_transition_and_convert_opportunity_to_customer(): void
    {
        $this->withoutVite();
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        $opp = new Lead([
            'title' => 'Cung cấp kính và nút tai chống ồn',
            'contact_name' => 'Phạm Hoàng Nam',
            'company' => 'Công ty Thép Hòa Phát Hải Dương',
            'phone' => '0988123456',
            'email' => 'nam.ph@hoaphat.test',
            'stage' => 'proposal',
            'estimated_value' => '220000000',
            'probability' => 30,
        ]);
        $opp->owner_id = $sales->id;
        $opp->kind = 'opportunity';
        $opp->save();

        $this->actingAs($sales)
            ->patch(route('opportunities.stage', $opp), [
                'stage' => 'won',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'id' => $opp->id,
            'stage' => 'won',
            'probability' => 100,
        ]);

        $this->actingAs($sales)
            ->post(route('opportunities.convert-customer', $opp))
            ->assertRedirect();

        $opp->refresh();
        $this->assertNotNull($opp->customer_id);

        $this->assertDatabaseHas('customers', [
            'id' => $opp->customer_id,
            'name' => 'Công ty Thép Hòa Phát Hải Dương',
            'lifecycle_stage' => 'new_customer',
        ]);

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $opp->customer_id,
            'full_name' => 'Phạm Hoàng Nam',
            'is_primary' => true,
        ]);

        $this->actingAs($sales)
            ->get(route('customers.show', $opp->customer_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Show')
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $opp->id));
    }

    public function test_manager_can_assign_owner_delete_opportunity_and_audit_log_is_recorded(): void
    {
        $this->withoutVite();
        $manager = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $customer = (new SaveCustomer)->handle(['name' => 'Công ty Điện tử Canon'], $sales);

        $this->actingAs($manager)
            ->post(route('opportunities.store'), [
                'owner_id' => $sales->id,
                'customer_id' => $customer->id,
                'title' => 'Đơn hàng khẩu trang phòng sạch',
                'contact_name' => 'Đặng Thu Hà',
                'stage' => 'negotiation',
                'estimated_value' => '120000000',
            ])
            ->assertRedirect();

        $opp = Lead::query()->where('title', 'Đơn hàng khẩu trang phòng sạch')->firstOrFail();
        $this->assertSame($sales->id, $opp->owner_id);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Lead::class,
            'auditable_id' => $opp->id,
            'event' => 'created',
        ]);

        $this->actingAs($sales)
            ->delete(route('opportunities.destroy', $opp))
            ->assertRedirect();

        $this->assertDatabaseMissing('leads', [
            'id' => $opp->id,
        ]);
    }
}
