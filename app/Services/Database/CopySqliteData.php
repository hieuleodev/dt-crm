<?php

namespace App\Services\Database;

use Illuminate\Database\Connection;
use RuntimeException;

class CopySqliteData
{
    /**
     * Danh sách bảng theo thứ tự phụ thuộc khóa ngoại, gồm danh mục và kết nối Sapo.
     * Token OAuth được sao chép ở dạng đã mã hóa và cần APP_KEY tương ứng để đọc ở đích.
     *
     * @var list<string>
     */
    private const TABLES = [
        'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'company_settings', 'sapo_connections', 'leads', 'sapo_products', 'customers', 'plants',
        'contacts', 'tags', 'customer_tag', 'customer_owner_histories',
        'audit_logs', 'care_tasks', 'customer_notes',
    ];

    /** @return array<string, int> */
    public function handle(Connection $source, Connection $target, bool $dryRun = false): array
    {
        return $source->transaction(function () use ($source, $target, $dryRun): array {
            $unknown = array_diff($source->getSchemaBuilder()->getTableListing(schemaQualified: false), [...self::TABLES, 'migrations']);
            if ($unknown !== []) {
                throw new RuntimeException('Unrecognized source tables: '.implode(', ', $unknown));
            }

            $counts = [];
            foreach (self::TABLES as $table) {
                if (! $source->getSchemaBuilder()->hasTable($table) || ! $target->getSchemaBuilder()->hasTable($table)) {
                    throw new RuntimeException("Missing table {$table}. Run migrations on both databases first.");
                }
                if ($target->table($table)->exists()) {
                    throw new RuntimeException("Destination table {$table} is not empty. Nothing was copied.");
                }
                $counts[$table] = $source->table($table)->count();
            }

            if ($dryRun) {
                return $counts;
            }

            $target->transaction(function () use ($source, $target, $counts): void {
                foreach (self::TABLES as $table) {
                    $query = $source->table($table);
                    foreach ($source->getSchemaBuilder()->getColumnListing($table) as $column) {
                        $query->orderBy($column);
                    }
                    $query->chunk(100, function ($rows) use ($target, $table): void {
                        $target->table($table)->insert($rows->map(fn ($row) => (array) $row)->all());
                    });
                    if ($target->table($table)->count() !== $counts[$table]) {
                        throw new RuntimeException("Row count mismatch for {$table}; rolling back.");
                    }
                }
            });

            return $counts;
        });
    }
}
