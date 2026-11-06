<?php

namespace Tests\Feature;

use App\Enums\Staff\UserRole;
use App\Models\User;
use App\Services\Customer\SaveCustomer;
use App\Services\Database\CopySqliteData;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DatabaseImportTest extends TestCase
{
    use RefreshDatabase;

    private function target(): Connection
    {
        config(['database.connections.import_target' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('import_target');
        $this->artisan('migrate', ['--database' => 'import_target', '--no-interaction' => true])->assertSuccessful();

        return DB::connection('import_target');
    }

    public function test_copy_preserves_ids_relationships_and_source_data(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $customer = (new SaveCustomer)->handle(['name' => 'Imported customer'], $owner);
        $target = $this->target();

        $counts = (new CopySqliteData)->handle(DB::connection(), $target);

        $this->assertSame(1, $counts['customers']);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'owner_id' => $owner->id, 'name' => 'Imported customer'], 'import_target');
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'email' => $owner->email], 'import_target');
        $this->assertSame($owner->getRawOriginal('password'), $target->table('users')->value('password'));
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_owner_histories', 1, 'import_target');
        $this->assertDatabaseCount('audit_logs', 1, 'import_target');
    }

    public function test_dry_run_counts_rows_without_copying(): void
    {
        User::factory()->create();
        $target = $this->target();

        $counts = (new CopySqliteData)->handle(DB::connection(), $target, true);

        $this->assertSame(1, $counts['users']);
        $this->assertDatabaseCount('users', 0, 'import_target');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_nonempty_destination_is_refused_without_overwriting(): void
    {
        $owner = User::factory()->create();
        $target = $this->target();
        $target->table('users')->insert($owner->getAttributes());

        try {
            (new CopySqliteData)->handle(DB::connection(), $target);
            $this->fail('Import should refuse a nonempty destination.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Destination table users is not empty. Nothing was copied.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1, 'import_target');
        $this->assertDatabaseHas('users', ['email' => $owner->email], 'import_target');
    }

    public function test_failed_insert_rolls_back_previously_copied_tables(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        (new SaveCustomer)->handle(['name' => 'Same name'], $owner);
        (new SaveCustomer)->handle(['name' => 'Same name'], $owner);
        $target = $this->target();
        $target->statement('CREATE UNIQUE INDEX import_unique_customer_name ON customers (name)');

        try {
            (new CopySqliteData)->handle(DB::connection(), $target);
            $this->fail('The stricter destination constraint should reject the duplicate.');
        } catch (QueryException) {
            $this->assertDatabaseCount('users', 0, 'import_target');
            $this->assertDatabaseCount('customers', 0, 'import_target');
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_unknown_source_tables_are_refused_without_copying(): void
    {
        User::factory()->create();
        $target = $this->target();
        DB::statement('CREATE TABLE unexpected_data (id INTEGER PRIMARY KEY)');

        try {
            (new CopySqliteData)->handle(DB::connection(), $target);
            $this->fail('Unrecognized source tables must not be silently skipped.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unrecognized source tables: unexpected_data', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0, 'import_target');
    }

    public function test_missing_destination_schema_is_refused_before_inserting(): void
    {
        User::factory()->create();
        $target = $this->target();
        $target->getSchemaBuilder()->drop('customer_notes');

        try {
            (new CopySqliteData)->handle(DB::connection(), $target);
            $this->fail('Missing destination tables must prevent the import.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Missing table customer_notes. Run migrations on both databases first.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0, 'import_target');
    }
}
