<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
