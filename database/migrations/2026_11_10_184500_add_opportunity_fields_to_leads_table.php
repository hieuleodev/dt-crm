<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('customers')
                ->nullOnDelete();

            $table->unsignedTinyInteger('probability')
                ->nullable()
                ->after('estimated_value');

            $table->date('expected_close_date')
                ->nullable()
                ->after('probability');

            $table->string('lost_reason', 255)
                ->nullable()
                ->after('expected_close_date');

            $table->index(['kind', 'expected_close_date']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['kind', 'expected_close_date']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['probability', 'expected_close_date', 'lost_reason']);
        });
    }
};
