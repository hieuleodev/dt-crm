<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title', 180);
            $table->string('contact_name', 120);
            $table->string('company', 180)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('kind', 20)->default('lead');
            $table->string('stage', 30)->default('new');

            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();

            $table->index(['owner_id', 'kind', 'stage']);
            $table->index(['kind', 'stage']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};