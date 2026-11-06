<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MvcCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_page_and_relationship_include_legacy_and_current_audit_history(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $customer = (new SaveCustomer)->handle(['name' => 'MVC customer'], $owner);
        $legacy = AuditLog::create([
            'auditable_type' => 'App\\Domain\\Customer\\Models\\Customer',
            'auditable_id' => $customer->id,
            'event' => 'updated',
            'new_values' => ['name' => 'Legacy name'],
        ]);

        $this->actingAs($owner)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Customers/Show')
                ->where('customer.id', $customer->id)
                ->where('auditLogs.0.id', $legacy->id)
                ->has('auditLogs', 2));

        $this->assertCount(2, $customer->auditLogs);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Customer::class, 'auditable_id' => $customer->id]);
    }

    public function test_import_refuses_a_missing_source_without_connecting_to_mysql(): void
    {
        $this->artisan('crm:copy-sqlite-to-mysql', ['--source' => database_path('missing-backup.sqlite')])
            ->expectsOutput('SQLite source file does not exist.')
            ->assertFailed();
    }

    public function test_existing_react_pages_remain_accessible_after_model_moves(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        foreach (['dashboard' => 'Dashboard/Index', 'customers.index' => 'Customers/Index', 'leads.index' => 'Leads/Index', 'tasks.index' => 'Tasks/Index', 'workbench' => 'Workbench/Index'] as $route => $component) {
            $this->actingAs($owner)->get(route($route))->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }
}
