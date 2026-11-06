<?php

namespace App\Console\Commands;

use App\Services\Database\CopySqliteData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

#[Signature('crm:copy-sqlite-to-mysql {--source= : Path to the SQLite backup} {--dry-run : Check and count without inserting data}')]
#[Description('Copy an SQLite backup into an empty, migrated MySQL database')]
class CopySqliteToMysql extends Command
{
    public function handle(CopySqliteData $copy): int
    {
        $path = realpath($this->option('source') ?: database_path('database.sqlite'));

        if ($path === false || ! is_file($path)) {
            $this->error('SQLite source file does not exist.');

            return self::FAILURE;
        }

        config(['database.connections.crm_import_source' => [
            'driver' => 'sqlite', 'database' => $path, 'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('crm_import_source');

        try {
            $source = DB::connection('crm_import_source');
            $target = DB::connection('mysql');

            if ($target->getDriverName() !== 'mysql') {
                throw new RuntimeException('The mysql connection must use the MySQL driver.');
            }

            $counts = $copy->handle($source, $target, (bool) $this->option('dry-run'));
            $this->table(['Table', 'Source rows'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all());
            $this->info($this->option('dry-run') ? 'Checks passed. No data was copied.' : 'Copy complete. SQLite source was preserved.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::disconnect('crm_import_source');
        }
    }
}
