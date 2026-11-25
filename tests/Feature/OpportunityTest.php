<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Lead;
use App\Models\SapoProduct;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestWith;
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

    public function test_customer_owner_can_convert_a_linked_lead_created_by_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $customer = app(SaveCustomer::class)->handle(['name' => 'Linked customer'], $sales);

        $this->actingAs($admin)->post(route('leads.store'), [
            'title' => 'Qualified request',
            'contact_name' => 'Buyer',
            'email' => 'buyer@example.test',
            'stage' => 'qualified',
            'customer_id' => $customer->id,
        ])->assertSessionHasNoErrors();
        $lead = Lead::sole();

        $this->actingAs($sales)->post(route('leads.convert', $lead))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'kind' => 'opportunity',
            'customer_id' => $customer->id,
            'owner_id' => $sales->id,
        ]);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_lead_customer_search_returns_contact_defaults_for_each_matching_customer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $saveCustomer = app(SaveCustomer::class);
        $company = $saveCustomer->handle([
            'name' => 'Acme Tools',
            'email' => 'office@example.test',
            'phone' => '0912345678',
        ], $admin);
        $company->contacts()->create(['full_name' => 'Secondary contact']);
        $company->contacts()->create([
            'full_name' => 'Former primary contact',
            'is_primary' => true,
            'left_at' => '2026-01-01',
        ]);
        $company->contacts()->create([
            'full_name' => 'Current primary contact',
            'email' => 'buyer@example.test',
            'is_primary' => true,
        ]);
        $customerWithoutContacts = $saveCustomer->handle([
            'name' => 'Acme Supplies',
            'email' => 'supplies@example.test',
        ], $admin);
        $saveCustomer->handle(['name' => 'Unrelated customer'], $admin);

        $this->actingAs($admin)->getJson(route('leads.customer-options', ['q' => 'Acme']))
            ->assertOk()
            ->assertExactJson(['options' => [
                [
                    'value' => $customerWithoutContacts->id,
                    'label' => "{$customerWithoutContacts->code} · Acme Supplies",
                    'details' => [
                        'contact_name' => '',
                        'company' => 'Acme Supplies',
                        'email' => 'supplies@example.test',
                        'phone' => '',
                    ],
                ],
                [
                    'value' => $company->id,
                    'label' => "{$company->code} · Acme Tools",
                    'details' => [
                        'contact_name' => 'Current primary contact',
                        'company' => 'Acme Tools',
                        'email' => 'buyer@example.test',
                        'phone' => '0912345678',
                    ],
                ],
            ]]);
    }

    public function test_sales_cannot_fetch_contact_defaults_for_lead_linking(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        $this->actingAs($sales)->getJson(route('leads.customer-options'))->assertForbidden();
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

    public function test_multi_product_lead_keeps_all_saved_lines_when_converted(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $customer = app(SaveCustomer::class)->handle(['name' => 'Existing buyer'], $sales);
        $gloves = SapoProduct::create([
            'sapo_product_id' => 100, 'sapo_variant_id' => 101, 'product_name' => 'Gloves',
            'variant_name' => 'Size M', 'sku' => 'GT-M', 'unit_price' => '1200.10', 'synced_at' => now(),
            'image_url' => 'https://catalog.example.test/gloves.jpg',
        ]);
        SapoProduct::create([
            'sapo_product_id' => 200, 'sapo_variant_id' => 201, 'product_name' => 'Safety glasses',
            'sku' => 'KG-01', 'unit_price' => '1800.25', 'synced_at' => now(),
        ]);
        $rows = [
            ['key' => '101', 'quantity' => '3', 'unit_price' => '1200.10'],
            ['key' => '201', 'quantity' => '2', 'unit_price' => '1800.25'],
        ];
        $details = [
            'title' => 'PPE request',
            'contact_name' => 'Buyer',
            'email' => 'buyer@example.test',
            'stage' => 'qualified',
        ];

        $this->actingAs($admin)->post(route('leads.store'), [
            ...$details,
            'customer_id' => $customer->id,
            'estimated_value' => '1',
            'products' => $rows,
        ])->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $this->assertSame('7200.80', $lead->estimated_value);
        $this->assertSame(['Gloves · Size M', 'Safety glasses'], array_column($lead->products, 'name'));
        $this->get(route('leads.index'))->assertInertia(fn ($page) => $page
            ->where('productImages.101', 'https://catalog.example.test/gloves.jpg')
            ->missing('productImages.201'));

        $gloves->update(['product_name' => 'Updated catalog name', 'unit_price' => '5000']);
        $this->actingAs($sales)->put(route('leads.update', $lead), [
            ...$details,
            'products' => $rows,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Gloves · Size M', $lead->refresh()->products[0]['name']);
        $this->assertSame('1200.10', $lead->products[0]['unit_price']);

        $this->post(route('leads.convert', $lead), ['products' => [$rows[0]]])
            ->assertSessionHasErrors('products');
        $this->assertSame('lead', $lead->refresh()->kind);

        $rows[1]['quantity'] = '4';
        $rows[1]['unit_price'] = '2000.25';
        $this->post(route('leads.convert', $lead), ['products' => $rows])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'kind' => 'opportunity',
            'customer_id' => $customer->id,
            'owner_id' => $sales->id,
            'estimated_value' => '11601.30',
        ]);
        $this->assertDatabaseCount('customers', 1);

        $this->get(route('opportunities.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('opportunities.data.0.products', 2)
            ->where('opportunities.data.0.products.1', [
                'key' => '201',
                'sapo_product_id' => '200',
                'name' => 'Safety glasses',
                'sku' => 'KG-01',
                'quantity' => '4.000',
                'unit_price' => '2000.25',
            ])
            ->where('summary.open_value', '11601.3'));
        $this->get(route('customers.show', $customer))->assertOk()->assertInertia(fn ($page) => $page
            ->has('opportunities.0.products', 2)
            ->where('opportunities.0.products.1.name', 'Safety glasses'));
    }

    public function test_sales_can_customize_product_names_and_prices_without_changing_the_sapo_catalog(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $product = SapoProduct::create([
            'sapo_product_id' => 100, 'sapo_variant_id' => 101,
            'product_name' => 'Găng tay Sapo', 'sku' => 'GT-01',
            'unit_price' => '1200.10', 'synced_at' => now(),
        ]);
        $details = [
            'title' => 'Đặt găng tay', 'contact_name' => 'Người mua',
            'email' => 'buyer@example.test', 'stage' => 'qualified',
        ];
        $row = ['key' => '101', 'name' => 'Găng tay theo yêu cầu', 'quantity' => '2', 'unit_price' => '1500.25'];

        $this->actingAs($sales)->post(route('leads.store'), [
            ...$details, 'products' => [$row],
        ])->assertSessionHasNoErrors();

        $lead = Lead::sole();
        $this->assertSame('Găng tay theo yêu cầu', $lead->products[0]['name']);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id, 'requested_product' => 'Găng tay theo yêu cầu',
            'requested_unit_price' => '1500.25', 'estimated_value' => '3000.50',
        ]);

        $row['name'] = 'Găng tay đã điều chỉnh';
        $row['unit_price'] = '1600.50';
        $this->put(route('leads.update', $lead), [
            ...$details, 'products' => [$row],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Găng tay đã điều chỉnh', $lead->refresh()->products[0]['name']);
        $this->assertSame('3201.00', $lead->estimated_value);

        $row['name'] = 'Găng tay chốt báo giá';
        $row['unit_price'] = '1700.75';
        $this->post(route('leads.convert', $lead), ['products' => [$row]])->assertSessionHasNoErrors();

        $this->assertSame('Găng tay chốt báo giá', $lead->refresh()->products[0]['name']);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id, 'kind' => 'opportunity', 'sapo_variant_id' => 101,
            'requested_product' => 'Găng tay chốt báo giá',
            'requested_unit_price' => '1700.75', 'estimated_value' => '3401.50',
        ]);
        $this->assertDatabaseHas('sapo_products', [
            'id' => $product->id, 'product_name' => 'Găng tay Sapo',
            'sku' => 'GT-01', 'unit_price' => '1200.10',
        ]);
    }

    #[TestWith(['', 'Vui lòng nhập tên sản phẩm.'])]
    #[TestWith([123, 'Tên sản phẩm không đúng định dạng.'])]
    public function test_product_lines_reject_invalid_custom_names(mixed $name, string $message): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        SapoProduct::create([
            'sapo_product_id' => 100, 'sapo_variant_id' => 101,
            'product_name' => 'Găng tay Sapo', 'unit_price' => '1200.10', 'synced_at' => now(),
        ]);

        $this->actingAs($sales)->post(route('leads.store'), [
            'title' => 'Đặt găng tay', 'contact_name' => 'Người mua',
            'email' => 'buyer@example.test', 'stage' => 'qualified',
            'products' => [['key' => '101', 'name' => $name, 'quantity' => '1', 'unit_price' => '1200.10']],
        ])->assertSessionHasErrors(['products.0.name' => $message]);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_product_lines_require_positive_integer_quantities(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        SapoProduct::create([
            'sapo_product_id' => 100, 'sapo_variant_id' => 101,
            'product_name' => 'Gloves', 'unit_price' => '1200.10', 'synced_at' => now(),
        ]);
        $details = [
            'title' => 'Quantity validation',
            'contact_name' => 'Buyer',
            'email' => 'buyer@example.test',
            'stage' => 'qualified',
        ];
        $row = ['key' => '101', 'quantity' => '2', 'unit_price' => '1200.10'];

        $this->actingAs($sales)->post(route('leads.store'), [
            ...$details,
            'products' => [[...$row, 'quantity' => '2.5']],
        ])->assertSessionHasErrors('products.0.quantity');
        $this->assertDatabaseCount('leads', 0);

        $this->post(route('leads.store'), [...$details, 'products' => [$row]])->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $this->put(route('leads.update', $lead), [
            ...$details,
            'products' => [[...$row, 'quantity' => '0']],
        ])->assertSessionHasErrors('products.0.quantity');
        $this->post(route('leads.convert', $lead), [
            'products' => [[...$row, 'quantity' => '-1']],
        ])->assertSessionHasErrors('products.0.quantity');
        $this->assertSame('lead', $lead->refresh()->kind);
        $this->assertSame('2.000', $lead->products[0]['quantity']);
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

    public function test_closed_opportunities_are_hidden_from_kanban_board_after_24_hours_but_remain_in_summary_and_history(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-20 08:00:00', 'UTC'));

        $sales = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        $wonOpp = new Lead([
            'title' => 'Đơn hàng mũ bảo hộ thành công',
            'contact_name' => 'Nguyễn Thành Công',
            'company' => 'Công ty Xây dựng A',
            'stage' => 'won',
            'estimated_value' => '100000000',
            'probability' => 100,
        ]);
        $wonOpp->owner_id = $sales->id;
        $wonOpp->kind = 'opportunity';
        $wonOpp->save();

        $lostOpp = new Lead([
            'title' => 'Đơn hàng áo phản quang thất bại',
            'contact_name' => 'Lê Thất Bại',
            'company' => 'Công ty Xây dựng B',
            'stage' => 'lost',
            'estimated_value' => '50000000',
            'probability' => 0,
            'lost_reason' => 'Giá cao hơn đối thủ',
        ]);
        $lostOpp->owner_id = $sales->id;
        $lostOpp->kind = 'opportunity';
        $lostOpp->save();

        $openOpp = new Lead([
            'title' => 'Đơn hàng đang đàm phán',
            'contact_name' => 'Trần Đàm Phán',
            'company' => 'Công ty Xây dựng C',
            'stage' => 'negotiation',
            'estimated_value' => '200000000',
            'probability' => 70,
        ]);
        $openOpp->owner_id = $sales->id;
        $openOpp->kind = 'opportunity';
        $openOpp->save();

        // Within 24 hours (23 hours elapsed): won and lost cards are still visible on Kanban board
        $this->travelTo(Carbon::parse('2026-11-21 07:00:00', 'UTC'));

        $this->actingAs($sales)
            ->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('board.won', 1)
                ->has('board.lost', 1)
                ->has('board.negotiation', 1)
                ->where('hiddenBoardCounts.won', 0)
                ->where('hiddenBoardCounts.lost', 0));

        // After 24 hours (25 hours elapsed): won and lost cards disappear from Kanban board, but open negotiation remains
        $this->travelTo(Carbon::parse('2026-11-21 09:00:00', 'UTC'));

        $this->actingAs($sales)
            ->get(route('opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('board.won', 0)
                ->has('board.lost', 0)
                ->has('board.negotiation', 1)
                ->where('hiddenBoardCounts.won', 1)
                ->where('hiddenBoardCounts.lost', 1)
                ->where('summary.won_value', '100000000')
                ->where('summary.by_stage.won.count', 1)
                ->where('summary.by_stage.lost.count', 1)
                ->has('opportunities.data', 3));

        // History tab still retains both closed opportunities
        $this->actingAs($sales)
            ->get(route('history.index', ['type' => 'opportunity']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('records.data', 2));

        // Optional Table filter hide_old_closed=1 hides won/lost opportunities older than 24h in Table view as well
        $this->actingAs($sales)
            ->get(route('opportunities.index', ['hide_old_closed' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.hide_old_closed', true)
                ->has('opportunities.data', 1)
                ->where('opportunities.data.0.id', $openOpp->id));
    }
}
